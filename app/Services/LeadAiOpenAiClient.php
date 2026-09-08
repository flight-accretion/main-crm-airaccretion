<?php

namespace App\Services;

use App\Models\LeadAiScoringSetting;
use App\Services\Ai\AiProviderManager;
use RuntimeException;

class LeadAiOpenAiClient
{
    public function __construct(
        private AiProviderManager $providers
    ) {
    }


    public function analyse(
        LeadAiScoringSetting $setting,
        array $payload
    ): array {
        $agent =
            $setting
                ->aiAgent()
                ->with(
                    'modelProfile'
                )
                ->first();


        if (!$agent) {
            throw new RuntimeException(
                'Lead Scoring AI Agent is not configured.'
            );
        }


        if (!$agent->isReady()) {
            throw new RuntimeException(
                'Lead Scoring AI Agent is unavailable.'
            );
        }


        $profile =
            $agent->modelProfile;


        $text =
            $this->providers->generate(
                $profile,

                $this->instructions(
                    $setting,
                    (string) $agent->prompt
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


    private function instructions(
        LeadAiScoringSetting $setting,
        string $businessPrompt
    ): string {
        $coldMax =
            (int) $setting->cold_max;

        $coldMaxPlusOne =
            $coldMax + 1;

        $neutralMax =
            (int) $setting
                ->neutral_max;

        $hotMin =
            $neutralMax + 1;


        $businessPrompt =
            trim(
                $businessPrompt
            );


        if ($businessPrompt === '') {
            $businessPrompt =
                LeadAiScoringSetting::defaultPrompt();
        }


        return trim(
            <<<INSTRUCTIONS
You are the CRM Lead Intelligence Engine for Accretion Aviation.

YOUR TASK:
Evaluate the customer's CURRENT buying intent.

You receive:

1. Current factual CRM state.
2. Previous AI state, if this is not the first analysis.
3. ONLY the newly created eligible follow-up.

IMPORTANT RULES:

- Previous AI score is context only.
- Previous AI score is NOT a fixed baseline.
- Reassess CURRENT buying intent from the latest evidence.
- Increase, decrease, or retain the score when appropriate.
- Never invent customer statements or intent.
- Current CRM factual data overrides older AI memory.
- Missing information does not automatically mean negative intent.
- Do not treat system/audit actions as customer intent.
- Use lower confidence when evidence is ambiguous.
- Summary must contain maximum 3 bullets.
- Summary must be concise and useful to salesperson.
- Do not expose phone numbers, emails or unnecessary PII.
- Suggested action must be practical.
- Never create fake urgency.

SCORING:

Cold = 0 to {$coldMax}
Neutral = {$coldMaxPlusOne} to {$neutralMax}
Hot = {$hotMin} to 100

The numeric score is the primary assessment.

Application code will independently determine final Hot / Neutral / Cold from the numeric score.

RETURN JSON ONLY:

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
  "score_change_reason": "brief reason for score movement or initial score",
  "state": {
    "requirements_confirmed": false,
    "date_confirmed": false,
    "passengers_confirmed": false,
    "route_confirmed": false,
    "price_discussed": false,
    "price_objection": false,
    "final_price_accepted": false,
    "availability_requested": false,
    "payment_discussed": false,
    "payment_intent": false,
    "booking_requested": false,
    "booking_confirmed": false,
    "customer_postponed": false,
    "customer_declined": false,
    "customer_cancelled": false
  }
}

BUSINESS SCORING INSTRUCTIONS:

{$businessPrompt}
INSTRUCTIONS
        );
    }


    private function parseResponse(
        string $text,
        LeadAiScoringSetting $setting,
        string $model
    ): array {
        $text =
            trim($text);


        $text =
            preg_replace(
                '/^```(?:json)?/i',
                '',
                $text
            );


        $text =
            preg_replace(
                '/```$/',
                '',
                trim(
                    (string) $text
                )
            );


        $decoded =
            json_decode(
                trim(
                    (string) $text
                ),
                true
            );


        if (!is_array($decoded)) {
            throw new RuntimeException(
                'AI lead scoring response was not valid JSON.'
            );
        }


        if (
            !array_key_exists(
                'score',
                $decoded
            )
            ||
            !is_numeric(
                $decoded['score']
            )
        ) {
            throw new RuntimeException(
                'AI lead scoring response did not include a valid score.'
            );
        }


        $score =
            (int) round(
                (float)
                $decoded['score']
            );


        if (
            $score < 0
            ||
            $score > 100
        ) {
            throw new RuntimeException(
                'AI lead score must be between 0 and 100.'
            );
        }


        $confidence =
            $decoded[
                'confidence'
            ] ?? null;


        if (
            !is_numeric(
                $confidence
            )
        ) {
            throw new RuntimeException(
                'AI response did not include valid confidence.'
            );
        }


        $confidence =
            (int) round(
                (float)
                $confidence
            );


        if (
            $confidence < 0
            ||
            $confidence > 100
        ) {
            throw new RuntimeException(
                'AI confidence must be between 0 and 100.'
            );
        }


        $summary =
            $decoded[
                'summary'
            ] ?? [];


        if (!is_array($summary)) {
            throw new RuntimeException(
                'AI lead summary must be an array.'
            );
        }


        $summary =
            collect(
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
                'AI lead summary was empty.'
            );
        }


        $state =
            $decoded[
                'state'
            ] ?? [];


        if (!is_array($state)) {
            $state = [];
        }


        return [
            /*
             * Never trust model's temperature.
             */
            'temperature' =>
                $setting
                    ->temperatureFor(
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