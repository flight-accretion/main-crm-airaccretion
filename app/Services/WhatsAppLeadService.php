<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\WhatsAppLeadIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppLeadService
{
    private const NEW_LEAD_ALLOWED_FOLLOWUP_STATUSES = [2, 5];

    public function __construct(
        private WhatsAppProductAllocationService $allocator,
        private LeadAllocationService $leadAllocationService,
        private WhatCrmAssignmentWebhookService $callback,
        private WhatCrmAssignmentCustomerMessageService $assignmentMessage,
        private LeadProductRoutingService $productRouter,
        private LeadSourceDataHydrationService $sourceDataHydrator
    ) {
    }


    public function process(
        array $data
    ): array {
        $result = DB::transaction(
            function () use ($data) {

                $phone =
                    $this->normalizePhone(
                        $data['number']
                    );

                /*
                 * --------------------------------------------------
                 * REQUEST IDEMPOTENCY
                 * --------------------------------------------------
                 */

                if (!empty($data['external_id'])) {

                    $old =
                        WhatsAppLeadIntegration::query()
                            ->where(
                                'external_id',
                                $data['external_id']
                            )
                            ->first();

                    if ($old) {

                        $old->loadMissing(
                            'lead.representative'
                        );

                        return [
                            'integration' => $old,

                            'response' => [
                                'success' => true,
                                'duplicate_request' => true,
                                'status' => $old->status,
                                'lead_id' => $old->lead_id,

                                'agent' =>
                                    optional(
                                        optional(
                                            $old->lead
                                        )->representative
                                    )->name,

                                'agent_user_id' =>
                                    optional($old->lead)
                                        ->representative_user_id,

                                'message' =>
                                    'This WhatCRM request was already processed.',
                            ],
                        ];
                    }
                }


                /*
                 * --------------------------------------------------
                 * EXISTING ACTIVE CRM LEAD
                 * --------------------------------------------------
                 */

                $existingLead =
                    $this->findActiveLeadByPhone(
                        $phone
                    );

                if ($existingLead) {

                    $integration =
                        WhatsAppLeadIntegration::create([
                            'lead_id' =>
                                $existingLead->id,

                            'product_id' =>
                                null,

                            'phone' =>
                                $phone,

                            'external_id' =>
                                $data['external_id']
                                    ?? null,

                            'status' =>
                                'existing_lead',

                            'assigned_user_id' =>
                                $existingLead
                                    ->representative_user_id,

                            'payload' =>
                                $data,

                            'assigned_at' =>
                                $existingLead
                                    ->representative_user_id
                                    ? now()
                                    : null,
                        ]);

                    return [
                        'integration' =>
                            $integration,

                        'response' => [
                            'success' => true,
                            'status' =>
                                'existing_lead',

                            'existing_lead' =>
                                true,

                            'lead_id' =>
                                $existingLead->id,

                            'agent' =>
                                optional(
                                    $existingLead
                                        ->representative
                                )->name,

                            'agent_user_id' =>
                                $existingLead
                                    ->representative_user_id,

                            'message' =>
                                'Existing active lead found. Duplicate lead was not created.',
                        ],
                    ];
                }


                /*
                 * --------------------------------------------------
                 * FIND / CREATE CLIENT
                 * --------------------------------------------------
                 */

                $client =
                    $this->findClientByPhone(
                        $phone
                    );

                if (!$client) {

                    $client = Client::create([
                        'id' =>
                            (string) Str::uuid(),

                        'name' =>
                            trim(
                                $data['name']
                                    ?? ''
                            )
                            ?: 'WhatsApp Lead '
                                . $phone,

                        'email' => null,

                        'contact_number' =>
                            $phone,

                        'alternate_number' =>
                            null,

                        'status' => 1,

                        'created_by' => null,
                    ]);
                }


                /*
                 * --------------------------------------------------
                 * RESOLVE PRODUCT
                 * --------------------------------------------------
                 */

                $serviceText =
                    $this->sourceServiceText(
                        $data
                    )
                    ?? null;

                $product =
                    $this->productRouter
                        ->resolveProduct(
                            $serviceText
                        );


                /*
                 * --------------------------------------------------
                 * CREATE LEAD
                 * --------------------------------------------------
                 */

                $guest =
                    is_numeric(
                        $data['guest']
                            ?? null
                    )
                    ? max(
                        1,
                        (int) $data['guest']
                    )
                    : 1;

                $lead = Lead::create([
                    'id' =>
                        (string) Str::uuid(),

                    'client_id' =>
                        $client->id,

                    'representative_user_id' =>
                        null,

                    'service_ids' =>
                        null,

                    'product_ids' =>
                        $product
                            ? [$product->id]
                            : null,

                    'number_of_passengers' =>
                        $guest,

                    'description' =>
                        $this->description(
                            $data
                        ),

                    'occasion' =>
                        $data['occasion']
                            ?? $data['ocassion']
                            ?? null,
                ]);

                $this->sourceDataHydrator->hydrate(
                    $lead,
                    $data
                );


                $integration =
                    WhatsAppLeadIntegration::create([
                        'lead_id' =>
                            $lead->id,

                        'product_id' =>
                            optional($product)->id,

                        'phone' =>
                            $phone,

                        'external_id' =>
                            $data['external_id']
                                ?? null,

                        'status' =>
                            'created',

                        'payload' =>
                            $data,
                    ]);


                /*
                 * --------------------------------------------------
                 * PRODUCT BASED ASSIGNMENT
                 * --------------------------------------------------
                 */

                $assignmentRoute =
                    $this->allocator
                        ->assignmentRoute(
                            $product,
                            $serviceText
                        );

                $user =
                    $this->allocator
                        ->findUserForAssignment(
                            $product,
                            $serviceText
                        );


                if ($user) {

                    $lead->representative_user_id =
                        $user->id;

                    $lead->save();


                    LeadAllocationLog::create([
                        'lead_id' =>
                            $lead->id,

                        'salesperson_id' =>
                            $user->id,

                        'action' =>
                            'whatsapp_assigned',

                        'result' =>
                            'success',

                        'details' =>
                            $this->assignmentDetails(
                                $assignmentRoute
                            ),
                    ]);


                    $integration->update([
                        'status' =>
                            'assigned',

                        'assigned_user_id' =>
                            $user->id,

                        'assigned_at' =>
                            now(),
                    ]);


                    return [
                        'integration' =>
                            $integration,

                        'response' => [
                            'success' => true,

                            'status' =>
                                'assigned',

                            'existing_lead' =>
                                false,

                            'lead_id' =>
                                $lead->id,

                            'product_id' =>
                                optional($product)->id,

                            'product' =>
                                optional($product)->product,

                            'agent' =>
                                $user->name,

                            'agent_user_id' =>
                                $user->id,

                            'message' =>
                                'WhatsApp lead created and assigned successfully.',
                        ],
                    ];
                }


                /*
                 * --------------------------------------------------
                 * QUEUE
                 * --------------------------------------------------
                 */

                $this->leadAllocationService
                    ->queueLead(
                        $lead,
                        $this->queueReason(
                            $assignmentRoute
                        )
                    );


                $integration->update([
                        'status' =>
                            'queued',
                ]);


                return [
                    'integration' =>
                        $integration,

                    'response' => [
                        'success' => true,

                        'status' =>
                            'queued',

                        'existing_lead' =>
                            false,

                        'lead_id' =>
                            $lead->id,

                        'product_id' =>
                            optional($product)->id,

                        'product' =>
                            optional($product)->product,

                        'agent' => null,

                        'agent_user_id' => null,

                        'message' =>
                            $this->queuedMessage(
                                $assignmentRoute
                            ),
                    ],
                ];
            }
        );


        /*
         * Callback only AFTER transaction succeeds.
         */
        $integration =
            $result['integration'];

        if (
            !empty(
                $result['response']['agent']
            )
        ) {
            $this->callback->send(
                $integration
            );

            $this->assignmentMessage->send(
                $integration
            );
        }


        return $result['response'];
    }

    private function assignmentDetails(
        string $assignmentRoute
    ): string {
        if ($assignmentRoute === 'charter') {
            return 'Assigned from WhatCRM using charter product routing.';
        }

        if ($assignmentRoute === 'retail') {
            return 'Assigned from WhatCRM using retail empty-product routing.';
        }

        return 'Assigned from WhatCRM using dynamic product routing.';
    }

    private function queueReason(
        string $assignmentRoute
    ): string {
        if ($assignmentRoute === 'charter') {
            return 'whatsapp_charter_waiting';
        }

        if ($assignmentRoute === 'retail') {
            return 'whatsapp_retail_waiting';
        }

        return 'whatsapp_product_waiting';
    }

    private function queuedMessage(
        string $assignmentRoute
    ): string {
        if ($assignmentRoute === 'charter') {
            return 'Lead created. Waiting for an eligible charter salesperson.';
        }

        if ($assignmentRoute === 'retail') {
            return 'Lead created. Waiting for an eligible retail salesperson.';
        }

        return 'Lead created. Waiting for an eligible mapped salesperson.';
    }


    private function findActiveLeadByPhone(
        string $phone
    ): ?Lead {
        $expression =
            $this->digitsSql(
                'clients.contact_number'
            );

        $leads = Lead::query()
            ->join(
                'clients',
                'clients.id',
                '=',
                'leads.client_id'
            )
            ->whereRaw(
                "{$expression} LIKE ?",
                ['%' . $phone]
            )
            ->select('leads.*')
            ->orderByDesc(
                'leads.created_at'
            )
            ->get();

        foreach ($leads as $lead) {

            $latest =
                $lead->leadFollowups()
                    ->orderByDesc(
                        'created_at'
                    )
                    ->first();

            /*
             * Cancelled or confirmed/completed rides can start a new lead.
             * Every other latest status keeps this WhatsApp request on
             * the existing lead.
             */
            if (
                $latest
                && !in_array(
                    (int) $latest->status,
                    self::NEW_LEAD_ALLOWED_FOLLOWUP_STATUSES,
                    true
                )
            ) {
                return $lead;
            }
        }

        return null;
    }


    private function findClientByPhone(
        string $phone
    ): ?Client {
        $expression =
            $this->digitsSql(
                'contact_number'
            );

        return Client::query()
            ->whereRaw(
                "{$expression} LIKE ?",
                ['%' . $phone]
            )
            ->first();
    }


    private function normalizePhone(
        string $phone
    ): string {
        $digits =
            preg_replace(
                '/[^0-9]/',
                '',
                $phone
            );

        /*
         * Normalize Indian +91 number to last 10 digits.
         */
        if (
            strlen($digits) > 10
            && str_starts_with(
                $digits,
                '91'
            )
        ) {
            $digits =
                substr(
                    $digits,
                    -10
                );
        }

        return $digits;
    }


    private function digitsSql(
        string $column
    ): string {
        if (
            config('database.default')
            === 'pgsql'
        ) {
            return
                "regexp_replace({$column}, '[^0-9]', '', 'g')";
        }

        return
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(" .
            "{$column}, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '')";
    }


    private function sourceServiceText(
        array $data
    ): ?string {
        foreach (
            [
                'service',
                'message',
                'body',
            ]
            as $key
        ) {
            $value =
                trim(
                    (string) (
                        $data[$key]
                        ?? ''
                    )
                );

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }


    private function description(
        array $data
    ): string {
        $values = [
            'Lead received automatically from WhatsApp / WhatCRM.',
        ];

        foreach (
            [
                'service' => 'Service',
                'date' => 'Date',
                'city' => 'City',
                'guest' => 'Guests',
            ]
            as $key => $label
        ) {
            if (
                isset($data[$key])
                && trim(
                    (string) $data[$key]
                ) !== ''
            ) {
                $values[] =
                    $label
                    . ': '
                    . trim(
                        (string) $data[$key]
                    );
            }
        }

        $occasion =
            $data['occasion']
            ?? $data['ocassion']
            ?? null;

        if ($occasion) {
            $values[] =
                'Occasion: '
                . $occasion;
        }

        return implode(
            PHP_EOL,
            $values
        );
    }
}
