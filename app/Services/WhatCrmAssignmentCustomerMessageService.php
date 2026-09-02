<?php

namespace App\Services;

use App\Models\Lead;
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

        return $this->sendForLead(
            $integration->lead,
            $integration
        );
    }

    public function sendForLeadId(string $leadId): bool
    {
        $lead = Lead::query()
            ->with([
                'client',
                'representative',
            ])
            ->find($leadId);

        if (!$lead) {
            return false;
        }

        return $this->sendForLead(
            $lead,
            $this->integrationForLead($lead)
        );
    }

    private function sendForLead(
        ?Lead $lead,
        ?WhatsAppLeadIntegration $integration = null
    ): bool {
        if (
            !config(
                'whatcrm.assignment_customer_message_enabled',
                true
            )
        ) {
            return false;
        }

        if (!$lead) {
            return false;
        }

        $lead->loadMissing([
            'client',
            'representative',
        ]);

        $representative = $lead->representative;

        if (!$representative) {
            return false;
        }

        if (
            $integration
            && $this->assignmentAlreadySent($integration)
        ) {
            return true;
        }

        $customerNumber = $this->customerNumber(
            $lead,
            $integration
        );
        $agentName = trim((string) $representative->name);
        $agentNumber = trim(
            (string) $representative->contact_number
        );

        if (
            $customerNumber === ''
            || $agentName === ''
            || $agentNumber === ''
        ) {
            if ($integration) {
                $this->storeError(
                    $integration,
                    'Lead assignment message requires customer number, agent name, and agent phone.'
                );
            }

            return false;
        }

        $templateName = trim(
            (string) config(
                'whatcrm.assignment_customer_template',
                'lead_qualified'
            )
        );

        try {
            $result = $this->outbound->sendTemplate([
                'number' => $customerNumber,
                'name' => optional($lead->client)->name,
                'template_name' => $templateName,
                'body_values' => [
                    $agentName,
                    $agentNumber,
                ],
                'rendered_body' =>
                    $this->templateBody(
                        $agentName,
                        $agentNumber
                    ),
                'chat_id' =>
                    $integration
                        ? (
                            data_get(
                                $integration->payload,
                                'chat_id'
                            )
                            ?: data_get(
                                $integration->payload,
                                'whatcrm_chat_id'
                            )
                        )
                        : null,
                'agent_user_id' => $representative->id,
                'assigned_agent_user_id' => $representative->id,
                'assigned_agent' => $representative->name,
                'lead_id' => $lead->id,
            ]);

            if (!($result['success'] ?? false)) {
                if ($integration) {
                    $this->storeError(
                        $integration,
                        'WhatCRM did not accept the assignment template message.'
                    );
                }

                return false;
            }

            if ($integration) {
                $this->storeSuccess($integration);
            }

            return true;
        } catch (\Throwable $exception) {
            if ($integration) {
                $this->storeError(
                    $integration,
                    $exception->getMessage()
                );
            }

            Log::warning(
                'WhatCRM assignment customer template failed',
                [
                    'integration_id' => optional($integration)->id,
                    'lead_id' => $lead->id,
                    'error' => $exception->getMessage(),
                ]
            );

            return false;
        }
    }

    private function integrationForLead(
        Lead $lead
    ): ?WhatsAppLeadIntegration {
        if (!Schema::hasTable('whatsapp_lead_integrations')) {
            return null;
        }

        return WhatsAppLeadIntegration::query()
            ->where('lead_id', $lead->id)
            ->orderByDesc('created_at')
            ->first();
    }

    private function assignmentAlreadySent(
        WhatsAppLeadIntegration $integration
    ): bool {
        if (!$this->hasTrackingColumn('assignment_message_sent_at')) {
            return false;
        }

        $fresh = WhatsAppLeadIntegration::query()
            ->whereKey($integration->id)
            ->first();

        return (bool) optional($fresh)
            ->assignment_message_sent_at;
    }

    private function customerNumber(
        Lead $lead,
        ?WhatsAppLeadIntegration $integration
    ): string {
        $integrationPhone = trim(
            (string) optional($integration)->phone
        );

        if ($integrationPhone !== '') {
            return $integrationPhone;
        }

        foreach (
            [
                'alternate_number',
                'contact_number',
            ]
            as $field
        ) {
            $value = trim(
                (string) optional($lead->client)->{$field}
            );

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function templateBody(
        string $agentName,
        string $agentNumber
    ): string {
        return sprintf(
            'Thank you for your enquiry with Accretion Aviation India\'s leading Private Plane Helicopter and Yacht rental company. Your enquiry is being handled by %s and their direct number is %s Accretion Aviation',
            $agentName,
            $agentNumber
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
