<?php

namespace App\Services;

use App\Models\AiModelProfile;
use App\Models\WhatsAppAiAgentSetting;
use App\Models\WhatsAppConversation;
use Illuminate\Support\Collection;
// use Illuminate\Support\Facades\Http;
use App\Services\Ai\AiProviderManager;
use RuntimeException;

class WhatsAppOpenAiClient
{
 public function __construct(
    private WhatsAppAiRuntimeDataService $runtimeData,
    private AiProviderManager $providers
) {
}

public function generateReply(
    WhatsAppAiAgentSetting $setting,
    WhatsAppConversation $conversation,
    Collection $messages,
    Collection $products,
    ?Collection $contextMessages = null
): array {
    $agent =
        $setting
            ->aiAgent()
            ->with(
                'modelProfile'
            )
            ->first();


    if ($agent && !$agent->isReady()) {
        throw new RuntimeException(
            'WhatsApp AI Agent is unavailable.'
        );
    }


    $profile = $agent
        ? $agent->modelProfile
        : $this->legacyProfile($setting);

    $agentPrompt = $agent
        ? (string) $agent->prompt
        : (string) $setting->prompt;


    $text =
        $this->providers->generate(
            $profile,

            $this->instructions(
                $agentPrompt,
                $conversation,
                $messages,
                $products,
                $contextMessages
            ),

            $this->prompt(
                $conversation,
                $messages,
                $products,
                $contextMessages
            )
        );


    return $this->parseResponse(
        $text
    );
}

    private function legacyProfile(
        WhatsAppAiAgentSetting $setting
    ): AiModelProfile {
        $apiKey = $setting->apiKey();

        if (!$apiKey) {
            throw new RuntimeException(
                'WhatsApp AI Agent is not configured.'
            );
        }

        $profile = new AiModelProfile([
            'name' => 'Legacy WhatsApp AI Setting',
            'provider' => $setting->provider ?: 'openai',
            'model' => $setting->model ?: WhatsAppAiAgentSetting::defaultModel(),
            'enabled' => true,
        ]);
        $profile->setApiKey($apiKey);

        return $profile;
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
            'Return JSON only with exactly this structure: '
            . '{"reply":"customer-facing reply",'
            . '"service_family":null,'
            . '"product_id":null,'
            . '"product_name":null,'
            . '"extracted_fields":{'
            . '"origin":null,'
            . '"destination":null,'
            . '"city":null,'
            . '"date":null,'
            . '"departure_time":null,'
            . '"passengers":null,'
            . '"occasion":null,'
            . '"patient_location":null,'
            . '"date_or_urgency":null,'
            . '"budget":null,'
            . '"preferred_option":null'
            . '},'
            . '"initial_booking_intent":false,'
            . '"initial_payment_intent":false,'
            . '"customer_ready_to_book":false,'
            . '"customer_ready_to_pay":false,'
            . '"main_objection":null,'
            . '"unresolved_question":null,'
            . '"language":null,'
            . '"confidence":0.0,'
            . '"needs_human":false,'
            . '"needs_human_reason":null}';

        return implode(PHP_EOL, $lines);
    }

