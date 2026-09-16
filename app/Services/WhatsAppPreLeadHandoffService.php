<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsAppPreLeadHandoffService
{
    public function __construct(
        private WhatsAppAiEligibilityService $eligibility,
        private WhatsAppLeadResolverService $leadResolver,
        private LeadAllocationService $leadAllocationService
    ) {
    }

    public function handoff(
        WhatsAppConversation $conversation,
        array $data = [],
        string $reason = 'customer_requested_human',
        string $priority = 'P2',
        ?string $summary = null
    ): ?Lead {
        return DB::transaction(function () use (
            $conversation,
            $data,
            $reason,
            $priority,
            $summary
        ) {
            $locked = WhatsAppConversation::query()
                ->with('contact')
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->first();

            if (!$locked || !$locked->contact) {
                return null;
            }

            $activeLead = $this->eligibility->activeLead($locked);
            $lead = $activeLead ?: $this->leadResolver->resolveForIncoming(
                $locked->contact,
                $locked,
                $this->handoffData($locked, $data)
            );

            if (!$lead) {
                return null;
            }

            $this->markHandoff($locked, $lead, $reason, $priority, $summary);

            if (empty($lead->representative_user_id)) {
                $this->leadAllocationService->queueLead(
                    $lead,
                    $this->queueReason($reason)
                );
            } else {
                app(WhatCrmAssignmentCustomerMessageService::class)
                    ->sendForLeadId($lead->id);
            }

            return $lead;
        });
    }

    private function handoffData(
        WhatsAppConversation $conversation,
        array $data
    ): array {
        $conversation->loadMissing('contact');
        $latest = $this->latestIncomingMessage($conversation);
        $state = is_array($conversation->ai_state)
            ? $conversation->ai_state
            : [];

        return array_merge(
            [
                'normalized_phone' => optional($conversation->contact)->normalized_phone,
                'raw_phone' => optional($conversation->contact)->raw_phone,
                'customer_name' => optional($conversation->contact)->name,
                'body' => optional($latest)->body ?: $conversation->last_message,
                'message' => optional($latest)->body ?: $conversation->last_message,
                'message_at' => optional($latest)->message_at ?: now(),
                'whatcrm_chat_id' => $conversation->whatcrm_chat_id,
                'chat_id' => $conversation->whatcrm_chat_id,
                'service' => $state['product_name'] ?? $state['service_family'] ?? null,
                'date' => $state['date'] ?? null,
                'guest' => $state['passengers'] ?? null,
                'route' => $this->routeText($state),
                'origin' => $state['origin'] ?? null,
                'destination' => $state['destination'] ?? null,
                'city' => $state['city'] ?? null,
                'occasion' => $state['occasion'] ?? null,
            ],
            $data
        );
    }

    private function latestIncomingMessage(
        WhatsAppConversation $conversation
    ): ?WhatsAppMessage {
        return WhatsAppMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'incoming')
            ->orderByDesc('message_at')
            ->orderByDesc('created_at')
            ->first();
    }

    private function markHandoff(
        WhatsAppConversation $conversation,
        Lead $lead,
        string $reason,
        string $priority,
        ?string $summary
    ): void {
        $conversation->lead_id = $lead->id;
        $conversation->assigned_user_id = $lead->representative_user_id;

        if ($this->hasColumn('conversation_owner')) {
            $conversation->conversation_owner = 'HUMAN';
        }

        if ($this->hasColumn('human_handoff_at')) {
            $conversation->human_handoff_at = $conversation->human_handoff_at ?: now();
        }

        if ($this->hasColumn('handoff_reason')) {
            $conversation->handoff_reason = $reason;
        }

        if ($this->hasColumn('handoff_priority')) {
            $conversation->handoff_priority = $priority;
        }

        if ($summary && $this->hasColumn('human_summary')) {
            $conversation->human_summary = $summary;
        }

        $conversation->save();
    }

    private function queueReason(string $reason): string
    {
        return 'whatsapp_ai_handoff_' . $reason;
    }

    private function routeText(array $state): ?string
    {
        $origin = trim((string) ($state['origin'] ?? ''));
        $destination = trim((string) ($state['destination'] ?? ''));

        if ($origin !== '' && $destination !== '') {
            return $origin . ' to ' . $destination;
        }

        return null;
    }

    private function hasColumn(string $column): bool
    {
        return Schema::hasTable('whatsapp_conversations')
            && Schema::hasColumn('whatsapp_conversations', $column);
    }
}
