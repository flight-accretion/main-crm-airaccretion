<?php

namespace App\Services;

use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppOpenAiClient
{
    public function __construct(
        private WhatsAppAiRuntimeDataService $runtimeData
    ) {
    }

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
                    'instructions' => $this->instructions(
                        $setting,
                        $conversation,
                        $messages,
                        $products,
                        $contextMessages
                    ),
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
            'Return JSON only: {"reply":"message to customer","product":"matching CRM product name or N/A","service":"matching CRM service/package name or N/A","service_date":"DD-MMM-YYYY or N/A","guests":"number or N/A","route":"Origin to Destination or N/A","origin":"origin city or N/A","destination":"destination city or N/A","occasion":"occasion or N/A"}';

        return implode(PHP_EOL, $lines);
    }

    private function instructions(
        WhatsAppAiAgentSetting $setting,
        WhatsAppConversation $conversation,
        Collection $messages,
        Collection $products,
        ?Collection $contextMessages = null
    ): string {
        $instructions = trim(
            (string) (
                $setting->prompt
                ?: WhatsAppAiAgentSetting::defaultPrompt()
            )
        );

        $runtimeData = $this->runtimeData->build(
            $conversation,
            $messages,
            $products,
            $contextMessages
        );
        $customerNumber = $runtimeData['CRM_CUSTOMER_NUMBER']
            ?? $this->customerNumber($conversation);
        $currentIst = now('Asia/Kolkata');
        $currentDate = $currentIst->format('d F Y');
        $currentDateTime = $currentIst->format('d F Y h:i A') . ' IST';

        $instructions = preg_replace(
            '/\{\{\s*\$\([\'"]Webhook[\'"]\)\.item\.json\.body\.number\s*\}\}/',
            $customerNumber,
            $instructions
        );

        $instructions = preg_replace(
            '/\{\{\s*\$now\\\\?\.format\([\'"]dd MMMM yyyy[\'"]\)\s*\}\}/',
            $currentDateTime,
            $instructions
        );

        foreach ($runtimeData as $placeholder => $value) {
            $instructions = preg_replace(
                '/\{\{\s*' . preg_quote($placeholder, '/') . '\s*\}\}/',
                $value,
                $instructions
            );
        }

        $lines = [
            '',
            'CRM Runtime Data:',
            'Current CRM date: ' . $currentDate,
            'Current CRM date/time: ' . $currentDateTime,
            'Current CRM timestamp (IST): '
                . $runtimeData['CRM_CURRENT_DATETIME_IST'],
            'Customer number: ' . $customerNumber,
            'Customer name: ' . $runtimeData['CRM_CUSTOMER_NAME'],
            'Lead status: ' . $runtimeData['CRM_LEAD_STATUS'],
            'Previous service: ' . $runtimeData['CRM_PREVIOUS_SERVICE'],
            'Last booking date: ' . $runtimeData['CRM_LAST_BOOKING_DATE'],
            'Lead qualification state: ' . $runtimeData['CRM_LEAD_STATE'],
            'Missing qualification fields: '
                . $runtimeData['CRM_MISSING_FIELDS'],
            'CRM notes: ' . $runtimeData['CRM_NOTES'],
            'Assigned agent name: '
                . $runtimeData['CRM_ASSIGNED_AGENT_NAME'],
            'Assigned agent number: '
                . $runtimeData['CRM_ASSIGNED_AGENT_NUMBER'],
            'Available CRM products/services:',
        ];

        foreach (explode(PHP_EOL, $runtimeData['CRM_ACTIVE_PRODUCTS']) as $name) {
            $name = trim((string) $name);

            $lines[] = $name !== '' && $name !== 'Not provided by CRM'
                ? '- ' . $name
                : $name;
        }

        $lines[] = 'CRM service data: '
            . $runtimeData['CRM_SERVICE_DATA'];
        $lines[] = 'CRM service cities/routes: '
            . $runtimeData['CRM_SERVICE_LOCATIONS'];
        $lines[] = 'CRM approved pricing data: '
            . $runtimeData['CRM_PRICING_DATA'];
        $lines[] = 'CRM confirmed availability data: '
            . $runtimeData['CRM_AVAILABILITY_DATA'];
        $lines[] = 'CRM approved product link: '
            . $runtimeData['CRM_PRODUCT_LINK'];
        $lines[] = 'CRM approved selling facts: '
            . $runtimeData['CRM_APPROVED_SELLING_FACTS'];
        $lines[] = 'CRM conversation history: '
            . $runtimeData['CRM_CONVERSATION_HISTORY'];
        $lines[] = 'Current customer message: '
            . $runtimeData['CRM_CURRENT_CUSTOMER_MESSAGE'];
        $lines[] =
            'Use CRM Runtime Data and available CRM products/services instead of n8n variables.';

        return trim($instructions . PHP_EOL . implode(PHP_EOL, $lines));
    }

    private function customerNumber(
        WhatsAppConversation $conversation
    ): string {
        return (string) (
            optional($conversation->contact)->normalized_phone
            ?: optional($conversation->contact)->raw_phone
            ?: '-'
        );
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

        $product = $this->firstText(
            $decoded,
            [
                'product',
                'product_name',
                'crm_product',
                'lead.product',
            ]
        );

        $service = $this->firstText(
            $decoded,
            [
                'service',
                'service_name',
                'selected_service',
                'service_code',
                'lead.service',
                'lead.service_code',
            ]
        );

        if (!$product) {
            $product = $service ?: 'N/A';
        }

        return [
            'reply' => $reply,
            'product' => $product === '' ? 'N/A' : $product,
            'service' => $service,
            'service_date' => $this->firstText(
                $decoded,
                [
                    'service_date',
                    'date',
                    'travel_date',
                    'ride_date',
                    'departure_date',
                    'lead.date',
                    'lead.service_date',
                ]
            ),
            'guests' => $this->firstText(
                $decoded,
                [
                    'guests',
                    'guest',
                    'number_of_guests',
                    'number_of_passengers',
                    'passengers',
                    'pax',
                    'lead.guests',
                ]
            ),
            'route' => $this->firstText(
                $decoded,
                [
                    'route',
                    'travel_route',
                    'city_or_route',
                    'lead.route',
                ]
            ),
            'origin' => $this->firstText(
                $decoded,
                [
                    'origin',
                    'from',
                    'from_place',
                    'departure_city',
                    'lead.origin',
                ]
            ),
            'destination' => $this->firstText(
                $decoded,
                [
                    'destination',
                    'to',
                    'to_place',
                    'arrival_city',
                    'lead.destination',
                ]
            ),
            'city' => $this->firstText(
                $decoded,
                [
                    'city',
                    'service_city',
                    'location',
                    'lead.city',
                ]
            ),
            'occasion' => $this->firstText(
                $decoded,
                [
                    'occasion',
                    'ocassion',
                    'event',
                    'lead.occasion',
                ]
            ),
        ];
    }

    private function firstText(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (!is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);

            if (
                $value === ''
                || in_array(
                    strtolower($value),
                    [
                        'n/a',
                        'na',
                        'none',
                        'null',
                        'not provided',
                        'not available',
                    ],
                    true
                )
            ) {
                continue;
            }

            return $value;
        }

        return null;
    }
}
