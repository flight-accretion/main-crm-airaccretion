<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;

class LeadAiContactSignalService
{
    public function __construct(
        private LeadAiScoringEligibilityService $eligibility
    ) {
    }

    public function build(
        Lead $lead,
        ?LeadFollowup $through = null
    ): array
    {
        $query = $lead->leadFollowups()
            ->with('followedBy.userType')
            ->orderByDesc('created_at');

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

        $followups =
            $query
                ->limit(30)
                ->get();

        $consecutiveNoResponse = 0;
        $lastAttemptAt = null;
        $lastMeaningfulEngagementAt = null;

        foreach ($followups as $followup) {
            if (!$this->eligibility->eligible($followup)) {
                continue;
            }

            if (
                $followup->contact_outcome
                === LeadFollowup::CONTACT_OUTCOME_NO_ANSWER
            ) {
                if ($lastAttemptAt === null) {
                    $lastAttemptAt = $followup->created_at;
                }

                $consecutiveNoResponse++;
                continue;
            }

            $lastMeaningfulEngagementAt = $followup->created_at;
            break;
        }

        return [
            'consecutive_no_response_attempts' =>
                $consecutiveNoResponse,

            'customer_ghosting_candidate' =>
                $consecutiveNoResponse >= 3,

            'last_contact_attempt_at' =>
                optional($lastAttemptAt)?->toIso8601String(),

            'last_meaningful_engagement_at' =>
                optional($lastMeaningfulEngagementAt)?->toIso8601String(),
        ];
    }
}
