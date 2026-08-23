<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatCrmOutboundMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class WhatCrmSendMessageController extends Controller
{
    public function store(
        Request $request,
        WhatCrmOutboundMessageService $service
    ) {
        $validator = Validator::make(
            $request->all(),
            [
                'number' =>
                    'required_without:to|string|max:50',
                'to' =>
                    'nullable|string|max:50',
                'name' =>
                    'nullable|string|max:255',
                'customer_name' =>
                    'nullable|string|max:255',
                'message' =>
                    'required_without:body|string|max:4096',
                'body' =>
                    'nullable|string|max:4096',
                'chat_id' =>
                    'nullable|string|max:255',
                'agent_user_id' =>
                    'nullable|string|max:100',
                'crm_user_id' =>
                    'nullable|string|max:100',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid WhatCRM send-message data.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            return response()->json(
                $service->sendText(
                    $validator->validated()
                )
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error(
                'WhatCRM outbound message send failed',
                [
                    'error' => $exception->getMessage(),
                    'number_present' =>
                        $request->filled('number')
                        || $request->filled('to'),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'CRM could not send the WhatCRM message.',
            ], 500);
        }
    }
}
