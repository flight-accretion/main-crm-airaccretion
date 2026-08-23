<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatCrmMessageIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class WhatCrmMessageController extends Controller
{
    public function store(
        Request $request,
        WhatCrmMessageIngestionService $service
    ) {
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
                'message_id' =>
                    'nullable|string|max:500',
                'provider_message_id' =>
                    'nullable|string|max:500',
                'chat_id' =>
                    'nullable|string|max:255',
                'whatcrm_chat_id' =>
                    'nullable|string|max:255',
                'number' =>
                    'required_without_all:phone,from,wa_id,contact_number,messageObject.to|string|max:50',
                'phone' =>
                    'nullable|string|max:50',
                'from' =>
                    'nullable|string|max:50',
                'wa_id' =>
                    'nullable|string|max:50',
                'contact_number' =>
                    'nullable|string|max:50',
                'customer_name' =>
                    'nullable|string|max:255',
                'name' =>
                    'nullable|string|max:255',
                'message' =>
                    'nullable',
                'body' =>
                    'nullable|string',
                'text' =>
                    'nullable',
                'messageObject' =>
                    'nullable|array',
                'messageObject.to' =>
                    'nullable|string|max:50',
                'messageObject.type' =>
                    'nullable|string|max:30',
                'messageObject.text' =>
                    'nullable|array',
                'messageObject.text.body' =>
                    'nullable|string',
                'contact' =>
                    'nullable|array',
                'contact.name' =>
                    'nullable|string|max:255',
                'message_type' =>
                    'nullable|string|max:30',
                'direction' =>
                    'nullable|in:incoming,outgoing',
                'message_at' =>
                    'nullable|string|max:100',
                'timestamp' =>
                    'nullable|string|max:100',
                'status' =>
                    'nullable|string|max:50',
                'agent_user_id' =>
                    'nullable|string|max:100',
                'crm_user_id' =>
                    'nullable|string|max:100',
                'agent_name' =>
                    'nullable|string|max:255',
                'sender_name' =>
                    'nullable|string|max:255',
                'whatcrm_agent_id' =>
                    'nullable|string|max:100',
                'agent_id' =>
                    'nullable|string|max:100',
                'service' =>
                    'nullable|string|max:255',
                'city' =>
                    'nullable|string|max:255',
                'date' =>
                    'nullable|string|max:100',
                'guest' =>
                    'nullable',
                'occasion' =>
                    'nullable|string|max:255',
                'ocassion' =>
                    'nullable|string|max:255',
                'raw_payload' =>
                    'nullable|array',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid WhatCRM message data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $service->process(
                $payload
            );

            return response()->json(
                $result,
                $result['duplicate'] ? 200 : 201
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error(
                'WhatCRM message webhook processing failed',
                [
                    'error' => $exception->getMessage(),
                    'payload' => $payload,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'CRM could not process the message.',
            ], 500);
        }
    }
}
