<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Str;

class LeadAssignmentFollowupService
{
    public function syncForAssignment(
        Lead $lead,
        User $salesperson,
        ?Carbon $assignedAt = null
    ): LeadFollowup {
        $assignedAt = $assignedAt ?: now();

        $followup = $lead
            ->leadFollowups()
            ->whereNotIn(
                'status',
                [
                    LeadFollowup::STATUS_CANCELLED,
                    LeadFollowup::STATUS_CONFIRMED,
                    LeadFollowup::STATUS_REJECTED,
                ]
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($followup) {
            $useAssignmentTime = $this->shouldUseAssignmentTime(
                $followup,
                $assignedAt
            );

            $followup->followed_by = $salesperson->id;

            if ($useAssignmentTime) {
                $followup->next_followup_date = $assignedAt;
            }

            $followup->save();

            return $followup;
        }

        return LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => $assignedAt,
            'followup_note' => 'Lead assigned to ' . $salesperson->name . '.',
            'followed_by' => $salesperson->id,
            'status' => LeadFollowup::STATUS_ACTIVE,
        ]);
    }

    private function shouldUseAssignmentTime(
        LeadFollowup $followup,
        Carbon $assignedAt
    ): bool {
        if (!$followup->next_followup_date) {
            return true;
        }

        if (empty($followup->followed_by)) {
            return true;
        }

        if (
            $followup->created_at
            && $followup->created_at->greaterThanOrEqualTo(
                $assignedAt->copy()->subSecond()
            )
        ) {
            return true;
        }

        return $followup->next_followup_date->lessThanOrEqualTo(
            $assignedAt
        );
    }
}
