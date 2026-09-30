<?php

namespace Tests\Feature\GoogleChat;

use App\Http\Controllers\Api\GoogleChatPubSubController;
use App\Models\GoogleChatEvent;
use App\Services\GoogleChat\GoogleChatPushVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;

class GoogleChatWebhookTest extends GoogleChatTestCase
{
    public function test_batch_progress_survives_a_later_message_failure(): void
    {
        $this->harden();
        config(['services.google_chat.enabled' => true]);
        $event = GoogleChatEvent::create(['delivery_id' => 'batch', 'event_type' => 'google.workspace.chat.message.v1.batchCreated',
            'payload' => ['messages' => [['name' => 'spaces/one/messages/a'], ['name' => 'spaces/one/messages/b']]]]);
        $inbound = \Mockery::mock(\App\Services\GoogleChat\GoogleChatInboundService::class)->makePartial();
        $calls = 0;
        $inbound->shouldReceive('handle')->andReturnUsing(function () use (&$calls) {
            if (++$calls === 2) throw new \RuntimeException('Temporary outage');
        });
        try { (new \App\Jobs\ProcessGoogleChatEvent($event->id))->handle($inbound); } catch (\RuntimeException $e) {}
        $this->assertSame(1, $event->fresh()->processed_count);
        $this->assertSame('failed', $event->fresh()->status);
        (new \App\Jobs\ProcessGoogleChatEvent($event->id))->handle($inbound);
        $this->assertSame(2, $event->fresh()->processed_count);
        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame(3, $calls);
    }
    public function test_valid_delivery_is_durable_and_deduplicated_before_acknowledgement(): void
    {
        $this->harden(); Queue::fake();
        config(['services.google_chat.enabled' => true]);
        $verifier = $this->mock(GoogleChatPushVerifier::class);
        $verifier->shouldReceive('verify')->with('test-token')->twice()->andReturn([]);
        $request = Request::create('/api/google-chat/pubsub', 'POST', [
            'message' => ['messageId' => 'delivery-1', 'attributes' => [
                'ce-type' => 'google.workspace.chat.message.v1.created', 'ce-time' => '2026-09-29T10:00:00Z'],
                'data' => base64_encode(json_encode(['message' => ['name' => 'spaces/one/messages/a']]))],
        ]);
        $request->headers->set('Authorization', 'Bearer test-token');
        $controller = app(GoogleChatPubSubController::class);
        $this->assertSame(204, $controller->handle($request, $verifier)->getStatusCode());
        $this->assertSame(204, $controller->handle($request, $verifier)->getStatusCode());
        $this->assertSame(1, GoogleChatEvent::count());
        $this->assertSame('pending', GoogleChatEvent::first()->status);
    }
}
