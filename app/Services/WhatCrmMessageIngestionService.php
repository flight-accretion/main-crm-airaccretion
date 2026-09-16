<?php

namespace App\Services;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppAiReplyBatch;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class WhatCrmMessageIngestionService
{
    public function __construct(
        private WhatCrmMessageNormalizer $normalizer,
        private WhatCrmAgentResolver $agentResolver,
        private WhatsAppLeadResolverService $leadResolver,
        private WhatsAppLeadFollowupService $followupService,
        private WhatsAppAiBufferService $aiBufferService,
        private WhatsAppAiEligibilityService $aiEligibility,
        private WhatsAppAiStateService $aiState,
        private WhatsAppDeterministicRouterService $deterministicRouter,
        private WhatsAppPreLeadHandoffService $handoffService
    ) {
    }

    public function process(array $payload): array
    {
        $this->aiBufferService->clearStatus();
        $data = $this->normalizer->normalize($payload);

        if (empty($data['normalized_phone'])) {
            throw new InvalidArgumentException(
                'A valid WhatsApp phone number is required.'
            );
        }

        if (!empty($data['provider_message_id'])) {
            $duplicate = WhatsAppMessage::query()
                ->where(
                    'provider_message_id',
                    $data['provider_message_id']
                )
                ->with('conversation')
                ->first();

            if ($duplicate) {
                $aiBatch = $this->queueAiIfNeeded(
                    $duplicate,
                    $duplicate->conversation
                );

                return $this->response(
                    true,
                    $duplicate->conversation,
                    $duplicate,
                    $aiBatch
                );
            }
        }

        return DB::transaction(function () use ($data) {
            if (!empty($data['provider_message_id'])) {
                $duplicate = WhatsAppMessage::query()
                    ->where(
                        'provider_message_id',
                        $data['provider_message_id']
                    )
                    ->with('conversation')
                    ->first();

                if ($duplicate) {
                    $aiBatch = $this->queueAiIfNeeded(
                        $duplicate,
                        $duplicate->conversation
                    );

                    return $this->response(
                        true,
                        $duplicate->conversation,
                        $duplicate,
                        $aiBatch
                    );
                }
            }

            $contact = $this->findOrCreateContact($data);

            $contact = WhatsAppContact::query()
                ->whereKey($contact->id)
                ->lockForUpdate()
                ->first();

            if (!empty($data['provider_message_id'])) {
                $duplicate = WhatsAppMessage::query()
                    ->where(
                        'provider_message_id',
                        $data['provider_message_id']
                    )
                    ->with('conversation')
                    ->first();

                if ($duplicate) {
                    $aiBatch = $this->queueAiIfNeeded(
                        $duplicate,
                        $duplicate->conversation
                    );

                    return $this->response(
                        true,
                        $duplicate->conversation,
                        $duplicate,
                        $aiBatch
                    );
                }
            }

            $conversation = WhatsAppConversation::query()
                ->where('contact_id', $contact->id)
                ->lockForUpdate()
                ->first();

            if (!$conversation) {
                $conversation = WhatsAppConversation::create(
                    $this->conversationCreateAttributes([
                    'contact_id' => $contact->id,
                    'whatcrm_chat_id' => $data['whatcrm_chat_id'],
                    'status' => 'open',
                    'unread_count' => 0,
                    ])
                );
            }

            $agent = $this->agentResolver->resolve(
                $data['agent_user_id'],
                $data['agent_name'],
                $data['whatcrm_agent_id']
            );

            if (
                $data['whatcrm_chat_id']
                && $conversation->whatcrm_chat_id !== $data['whatcrm_chat_id']
            ) {
                $conversation->whatcrm_chat_id = $data['whatcrm_chat_id'];
            }

            if (
                $data['direction'] === 'outgoing'
                && !empty($data['lead_id'])
            ) {
                $conversation->lead_id = $data['lead_id'];
            }

            if (
                $data['direction'] === 'outgoing'
                && $agent
            ) {
                $conversation->assigned_user_id = $agent->id;
            }

            $message = WhatsAppMessage::create([
                'conversation_id' => $conversation->id,
                'provider_message_id' => $data['provider_message_id'],
                'direction' => $data['direction'],
                'sender_type' => $data['direction'] === 'incoming'
                    ? 'customer'
                    : 'agent',
                'sender_user_id' => $data['direction'] === 'outgoing'
                    ? optional($agent)->id
                    : null,
                'message_type' => $data['message_type'],
                'body' => $data['body'],
                'provider_status' => $data['provider_status'],
                'message_at' => $data['message_at'],
                'raw_payload' => $data['raw_payload'],
            ]);

            $aiBatch = null;

            if ($data['direction'] === 'incoming') {
                $this->markCustomerActivity(
                    $conversation,
                    $message
                );

                $activeLead = $this->aiEligibility
                    ->activeLead($conversation);

                if ($activeLead) {
                    $this->aiEligibility->markHumanOwned(
                        $conversation,
                        $activeLead,
                        'active_lead_found'
                    );

                    $this->followupService
                        ->createForIncomingMessage(
                            $activeLead,
                            $message,
                            $data,
                            $conversation
                        );
                } else {
                    $this->markAiPreLead($conversation);

                    $routerResult = $this->deterministicRouter
                        ->route(
                            (string) $message->body,
                            $this->aiState->get($conversation)
                        );

                    if (!empty($routerResult['updates'])) {
                        $this->aiState->merge(
                            $conversation,
                            $routerResult['updates']
                        );
                    }

                    if ($routerResult['handoff'] ?? false) {
                        $this->handoffService->handoff(
                            $conversation,
                            $data,
                            $routerResult['reason']
                                ?? 'customer_requested_human',
                            $routerResult['priority']
                                ?? 'P2'
                        );
                    }
                }

                $conversation->refresh();
                $conversation->unread_count =
                    (int) $conversation->unread_count + 1;
                $conversation->save();

                if ($this->aiEligibility->canAiOwn($conversation)) {
                    $aiBatch = $this->queueAiIfNeeded(
                        $message,
                        $conversation
                    );
                }
            }

            $conversation->last_message = $data['body'];
            $conversation->last_message_at = $data['message_at'];
            $conversation->save();

            return $this->response(
                false,
                $conversation,
                $message,
                $aiBatch
            );
        });
    }

