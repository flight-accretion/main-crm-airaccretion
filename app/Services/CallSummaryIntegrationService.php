<?php

namespace App\Services;

use App\Models\CallSummaryIntegration;
use App\Models\IvrAgent;
use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CallSummaryIntegrationService
{
    /*
    |--------------------------------------------------------------------------
    | Create/store incoming event
    |--------------------------------------------------------------------------
    */

    public function receive(
        array $payload
    ): CallSummaryIntegration {

        $phone =
            $this->normalizePhone(
                $payload['phone_number']
                ?? ''
            );


        $agent =
            $this->normalizeAgent(
                $payload['agent_name']
                ?? ''
            );


        $start =
            Carbon::parse(
                $payload['call_start_at']
            );


        $end =
            Carbon::parse(
                $payload['call_end_at']
            );


        $direction =
            strtolower(
                trim(
                    (string)
                    $payload['direction']
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Generate deterministic unique fingerprint
        |--------------------------------------------------------------------------
        |
        | Third party has no unique call ID.
        |
        | This prevents duplicate followups if webhook
        | is retried.
        |
        */

        $fingerprint = hash(
            'sha256',
            implode(
                '|',
                [
                    $phone,
                    $agent,
                    $start->format(
                        'Y-m-d H:i:s'
                    ),
                    $end->format(
                        'Y-m-d H:i:s'
                    ),
                    $direction,
                ]
            )
        );


        /*
        |--------------------------------------------------------------------------
        | Idempotency
        |--------------------------------------------------------------------------
        */

        $existing =
            CallSummaryIntegration::query()
                ->where(
                    'call_fingerprint',
                    $fingerprint
                )
                ->first();


        if ($existing) {

            return $existing;
        }


        /*
        |--------------------------------------------------------------------------
        | Save event BEFORE trying to match
        |--------------------------------------------------------------------------
        */

        $integration =
            CallSummaryIntegration::create([

                'call_fingerprint' =>
                    $fingerprint,

                'phone_number' =>
                    trim(
                        (string)
                        $payload[
                            'phone_number'
                        ]
                    ),

                'normalized_phone' =>
                    $phone,

                'summary' =>
                    trim(
                        (string)
                        $payload['summary']
                    ),

                'followup_date' =>
                    !empty(
                        $payload[
                            'followup_date'
                        ]
                    )
                        ? Carbon::parse(
                            $payload[
                                'followup_date'
                            ]
                        )
                        : null,

                'call_start_at' =>
                    $start,

                'call_end_at' =>
                    $end,

                'agent_name' =>
                    trim(
                        (string)
                        $payload[
                            'agent_name'
                        ]
                    ),

                'normalized_agent_name' =>
                    $agent,

                'direction' =>
                    $direction,

                'sentiment_score' =>
                    array_key_exists(
                        'sentiment_score',
                        $payload
                    )
                        ? $payload[
                            'sentiment_score'
                        ]
                        : null,

                'status' =>
                    'received',

                'attempt_count' =>
                    0,

                'payload' =>
                    $payload,
            ]);


        /*
        |--------------------------------------------------------------------------
        | Try immediate processing
        |--------------------------------------------------------------------------
        */

        return $this->process(
            $integration
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Process/retry a stored call summary
    |--------------------------------------------------------------------------
    */

   public function process(
    CallSummaryIntegration $integration
): CallSummaryIntegration {

    /*
    |--------------------------------------------------------------------------
    | Already completed
    |--------------------------------------------------------------------------
    |
    | Never create a duplicate CRM follow-up.
    |
    */

    if (
        $integration->status === 'followup_created'
        &&
        !empty($integration->followup_id)
    ) {
        return $integration;
    }


    /*
    |--------------------------------------------------------------------------
    | Increment processing attempt
    |--------------------------------------------------------------------------
    */

    $integration->attempt_count =
        ((int) $integration->attempt_count) + 1;

    $integration->last_error = null;

    $integration->save();


    try {

        /*
        |--------------------------------------------------------------------------
        | Resolve API / IVR agent to CRM user
        |--------------------------------------------------------------------------
        */

        $agentUser =
            $this->resolveAgentUser(
                $integration
            );


        if ($agentUser) {

            $integration->agent_user_id =
                $agentUser->id;

            $integration->save();
        }


        /*
        |--------------------------------------------------------------------------
        | PRIORITY 1
        | Find matching IVR call
        |--------------------------------------------------------------------------
        */

        $ivrMatch =
            $this->findBestIvrMatch(
                $integration,
                $agentUser
            );


        if ($ivrMatch) {

            $ivrLog =
                $ivrMatch['log'];


            $integration->ivr_call_log_id =
                $ivrLog->id;

            $integration->match_score =
                $ivrMatch['score'];

            $integration->match_method =
                'ivr_phone_agent_time';

            $integration->save();


            /*
            |--------------------------------------------------------------------------
            | IVR already has CRM lead
            |--------------------------------------------------------------------------
            */

            if (!empty($ivrLog->lead_id)) {

                $lead =
                    Lead::find(
                        $ivrLog->lead_id
                    );


                if ($lead) {

                    $integration->lead_id =
                        $lead->id;

                    $integration->status =
                        'matched';

                    $integration->last_error =
                        null;

                    $integration->save();


                    return $this->createFollowup(
                        $integration,
                        $lead,
                        $agentUser
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | IMPORTANT FALLBACK
            |--------------------------------------------------------------------------
            |
            | We found the IVR call, but its lead_id may not yet be populated.
            |
            | DO NOT return pending_lead here.
            |
            | The CRM lead may already exist and ActiveLeadService may be able
            | to find it from the customer phone number.
            |
            */

            $activeLead =
                $this->findActiveLead(
                    $integration
                );


            if ($activeLead) {

                /*
                |--------------------------------------------------------------------------
                | Agent safety check
                |--------------------------------------------------------------------------
                |
                | Do not attach Sourav's call to another salesperson's lead
                | when both identities are known.
                |
                */

                if (
                    $agentUser
                    &&
                    !empty(
                        $activeLead->representative_user_id
                    )
                    &&
                    (string)
                    $activeLead->representative_user_id
                    !==
                    (string)
                    $agentUser->id
                ) {

                    $integration->status =
                        'ambiguous_match';

                    $integration->match_method =
                        'ivr_active_lead_agent_mismatch';

                    $integration->last_error =
                        'IVR call matched and phone matched an active lead, but the call agent does not match the lead representative.';

                    $integration->save();


                    return $integration;
                }


                /*
                |--------------------------------------------------------------------------
                | Connect integration to CRM lead
                |--------------------------------------------------------------------------
                */

                $integration->lead_id =
                    $activeLead->id;

                $integration->match_method =
                    'ivr_plus_active_lead_phone';

                $integration->status =
                    'matched';

                $integration->last_error =
                    null;

                $integration->save();


                /*
                |--------------------------------------------------------------------------
                | Optional but recommended:
                | attach lead back to IVR record
                |--------------------------------------------------------------------------
                |
                | This makes the IVR record useful for future processing.
                |
                */

                if (empty($ivrLog->lead_id)) {

                    $ivrLog->lead_id =
                        $activeLead->id;

                    $ivrLog->save();
                }


                /*
                |--------------------------------------------------------------------------
                | CREATE CRM FOLLOW-UP
                |--------------------------------------------------------------------------
                */

                return $this->createFollowup(
                    $integration,
                    $activeLead,
                    $agentUser
                );
            }


            /*
            |--------------------------------------------------------------------------
            | IVR exists but CRM lead genuinely unavailable
            |--------------------------------------------------------------------------
            */

            $integration->status =
                'pending_lead';

            $integration->last_error =
                'IVR call matched, but no CRM lead is currently available.';

            $integration->save();


            return $integration;
        }


        /*
        |--------------------------------------------------------------------------
        | PRIORITY 2
        | No IVR match - search CRM directly by phone
        |--------------------------------------------------------------------------
        */

        $activeLead =
            $this->findActiveLead(
                $integration
            );


        if ($activeLead) {

            /*
            |--------------------------------------------------------------------------
            | Agent consistency protection
            |--------------------------------------------------------------------------
            */

            if (
                $agentUser
                &&
                !empty(
                    $activeLead->representative_user_id
                )
                &&
                (string)
                $activeLead->representative_user_id
                !==
                (string)
                $agentUser->id
            ) {

                $integration->status =
                    'ambiguous_match';

                $integration->match_method =
                    'active_lead_agent_mismatch';

                $integration->last_error =
                    'Phone matched an active lead but the mapped call agent does not match the lead representative.';

                $integration->save();


                return $integration;
            }


            /*
            |--------------------------------------------------------------------------
            | CRM lead found
            |--------------------------------------------------------------------------
            */

            $integration->lead_id =
                $activeLead->id;

            $integration->match_score =
                $agentUser
                    ? 80
                    : 70;

            $integration->match_method =
                'active_lead_phone_agent';

            $integration->status =
                'matched';

            $integration->last_error =
                null;

            $integration->save();


            return $this->createFollowup(
                $integration,
                $activeLead,
                $agentUser
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Nothing found yet
        |--------------------------------------------------------------------------
        */

        $integration->status =
            'pending_lead';

        $integration->last_error =
            'No safe IVR or active CRM lead match is currently available.';

        $integration->save();


        return $integration;


    } catch (\Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Processing failure
        |--------------------------------------------------------------------------
        */

        $integration->status =
            'failed';

        $integration->last_error =
            mb_substr(
                $e->getMessage(),
                0,
                5000
            );

        $integration->save();


        Log::error(
            'Call Summary integration processing failed.',
            [
                'integration_id' =>
                    $integration->id,

                'phone' =>
                    $integration->normalized_phone,

                'error' =>
                    $e->getMessage(),

                'trace' =>
                    $e->getTraceAsString(),
            ]
        );


        return $integration;
    }
}


    /*
    |--------------------------------------------------------------------------
    | Find best IVR call
    |--------------------------------------------------------------------------
    */

    private function findBestIvrMatch(
        CallSummaryIntegration $integration,
        ?User $agentUser
    ): ?array {

        $window =
            (int)
            config(
                'call_summary.ivr_match_window_minutes',
                10
            );


        $minimumScore =
            (int)
            config(
                'call_summary.minimum_match_score',
                70
            );


        $start =
            Carbon::parse(
                $integration->call_start_at
            );


        /*
        |--------------------------------------------------------------------------
        | Search by TIME first
        |--------------------------------------------------------------------------
        |
        | Important because the supplied phone might
        | sometimes be the common company/DNI number.
        |
        */

        $logs =
            IvrCallLog::query()
                ->whereBetween(
                    'call_start_at',
                    [
                        $start
                            ->copy()
                            ->subMinutes(
                                $window
                            ),

                        $start
                            ->copy()
                            ->addMinutes(
                                $window
                            ),
                    ]
                )
                ->get();


        if ($logs->isEmpty()) {

            return null;
        }


        $apiPhone =
            $integration
                ->normalized_phone;

        $apiAgent =
            $integration
                ->normalized_agent_name;


        /*
        |--------------------------------------------------------------------------
        | Score each IVR candidate
        |--------------------------------------------------------------------------
        */

        $scored =
            $logs
                ->map(
                    function (
                        IvrCallLog $log
                    ) use (
                        $integration,
                        $start,
                        $apiPhone,
                        $apiAgent
                    ) {

                        $score = 0;


                        /*
                        |--------------------------------------------------------------------------
                        | CUSTOMER NUMBER MATCH
                        |--------------------------------------------------------------------------
                        |
                        | normalized_phone / CLI is strong.
                        |
                        */

                        $logPhone =
                            $this
                                ->normalizePhone(
                                    $log
                                        ->normalized_phone
                                    ?:
                                    $log->cli
                                    ?:
                                    ''
                                );


                        if (
                            $apiPhone !== ''
                            &&
                            $logPhone !== ''
                            &&
                            $apiPhone
                                ===
                            $logPhone
                        ) {

                            $score += 50;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | DNI / company number match
                        |--------------------------------------------------------------------------
                        |
                        | Weak signal because company number can
                        | be identical for every call.
                        |
                        */

                        $dni =
                            $this
                                ->normalizePhone(
                                    $log->dni
                                    ?? ''
                                );


                        if (
                            $apiPhone !== ''
                            &&
                            $dni !== ''
                            &&
                            $apiPhone === $dni
                            &&
                            $apiPhone !== $logPhone
                        ) {

                            $score += 5;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | AGENT MATCH
                        |--------------------------------------------------------------------------
                        */

                    /* --------------------------------------------------------------------------
| AGENT MATCH
|--------------------------------------------------------------------------
|
| Do not require exact spelling.
|
| Example:
|
| API : Saurav Namdeo
| IVR : Sourav Namdeo
|
*/

$logAgent =
    $this->normalizeAgent(
        $log->agent_name
        ?? ''
    );


$agentSimilarity =
    $this->agentSimilarity(
        $apiAgent,
        $logAgent
    );


if ($agentSimilarity >= 95) {

    /*
     * Exact / practically exact.
     */
    $score += 25;

} elseif ($agentSimilarity >= 85) {

    /*
     * Minor spelling error.
     */
    $score += 22;

} elseif ($agentSimilarity >= 75) {

    /*
     * Reasonable similarity,
     * but weaker evidence.
     */
    $score += 12;
}


                        /*
                        |--------------------------------------------------------------------------
                        | CALL TIME MATCH
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $log
                                ->call_start_at
                        ) {

                            $difference =
                                abs(
                                    Carbon::parse(
                                        $log
                                            ->call_start_at
                                    )
                                        ->diffInSeconds(
                                            $start,
                                            false
                                        )
                                );


                            if (
                                $difference
                                <= 60
                            ) {

                                $score += 25;

                            } elseif (
                                $difference
                                <= 180
                            ) {

                                $score += 20;

                            } elseif (
                                $difference
                                <= 600
                            ) {

                                $score += 10;
                            }
                        }

                        /* --------------------------------------------------------------------------
| COMPANY DNI FALLBACK
|--------------------------------------------------------------------------
|
| Some third-party recording/summarization systems send our
| common company/DNI number instead of the customer CLI.
|
| DNI alone is NOT enough because the same number is used
| across many calls.
|
| But:
|
| same DNI
| + same/fuzzy agent
| + very close call start time
|
| is strong enough to identify the IVR call safely.
|
*/

$isDniMatch =
    $apiPhone !== ''
    &&
    $dni !== ''
    &&
    $apiPhone === $dni
    &&
    $apiPhone !== $logPhone;


if (
    $isDniMatch
    &&
    $agentSimilarity >= 85
    &&
    isset($difference)
) {

    /*
     * Summary provider and VI timestamps can differ
     * slightly because one may use connected-call time
     * while the other uses IVR call-start time.
     */

    if ($difference <= 30) {

        $score += 30;

    } elseif ($difference <= 60) {

        $score += 25;

    } elseif ($difference <= 120) {

        $score += 15;
    }
}

/* --------------------------------------------------------------------------
| CALL END TIME MATCH
|--------------------------------------------------------------------------
|
| This is useful because two calls may start near each other,
| but start + end together is much more specific.
|
*/

if (
    $log->call_end_at
    &&
    $integration->call_end_at
) {

    $endDifference =
        abs(
            Carbon::parse(
                $log->call_end_at
            )->diffInSeconds(
                Carbon::parse(
                    $integration->call_end_at
                ),
                false
            )
        );


    if ($endDifference <= 30) {

        $score += 15;

    } elseif ($endDifference <= 90) {

        $score += 10;

    } elseif ($endDifference <= 180) {

        $score += 5;
    }
}


                        /*
                        |--------------------------------------------------------------------------
                        | Already connected to CRM lead
                        |--------------------------------------------------------------------------
                        */

                        if (
                            !empty(
                                $log->lead_id
                            )
                        ) {

                            $score += 5;
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Direction assistance
                        |--------------------------------------------------------------------------
                        |
                        | Existing IVR records may expose
                        | call_type_code rather than literal
                        | incoming/outgoing.
                        |
                        | Therefore direction is intentionally
                        | NOT used as a hard filter.
                        |
                        */


                        return [
                            'log' =>
                                $log,

                            'score' =>
                                $score,
                        ];
                    }
                )
                ->sortByDesc(
                    'score'
                )
                ->values();


        if ($scored->isEmpty()) {

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Unique candidate bonus
        |--------------------------------------------------------------------------
        |
        | This is particularly important when the
        | API phone is the common company IVR number.
        |
        */

        if (
            $scored->count()
            === 1
        ) {

            $first =
                $scored->first();

            $first['score'] += 10;

            $scored[0] =
                $first;
        }


        $best =
            $scored->first();


        if (
            !$best
            ||
            $best['score']
                <
            $minimumScore
        ) {

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Ambiguity protection
        |--------------------------------------------------------------------------
        |
        | If two calls are almost equally likely,
        | do not attach automatically.
        |
        */

        $second =
            $scored->get(1);


        if (
            $second
            &&
            (
                $best['score']
                -
                $second['score']
            )
            < 10
        ) {

            return null;
        }


        return $best;
    }


    /*
    |--------------------------------------------------------------------------
    | Agent -> CRM User
    |--------------------------------------------------------------------------
    */

    private function resolveAgentUser(
        CallSummaryIntegration $integration
    ): ?User {

        $normalized =
            $integration
                ->normalized_agent_name;


        if (!$normalized) {

            return null;
        }


        /*
        |--------------------------------------------------------------------------
        | Prefer your existing IVR Agent Mapping
        |--------------------------------------------------------------------------
        */

        $agents =
            IvrAgent::query()
                ->where(
                    'is_active',
                    true
                )
                ->whereNotNull(
                    'mapped_user_id'
                )
                ->get();


        foreach (
            $agents
            as
            $agent
        ) {

            if (
                $this->normalizeAgent(
                    $agent->vi_agent_name
                )
                ===
                $normalized
            ) {

                return User::query()
                    ->where(
                        'id',
                        $agent
                            ->mapped_user_id
                    )
                    ->where(
                        'status',
                        1
                    )
                    ->first();
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Conservative fallback by User name
        |--------------------------------------------------------------------------
        */

        $users =
            User::query()
                ->where(
                    'status',
                    1
                )
                ->get([
                    'id',
                    'name',
                    'status',
                ]);


        foreach (
            $users
            as
            $user
        ) {

            if (
                $this->normalizeAgent(
                    $user->name
                )
                ===
                $normalized
            ) {

                return $user;
            }
        }


        return null;
    }


    /*
    |--------------------------------------------------------------------------
    | Existing active CRM Lead fallback
    |--------------------------------------------------------------------------
    */

    private function findActiveLead(
        CallSummaryIntegration $integration
    ): ?Lead {

        if (
            empty(
                $integration
                    ->normalized_phone
            )
        ) {

            return null;
        }


        try {

            return app(
                ActiveLeadService::class
            )->findByPhone(
                $integration
                    ->normalized_phone
            );

        } catch (\Throwable $e) {

            Log::warning(
                'Active Lead fallback failed during Call Summary matching.',
                [
                    'integration_id' =>
                        $integration->id,

                    'error' =>
                        $e->getMessage(),
                ]
            );


            return null;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Create NEW follow-up
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | This never updates an existing followup.
    |
    */

    private function createFollowup(
        CallSummaryIntegration $integration,
        Lead $lead,
        ?User $agentUser
    ): CallSummaryIntegration {

        /*
        |--------------------------------------------------------------------------
        | Final idempotency guard
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $integration
                    ->followup_id
            )
        ) {

            $existing =
                LeadFollowup::find(
                    $integration
                        ->followup_id
                );


            if ($existing) {

                $integration
                    ->status =
                        'followup_created';

                $integration
                    ->processed_at =
                        now();

                $integration
                    ->save();


                return $integration;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Resolve followed_by
        |--------------------------------------------------------------------------
        |
        | First: mapped call agent
        | Fallback: current lead representative
        |
        */

        $followedBy =
            $agentUser
                ? $agentUser->id
                : $lead
                    ->representative_user_id;


        /*
         * Don't invent/fake a CRM user.
         */
        if (empty($followedBy)) {

            $integration->status =
                'pending_lead';

            $integration
                ->last_error =
                    'Lead matched but no CRM user could be resolved for followed_by.';

            $integration->save();

            return $integration;
        }


        /*
        |--------------------------------------------------------------------------
        | Preserve current lead status
        |--------------------------------------------------------------------------
        |
        | A call summary should NOT silently change
        | lead status.
        |
        | Use latest followup status if one exists.
        |
        */

        $latestFollowup =
            LeadFollowup::query()
                ->where(
                    'lead_id',
                    $lead->id
                )
                ->latest(
                    'created_at'
                )
                ->first();


        $status =
            $latestFollowup
                ? $latestFollowup->status
                : 1;


        /*
        |--------------------------------------------------------------------------
        | Transaction
        |--------------------------------------------------------------------------
        */

        DB::transaction(
            function () use (
                $integration,
                $lead,
                $followedBy,
                $status
            ) {

                /*
                |--------------------------------------------------------------------------
                | Lock integration row to stop two simultaneous
                | retries from creating two followups.
                |--------------------------------------------------------------------------
                */

                $locked =
                    CallSummaryIntegration::query()
                        ->where(
                            'id',
                            $integration->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                if (
                    !empty(
                        $locked
                            ->followup_id
                    )
                ) {

                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Create a completely NEW CRM followup
                |--------------------------------------------------------------------------
                */

                $followup =
                    LeadFollowup::create([

                        'id' =>
                            (string)
                            Str::uuid(),

                        'lead_id' =>
                            $lead->id,

                        /*
                         * Summary and note are the SAME field.
                         */
                        'followup_note' =>
                            $integration
                                ->summary,

                        'next_followup_date' =>
                            $integration
                                ->followup_date,

                        /*
                         * Existing CRM salesperson.
                         */
                        'followed_by' =>
                            $followedBy,

                        /*
                         * Preserve existing pipeline status.
                         */
                        'status' =>
                            $status,
                    ]);


                /*
                |--------------------------------------------------------------------------
                | Complete integration
                |--------------------------------------------------------------------------
                */

                $locked->lead_id =
                    $lead->id;

                $locked->agent_user_id =
                    $followedBy;

                $locked->followup_id =
                    $followup->id;

                $locked->status =
                    'followup_created';

                $locked->processed_at =
                    now();

                $locked->last_error =
                    null;

                $locked->save();


                /*
                 * Keep local model synchronized.
                 */

                $integration
                    ->followup_id =
                        $followup->id;

                $integration
                    ->lead_id =
                        $lead->id;

                $integration
                    ->agent_user_id =
                        $followedBy;

                $integration->status =
                    'followup_created';

                $integration
                    ->processed_at =
                        now();
            }
        );


        return $integration->fresh();
    }


    /*
    |--------------------------------------------------------------------------
    | Normalization
    |--------------------------------------------------------------------------
    */

    private function normalizePhone(
        ?string $phone
    ): string {

        $digits =
            preg_replace(
                '/\D+/',
                '',
                (string) $phone
            );


        if (!$digits) {

            return '';
        }


        /*
         * Indian CRM matching:
         * use final 10 digits so:
         *
         * +91-9876543210
         * 919876543210
         * 9876543210
         *
         * all match.
         */

        if (
            strlen($digits)
            > 10
        ) {

            $digits =
                substr(
                    $digits,
                    -10
                );
        }


        return $digits;
    }


    private function normalizeAgent(
        ?string $name
    ): string {

        $name =
            trim(
                mb_strtolower(
                    (string) $name
                )
            );


        /*
         * Collapse repeated whitespace.
         */

        $name =
            preg_replace(
                '/\s+/u',
                ' ',
                $name
            );


        return $name
            ?: '';
    }

    /**
 * Compare agent names safely.
 *
 * Handles minor spelling differences such as:
 *
 * Sourav Namdeo
 * Saurav Namdeo
 */
private function agentSimilarity(
    ?string $first,
    ?string $second
): float {

    $first = $this->normalizeAgent(
        $first ?? ''
    );

    $second = $this->normalizeAgent(
        $second ?? ''
    );

    if (
        $first === ''
        ||
        $second === ''
    ) {
        return 0;
    }


    /*
    |--------------------------------------------------------------------------
    | Exact normalized match
    |--------------------------------------------------------------------------
    */

    if ($first === $second) {
        return 100;
    }


    /*
    |--------------------------------------------------------------------------
    | Similarity percentage
    |--------------------------------------------------------------------------
    */

    similar_text(
        $first,
        $second,
        $percentage
    );


    /*
    |--------------------------------------------------------------------------
    | Also consider Levenshtein distance
    |--------------------------------------------------------------------------
    |
    | This is particularly useful for:
    |
    | Sourav / Saurav
    |
    */

    $distance =
        levenshtein(
            $first,
            $second
        );


    $maxLength =
        max(
            strlen($first),
            strlen($second)
        );


    if ($maxLength > 0) {

        $levenshteinPercentage =
            (
                1
                -
                (
                    $distance
                    /
                    $maxLength
                )
            )
            * 100;


        $percentage =
            max(
                $percentage,
                $levenshteinPercentage
            );
    }


    return round(
        $percentage,
        2
    );
}
}