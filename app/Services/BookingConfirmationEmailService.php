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


    /*
     * =========================================================
     * PREVIEW
     * =========================================================
     */
    public function previewForLead(
        Lead $lead,
        ?User $actor = null
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
                    [
                        'payment_mode' =>
                            'payment_due',
                    ]
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


        $context =
            $this->emailContext(
                $freshLead,
                $agent,
                $registration[
                    'display_link'
                ]
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
        string $registrationLink
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


        $firstRide =
            $this->firstRideSegment(
                $lead
            );


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


        return [
            'total_amount_numeric' =>
                $totalAmount,

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
                    $this->duration(
                        $services,
                        $firstRide
                    ),

                'timing' =>
                    $this->timing(
                        $firstRide
                    ),

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


    private function firstRideSegment(
        Lead $lead
    ) {
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
                ->first();
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


    private function timing(
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


        $date =
            Carbon::parse(
                $ride->from_date
            );


        if (
            $date->format(
                'H:i:s'
            )
            === '00:00:00'
        ) {
            return 'TBA';
        }


        return
            $date->format(
                'g:i A'
            )
            . ' IST';
    }


    private function duration(
        Collection $services,
        $ride
    ): string {
        foreach (
            $services
            as $service
        ) {
            if (
                preg_match(
                    '/\b(\d+\s*(?:minutes?|mins?|min|hours?|hrs?|hr))\b/i',
                    (string)
                    $service->service,
                    $match
                )
            ) {
                return
                    (string)
                    Str::of(
                        $match[1]
                    )
                        ->lower()
                        ->title();
            }
        }


        if (
            $ride
            &&
            !empty(
                $ride->from_date
            )
            &&
            !empty(
                $ride->to_date
            )
        ) {
            $minutes =
                Carbon::parse(
                    $ride->from_date
                )
                    ->diffInMinutes(
                        Carbon::parse(
                            $ride->to_date
                        ),
                        false
                    );


            if (
                $minutes > 0
            ) {
                return
                    $minutes
                    . ' Minutes';
            }
        }


        return 'TBA';
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