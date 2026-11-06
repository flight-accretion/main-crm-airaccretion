<?php

namespace App\Http\Controllers;

use App\Models\Msg91WhatsAppCampaignRecipient;
use App\Services\WhatsApp\Msg91\Msg91DeliveryStatusService;
use Illuminate\Http\Request;

class Msg91WhatsAppWebhookController extends Controller
{
    public function handle(
        Request $request,
        Msg91DeliveryStatusService $deliveryStatuses
    ) {
        $secret = trim((string) config('services.msg91_campaigns.webhook_secret'));

        abort_if(
            $secret === ''
            || !hash_equals(
                $secret,
                (string) $request->header('X-MSG91-Webhook-Secret')
            ),
            401
        );

        $payload = $request->validate([
            'request_id' => [
                'nullable',
                'required_without:message_id',
                'string',
                'max:191',
            ],
            'message_id' => [
                'nullable',
                'required_without:request_id',
                'string',
                'max:191',
            ],
            'event_id' => ['nullable', 'string', 'max:191'],
            'status' => ['required', 'string', 'max:30'],
        ]);

        $status = strtolower(trim((string) $payload['status']));

        if (!in_array(
            $status,
            ['accepted', 'sent', 'delivered', 'read', 'failed'],
            true
        )) {
            return response()->json([
                'accepted' => true,
                'ignored' => true,
            ], 202);
        }

        $recipient = Msg91WhatsAppCampaignRecipient::query()
            ->where(function ($query) use ($payload) {
                if (!empty($payload['request_id'])) {
                    $query->orWhere(
                        'provider_request_id',
                        $payload['request_id']
                    );
                }

                if (!empty($payload['message_id'])) {
                    $query->orWhere(
                        'provider_message_id',
                        $payload['message_id']
                    );
                }
            })
            ->first();

        if (!$recipient) {
            return response()->json([
                'accepted' => true,
                'matched' => false,
            ], 202);
        }

        $deliveryStatuses->apply(
            $recipient->id,
            $status,
            [
                'source' => 'msg91-webhook',
                'event_id' => $payload['event_id'] ?? null,
                'request_id' => $payload['request_id'] ?? null,
                'message_id' => $payload['message_id'] ?? null,
            ]
        );

        return response()->json([
            'accepted' => true,
            'matched' => true,
        ]);
    }
}