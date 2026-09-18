<?php

namespace App\Services\Kpi;

use App\Models\KpiOutreachAssignment;
use App\Models\KpiOutreachBatch;
use App\Models\KpiOutreachPool;
use App\Models\KpiUserAssignment;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActiveLeadService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KpiOutreachService
{
    public const STANDARD_QUEUE_SIZE = 50;
    public const DAILY_STANDARD_TARGET = 50;
    public const EXTRA_BATCH_SIZE = 50;
    public const COOLING_DAYS = 60;

    public function __construct(
        private KpiOutreachAllocator $allocator,
        private KpiOutreachCallVerifier $verifier,
        private ActiveLeadService $activeLeadService
    ) {}

  public function dailyTarget(User $user): int
{
    $assignment = KpiUserAssignment::query()
        ->with(['template.metrics'])
        ->where('user_id', $user->id)
        ->where('active', true)
        ->whereDate(
            'effective_from',
            '<=',
            now()->toDateString()
        )
        ->where(function ($query) {
            $query
                ->whereNull('effective_to')
                ->orWhereDate(
                    'effective_to',
                    '>=',
                    now()->toDateString()
                );
        })
        ->orderByDesc('effective_from')
        ->first();

    $metric = $assignment
        ?->template
        ?->metrics
        ?->first(function ($metric) {
            return $metric->active
                && $metric->code === 'daily_outreach';
        });

    return max(
        0,
        (int) (
            $metric?->target_value
            ?? 0
        )
    );
}

    public function standardQueueSize(): int
    {
        return max(
            1,
            (int) config('kpi.outreach.standard_queue_size', self::STANDARD_QUEUE_SIZE)
        );
    }

    public function extraBatchSize(): int
    {
        return max(
            1,
            (int) config('kpi.outreach.extra_batch_size', self::EXTRA_BATCH_SIZE)
        );
    }

    public function releaseAssignmentsThatNowHaveActiveLeads(User $user): int
    {
        $released = 0;

        KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->get()
            ->each(function (KpiOutreachAssignment $assignment) use (&$released) {
                if (!$this->activeLeadService->findByPhone($assignment->normalized_phone)) {
                    return;
                }

                $assignment->update([
                    'status' => 'released',
                    'active_phone_key' => null,
                    'completed_at' => null,
                ]);

                $released++;
            });

        return $released;
    }

    public function ensureStandardQueue(User $user): int
    {
        $pending = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('allocation_type', 'standard')
            ->where('status', 'pending')
            ->count();

        return $this->allocator->allocate(
            $user,
            max(0, $this->standardQueueSize() - $pending),
            'standard'
        );
    }

    public function standardCompletedToday(User $user): int
    {
        return KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('allocation_type', 'standard')
            ->where('status', 'completed')
            ->whereDate('completed_at', now()->toDateString())
            ->count();
    }

    public function standardActionsLocked(User $user): bool
    {
        $target = $this->dailyTarget($user);

        return $target > 0
            && $this->standardCompletedToday($user) >= $target;
    }

    public function canRequestExtra(User $user): bool
    {
        if (!$this->standardActionsLocked($user)) {
            return false;
        }

        return !KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('allocation_type', 'extra')
            ->where('status', 'pending')
            ->exists();
    }

    public function pendingAssignments(
        User $user,
        string $type,
        array $filters = []
    ): Collection {
        $query = KpiOutreachAssignment::with('pool')
            ->where('user_id', $user->id)
            ->where('allocation_type', $type)
            ->where('status', 'pending');

        $number = $this->normalizePhoneFilter($filters['number'] ?? null);

        if ($number !== null) {
            $query->where('normalized_phone', 'like', "%{$number}%");
        }

        if (!empty($filters['date'])) {
            $query->whereDate('assigned_at', $filters['date']);
        }

        return $query
            ->orderBy('assigned_at')
            ->get();
    }

    public function requestExtra(User $user): KpiOutreachBatch
    {
        if (!$this->canRequestExtra($user)) {
            $target = $this->dailyTarget($user);
            $extraSize = $this->extraBatchSize();

            throw ValidationException::withMessages([
                'extra' => "Complete the daily {$target} and any current extra batch before requesting another {$extraSize} numbers.",
            ]);
        }

        $extraSize = $this->extraBatchSize();

        return DB::transaction(function () use ($user, $extraSize) {
            $batch = KpiOutreachBatch::create([
                'user_id' => $user->id,
                'batch_type' => 'extra',
                'requested_count' => $extraSize,
                'allocated_count' => 0,
                'requested_at' => now(),
            ]);

            $this->allocator->allocate(
                $user,
                $extraSize,
                'extra',
                $batch
            );

            return $batch->fresh();
        });
    }

    public function completeDnp(
        KpiOutreachAssignment $assignment,
        User $user
    ): void {
        $this->assertActionAllowed($assignment, $user);

        $call = $this->verifier->noAnswerCall($assignment, $user);

        if (!$call) {
            throw ValidationException::withMessages([
                'dnp' => 'DNP cannot be completed until CRM verifies a matching outbound no-answer call from Skyrec/VI.',
            ]);
        }

        $this->complete($assignment, $user, 'dnp', null, null, $call->id);
    }

    public function completeRemark(
        KpiOutreachAssignment $assignment,
        User $user,
        string $remark
    ): void {
        $this->assertActionAllowed($assignment, $user);

        $remark = trim($remark);

        if ($remark === '') {
            throw ValidationException::withMessages([
                'remark' => 'Remark is required after a connected call.',
            ]);
        }

        $call = $this->verifier->connectedCall($assignment, $user);

        if (!$call) {
            throw ValidationException::withMessages([
                'remark' => 'Remark cannot be completed until CRM receives a matching connected outbound Skyrec summary.',
            ]);
        }

        $this->complete($assignment, $user, 'remark', $remark, $call->id, null);
    }

    public function completeWithLead(
        KpiOutreachAssignment $assignment,
        User $user,
        Lead $lead
    ): void {
        $this->assertOwnedPending($assignment, $user);

        DB::transaction(function () use ($assignment, $lead) {
            $assignment->update([
                'status' => 'completed',
                'completion_type' => 'lead',
                'created_lead_id' => $lead->id,
                'completed_at' => now(),
                'active_phone_key' => null,
            ]);
        });

        if ($assignment->allocation_type === 'standard') {
            $this->ensureStandardQueue($user);
        }

        $this->finishBatchIfNeeded($assignment);
    }

    private function complete(
        KpiOutreachAssignment $assignment,
        User $user,
        string $type,
        ?string $remark,
        ?string $summaryId,
        ?string $ivrId
    ): void {
        DB::transaction(function () use ($assignment, $type, $remark, $summaryId, $ivrId) {
            $assignment->update([
                'status' => 'completed',
                'completion_type' => $type,
                'remark' => $remark,
                'remark_expires_at' => now()->addDays(self::COOLING_DAYS),
                'call_summary_integration_id' => $summaryId,
                'ivr_call_log_id' => $ivrId,
                'completed_at' => now(),
                'active_phone_key' => null,
            ]);

            KpiOutreachPool::query()
                ->where('id', $assignment->pool_id)
                ->update([
                    'cooling_until' => now()->addDays(self::COOLING_DAYS),
                ]);
        });

        if ($assignment->allocation_type === 'standard') {
            $this->ensureStandardQueue($user);
        }

        $this->finishBatchIfNeeded($assignment);
    }

    private function finishBatchIfNeeded(KpiOutreachAssignment $assignment): void
    {
        if (!$assignment->batch_id) {
            return;
        }

        $pending = KpiOutreachAssignment::query()
            ->where('batch_id', $assignment->batch_id)
            ->where('status', 'pending')
            ->exists();

        if (!$pending) {
            KpiOutreachBatch::query()
                ->where('id', $assignment->batch_id)
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => now(),
                ]);
        }
    }

    private function assertActionAllowed(
        KpiOutreachAssignment $assignment,
        User $user
    ): void {
        $this->assertOwnedPending($assignment, $user);

        if (
            $assignment->allocation_type === 'standard'
            && $this->standardActionsLocked($user)
        ) {
            $target = $this->dailyTarget($user);

            throw ValidationException::withMessages([
                'outreach' => "Daily standard {$target} is complete. Use Get More Numbers for additional KPI work.",
            ]);
        }
    }

    private function assertOwnedPending(
        KpiOutreachAssignment $assignment,
        User $user
    ): void {
        if (
            $assignment->user_id !== $user->id
            || $assignment->status !== 'pending'
        ) {
            abort(403, 'This outreach record is not available to this user.');
        }
    }

    private function normalizePhoneFilter(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if ($digits === '') {
            return null;
        }

        return strlen($digits) > 10
            ? substr($digits, -10)
            : $digits;
    }
}
