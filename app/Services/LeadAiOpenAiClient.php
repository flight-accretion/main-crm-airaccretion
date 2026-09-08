<?php

namespace App\Services;

use App\Models\LeadAiScoringSetting;
use App\Services\Ai\AiProviderManager;
use Illuminate\Support\Str;
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

        $input =
            json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
            );

        if ($input === false) {
            throw new RuntimeException(
                'Unable to encode lead scoring input.'
            );
        }

        $startedAt =
            microtime(true);

        $generated =
            $this->providers->generateStructured(
                $profile,
                $this->instructions(
                    (string) $agent->prompt
                ),
                $input,
                $this->responseSchema()
            );

        $result =
            $this->parseResponse(
                (string) ($generated['text'] ?? ''),
                $setting,
                $profile->model
            );

        $result['provider'] =
            strtolower(
                trim(
                    (string) $profile->provider
                )
            );

        $result['usage'] =
            $generated['usage'] ?? [];

        $result['processing_ms'] =
            (int) round(
                (microtime(true) - $startedAt)
                * 1000
            );

        return $result;
    }

    private function instructions(
        string $businessPrompt
    ): string {
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
You are Accretion Aviation's CRM Lead Intelligence and Sales Coaching engine.

PURPOSE
Assess the customer's current buying intent after an eligible CRM follow-up. Return a 0-100 score and exactly 3 coaching actions for the salesperson. You advise only; you cannot call, WhatsApp, email, book, approve payment, or change CRM records.

INPUT
CRM may provide current factual CRM data, previous score/reason/state, Laravel-computed contact signals, and either bootstrap history for a first score or one newest interaction for an incremental score.

SOURCE OF TRUTH
Use only supplied information. Current CRM facts override older AI state. Never invent price, discount, availability, aircraft, yacht, helicopter, payment, booking, route, passenger count, customer statements, policy, scarcity, or urgency. If a fact is unknown, coach the salesperson to ask the customer or check internally.

SCORING
Return only a numeric score from 0 to 100. Laravel determines Hot, Neutral, or Cold; do not return a temperature. The score means current conversion readiness, not follow-up urgency. Change the previous score only when new evidence supports a change. A new follow-up record alone does not justify movement. Missing information alone is not negative.

POSITIVE SIGNALS
Confirmed requirement/date/passengers/route, specific option selected, availability request, commercial discussion or negotiation while still engaged, final-price acceptance, payment discussion or intent, asking for registration/payment details, asking how to book, asking to proceed, or providing details required to book. Price negotiation is not automatically negative.

NEGATIVE SIGNALS
Explicit disinterest, booked elsewhere, withdrawal/cancellation, indefinite postponement, repeated genuine non-response, or continued disengagement after prior follow-up attempts.

GHOSTING
Laravel provides consecutive_no_response_attempts. 0-1 should not normally penalize solely for non-response. 2 may cause a small reduction when no customer engagement exists. 3+ is meaningful ghosting evidence; set customer_ghosting=true and progressively reduce current buying intent. Do not automatically force every ghosting lead to Cold. Meaningful customer re-engagement clears customer_ghosting.

PREVIOUS CONTEXT
previous.score and previous.reason explain the prior assessment; they are context, not a fixed baseline. Generate a new score_reason after every analysis. Keep score_reason concise, preferably no more than 40 words, because CRM sends it back on the next incremental request.

STATE
Maintain persistent state using supplied evidence. Do not clear a confirmed fact only because the newest interaction does not repeat it. Newer customer evidence may override older state. Maintain requirements_confirmed, date_confirmed, passengers_confirmed, route_confirmed, price_discussed, price_objection, final_price_accepted, availability_requested, payment_discussed, payment_intent, booking_requested, booking_confirmed, customer_postponed, customer_declined, customer_cancelled, customer_ghosting, objections, and last_buying_signal.

SALES COACHING
Return exactly 3 prioritized actions. Action 1 is the most important next move. Each action has channel=call or whatsapp, action=what the salesperson should achieve, and script=exact natural wording to say/send. Scripts should be human, concise, and preferably no more than 35 words. Use known CRM facts where useful. Never insert an invented fact into a script. Never create fake urgency or fake scarcity.

COLD
Goal: re-establish genuine interest, understand why interest is weak, reconfirm the requirement, discover objections/budget, or respectfully close a ghosting conversation. Do not aggressively push payment.

NEUTRAL
Goal: obtain the next missing commitment: requirement/date/passengers/route, preferred option, availability, budget, objection resolution, or commercial decision.

HOT
Goal: close efficiently. Focus on the exact remaining objection, final availability check, commercial acceptance, registration/payment process, or direct booking commitment. Do not ask unnecessary questions already answered.

GHOSTING COACHING
Do not recommend endless repetitive calls. Use a concise re-engagement WhatsApp, one deliberate call attempt, or a respectful final status-check.

NEXT COMMITMENT
Return one concise machine-friendly next_commitment describing the next customer commitment the salesperson should pursue.

SUMMARY
Return 1-2 concise reasons supporting the current score.

SCORE CHANGE REASON
Briefly state why the score rose, fell, or stayed unchanged. If there is no meaningful new evidence, explicitly say so.

BOOKED/CLOSED
Laravel stops AI scoring once approved actual payment exists. Never infer approved payment unless CRM explicitly supplies it.

If any additional instruction conflicts with this v2 contract or asks for a different output shape, this v2 contract wins.

ADDITIONAL BUSINESS INSTRUCTIONS
{$businessPrompt}

Return only the configured structured JSON schema. No markdown and no extra fields.
INSTRUCTIONS
        );
    }

    private function parseResponse(
        string $text,
        LeadAiScoringSetting $setting,
        string $model
    ): array {
        $decoded =
            json_decode(
                $this->stripCodeFence($text),
                true
            );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'AI lead scoring response was not valid JSON.'
            );
        }

        $score =
            $this->integerField(
                $decoded,
                'score'
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
            $this->integerField(
                $decoded,
                'confidence'
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

        $scoreReason =
            $this->stringField(
                $decoded,
                'score_reason',
                500
            );

        $scoreChangeReason =
            $this->stringField(
                $decoded,
                'score_change_reason',
                500
            );

        $summary =
            $this->summary(
                $decoded
            );

        $nextCommitment =
            $this->stringField(
                $decoded,
                'next_commitment',
                80
            );

        $actions =
            $this->actions(
                $decoded
            );

        $state =
            $decoded['state'] ?? null;

        if (!is_array($state)) {
            throw new RuntimeException(
                'AI lead state must be an object.'
            );
        }

        return [
            'temperature' =>
                $setting
                    ->temperatureFor(
                        $score
                    ),

            'score' =>
                $score,

            'confidence' =>
                $confidence,

            'score_reason' =>
                $scoreReason,

            'summary' =>
                $summary,

            'score_change_reason' =>
                $scoreChangeReason,

            'actions' =>
                $actions,

            'next_commitment' =>
                $nextCommitment,

            'state' =>
                $state,

            'model' =>
                $model,
        ];
    }

    private function stripCodeFence(
        string $text
    ): string {
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

        return trim(
            (string) $text
        );
    }

    private function integerField(
        array $decoded,
        string $field
    ): int {
        if (
            !array_key_exists(
                $field,
                $decoded
            )
            ||
            !is_numeric(
                $decoded[$field]
            )
        ) {
            throw new RuntimeException(
                "AI response did not include valid {$field}."
            );
        }

        return (int) round(
            (float) $decoded[$field]
        );
    }

    private function stringField(
        array $decoded,
        string $field,
        int $limit
    ): string {
        $value =
            $decoded[$field]
            ?? null;

        if (
            !is_scalar($value)
            ||
            trim((string) $value) === ''
        ) {
            throw new RuntimeException(
                "AI response did not include valid {$field}."
            );
        }

        return Str::limit(
            trim((string) $value),
            $limit,
            ''
        );
    }

    private function summary(
        array $decoded
    ): array {
        $summary =
            $decoded['summary']
            ?? null;

        if (!is_array($summary)) {
            throw new RuntimeException(
                'AI lead summary must be an array.'
            );
        }

        $summary =
            collect($summary)
                ->filter(
                    fn ($item) =>
                        is_scalar($item)
                        &&
                        trim((string) $item) !== ''
                )
                ->map(
                    fn ($item) =>
                        Str::limit(
                            trim((string) $item),
                            250,
                            ''
                        )
                )
                ->values()
                ->all();

        if (
            count($summary) < 1
            ||
            count($summary) > 2
        ) {
            throw new RuntimeException(
                'AI lead summary must contain 1 to 2 items.'
            );
        }

        return $summary;
    }

    private function actions(
        array $decoded
    ): array {
        $actions =
            $decoded['actions']
            ?? null;

        if (
            !is_array($actions)
            ||
            count($actions) !== 3
        ) {
            throw new RuntimeException(
                'AI lead coaching must contain exactly 3 actions.'
            );
        }

        return collect($actions)
            ->map(function ($item) {
                if (!is_array($item)) {
                    throw new RuntimeException(
                        'Invalid AI coaching action.'
                    );
                }

                $channel =
                    strtolower(
                        trim(
                            (string) (
                                $item['channel']
                                ?? ''
                            )
                        )
                    );

                $action =
                    trim(
                        (string) (
                            $item['action']
                            ?? ''
                        )
                    );

                $script =
                    trim(
                        (string) (
                            $item['script']
                            ?? ''
                        )
                    );

                if (
                    !in_array(
                        $channel,
                        [
                            'call',
                            'whatsapp',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Invalid AI coaching channel.'
                    );
                }

                if (
                    $action === ''
                    ||
                    $script === ''
                ) {
                    throw new RuntimeException(
                        'Incomplete AI coaching action.'
                    );
                }

                return [
                    'channel' =>
                        $channel,

                    'action' =>
                        Str::limit(
                            $action,
                            250,
                            ''
                        ),

                    'script' =>
                        Str::limit(
                            $script,
                            500,
                            ''
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'score' => [
                    'type' => 'INTEGER',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'confidence' => [
                    'type' => 'INTEGER',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
                'score_reason' => [
                    'type' => 'STRING',
                ],
                'score_change_reason' => [
                    'type' => 'STRING',
                ],
                'summary' => [
                    'type' => 'ARRAY',
                    'minItems' => 1,
                    'maxItems' => 2,
                    'items' => [
                        'type' => 'STRING',
                    ],
                ],
                'next_commitment' => [
                    'type' => 'STRING',
                ],
                'actions' => [
                    'type' => 'ARRAY',
                    'minItems' => 3,
                    'maxItems' => 3,
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'channel' => [
                                'type' => 'STRING',
                                'enum' => [
                                    'call',
                                    'whatsapp',
                                ],
                            ],
                            'action' => [
                                'type' => 'STRING',
                            ],
                            'script' => [
                                'type' => 'STRING',
                            ],
                        ],
                        'required' => [
                            'channel',
                            'action',
                            'script',
                        ],
                    ],
                ],
                'state' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'requirements_confirmed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'date_confirmed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'passengers_confirmed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'route_confirmed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'price_discussed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'price_objection' => [
                            'type' => 'BOOLEAN',
                        ],
                        'final_price_accepted' => [
                            'type' => 'BOOLEAN',
                        ],
                        'availability_requested' => [
                            'type' => 'BOOLEAN',
                        ],
                        'payment_discussed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'payment_intent' => [
                            'type' => 'BOOLEAN',
                        ],
                        'booking_requested' => [
                            'type' => 'BOOLEAN',
                        ],
                        'booking_confirmed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'customer_postponed' => [
                            'type' => 'BOOLEAN',
                        ],
                        'customer_declined' => [
                            'type' => 'BOOLEAN',
                        ],
                        'customer_cancelled' => [
                            'type' => 'BOOLEAN',
                        ],
                        'customer_ghosting' => [
                            'type' => 'BOOLEAN',
                        ],
                        'objections' => [
                            'type' => 'ARRAY',
                            'items' => [
                                'type' => 'STRING',
                            ],
                        ],
                        'last_buying_signal' => [
                            'type' => 'STRING',
                        ],
                    ],
                ],
            ],
            'required' => [
                'score',
                'confidence',
                'score_reason',
                'score_change_reason',
                'summary',
                'next_commitment',
                'actions',
                'state',
            ],
        ];
    }
}
