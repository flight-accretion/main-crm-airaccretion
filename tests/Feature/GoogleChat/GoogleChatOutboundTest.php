<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{LeadChatConversation, LeadChatMessage};
use App\Services\GoogleChat\{GoogleChatBridgeService, GoogleChatClient};
use Illuminate\Support\Facades\{Bus, Http, Queue};
use Illuminate\Support\Str;

class GoogleChatOutboundTest extends GoogleChatTestCase
{
    public function test_queue_visibility_exceeds_worker_timeout(): void
    {
        $job = new \App\Jobs\SyncLeadChatMessageToGoogleChat('test');
        $this->assertGreaterThan($job->timeout, config('queue.connections.'.$job->connection.'.retry_after'));
    }

    public function test_successful_send_is_not_duplicated(): void
    {
        $m = $this->message();
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('createMessage')->once()->andReturn([
            'name' => 'spaces/one/messages/canonical', 'thread' => ['name' => 'spaces/one/threads/t'],
        ]);
        $service = app(GoogleChatBridgeService::class);
        $service->sync($m->id);
        $service->sync($m->id);
        $this->assertSame('synced', $m->fresh()->google_sync_status);
        $this->assertSame(1, $m->fresh()->google_synced_version);
    }

    public function test_google_failure_leaves_retryable_state(): void
    {
        $m = $this->message();
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('createMessage')->andThrow(new \RuntimeException('Simulated outage'));
        try { app(GoogleChatBridgeService::class)->sync($m->id); } catch (\RuntimeException $e) {}
        $this->assertSame('hello', $m->fresh()->body);
        $this->assertSame('failed', $m->fresh()->google_sync_status);
    }

    public function test_edit_during_delivery_remains_pending(): void
    {
        $m = $this->message();
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('createMessage')->once()->andReturnUsing(function () use ($m) {
            LeadChatMessage::whereKey($m->id)->update(['body' => 'edited during delivery', 'google_sync_version' => 2]);
            return ['name' => 'spaces/one/messages/a', 'thread' => ['name' => 'spaces/one/threads/t']];
        });
        app(GoogleChatBridgeService::class)->sync($m->id);
        $this->assertSame('edited during delivery', $m->fresh()->body);
        $this->assertSame('pending', $m->fresh()->google_sync_status);
        $this->assertSame(1, $m->fresh()->google_synced_version);
    }

    public function test_wrong_remote_thread_is_not_saved_as_success(): void
    {
        $m = $this->message();
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldReceive('createMessage')->once()->andReturn([
            'name' => 'spaces/one/messages/a', 'thread' => ['name' => 'spaces/other/threads/t'],
        ]);
        try { app(GoogleChatBridgeService::class)->sync($m->id); } catch (\RuntimeException $e) {}
        $this->assertSame('failed', $m->fresh()->google_sync_status);
        $this->assertNull($m->fresh()->google_message_name);
    }

    protected function setUp(): void
    {
        parent::setUp(); $this->harden();
        config(['services.google_chat.enabled' => true, 'services.google_chat.allowed_spaces' => ['spaces/one']]);
        Queue::fake();
    }

    private function message(array $attributes = []): LeadChatMessage
    {
        $c = LeadChatConversation::create(['lead_id' => (string) Str::uuid(),
            'google_space_name' => 'spaces/one', 'google_thread_name' => 'spaces/one/threads/t',
            'google_connection_status' => 'ready']);
        return LeadChatMessage::create(array_merge(['conversation_id' => $c->id,
            'lead_id' => $c->lead_id, 'body' => 'hello', 'message_type' => 'text'], $attributes));
    }

    public function test_new_crm_message_has_durable_pending_state(): void
    {
        $m = $this->message();
        $this->assertSame('crm', $m->source);
        $this->assertSame('pending', $m->fresh()->google_sync_status);
        $this->assertSame(1, $m->fresh()->google_sync_version);
    }

    public function test_queue_failure_preserves_message(): void
    {
        Bus::shouldReceive('dispatch')->andThrow(new \RuntimeException('Queue unavailable'));
        $m = $this->message();
        $this->assertNotNull($m->fresh());
        $this->assertSame('pending', $m->fresh()->google_sync_status);
    }

    public function test_deleted_message_is_not_sent(): void
    {
        $m = $this->message(['deleted_at' => now()]);
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldNotReceive('createMessage');
        app(GoogleChatBridgeService::class)->sync($m->id);
        $this->assertSame('not_required', $m->fresh()->google_sync_status);
    }

    public function test_reply_uses_exact_saved_thread_and_space(): void
    {
        Http::fake(['*' => Http::response(['name' => 'spaces/one/messages/a'], 200)]);
        $client = \Mockery::mock(GoogleChatClient::class)->makePartial();
        $client->shouldReceive('accessToken')->andReturn('test');
        $client->createMessage('hello', 'lead-key', (string) Str::uuid(), 'spaces/one', 'spaces/one/threads/t');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'REPLY_MESSAGE_OR_FAIL')
            && $r['thread']['name'] === 'spaces/one/threads/t');
    }

    public function test_existing_remote_message_is_recovered_after_retry(): void
    {
        Http::fakeSequence()->push([], 409)->push(['name' => 'spaces/one/messages/existing'], 200);
        $client = \Mockery::mock(GoogleChatClient::class)->makePartial();
        $client->shouldReceive('accessToken')->andReturn('test');
        $result = $client->createMessage('hello', 'lead-key', (string) Str::uuid(), 'spaces/one');
        $this->assertSame('spaces/one/messages/existing', $result['name']);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_already_deleted_remote_message_is_successful(): void
    {
        Http::fake(['*' => Http::response([], 404)]);
        $client = \Mockery::mock(GoogleChatClient::class)->makePartial();
        $client->shouldReceive('accessToken')->andReturn('test');
        $client->deleteMessage('spaces/one/messages/deleted');
        Http::assertSentCount(1);
    }

    public function test_revoked_space_cannot_receive_queued_messages(): void
    {
        $m = $this->message();
        config(['services.google_chat.allowed_spaces' => []]);
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('enabled')->andReturn(true);
        $client->shouldNotReceive('createMessage');
        try { app(GoogleChatBridgeService::class)->sync($m->id); } catch (\RuntimeException $e) {}
        $this->assertSame('failed', $m->fresh()->google_sync_status);
    }
}
