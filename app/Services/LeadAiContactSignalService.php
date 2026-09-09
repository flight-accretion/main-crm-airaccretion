<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadFollowup;

class LeadAiContactSignalService
{
    public function __construct(
        private LeadAiNoResponseService $noResponse
    ) {
    }

    public function build(
        Lead $lead,
        ?LeadFollowup $through = null
    ): array
    {
        $facts =
            $this->noResponse->facts(
                $lead,
                $through
            );

        $consecutiveNoResponse =
            (int) $facts['consecutive_no_response_attempts'];

        return [
            'customer_not_picked_up' =>
                (bool) optional($through)->customer_not_picked_up,

            'consecutive_no_response_attempts' =>
                $consecutiveNoResponse,

            'customer_ghosting_candidate' =>
                $consecutiveNoResponse >= 3,

            'last_contact_attempt_at' =>
                $facts['last_contact_attempt_at'],

            'last_meaningful_engagement_at' =>
                $facts['last_meaningful_engagement_at'],
        ];
    }
}
