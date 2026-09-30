<?php

namespace Tests\Feature\GoogleChat;

use App\Models\{Lead, User, UserType};
use Illuminate\Support\Str;

class GoogleChatUiTest extends GoogleChatTestCase
{
    public function test_connection_controls_render_inside_existing_chat(): void
    {
        $this->harden();
        config(['services.google_chat.enabled' => true]);
        $actor = new User(['id' => (string) Str::uuid(), 'name' => 'Sales']);
        $actor->setRelation('userType', new UserType(['user_type' => UserType::SALES_EXECUTIVE]));
        $this->actingAs($actor);
        $lead = new Lead(['id' => (string) Str::uuid(), 'representative_user_id' => $actor->id]);
        $html = view('admin.pages.follow-ups.partials.lead-chat', compact('lead'))->render();
        $this->assertStringContainsString('data-google-connect', $html);
        $this->assertStringContainsString('lead-chat-send', $html);
        $this->assertStringContainsString('google_sync_status', $html);
        $this->assertStringContainsString('Retry Google delivery', $html);
    }
}
