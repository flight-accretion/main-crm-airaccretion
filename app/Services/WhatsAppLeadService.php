<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\WhatsAppLeadIntegration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Service;

class WhatsAppLeadService
{
    private const NEW_LEAD_ALLOWED_FOLLOWUP_STATUSES = [2, 5];

    public function __construct(
        private WhatsAppProductAllocationService $allocator,
        private LeadAllocationService $leadAllocationService,
        private WhatCrmAssignmentWebhookService $callback,
        private WhatCrmAssignmentCustomerMessageService $assignmentMessage,
        private LeadProductRoutingService $productRouter,
        private LeadSourceDataHydrationService $sourceDataHydrator,
        private LeadSourceFollowupService $sourceFollowups
    ) {
    }


    public function process(
        array $data,
        array $sourceOptions = []
    ): array {
        $source =
            $this->sourceOptions(
                $sourceOptions
            );

        $data =
            $this->withSourceMetadata(
                $data,
                $source
            );

        $result = DB::transaction(
            function () use ($data, $source) {

                $phone =
                    $this->normalizePhone(
                        $data['number']
                    );

                $serviceText =
                    $this->sourceServiceText(
                        $data
                    )
                    ?? null;

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
                                    'This '
                                    . $source['request_label']
                                    . ' request was already processed.',
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
                        $this->recordExistingLeadIntegration(
                            $existingLead,
                            $phone,
                            $data
                        );

                    $this->sourceFollowups
                        ->create(
                            $existingLead,
                            $source['label'],
                            $this->existingLeadFollowupMessage(
                                $data
                            ),
                            array_filter([
                                'phone' => $phone,
                                'service' => $serviceText,
                                'reference' =>
                                    $data['external_id'] ?? null,
                            ]),
                            true
                        );

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
                            ?: $source['lead_name_prefix']
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
                    $this->productRouter
                        ->resolveProduct(
                            $serviceText
                        );

                if (
                    !$product
                    && $source['empty_product_on_unmapped']
                ) {
                    $product =
                        $this->allocator
                            ->emptyProduct();
                }

                        /*
                * --------------------------------------------------
                * RESOLVE SERVICES FROM IDENTIFIED PRODUCT
                * --------------------------------------------------
                *
                * Service master stores related product UUIDs
                * inside services.product_ids.
                *
                * If a product is identified, attach all ACTIVE
                * services configured for that product.
                */
                    $serviceIds =
                    $this->resolveServiceIdsForProduct(
                        $product
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
                        !empty($serviceIds)
                            ? $serviceIds
                            : null,

                    'product_ids' =>
                        $product
                            ? [$product->id]
                            : null,

                    'number_of_passengers' =>
                        $guest,

                    'description' =>
                        $this->description(
                            $data,
                            $source
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
                            $source['assigned_action'],

                        'result' =>
                            'success',

                        'details' =>
                            $this->assignmentDetails(
                                $assignmentRoute,
                                $source
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

                    $this->sourceFollowups
                        ->createIfMissing(
                            $lead,
                            $source['label'],
                            trim(
                                (string) ($data['message'] ?? '')
                            ) ?: 'Lead assigned automatically from '
                                . $source['label']
                                . '.',
                            array_filter([
                                'phone' => $phone,
                                'service' => $serviceText,
                                'reference' =>
                                    $data['external_id'] ?? null,
                            ])
                        );


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
                                $source['label']
                                . ' lead created and assigned successfully.',
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
                            $assignmentRoute,
                            $source
                        )
                    );

                $this->sourceFollowups
                    ->createIfMissing(
                        $lead,
                        $source['label'],
                        trim(
                            (string) ($data['message'] ?? '')
                        ) ?: 'Lead queued automatically from '
                            . $source['label']
                            . '.',
                        array_filter([
                            'phone' => $phone,
                            'service' => $serviceText,
                            'reference' =>
                                $data['external_id'] ?? null,
                        ]),
                        true
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

    private function sourceOptions(
        array $options
    ): array {
        $key =
            trim(
                (string) (
                    $options['key']
                    ?? 'whatsapp'
                )
            );

        if ($key === '') {
            $key = 'whatsapp';
        }

        $queuePrefix =
            trim(
                (string) (
                    $options['queue_prefix']
                    ?? $key
                )
            );

        $queuePrefix =
            trim(
                preg_replace(
                    '/[^a-z0-9]+/',
                    '_',
                    Str::lower($queuePrefix)
                ) ?: '',
                '_'
            );

        if ($queuePrefix === '') {
            $queuePrefix = 'whatsapp';
        }

        $label =
            trim(
                (string) (
                    $options['label']
                    ?? 'WhatsApp / WhatCRM'
                )
            );

        if ($label === '') {
            $label = 'WhatsApp / WhatCRM';
        }

        return [
            'key' => $key,
            'label' => $label,
            'request_label' =>
                trim(
                    (string) (
                        $options['request_label']
                        ?? 'WhatCRM'
                    )
                ) ?: 'WhatCRM',
            'lead_name_prefix' =>
                (string) (
                    $options['lead_name_prefix']
                    ?? 'WhatsApp Lead '
                ),
            'assigned_action' =>
                (string) (
                    $options['assigned_action']
                    ?? 'whatsapp_assigned'
                ),
            'queue_prefix' => $queuePrefix,
            'routing_label' =>
                trim(
                    (string) (
                        $options['routing_label']
                        ?? 'WhatCRM'
                    )
                ) ?: 'WhatCRM',
            'description_intro' =>
                (string) (
                    $options['description_intro']
                    ?? 'Lead received automatically from WhatsApp / WhatCRM.'
                ),
            'empty_product_on_unmapped' =>
                (bool) (
                    $options['empty_product_on_unmapped']
                    ?? false
                ),
        ];
    }

    private function withSourceMetadata(
        array $data,
        array $source
    ): array {
        if ($source['key'] === 'whatsapp') {
            return $data;
        }

        $data['_source'] =
            $source['key'];

        $data['_source_label'] =
            $source['label'];

        return $data;
    }

    private function recordExistingLeadIntegration(
        Lead $lead,
        string $phone,
        array $data
    ): WhatsAppLeadIntegration {
        $integration =
            WhatsAppLeadIntegration::query()
                ->where('lead_id', $lead->id)
                ->first();

        $payload = [
            'phone' => $phone,
            'status' =>
                $integration
                && $integration->status === 'assigned'
                    ? 'assigned'
                    : 'existing_lead',
            'assigned_user_id' =>
                $lead->representative_user_id,
            'payload' => $data,
            'assigned_at' =>
                $lead->representative_user_id
                    ? now()
                    : null,
        ];

        if (!empty($data['external_id'])) {
            $payload['external_id'] =
                $data['external_id'];
        }

        if ($integration) {
            if (
                !empty($integration->external_id)
                && !empty($payload['external_id'])
                && $integration->external_id
                    !== $payload['external_id']
            ) {
                unset($payload['external_id']);
            }

            $integration->update($payload);

            return $integration->fresh()
                ?: $integration;
        }

        return WhatsAppLeadIntegration::create(
            array_merge(
                [
                    'lead_id' => $lead->id,
                    'product_id' => null,
                ],
                $payload
            )
        );
    }

    private function existingLeadFollowupMessage(
        array $data
    ): string {
        $values = [
            'Existing CRM lead matched by phone. Duplicate lead was not created.',
        ];

        foreach (
            [
                'date' => 'Date',
                'guest' => 'Guests',
                'type' => 'Type',
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
            ?? $data['occassion']
            ?? $data['ocassion']
            ?? null;

        if (
            is_scalar($occasion)
            && trim((string) $occasion) !== ''
        ) {
            $values[] =
                'Occasion: '
                . trim((string) $occasion);
        }

        return implode(
            PHP_EOL,
            $values
        );
    }

    private function resolveServiceIdsForProduct(
    ?\App\Models\Product $product
): array {

    if (!$product) {
        return [];
    }

    /*
     * Do not use whereJsonContains here.
     *
     * PostgreSQL supports it, but the SQLite
     * in-memory test database used by this project
     * does not.
     *
     * Service model casts product_ids to array,
     * so filter the small active service master
     * safely in PHP.
     */
    return Service::query()
        ->where(
            'status',
            1
        )
        ->get([
            'id',
            'product_ids',
        ])
        ->filter(
            function (Service $service) use (
                $product
            ) {

                $productIds =
                    $service->product_ids;

                /*
                 * Normally Eloquent cast already
                 * gives us an array.
                 *
                 * Keep this defensive handling for
                 * legacy/double encoded values.
                 */
                if (is_string($productIds)) {

                    $decoded =
                        json_decode(
                            $productIds,
                            true
                        );

                    if (is_array($decoded)) {

                        $productIds =
                            $decoded;

                    } elseif (
                        is_string($decoded)
                    ) {

                        $decodedAgain =
                            json_decode(
                                $decoded,
                                true
                            );

                        $productIds =
                            is_array(
                                $decodedAgain
                            )
                                ? $decodedAgain
                                : [];
                    }
                }

                if (!is_array($productIds)) {
                    return false;
                }

                return in_array(
                    (string) $product->id,
                    array_map(
                        'strval',
                        $productIds
                    ),
                    true
                );
            }
        )
        ->pluck('id')
        ->filter()
        ->values()
        ->all();
}

    private function assignmentDetails(
        string $assignmentRoute,
        array $source
    ): string {
        if ($assignmentRoute === 'charter') {
            return 'Assigned from '
                . $source['routing_label']
                . ' using charter product routing.';
        }

        if ($assignmentRoute === 'retail') {
            return 'Assigned from '
                . $source['routing_label']
                . ' using retail empty-product routing.';
        }

        return 'Assigned from '
            . $source['routing_label']
            . ' using dynamic product routing.';
    }

    private function queueReason(
        string $assignmentRoute,
        array $source
    ): string {
        if ($assignmentRoute === 'charter') {
            return $source['queue_prefix']
                . '_charter_waiting';
        }

        if ($assignmentRoute === 'retail') {
            return $source['queue_prefix']
                . '_retail_waiting';
        }

        return $source['queue_prefix']
            . '_product_waiting';
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
        array $data,
        array $source
    ): string {
        $values = [
            $source['description_intro'],
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

        foreach (
            [
                'instagram_id' => 'Instagram ID',
                'type' => 'Type',
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

        return implode(
            PHP_EOL,
            $values
        );
    }
}
