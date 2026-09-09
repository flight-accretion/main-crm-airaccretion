<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;

class LeadAiNoResponseService
{
    public function __construct(
        private LeadAiScoringEligibilityService $eligibility
    ) {
    }

    public function consecutiveNoAnswers(
        LeadFollowup $current
    ): int {
        $lead =
            $current->relationLoaded('enquiry')
                ? $current->enquiry
                : $current->enquiry()->first();

        if (!$lead) {
            return 0;
        }

        return $this->consecutiveNoResponseCount(
            $lead,
            $current
        );
    }

    public function consecutiveNoResponseCount(
        Lead $lead,
        ?LeadFollowup $through = null
    ): int {
        return $this->facts(
            $lead,
            $through
        )['consecutive_no_response_attempts'];
    }

    public function facts(
        Lead $lead,
        ?LeadFollowup $through = null
    ): array {
        if (
            $through
            && !$this->isStructuredNoAnswer($through)
        ) {
            return [
                'consecutive_no_response_attempts' => 0,
                'last_contact_attempt_at' => null,
                'last_meaningful_engagement_at' =>
                    optional($through->created_at)?->toIso8601String(),
            ];
        }

        $query =
            $lead
                ->leadFollowups()
                ->with('followedBy.userType')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

        if (
            $through
            && $through->created_at
        ) {
            $query->where(
                'created_at',
                '<=',
                $through->created_at
            );
        }

        $count = 0;
        $lastAttemptAt = null;
        $lastMeaningfulEngagementAt = null;

        foreach ($query->limit(30)->get() as $followup) {
            if (!$this->eligibility->eligible($followup)) {
                continue;
            }

            if ($this->isStructuredNoAnswer($followup)) {
                $lastAttemptAt =
                    $lastAttemptAt ?: $followup->created_at;

                $count++;
                continue;
            }

            $lastMeaningfulEngagementAt =
                $followup->created_at;

            break;
        }

        return [
            'consecutive_no_response_attempts' =>
                $count,

            'last_contact_attempt_at' =>
                optional($lastAttemptAt)?->toIso8601String(),

            'last_meaningful_engagement_at' =>
                optional($lastMeaningfulEngagementAt)?->toIso8601String(),
        ];
    }

    private function isStructuredNoAnswer(
        LeadFollowup $followup
    ): bool {
        return (bool) $followup->customer_not_picked_up;
    }
}
