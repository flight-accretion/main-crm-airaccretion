<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OperationCaseService
{
    public function open(
        Lead $lead,
        string $type,
        array $metadata = [],
        ?string $createdBy = null
    ): OperationCase {
        if (!in_array($type, OperationCase::validTypes(), true)) {
            throw new \InvalidArgumentException('Invalid operation type.');
        }

        return DB::transaction(function () use ($lead, $type, $metadata, $createdBy) {
            $existing = OperationCase::query()
                ->where('lead_id', $lead->id)
                ->where('type', $type)
                ->whereNull('completed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $case = OperationCase::create([
                'lead_id' => $lead->id,
                'type' => $type,
                'status' => OperationCase::STATUS_PENDING,
                'created_by' => $createdBy,
                'opened_at' => now(),
                'metadata' => $metadata,
            ]);

            $this->activity($case, 'opened', null, OperationCase::STATUS_PENDING, $createdBy, null, $metadata);

            return $case;
        });
    }

    public function start(OperationCase $case, User $user): OperationCase
    {
        return DB::transaction(function () use ($case, $user) {
            $case = OperationCase::query()
                ->lockForUpdate()
                ->findOrFail($case->id);

            if ($case->completed_at) {
                return $case;
            }

            if ($case->status === OperationCase::STATUS_IN_PROGRESS
                && $case->assigned_to === $user->id) {
                return $case;
            }

            $old = $case->status;

            $case->update([
                'status' => OperationCase::STATUS_IN_PROGRESS,
                'assigned_to' => $user->id,
            ]);

            $this->activity(
                $case,
                'started',
                $old,
                OperationCase::STATUS_IN_PROGRESS,
                $user->id
            );

            return $case->fresh();
        });
    }

    public function complete(
        OperationCase $case,
        User $user,
        ?string $note = null
    ): OperationCase {
        return DB::transaction(function () use ($case, $user, $note) {
            $case = OperationCase::query()
                ->lockForUpdate()
                ->findOrFail($case->id);

            if ($case->completed_at) {
                return $case;
            }

            $old = $case->status;

            $case->update([
                'status' => OperationCase::STATUS_COMPLETED,
                'assigned_to' => $case->assigned_to ?: $user->id,
                'completed_by' => $user->id,
                'completed_at' => now(),
                'note' => $note,
            ]);

            $this->activity(
                $case,
                'completed',
                $old,
                OperationCase::STATUS_COMPLETED,
                $user->id,
                $note
            );

            return $case->fresh();
        });
    }

    private function activity(
        OperationCase $case,
        string $action,
        ?string $fromStatus = null,
        ?string $toStatus = null,
        ?string $userId = null,
        ?string $note = null,
        array $metadata = []
    ): OperationCaseActivity {
        return OperationCaseActivity::create([
            'operation_case_id' => $case->id,
            'lead_id' => $case->lead_id,
            'user_id' => $userId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'note' => $note,
            'metadata' => $metadata,
        ]);
    }
}
