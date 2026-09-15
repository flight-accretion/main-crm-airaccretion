<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class WebsiteCatalogAiNotesService
{
    public function forProduct(
        Product $product
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Website Mapping
        |--------------------------------------------------------------------------
        */

        $websiteServiceTypeId =
            (int) (
                $product
                    ->website_service_type_id
                ?? 0
            );


        if ($websiteServiceTypeId <= 0) {
            return [
                'status' =>
                    'not_mapped',

                'locations' =>
                    [],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Website API Configuration
        |--------------------------------------------------------------------------
        */

        $url =
            trim(
                (string) config(
                    'services.website_catalog.notes_url'
                )
            );


        $secret =
            trim(
                (string) config(
                    'services.website_catalog.notes_secret'
                )
            );


        if (
            $url === ''
            ||
            $secret === ''
        ) {
            return [
                'status' =>
                    'unavailable',

                'locations' =>
                    [],

                'message' =>
                    'Website AI Notes configuration is missing.',
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Exact JSON Body
        |--------------------------------------------------------------------------
        |
        | Important:
        | Signature must be generated from the same exact JSON
        | that gets sent to Website.
        |
        */

        $body =
            json_encode(
                [
                    'service_type_id' =>
                        $websiteServiceTypeId,
                ],
                JSON_UNESCAPED_UNICODE
                |
                JSON_UNESCAPED_SLASHES
                |
                JSON_THROW_ON_ERROR
            );


        $timestamp =
            (string) time();


        $signature =
            hash_hmac(
                'sha256',
                $timestamp
                . '.'
                . $body,
                $secret
            );


        try {

            /*
            |--------------------------------------------------------------------------
            | Website Request
            |--------------------------------------------------------------------------
            */

            $response =
                Http::connectTimeout(
                    4
                )
                ->timeout(
                    8
                )
                ->acceptJson()
                ->withHeaders([
                    'X-Accretion-Timestamp' =>
                        $timestamp,

                    'X-Accretion-Signature' =>
                        $signature,
                ])
                ->withBody(
                    $body,
                    'application/json'
                )
                ->post(
                    $url
                );


            /*
            |--------------------------------------------------------------------------
            | HTTP Error
            |--------------------------------------------------------------------------
            */

            if (!$response->successful()) {

                Log::warning(
                    'Website AI Notes API returned an error.',
                    [
                        'product_id' =>
                            $product->id,

                        'website_service_type_id' =>
                            $websiteServiceTypeId,

                        'http_status' =>
                            $response->status(),
                    ]
                );


                return [
                    'status' =>
                        'unavailable',

                    'locations' =>
                        [],
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Parse JSON
            |--------------------------------------------------------------------------
            */

            $payload =
                $response->json();


            if (
                !is_array($payload)
                ||
                !(
                    $payload['success']
                    ?? false
                )
            ) {
                return [
                    'status' =>
                        'unavailable',

                    'locations' =>
                        [],
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Normalize Website Locations
            |--------------------------------------------------------------------------
            */

            $locations =
                collect(
                    $payload['locations']
                    ?? []
                )
                ->filter(
                    function ($location) {

                        if (
                            !is_array(
                                $location
                            )
                        ) {
                            return false;
                        }


                        return trim(
                            (string) (
                                $location['ai_note']
                                ?? ''
                            )
                        ) !== '';
                    }
                )
                ->map(
                    function ($location) {

                        return [
                            'location_id' =>
                                isset(
                                    $location[
                                        'location_id'
                                    ]
                                )
                                    ? (int) $location[
                                        'location_id'
                                    ]
                                    : null,

                            'name' =>
                                trim(
                                    (string) (
                                        $location['name']
                                        ?? ''
                                    )
                                ),

                            'slug' =>
                                trim(
                                    (string) (
                                        $location['slug']
                                        ?? ''
                                    )
                                ),

                            'city_id' =>
                                isset(
                                    $location['city_id']
                                )
                                    ? (int) $location[
                                        'city_id'
                                    ]
                                    : null,

                            'city' =>
                                trim(
                                    (string) (
                                        $location['city']
                                        ?? ''
                                    )
                                ),

                            'ai_note' =>
                                trim(
                                    (string) (
                                        $location['ai_note']
                                        ?? ''
                                    )
                                ),
                        ];
                    }
                )
                ->values()
                ->all();


            return [
                'status' =>
                    'ok',

                'website_service_type_id' =>
                    $websiteServiceTypeId,

                'locations' =>
                    $locations,
            ];

        } catch (Throwable $exception) {

            /*
            |--------------------------------------------------------------------------
            | Non-blocking Failure
            |--------------------------------------------------------------------------
            |
            | Product page / WhatsApp AI must continue working
            | even if Website is temporarily unavailable.
            |
            */

            Log::warning(
                'Website AI Notes are temporarily unavailable.',
                [
                    'product_id' =>
                        $product->id,

                    'website_service_type_id' =>
                        $websiteServiceTypeId,

                    'error' =>
                        $exception->getMessage(),
                ]
            );


            return [
                'status' =>
                    'unavailable',

                'locations' =>
                    [],
            ];
        }
    }
}