<?php

namespace Tests\Unit;

use Tests\TestCase;

class CustomerRefundNotificationFlowTest extends TestCase
{
    public function test_customer_refunds_always_dispatch_to_active_notification_master_recipients(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RideController.php')
        );

        $this->assertStringContainsString(
            'NotificationMaster::activeInternalRecipients',
            $controller
        );
        $this->assertStringContainsString(
            'WhatsAppPhoneNumber::e164(',
            $controller
        );
        $this->assertStringNotContainsString(
            'notifications skipped — user role not permitted',
            $controller
        );
    }

    public function test_customer_refund_whatsapp_uses_the_operations_account(): void
    {
        $sender = file_get_contents(
            app_path('Http/Controllers/SendMessageController.php')
        );

        $start = strpos($sender, 'public function sendWhatsCrmRefundMessage(');
        $end = strpos($sender, 'public function sendWhatsCrmRegistrationLinkMessage(', $start);
        $refundMethod = substr($sender, $start, $end - $start);

        $this->assertStringContainsString(
            "config('services.whatscrm.api_url')",
            $refundMethod
        );
        $this->assertStringNotContainsString(
            "config('services.whatscrm_vouchers.api_url')",
            $refundMethod
        );
    }
}
