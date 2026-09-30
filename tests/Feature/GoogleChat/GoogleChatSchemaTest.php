<?php

namespace Tests\Feature\GoogleChat;

use App\Models\LeadChatConversation;
use App\Models\GoogleChatEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class GoogleChatSchemaTest extends GoogleChatTestCase
{
    public function test_existing_mappings_survive_migration(): void
    {
        $conversation = LeadChatConversation::create([
            'lead_id' => (string) Str::uuid(), 'google_space_name' => 'spaces/one',
            'google_thread_name' => 'spaces/one/threads/t1', 'google_thread_key' => 'lead-one',
        ]);
        $this->harden();
        $this->assertSame('spaces/one/threads/t1', $conversation->fresh()->google_thread_name);
        $this->assertSame('ready', $conversation->fresh()->google_connection_status);
        $id = (string) Str::uuid();
        $conversation->update(['operations_user_id' => $id]);
        $this->assertSame($id, $conversation->fresh()->operations_user_id);
    }

    public function test_event_delivery_id_is_unique(): void
    {
        $this->harden();
        GoogleChatEvent::create(['delivery_id' => 'one', 'event_type' => 'created', 'payload' => []]);
        $this->expectException(QueryException::class);
        GoogleChatEvent::create(['delivery_id' => 'one', 'event_type' => 'created', 'payload' => []]);
    }
}
