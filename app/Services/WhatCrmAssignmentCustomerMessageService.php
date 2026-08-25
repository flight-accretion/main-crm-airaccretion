<?php

namespace App\Services;

use App\Models\User;
use App\Models\WhatsAppLeadIntegration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatCrmAssignmentCustomerMessageService
{
    public function __construct(
        private WhatCrmOutboundMessageService $outbound
    ) {
    }

    public function send(
        WhatsAppLeadIntegration $integration
    ): bool {
        $integration->loadMissing([
            'lead.client',
            'lead.representative',
        ]);

        $lead = $integration->lead;
        $representative = optional($lead)->representative;

        if (
            !$lead
            || !$representative
            || trim((string) $integration->phone) === ''
        ) {
            return false;
        }

        if (
            $this->hasTrackingColumn('assignment_message_sent_at')
            && $integration->assignment_message_sent_at
        ) {
            return true;
        }

        try {
            $result = $this->outbound->sendText([
                'number' => $integration->phone,
                'name' => optional($lead->client)->name,
                'message' =>
                    $this->messageForRepresentative($representative),
                'chat_id' =>
                    data_get($integration->payload, 'chat_id')
                    ?: data_get(
                        $integration->payload,
                        'whatcrm_chat_id'
                    ),
                'agent_user_id' => $representative->id,
                'assigned_agent_user_id' => $representative->id,
                'assigned_agent' => $representative->name,
            ]);

            if (!($result['success'] ?? false)) {
                $this->storeError(
                    $integration,
                    'WhatCRM did not accept the assignment message.'
                );

                return false;
            }

            $this->storeSuccess($integration);

            return true;
        } catch (\Throwable $exception) {
            $this->storeError(
                $integration,
                $exception->getMessage()
            );

            Log::warning(
                'WhatCRM assignment customer message failed',
                [
                    'integration_id' => $integration->id,
                    'lead_id' => $lead->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return false;
        }
    }

    private function messageForRepresentative(User $representative): string
    {
        $name = trim((string) $representative->name);
        $number = trim((string) $representative->contact_number);

        if ($number === '') {
            return sprintf(
                'Our representative %s will call you shortly.',
                $name
            );
        }

        return sprintf(
            'Our representative %s (%s) will call you shortly.',
            $name,
            $number
        );
    }

    private function storeSuccess(
        WhatsAppLeadIntegration $integration
    ): void {
        $updates = [];

        if ($this->hasTrackingColumn('assignment_message_sent_at')) {
            $updates['assignment_message_sent_at'] = now();
        }

        if ($this->hasTrackingColumn('assignment_message_error')) {
            $updates['assignment_message_error'] = null;
        }

        if (!empty($updates)) {
            $integration->update($updates);
        }
    }

    private function storeError(
        WhatsAppLeadIntegration $integration,
        string $message
    ): void {
        if (!$this->hasTrackingColumn('assignment_message_error')) {
            return;
        }

        $integration->update([
            'assignment_message_error' =>
                mb_substr($message, 0, 2000),
        ]);
    }

    private function hasTrackingColumn(string $column): bool
    {
        return Schema::hasColumn(
            'whatsapp_lead_integrations',
            $column
        );
    }
}
