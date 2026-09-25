<?php

namespace App\Services;

use App\Mail\BookingConfirmationMail;
use App\Models\BookingEmailTemplate;
use App\Models\ExtraService;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\BookingTravelDetailService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class BookingConfirmationEmailService
{
    /*
     * =========================================================
     * FINAL SEND
     * =========================================================
     */
    public function sendForLead(
        Lead $lead,
        ?User $actor = null,
        array $paymentData = []
    ): array {
        try {
            $prepared =
                $this->prepareEmail(
                    $lead,
                    $actor,
                    $paymentData
                );


            /*
             * Validate temporary popup values.
             */
            $this->validatePaymentData(
                $prepared[
                    'total_amount_numeric'
                ],
                $paymentData
            );


            Mail::to(
                $prepared[
                    'customer_email'
                ]
            )->send(
                new BookingConfirmationMail(
                    $prepared[
                        'subject'
                    ],

                    $prepared[
                        'body'
                    ],

                    optional(
                        $prepared[
                            'agent'
                        ]
                    )->email,

                    optional(
                        $prepared[
                            'agent'
                        ]
                    )->name
                )
            );


            /*
             * Existing generic CRM note.
             *
             * IMPORTANT:
             * We do NOT store:
             *
             * - popup Total Service Cost
             * - Advance Payment Due
             * - installment dates
             * - installment amounts
             */
            $this->createFollowupNote(
                $lead,
                $actor
                    ?: $prepared[
                        'agent'
                    ],
                $prepared[
                    'customer_email'
                ]
            );


            $whatsAppResult =
                $this->sendBookingConfirmationWhatsApp(
                    $lead,
                    $actor
                        ?: $prepared[
                            'agent'
                        ],
                    $prepared
                );


            return [
                'success' =>
                    true,

                'message' =>
                    'Booking confirmation email sent successfully.',

                'registration_link' =>
                    $prepared[
                        'registration'
                    ][
                        'long_link'
                    ],

                'short_link' =>
                    $prepared[
                        'registration'
                    ][
                        'short_link'
                    ],

                'whatsapp_sent' =>
                    (bool) (
                        $whatsAppResult[
                            'sent'
                        ]
                        ?? false
                    ),

                'whatsapp_message' =>
                    $whatsAppResult[
                        'message'
                    ]
                    ?? null,

                'whatsapp_provider_message_id' =>
                    $whatsAppResult[
                        'provider_message_id'
                    ]
                    ?? null,
            ];

        } catch (\Throwable $exception) {
            Log::error(
                'Booking confirmation email failed',
                [
                    'lead_id' =>
                        $lead->id,

                    'error' =>
                        $exception
                            ->getMessage(),
                ]
            );


            return [
                'success' =>
                    false,

                'message' =>
                    $exception
                        ->getMessage()
                    ?: 'Booking confirmation email could not be sent.',
            ];
        }
    }


    private function sendBookingConfirmationWhatsApp(
        Lead $lead,
        ?User $actor,
        array $prepared
    ): array {
        if (
            !config(
                'services.booking_whatsapp.enabled',
                true
            )
        ) {
            return [
                'sent' => false,
                'message' => 'Booking confirmation WhatsApp is disabled.',
            ];
        }

        $lead->loadMissing([
            'client',
        ]);

        $customerNumber =
            $this->customerWhatsAppNumber(
                $lead
            );

        if (
            $customerNumber === ''
        ) {
            return [
                'sent' => false,
                'message' => 'Customer WhatsApp number is not available.',
            ];
        }

        $body =
            $this->bookingConfirmationWhatsAppBody(
                $lead,
                $prepared
            );

        try {
            $result =
                app(
                    WhatCrmOutboundMessageService::class
                )->sendText([
                    'number' =>
                        $customerNumber,

                    'name' =>
                        optional(
                            $lead->client
                        )->name,

                    'message' =>
                        $body,

                    'agent_user_id' =>
                        optional(
                            $actor
                        )->id,

                    'lead_id' =>
                        $lead->id,
                ]);

            return [
                'sent' =>
                    (bool) (
                        $result[
                            'success'
                        ]
                        ?? false
                    ),

                'message' =>
                    (
                        $result[
                            'success'
                        ]
                        ?? false
                    )
                        ? 'Booking confirmation WhatsApp sent successfully.'
                        : 'WhatCRM did not accept the booking confirmation WhatsApp message.',

                'provider_message_id' =>
                    $result[
                        'provider_message_id'
                    ]
                    ?? null,
            ];
        } catch (\Throwable $exception) {
            Log::warning(
                'Booking confirmation WhatsApp failed',
                [
                    'lead_id' =>
                        $lead->id,

                    'error' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return [
                'sent' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }


    private function customerWhatsAppNumber(
        Lead $lead
    ): string {
        foreach (
            [
                optional(
                    $lead->client
                )->alternate_number,

                optional(
                    $lead->client
                )->contact_number,
            ]
            as $number
        ) {
            $number =
                trim(
                    (string) $number
                );

            if (
                $number !== ''
            ) {
                return $number;
            }
        }

        return '';
    }


    private function bookingConfirmationWhatsAppBody(
        Lead $lead,
        array $prepared
    ): string {
        $customerName =
            $this->value(
                optional(
                    $lead->client
                )->name
            );

        $registrationLink =
            $prepared[
                'registration'
            ][
                'short_link'
            ]
            ?: $prepared[
                'registration'
            ][
                'long_link'
            ];

        $companyNumber =
            trim(
                (string) config(
                    'services.booking_whatsapp.company_number',
                    '+91 95753 40786'
                )
            );

        if (
            $companyNumber === ''
        ) {
            $companyNumber =
                '+91 95753 40786';
        }

        return implode(
            PHP_EOL,
            [
                'Dear ' . $customerName . ',',
                '',
                'Your booking confirmation has been sent to your email.',
                'Passenger registration link: ' . $registrationLink,
                'Please complete the payment steps shared in the booking email.',
                'For any assistance, WhatsApp or call ' . $companyNumber . '.',
                '',
                'Accretion Aviation',
            ]
        );
    }


    /*
     * =========================================================
     * PREVIEW
     * =========================================================
     */
public function previewForLead(
    Lead $lead,
    ?User $actor = null,
    array $options = []
): array {
        try {
            /*
             * Preview defaults to Payment Due.
             *
             * Full CRM/service amount will populate:
             *
             * Total Service Cost
             * Advance Payment Due Now
             */
            $prepared =
            $this->prepareEmail(
                $lead,
                $actor,
                array_merge(
                    [
                        'payment_mode' => 'payment_due',
                    ],
                    $options
                )
            );


            $parts =
                $this->splitPaymentSection(
                    $prepared[
                        'body'
                    ]
                );


            return [
                'success' =>
                    true,

                'customer_email' =>
                    $prepared[
                        'customer_email'
                    ],

                'subject' =>
                    $prepared[
                        'subject'
                    ],

                'body_before_payment' =>
                    $parts[
                        'before'
                    ],

                'body_after_payment' =>
                    $parts[
                        'after'
                    ],

                /*
                 * Numeric value for editable textbox.
                 */
                'total_amount' =>
                    $prepared[
                        'total_amount_numeric'
                    ],

                'total_amount_formatted' =>
                    $this->money(
                        $prepared[
                            'total_amount_numeric'
                        ]
                    ),

                'registration_link' =>
                    $prepared[
                        'registration'
                    ][
                        'long_link'
                    ],

                'short_link' =>
                    $prepared[
                        'registration'
                    ][
                        'short_link'
                    ],

                /*
                 * Ride time / duration controls in the popup.
                 */
                'voucher_time' =>
                    $prepared[
                        'travel'
                    ][
                        'voucher_time'
                    ],

                'time_is_tba' =>
                    $prepared[
                        'travel'
                    ][
                        'time_is_tba'
                    ],

                'email_time' =>
                    $prepared[
                        'travel'
                    ][
                        'email_time'
                    ],

                'time_note' =>
                    $prepared[
                        'travel'
                    ][
                        'time_note'
                    ],

                'duration' =>
                    $prepared[
                        'travel'
                    ][
                        'duration_suggestion'
                    ],

                'duration_is_tba' =>
                    $prepared[
                        'travel'
                    ][
                        'duration_is_tba'
                    ],

                'duration_source' =>
                    $prepared[
                        'travel'
                    ][
                        'duration_source'
                    ],

                'duration_note' =>
                    $prepared[
                        'travel'
                    ][
                        'duration_note'
                    ],
            ];

        } catch (\Throwable $exception) {
            Log::error(
                'Booking confirmation preview failed',
                [
                    'lead_id' =>
                        $lead->id,

                    'error' =>
                        $exception
                            ->getMessage(),
                ]
            );


            return [
                'success' =>
                    false,

                'message' =>
                    $exception
                        ->getMessage()
                    ?: 'Unable to prepare booking email preview.',
            ];
        }
    }


    /*
     * =========================================================
     * PREPARE FULL EMAIL
     * =========================================================
     */
    private function prepareEmail(
        Lead $lead,
        ?User $actor,
        array $paymentData = []
    ): array {
        $lead->loadMissing([
            'client',
            'representative',
            'rideSegments',
        ]);


        $customerEmail =
            trim(
                (string)
                optional(
                    $lead->client
                )->email
            );


        if (
            $customerEmail === ''
            ||
            !filter_var(
                $customerEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Customer email is not available or invalid.'
            );
        }


        $registration =
            $this->registrationLink(
                $lead
            );


        $template =
            BookingEmailTemplate::active();


        $agent =
            $lead->representative
            ?: $actor;


        $freshLead =
            $lead->fresh([
                'client',
                'representative',
                'rideSegments',
            ]);


        /*
         * Email-only overrides chosen by the sender in the popup.
         * Nothing here is saved to the CRM.
         */
        $context =
            $this->emailContext(
                $freshLead,
                $agent,
                $registration[
                    'display_link'
                ],
                $this->travelOverrides(
                    $paymentData
                )
            );


        /*
         * CRM amount is the default.
         *
         * Final-send popup amount may temporarily
         * override it for the EMAIL ONLY.
         */
        $totalAmountForEmail =
            array_key_exists(
                'total_amount',
                $paymentData
            )
                ? (float)
                    $paymentData[
                        'total_amount'
                    ]
                : $context[
                    'total_amount_numeric'
                ];


        $variables =
            $context[
                'variables'
            ];


        /*
         * Override template total with temporary
         * popup amount when final sending.
         */
        $variables[
            'total_amount'
        ] =
            $this->money(
                $totalAmountForEmail
            );


        $subject =
            $this->renderTemplate(
                $template->subject
                ?: BookingEmailTemplate::defaultSubject(),

                $variables
            );


        $body =
            $this->renderTemplate(
                $template->body
                ?: BookingEmailTemplate::defaultBody(),

                $variables
            );

        $body =
            $this->normalizeTimeLabel(
                $body
            );


        /*
         * Completely replace old fixed payment block.
         *
         * Therefore these old fields never appear:
         *
         * Balance Amount
         * Balance Due By
         */
        $body =
            $this->replacePaymentSection(
                $body,
                $totalAmountForEmail,
                $paymentData
            );


        return [
            'customer_email' =>
                $customerEmail,

            'agent' =>
                $agent,

            'registration' =>
                $registration,

            'subject' =>
                $subject,

            'body' =>
                $body,

            'total_amount_numeric' =>
                $totalAmountForEmail,

            'travel' =>
                $context[
                    'travel'
                ],
        ];
    }


    /*
     * =========================================================
     * REGISTRATION LINK
     * =========================================================
     */
    private function registrationLink(
        Lead $lead
    ): array {
        $token =
            $lead
                ->generatePassengerRegistrationToken();


        $passenger =
            $lead->passengers()
                ->whereNull(
                    'voucher_id'
                )
                ->first();


        if (
            $passenger
            &&
            empty(
                $passenger
                    ->registration_slug
            )
        ) {
            $passenger
                ->generateRegistrationSlug();
        }


        $longLink =
            route(
                'lead.register.form',
                [
                    'lead' =>
                        $lead->id,

                    'token' =>
                        $token,
                ]
            );


        $shortLink =
            $passenger
                ? $passenger
                    ->getShortRegistrationLink()
                : null;


        return [
            'long_link' =>
                $longLink,

            'short_link' =>
                $shortLink,

            'display_link' =>
                $shortLink
                ?: $longLink,
        ];
    }


    /*
     * =========================================================
     * CURRENT CRM EMAIL CONTEXT
     * =========================================================
     */
private function emailContext(
    Lead $lead,
    ?User $agent,
    string $registrationLink,
    array $travelOverrides = []
): array {
        $amountFollowup =
            $this->amountFollowup(
                $lead
            );


        $serviceIds =
            $this->selectedServiceIds(
                $lead,
                $amountFollowup
            );


        $extraServiceIds =
            $this->selectedExtraServiceIds(
                $amountFollowup
            );


        $services =
            $this->services(
                $serviceIds
            );


        $extraServices =
            $this->extraServices(
                $extraServiceIds
            );


        $products =
            $this->products(
                $lead,
                $services
            );


        $rideSegments =
            $this->rideSegments(
                $lead
            );

        $firstRide =
            $rideSegments
                ->first();


        /*
         * CRM/service amount.
         *
         * This is only the default shown
         * when popup opens.
         */
        $totalAmount =
            $this->totalAmount(
                $amountFollowup,
                $services,
                $extraServices
            );

        $travel =
            $this->travelDetails(
                $services,
                $firstRide,
                $travelOverrides
            );

        $timing =
            $travel[
                'timing'
            ];

        $duration =
            $travel[
                'duration'
            ];


        return [
            'total_amount_numeric' =>
                $totalAmount,

            'travel' =>
                $travel,

            'variables' => [
                'customer_name' =>
                    $this->value(
                        optional(
                            $lead->client
                        )->name
                    ),

                'customer_email' =>
                    $this->value(
                        optional(
                            $lead->client
                        )->email
                    ),

                'customer_phone' =>
                    $this->value(
                        optional(
                            $lead->client
                        )->contact_number
                    ),

                'service_name' =>
                    $this->names(
                        $services,
                        'service'
                    ),

                'product_name' =>
                    $this->names(
                        $products,
                        'product'
                    ),

                'service_date' =>
                    $this->serviceDate(
                        $firstRide
                    ),

                'duration' =>
                    $duration,

                'timing' =>
                    $timing,

                'time' =>
                    $timing,

                'passengers' =>
                    $this->value(
                        $lead
                            ->number_of_passengers
                    ),

                'total_amount' =>
                    $this->money(
                        $totalAmount
                    ),

                /*
                 * Kept so old/custom templates render
                 * before payment section replacement.
                 */
                'advance_amount' =>
                    $this->money(
                        $totalAmount
                    ),

                'balance_amount' =>
                    '',

                'balance_due_by' =>
                    '',

                'registration_link' =>
                    $registrationLink,

                'product_service_notes' =>
                    $this->productServiceNotes(
                        $products,
                        $services
                    ),

                'payment_link' =>
                    'https://www.accretionaviation.com/pay',

                    'bank_account_name' => config('services.booking_bank.account_name', ''),
                    'bank_name' => config('services.booking_bank.bank_name', ''),
                    'bank_account_number' => config('services.booking_bank.account_number', ''),
                    'bank_ifsc' => config('services.booking_bank.ifsc', ''),
                    'bank_branch' => config('services.booking_bank.branch', ''),

                'terms_link' =>
                    'https://www.accretionaviation.com/terms&condition.php',

                'agent_name' =>
                    $this->value(
                        optional(
                            $agent
                        )->name
                    ),

                'agent_email' =>
                    $this->value(
                        optional(
                            $agent
                        )->email
                    ),

                'agent_phone' =>
                    $this->value(
                        optional(
                            $agent
                        )->contact_number
                    ),

                'lead_code' =>
                    $this->value(
                        $lead->crm_lead_code
                        ?: $lead->id
                    ),
            ],
        ];
    }


    /*
     * =========================================================
     * RENDER TEMPLATE VARIABLES
     * =========================================================
     */
    private function renderTemplate(
        string $template,
        array $variables
    ): string {
        foreach (
            $variables
            as $key => $value
        ) {
            $template =
                str_replace(
                    [
                        '{{'
                        . $key
                        . '}}',

                        '{{ '
                        . $key
                        . ' }}',
                    ],

                    (string)
                    $value,

                    $template
                );
        }


        return $template;
    }


    private function normalizeTimeLabel(
        string $body
    ): string {
        return
            preg_replace(
                '/(^|\R)([ \t]*)Timing\s*:/',
                '$1$2Time:',
                $body
            )
            ??
            $body;
    }


    /*
     * =========================================================
     * BUILD PAYMENT SECTION
     * =========================================================
     */
    public function buildPaymentBreakdown(
        ?float $totalAmount,
        array $paymentData
    ): string {
        $mode =
            $paymentData[
                'payment_mode'
            ]
            ?? 'payment_due';


        $lines = [
            'PAYMENT BREAKDOWN',

            'Total Service Cost: '
                . $this->money(
                    $totalAmount
                ),
        ];


        /*
         * =====================================================
         * PAYMENT DUE
         * =====================================================
         */
        if (
            $mode === 'payment_due'
        ) {
            $advanceAmount =
                array_key_exists(
                    'advance_amount',
                    $paymentData
                )
                    ? (float)
                        $paymentData[
                            'advance_amount'
                        ]
                    : $totalAmount;


            $lines[] =
                'Advance Payment Due Now: '
                . $this->money(
                    $advanceAmount
                );


            return implode(
                PHP_EOL,
                $lines
            );
        }


        /*
         * =====================================================
         * INSTALLMENTS
         * =====================================================
         */
        $lines[] =
            '';

        $lines[] =
            'INSTALLMENT SCHEDULE';


        $installments =
            $paymentData[
                'installments'
            ] ?? [];


        foreach (
            $installments
            as $index => $installment
        ) {
            $date =
                $this->parseInstallmentDate(
                    trim(
                        (string)
                        $installment[
                            'date'
                        ]
                    )
                );


            $amount =
                (float)
                $installment[
                    'amount'
                ];


            $lines[] =
                ($index + 1)
                . '. '
                . $this->money(
                    $amount
                )
                . ' due on '
                . $date->format(
                    'j F Y'
                );
        }


        return implode(
            PHP_EOL,
            $lines
        );
    }


    /*
     * =========================================================
     * VALIDATE POPUP PAYMENT VALUES
     * =========================================================
     */
    private function validatePaymentData(
        ?float $totalAmount,
        array $paymentData
    ): void {
        if (
            $totalAmount === null
            ||
            $totalAmount <= 0
        ) {
            throw new RuntimeException(
                'Total Service Cost must be greater than zero.'
            );
        }


        $mode =
            $paymentData[
                'payment_mode'
            ]
            ?? null;


        if (
            !in_array(
                $mode,
                [
                    'payment_due',
                    'installment',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Please select Payment Due or Installment.'
            );
        }


        /*
         * =====================================================
         * PAYMENT DUE
         * =====================================================
         */
        if (
            $mode === 'payment_due'
        ) {
            $advance =
                (float)
                (
                    $paymentData[
                        'advance_amount'
                    ]
                    ?? 0
                );


            if (
                $advance <= 0
            ) {
                throw new RuntimeException(
                    'Advance Payment Due Now must be greater than zero.'
                );
            }


            if (
                $advance
                > $totalAmount
            ) {
                throw new RuntimeException(
                    'Advance Payment Due Now cannot exceed Total Service Cost.'
                );
            }


            return;
        }


        /*
         * =====================================================
         * INSTALLMENT
         * =====================================================
         */
        $installments =
            $paymentData[
                'installments'
            ] ?? [];


        if (
            !is_array(
                $installments
            )
            ||
            count(
                $installments
            ) === 0
        ) {
            throw new RuntimeException(
                'Please add at least one installment.'
            );
        }


        $installmentTotal =
            0;


        foreach (
            $installments
            as $installment
        ) {
            $dateValue =
                trim(
                    (string)
                    (
                        $installment[
                            'date'
                        ]
                        ?? ''
                    )
                );


            /*
             * Validates actual date too.
             *
             * Example:
             * 31/02/26 is rejected.
             */
            $this->parseInstallmentDate(
                $dateValue
            );


            $amount =
                (float)
                (
                    $installment[
                        'amount'
                    ]
                    ?? 0
                );


            if (
                $amount <= 0
            ) {
                throw new RuntimeException(
                    'Every installment Amount must be greater than zero.'
                );
            }


            $installmentTotal +=
                $amount;
        }


        if (
            $installmentTotal
            > $totalAmount
        ) {
            throw new RuntimeException(
                'Total installment amount cannot exceed Total Service Cost.'
            );
        }
    }


    /*
     * =========================================================
     * STRICT DD/MM/YY DATE
     * =========================================================
     */
    private function parseInstallmentDate(
        string $dateValue
    ): Carbon {
        if (
            !preg_match(
                '/^\d{2}\/\d{2}\/\d{2}$/',
                $dateValue
            )
        ) {
            throw new RuntimeException(
                'Every installment Due Date must use dd/mm/yy format.'
            );
        }


        try {
            $date =
                Carbon::createFromFormat(
                    '!d/m/y',
                    $dateValue
                );


            $errors =
                Carbon::getLastErrors();


            if (
                !$date
                ||
                (
                    is_array(
                        $errors
                    )
                    &&
                    (
                        ($errors['warning_count'] ?? 0)
                            > 0
                        ||
                        ($errors['error_count'] ?? 0)
                            > 0
                    )
                )
                ||
                $date->format(
                    'd/m/y'
                )
                    !== $dateValue
            ) {
                throw new RuntimeException(
                    'Invalid installment Due Date.'
                );
            }


            return $date;

        } catch (\Throwable $exception) {
            throw new RuntimeException(
                'Every installment Due Date must be a valid date in dd/mm/yy format.'
            );
        }
    }


    /*
     * =========================================================
     * REPLACE OLD TEMPLATE PAYMENT BLOCK
     * =========================================================
     */
  private function replacePaymentSection(
    string $body,
    ?float $totalAmount,
    array $paymentData
): string {
    $positions =
        $this->paymentSectionPositions(
            $body
        );


    $before =
        rtrim(
            substr(
                $body,
                0,
                $positions[
                    'start'
                ]
            )
        );


    $after =
        ltrim(
            substr(
                $body,
                $positions[
                    'end'
                ]
            )
        );


    $paymentSection =
        $this->buildPaymentBreakdown(
            $totalAmount,
            $paymentData
        );


    $result =
        $before;


    if (
        $result !== ''
    ) {
        $result .=
            PHP_EOL
            . PHP_EOL;
    }


    $result .=
        $paymentSection;


    if (
        $after !== ''
    ) {
        $result .=
            PHP_EOL
            . PHP_EOL
            . $after;
    }


    return $result;
}


    /*
     * =========================================================
     * SPLIT FOR POPUP
     * =========================================================
     */
private function splitPaymentSection(
    string $body
): array {
    $positions =
        $this->paymentSectionPositions(
            $body
        );


    return [
        'before' =>
            rtrim(
                substr(
                    $body,
                    0,
                    $positions[
                        'start'
                    ]
                )
            ),

        'after' =>
            ltrim(
                substr(
                    $body,
                    $positions[
                        'end'
                    ]
                )
            ),
    ];
}


private function paymentSectionPositions(
    string $body
): array {
    /*
     * =========================================================
     * 1. PREFERRED: CURRENT SECTION HEADINGS
     * =========================================================
     */

    $paymentHeadings = [
        'PAYMENT BREAKDOWN',
        'PAYMENT DETAILS',
        'PAYMENT INFORMATION',
    ];


    $nextHeadings = [
        'NEXT STEPS TO CONFIRM YOUR BOOKING',
        'NEXT STEPS',
        'BOOKING NEXT STEPS',
    ];


    $start =
        false;


    foreach (
        $paymentHeadings
        as $heading
    ) {
        $position =
            stripos(
                $body,
                $heading
            );


        if (
            $position !== false
        ) {
            $start =
                $position;

            break;
        }
    }


    $end =
        false;


    if (
        $start !== false
    ) {
        foreach (
            $nextHeadings
            as $heading
        ) {
            $position =
                stripos(
                    $body,
                    $heading,
                    $start
                );


            if (
                $position !== false
            ) {
                $end =
                    $position;

                break;
            }
        }
    }


    /*
     * Normal working template.
     */
    if (
        $start !== false
        &&
        $end !== false
        &&
        $end > $start
    ) {
        return [
            'start' =>
                $start,

            'end' =>
                $end,
        ];
    }


    /*
     * =========================================================
     * 2. FALLBACK
     *
     * Older/custom templates may use different heading text.
     *
     * Look for the rendered payment lines instead.
     * =========================================================
     */

    $paymentLineCandidates = [
        'Total Service Cost:',
        'Advance Payment Due Now:',
        'Balance Amount:',
        'Balance Due By:',
    ];


    $firstPaymentPosition =
        false;


    foreach (
        $paymentLineCandidates
        as $candidate
    ) {
        $position =
            stripos(
                $body,
                $candidate
            );


        if (
            $position !== false
            &&
            (
                $firstPaymentPosition === false
                ||
                $position
                    < $firstPaymentPosition
            )
        ) {
            $firstPaymentPosition =
                $position;
        }
    }


    /*
     * If we found payment lines,
     * start from the beginning of that line.
     */
    if (
        $firstPaymentPosition !== false
    ) {
        $lineStart =
            strrpos(
                substr(
                    $body,
                    0,
                    $firstPaymentPosition
                ),
                "\n"
            );


        $start =
            $lineStart === false
                ? $firstPaymentPosition
                : $lineStart + 1;


        /*
         * Try to locate the beginning of the next
         * logical email section.
         */
        $possibleNextSections = [
            'NEXT STEPS TO CONFIRM YOUR BOOKING',
            'NEXT STEPS',
            'Step 1',
            'IMPORTANT NOTES',
        ];


        foreach (
            $possibleNextSections
            as $heading
        ) {
            $position =
                stripos(
                    $body,
                    $heading,
                    $firstPaymentPosition
                );


            if (
                $position !== false
            ) {
                $end =
                    $position;

                break;
            }
        }


        if (
            $end !== false
            &&
            $end > $start
        ) {
            return [
                'start' =>
                    $start,

                'end' =>
                    $end,
            ];
        }
    }


    /*
     * =========================================================
     * 3. FINAL SAFE FALLBACK
     *
     * Do NOT crash the popup.
     *
     * Insert the editable payment section before
     * IMPORTANT NOTES if possible.
     * =========================================================
     */

    $importantNotes =
        stripos(
            $body,
            'IMPORTANT NOTES'
        );


    if (
        $importantNotes !== false
    ) {
        return [
            'start' =>
                $importantNotes,

            'end' =>
                $importantNotes,
        ];
    }


    /*
     * Last resort:
     * use end of email.
     *
     * This prevents preview from failing completely.
     */
    $length =
        strlen(
            $body
        );


    return [
        'start' =>
            $length,

        'end' =>
            $length,
    ];
}


    /*
     * =========================================================
     * EXISTING CRM DATA HELPERS
     * =========================================================
     */
    private function amountFollowup(
        Lead $lead
    ): ?LeadFollowup {
        return
            $lead->leadFollowups()
                ->whereNotNull(
                    'total_amount'
                )
                ->latest(
                    'created_at'
                )
                ->first()
            ?:
            $lead->leadFollowups()
                ->latest(
                    'created_at'
                )
                ->first();
    }


    private function selectedServiceIds(
        Lead $lead,
        ?LeadFollowup $followup
    ): array {
        $followupIds =
            $this->ids(
                optional(
                    $followup
                )->service_ids
            );


        return
            $followupIds
            ?: $this->ids(
                $lead->service_ids
            );
    }


    private function selectedExtraServiceIds(
        ?LeadFollowup $followup
    ): array {
        return $this->ids(
            optional(
                $followup
            )->extra_service_ids
        );
    }


    private function ids(
        $value
    ): array {
        if (
            $value
            instanceof Collection
        ) {
            $value =
                $value->all();
        }


        if (
            is_string(
                $value
            )
        ) {
            $decoded =
                json_decode(
                    stripslashes(
                        $value
                    ),
                    true
                );


            $value =
                is_array(
                    $decoded
                )
                    ? $decoded
                    : [];
        }


        if (
            !is_array(
                $value
            )
        ) {
            return [];
        }


        return collect(
            $value
        )
            ->filter(
                fn ($id) =>
                    is_string(
                        $id
                    )
                    ||
                    is_numeric(
                        $id
                    )
            )
            ->map(
                fn ($id) =>
                    (string)
                    $id
            )
            ->unique()
            ->values()
            ->all();
    }


    private function services(
        array $serviceIds
    ): Collection {
        if (
            empty(
                $serviceIds
            )
        ) {
            return collect();
        }


        return Service::query()
            ->whereIn(
                'id',
                $serviceIds
            )
            ->get();
    }


    private function extraServices(
        array $extraServiceIds
    ): Collection {
        if (
            empty(
                $extraServiceIds
            )
        ) {
            return collect();
        }


        return ExtraService::query()
            ->whereIn(
                'id',
                $extraServiceIds
            )
            ->get();
    }


    private function products(
        Lead $lead,
        Collection $services
    ): Collection {
        $productIds =
            collect(
                $this->ids(
                    $lead->product_ids
                )
            );


        $services->each(
            function (
                Service $service
            ) use (
                $productIds
            ) {
                foreach (
                    $this->ids(
                        $service
                            ->product_ids
                    )
                    as $productId
                ) {
                    $productIds
                        ->push(
                            $productId
                        );
                }
            }
        );


        $productIds =
            $productIds
                ->unique()
                ->values()
                ->all();


        if (
            empty(
                $productIds
            )
        ) {
            return collect();
        }


        return Product::query()
            ->whereIn(
                'id',
                $productIds
            )
            ->get();
    }


    private function names(
        Collection $records,
        string $field
    ): string {
        $names =
            $records
                ->pluck(
                    $field
                )
                ->filter()
                ->unique()
                ->values()
                ->all();


        return empty(
            $names
        )
            ? 'N/A'
            : implode(
                ', ',
                $names
            );
    }


    private function rideSegments(
        Lead $lead
    ): Collection {
        return
            $lead
                ->rideSegments
                ->filter(
                    fn ($ride) =>
                        !empty(
                            $ride
                                ->from_date
                        )
                )
                ->sortBy(
                    'from_date'
                )
                ->values();
    }


    private function serviceDate(
        $ride
    ): string {
        if (
            !$ride
            ||
            empty(
                $ride->from_date
            )
        ) {
            return 'TBA';
        }


        return Carbon::parse(
            $ride->from_date
        )->format(
            'l, jS F Y'
        );
    }


    /**
     * Email-only ride time / duration choices sent by the popup.
     * A key is present only when the sender's choice was actually sent.
     */
    private function travelOverrides(
        array $paymentData
    ): array {
        $overrides = [];

        foreach (
            [
                'email_time_tba',
                'email_duration_tba',
            ]
            as $key
        ) {
            if (
                array_key_exists(
                    $key,
                    $paymentData
                )
                && $paymentData[$key] !== null
            ) {
                $overrides[$key] =
                    (bool) $paymentData[$key];
            }
        }

        $time =
            trim(
                (string) (
                    $paymentData['email_time']
                    ?? ''
                )
            );

        if (
            preg_match(
                '/^([01]\d|2[0-3]):[0-5]\d$/',
                $time
            )
        ) {
            $overrides['email_time'] =
                Carbon::createFromFormat(
                    'H:i',
                    $time
                )->format(
                    'h:i A'
                );

            $overrides['email_time_input'] =
                $time;
        }

        $duration =
            trim(
                (string) (
                    $paymentData['email_duration']
                    ?? ''
                )
            );

        if ($duration !== '') {
            $overrides['email_duration'] =
                Str::limit(
                    $duration,
                    50,
                    ''
                );
        }

        return $overrides;
    }


    /**
     * Ride time and duration printed in the email, plus what the popup
     * needs to pre-fill its controls. Never returns a blank value:
     * whatever cannot be worked out is printed as TBA.
     *
     * Time:     TBA ticked -> TBA, otherwise sender's time, otherwise the
     *           voucher's stored start time, otherwise TBA.
     *           With no sender choice: TBA when the voucher says TBA, has no
     *           time, or only has the default 12:00.
     * Duration: TBA ticked -> TBA, otherwise sender's text, otherwise
     *           service name -> voucher Total Time -> ride dates -> TBA.
     */
    private function travelDetails(
        Collection $services,
        $firstRide,
        array $overrides
    ): array {
        $travel =
            app(
                BookingTravelDetailService::class
            );

        /*
         * ---------------- Time ----------------
         */
        $state =
            $travel->timeState(
                $firstRide
            );

        $stored =
            $state[
                'voucher_time'
            ];

        if (
            array_key_exists(
                'email_time_tba',
                $overrides
            )
        ) {
            $timeIsTba =
                $overrides[
                    'email_time_tba'
                ];
        } else {
            $timeIsTba =
                $state[
                    'default_tba'
                ];
        }

        if ($timeIsTba) {
            $timing = 'TBA';
        } else {
            $timing =
                $overrides[
                    'email_time'
                ]
                ?? $stored;

            if ($timing === '') {
                $timing = 'TBA';
            }
        }

        $emailTimeInput =
            $overrides[
                'email_time_input'
            ]
            ?? (
                $stored !== ''
                    ? Carbon::createFromFormat(
                        'h:i A',
                        $stored
                    )->format(
                        'H:i'
                    )
                    : ''
            );

        /*
         * ---------------- Duration ----------------
         */
        $resolved =
            $travel->resolveDuration(
                $this->serviceDuration(
                    $services
                ),
                $firstRide
            );

        if (
            array_key_exists(
                'email_duration_tba',
                $overrides
            )
        ) {
            $durationIsTba =
                $overrides[
                    'email_duration_tba'
                ];
        } else {
            // A multi-day date range may only be the travel window,
            // so it stays TBA until the sender confirms it.
            $durationIsTba =
                $resolved[
                    'value'
                ] === ''
                || $resolved[
                    'needs_confirm'
                ];
        }

        if ($durationIsTba) {
            $duration = 'TBA';
        } else {
            $duration =
                $overrides[
                    'email_duration'
                ]
                ?? $resolved[
                    'value'
                ];

            if ($duration === '') {
                $duration = 'TBA';
            }
        }

        return [
            'timing' =>
                $timing,

            'duration' =>
                $duration,

            'voucher_time' =>
                $stored,

            'time_is_tba' =>
                $timeIsTba,

            'email_time' =>
                $emailTimeInput,

            'time_note' =>
                $state[
                    'note'
                ],

            'duration_is_tba' =>
                $durationIsTba,

            /*
             * Value the Duration textbox is pre-filled with.
             */
            'duration_suggestion' =>
                $overrides[
                    'email_duration'
                ]
                ?? $resolved[
                    'value'
                ],

            'duration_source' =>
                array_key_exists(
                    'email_duration',
                    $overrides
                )
                    ? 'entered by you'
                    : $resolved[
                        'source'
                    ],

            'duration_note' =>
                (
                    $resolved[
                        'needs_confirm'
                    ]
                    && !array_key_exists(
                        'email_duration',
                        $overrides
                    )
                )
                    ? 'This is the number of days in the ride dates, which may only be the travel window. Untick To Be Announced only if it is the service duration.'
                    : '',
        ];
    }


    private function rideTiming(
        $ride
    ): string {
        if (
            !$ride
            ||
            empty(
                $ride->from_date
            )
            ||
            empty(
                $ride->to_date
            )
            ||
            !empty(
                $ride->is_tba
            )
        ) {
            return 'TBA';
        }


        $fromDate =
            Carbon::parse(
                $ride->from_date
            );

        $toDate =
            Carbon::parse(
                $ride->to_date
            );

        if (
            !$fromDate->lt(
                $toDate
            )
        ) {
            return 'TBA';
        }


        if (
            $fromDate->format(
                'H:i:s'
            )
            === '00:00:00'
            &&
            $toDate->format(
                'H:i:s'
            )
            === '00:00:00'
        ) {
            return 'TBA';
        }


        return
            $fromDate->format(
                'j M Y, g:i A'
            )
            . ', '
            . $this->value(
                $ride->from_place
            )
            . ' to '
            . $toDate->format(
                'j M Y, g:i A'
            )
            . ', '
            . $this->value(
                $ride->to_place
            );
    }


    /**
     * Duration written in the service name, for example
     * "Private Plane Ride In Mumbai 30 Minutes" -> "30 Minutes",
     * "Yatra 2 Night and 3 days" -> "2 Nights 3 Days".
     * Blank when no selected service states one.
     */
    private function serviceDuration(
        Collection $services
    ): string {
        $minutes =
            $this->serviceDurationMinutes(
                $services
            );

        if (
            $minutes !== null
        ) {
            return $this->formatDurationMinutes(
                $minutes
            );
        }

        foreach (
            $services
            as $service
        ) {
            $name =
                (string)
                $service->service;

            $nights =
                preg_match(
                    '/(?<![\d.])(\d+)\s*nights?\b/i',
                    $name,
                    $nightMatch
                )
                    ? (int)
                        $nightMatch[1]
                    : 0;

            $days =
                preg_match(
                    '/(?<![\d.])(\d+)\s*days?\b/i',
                    $name,
                    $dayMatch
                )
                    ? (int)
                        $dayMatch[1]
                    : 0;

            $parts = [];

            if ($nights > 0) {
                $parts[] =
                    $nights
                    . ' '
                    . (
                        $nights === 1
                            ? 'Night'
                            : 'Nights'
                    );
            }

            if ($days > 0) {
                $parts[] =
                    $days
                    . ' '
                    . (
                        $days === 1
                            ? 'Day'
                            : 'Days'
                    );
            }

            if ($parts !== []) {
                return implode(
                    ' ',
                    $parts
                );
            }
        }

        return '';
    }


    private function serviceDurationMinutes(
        Collection $services
    ): ?int {
        foreach (
            $services
            as $service
        ) {
            if (
                preg_match(
                    '/(?<![\d.])(\d+(?:\.\d+)?)\s*(minutes?|mins?|min|hours?|hrs?|hr)\b/i',
                    (string)
                    $service->service,
                    $match
                )
            ) {
                $quantity =
                    (float)
                    $match[1];

                $unit =
                    strtolower(
                        $match[2]
                    );

                return
                    (int)
                    round(
                        Str::startsWith(
                            $unit,
                            'h'
                        )
                            ? $quantity * 60
                            : $quantity
                    );
            }
        }


        return null;
    }


    private function rideDurationMinutes(
        $ride
    ): ?int {
        if (
            !$ride
            ||
            empty(
                $ride->from_date
            )
            ||
            empty(
                $ride->to_date
            )
            ||
            !empty(
                $ride->is_tba
            )
        ) {
            return null;
        }


        $fromDate =
            Carbon::parse(
                $ride->from_date
            );

        $toDate =
            Carbon::parse(
                $ride->to_date
            );

        if (
            !$fromDate->lt(
                $toDate
            )
        ) {
            return null;
        }

        if (
            $fromDate->format(
                'H:i:s'
            )
            === '00:00:00'
            &&
            $toDate->format(
                'H:i:s'
            )
            === '00:00:00'
        ) {
            return null;
        }

        return
            $fromDate
                ->diffInMinutes(
                    $toDate,
                    false
                );
    }


    private function formatDurationMinutes(
        int $minutes
    ): string {
        if (
            $minutes <= 0
        ) {
            return 'TBA';
        }

        $hours =
            intdiv(
                $minutes,
                60
            );

        $remainingMinutes =
            $minutes % 60;

        $parts = [];

        if (
            $hours > 0
        ) {
            $parts[] =
                $hours
                . ' '
                . (
                    $hours === 1
                        ? 'Hour'
                        : 'Hours'
                );
        }

        if (
            $remainingMinutes > 0
        ) {
            $parts[] =
                $remainingMinutes
                . ' '
                . (
                    $remainingMinutes === 1
                        ? 'Minute'
                        : 'Minutes'
                );
        }

        return implode(
            ' ',
            $parts
        );
    }


    private function totalAmount(
        ?LeadFollowup $followup,
        Collection $services,
        Collection $extraServices
    ): ?float {
        if (
            $followup
            &&
            $followup
                ->total_amount
            !== null
        ) {
            return
                (float)
                $followup
                    ->total_amount;
        }


        $serviceAmount =
            $services
                ->sum(
                    'service_amount'
                );


        $extraServiceAmount =
            $extraServices
                ->sum(
                    'extra_service_amount'
                );


        $total =
            (float)
            $serviceAmount
            +
            (float)
            $extraServiceAmount;


        return
            $total > 0
                ? $total
                : null;
    }


    private function money(
        $amount
    ): string {
        if (
            $amount === null
            ||
            $amount === ''
        ) {
            return 'TBA';
        }


        return
            $this->currencySymbol()
            .
            number_format(
                (float)
                $amount,
                2
            );
    }


    private function currencySymbol(): string
    {
        $symbol =
            config(
                'settings.currency_symbol'
            );


        return
            is_string(
                $symbol
            )
            &&
            trim(
                $symbol
            ) !== ''
                ? trim(
                    $symbol
                )
                : '₹';
    }


    private function productServiceNotes(
        Collection $products,
        Collection $services
    ): string {
        $lines = [];


        if (
            Schema::hasColumn(
                'products',
                'booking_email_note'
            )
        ) {
            foreach (
                $products
                as $product
            ) {
                $note =
                    trim(
                        (string)
                        $product
                            ->booking_email_note
                    );


                if (
                    $note !== ''
                ) {
                    $lines[] =
                        '- '
                        . $product->product
                        . ': '
                        . $note;
                }
            }
        }


        if (
            Schema::hasColumn(
                'services',
                'booking_email_note'
            )
        ) {
            foreach (
                $services
                as $service
            ) {
                $note =
                    trim(
                        (string)
                        $service
                            ->booking_email_note
                    );


                if (
                    $note !== ''
                ) {
                    $lines[] =
                        '- '
                        . $service->service
                        . ': '
                        . $note;
                }
            }
        }


        $lines =
            array_values(
                array_unique(
                    $lines
                )
            );


        return empty(
            $lines
        )
            ? ''
            : 'Product/Service Notes:'
                . PHP_EOL
                . implode(
                    PHP_EOL,
                    $lines
                );
    }


    /*
     * =========================================================
     * GENERIC FOLLOW-UP NOTE ONLY
     * =========================================================
     */
    private function createFollowupNote(
        Lead $lead,
        ?User $user,
        string $customerEmail
    ): void {
        LeadFollowup::create([
            'id' =>
                (string)
                Str::uuid(),

            'lead_id' =>
                $lead->id,

            'next_followup_date' =>
                now(),

            'followup_note' =>
                'Booking confirmation email sent to '
                . $customerEmail
                . ' with passenger registration link.',

            'status' =>
                1,

            'followed_by' =>
                optional(
                    $user
                )->id,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }


    private function value(
        $value
    ): string {
        $value =
            trim(
                (string)
                $value
            );


        return
            $value === ''
                ? 'N/A'
                : $value;
    }
}
