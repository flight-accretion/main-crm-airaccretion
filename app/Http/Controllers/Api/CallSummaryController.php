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
}
