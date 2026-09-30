<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{LeadChatConversation, LeadChatMessage, GoogleChatIdentity};
use App\Services\GoogleChat\{GoogleChatClient, GoogleChatInboundService};
use App\Services\LeadChat\LeadChatNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoogleChatInboundTest extends GoogleChatTestCase
{
    private function fixture(): array
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
        $this->mock(LeadChatNotificationService::class)->shouldReceive('notifyGoogleMessage')->zeroOrMoreTimes();
        return [$c, $google];
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
