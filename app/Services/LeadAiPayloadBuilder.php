<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadFollowup;
use Illuminate\Support\Str;

class LeadAiPayloadBuilder
{
    public function __construct(
        private LeadAiCurrentFactsService $facts,
        private LeadAiNoResponseService $noResponse,
        private LeadAiScoringEligibilityService $eligibility
    ) {
    }

    public function build(
        Lead $lead,
        LeadFollowup $followup,
        ?LeadAiScore $previous
    ): array {
        $payload = [
            'crm' =>
                $this->crmPayload(
                    $this->facts->build($lead)
                ),

            'contact' =>
                $this->contactPayload(
                    $lead,
                    $followup
                ),
        ];

        if ($previous) {
            $payload['previous'] = [
                'score' =>
                    (int) $previous->score,

                'reason' =>
                    (string) $previous->score_reason,

                'state' =>
                    $previous->state_json ?: [],
            ];

            $payload['interaction'] =
                $this->followupPayload($followup);
        } else {
            $payload['bootstrap_history'] =
                $this->bootstrapHistory(
                    $lead,
                    $followup
                );
        }

        return $payload;
    }

    private function crmPayload(
        array $facts
    ): array {
        return [
            'products' =>
                $facts['products'] ?? [],

            'services' =>
                $facts['services'] ?? [],

            'status' =>
                $facts['lead_status'] ?? null,

            'status_code' =>
                $facts['lead_status_code'] ?? null,

            'lead_source' =>
                $facts['lead_source'] ?? null,

            'email_source_lead' =>
                (bool) ($facts['email_source_lead'] ?? false),

            'pax' =>
                $facts['passenger_count'] ?? null,

            'occasion' =>
                $facts['occasion'] ?? null,

            'quoted' =>
                $facts['total_amount'] ?? null,

            'approved_received' =>
                $facts['approved_received_amount'] ?? null,

            'pending_amount' =>
                $facts['pending_amount'] ?? null,

            'service_date' =>
                $facts['service_date'] ?? null,

            'route' =>
                $facts['route'] ?? null,
        ];
    }

    private function contactPayload(
        Lead $lead,
        LeadFollowup $followup
    ): array {
        $facts =
            $this->noResponse->facts(
                $lead,
                $followup
            );

        return [
            'customer_not_picked_up' =>
                (bool) $followup->customer_not_picked_up,

            'consecutive_no_response_attempts' =>
                (int) $facts['consecutive_no_response_attempts'],

            'customer_ghosting_candidate' =>
                (int) $facts['consecutive_no_response_attempts'] >= 3,

            'last_contact_attempt_at' =>
                $facts['last_contact_attempt_at'],

            'last_meaningful_engagement_at' =>
                $facts['last_meaningful_engagement_at'],
        ];
    }

    private function bootstrapHistory(
        Lead $lead,
        LeadFollowup $current
    ): array {
        return $lead
            ->leadFollowups()
            ->with('followedBy.userType')
            ->where(
                'created_at',
                '<=',
                $current->created_at
            )
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->filter(
                fn (LeadFollowup $item) =>
                    $this->eligibility->eligible($item)
            )
            ->take(10)
            ->reverse()
            ->values()
            ->map(
                fn (LeadFollowup $item) =>
                    $this->followupPayload($item)
            )
            ->all();
    }

    private function followupPayload(
        LeadFollowup $followup
    ): array {
        return [
            'note' =>
                $this->sanitizeNote(
                    (string) $followup->followup_note
                ),

            'status' =>
                (int) $followup->status,

            'customer_not_picked_up' =>
                (bool) $followup->customer_not_picked_up,

            'contact_outcome' =>
                $followup->contact_outcome,

            'at' =>
                optional($followup->created_at)
                    ?->toIso8601String(),
        ];
    }

    private function sanitizeNote(
        string $note
    ): string {
        $lines =
            preg_split(
                '/\R/',
                $note
            ) ?: [];

        $lines = collect($lines)
            ->reject(function ($line) {
                return preg_match(
                    '/^\s*(phone|email|customer|name)\s*:/i',
                    (string) $line
                ) === 1;
            })
            ->values()
            ->all();

        $note =
            implode(
                PHP_EOL,
                $lines
            );

        $note = preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '[email removed]',
            $note
        );

        $note = preg_replace(
            '/(?<!\d)\+?\d{10,15}(?!\d)/',
            '[phone removed]',
            (string) $note
        );

        return Str::limit(
            trim((string) $note),
            600,
            ''
        );
    }
}
