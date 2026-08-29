<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppLeadController extends Controller
{
    public function store(
        Request $request,
        WhatsAppLeadService $service
    ) {
        if (!config('whatcrm.legacy_lead_api_enabled', true)) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Legacy WhatCRM lead API is disabled. Use /api/whatcrm/messages.',
            ], 410);
        }

        /*
         * Support both:
         *
         * { ... }
         *
         * and:
         *
         * [ { ... } ]
         */
        $payload = $request->all();

        if (
            array_is_list($payload)
            && count($payload) === 1
        ) {
            $payload = $payload[0];
        }

        $validator = Validator::make(
            $payload,
            [
                'name' =>
                    'nullable|string|max:255',

                'number' =>
                    'required|string|max:30',

                'service' =>
                    'required|string|max:255',

                'date' =>
                    'nullable|string|max:100',

                'ocassion' =>
                    'nullable|string|max:255',

                'occasion' =>
                    'nullable|string|max:255',

                'city' =>
                    'nullable|string|max:255',

                'guest' =>
                    'nullable',

                'external_id' =>
                    'nullable|string|max:255',
            ]
        );

        if ($validator->fails()) {

            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid WhatsApp lead data.',
                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        try {

            return response()->json(
                $service->process(
                    $validator->validated()
                )
            );

        } catch (\Throwable $e) {

            Log::error(
                'WhatCRM webhook processing failed',
                [
                    'error' =>
                        $e->getMessage(),

                    'payload' =>
                        $payload,
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'CRM could not process the lead.',
            ], 500);
        }
    }
}
