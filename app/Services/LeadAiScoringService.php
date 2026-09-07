<?php

namespace App\Services;

use App\Jobs\ProcessLeadAiScore;
use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\LeadFollowup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadAiScoringService
{
    public function __construct(
        private LeadAiScoringEligibilityService $eligibility,
        private LeadAiCurrentFactsService $facts,
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
                            $followup->id
                            . '|'
                            . $followup->lead_id
                            . '|'
                            . (
                                $followup
                                    ->followup_note
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

            $payload = [
                'current_crm_facts' =>
                    $this->facts->build(
                        $lead
                    ),

                'previous_ai_state' =>
                    $previous
                        ? [
                            'temperature' =>
                                $previous
                                    ->temperature,

                            'score' =>
                                $previous
                                    ->score,

                            'confidence' =>
                                $previous
                                    ->confidence,

                            'summary' =>
                                $previous
                                    ->summary,

                            'suggested_action' =>
                                $previous
                                    ->suggested_action,

                            'state' =>
                                $previous
                                    ->state_json,
                        ]
                        : null,

                /*
                 * ONLY this newly created
                 * raw follow-up is sent.
                 */
                'new_followup' => [
                    'note' =>
                        $this->sanitizeNote(
                            (string)
                            $followup
                                ->followup_note
                        ),

                    'status' =>
                        (int)
                        $followup->status,

                    'created_at' =>
                        $followup->created_at
                            ? $followup
                                ->created_at
                                ->copy()
                                ->timezone(
                                    'Asia/Kolkata'
                                )
                                ->format(
                                    'd-M-Y h:i A'
                                )
                                . ' IST'
                            : null,
                ],
            ];

            $inputHash =
                hash(
                    'sha256',
                    json_encode(
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
                    $result['temperature'],

                'score' =>
                    $result['score'],

                'confidence' =>
                    $result['confidence'],

                'summary' =>
                    $result['summary'],

                'suggested_action' =>
                    $result[
                        'suggested_action'
                    ],

                'score_change_reason' =>
                    $result[
                        'score_change_reason'
                    ],

                'state_json' =>
                    $result['state'],

                'input_hash' =>
                    $inputHash,

                'model' =>
                    $result['model'],

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

        return trim(
            (string) $note
        );
    }
}