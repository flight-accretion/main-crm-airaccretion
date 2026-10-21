<?php

namespace App\Services\Operations;

use App\Models\LeadRide;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleWindowService
{
    public const REMINDER_SOURCE = 'reschedule_window_reminder';
    public const ASSESSMENT_SOURCE = 'reschedule_expiry_assessment';

    public function process(): array
    {
        $counts = ['reminders' => 0, 'assessments' => 0, 'missing_dates' => 0];
        if (!config('operations.reschedule_window_automation', false)) return $counts;
        OperationCase::where('type', OperationCase::TYPE_RESCHEDULE)->whereNull('completed_at')
            ->where('metadata->reschedule->state', 'open')->orderBy('id')
            ->chunkById(100, function ($cases) use (&$counts) {
                foreach ($cases as $case) {
                    $result = $this->sync($case);
                    foreach ($counts as $key => $value) $counts[$key] += $result[$key];
                }
            });
        // A generic completion outside the editor may have closed a parent since the last run.
        OperationCase::whereIn('metadata->source', [self::REMINDER_SOURCE, self::ASSESSMENT_SOURCE])
            ->whereNull('completed_at')->orderBy('id')->chunkById(100, function ($tasks) {
                foreach ($tasks as $task) {
                    $parent = OperationCase::find(data_get($task->metadata, 'reschedule_case_id'));
                    if ($parent && $parent->completed_at) $this->sync($parent);
                }
            });
        return $counts;
    }

    public function sync(OperationCase $case): array
    {
        if (!config('operations.reschedule_window_automation', false)) {
            return ['reminders' => 0, 'assessments' => 0, 'missing_dates' => 0];
        }
        return DB::transaction(function () use ($case) {
            $case = OperationCase::whereKey($case->id)->lockForUpdate()->firstOrFail();
            $counts = ['reminders' => 0, 'assessments' => 0, 'missing_dates' => 0];
            $metadata = $case->metadata ?: [];
            $tracking = $metadata['reschedule'] ?? [];
            if ($case->completed_at || ($tracking['state'] ?? '') !== 'open') {
                $this->closeLinkedTasks($case);
                return $counts;
            }
            $ride = LeadRide::where('lead_id', $case->lead_id)->find($metadata['ride_id'] ?? null);
            if (!$ride || !$ride->is_tba || $ride->from_date) {
                // A date set elsewhere must not leave a stale call/refund-assessment task active.
                $this->closeLinkedTasks($case);
                return $counts;
            }
            $window = app(RescheduleTrackingService::class)->window(data_get($tracking, 'original.from_date'));
            if ($window['days_left'] === null) {
                $counts['missing_dates']++;
                return $counts;
            }
            $tracking['expires_on'] = $window['expires_on']->toDateString();
            if ($window['days_left'] < 0) {
                $this->closeTask($case, self::REMINDER_SOURCE);
                $task = $this->ensureTask($case, self::ASSESSMENT_SOURCE, OperationCase::TYPE_REFUND,
                    '90-day window lapsed. Assess refund eligibility; no refund or cancellation has been made.', $counts, 'assessments');
                $tracking['assessment_case_id'] = $task->id;
            } elseif ($window['days_left'] <= 10) {
                $task = $this->ensureTask($case, self::REMINDER_SOURCE, OperationCase::TYPE_CUSTOMER_CALL,
                    'Call customer to confirm a new date. Open reschedule expires '.$tracking['expires_on'].'.', $counts, 'reminders');
                $tracking['reminder_case_id'] = $task->id;
            }
            if ($tracking !== ($metadata['reschedule'] ?? [])) {
                $metadata['reschedule'] = $tracking;
                $case->update(['metadata' => $metadata]);
            }
            return $counts;
        });
    }

    private function ensureTask(OperationCase $parent, string $source, string $type, string $note, array &$counts, string $counter): OperationCase
    {
        $task = OperationCase::where('metadata->source', $source)->where('metadata->reschedule_case_id', $parent->id)->first();
        if ($task) return $task;
        $task = OperationCase::create([
            'lead_id' => $parent->lead_id, 'type' => $type, 'status' => OperationCase::STATUS_PENDING,
            'assigned_to' => $parent->assigned_to, 'opened_at' => now(), 'next_followup_at' => now(), 'note' => $note,
            'metadata' => ['source' => $source, 'reschedule_case_id' => $parent->id,
                'ride_id' => data_get($parent->metadata, 'ride_id'), 'assessment_only' => $source === self::ASSESSMENT_SOURCE],
        ]);
        $this->activity($task, 'opened', null, $note);
        $counts[$counter]++;
        return $task;
    }

    public function closeLinkedTasks(OperationCase $case): void
    {
        $this->closeTask($case, self::REMINDER_SOURCE);
        $this->closeTask($case, self::ASSESSMENT_SOURCE);
    }

    private function closeTask(OperationCase $parent, string $source): void
    {
        $tasks = OperationCase::where('metadata->source', $source)->where('metadata->reschedule_case_id', $parent->id)
            ->whereNull('completed_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($tasks as $task) {
            $metadata = $task->metadata ?: []; $metadata['superseded'] = true;
            $task->update(['status' => OperationCase::STATUS_COMPLETED, 'completed_at' => now(), 'completed_by' => null, 'next_followup_at' => null, 'metadata' => $metadata]);
            $this->activity($task, 'superseded', null, 'Linked reschedule changed; this task is no longer required.');
        }
    }

    public function assess(OperationCase $task, User $user, string $decision, string $note): void
    {
        abort_unless(config('operations.reschedule_window_automation', false), 404);
        if (!in_array($decision, ['refund_review_required', 'no_refund', 'more_information'], true) || trim($note) === '') {
            throw ValidationException::withMessages(['assessment' => 'A valid decision and assessment note are required.']);
        }
        $parentId = data_get($task->metadata, 'reschedule_case_id');
        DB::transaction(function () use ($task, $user, $decision, $note, $parentId) {
            // Parent-first locking matches the scheduler and date-save path.
            $parent = OperationCase::whereKey($parentId)->lockForUpdate()->firstOrFail();
            $task = OperationCase::whereKey($task->id)->lockForUpdate()->firstOrFail();
            if (data_get($task->metadata, 'source') !== self::ASSESSMENT_SOURCE || $task->completed_at || $parent->completed_at) {
                throw ValidationException::withMessages(['assessment' => 'This assessment is no longer open. Refresh the queue.']);
            }
            $canDecide = in_array($user->userType?->user_type, array_merge(UserType::ACCOUNTS_ROLES, UserType::ADMIN_ROLES), true);
            if ($decision === 'no_refund' && !$canDecide) abort(403);
            // A refund referral stays actionable until the existing refund workflow handles the money.
            $complete = $canDecide && $decision === 'no_refund';
            $metadata = $task->metadata ?: [];
            $metadata['assessment'] = ['decision' => $decision, 'recorded_at' => now()->toDateTimeString(), 'recorded_by' => $user->id];
            $task->update(['metadata' => $metadata, 'note' => $note, 'assigned_to' => $task->assigned_to ?: $user->id,
                'status' => $complete ? OperationCase::STATUS_COMPLETED : OperationCase::STATUS_IN_PROGRESS,
                'completed_at' => $complete ? now() : null, 'completed_by' => $complete ? $user->id : null,
                'next_followup_at' => $complete ? null : now()->addDay()]);
            $this->activity($task, $complete ? 'completed' : 'assessment_updated', $user->id, $note);
        });
    }

    private function activity(OperationCase $task, string $action, ?string $userId, string $note): void
    {
        OperationCaseActivity::create(['operation_case_id' => $task->id, 'lead_id' => $task->lead_id,
            'user_id' => $userId, 'action' => $action, 'to_status' => $task->status, 'note' => $note]);
    }
}
