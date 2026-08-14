<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\Product;
use App\Models\WhatsAppLeadIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WhatsAppLeadService
{
    public function __construct(
        private WhatsAppProductAllocationService $allocator,
        private LeadAllocationService $leadAllocationService,
        private WhatCrmAssignmentWebhookService $callback
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

                $product =
                    $this->resolveProduct(
                        $data['service']
                            ?? null
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

                $user = null;

                if ($product) {

                    $user =
                        $this->allocator
                            ->findUser(
                                $product->id
                            );
                }


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
                            'Assigned from WhatCRM using dynamic product routing.',
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
                                $product->id,

                            'product' =>
                                $product->product,

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
                        $product
                            ? 'whatsapp_product_waiting'
                            : 'whatsapp_product_unmatched'
                    );


                $integration->update([
                    'status' =>
                        $product
                            ? 'queued'
                            : 'product_unmatched',
                ]);


                return [
                    'integration' =>
                        $integration,

                    'response' => [
                        'success' => true,

                        'status' =>
                            $product
                                ? 'queued'
                                : 'product_unmatched',

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
                            $product
                                ? 'Lead created. Waiting for an eligible mapped salesperson.'
                                : 'Lead created, but the incoming service did not match a CRM product.',
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
        }


        return $result['response'];
    }


    private function resolveProduct(
        ?string $service
    ): ?Product {
        $service = trim(
            (string) $service
        );

        if ($service === '') {
            return null;
        }

        /*
         * Exact match first.
         */
        $product = Product::query()
            ->whereRaw(
                'LOWER(product) = ?',
                [
                    mb_strtolower(
                        $service
                    )
                ]
            )
            ->first();

        if ($product) {
            return $product;
        }

        /*
         * Same broad matching style already used
         * elsewhere in your CRM imports.
         */
        return Product::query()
            ->where(
                'product',
                'LIKE',
                '%' . $service . '%'
            )
            ->first();
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
             * Same active-lead rule you were
             * already using for IVR.
             */
            if (
                $latest
                && (int) $latest->status === 1
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
            "{$column}, '+', ''), '-', ''), ' ', ''), '(', ''), ')')";
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