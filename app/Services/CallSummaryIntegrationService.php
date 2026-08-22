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


        $recordingId =
            $this->normalizeRecordingId(
                $payload['followup_recording_id']
                ?? null
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
        | Keep the old fingerprint stable for backward compatibility.
        | followup_recording_id is used after lead matching to update
        | the exact CRM follow-up for that lead.
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


        $attributes = [

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

            'payload' =>
                $payload,
        ];


        if ($recordingId !== null) {

            $attributes['followup_recording_id'] =
                $recordingId;
        }


        if (!empty($payload['lead_id'])) {

            $attributes['lead_id'] =
                trim(
                    (string)
                    $payload['lead_id']
                );
        }


        Log::info(
            'Call Summary integration normalized payload.',
            [
                'payload_debug' =>
                    $this->payloadDebugContext(
                        $payload
                    ),

                'normalized_phone' =>
                    $phone,

                'normalized_agent_name' =>
                    $agent,

                'normalized_recording_id' =>
                    $recordingId,

                'parsed_followup_date' =>
                    $this->dateForLog(
                        $attributes[
                            'followup_date'
                        ]
                    ),

                'call_fingerprint' =>
                    $fingerprint,
            ]
        );


        /*
        |--------------------------------------------------------------------------
        | Idempotency / payload refresh
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

            Log::info(
                'Call Summary integration existing record found; refreshing payload.',
                [
                    'integration_id' =>
                        $existing
                            ->id,

                    'status_before' =>
                        $existing
                            ->status,

                    'followup_id_before' =>
                        $existing
                            ->followup_id,

                    'followup_recording_id_before' =>
                        $existing
                            ->followup_recording_id,

                    'incoming_followup_recording_id' =>
                        $recordingId,

                    'incoming_summary_preview' =>
                        $this->previewForLog(
                            $attributes[
                                'summary'
                            ]
                        ),

                    'incoming_followup_date' =>
                        $this->dateForLog(
                            $attributes[
                                'followup_date'
                            ]
                        ),
                ]
            );

            $existing->fill(
                $attributes
            );

            $existing->save();

            return $this->process(
                $existing->fresh()
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Save event BEFORE trying to match
        |--------------------------------------------------------------------------
        */

        $integration =
            CallSummaryIntegration::create(
                array_merge(
                    $attributes,
                    [
                        'call_fingerprint' =>
                            $fingerprint,

                        'status' =>
                            'received',

                        'attempt_count' =>
                            0,
                    ]
                )
            );

        Log::info(
            'Call Summary integration stored new record.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'followup_recording_id' =>
                    $integration
                        ->followup_recording_id,

                'summary_preview' =>
                    $this->previewForLog(
                        $integration
                            ->summary
                    ),

                'followup_date' =>
                    $this->dateForLog(
                        $integration
                            ->followup_date
                    ),
            ]
        );


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

    $recordingId =
        $this->normalizeRecordingId(
            $integration->followup_recording_id
        );

    Log::info(
        'Call Summary integration processing started.',
        [
            'integration_id' =>
                $integration
                    ->id,

            'status' =>
                $integration
                    ->status,

            'lead_id' =>
                $integration
                    ->lead_id,

            'followup_id' =>
                $integration
                    ->followup_id,

            'followup_recording_id' =>
                $recordingId,

            'summary_preview' =>
                $this->previewForLog(
                    $integration
                        ->summary
                ),

            'followup_date' =>
                $this->dateForLog(
                    $integration
                        ->followup_date
                ),
        ]
    );

    if (
        in_array(
            $integration->status,
            [
                'followup_created',
                'followup_updated',
            ],
            true
        )
        &&
        !empty($integration->followup_id)
        &&
        $recordingId === null
    ) {

        Log::info(
            'Call Summary integration already completed; skipping duplicate follow-up creation.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'status' =>
                    $integration
                        ->status,

                'followup_id' =>
                    $integration
                        ->followup_id,

                'followup_recording_id' =>
                    $recordingId,
            ]
        );

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
        | PRIORITY 0
        | Exact CRM lead supplied by Skyrack
        |--------------------------------------------------------------------------
        |
        | The outbound lead sync sends our CRM UUID. When Skyrack returns a
        | call summary with that same lead_id, it is the strongest match, but
        | we still compare the customer phone when the CRM lead has a phone.
        |
        */

        if (!empty($integration->lead_id)) {

            $providedLead =
                Lead::query()
                    ->with('client')
                    ->find(
                        $integration->lead_id
                    );


            if (!$providedLead) {

                $integration->status =
                    'pending_lead';

                $integration->match_method =
                    'provided_lead_id_missing';

                $integration->last_error =
                    'Call summary supplied a CRM lead_id, but that lead does not currently exist.';

                $integration->save();


                return $integration;
            }


            if (
                !$this->leadPhoneMatches(
                    $providedLead,
                    $integration->normalized_phone
                )
            ) {

                $integration->status =
                    'ambiguous_match';

                $integration->match_method =
                    'provided_lead_id_phone_mismatch';

                $integration->match_score =
                    0;

                $integration->last_error =
                    'Call summary supplied a CRM lead_id, but the phone number does not match that lead client.';

                $integration->save();


                return $integration;
            }


            $integration->match_score =
                100;

            $integration->match_method =
                'provided_lead_id';

            $integration->status =
                'matched';

            $integration->last_error =
                null;

            $integration->save();


            return $this->createOrUpdateFollowup(
                $integration,
                $providedLead,
                $agentUser
            );
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


                    return $this->createOrUpdateFollowup(
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

                return $this->createOrUpdateFollowup(
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


            return $this->createOrUpdateFollowup(
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
    try {
        $phone = preg_replace(
            '/\D+/',
            '',
            (string) $integration->normalized_phone
        );

        if (empty($phone)) {
            return null;
        }

        // Keep only last 10 digits so these all match:
        // +91-8976168115
        // 918976168115
        // 8976168115
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        /*
        |--------------------------------------------------------------------------
        | 1. First prefer the existing ActiveLeadService
        |--------------------------------------------------------------------------
        |
        | If an ACTIVE lead exists, this remains the safest match.
        |
        */
        $activeLead = app(
            ActiveLeadService::class
        )->findByPhone($phone);

        if ($activeLead) {
            return $activeLead;
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Fall back to latest existing CRM lead
        |--------------------------------------------------------------------------
        |
        | Call summaries belong to existing calls/leads even if the latest
        | follow-up is Cancelled/Closed/etc.
        |
        | This fallback is ONLY for call-summary matching.
        | It does not change CRM duplicate-lead protection.
        |
        */

        if (config('database.default') === 'pgsql') {

            $phoneExpression =
                "regexp_replace(clients.contact_number, '[^0-9]', '', 'g')";

            $alternatePhoneExpression =
                "regexp_replace(clients.alternate_number, '[^0-9]', '', 'g')";

        } else {

            $phoneExpression =
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                "clients.contact_number, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";

            $alternatePhoneExpression =
                "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
                "clients.alternate_number, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";
        }

        return Lead::query()
            ->join(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )
            ->where(function ($query) use (
                $phoneExpression,
                $alternatePhoneExpression,
                $phone
            ) {
                $query
                    ->whereRaw(
                        "{$phoneExpression} LIKE ?",
                        ['%' . $phone]
                    )
                    ->orWhereRaw(
                        "{$alternatePhoneExpression} LIKE ?",
                        ['%' . $phone]
                    );
            })
            ->select('leads.*')
            ->orderByDesc('leads.created_at')
            ->first();

    } catch (\Throwable $e) {

        Log::warning(
            'Call summary lead lookup failed',
            [
                'integration_id' =>
                    $integration->id,

                'phone' =>
                    $integration->normalized_phone,

                'error' =>
                    $e->getMessage(),
            ]
        );

        return null;
    }
}


    private function leadPhoneMatches(
        Lead $lead,
        ?string $normalizedPhone
    ): bool {

        $phone =
            $this->normalizePhone(
                $normalizedPhone
            );


        if ($phone === '') {

            return false;
        }


        $lead->loadMissing(
            'client'
        );


        $crmPhones =
            collect(
                [
                    optional($lead->client)->contact_number,
                    optional($lead->client)->alternate_number,
                ]
            )
                ->map(function ($crmPhone) {
                    return $this->normalizePhone(
                        $crmPhone
                    );
                })
                ->filter()
                ->values()
                ->all();


        if (empty($crmPhones)) {

            return true;
        }


        return in_array(
            $phone,
            $crmPhones,
            true
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Create or update follow-up
    |--------------------------------------------------------------------------
    |
    | lead_id alone is not enough for duplicate checks because one lead can
    | have multiple follow-ups. When followup_recording_id is supplied,
    | lead_id + followup_recording_id identifies the CRM follow-up to update.
    |
    */

    private function createOrUpdateFollowup(
        CallSummaryIntegration $integration,
        Lead $lead,
        ?User $agentUser
    ): CallSummaryIntegration {

        $followedBy =
            $agentUser
                ? $agentUser->id
                : $lead
                    ->representative_user_id;


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

        Log::info(
            'Call Summary follow-up sync entered.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'lead_id' =>
                    $lead
                        ->id,

                'incoming_followup_id' =>
                    $integration
                        ->followup_id,

                'incoming_followup_recording_id' =>
                    $this->normalizeRecordingId(
                        $integration
                            ->followup_recording_id
                    ),

                'incoming_summary_preview' =>
                    $this->previewForLog(
                        $integration
                            ->summary
                    ),

                'incoming_followup_date' =>
                    $this->dateForLog(
                        $integration
                            ->followup_date
                    ),

                'candidate_followed_by' =>
                    $followedBy,

                'latest_followup_id' =>
                    $latestFollowup
                        ? $latestFollowup
                            ->id
                        : null,

                'latest_followup_status' =>
                    $latestFollowup
                        ? $latestFollowup
                            ->status
                        : null,
            ]
        );


        DB::transaction(
            function () use (
                $integration,
                $lead,
                $followedBy,
                $status
            ) {

                $locked =
                    CallSummaryIntegration::query()
                        ->where(
                            'id',
                            $integration->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();


                $recordingId =
                    $this->normalizeRecordingId(
                        $locked->followup_recording_id
                    );


                if ($recordingId !== null) {

                    Lead::query()
                        ->where(
                            'id',
                            $lead->id
                        )
                        ->lockForUpdate()
                        ->first();
                }


                $followup = null;

                $lookupMethod = null;


                if (!empty($locked->followup_id)) {

                    $followup =
                        LeadFollowup::query()
                            ->where(
                                'id',
                                $locked->followup_id
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($followup) {

                        $lookupMethod =
                            'integration_followup_id';
                    }
                }


                if (
                    $followup
                    &&
                    (string) $followup->lead_id !== (string) $lead->id
                ) {

                    $followup = null;
                }


                if (
                    !$followup
                    &&
                    $recordingId !== null
                ) {

                    $followup =
                        LeadFollowup::query()
                            ->where(
                                'lead_id',
                                $lead->id
                            )
                            ->where(
                                'followup_recording_id',
                                $recordingId
                            )
                            ->lockForUpdate()
                            ->first();

                    if ($followup) {

                        $lookupMethod =
                            'lead_recording_id';
                    }
                }


                Log::info(
                    'Call Summary follow-up lookup completed.',
                    [
                        'integration_id' =>
                            $locked
                                ->id,

                        'lead_id' =>
                            $lead
                                ->id,

                        'locked_followup_id' =>
                            $locked
                                ->followup_id,

                        'followup_recording_id' =>
                            $recordingId,

                        'lookup_method' =>
                            $lookupMethod,

                        'found_followup_id' =>
                            $followup
                                ? $followup
                                    ->id
                                : null,

                        'incoming_summary_preview' =>
                            $this->previewForLog(
                                $locked
                                    ->summary
                            ),

                        'incoming_followup_date' =>
                            $this->dateForLog(
                                $locked
                                    ->followup_date
                            ),
                    ]
                );


                if ($followup) {

                    $effectiveFollowedBy =
                        $followedBy
                            ?: $followup->followed_by;

                    Log::info(
                        'Call Summary follow-up update target found.',
                        [
                            'integration_id' =>
                                $locked
                                    ->id,

                            'lead_id' =>
                                $lead
                                    ->id,

                            'followup_id' =>
                                $followup
                                    ->id,

                            'lookup_method' =>
                                $lookupMethod,

                            'followup_recording_id' =>
                                $recordingId,

                            'current_followup_note_preview' =>
                                $this->previewForLog(
                                    $followup
                                        ->followup_note
                                ),

                            'incoming_summary_preview' =>
                                $this->previewForLog(
                                    $locked
                                        ->summary
                                ),

                            'current_next_followup_date' =>
                                $this->dateForLog(
                                    $followup
                                        ->next_followup_date
                                ),

                            'incoming_followup_date' =>
                                $this->dateForLog(
                                    $locked
                                        ->followup_date
                                ),

                            'effective_followed_by' =>
                                $effectiveFollowedBy,
                        ]
                    );

                    $this->applyCallSummaryToFollowup(
                        $followup,
                        $locked,
                        $effectiveFollowedBy,
                        $recordingId
                    );

                    $this->completeIntegrationWithFollowup(
                        $locked,
                        $lead,
                        $followup,
                        $effectiveFollowedBy,
                        $recordingId !== null
                            ? 'followup_updated'
                            : 'followup_created'
                    );

                    return;
                }


                if (empty($followedBy)) {

                    Log::warning(
                        'Call Summary follow-up could not be saved because followed_by is missing.',
                        [
                            'integration_id' =>
                                $locked
                                    ->id,

                            'lead_id' =>
                                $lead
                                    ->id,

                            'followup_recording_id' =>
                                $recordingId,

                            'incoming_summary_preview' =>
                                $this->previewForLog(
                                    $locked
                                        ->summary
                                ),

                            'incoming_followup_date' =>
                                $this->dateForLog(
                                    $locked
                                        ->followup_date
                                ),
                        ]
                    );

                    $locked->status =
                        'pending_lead';

                    $locked->last_error =
                        'Lead matched but no CRM user could be resolved for followed_by.';

                    $locked->save();

                    return;
                }

                Log::info(
                    'Call Summary follow-up target not found; creating new follow-up.',
                    [
                        'integration_id' =>
                            $locked
                                ->id,

                        'lead_id' =>
                            $lead
                                ->id,

                        'followup_recording_id' =>
                            $recordingId,

                        'incoming_summary_preview' =>
                            $this->previewForLog(
                                $locked
                                    ->summary
                            ),

                        'incoming_followup_date' =>
                            $this->dateForLog(
                                $locked
                                    ->followup_date
                            ),

                        'followed_by' =>
                            $followedBy,
                    ]
                );


                $followup =
                    LeadFollowup::create([

                        'id' =>
                            (string)
                            Str::uuid(),

                        'lead_id' =>
                            $lead->id,

                        'followup_recording_id' =>
                            $recordingId,

                        'followup_note' =>
                            $locked
                                ->summary,

                        'next_followup_date' =>
                            $locked
                                ->followup_date,

                        'followed_by' =>
                            $followedBy,

                        'status' =>
                            $status,
                    ]);

                Log::info(
                    'Call Summary follow-up created from API summary.',
                    [
                        'integration_id' =>
                            $locked
                                ->id,

                        'lead_id' =>
                            $lead
                                ->id,

                        'followup_id' =>
                            $followup
                                ->id,

                        'followup_recording_id' =>
                            $followup
                                ->followup_recording_id,

                        'saved_followup_note_preview' =>
                            $this->previewForLog(
                                $followup
                                    ->followup_note
                            ),

                        'saved_next_followup_date' =>
                            $this->dateForLog(
                                $followup
                                    ->next_followup_date
                            ),
                    ]
                );


                $this->completeIntegrationWithFollowup(
                    $locked,
                    $lead,
                    $followup,
                    $followedBy,
                    'followup_created'
                );
            }
        );


        return $integration->fresh();
    }


    private function applyCallSummaryToFollowup(
        LeadFollowup $followup,
        CallSummaryIntegration $integration,
        ?string $followedBy,
        ?int $recordingId
    ): void {

        Log::info(
            'Call Summary follow-up applying update.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'followup_id' =>
                    $followup
                        ->id,

                'lead_id' =>
                    $followup
                        ->lead_id,

                'followup_recording_id' =>
                    $recordingId,

                'before_followup_note_preview' =>
                    $this->previewForLog(
                        $followup
                            ->followup_note
                    ),

                'incoming_summary_preview' =>
                    $this->previewForLog(
                        $integration
                            ->summary
                    ),

                'before_next_followup_date' =>
                    $this->dateForLog(
                        $followup
                            ->next_followup_date
                    ),

                'incoming_followup_date' =>
                    $this->dateForLog(
                        $integration
                            ->followup_date
                    ),

                'incoming_followed_by' =>
                    $followedBy,
            ]
        );

        $followup->followup_note =
            $integration->summary;

        $followup->next_followup_date =
            $integration->followup_date;

        if (!empty($followedBy)) {

            $followup->followed_by =
                $followedBy;
        }

        if ($recordingId !== null) {

            $followup->followup_recording_id =
                $recordingId;
        }

        $followup->save();

        $followup->refresh();

        Log::info(
            'Call Summary follow-up update saved.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'followup_id' =>
                    $followup
                        ->id,

                'lead_id' =>
                    $followup
                        ->lead_id,

                'followup_recording_id' =>
                    $followup
                        ->followup_recording_id,

                'saved_followup_note_preview' =>
                    $this->previewForLog(
                        $followup
                            ->followup_note
                    ),

                'saved_next_followup_date' =>
                    $this->dateForLog(
                        $followup
                            ->next_followup_date
                    ),

                'saved_followed_by' =>
                    $followup
                        ->followed_by,
            ]
        );
    }


    private function completeIntegrationWithFollowup(
        CallSummaryIntegration $integration,
        Lead $lead,
        LeadFollowup $followup,
        ?string $followedBy,
        string $status
    ): void {

        $integration->lead_id =
            $lead->id;

        $integration->agent_user_id =
            $followedBy;

        $integration->followup_id =
            $followup->id;

        $integration->status =
            $status;

        $integration->processed_at =
            now();

        $integration->last_error =
            null;

        $integration->save();

        Log::info(
            'Call Summary integration completed with follow-up.',
            [
                'integration_id' =>
                    $integration
                        ->id,

                'status' =>
                    $integration
                        ->status,

                'lead_id' =>
                    $integration
                        ->lead_id,

                'followup_id' =>
                    $integration
                        ->followup_id,

                'followup_recording_id' =>
                    $integration
                        ->followup_recording_id,

                'saved_followup_note_preview' =>
                    $this->previewForLog(
                        $followup
                            ->followup_note
                    ),

                'saved_next_followup_date' =>
                    $this->dateForLog(
                        $followup
                            ->next_followup_date
                    ),
            ]
        );
    }

    private function payloadDebugContext(
        array $payload
    ): array {

        $keys =
            array_keys(
                $payload
            );

        return [
            'keys' =>
                $keys,

            'lead_id' =>
                $payload['lead_id']
                ?? null,

            'phone_number' =>
                $payload['phone_number']
                ?? null,

            'agent_name' =>
                $payload['agent_name']
                ?? null,

            'direction' =>
                $payload['direction']
                ?? null,

            'call_start_at' =>
                $payload['call_start_at']
                ?? null,

            'call_end_at' =>
                $payload['call_end_at']
                ?? null,

            'followup_recording_id_present' =>
                array_key_exists(
                    'followup_recording_id',
                    $payload
                ),

            'followup_recording_id' =>
                $payload['followup_recording_id']
                ?? null,

            'summary_present' =>
                array_key_exists(
                    'summary',
                    $payload
                ),

            'summary_length' =>
                array_key_exists(
                    'summary',
                    $payload
                )
                    ? mb_strlen(
                        (string)
                        $payload['summary']
                    )
                    : null,

            'summary_preview' =>
                $this->previewForLog(
                    $payload['summary']
                    ?? null
                ),

            'followup_date_present' =>
                array_key_exists(
                    'followup_date',
                    $payload
                ),

            'followup_date' =>
                $payload['followup_date']
                ?? null,

            'possible_summary_fields' =>
                array_values(
                    array_intersect(
                        [
                            'summary',
                            'call_summary',
                            'followup_summary',
                            'followup_note',
                            'notes',
                            'note',
                        ],
                        $keys
                    )
                ),

            'possible_date_fields' =>
                array_values(
                    array_intersect(
                        [
                            'followup_date',
                            'next_followup_date',
                            'next_follow_up',
                            'follow_up_date',
                            'date',
                        ],
                        $keys
                    )
                ),
        ];
    }


    private function previewForLog(
        $value
    ): ?string {

        if ($value === null) {

            return null;
        }

        return mb_substr(
            trim(
                (string)
                $value
            ),
            0,
            500
        );
    }


    private function dateForLog(
        $value
    ): ?string {

        if (empty($value)) {

            return null;
        }

        if ($value instanceof Carbon) {

            return $value->format(
                'Y-m-d H:i:s'
            );
        }

        try {

            return Carbon::parse(
                $value
            )->format(
                'Y-m-d H:i:s'
            );

        } catch (\Throwable $e) {

            return (string)
                $value;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Normalization
    |--------------------------------------------------------------------------
    */

    private function normalizeRecordingId(
        $recordingId
    ): ?int {

        if ($recordingId === null) {

            return null;
        }


        $recordingId =
            trim(
                (string)
                $recordingId
            );


        if (
            $recordingId === ''
            ||
            !preg_match('/^\d+$/', $recordingId)
        ) {

            return null;
        }


        return (int) $recordingId;
    }

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
