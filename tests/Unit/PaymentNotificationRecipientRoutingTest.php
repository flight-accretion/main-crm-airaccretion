<?php

namespace Tests\Unit;

use Tests\TestCase;

class PaymentNotificationRecipientRoutingTest extends TestCase
{
    public function test_vendor_payment_ccs_internal_recipients_without_sending_vendor_payment_to_customer(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/VendorPaymentController.php')
        );
        $storePaymentStart = strpos($controller, "\n    public function storePayment(");
        $storePaymentEnd = strpos($controller, "\n    public function ", $storePaymentStart + 1);
        $storePayment = substr($controller, $storePaymentStart, $storePaymentEnd - $storePaymentStart);

        $this->assertStringContainsString('$mail->cc($notificationMasterCcEmails);', $storePayment);
        $this->assertStringContainsString('\Illuminate\Support\Facades\Mail::to($vendorEmail)', $storePayment);
        $this->assertStringContainsString('NotificationMaster::activeInternalRecipients', $storePayment);
        $this->assertStringNotContainsString('Mail::to($nmEmail)->send', $storePayment);
    }

    public function test_vendor_refund_ccs_internal_recipients_without_sending_vendor_refund_to_customer(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/RideController.php')
        );

        $vendorRefundStart = strpos($controller, 'private function sendVendorRefundNotifications(');
        $vendorRefundEnd = strpos($controller, 'private function formatNotificationDate(', $vendorRefundStart);
        $vendorRefund = substr($controller, $vendorRefundStart, $vendorRefundEnd - $vendorRefundStart);

        $this->assertStringContainsString('$mail->cc($notificationMasterCcEmails);', $vendorRefund);
        $this->assertStringContainsString('Mail::to($vendorEmail)', $vendorRefund);
        $this->assertStringNotContainsString('Mail::to($nmEmail)->send', $vendorRefund);
        $this->assertStringContainsString('Vendor refund notification masters', $controller);
    }
}
