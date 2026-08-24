<?php

namespace App\Services;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WhatCrmMessageIngestionService
{
    public function __construct(
        private WhatCrmMessageNormalizer $normalizer,
        private WhatCrmAgentResolver $agentResolver,
        private WhatsAppLeadResolverService $leadResolver,
        private WhatsAppLeadFollowupService $followupService,
        private WhatsAppAiBufferService $aiBufferService
    ) {
    }

    public function process(array $payload): array
    {
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
                return $this->response(
                    true,
                    $duplicate->conversation,
                    $duplicate
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
                    return $this->response(
                        true,
                        $duplicate->conversation,
                        $duplicate
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
                    return $this->response(
                        true,
                        $duplicate->conversation,
                        $duplicate
                    );
                }
            }

            $conversation = WhatsAppConversation::query()
                ->where('contact_id', $contact->id)
                ->lockForUpdate()
                ->first();

            if (!$conversation) {
                $conversation = WhatsAppConversation::create([
                    'contact_id' => $contact->id,
                    'whatcrm_chat_id' => $data['whatcrm_chat_id'],
                    'status' => 'open',
                    'unread_count' => 0,
                ]);
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

            if ($data['direction'] === 'incoming') {
                $lead = $this->leadResolver
                    ->resolveForIncoming(
                        $contact,
                        $conversation,
                        $data
                    );

                if ($lead) {
                    $this->followupService
                        ->createForIncomingMessage(
                            $lead,
                            $message,
                            $data
                        );
                }

                $conversation->refresh();
                $conversation->unread_count =
                    (int) $conversation->unread_count + 1;

                $this->aiBufferService->queue(
                    $conversation,
                    $message
                );
            }

            $conversation->last_message = $data['body'];
            $conversation->last_message_at = $data['message_at'];
            $conversation->save();

            return $this->response(
                false,
                $conversation,
                $message
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

    private function response(
        bool $duplicate,
        ?WhatsAppConversation $conversation,
        WhatsAppMessage $message
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
        ];
    }
}
