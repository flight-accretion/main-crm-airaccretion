<?php

namespace App\Services\Kpi;

use App\Models\KpiOutreachAssignment;
use App\Models\KpiOutreachBatch;
use App\Models\KpiOutreachPool;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActiveLeadService;
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
            max(0, self::STANDARD_QUEUE_SIZE - $pending),
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
        return $this->standardCompletedToday($user) >= self::DAILY_STANDARD_TARGET;
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

    public function requestExtra(User $user): KpiOutreachBatch
    {
        if (!$this->canRequestExtra($user)) {
            throw ValidationException::withMessages([
                'extra' => 'Complete the daily 50 and any current extra batch before requesting another 50 numbers.',
            ]);
        }

        return DB::transaction(function () use ($user) {
            $batch = KpiOutreachBatch::create([
                'user_id' => $user->id,
                'batch_type' => 'extra',
                'requested_count' => self::EXTRA_BATCH_SIZE,
                'allocated_count' => 0,
                'requested_at' => now(),
            ]);

            $this->allocator->allocate(
                $user,
                self::EXTRA_BATCH_SIZE,
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
            throw ValidationException::withMessages([
                'outreach' => 'Daily standard 50 is complete. Use Get More Numbers for additional KPI work.',
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
}
