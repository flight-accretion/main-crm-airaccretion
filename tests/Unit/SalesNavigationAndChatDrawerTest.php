<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SalesNavigationAndChatDrawerTest extends TestCase
{
    public function test_sales_navigation_uses_direct_work_done_and_mobile_more_pattern(): void
    {
        $root = dirname(__DIR__, 2);
        $header = file_get_contents($root . '/resources/views/admin/layouts/header.blade.php');
        $mobileNavigation = file_get_contents($root . '/resources/views/admin/layouts/partials/operations-mobile-nav.blade.php');
        $notifications = file_get_contents($root . '/resources/views/admin/layouts/partials/lead-chat-notifications.blade.php');

        $this->assertStringContainsString('$isSalesSidebar', $header);
        $this->assertStringContainsString('Daily Work Done', $header);
        $this->assertStringContainsString('Assign more leads', $header);
        $this->assertStringContainsString("[route('admin.upcoming-follow-up.index'), 'ri-time-line', 'Follow-up']", $mobileNavigation);
        $this->assertStringContainsString("[route('admin.clients.index'), 'ri-group-line', 'Leads']", $mobileNavigation);
        $this->assertStringContainsString('lead-chat-notification-drawer', $notifications);
        $this->assertStringContainsString("notificationDrawer?.classList.add('open')", $notifications);
        $this->assertStringContainsString('function closeNotificationDrawer()', $notifications);
        $this->assertStringContainsString("notificationCloseButton?.addEventListener('click', closeNotificationDrawer)", $notifications);
        $this->assertStringContainsString('lead-chat-notification-modal__box', $notifications);
    }
}
