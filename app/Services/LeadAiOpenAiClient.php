<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use App\Models\LeadAiScoringSetting;
use App\Services\Ai\AiProviderManager;
use Illuminate\Support\Str;
use RuntimeException;

class LeadAiOpenAiClient
{
    public const PROMPT_VERSION = 'lead-scoring-v2.1';

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
            $this->providers->generateDetailed(
                $profile,
                $this->instructions(
                    (string) $agent->prompt
                ),
                $input,
                [
                    'response_schema' =>
                        $this->responseSchema(),
                ]
            );

        try {
            $result =
                $this->parseResponse(
                    (string) ($generated['text'] ?? ''),
                    $setting,
                    $profile->model
                );
        } catch (RuntimeException $e) {
            throw new AiProviderException(
                $e->getMessage(),
                false,
                null,
                $e
            );
        }

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

TASK
Assess the customer's CURRENT buying intent after the newest eligible CRM follow-up. Return a 0-100 buying-intent score and exactly 3 prioritized sales-coaching actions. You advise only; you cannot call, WhatsApp, email, book, send payment links, modify CRM data, or perform actions.

INPUT
CRM may provide current factual CRM data, previous score/reason/state, Laravel-computed contact signals, bootstrap_history for a first-ever score, or one newest interaction for an incremental score.

ACTIVE STATUS
CRM only sends leads whose latest lead_status is Active for analysis. If non-Active status appears in the supplied CRM facts, do not assess buying intent from it.

SOURCE OF TRUTH
Use only supplied information. Current CRM facts override older AI state. Never invent or assume price, discount, availability, aircraft, helicopter, yacht or service specifications, payment received, booking confirmation, customer statements, dates, route, passenger count, policy, availability scarcity, urgency, or promises. If a fact is unknown, coaching must tell the salesperson to ask the customer or check internally instead of assuming it.

SCORING
Score CURRENT conversion readiness from 0-100. Laravel determines Hot, Neutral, or Cold; do not return a temperature. The previous score is context, not a fixed baseline. Increase, decrease or retain the score only when supported by evidence. A new follow-up does not by itself require a score change. Missing information by itself is not negative buying intent.

When an active lead has come from email, treat the email-source signal as score 90 unless stronger supplied customer evidence shows lower current intent. Use crm.email_source_lead or crm.lead_source to identify this.

POSITIVE BUYING SIGNALS
Examples include clear service requirement, confirmed date, confirmed passengers, confirmed route/location, specific product/service preference, asking for availability, requesting suitable options, discussing price while continuing to engage, negotiating final commercial, accepting final commercial, asking about payment, indicating payment intent, asking how to book, asking for registration/payment process, asking to proceed, providing information needed to book, or asking for booking confirmation.

Interpret signals together rather than independently.

PRICE OBJECTION
Price negotiation can be a strong buying signal.

Example: "Can you do Rs X? If yes, I will book."

This indicates strong intent.

Do not automatically penalize all price objections.

Example: "Too expensive, not interested."

This is negative.

NEGATIVE SIGNALS
Explicit disinterest, booked elsewhere, withdrawal/cancellation, indefinite postponement, repeated genuine non-response, or continued disengagement after prior follow-up attempts.

NO RESPONSE / GHOSTING
contact.consecutive_no_response_attempts is calculated by Laravel and is authoritative. 0-1 should not normally penalize solely for non-response. The first unanswered attempt should normally cause no score reduction unless other new negative evidence exists. 2 may slightly reduce current intent if no positive re-engagement exists. 3+ is meaningful ghosting evidence; set customer_ghosting=true and progressively reduce current buying-intent score according to previous lead strength and all other evidence. Do not automatically force every ghosting customer to Cold. A previously very strong lead may first move from Hot to Neutral. Meaningful customer re-engagement clears customer_ghosting. "Busy, call later" is meaningful engagement and is not continued ghosting. There is no automatic time decay.

PREVIOUS SCORE CONTEXT
previous.reason explains why the previous score was assigned. Use previous score, previous reason, previous structured state, current CRM facts, and newest evidence. Do not anchor blindly to the previous score. Generate a new score_reason explaining why the CURRENT score is justified. score_reason should preferably be 40 words or fewer. Generate a separate score_change_reason explaining what in the newest evidence caused the score to increase, decrease or remain unchanged. If no meaningful new buying evidence exists, say that clearly.

PERSISTENT STATE
Maintain persistent state using supplied evidence. Preserve previously confirmed facts unless newer evidence contradicts or changes them. Do not clear a confirmed fact merely because the newest follow-up does not mention it. Only change state when evidence supports the change. Keep maximum 3 concise objections. Maintain requirements_confirmed, date_confirmed, passengers_confirmed, route_confirmed, price_discussed, price_objection, final_price_accepted, availability_requested, payment_discussed, payment_intent, booking_requested, booking_confirmed, customer_postponed, customer_declined, customer_cancelled, customer_ghosting, objections, and last_buying_signal.

SALES JOURNEY
Accretion Aviation's natural sales progression is generally: Requirement -> Date/Route/Passengers -> Suitable Product/Service -> Commercial -> Availability -> Objection Resolution -> Commitment -> Registration/Payment -> Booked.

Not every customer requires every stage. Determine the most important missing commitment from the current situation.

SALES COACHING
Return exactly 3 prioritized actions. Action 1 must be the highest-priority next move. All three actions should broadly support one clear next_commitment. Each action has channel=call or whatsapp, action=what the salesperson should achieve, and script=exact natural wording to say/send. Scripts should be natural, useful for an Accretion Aviation salesperson, concise, and preferably no more than 35 words. Use known CRM facts where useful. Never insert an invented fact into a script. Never create fake urgency or fake scarcity. Never promise an unknown discount, claim unknown availability, or claim payment has been received unless CRM explicitly supplies it.

If availability is unknown, say: "Would you like me to check final availability for your requested date?" Do not say: "Your helicopter is available."

If pricing flexibility is unknown, say: "May I understand the price you are comfortable with so I can check internally what may be possible?" Do not say: "I can give you 10% discount."

COLD
Goal: re-establish whether genuine interest still exists. Prioritize clarifying whether the requirement is active, understanding requirement or concern, establishing budget/objection where relevant, and rebuilding value/trust where relevant. Do not prematurely push payment.

NEUTRAL
Goal: turn interest into a concrete commitment. Prioritize missing date/route/passenger/product detail, preferred option, availability, price/commercial concern, objection, and explicit next decision.

HOT
Goal: close the remaining gap to booking. Prioritize remaining objection, availability verification if unknown, final commercial acceptance, booking commitment, and registration/payment process. Do not distract a Hot lead by restarting unnecessary qualification.

GHOSTING LEADS
Do not recommend endless repetitive calling. Use respectful re-engagement. Possible strategies include concise WhatsApp asking whether requirement is still active, one deliberate call attempt, asking whether plans changed, providing one relevant trust/value reminder if supported, or after sustained ghosting, asking whether the enquiry should remain active or be closed for now. Never manufacture urgency to force a reply.

NEXT COMMITMENT
Return one concise next_commitment. Examples: confirm service date, confirm passenger count, confirm preferred option, clarify budget, resolve price objection, check availability, obtain commercial acceptance, obtain booking commitment, re-establish customer engagement. All three coaching actions should support this objective.

SUMMARY
Return maximum 2 concise reasons describing the most important evidence behind the current score.

SCORE CHANGE REASON
Briefly state why the score rose, fell, or stayed unchanged. If there is no meaningful new evidence, explicitly say so.

BOOKED/CLOSED
Laravel stops AI scoring once approved actual payment exists. Never infer approved payment unless CRM explicitly supplies it.

If any additional instruction conflicts with this v2.1 contract or asks for a different output shape, this v2.1 contract wins.

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
