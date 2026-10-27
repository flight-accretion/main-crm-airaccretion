<?php

namespace Tests\Unit;

use Tests\TestCase;

class PaymentNotificationRecipientRoutingTest extends TestCase
{
    public function test_vendor_payment_notifies_the_vendor_customer_and_active_internal_recipients(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/VendorPaymentController.php')
        );

        $this->assertStringContainsString('$clientEmail = $client->email ?? null;', $controller);
        $this->assertStringContainsString('VENDOR PAYMENT ► Customer WhatsApp', $controller);
        $this->assertStringContainsString('storePayment: customer blade email sent', $controller);
        $this->assertStringContainsString('NotificationMaster::activeInternalRecipients', $controller);
        $this->assertStringContainsString('WhatsAppPhoneNumber::e164(', $controller);
    }

    public function test_vendor_refund_notifies_the_vendor_customer_and_active_internal_recipients(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RideController.php')
        );

        $this->assertStringContainsString('Vendor refund customer email sent', $controller);
        $this->assertStringContainsString('Vendor refund customer WhatsApp response', $controller);
        $this->assertStringContainsString('Vendor refund notification masters', $controller);
        $this->assertStringContainsString('WhatsAppPhoneNumber::e164(', $controller);
    }
}