    private function instructions(
        string $agentPrompt,
    WhatsAppConversation $conversation,
        Collection $messages,
        Collection $products,
        ?Collection $contextMessages = null
    ): string {
     $instructions =
    trim(
        $agentPrompt
    );

        if ($instructions === '') {
            $instructions =
                WhatsAppAiAgentSetting::defaultPrompt();
        }

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
            'Conversation owner: ' . $runtimeData['CRM_CONVERSATION_OWNER'],
            'Lead status: ' . $runtimeData['CRM_LEAD_STATUS'],
            'Previous service: ' . $runtimeData['CRM_PREVIOUS_SERVICE'],
            'Last booking date: ' . $runtimeData['CRM_LAST_BOOKING_DATE'],
            'Lead qualification state: ' . $runtimeData['CRM_LEAD_STATE'],
            'AI pre-lead state: ' . $runtimeData['CRM_AI_STATE'],
            'Required pre-lead fields: ' . $runtimeData['CRM_REQUIRED_FIELDS'],
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
        $lines[] = 'CRM live product data: '
            . $runtimeData['CRM_LIVE_PRODUCT_DATA'];
        $lines[] = 'CRM recommended alternatives: '
            . $runtimeData['CRM_RECOMMENDED_ALTERNATIVES'];
        $lines[] = 'CRM value comparison: '
            . $runtimeData['CRM_VALUE_COMPARISON'];
        $lines[] = 'CRM website data error: '
            . $runtimeData['CRM_WEBSITE_DATA_ERROR'];
            $lines[] =
    'Website AI product/location knowledge: '
    . $runtimeData[
        'CRM_WEBSITE_AI_NOTES'
    ];


$lines[] =
    'Website AI Notes are trusted product/location information manually entered by Accretion Aviation staff. '
    . 'They are not customer statements, payment confirmation, booking confirmation, or live availability confirmation. '
    . 'Never let Website AI Notes override verified CRM payment, booking, or lead-status facts.';
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
                'AI response did not include a reply.'
            );
        }

        $extracted = data_get($decoded, 'extracted_fields');
        $extracted = is_array($extracted) ? $extracted : [];

        $product = $this->firstText(
            $decoded,
            [
                'product_name',
                'product',
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

        $productName = strtolower((string) $product) === 'n/a'
            ? null
            : $product;

        $date = $this->firstText(
            $decoded,
            [
                'date',
                'service_date',
                'travel_date',
                'ride_date',
                'departure_date',
                'lead.date',
                'lead.service_date',
            ]
        ) ?: $this->firstText($extracted, ['date']);

        $passengers = $this->firstText(
            $decoded,
            [
                'passengers',
                'guests',
                'guest',
                'number_of_guests',
                'number_of_passengers',
                'pax',
                'lead.guests',
            ]
        ) ?: $this->firstText($extracted, ['passengers']);

        $origin = $this->firstText(
            $decoded,
            [
                'origin',
                'from',
                'from_place',
                'departure_city',
                'lead.origin',
            ]
        ) ?: $this->firstText($extracted, ['origin']);

        $destination = $this->firstText(
            $decoded,
            [
                'destination',
                'to',
                'to_place',
                'arrival_city',
                'lead.destination',
            ]
        ) ?: $this->firstText($extracted, ['destination']);

        $city = $this->firstText(
            $decoded,
            [
                'city',
                'service_city',
                'location',
                'lead.city',
            ]
        ) ?: $this->firstText($extracted, ['city']);

        $occasion = $this->firstText(
            $decoded,
            [
                'occasion',
                'ocassion',
                'event',
                'lead.occasion',
            ]
        ) ?: $this->firstText($extracted, ['occasion']);

        return [
            'reply' => $reply,
            'product' => $product === '' ? 'N/A' : $product,
            'product_id' => $this->firstText($decoded, ['product_id']),
            'product_name' => $productName,
            'service_family' => $this->firstText(
                $decoded,
                ['service_family', 'family']
            ),
            'service' => $service,
            'date' => $date,
            'service_date' => $date,
            'passengers' => $passengers,
            'guests' => $passengers,
            'route' => $this->firstText(
                $decoded,
                [
                    'route',
                    'travel_route',
                    'city_or_route',
                    'lead.route',
                ]
            ),
            'origin' => $origin,
            'destination' => $destination,
            'city' => $city,
            'departure_time' => $this->firstText($extracted, ['departure_time']),
            'occasion' => $occasion,
            'patient_location' => $this->firstText($extracted, ['patient_location']),
            'date_or_urgency' => $this->firstText($extracted, ['date_or_urgency']),
            'budget' => $this->firstText($extracted, ['budget']),
            'preferred_option' => $this->firstText($extracted, ['preferred_option']),
            'extracted_fields' => $extracted,
            'initial_booking_intent' => $this->boolValue(
                $decoded['initial_booking_intent'] ?? false
            ),
            'initial_payment_intent' => $this->boolValue(
                $decoded['initial_payment_intent'] ?? false
            ),
            'customer_ready_to_book' => $this->boolValue(
                $decoded['customer_ready_to_book'] ?? false
            ),
            'customer_ready_to_pay' => $this->boolValue(
                $decoded['customer_ready_to_pay'] ?? false
            ),
            'main_objection' => $this->firstText($decoded, ['main_objection']),
            'unresolved_question' => $this->firstText($decoded, ['unresolved_question']),
            'language' => $this->firstText($decoded, ['language']),
            'confidence' => is_numeric($decoded['confidence'] ?? null)
                ? (float) $decoded['confidence']
                : null,
            'needs_human' => $this->boolValue(
                $decoded['needs_human'] ?? false
            ),
            'needs_human_reason' => $this->firstText(
                $decoded,
                ['needs_human_reason']
            ),
        ];
    }

    private function boolValue($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'y'],
            true
        );
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
