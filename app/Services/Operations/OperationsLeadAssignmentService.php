<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\OperationsLeadAssignment;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OperationsLeadAssignmentService
{
    public function __construct(
        private OperationsLeadEligibilityService $eligibility
    ) {
    }

    public function assign(
        Lead $lead,
        User $operationsUser,
        ?User $assignedBy = null
    ): OperationsLeadAssignment {
        if (!$this->eligibility->isEligible($lead)) {
            throw new RuntimeException(
                'This lead is not currently eligible for Operations handling.'
            );
        }

        $operationsUser->loadMissing('userType');
        $role = optional($operationsUser->userType)->user_type;

        if (!in_array($role, UserType::OPERATIONS_ROLES, true)) {
            throw new RuntimeException('Selected user is not an Operations user.');
        }

        return DB::transaction(function () use ($lead, $operationsUser, $assignedBy) {
            $current = OperationsLeadAssignment::query()
                ->where('lead_id', $lead->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->latest('assigned_at')
                ->first();

            if (
                $current
                && (string) $current->operations_user_id === (string) $operationsUser->id
            ) {
                return $current;
            }

            $previousName = null;

            if ($current) {
                $current->loadMissing('operationsUser');
                $previousName = optional($current->operationsUser)->name;

                $current->update([
                    'is_active' => false,
                    'unassigned_at' => now(),
                ]);
            }

            $assignment = OperationsLeadAssignment::create([
                'lead_id' => $lead->id,
                'operations_user_id' => $operationsUser->id,
                'assigned_by' => $assignedBy?->id,
                'assigned_at' => now(),
                'unassigned_at' => null,
                'is_active' => true,
            ]);

            $latestFollowup = $this->eligibility->latestFollowup($lead);
            $status = $latestFollowup
                ? (int) $latestFollowup->status
                : LeadFollowup::STATUS_ACTIVE;

            $note = $previousName
                ? sprintf(
                    'Operations handling reassigned from %s to %s by %s.',
                    $previousName,
                    $operationsUser->name,
                    $assignedBy?->name ?? 'System'
                )
                : sprintf(
                    'Operations handling assigned to %s by %s.',
                    $operationsUser->name,
                    $assignedBy?->name ?? 'System'
                );

            LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'parent_followup_id' => $latestFollowup?->id,
                'followup_recording_id' => null,
                'next_followup_date' => null,
                'followup_note' => $note,
                'status' => $status,
                'followed_by' => $assignedBy?->id,
                'contact_outcome' => null,
                'customer_not_picked_up' => false,
            ]);

            return $assignment->fresh(['operationsUser', 'assignedBy']);
        });
    }
}
