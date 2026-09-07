<?php

namespace App\Services;

use App\Models\LeadAiScoringSetting;
use App\Models\WhatsAppAiAgentSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use App\Services\Ai\AiProviderManager;

class LeadAiOpenAiClient
{
   public function analyse(
    LeadAiScoringSetting $setting,
    array $payload
): array {
    $profile =
        $setting
            ->aiModelProfile()
            ->first();

    if (!$profile) {
        throw new RuntimeException(
            'Lead Scoring AI Model Profile is not configured.'
        );
    }

    if (!$profile->isReady()) {
        throw new RuntimeException(
            'Lead Scoring AI Model Profile is unavailable.'
        );
    }

    $text =
        $this->providers->generate(
            $profile,

            $this->instructions(
                $setting
            ),

            json_encode(
                $payload,
                JSON_PRETTY_PRINT
                |
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            )
        );

    return $this->parseResponse(
        $text,
        $setting,
        $profile->model
    );
}

    public function testConnection(
        ?string $temporaryApiKey = null,
        ?string $temporaryModel = null
    ): void {
        $global =
            WhatsAppAiAgentSetting::active();

        $apiKey = trim(
            (string) $temporaryApiKey
        );

        if ($apiKey === '') {
            $apiKey =
                (string) $global->apiKey();
        }

        if ($apiKey === '') {
            throw new RuntimeException(
                'OpenAI API key is not configured.'
            );
        }

        $model = trim(
            (string) $temporaryModel
        );

        if ($model === '') {
            $model =
                trim(
                    (string) $global->model
                )
                ?: WhatsAppAiAgentSetting::defaultModel();
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->post(
                (string) config(
                    'whatcrm.openai_responses_url'
                ),
                [
                    'model' => $model,

                    'instructions' =>
                        'This is a connection test. Reply with OK.',

                    'input' => [
                        [
                            'role' => 'user',

                            'content' => [
                                [
                                    'type' =>
                                        'input_text',

                                    'text' =>
                                        'Connection test.',
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
                $message
                    ?: 'OpenAI connection test failed.'
            );
        }
    }

    public function __construct(
    private AiProviderManager $providers
) {
}

    private function credentials(
        LeadAiScoringSetting $setting
    ): array {
        /*
         * Shared OpenAI credentials.
         *
         * IMPORTANT:
         * We deliberately do NOT call
         * WhatsAppAiAgentSetting::isReady()
         * because that method also requires
         * WhatsApp auto reply to be enabled.
         */
        $global =
            WhatsAppAiAgentSetting::active();

        if (
            strtolower(
                trim(
                    (string) $global->provider
                )
            ) !== 'openai'
        ) {
            throw new RuntimeException(
                'OpenAI provider is not configured.'
            );
        }

        $apiKey =
            (string) $global->apiKey();

        if (trim($apiKey) === '') {
            throw new RuntimeException(
                'OpenAI API key is not configured.'
            );
        }

        $model =
            trim(
                (string) $setting->model
            );

        if ($model === '') {
            $model =
                trim(
                    (string) $global->model
                )
                ?: WhatsAppAiAgentSetting::defaultModel();
        }

        return [
            $apiKey,
            $model,
        ];
    }

    private function instructions(
        LeadAiScoringSetting $setting
    ): string {
        $coldMax =
            (int) $setting->cold_max;

        $neutralMax =
            (int) $setting->neutral_max;
            $coldMaxPlusOne =
        $coldMax + 1;

        $hotMin =
            $neutralMax + 1;

        $businessPrompt = trim(
            (string) (
                $setting->prompt
                ?: LeadAiScoringSetting::defaultPrompt()
            )
        );

        return trim(
            <<<INSTRUCTIONS
You are the CRM Lead Intelligence Engine for Accretion Aviation.

YOUR TASK:
Evaluate the customer's CURRENT buying intent.

You receive:
1. Current factual CRM state.
2. The previous AI state, if this is not the first analysis.
3. ONLY the newly created follow-up.

IMPORTANT RULES:
- The previous AI score is context, not a binding baseline.
- Reassess the CURRENT lead state from the new evidence.
- Increase, decrease, or keep the score when appropriate.
- Never invent customer statements or buying intent.
- Current CRM factual data overrides old AI memory.
- Do not assume that silence, missing data, or an internal CRM action means buying intent.
- Use lower confidence when evidence is weak or ambiguous.
- Do not provide more than 3 summary bullets.
- Each bullet must be concise and useful to a salesperson.
- Do not include phone numbers, email addresses or sensitive information in the summary.
- Suggested action must be concise and practical.
- Do not create fake urgency.

SCORING:
Cold = 0 to {$coldMax}
Neutral = {$coldMaxPlusOne} to {$neutralMax}
Hot = {$hotMin} to 100

The numeric score is the main result.
The application will independently derive Hot/Neutral/Cold from the numeric score.

Maintain structured factual memory under "state".

RETURN JSON ONLY, exactly following this structure:

{
  "temperature": "hot|neutral|cold",
  "score": 0,
  "confidence": 0,
  "summary": [
    "bullet 1",
    "bullet 2",
    "bullet 3"
  ],
  "suggested_action": "one concise next action",
  "score_change_reason": "brief explanation for movement or initial score",
  "state": {
    "requirements_confirmed": false,
    "date_confirmed": false,
    "passengers_confirmed": false,
    "price_discussed": false,
    "price_objection": false,
    "availability_requested": false,
    "payment_discussed": false,
    "payment_intent": false,
    "booking_confirmed": false,
    "customer_declined": false
  }
}

BUSINESS SCORING INSTRUCTIONS:
{$businessPrompt}
INSTRUCTIONS
        );
    }

    private function responseText(
        array $payload
    ): string {
        $outputText = data_get(
            $payload,
            'output_text'
        );

        if (
            is_string($outputText)
            &&
            trim($outputText) !== ''
        ) {
            return trim($outputText);
        }

        $parts = [];

        foreach (
            (array) data_get(
                $payload,
                'output',
                []
            )
            as $item
        ) {
            foreach (
                (array) data_get(
                    $item,
                    'content',
                    []
                )
                as $content
            ) {
                $text = data_get(
                    $content,
                    'text'
                );

                if (
                    is_string($text)
                    &&
                    trim($text) !== ''
                ) {
                    $parts[] = $text;
                }
            }
        }

        return trim(
            implode(
                PHP_EOL,
                $parts
            )
        );
    }

    private function parseResponse(
        string $text,
        LeadAiScoringSetting $setting,
        string $model
    ): array {
        $text = trim($text);

        $text = preg_replace(
            '/^```(?:json)?/i',
            '',
            $text
        );

        $text = preg_replace(
            '/```$/',
            '',
            trim((string) $text)
        );

        $decoded = json_decode(
            trim((string) $text),
            true
        );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'OpenAI lead scoring response was not valid JSON.'
            );
        }

        if (
            !array_key_exists(
                'score',
                $decoded
            )
            ||
            !is_numeric($decoded['score'])
        ) {
            throw new RuntimeException(
                'OpenAI lead scoring response did not include a valid score.'
            );
        }

        $score =
            (int) round(
                (float) $decoded['score']
            );

        if (
            $score < 0
            ||
            $score > 100
        ) {
            throw new RuntimeException(
                'OpenAI lead score must be between 0 and 100.'
            );
        }

        $confidence = $decoded[
            'confidence'
        ] ?? null;

        if (!is_numeric($confidence)) {
            throw new RuntimeException(
                'OpenAI response did not include valid confidence.'
            );
        }

        $confidence =
            (int) round(
                (float) $confidence
            );

        if (
            $confidence < 0
            ||
            $confidence > 100
        ) {
            throw new RuntimeException(
                'OpenAI confidence must be between 0 and 100.'
            );
        }

        $summary =
            $decoded['summary']
            ?? [];

        if (!is_array($summary)) {
            throw new RuntimeException(
                'OpenAI lead summary must be an array.'
            );
        }

        $summary = collect(
            $summary
        )
            ->filter(
                fn ($item) =>
                    is_scalar($item)
                    &&
                    trim(
                        (string) $item
                    ) !== ''
            )
            ->map(
                fn ($item) =>
                    trim(
                        (string) $item
                    )
            )
            ->take(3)
            ->values()
            ->all();

        if (empty($summary)) {
            throw new RuntimeException(
                'OpenAI lead summary was empty.'
            );
        }

        $state =
            $decoded['state']
            ?? [];

        if (!is_array($state)) {
            $state = [];
        }

        return [
            /*
             * Do NOT trust the AI temperature.
             * Derive it from the numeric score.
             */
            'temperature' =>
                $setting->temperatureFor(
                    $score
                ),

            'score' =>
                $score,

            'confidence' =>
                $confidence,

            'summary' =>
                $summary,

            'suggested_action' =>
                trim(
                    (string) (
                        $decoded[
                            'suggested_action'
                        ]
                        ?? ''
                    )
                ),

            'score_change_reason' =>
                trim(
                    (string) (
                        $decoded[
                            'score_change_reason'
                        ]
                        ?? ''
                    )
                ),

            'state' =>
                $state,

            'model' =>
                $model,
        ];
    }
}