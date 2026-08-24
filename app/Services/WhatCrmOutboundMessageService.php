<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class WhatCrmOutboundMessageService
{
    public function __construct(
        private WhatCrmMessageIngestionService $ingestionService
    ) {
    }

    public function sendText(array $data): array
    {
        return $this->sendMessage(
            array_merge(
                $data,
                [
                    'message_type' => 'text',
                ]
            )
        );
    }

    public function sendMessage(array $data): array
    {
        $body = trim(
            (string) (
                $data['message']
                ?? $data['body']
                ?? ''
            )
        );

        $messageType = $this->normalizeMessageType(
            $data['message_type'] ?? 'text'
        );

        $rawNumber = trim(
            (string) (
                $data['number']
                ?? $data['to']
                ?? ''
            )
        );

        if ($rawNumber === '') {
            throw new InvalidArgumentException(
                'Customer WhatsApp number is required.'
            );
        }

        if ($messageType === 'text' && $body === '') {
            throw new InvalidArgumentException(
                'Message body is required.'
            );
        }

        $apiUrl = trim(
            (string) config('whatcrm.send_message_url')
        );

        $apiToken = trim(
            (string) config('whatcrm.send_message_token')
        );

        if ($apiUrl === '') {
            throw new InvalidArgumentException(
                'WhatCRM send-message API URL is not configured.'
            );
        }

        if ($apiToken === '') {
            throw new InvalidArgumentException(
                'WhatCRM send-message API token is not configured.'
            );
        }

        $toNumber = $this->formatOutboundPhone($rawNumber);

        $payload = $this->messagePayload(
            $toNumber,
            $messageType,
            $body,
            $data
        );
        $storedBody = $this->storedBody(
            $messageType,
            $body,
            $data
        );

        $response = Http::timeout(
            (int) config('whatcrm.timeout', 10)
        )
            ->acceptJson()
            ->asJson()
            ->post(
                $this->urlWithToken($apiUrl, $apiToken),
                $payload
            );

        $result = $response->json();

        if (!is_array($result)) {
            $result = [
                'raw_response' => $response->body(),
            ];
        }

        $accepted =
            $response->successful()
            && ($result['success'] ?? true) !== false;

        $providerMessageId = $this->providerMessageId($result);
        $crmResult = null;

        if ($accepted) {
            $crmResult = $this->ingestionService->process([
                'message_id' => $providerMessageId,
                'chat_id' => $data['chat_id'] ?? null,
                'number' => $toNumber,
                'customer_name' =>
                    $data['name']
                    ?? $data['customer_name']
                    ?? null,
                'message' => $storedBody,
                'message_type' => $messageType,
                'direction' => 'outgoing',
                'message_at' => now()->toIso8601String(),
                'status' =>
                    data_get(
                        $result,
                        'metaResponse.messages.0.message_status'
                    )
                    ?? data_get($result, 'status')
                    ?? 'sent',
                'agent_user_id' =>
                    $data['agent_user_id']
                    ?? $data['crm_user_id']
                    ?? null,
                'raw_payload' => [
                    'crm_outbound' => true,
                    'whatcrm_request' => $payload,
                    'whatcrm_response' => $result,
                ],
            ]);
        }

        return [
            'success' => $accepted,
            'http_status' => $response->status(),
            'provider_message_id' => $providerMessageId,
            'conversation_id' => data_get(
                $crmResult,
                'conversation_id'
            ),
            'contact_id' => data_get($crmResult, 'contact_id'),
            'crm_message_id' => data_get($crmResult, 'message_id'),
            'duplicate' => (bool) data_get(
                $crmResult,
                'duplicate',
                false
            ),
            'whatcrm_response' => $result,
        ];
    }

    private function storedBody(
        string $messageType,
        string $body,
        array $data
    ): string {
        if ($messageType === 'text') {
            return $body;
        }

        $caption = trim(
            (string) (
                $data['caption']
                ?? $data['message']
                ?? $data['body']
                ?? ''
            )
        );

        if ($caption !== '') {
            return $caption;
        }

        if ($messageType === 'location') {
            return trim(
                (string) (
                    $data['name']
                    ?? data_get($data, 'location.name')
                    ?? 'Shared location'
                )
            );
        }

        if ($messageType === 'contacts') {
            return 'Shared contact';
        }

        return '[' . ucfirst($messageType) . ']';
    }

    private function messagePayload(
        string $toNumber,
        string $messageType,
        string $body,
        array $data
    ): array {
        $payload = [
            'messageObject' => [
                'messaging_product' => 'whatsapp',
                'to' => $toNumber,
                'type' => $messageType,
            ],
        ];

        $contentKey = $this->contentKey($messageType);
        $content = $this->contentForType(
            $messageType,
            $body,
            $data
        );

        $assignment = $this->assignmentMetadata($data);

        if (is_array($content) && !array_is_list($content)) {
            $content['pass'] = $assignment['pass'];
            $content['assigned'] = $assignment['assigned'];
        } else {
            $payload['messageObject']['pass'] = $assignment['pass'];
            $payload['messageObject']['assigned'] =
                $assignment['assigned'];
        }

        $payload['messageObject'][$contentKey] = $content;

        return $payload;
    }

    private function contentForType(
        string $messageType,
        string $body,
        array $data
    ) {
        if ($messageType === 'text') {
            return [
                'body' => $body,
            ];
        }

        if (
            in_array(
                $messageType,
                [
                    'image',
                    'video',
                    'audio',
                ],
                true
            )
        ) {
            $link = trim(
                (string) (
                    $data['media_url']
                    ?? $data['link']
                    ?? $data[$messageType . '_url']
                    ?? ''
                )
            );

            if ($link === '') {
                throw new InvalidArgumentException(
                    ucfirst($messageType) . ' URL is required.'
                );
            }

            $content = [
                'link' => $link,
            ];

            $caption = trim(
                (string) (
                    $data['caption']
                    ?? $body
                    ?? ''
                )
            );

            if ($caption !== '') {
                $content['caption'] = $caption;
            }

            return $content;
        }

        if ($messageType === 'contacts') {
            $contacts =
                $data['contacts']
                ?? $data['contact']
                ?? null;

            if (is_string($contacts)) {
                $decoded = json_decode($contacts, true);
                $contacts = is_array($decoded) ? $decoded : null;
            }

            if (!is_array($contacts) || empty($contacts)) {
                throw new InvalidArgumentException(
                    'Contact payload is required.'
                );
            }

            return array_is_list($contacts)
                ? $contacts
                : [$contacts];
        }

        if ($messageType === 'location') {
            $latitude =
                $data['latitude']
                ?? data_get($data, 'location.latitude');
            $longitude =
                $data['longitude']
                ?? data_get($data, 'location.longitude');

            if (
                !is_numeric($latitude)
                || !is_numeric($longitude)
            ) {
                throw new InvalidArgumentException(
                    'Location latitude and longitude are required.'
                );
            }

            $location = [
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ];

            foreach (
                [
                    'name',
                    'address',
                ]
                as $field
            ) {
                $value = trim(
                    (string) (
                        $data[$field]
                        ?? data_get($data, 'location.' . $field)
                        ?? ''
                    )
                );

                if ($value !== '') {
                    $location[$field] = $value;
                }
            }

            return $location;
        }

        throw new InvalidArgumentException(
            'Unsupported WhatsApp message type.'
        );
    }

    private function contentKey(string $messageType): string
    {
        return $messageType === 'contacts'
            ? 'contacts'
            : $messageType;
    }

    private function normalizeMessageType($messageType): string
    {
        $messageType = strtolower(
            trim((string) $messageType)
        );

        if ($messageType === '') {
            return 'text';
        }

        if ($messageType === 'contact') {
            return 'contacts';
        }

        return $messageType;
    }

    private function assignmentMetadata(array $data): array
    {
        $assigned = trim(
            (string) (
                $data['assigned']
                ?? $data['assigned_agent']
                ?? $data['agent_name']
                ?? ''
            )
        );

        if ($assigned === '') {
            $userId =
                $data['assigned_agent_user_id']
                ?? $data['agent_user_id']
                ?? $data['crm_user_id']
                ?? null;

            if ($userId) {
                $assigned = (string) optional(
                    User::query()
                        ->whereKey($userId)
                        ->first()
                )->name;
            }
        }

        return [
            'pass' => $assigned !== '' ? 'yes' : 'no',
            'assigned' => $assigned,
        ];
    }

    private function formatOutboundPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            throw new InvalidArgumentException(
                'Customer WhatsApp number is invalid.'
            );
        }

        if (strlen($digits) === 10) {
            $digits =
                trim(
                    (string) config(
                        'whatcrm.default_country_code',
                        '91'
                    ),
                    '+'
                )
                . $digits;
        }

        return '+' . $digits;
    }

    private function urlWithToken(
        string $url,
        string $token
    ): string {
        $separator =
            str_contains($url, '?')
                ? '&'
                : '?';

        return $url . $separator . 'token=' . urlencode($token);
    }

    private function providerMessageId(array $response): ?string
    {
        return data_get($response, 'metaResponse.messages.0.id')
            ?? data_get($response, 'messages.0.id')
            ?? data_get($response, 'message_id')
            ?? data_get($response, 'id');
    }
}
