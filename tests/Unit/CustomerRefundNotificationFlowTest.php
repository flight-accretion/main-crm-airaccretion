<?php

namespace Tests\Unit;

use App\Http\Controllers\SendMessageController;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomerRefundNotificationFlowTest extends TestCase
{
    public function test_customer_refunds_always_dispatch_to_active_notification_master_recipients(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RideController.php')
        );

        $this->assertStringContainsString('$mail->cc($notificationMasterCcEmails);', $controller);
        $this->assertStringContainsString('$vendorEmails = $lead->leadVendorPayments', $controller);
        $this->assertStringContainsString('$vendorPhones = $lead->leadVendorPayments', $controller);
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
        config()->set('services.operations_ride_alert.api_url', 'https://operations.example.test/send_templet');
        config()->set('services.operations_ride_alert.api_key', 'operations-api-key');
        config()->set('services.operations_ride_alert.token', 'operations-ivr-token');

        Http::fake([
            'https://operations.example.test/send_templet' => Http::response([
                'success' => true,
                'message_id' => 'refund-message-1',
            ]),
        ]);

        $result = (new SendMessageController())->sendWhatsCrmRefundMessage(
            '+91 9876543210',
            ['Customer', 'Service', '29th October, 2026', '1000.00', '29th October, 2026'],
            'https://crm.example.test/storage/refunds/proof.pdf',
            'proof.pdf'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('refund-message-1', $result['provider_message_id']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://operations.example.test/send_templet'
                && $request->hasHeader('Authorization', 'Bearer operations-api-key')
                && $request->data() === [
                    'sendTo' => '+919876543210',
                    'templetName' => 'refund_cust_notify_v2',
                    'exampleArr' => ['Customer', 'Service', '29th October, 2026', '1000.00', '29th October, 2026'],
                    'token' => 'operations-ivr-token',
                    'mediaUri' => 'https://crm.example.test/storage/refunds/proof.pdf',
                    'enableLog' => true,
                ];
        });
    }

    public function test_customer_refund_image_uses_the_image_template_with_operations_account(): void
    {
        config()->set('services.operations_ride_alert.api_url', 'https://operations.example.test/send_templet');
        config()->set('services.operations_ride_alert.api_key', 'operations-api-key');
        config()->set('services.operations_ride_alert.token', 'operations-ivr-token');

        Http::fake([
            'https://operations.example.test/send_templet' => Http::response(['success' => true]),
        ]);

        (new SendMessageController())->sendWhatsCrmRefundMessage(
            '9876543210',
            ['Customer', 'Service', 'Date', '1000.00', 'Refund date'],
            'https://crm.example.test/storage/refunds/proof.jpg',
            'proof.jpg'
        );

        Http::assertSent(fn ($request) => $request->data()['templetName'] === 'refund_cust_notify_v2_img');
    }
}
