<?php

namespace App\Http\Controllers;

use App\Models\Msg91WhatsAppCampaignEvent;
use App\Models\Msg91WhatsAppCampaignRecipient;
use App\Services\WhatsApp\Msg91\Msg91CampaignService;
use Illuminate\Http\Request;

class Msg91WhatsAppWebhookController extends Controller
{
    public function handle(Request $request, Msg91CampaignService $campaigns)
    {
        $secret = trim((string) config('services.msg91_campaigns.webhook_secret'));
        abort_if($secret === '' || !hash_equals($secret, (string) $request->header('X-MSG91-Webhook-Secret')), 401);
        $payload = $request->validate(['request_id' => ['nullable', 'string', 'max:191'], 'message_id' => ['nullable', 'string', 'max:191'], 'event_id' => ['nullable', 'string', 'max:191'], 'status' => ['required', 'string', 'max:30']]);
        $recipient = Msg91WhatsAppCampaignRecipient::query()->where('provider_message_id', $payload['message_id'] ?? null)->orWhere('provider_request_id', $payload['request_id'] ?? null)->first();
        if (!$recipient) return response()->json(['accepted' => true]);
        $status = strtolower($payload['status']);
        $allowed = ['accepted', 'sent', 'delivered', 'read', 'failed'];
        if (!in_array($status, $allowed, true)) $status = 'unknown';
        $attributes = [
            'status' => $status,
            'provider_message_id' => $payload['message_id'] ?? $recipient->provider_message_id,
        ];
        if (in_array($status, ['accepted', 'sent', 'delivered', 'read'], true)) {
            $attributes[$status . '_at'] = now();
        }
        $recipient->forceFill($attributes)->save();
        Msg91WhatsAppCampaignEvent::firstOrCreate(['provider_event_id' => $payload['event_id'] ?? sha1(json_encode($payload)), 'event_type' => 'webhook'], ['campaign_recipient_id' => $recipient->id, 'campaign_id' => $recipient->campaign_id, 'status' => $status, 'payload' => $payload, 'occurred_at' => now()]);
        $campaigns->refreshCounters($recipient->campaign);
        return response()->json(['accepted' => true]);
    }
}
