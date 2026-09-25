<?php

namespace App\Services\Operations;

use App\Models\LeadFollowup;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationsActivityService
{
    public function addFollowup(
        OperationCase $case,
        User $user,
        string $note,
        string $status = OperationCase::STATUS_IN_PROGRESS,
        $nextFollowupAt = null,
        bool $customerNotPickedUp = false
    ): LeadFollowup {
        return $this->recordFollowup($case, $user, [
            'note' => $note,
            'status' => $status,
            'next_followup_at' => $nextFollowupAt,
            'customer_not_picked_up' => $customerNotPickedUp,
        ]);
    }

    public function recordFollowup(
        OperationCase $case,
        User $user,
        array $data
    ): LeadFollowup {
        return DB::transaction(function () use ($case, $user, $data) {
            $case = OperationCase::query()
                ->lockForUpdate()
                ->findOrFail($case->id);

            $latestFollowup = LeadFollowup::query()
                ->where('lead_id', $case->lead_id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $note = trim((string) ($data['note'] ?? ''));
            $customerNotPickedUp = (bool) ($data['customer_not_picked_up'] ?? false);

            if ($customerNotPickedUp && $note === '') {
                $note = 'Customer did not pick up.';
            }

            $followup = LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'parent_followup_id' => $latestFollowup?->id,
                'operation_case_id' => $case->id,
                'lead_id' => $case->lead_id,
                'next_followup_date' => null,
                'followup_note' => $note,
                'status' => $latestFollowup
                    ? (int) $latestFollowup->status
                    : LeadFollowup::STATUS_ACTIVE,
                'followed_by' => $user->id,
                'contact_outcome' => $customerNotPickedUp
                    ? LeadFollowup::CONTACT_OUTCOME_NO_ANSWER
                    : null,
                'customer_not_picked_up' => $customerNotPickedUp,
            ]);

            $nextStatus = $this->statusFromData($data);
            $oldStatus = $case->status;
            $nextFollowupAt = $nextStatus === OperationCase::STATUS_COMPLETED
                ? null
                : $this->parseDate($data['next_followup_at'] ?? null);

            $caseUpdates = [
                'status' => $nextStatus,
                'assigned_to' => $case->assigned_to ?: $user->id,
                'next_followup_at' => $nextFollowupAt,
                'note' => $note,
            ];

            if ($nextStatus === OperationCase::STATUS_COMPLETED) {
                $caseUpdates['completed_by'] = $user->id;
                $caseUpdates['completed_at'] = now();
            } else {
                $caseUpdates['completed_by'] = null;
                $caseUpdates['completed_at'] = null;
            }

            $case->update($caseUpdates);

            OperationCaseActivity::create([
                'operation_case_id' => $case->id,
                'lead_id' => $case->lead_id,
                'user_id' => $user->id,
                'action' => 'followup_saved',
                'from_status' => $oldStatus,
                'to_status' => $nextStatus,
                'note' => $note,
                'metadata' => [
                    'lead_followup_id' => $followup->id,
                    'next_followup_at' => $nextFollowupAt?->toDateTimeString(),
                    'customer_not_picked_up' => $customerNotPickedUp,
                ],
            ]);

            return $followup->fresh();
        });
    }

    private function statusFromData(array $data): string
    {
        $status = (string) ($data['status'] ?? OperationCase::STATUS_IN_PROGRESS);

        return in_array($status, [
            OperationCase::STATUS_PENDING,
            OperationCase::STATUS_IN_PROGRESS,
            OperationCase::STATUS_COMPLETED,
        ], true)
            ? $status
            : OperationCase::STATUS_IN_PROGRESS;
    }

    private function parseDate($value): ?Carbon
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
