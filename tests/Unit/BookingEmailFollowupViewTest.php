<?php

namespace Tests\Unit;

use Tests\TestCase;

class BookingEmailFollowupViewTest extends TestCase
{
    public function test_booking_email_success_message_uses_shared_project_modal(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/follow-ups/add-follow-up.blade.php')
        );

        $this->assertStringContainsString(
            "@include('admin.partials.modals.success-error-modals')",
            $source
        );
        $this->assertMatchesRegularExpression(
            "/showSuccessMessage\\(\\s*'send-booking-email'\\s*,/",
            $source
        );
        $this->assertStringNotContainsString(
            'function showSuccessMessage(message)',
            $source
        );
    }

    public function test_registration_link_success_messages_keep_inline_toast(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/follow-ups/add-follow-up.blade.php')
        );

        $this->assertStringContainsString('booking-success-toast', $source);
        $this->assertStringContainsString('function showFollowupToastMessage(message)', $source);
        $this->assertStringContainsString(
            "showFollowupToastMessage('Registration link generated successfully!')",
            $source
        );
    }
}
