<?php

namespace App\Services;

use App\Models\Client;
use App\Models\EmailLeadLog;
use App\Models\Lead;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmailLeadService
{
    public function __construct(
        private EmailLeadParserService $parser,
        private LeadAllocationService $allocationService,
        private EmailLeadFollowupService $followupService,
        private ActiveLeadService $activeLeadService
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
                $productId =
                    $this->findProductId(
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

                $emailLog->lead_id =
                    $lead->id;

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
                        'email_new_lead'
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

    private function findProductId(
        ?string $serviceName
    ): ?string {
        if (!$serviceName) {
            return null;
        }

        $needle =
            $this->normalizeText(
                $serviceName
            );

        /*
         * Load products and use exact normalized
         * matching to prevent assigning the wrong
         * product just because two names are similar.
         *
         * Your Lead model reads product names
         * from Product.product.
         */
        return Product::query()
            ->get([
                'id',
                'product',
            ])
            ->first(function ($product) use (
                $needle
            ) {
                return $this->normalizeText(
                    $product->product
                ) === $needle;
            })
            ?->id;
    }

    private function normalizeText(
        ?string $value
    ): string {
        return Str::lower(
            preg_replace(
                '/\s+/',
                ' ',
                trim(
                    (string) $value
                )
            )
        );
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