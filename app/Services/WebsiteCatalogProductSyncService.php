<?php

namespace App\Services;

use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteCatalogProductSyncService
{
    public function sync(
        array $payload
    ): array {
        return DB::transaction(
            function () use (
                $payload
            ) {
                /*
                |--------------------------------------------------------------------------
                | Normalize Payload
                |--------------------------------------------------------------------------
                */

                $eventId =
                    (string) $payload[
                        'event_id'
                    ];


                $websiteServiceTypeId =
                    (int) $payload[
                        'website_service_type_id'
                    ];


                $name =
                    trim(
                        (string) $payload[
                            'name'
                        ]
                    );


                $reconcile =
                    (bool) (
                        $payload[
                            'reconcile'
                        ]
                        ?? false
                    );


                /*
                |--------------------------------------------------------------------------
                | Event-level Idempotency
                |--------------------------------------------------------------------------
                */

                $existingEvent =
                    DB::table(
                        'website_catalog_sync_events'
                    )
                    ->where(
                        'event_id',
                        $eventId
                    )
                    ->first();


                if ($existingEvent) {
                    return [
                        'action' =>
                            'already_processed',

                        'product_id' =>
                            $existingEvent
                                ->product_id,

                        'website_service_type_id' =>
                            $websiteServiceTypeId,
                    ];
                }


                /*
                |--------------------------------------------------------------------------
                | Normal Matching = Website ID Only
                |--------------------------------------------------------------------------
                */

                $product =
                    Product::query()
                        ->where(
                            'website_service_type_id',
                            $websiteServiceTypeId
                        )
                        ->first();


                $action =
                    'updated';


                /*
                |--------------------------------------------------------------------------
                | Initial Reconciliation ONLY
                |--------------------------------------------------------------------------
                |
                | Exact name matching is allowed only while reconcile=true.
                |
                | Once website_service_type_id is mapped,
                | normal sync never depends on Product name again.
                |
                */

                if (
                    !$product
                    &&
                    $reconcile
                ) {

                    $matches =
                        Product::query()
                            ->whereNull(
                                'website_service_type_id'
                            )
                            ->whereRaw(
                                'LOWER(product) = LOWER(?)',
                                [
                                    $name,
                                ]
                            )
                            ->limit(
                                2
                            )
                            ->get();


                    if (
                        $matches->count()
                        > 1
                    ) {
                        throw new DomainException(
                            'Multiple CRM Products match Website Service Type "'
                            . $name
                            . '". Manual mapping is required.'
                        );
                    }


                    if (
                        $matches->count()
                        === 1
                    ) {
                        $product =
                            $matches->first();


                        $product
                            ->website_service_type_id =
                                $websiteServiceTypeId;


                        $action =
                            'reconciled';
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | New Website Product
                |--------------------------------------------------------------------------
                |
                | No mapping exists and no existing exact match was found.
                |
                */

                if (!$product) {

                    $product =
                        new Product();


                    /*
                     * Keep normal CRM UUID architecture.
                     */
                    $product->id =
                        (string) Str::uuid();


                    $product
                        ->website_service_type_id =
                            $websiteServiceTypeId;


                    /*
                     * Safe defaults.
                     *
                     * We do NOT invent:
                     * booking_email_note
                     * users
                     * vendor
                     * etc.
                     */

                    $product->is_private =
                        false;

                    $product->is_airambulance =
                        false;

                    $product->status =
                        1;


                    $action =
                        'created';
                }


                /*
                |--------------------------------------------------------------------------
                | Website-controlled Shared Field
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | Website is allowed to change ONLY Product name.
                |
                | We intentionally DO NOT modify:
                |
                | booking_email_note
                | user_ids
                | vendor_id
                | is_private on existing Product
                | is_airambulance on existing Product
                | status on existing Product
                |
                */

                $product->product =
                    $name;


                $product->save();


                /*
                |--------------------------------------------------------------------------
                | Record Processed Event
                |--------------------------------------------------------------------------
                */

                DB::table(
                    'website_catalog_sync_events'
                )
                ->insert([
                    'event_id' =>
                        $eventId,

                    'event_type' =>
                        'product.upsert',

                    'website_service_type_id' =>
                        $websiteServiceTypeId,

                    'product_id' =>
                        (string) $product->id,

                    'processed_at' =>
                        now(),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);


                return [
                    'action' =>
                        $action,

                    'product_id' =>
                        (string) $product->id,

                    'website_service_type_id' =>
                        $websiteServiceTypeId,

                    'product' =>
                        $product->product,
                ];
            }
        );
    }
}