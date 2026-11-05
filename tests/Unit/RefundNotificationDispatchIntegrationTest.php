<?php

namespace Tests\Unit;

use Tests\TestCase;

class RefundNotificationDispatchIntegrationTest extends TestCase
{
    public function test_two_step_customer_payment_queues_the_existing_customer_notification_sender(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/CustomerRefundTwoStepController.php')
        );

        $this->assertStringContainsString(
            '$this->queueCustomerRefundNotifications($ride->id);',
            $controller
        );
        $this->assertStringContainsString(
            'app(RideController::class)->sendRefundEmail(request(), $rideId)',
            $controller
        );
    }

    public function test_operations_vendor_refund_updates_queue_the_existing_vendor_notification_sender(): void
    {
        $controller = file_get_contents(
            app_path('Http/Controllers/OperationsVendorRefundController.php')
        );

        $this->assertStringContainsString(
            'queueVendorRefundNotifications((string) $refund->getKey())',
            $controller
        );
    }
}
