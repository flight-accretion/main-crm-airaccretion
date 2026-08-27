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
}
