<?php

namespace Tests\Unit;

use App\Models\CustomerRefundSettlement;
use App\Models\CustomerRefundTransaction;
use App\Services\Rides\CustomerRefundWorkflowService as Workflow;
use PHPUnit\Framework\TestCase;

class CustomerRefundWorkflowServiceTest extends TestCase
{
    private function settlement(string $due, array $paid = []): CustomerRefundSettlement
    {
        $settlement = new CustomerRefundSettlement([
            'refund_due' => $due,
            'approved_paid' => '30000.00',
            'cancellation_amount' => '5000.00',
            'reason' => 'Cancellation',
        ]);
        $settlement->setRelation('transactions', collect(array_map(
            fn ($amount) => new CustomerRefundTransaction(['amount' => $amount, 'refund_type' => 'UPI']),
            $paid
        )));
        return $settlement;
    }

    public function test_zero_refund_does_not_enter_pending(): void
    {
        $status = (new Workflow())->snapshot($this->settlement('0.00'));
        $this->assertSame('No Refund Required', $status['label']);
    }

    public function test_partial_refund_preserves_balance(): void
    {
        $status = (new Workflow())->snapshot($this->settlement('25000.00', ['10000.00']));
        $this->assertSame('Partially Refunded', $status['label']);
        $this->assertSame('15000.00', $status['remaining']);
    }

    public function test_full_payment_is_refunded(): void
    {
        $status = (new Workflow())->snapshot($this->settlement('25000.00', ['10000.00', '15000.00']));
        $this->assertSame('Refunded', $status['label']);
        $this->assertSame('0.00', $status['remaining']);
    }
}
