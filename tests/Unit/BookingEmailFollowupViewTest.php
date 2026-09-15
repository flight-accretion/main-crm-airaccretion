<?php

namespace Tests\Unit;

use Tests\TestCase;

class BookingEmailFollowupViewTest extends TestCase
{
    public function test_booking_email_success_message_uses_toast_markup(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/follow-ups/add-follow-up.blade.php')
        );

        $this->assertStringContainsString('booking-success-toast', $source);
        $this->assertStringContainsString("setAttribute('role', 'status')", $source);
        $this->assertStringContainsString("setAttribute('aria-live', 'polite')", $source);
        $this->assertStringContainsString('textContent = message', $source);
        $this->assertStringNotContainsString(
            "alert alert-success alert-dismissible fade show position-fixed",
            $source
        );
    }
}
