<?php

namespace App\Services;

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
        $body = trim(
            (string) (
                $data['message']
                ?? $data['body']
                ?? ''
            )
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

        if ($body === '') {
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

        $payload = [
            'messageObject' => [
                'messaging_product' => 'whatsapp',
                'to' => $toNumber,
                'type' => 'text',
                'text' => [
                    'body' => $body,
                ],
            ],
        ];

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
                'message' => $body,
                'message_type' => 'text',
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
