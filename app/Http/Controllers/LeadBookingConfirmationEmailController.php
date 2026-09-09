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
                    Auth::user()
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
}