    private function findOrCreateContact(array $data): WhatsAppContact
    {
        $contact = WhatsAppContact::query()
            ->where(
                'normalized_phone',
                $data['normalized_phone']
            )
            ->first();

        if (!$contact) {
            return WhatsAppContact::create([
                'name' => $data['customer_name'],
                'normalized_phone' => $data['normalized_phone'],
                'raw_phone' => $data['raw_phone'],
            ]);
        }

        $dirty = false;

        if (
            $data['customer_name']
            && $contact->name !== $data['customer_name']
        ) {
            $contact->name = $data['customer_name'];
            $dirty = true;
        }

        if (
            $data['raw_phone']
            && $contact->raw_phone !== $data['raw_phone']
        ) {
            $contact->raw_phone = $data['raw_phone'];
            $dirty = true;
        }

        if ($dirty) {
            $contact->save();
        }

        return $contact;
    }

    private function conversationCreateAttributes(array $attributes): array
    {
        if ($this->hasConversationColumn('conversation_owner')) {
            $attributes['conversation_owner'] = 'AI';
        }

        if ($this->hasConversationColumn('activity_version')) {
            $attributes['activity_version'] = 0;
        }

        return $attributes;
    }

    private function markCustomerActivity(
        WhatsAppConversation $conversation,
        WhatsAppMessage $message
    ): void {
        $changed = false;
        $activityAt = $message->message_at ?: now();

        if ($this->hasConversationColumn('last_customer_message_at')) {
            $conversation->last_customer_message_at = $activityAt;
            $changed = true;
        }

        if ($this->hasConversationColumn('last_conversation_activity_at')) {
            $conversation->last_conversation_activity_at = $activityAt;
            $changed = true;
        }

        if ($this->hasConversationColumn('activity_version')) {
            $conversation->activity_version =
                (int) $conversation->activity_version + 1;
            $changed = true;
        }

        if ($changed) {
            $conversation->save();
        }
    }

    private function markAiPreLead(
        WhatsAppConversation $conversation
    ): void {
        $conversation->lead_id = null;
        $conversation->assigned_user_id = null;

        if ($this->hasConversationColumn('conversation_owner')) {
            $conversation->conversation_owner = 'AI';
        }

        $conversation->save();
    }

    private function response(
        bool $duplicate,
        ?WhatsAppConversation $conversation,
        WhatsAppMessage $message,
        ?WhatsAppAiReplyBatch $aiBatch = null
    ): array {
        return [
            'success' => true,
            'duplicate' => $duplicate,
            'message_id' => $message->id,
            'conversation_id' => optional($conversation)->id,
            'contact_id' => optional($conversation)->contact_id,
            'lead_id' => optional($conversation)->lead_id,
            'assigned_user_id' => optional($conversation)
                ->assigned_user_id,
            'ai_status' => $this->aiBufferService->lastStatus(),
            'ai_reply_batch_id' => optional($aiBatch)->id,
        ];
    }

    private function hasConversationColumn(string $column): bool
    {
        return Schema::hasTable('whatsapp_conversations')
            && Schema::hasColumn('whatsapp_conversations', $column);
    }

    private function queueAiIfNeeded(
        WhatsAppMessage $message,
        ?WhatsAppConversation $conversation = null
    ): ?WhatsAppAiReplyBatch {
        if (
            $message->direction !== 'incoming'
            || $message->ai_processed_at
        ) {
            return null;
        }

        $conversation = $conversation
            ?: $message->conversation
            ?: WhatsAppConversation::query()
                ->find($message->conversation_id);

        if (!$conversation) {
            return null;
        }

        return $this->aiBufferService->queue(
            $conversation,
            $message
        );
    }
}
