<?php

namespace App\Services;

use App\Models\WhatsAppLeadIntegration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatCrmAssignmentWebhookService
{
    public function send(
        WhatsAppLeadIntegration $integration
    ): bool {
        $integration->loadMissing([
            'lead.representative',
            'product',
        ]);

        $lead = $integration->lead;

        if (
            !$lead
            || !$lead->representative
        ) {
            return false;
        }

        /*
         * Prevent duplicate successful callbacks.
         */
        if ($integration->callback_sent) {
            return true;
        }

        $url = config(
            'whatcrm.assignment_webhook'
        );

        if (!$url) {
            Log::warning(
                'WhatCRM callback URL not configured'
            );

            return false;
        }

        $integration->increment(
            'callback_attempts'
        );

        try {

            $response = Http::timeout(
                config('whatcrm.timeout', 10)
            )
                ->acceptJson()
                ->post(
                    $url,
                    [
                        'number' =>
                            $integration->phone,

                        'lead_id' =>
                            $lead->id,

                        'external_id' =>
                            $integration->external_id,

                        'service' =>
                            optional(
                                $integration->product
                            )->product,

                        'agent' =>
                            $lead->representative->name,

                        'agent_user_id' =>
                            $lead->representative_user_id,

                        'assigned' =>
                            true,
                    ]
                );

            if ($response->successful()) {

                $integration->update([
                    'callback_sent' => true,
                    'callback_error' => null,
                ]);

                return true;
            }

            $integration->update([
                'callback_error' =>
                    'HTTP '
                    . $response->status()
                    . ': '
                    . mb_substr(
                        $response->body(),
                        0,
                        2000
                    ),
            ]);

            return false;

        } catch (\Throwable $e) {

            $integration->update([
                'callback_error' =>
                    $e->getMessage(),
            ]);

            Log::error(
                'WhatCRM assignment callback failed',
                [
                    'lead_id' =>
                        $lead->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return false;
        }
    }
}