<?php

namespace App\Services;

use App\Jobs\ProcessLeadAiScore;
use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\LeadFollowup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadAiScoringService
{
    public const PROMPT_VERSION = 'lead-scoring-v2.0';

    public function __construct(
        private LeadAiScoringEligibilityService $eligibility,
        private LeadAiCurrentFactsService $facts,
        private LeadAiContactSignalService $contactSignals,
        private LeadAiOpenAiClient $client
    ) {
    }

    public function queueForFollowup(
        LeadFollowup $followup,
        bool $manual = false
    ): ?LeadAiScore {
        $setting =
            LeadAiScoringSetting::active();

        if (!$setting->enabled) {
            return null;
        }

        if (
            !$manual
            &&
            !$setting->auto_analyse
        ) {
            return null;
        }

        $lead =
            $followup
                ->enquiry()
                ->first();

        if (
            !$lead
            || $this->facts->isBookedClosed($lead)
        ) {
            return null;
        }

        if (
            !$this->eligibility
                ->eligible($followup)
        ) {
            return null;
        }

        $existing =
            LeadAiScore::query()
                ->where(
                    'followup_id',
                    $followup->id
                )
                ->first();

        if ($existing) {
            return $existing;
        }

        try {
            $score =
                LeadAiScore::create([
                    'lead_id' =>
                        $followup->lead_id,

                    'followup_id' =>
                        $followup->id,

                    'status' =>
                        'pending',

                    'input_hash' =>
                        hash(
                            'sha256',
                            self::PROMPT_VERSION
                            . '|'
                            . $followup->id
                            . '|'
                            . $followup->lead_id
                            . '|'
                            . (
                                $followup
                                    ->followup_note
                                ?? ''
                            )
                            . '|'
                            . (
                                $followup
                                    ->contact_outcome
                                ?? ''
                            )
                        ),
                ]);
        } catch (QueryException $e) {
            /*
             * Unique followup_id protects
             * against simultaneous duplicate triggers.
             */
            return LeadAiScore::query()
                ->where(
                    'followup_id',
                    $followup->id
                )
                ->first();
        }

        ProcessLeadAiScore::dispatch(
            $score->id
        );

        return $score;
    }

    public function process(
        string $scoreId
    ): void {
        /*
         * --------------------------------------------------
         * CLAIM JOB
         * --------------------------------------------------
         */

        $score = DB::transaction(
            function () use ($scoreId) {
                $score =
                    LeadAiScore::query()
                        ->where(
                            'id',
                            $scoreId
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$score) {
                    return null;
                }

                if (
                    $score->status
                    === 'completed'
                ) {
                    return null;
                }

                /*
                 * Prevent duplicate workers from
                 * processing the same row.
                 *
                 * Recover a processing row if it has
                 * been stuck for more than 5 minutes.
                 */
                if (
                    $score->status
                        === 'processing'
                    &&
                    $score->updated_at
                    &&
                    $score->updated_at->gt(
                        now()->subMinutes(5)
                    )
                ) {
                    return null;
                }

                $score->update([
                    'status' =>
                        'processing',

                    'attempt_count' =>
                        (int)
                        $score->attempt_count
                        + 1,

                    'last_error' =>
                        null,
                ]);

                return $score->fresh();
            }
        );

        if (!$score) {
            return;
        }

        try {
            $followup =
                LeadFollowup::query()
                    ->where(
                        'id',
                        $score->followup_id
                    )
                    ->firstOrFail();

            $lead =
                $followup->enquiry()
                    ->firstOrFail();

            if ($this->facts->isBookedClosed($lead)) {
                $score->update([
                    'status' =>
                        'failed',

                    'last_error' =>
                        'Skipped because approved payment has been received.',
                ]);

                return;
            }

            /*
             * Latest COMPLETED state created before
             * this new AI state.
             */
            $previous =
                LeadAiScore::query()
                    ->where(
                        'lead_id',
                        $score->lead_id
                    )
                    ->where(
                        'status',
                        'completed'
                    )
                    ->where(
                        'id',
                        '!=',
                        $score->id
                    )
                    ->where(
                        'created_at',
                        '<',
                        $score->created_at
                    )
                    ->orderByDesc(
                        'created_at'
                    )
                    ->first();

            $setting =
                LeadAiScoringSetting::active();

            $crmFacts =
                $this->facts->build(
                    $lead
                );

            $contactSignals =
                $this->contactSignals->build(
                    $lead,
                    $followup
                );

            $payload = [
                'crm' =>
                    $crmFacts,

                'contact' =>
                    $contactSignals,
            ];

            if ($previous) {
                $payload['previous'] = [
                    'score' =>
                        (int) $previous->score,

                    'reason' =>
                        (string) $previous->score_reason,

                    'state' =>
                        $previous->state_json ?: [],
                ];

                $payload['interaction'] =
                    $this->followupPayload(
                        $followup
                    );
            } else {
                $payload['history'] =
                    $this->bootstrapHistory(
                        $lead,
                        $followup
                    );
            }

            $inputHash =
                hash(
                    'sha256',
                    self::PROMPT_VERSION
                    . '|'
                    . json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE
                        |
                        JSON_UNESCAPED_SLASHES
                    )
                );

            $result =
                $this->client->analyse(
                    $setting,
                    $payload
                );

            $score->update([
                'previous_score_id' =>
                    optional($previous)->id,

                'status' =>
                    'completed',

                'temperature' =>
                    $setting->temperatureFor(
                        (int) $result['score']
                    ),

                'score' =>
                    $result['score'],

                'confidence' =>
                    $result['confidence'],

                'score_reason' =>
                    $result['score_reason'],

                'summary' =>
                    $result['summary'],

                'score_change_reason' =>
                    $result[
                        'score_change_reason'
                    ],

                'actions_json' =>
                    $result['actions'],

                'next_commitment' =>
                    $result['next_commitment'],

                'state_json' =>
                    $result['state'],

                'input_hash' =>
                    $inputHash,

                'model' =>
                    $result['model'],

                'provider' =>
                    $result['provider']
                    ?? 'gemini',

                'prompt_version' =>
                    self::PROMPT_VERSION,

                'input_tokens' =>
                    data_get(
                        $result,
                        'usage.input_tokens'
                    ),

                'cached_input_tokens' =>
                    data_get(
                        $result,
                        'usage.cached_input_tokens'
                    ),

                'output_tokens' =>
                    data_get(
                        $result,
                        'usage.output_tokens'
                    ),

                'total_tokens' =>
                    data_get(
                        $result,
                        'usage.total_tokens'
                    ),

                'processing_ms' =>
                    data_get(
                        $result,
                        'processing_ms'
                    ),

                'processed_at' =>
                    now(),

                'last_error' =>
                    null,
            ]);
        } catch (\Throwable $e) {
            $score->update([
                'status' =>
                    'failed',

                /*
                 * Do not show this directly
                 * to salesperson.
                 */
                'last_error' =>
                    Str::limit(
                        $e->getMessage(),
                        2000
                    ),
            ]);

            /*
             * Let Laravel queue retry.
             */
            throw $e;
        }
    }

    private function bootstrapHistory(
        Lead $lead,
        LeadFollowup $current
    ): array {
        return $lead
            ->leadFollowups()
            ->with('followedBy.userType')
            ->where(
                'created_at',
                '<=',
                $current->created_at
            )
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->filter(
                fn (LeadFollowup $item) =>
                    $this->eligibility
                        ->eligible($item)
            )
            ->take(10)
            ->reverse()
            ->values()
            ->map(
                fn (LeadFollowup $item) =>
                    $this->followupPayload($item)
            )
            ->all();
    }

    private function followupPayload(
        LeadFollowup $followup
    ): array {
        return [
            'note' =>
                $this->sanitizeNote(
                    (string) $followup->followup_note
                ),

            'status' =>
                (int) $followup->status,

            'contact_outcome' =>
                $followup->contact_outcome,

            'at' =>
                optional($followup->created_at)
                    ?->toIso8601String(),
        ];
    }

    private function sanitizeNote(
        string $note
    ): string {
        $lines =
            preg_split(
                '/\R/',
                $note
            ) ?: [];

        /*
         * Source-created notes currently contain
         * customer/phone/email metadata.
         * Lead scoring does not need these.
         */
        $lines = collect($lines)
            ->reject(function ($line) {
                return preg_match(
                    '/^\s*(phone|email|customer|name)\s*:/i',
                    (string) $line
                ) === 1;
            })
            ->values()
            ->all();

        $note =
            implode(
                PHP_EOL,
                $lines
            );

        /*
         * Remove obvious email addresses.
         */
        $note = preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
            '[email removed]',
            $note
        );

        /*
         * Remove plain 10-15 digit numbers.
         * Does not remove dates such as 07-09-2026.
         */
        $note = preg_replace(
            '/(?<!\d)\+?\d{10,15}(?!\d)/',
            '[phone removed]',
            (string) $note
        );

        return Str::limit(
            trim(
                (string) $note
            ),
            600,
            ''
        );
    }
}
