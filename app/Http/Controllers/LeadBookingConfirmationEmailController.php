<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\BookingConfirmationEmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeadBookingConfirmationEmailController extends Controller
{
    public function send(
        Request $request,
        Lead $lead,
        BookingConfirmationEmailService $service
    ) {
        /*
         * =====================================================
         * PREVIEW
         * =====================================================
         *
         * No email is sent.
         * No temporary payment value is stored.
         */
        if (
            $request->boolean(
                'preview_only'
            )
        ) {
            $result =
                $service->previewForLead(
                    $lead,
                    Auth::user(),
                    $this->travelInput(
                        $request
                    )
                );

            return response()->json(
                $result,
                ($result['success'] ?? false)
                    ? 200
                    : 422
            );
        }


        /*
         * =====================================================
         * FINAL SEND VALIDATION
         * =====================================================
         */
        $validated =
            $request->validate(
                [
                    'payment_mode' => [
                        'required',
                        'string',
                        'in:payment_due,installment',
                    ],

                    /*
                     * Editable in popup.
                     *
                     * EMAIL ONLY.
                     * NOT SAVED TO CRM.
                     */
                    'total_amount' => [
                        'required',
                        'numeric',
                        'min:0.01',
                    ],

                    'advance_amount' => [
                        'nullable',
                        'numeric',
                        'min:0.01',
                        'required_if:payment_mode,payment_due',
                    ],

                    'installments' => [
                        'nullable',
                        'array',
                        'min:1',
                        'required_if:payment_mode,installment',
                    ],

                    'installments.*.date' => [
                        'nullable',
                        'string',
                        'required_if:payment_mode,installment',
                        'regex:/^\d{2}\/\d{2}\/\d{2}$/',
                    ],

                    'installments.*.amount' => [
                        'nullable',
                        'numeric',
                        'min:0.01',
                        'required_if:payment_mode,installment',
                    ],

                    /*
                     * Ride time / duration for THIS email only.
                     */
                    'email_time_tba' => [
                        'nullable',
                        'boolean',
                    ],

                    'email_time' => [
                        'nullable',
                        'date_format:H:i',
                        'required_if:email_time_tba,0,false',
                    ],

                    'email_duration_tba' => [
                        'nullable',
                        'boolean',
                    ],

                    'email_duration' => [
                        'nullable',
                        'string',
                        'max:50',
                        'required_if:email_duration_tba,0,false',
                    ],
                ],
                [
                    'total_amount.required' =>
                        'Please enter Total Service Cost.',

                    'total_amount.numeric' =>
                        'Total Service Cost must be a valid amount.',

                    'advance_amount.required_if' =>
                        'Please enter Advance Payment Due Now.',

                    'installments.required_if' =>
                        'Please add at least one installment.',

                    'installments.*.date.required_if' =>
                        'Please enter a Due Date for every installment.',

                    'installments.*.date.regex' =>
                        'Every Due Date must use dd/mm/yy format.',

                    'installments.*.amount.required_if' =>
                        'Please enter an Amount for every installment.',

                    'email_time.required_if' =>
                        'Please enter a Ride Time or tick To Be Announced.',

                    'email_time.date_format' =>
                        'Ride Time must be a valid time.',

                    'email_duration.required_if' =>
                        'Please enter a Duration or tick To Be Announced.',
                ]
            );


        /*
         * Temporary email-only values.
         *
         * Nothing here is persisted.
         */
        $paymentData = [
            'payment_mode' =>
                $validated[
                    'payment_mode'
                ],

            'total_amount' =>
                (float)
                $validated[
                    'total_amount'
                ],
        ];


        $paymentData = array_merge(
            $paymentData,
            $this->travelInput(
                $request
            )
        );


        if (
            $validated[
                'payment_mode'
            ] === 'payment_due'
        ) {
            $paymentData[
                'advance_amount'
            ] =
                (float)
                $validated[
                    'advance_amount'
                ];
        } else {
            $paymentData[
                'installments'
            ] =
                collect(
                    $validated[
                        'installments'
                    ] ?? []
                )
                    ->map(
                        function (
                            array $installment
                        ) {
                            return [
                                'date' =>
                                    trim(
                                        (string)
                                        $installment[
                                            'date'
                                        ]
                                    ),

                                'amount' =>
                                    (float)
                                    $installment[
                                        'amount'
                                    ],
                            ];
                        }
                    )
                    ->values()
                    ->all();
        }


        $result =
            $service->sendForLead(
                $lead,
                Auth::user(),
                $paymentData
            );


        return response()->json(
            $result,
            ($result['success'] ?? false)
                ? 200
                : 422
        );
    }


    /**
     * Ride time / duration choices from the popup.
     *
     * A key is included only when the popup actually sent it, so callers
     * that send nothing keep the automatic behaviour.
     */
    private function travelInput(
        Request $request
    ): array {
        $input = [];

        foreach (
            [
                'email_time_tba',
                'email_duration_tba',
            ]
            as $key
        ) {
            if (
                $request->has(
                    $key
                )
                && $request->input(
                    $key
                ) !== null
            ) {
                $input[$key] =
                    $request->boolean(
                        $key
                    );
            }
        }

        foreach (
            [
                'email_time',
                'email_duration',
            ]
            as $key
        ) {
            if (
                is_string(
                    $request->input(
                        $key
                    )
                )
            ) {
                $input[$key] =
                    $request->input(
                        $key
                    );
            }
        }

        return $input;
    }
}