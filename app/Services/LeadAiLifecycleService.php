<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;

class LeadAiLifecycleService
{
    private const ACTIVE_STATUS = 1;

    public function isBooked(
        Lead $lead
    ): bool {
        return $this->isBookedOrClosed($lead);
    }

    public function isBookedOrClosed(
        Lead $lead
    ): bool {
        $followupIds =
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->pluck('id');

        if ($followupIds->isEmpty()) {
            return false;
        }

        return PaymentAuditTrail::query()
            ->whereIn(
                'lead_followup_id',
                $followupIds
            )
            ->where('payment_status', 1)
            ->where('paid_amount', '>', 0)
            ->exists();
    }

    public function hasActiveStatus(
        Lead $lead
    ): bool {
        $latestFollowup =
            $lead->relationLoaded('latestFollowup')
                ? $lead->latestFollowup
                : $lead
                    ->leadFollowups()
                    ->orderByDesc('created_at')
                    ->first();

        return
            $latestFollowup
            && (int) $latestFollowup->status
                === self::ACTIVE_STATUS;
    }
}
