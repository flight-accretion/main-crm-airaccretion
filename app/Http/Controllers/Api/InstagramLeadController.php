<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppLeadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InstagramLeadController extends Controller
{
    public function store(
        Request $request,
        WhatsAppLeadService $service
    ) {
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

                'IG' =>
                    'nullable|string|max:255',

                'service' =>
                    'nullable|string|max:255',

                'date' =>
                    'nullable|string|max:100',

                'ocassion' =>
                    'nullable|string|max:255',

                'occassion' =>
                    'nullable|string|max:255',

                'occasion' =>
                    'nullable|string|max:255',

                'guest' =>
                    'nullable',

                'type' =>
                    'nullable|string|max:255',

                'id' =>
                    'nullable',

                'createdAt' =>
                    'nullable|string|max:100',

                'updatedAt' =>
                    'nullable|string|max:100',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Invalid Instagram lead data.',
                'errors' =>
                    $validator->errors(),
            ], 422);
        }

        $leadData =
            $validator->validated();

        if (!empty($leadData['IG'])) {
            $leadData['external_id'] =
                $leadData['IG'];

            $leadData['instagram_id'] =
                $leadData['IG'];
        }

        if (
            !isset($leadData['occasion'])
            && isset($leadData['occassion'])
        ) {
            $leadData['occasion'] =
                $leadData['occassion'];
        }

        if (
            !isset($leadData['occasion'])
            && isset($leadData['ocassion'])
        ) {
            $leadData['occasion'] =
                $leadData['ocassion'];
        }

        if (array_key_exists('id', $leadData)) {
            $leadData['instagram_record_id'] =
                $leadData['id'];
        }

        try {
            return response()->json(
                $service->process(
                    $leadData,
                    [
                        'key' => 'instagram',
                        'label' => 'Instagram',
                        'request_label' => 'Instagram',
                        'lead_name_prefix' => 'Instagram Lead ',
                        'assigned_action' => 'instagram_assigned',
                        'queue_prefix' => 'instagram',
                        'routing_label' => 'Instagram',
                        'empty_product_on_unmapped' => true,
                        'description_intro' =>
                            'Lead received automatically from Instagram.',
                    ]
                )
            );
        } catch (\Throwable $e) {
            Log::error(
                'Instagram lead processing failed',
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
                    'CRM could not process the Instagram lead.',
            ], 500);
        }
    }
}
