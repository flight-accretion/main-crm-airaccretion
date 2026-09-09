<?php

namespace Tests\Unit;

use App\Services\BookingConfirmationEmailService;
use Tests\TestCase;

class BookingConfirmationEmailServicePaymentBreakdownTest extends TestCase
{
    public function test_payment_due_breakdown_does_not_include_balance()
    {
        $service =
            new BookingConfirmationEmailService();

        $result =
            $service->buildPaymentBreakdown(
                53000,
                [
                    'payment_mode' =>
                        'payment_due',

                    'advance_amount' =>
                        20000,
                ]
            );

        $this->assertStringContainsString(
            'Total Service Cost: ₹53,000.00',
            $result
        );

        $this->assertStringContainsString(
            'Advance Payment Due Now: ₹20,000.00',
            $result
        );

        $this->assertStringNotContainsString(
            'Balance Amount',
            $result
        );

        $this->assertStringNotContainsString(
            'Balance Due By',
            $result
        );
    }


    public function test_installment_breakdown_contains_multiple_installments()
    {
        $service =
            new BookingConfirmationEmailService();

        $result =
            $service->buildPaymentBreakdown(
                53000,
                [
                    'payment_mode' =>
                        'installment',

                    'installments' => [
                        [
                            'date' =>
                                '2026-09-15',

                            'amount' =>
                                20000,
                        ],
                        [
                            'date' =>
                                '2026-09-25',

                            'amount' =>
                                33000,
                        ],
                    ],
                ]
            );

        $this->assertStringContainsString(
            'Total Service Cost: ₹53,000.00',
            $result
        );

        $this->assertStringContainsString(
            'INSTALLMENT SCHEDULE',
            $result
        );

        $this->assertStringContainsString(
            '1. ₹20,000.00 due on 15 September 2026',
            $result
        );

        $this->assertStringContainsString(
            '2. ₹33,000.00 due on 25 September 2026',
            $result
        );

        $this->assertStringNotContainsString(
            'Balance Amount',
            $result
        );
    }
}