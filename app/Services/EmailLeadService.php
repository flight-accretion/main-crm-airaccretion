<?php

namespace App\Services;

use App\Models\Client;
use App\Models\EmailLeadLog;
use App\Models\Lead;
use App\Models\LeadAllocationLog;
use App\Models\LeadAllocationSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmailLeadService
{
    public function __construct(
        private EmailLeadParserService $parser,
        private LeadAllocationService $allocationService,
        private EmailLeadFollowupService $followupService,
        private ActiveLeadService $activeLeadService,
        private LeadProductRoutingService $productRouter,
        private EmailLeadAllocationService $emailAllocator,
        private LeadSourceDataHydrationService $sourceDataHydrator
    ) {
    }

    public function process(
        array $email
    ): array {
        /*
         * Absolute duplicate protection.
         */
        if (
            EmailLeadLog::where(
                'message_id',
                $email['message_id']
            )->exists()
        ) {
            return [
                'status' => 'duplicate_email',
            ];
        }

        $parsed = $this->parser->parse(
            $email['body']
        );

        return DB::transaction(
            function () use (
                $email,
                $parsed
            ) {
                /*
                 * Create processing/audit log FIRST.
                 */
                $emailLog =
                    EmailLeadLog::create([
                        'message_id' =>
                            $email['message_id'],

                        'imap_uid' =>
                            $email['uid'],

                        'sender_email' =>
                            $email['sender_email'],

                        'recipient_email' =>
                            $email['recipient_email'],

                        'subject' =>
                            $email['subject'],

                        'customer_name' =>
                            $parsed['name'],

                        'customer_phone' =>
                            $parsed['phone'],

                        'service_name' =>
                            $parsed['service'],

                        'departure_date' =>
                            $parsed['departure_date'],

                        'departure_time' =>
                            $parsed['departure_time'],

                        'passenger_count' =>
                            $parsed['passenger_count'],

                        'email_body' =>
                            $parsed[
                                'original_message'
                            ],

                        'parsed_data' =>
                            $parsed['all_fields'],

                        'received_at' =>
                            $email['received_at'],

                        'processing_status' =>
                            'received',
                    ]);

                if (
                    empty(
                        $parsed['phone']
                    )
                ) {
                    $emailLog->processing_status =
                        'error';

                    $emailLog->processing_message =
                        'Customer phone number not found in email.';

                    $emailLog->processed_at = now();

                    $emailLog->save();

                    return [
                        'status' => 'error',
                        'reason' => 'phone_missing',
                    ];
                }

                /*
                 * SAME RULE AS IVR BUSINESS LOGIC:
                 * Active lead wins regardless of age.
                 */
               $existingLead =
                $this->activeLeadService->findByPhone(
                    $parsed['phone']
                );

                if ($existingLead) {
                    $emailLog->lead_id =
                        $existingLead->id;

                    $emailLog->processing_status =
                        'repeat_lead';

                    $emailLog->processing_message =
                        'Existing active lead found. Email follow-up added to existing lead.';

                    $emailLog->processed_at =
                        now();

                    $emailLog->save();

                    /*
                     * Existing representative stays.
                     */
                    if (
                        !empty(
                            $existingLead
                                ->representative_user_id
                        )
                    ) {
                        $this->followupService
                            ->createIfNeeded(
                                $existingLead,
                                $emailLog
                            );
                    } else {
                        /*
                         * Existing lead has no representative.
                         * Put it back in allocation queue.
                         */
                        $this->allocationService
                            ->queueLead(
                                $existingLead,
                                'email_repeat_lead'
                            );
                    }

                    return [
                        'status' => 'repeat_lead',
                        'lead_id' =>
                            $existingLead->id,
                    ];
                }

                /*
                 * Find/reuse Client.
                 */
                $client =
                    $this->findClientByPhone(
                        $parsed['phone']
                    );

                if (!$client) {
                    $client = Client::create([
                        'id' =>
                            (string) Str::uuid(),

                        'name' =>
                            $parsed['name']
                            ?: (
                                'Email Lead '
                                . $parsed['phone']
                            ),

                        /*
                         * Email sender is the website
                         * noreply address, NOT customer email.
                         */
                        'email' => null,

                        'contact_number' =>
                            $parsed['phone'],

                        'alternate_number' =>
                            null,

                        'status' => 1,

                        'created_by' => null,
                    ]);
                }

                /*
                 * Update blank client name when email
                 * provides a real customer name.
                 */
                if (
                    $parsed['name']
                    &&
                    (
                        empty($client->name)
                        ||
                        str_starts_with(
                            (string) $client->name,
                            'Email Lead '
                        )
                    )
                ) {
                    $client->name =
                        $parsed['name'];

                    $client->save();
                }

                /*
                 * Match email "Services:"
                 * to Product.product.
                 */
                $product =
                    $this->productRouter
                        ->resolveProduct(
                            $parsed['service']
                        );

                $productId =
                    optional($product)->id;

                $isCharterProduct =
                    $this->productRouter
                        ->isCharterProduct(
                            $product,
                            $parsed['service']
                        );

                $descriptionParts = [
                    'Lead received automatically from Email.',
                ];

                if ($parsed['service']) {
                    $descriptionParts[] =
                        'Service: '
                        . $parsed['service'];
                }

                if ($parsed['departure_date']) {
                    $descriptionParts[] =
                        'Departure Date: '
                        . $parsed['departure_date'];
                }

                if ($parsed['departure_time']) {
                    $descriptionParts[] =
                        'Departure Time: '
                        . $parsed['departure_time'];
                }

                /*
                 * Create unassigned lead first.
                 * Existing common allocator handles it.
                 */
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
                        $productId
                        ? [$productId]
                        : null,

                    'number_of_passengers' =>
                        $parsed[
                            'passenger_count'
                        ] ?: 1,

                    'description' =>
                        implode(
                            PHP_EOL,
                            $descriptionParts
                        ),

                    'occasion' => null,
                ]);

                $this->sourceDataHydrator->hydrate(
                    $lead,
                    array_merge(
                        $parsed['all_fields'] ?? [],
                        [
                            'service' =>
                                $parsed['service'],

                            'service_name' =>
                                $parsed['service'],

                            'date' =>
                                $parsed['departure_date'],

                            'departure_date' =>
                                $parsed['departure_date'],

                            'departure_time' =>
                                $parsed['departure_time'],

                            'guest' =>
                                $parsed['passenger_count'],
                        ]
                    )
                );

                $emailLog->lead_id =
                    $lead->id;

                $emailLog->save();

                $settings =
                    LeadAllocationSetting::getActiveSettings();

                $salesperson = null;

                if (
                    $settings->auto_allocation_enabled
                    && $this->allocationService
                        ->isOfficeOpenForDebug(
                            $settings,
                            now()
                        )
                ) {
                    $salesperson =
                        $this->emailAllocator
                            ->pickSalesperson(
                                $lead,
                                $settings
                            );
                }

                if ($salesperson) {
                    $lead->representative_user_id =
                        $salesperson->id;

                    $lead->save();

                    LeadAllocationLog::create([
                        'lead_id' => $lead->id,
                        'salesperson_id' =>
                            $salesperson->id,
                        'action' =>
                            'email_assigned',
                        'result' =>
                            'success',
                        'details' =>
                            'Assigned from Email using dynamic source lead routing.',
                    ]);

                    $emailLog->processing_status =
                        'lead_created_assigned';

                    $emailLog->processing_message =
                        $productId
                        ? 'New email lead created with matched product and assigned immediately.'
                        : 'New email lead created. Product not matched; assigned to fallback retail allocation.';

                    $emailLog->processed_at =
                        now();

                    $emailLog->save();

                    $this->followupService
                        ->createIfNeeded(
                            $lead,
                            $emailLog
                        );

                    return [
                        'status' =>
                            'created_assigned',

                        'lead_id' =>
                            $lead->id,

                        'product_id' =>
                            $productId,

                        'agent_user_id' =>
                            $salesperson->id,
                    ];
                }

                $emailLog->processing_status =
                    'lead_created_queued';

                $emailLog->processing_message =
                    $productId
                    ? 'New email lead created with matched product and queued for allocation.'
                    : 'New email lead created. Product not matched; queued for fallback allocation.';

                $emailLog->processed_at =
                    now();

                $emailLog->save();

                /*
                 * Reuse existing allocation queue.
                 */
                $this->allocationService
                    ->queueLead(
                        $lead,
                        $isCharterProduct
                            ? 'email_charter_lead'
                            : 'email_new_lead'
                    );

                return [
                    'status' =>
                        'created_queued',

                    'lead_id' =>
                        $lead->id,

                    'product_id' =>
                        $productId,
                ];
            }
        );
    }

    private function findClientByPhone(
        string $phone
    ): ?Client {
        $expr = $this->digitsSqlExpression(
            'contact_number'
        );

        return Client::whereRaw(
            "{$expr} LIKE ?",
            ['%' . $phone]
        )->first();
    }

    private function digitsSqlExpression(
        string $column
    ): string {
        if (
            config(
                'database.default'
            ) === 'pgsql'
        ) {
            return
                "regexp_replace("
                . $column
                . ", '[^0-9]', '', 'g')";
        }

        return
            "REPLACE("
            . "REPLACE("
            . "REPLACE("
            . "REPLACE("
            . "REPLACE("
            . $column
            . ", '+', ''),"
            . " '-', ''),"
            . " ' ', ''),"
            . " '(', ''),"
            . " ')', '')";
    }
}
