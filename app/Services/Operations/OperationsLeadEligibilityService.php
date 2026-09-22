<?php

namespace App\Services\Operations;

use App\Models\Lead;
use App\Models\LeadFollowup;

class OperationsLeadEligibilityService
{
    public const ELIGIBLE_STATUSES = [
        LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
        LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED,
        LeadFollowup::STATUS_CONFIRMED,
        LeadFollowup::STATUS_RESCHEDULED,
    ];

    public function isEligible(Lead $lead): bool
    {
        $latest = $this->latestFollowup($lead);

        return $latest
            && in_array((int) $latest->status, self::ELIGIBLE_STATUSES, true);
    }

    public function latestFollowup(Lead $lead): ?LeadFollowup
    {
        if ($lead->relationLoaded('leadFollowups')) {
            return $lead->leadFollowups
                ->sortByDesc('created_at')
                ->first();
        }

        if ($lead->relationLoaded('latestFollowup')) {
            return $lead->latestFollowup;
        }

        return $lead->leadFollowups()
            ->orderByDesc('created_at')
            ->first();
    }

    public function applyEligibleLeadConstraint($query): void
    {
        $query->whereIn('leads.id', function ($subQuery) {
            $subQuery
                ->select('lead_followups.lead_id')
                ->from('lead_followups')
                ->whereIn('lead_followups.status', self::ELIGIBLE_STATUSES)
                ->whereRaw(
                    'lead_followups.created_at = (
                        select max(latest_operations_followups.created_at)
                        from lead_followups as latest_operations_followups
                        where latest_operations_followups.lead_id = lead_followups.lead_id
                    )'
                );
        });
    }

    public function statuses(): array
    {
        return self::ELIGIBLE_STATUSES;
    }
}
