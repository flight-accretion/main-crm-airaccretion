<?php

namespace Tests\Unit;

use App\Models\LeadFollowup;
use App\Services\ExtraServiceQuoteCalculator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExtraServiceQuoteCalculatorTest extends TestCase
{
    public function test_replaces_itemized_extra_without_repricing_base_line(): void
    {
        $base = ['type' => 'service', 'original_amount' => 31000, 'discount_amount' => 1000];
        $old = ['type' => 'extra_service', 'id' => 'old', 'original_amount' => 2000, 'discount_amount' => 0];
        $new = ['type' => 'extra_service', 'id' => 'new', 'original_amount' => 5000, 'discount_amount' => 0];
        $quote = new LeadFollowup(['service_details' => [$base, $old], 'extra_service_ids' => ['old'],
            'service_amount' => 33000, 'discount_amount' => 1000, 'total_amount' => 32000]);

        $calculator = new ExtraServiceQuoteCalculator();
        $result = $calculator->replaceExtras($quote, [$new]);
        $this->assertSame([$base, $new], $result['service_details']);
        $this->assertSame(['new'], $result['extra_service_ids']);
        $this->assertSame(35000.0, $result['total_amount']);
        $this->assertSame(30000.0, $calculator->replaceExtras($quote, [])['total_amount']);

        $quote->total_amount = 31000;
        $this->expectException(ValidationException::class);
        $calculator->replaceExtras($quote, [$new]);
    }
}
