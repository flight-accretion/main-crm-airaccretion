<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebsiteCatalogProductSyncService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class WebsiteCatalogProductController extends Controller
{
    public function store(
        Request $request,
        WebsiteCatalogProductSyncService $syncService
    ) {
        /*
        |--------------------------------------------------------------------------
        | Validate Website Payload
        |--------------------------------------------------------------------------
        */

        $validator =
            Validator::make(
                $request->all(),
                [
                    'event_id' => [
                        'required',
                        'uuid',
                    ],

                    'event_type' => [
                        'required',
                        'in:product.upsert',
                    ],

                    'website_service_type_id' => [
                        'required',
                        'integer',
                        'min:1',
                    ],

                    'name' => [
                        'required',
                        'string',
                        'max:255',
                    ],

                    /*
                     * Used ONLY during the
                     * first reconciliation.
                     */
                    'reconcile' => [
                        'nullable',
                        'boolean',
                    ],

                    'occurred_at' => [
                        'nullable',
                        'date',
                    ],
                ]
            );


        if ($validator->fails()) {
            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Validation failed.',

                    'errors' =>
                        $validator->errors(),
                ],
                422
            );
        }


        try {

            /*
            |--------------------------------------------------------------------------
            | Product Synchronization
            |--------------------------------------------------------------------------
            */

            $result =
                $syncService->sync(
                    $validator->validated()
                );


            return response()->json(
                [
                    'success' =>
                        true,

                    'data' =>
                        $result,
                ],
                200
            );

        } catch (
            DomainException $exception
        ) {

            /*
             * Used for initial-reconciliation
             * ambiguity, for example duplicate
             * exact Product names.
             */

            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        $exception->getMessage(),
                ],
                409
            );

        } catch (
            Throwable $exception
        ) {

            Log::error(
                'Website Product catalog synchronization failed.',
                [
                    'website_service_type_id' =>
                        $request->input(
                            'website_service_type_id'
                        ),

                    'event_id' =>
                        $request->input(
                            'event_id'
                        ),

                    'error' =>
                        $exception->getMessage(),
                ]
            );


            return response()->json(
                [
                    'success' =>
                        false,

                    'message' =>
                        'Catalog synchronization failed.',
                ],
                500
            );
        }
    }
}