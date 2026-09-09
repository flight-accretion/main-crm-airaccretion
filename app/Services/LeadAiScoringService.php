<?php

namespace App\Services;

use App\Exceptions\AiProviderException;
use App\Jobs\ProcessLeadAiScore;
use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\LeadFollowup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeadAiScoringService
{
    public const PROMPT_VERSION = LeadAiOpenAiClient::PROMPT_VERSION;

    public function __construct(
        private LeadAiScoringEligibilityService $eligibility,
        private LeadAiLifecycleService $lifecycle,
        private LeadAiPayloadBuilder $payloadBuilder,
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
            || $this->lifecycle->isBooked(
                $lead
            )
            || !$this->lifecycle->hasActiveStatus(
                $lead
            )
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
                            . '|'
                            . (
                                $followup
                                    ->customer_not_picked_up
                                    ? '1'
                                    : '0'
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
        )->afterCommit();

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

            if ($this->lifecycle->isBooked($lead)) {
                $score->update([
                    'status' =>
                        'skipped',

                    'processed_at' =>
                        now(),

                    'last_error' =>
                        null,
                ]);

                return;
            }

            if (!$this->lifecycle->hasActiveStatus($lead)) {
                $score->update([
                    'status' =>
                        'skipped',

                    'processed_at' =>
                        now(),

                    'last_error' =>
                        null,
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

            $payload =
                $this->payloadBuilder->build(
                    $lead,
                    $followup,
                    $previous
                );

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

                'thinking_tokens' =>
                    data_get(
                        $result,
                        'usage.thinking_tokens'
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
        } catch (AiProviderException $e) {
            $score->update([
                'status' =>
                    'failed',

                'last_error' =>
                    Str::limit(
                        $e->getMessage(),
                        2000
                    ),
            ]);

            if ($e->retryable) {
                throw $e;
            }
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
}
