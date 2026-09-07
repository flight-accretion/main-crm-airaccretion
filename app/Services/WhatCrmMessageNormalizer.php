<?php

namespace App\Services;

use Carbon\Carbon;

class WhatCrmMessageNormalizer
{
    public function __construct(
        private ActiveLeadService $activeLeadService
    ) {
    }

    public function normalize(array $payload): array
    {
        $rawPhone =
            $payload['number']
            ?? $payload['phone']
            ?? $payload['from']
            ?? $payload['wa_id']
            ?? $payload['contact_number']
            ?? ($payload['messageObject']['to'] ?? null)
            ?? null;

        $direction = strtolower(
            trim((string) ($payload['direction'] ?? 'incoming'))
        );

        if ($direction !== 'outgoing') {
            $direction = 'incoming';
        }

        $body =
            $payload['message']
            ?? $payload['body']
            ?? $payload['text']
            ?? ($payload['message']['text']['body'] ?? null)
            ?? ($payload['messageObject']['text']['body'] ?? null);

        $customerName =
            $payload['customer_name']
            ?? $payload['name']
            ?? $payload['profile_name']
            ?? ($payload['contact']['name'] ?? null);

        return [
            'provider_message_id' =>
                $payload['message_id']
                ?? $payload['provider_message_id']
                ?? $payload['id']
                ?? $payload['wamid']
                ?? null,

            'whatcrm_chat_id' =>
                $payload['chat_id']
                ?? $payload['whatcrm_chat_id']
                ?? $payload['conversation_id']
                ?? null,

            'raw_phone' => $rawPhone,

            'normalized_phone' =>
                $this->activeLeadService
                    ->normalizePhone(
                        is_null($rawPhone)
                            ? null
                            : (string) $rawPhone
                    ),

            'customer_name' =>
                is_null($customerName)
                    ? null
                    : trim((string) $customerName),

            'body' =>
                is_null($body)
                    ? null
                    : trim((string) $body),

            'message_type' =>
                trim(
                    (string) (
                        $payload['message_type']
                        ?? ($payload['messageObject']['type'] ?? null)
                        ?? 'text'
                    )
                )
                    ?: 'text',

            'direction' => $direction,

            'message_at' =>
                $this->parseMessageAt(
                    $payload['message_at']
                    ?? $payload['timestamp']
                    ?? null
                ),

            'provider_status' =>
                $payload['status']
                ?? $payload['provider_status']
                ?? null,

            'agent_user_id' =>
                $payload['agent_user_id']
                ?? $payload['crm_user_id']
                ?? null,

            'lead_id' => $payload['lead_id'] ?? null,

            'agent_name' =>
                $payload['agent_name']
                ?? $payload['sender_name']
                ?? null,

            'whatcrm_agent_id' =>
                $payload['whatcrm_agent_id']
                ?? $payload['agent_id']
                ?? null,

            'product' => $payload['product'] ?? null,
            'service' =>
                $payload['service']
                ?? $payload['service_name']
                ?? null,
            'city' =>
                $payload['city']
                ?? $payload['service_city']
                ?? $payload['location']
                ?? null,
            'date' =>
                $payload['date']
                ?? $payload['service_date']
                ?? $payload['travel_date']
                ?? $payload['ride_date']
                ?? null,
            'service_date' =>
                $payload['service_date']
                ?? $payload['date']
                ?? null,
            'guest' =>
                $payload['guest']
                ?? $payload['guests']
                ?? $payload['passengers']
                ?? null,
            'route' =>
                $payload['route']
                ?? $payload['travel_route']
                ?? $payload['city_route']
                ?? null,
            'origin' =>
                $payload['origin']
                ?? $payload['from_place']
                ?? $payload['departure_city']
                ?? $payload['pickup_city']
                ?? null,
            'destination' =>
                $payload['destination']
                ?? $payload['to_place']
                ?? $payload['arrival_city']
                ?? $payload['drop_city']
                ?? null,
            'occasion' =>
                $payload['occasion']
                ?? $payload['ocassion']
                ?? null,

            'raw_payload' =>
                $payload['raw_payload']
                ?? $payload,
        ];
    }

    private function parseMessageAt($value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (empty($value)) {
            return now();
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $exception) {
            return now();
        }
    }
}
