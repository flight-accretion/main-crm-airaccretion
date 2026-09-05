<?php

namespace Tests\Unit;

use Tests\TestCase;

class WhatsAppInboxLiveRefreshViewTest extends TestCase
{
    public function test_inbox_view_has_background_live_refresh_polling(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/admin/pages/whatsapp/inbox.blade.php'
            )
        );

        $this->assertStringContainsString(
            'pollIntervalMs',
            $source
        );
        $this->assertStringContainsString(
            'pollInbox',
            $source
        );
        $this->assertStringContainsString(
            'silent: true',
            $source
        );
        $this->assertStringContainsString(
            'document.hidden',
            $source
        );
    }

    public function test_inbox_view_has_show_more_control_for_previous_chats(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/admin/pages/whatsapp/inbox.blade.php'
            )
        );

        $this->assertStringContainsString(
            'wa-show-more',
            $source
        );
        $this->assertStringContainsString(
            'loadMoreConversations',
            $source
        );
        $this->assertStringContainsString(
            'hasMore',
            $source
        );
    }

    public function test_inbox_view_shows_followup_badge_and_view_lead_action(): void
    {
        $source = file_get_contents(
            resource_path(
                'views/admin/pages/whatsapp/inbox.blade.php'
            )
        );

        $this->assertStringContainsString(
            'followups_count',
            $source
        );
        $this->assertStringContainsString(
            'wa-followup-pill',
            $source
        );
        $this->assertStringContainsString(
            'lead_followup_url',
            $source
        );
        $this->assertStringContainsString(
            'wa-view-lead',
            $source
        );
    }
}
