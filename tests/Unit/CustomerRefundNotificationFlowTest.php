<?php

namespace Tests\Unit;

use App\Http\Controllers\SendMessageController;
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

    public function test_customer_refund_pdf_uses_the_main_whatscrm_sender(): void
    {
        $bodyValues = ['Customer', 'Service', '29th October, 2026', '1000.00', '29th October, 2026'];
        $mediaUrl = 'https://crm.example.test/storage/refunds/proof.pdf';
        $sender = new class extends SendMessageController {
            public array $templateCall = [];

            public function sendWhatsCrmTemplateMessage(
                string $toNumber,
                string $templateName,
                array $bodyValues,
                ?string $mediaUri = null
            ) {
                $this->templateCall = func_get_args();

                return ['success' => true, 'provider_message_id' => 'refund-message-1'];
            }
        };

        $result = $sender->sendWhatsCrmRefundMessage(
            '+91 9876543210',
            $bodyValues,
            $mediaUrl,
            'proof.pdf'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('refund-message-1', $result['provider_message_id']);
        $this->assertSame([
            '+91 9876543210',
            'refund_cust_notify_v2',
            $bodyValues,
            $mediaUrl,
        ], $sender->templateCall);

        $controller = file_get_contents(app_path('Http/Controllers/SendMessageController.php'));
        $senderStart = strpos($controller, 'public function sendWhatsCrmTemplateMessage(');
        $senderEnd = strpos($controller, 'public function sendSmsMessage(', $senderStart);
        $genericSender = substr($controller, $senderStart, $senderEnd - $senderStart);

        $this->assertStringContainsString("config('services.whatscrm.api_url')", $genericSender);
        $this->assertStringContainsString("config('services.whatscrm.api_token')", $genericSender);
    }

    public function test_customer_refund_image_uses_the_image_template_and_forwards_media(): void
    {
        $bodyValues = ['Customer', 'Service', 'Date', '1000.00', 'Refund date'];
        $mediaUrl = 'https://crm.example.test/storage/refunds/proof.jpg';
        $sender = new class extends SendMessageController {
            public array $templateCall = [];

            public function sendWhatsCrmTemplateMessage(
                string $toNumber,
                string $templateName,
                array $bodyValues,
                ?string $mediaUri = null
            ) {
                $this->templateCall = func_get_args();

                return ['success' => true];
            }
        };

        $result = $sender->sendWhatsCrmRefundMessage(
            '9876543210',
            $bodyValues,
            $mediaUrl,
            'proof.jpg'
        );

        $this->assertTrue($result['success']);
        $this->assertSame([
            '9876543210',
            'refund_cust_notify_v2_img',
            $bodyValues,
            $mediaUrl,
        ], $sender->templateCall);
    }
}
