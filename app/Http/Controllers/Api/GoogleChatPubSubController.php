<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGoogleChatEvent;
use App\Models\GoogleChatEvent;
use App\Services\GoogleChat\GoogleChatPushVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GoogleChatPubSubController extends Controller
{
    public function handle(Request $request, GoogleChatPushVerifier $verifier)
    {
        $verifier->verify((string) $request->bearerToken());
        abort_unless(config('services.google_chat.enabled'), 503);
        $request->validate([
            'message.messageId' => 'required|string|max:255',
            'message.data' => 'required|string|max:1400000',
            'message.attributes.ce-type' => 'required|string|max:255',
            'message.attributes.ce-time' => 'nullable|date',
        ]);
        $decoded = base64_decode($request->input('message.data'), true);
        $payload = $decoded === false ? null : json_decode($decoded, true);
        abort_unless(is_array($payload), 422, 'Invalid event data.');
        $delivery = $request->input('message.messageId');
        $id = (string) Str::uuid();
        DB::transaction(function () use ($request, $payload, $delivery, $id) {
            DB::table('google_chat_events')->insertOrIgnore([
                'id' => $id, 'delivery_id' => $delivery, 'event_type' => $request->input('message.attributes.ce-type'),
                'event_time' => $request->input('message.attributes.ce-time')
                    ? \App\Services\GoogleChat\GoogleChatTime::parse($request->input('message.attributes.ce-time')) : now(),
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'status' => 'pending',
                'attempts' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        // No successful acknowledgment unless the durable inbox row can be read.
        $event = GoogleChatEvent::where('delivery_id', $delivery)->firstOrFail();
        if ($event->status !== 'processed') {
            ProcessGoogleChatEvent::enqueue($event->id);
        }
        return response('', 204);
    }
}
