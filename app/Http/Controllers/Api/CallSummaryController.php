<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallSummaryIntegration;
use App\Services\CallSummaryIntegrationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CallSummaryController extends Controller
{
    public function store(
        Request $request,
        CallSummaryIntegrationService $service
    ) {

        Log::info(
            'Call Summary API received payload.',
            [
                'ip' =>
                    $request->ip(),

                'payload_debug' =>
                    $this->payloadDebugContext(
                        $request->all()
                    ),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        $validator =
            Validator::make(
                $request->all(),
                [

                    'phone_number' =>
                        [
                            'required',
                            'string',
                            'max:50',
                        ],

                    'lead_id' =>
                        [
                            'nullable',
                            'uuid',
                            'exists:leads,id',
                        ],

                    'summary' =>
                        [
                            'required',
                            'string',
                            'max:10000',
                        ],

                    'followup_date' =>
                        [
                            'nullable',
                            'date',
                        ],

                    'call_start_at' =>
                        [
                            'required',
                            'date',
                        ],

                    'call_end_at' =>
                        [
                            'required',
                            'date',
                            'after_or_equal:call_start_at',
                        ],

                    'agent_name' =>
                        [
                            'required',
                            'string',
                            'max:150',
                        ],

                    'direction' =>
                        [
                            'required',
                            'string',
                            'in:incoming,outgoing',
                        ],

                    'sentiment_score' =>
                        [
                            'nullable',
                            'numeric',
                            'min:0',
                            'max:100',
                        ],

                    'followup_recording_id' =>
                        [
                            'nullable',
                            'integer',
                            'min:0',
                        ],
                ],
                [
                    'phone_number.required' =>
                        'phone_number is required.',

                    'summary.required' =>
                        'summary is required.',

                    'call_start_at.required' =>
                        'call_start_at is required.',

                    'call_end_at.required' =>
                        'call_end_at is required.',

                    'agent_name.required' =>
                        'agent_name is required.',

                    'direction.in' =>
                        'direction must be incoming or outgoing.',

                    'sentiment_score.min' =>
                        'sentiment_score must be between 0 and 100.',

                    'sentiment_score.max' =>
                        'sentiment_score must be between 0 and 100.',

                    'followup_recording_id.integer' =>
                        'followup_recording_id must be an integer.',
                ]
            );


        if (
            $validator->fails()
        ) {

            Log::warning(
                'Call Summary API validation failed.',
                [
                    'errors' =>
                        $validator
                            ->errors()
                            ->toArray(),

                    'payload_debug' =>
                        $this->payloadDebugContext(
                            $request->all()
                        ),
                ]
            );

            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'Validation failed.',

                    'errors' =>
                        $validator
                            ->errors(),
                ],
                422
            );
        }


        try {

            $validated =
                $validator
                    ->validated();

            Log::info(
                'Call Summary API validated payload.',
                [
                    'payload_debug' =>
                        $this->payloadDebugContext(
                            $validated
                        ),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Normalize direction
            |--------------------------------------------------------------------------
            */

            $validated[
                'direction'
            ] =
                strtolower(
                    trim(
                        $validated[
                            'direction'
                        ]
                    )
                );


            $integration =
                $service->receive(
                    $validated
                );

            if (
                $integration
                    ->followup_id
            ) {

                $integration
                    ->loadMissing(
                        'followup'
                    );
            }

            Log::info(
                'Call Summary API processed payload.',
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

                    'integration_summary_preview' =>
                        $this->previewForLog(
                            $integration
                                ->summary
                        ),

                    'integration_followup_date' =>
                        optional(
                            $integration
                                ->followup_date
                        )->format(
                            'Y-m-d H:i:s'
                        ),

                    'saved_followup_note_preview' =>
                        $integration
                            ->followup
                            ? $this->previewForLog(
                                $integration
                                    ->followup
                                    ->followup_note
                            )
                            : null,

                    'saved_next_followup_date' =>
                        $integration
                            ->followup
                            &&
                        $integration
                            ->followup
                            ->next_followup_date
                            ? $integration
                                ->followup
                                ->next_followup_date
                                ->format(
                                    'Y-m-d H:i:s'
                                )
                            : null,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Duplicate request
            |--------------------------------------------------------------------------
            */

            $duplicate =
                $integration
                    ->created_at
                &&
                $integration
                    ->updated_at
                &&
                $integration
                    ->created_at
                    ->lt(
                        now()
                            ->subSeconds(
                                2
                            )
                    );


            /*
            |--------------------------------------------------------------------------
            | HTTP response
            |--------------------------------------------------------------------------
            */

            $httpStatus =
                in_array(
                    $integration
                        ->status,
                    [
                        'pending_lead',
                        'ambiguous_match',
                    ],
                    true
                )
                    ? 202
                    : 200;

            return response()->json(
                [

                    'success' => true,

                    'duplicate_request' =>
                        $duplicate,

                    'status' =>
                        $integration
                            ->status,

                    'integration_id' =>
                        $integration
                            ->id,

                    'lead_id' =>
                        $integration
                            ->lead_id,

                    'followup_id' =>
                        $integration
                            ->followup_id,

                    'followup_recording_id' =>
                        $integration
                            ->followup_recording_id,

                    'lead_followup_status' =>
                        $integration
                            ->followup
                            ? $integration
                                ->followup
                                ->status
                            : null,

                    'match_score' =>
                        $integration
                            ->match_score,

                    'match_method' =>
                        $integration
                            ->match_method,

                    'message' =>
                        $this->messageFor(
                            $integration
                        ),
                ],
                $httpStatus
            );


        } catch (\Throwable $e) {

            Log::error(
                'Call Summary API failed.',
                [
                    'phone_number' =>
                        $request->input(
                            'phone_number'
                        ),

                    'agent_name' =>
                        $request->input(
                            'agent_name'
                        ),

                    'error' =>
                        $e->getMessage(),
                ]
            );


            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'Call summary could not be processed.',
                ],
                500
            );
        }
    }


    private function messageFor(
        CallSummaryIntegration $integration
    ): string {

        switch (
            $integration->status
        ) {

            case 'followup_created':

                return
                    'Call summary was matched and a new CRM follow-up was created.';


            case 'followup_updated':

                return
                    'Call summary was matched and the existing CRM follow-up was updated.';


            case 'pending_lead':

                return
                    'Call summary was stored. The CRM will retry matching when the lead becomes available.';


            case 'ambiguous_match':

                return
                    'Call summary was stored but an automatic lead match was not safe enough.';


            case 'failed':

                return
                    'Call summary was stored but processing failed and will be retried.';


            default:

                return
                    'Call summary was received.';
        }
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
}
