<?php

namespace App\Services;

use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppOpenAiClient
{
    public function generateReply(
        WhatsAppAiAgentSetting $setting,
        WhatsAppConversation $conversation,
        Collection $messages,
        Collection $products,
        ?Collection $contextMessages = null
    ): array {
        $apiKey = $setting->apiKey();

        if (!$apiKey) {
            throw new RuntimeException(
                'OpenAI API key is not configured.'
            );
        }

        $model = trim((string) $setting->model)
            ?: WhatsAppAiAgentSetting::defaultModel();

        $response = Http::timeout(
            (int) config('whatcrm.timeout', 10)
        )
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->post(
                (string) config('whatcrm.openai_responses_url'),
                [
                    'model' => $model,
                    'instructions' =>
                        $setting->prompt
                        ?: WhatsAppAiAgentSetting::defaultPrompt(),
                    'input' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'input_text',
                                    'text' => $this->prompt(
                                        $conversation,
                                        $messages,
                                        $products,
                                        $contextMessages
                                    ),
                                ],
                            ],
                        ],
                    ],
                ]
            );

        if (!$response->successful()) {
            $message = data_get(
                $response->json(),
                'error.message'
            );

            throw new RuntimeException(
                'OpenAI request failed with HTTP '
                . $response->status()
                . ($message ? ': ' . $message : '')
            );
        }

        return $this->parseResponse(
            $this->responseText($response->json() ?: [])
        );
    }

    private function prompt(
        WhatsAppConversation $conversation,
        Collection $messages,
        Collection $products,
        ?Collection $contextMessages = null
    ): string {
        $contact = $conversation->contact;
        $contextMessages = $contextMessages ?: $messages;

        $lines = [
            'Customer name: ' . (optional($contact)->name ?: 'Unknown'),
            'Customer phone: '
                . (
                    optional($contact)->normalized_phone
                    ?: optional($contact)->raw_phone
                    ?: '-'
                ),
            'CRM products:',
        ];

        foreach ($products as $product) {
            $lines[] = '- ' . $product->product;
        }

        $lines[] = 'Conversation context, oldest to newest:';

        foreach ($contextMessages as $message) {
            $lines[] =
                '['
                . (
                    $message->message_at
                        ? $message->message_at->format('Y-m-d H:i:s')
                        : '-'
                )
                . '] '
                . strtoupper($message->direction)
                . ' '
                . ($message->message_type ?: 'text')
                . ': '
                . (
                    $message->body
                    ?: '[' . ($message->message_type ?: 'message') . ']'
                );
        }

        $lines[] = 'New customer messages to answer now:';

        foreach ($messages as $message) {
            $lines[] =
                '- '
                . (
                    $message->body
                    ?: '[' . ($message->message_type ?: 'message') . ']'
                );
        }

        $lines[] =
            'Return JSON only: {"reply":"message to customer","product":"matching product name or N/A"}';

        return implode(PHP_EOL, $lines);
    }

    private function responseText(array $payload): string
    {
        $outputText = data_get($payload, 'output_text');

        if (is_string($outputText) && trim($outputText) !== '') {
            return $outputText;
        }

        $parts = [];

        foreach ((array) data_get($payload, 'output', []) as $item) {
            foreach ((array) data_get($item, 'content', []) as $content) {
                $text = data_get($content, 'text');

                if (is_string($text) && trim($text) !== '') {
                    $parts[] = $text;
                }
            }
        }

        return trim(implode(PHP_EOL, $parts));
    }

    private function parseResponse(string $text): array
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?/i', '', $text);
        $text = preg_replace('/```$/', '', trim((string) $text));
        $decoded = json_decode(trim((string) $text), true);

        if (!is_array($decoded)) {
            $decoded = [
                'reply' => trim($text),
                'product' => 'N/A',
            ];
        }

        $reply = trim((string) ($decoded['reply'] ?? ''));

        if ($reply === '') {
            throw new RuntimeException(
                'OpenAI response did not include a reply.'
            );
        }

        $product = trim(
            (string) (
                $decoded['product']
                ?? $decoded['service']
                ?? 'N/A'
            )
        );

        return [
            'reply' => $reply,
            'product' => $product === '' ? 'N/A' : $product,
        ];
    }
}
