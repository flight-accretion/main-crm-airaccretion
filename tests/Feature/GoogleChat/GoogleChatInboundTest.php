<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{LeadChatConversation, LeadChatMessage, GoogleChatIdentity};
use App\Services\GoogleChat\{GoogleChatClient, GoogleChatInboundService};
use App\Services\LeadChat\LeadChatNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleChatInboundTest extends GoogleChatTestCase
{
    private function fixture(bool $mockNotifications = true): array
    {
        $this->harden();
        config(['services.google_chat.allowed_spaces' => ['spaces/one']]);
        $lead = (string) Str::uuid();
        DB::table('leads')->insert(['id' => $lead]);
        $c = LeadChatConversation::create(['lead_id' => $lead, 'google_space_name' => 'spaces/one',
            'google_thread_name' => 'spaces/one/threads/t', 'google_connection_status' => 'ready']);
        $google = ['name' => 'spaces/one/messages/a', 'thread' => ['name' => $c->google_thread_name],
            'sender' => ['type' => 'HUMAN', 'name' => 'users/actual', 'displayName' => 'Actual sender'],
            'text' => 'Reply', 'createTime' => '2026-09-29T10:00:00Z'];
        $client = $this->mock(GoogleChatClient::class);
        $client->shouldReceive('listSpaceMembers')->andReturn([
            ['state' => 'JOINED', 'member' => ['name' => 'users/actual', 'type' => 'HUMAN']],
        ]);
        $client->shouldReceive('getMessage')->andReturn($google);
        if ($mockNotifications) {
            $this->mock(LeadChatNotificationService::class)->shouldReceive('notifyGoogleMessage')->zeroOrMoreTimes();
        }
        return [$c, $google];
    }

    public function test_authenticated_delivery_replay_notifies_manager_once(): void
    {
        [$conversation, $google] = $this->fixture(false);
        $role = (string) Str::uuid();
        $manager = (string) Str::uuid();
        DB::table('user_types')->insert(['id' => $role, 'user_type' => \App\Models\UserType::SALES_MANAGER]);
        DB::table('users')->insert(['id' => $manager, 'name' => 'QA Manager', 'user_type_id' => $role]);
        DB::table('leads')->where('id', $conversation->lead_id)->update(['representative_user_id' => $manager]);

        config(['services.google_chat.enabled' => true]);
        \Illuminate\Support\Facades\Bus::fake();
        $this->mock(\App\Services\GoogleChat\GoogleChatPushVerifier::class)
            ->shouldReceive('verify')->with('synthetic-token')->twice()->andReturn([]);
        $payload = ['message' => [
            'messageId' => 'synthetic-delivery',
            'attributes' => ['ce-type' => 'google.workspace.chat.message.v1.created'],
            'data' => base64_encode(json_encode(['message' => ['name' => $google['name']]])),
        ]];
        $headers = ['Authorization' => 'Bearer synthetic-token'];
        $this->postJson('/api/google-chat/pubsub', $payload, $headers)->assertNoContent();
        $event = \App\Models\GoogleChatEvent::sole();
        $job = new \App\Jobs\ProcessGoogleChatEvent($event->id);
        $job->handle(app(GoogleChatInboundService::class));
        $this->postJson('/api/google-chat/pubsub', $payload, $headers)->assertNoContent();
        $job->handle(app(GoogleChatInboundService::class));

        $this->assertSame('processed', $event->fresh()->status);
        $this->assertSame(1, \App\Models\GoogleChatEvent::count());
        $message = LeadChatMessage::sole();
        $this->assertSame($conversation->id, $message->conversation_id);
        $notification = \App\Models\LeadChatNotification::sole();
        $this->assertSame($manager, $notification->user_id);
        $this->assertSame($message->id, $notification->message_id);

        $otherManager = (string) Str::uuid();
        DB::table('users')->insert(['id' => $otherManager, 'name' => 'Other Manager', 'user_type_id' => $role]);
        $url = route('admin.chat-notifications.index');
        $managerUser = \App\Models\User::findOrFail($manager);
        $open = route('admin.chat-notifications.open', $notification);
        $read = route('admin.chat-notifications.read', $notification);
        $this->actingAs($managerUser)->getJson($url)
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.id', $notification->id)
            ->assertJsonPath('notifications.0.body', 'Reply')
            ->assertJsonPath('notifications.0.title', 'Google Chat reply from Actual sender')
            ->assertJsonPath('notifications.0.open_url', $open);
        $this->actingAs(\App\Models\User::findOrFail($otherManager))->getJson($url)
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonCount(0, 'notifications');
        $this->get($open)->assertNotFound();
        $this->postJson($read)->assertNotFound();
        $this->actingAs($managerUser)->postJson($read)->assertOk()->assertJsonPath('success', true);
        $this->getJson($url)->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('notifications.0.is_read', true)
            ->assertJsonCount(1, 'notifications');
        $this->get($open)->assertRedirect(route('admin.leads.follow-up.create', $conversation->lead_id)
            .'?chat=1&message='.$message->id.'#lead-chat-panel');
    }

    public function test_inbound_create_has_uuid_and_actual_identity(): void
    {
        [$c, $g] = $this->fixture();
        $id = (string) Str::uuid();
        GoogleChatIdentity::create(['user_id' => $id, 'google_user_name' => 'users/actual', 'verified_at' => now()]);
        $service = app(GoogleChatInboundService::class);
        $service->handle('google.workspace.chat.message.v1.created', ['message' => ['name' => $g['name']]]);
        $m = LeadChatMessage::firstOrFail();
        $this->assertTrue(Str::isUuid($m->id));
        $this->assertSame($id, $m->sender_user_id);
        $this->assertSame('google_chat', $m->source);
        $service->handle('google.workspace.chat.message.v1.batchCreated', ['messages' => [['name' => $g['name']]]]);
        $this->assertSame(1, LeadChatMessage::count());
    }

    public function test_delete_before_create_does_not_resurrect(): void
    {
        [$c, $g] = $this->fixture();
        $service = app(GoogleChatInboundService::class);
        $service->handle('google.workspace.chat.message.v1.deleted', ['message' => ['name' => $g['name']]]);
        $service->handle('google.workspace.chat.message.v1.created', ['message' => ['name' => $g['name']]]);
        $this->assertSame(0, LeadChatMessage::whereNull('deleted_at')->count());
    }

    public function test_duplicate_and_older_events_do_not_overwrite_newer_text_in_kolkata_timezone(): void
    {
        [$c, $g] = $this->fixture();
        $service = app(GoogleChatInboundService::class);
        $service->ingest($g);
        $service->ingest($g);
        $this->assertNull(LeadChatMessage::first()->edited_at);
        $service->ingest(array_merge($g, ['text' => 'newest', 'lastUpdateTime' => '2026-09-29T11:00:00Z']));
        $service->ingest(array_merge($g, ['text' => 'older', 'lastUpdateTime' => '2026-09-29T10:30:00Z']));
        $this->assertSame('newest', LeadChatMessage::first()->body);
        $this->assertSame('2026-09-29T11:00:00+00:00', LeadChatMessage::first()->google_update_time->utc()->toIso8601String());
    }

    public function test_fractional_timestamp_duplicates_and_stale_edits_are_ignored(): void
    {
        [$c, $g] = $this->fixture();
        $g['createTime'] = '2026-09-29T10:00:00.123456Z';
        $service = app(GoogleChatInboundService::class);
        $service->ingest($g);
        $service->ingest($g);
        $this->assertNull(LeadChatMessage::first()->edited_at);
        $service->ingest(array_merge($g, ['text' => 'newest', 'lastUpdateTime' => '2026-09-29T10:00:00.900000Z']));
        $service->ingest(array_merge($g, ['text' => 'older', 'lastUpdateTime' => '2026-09-29T10:00:00.800000Z']));
        $this->assertSame('newest', LeadChatMessage::first()->body);
    }
}
