<?php

namespace Tests\Unit;

use App\Models\KpiMetric;
use App\Services\Kpi\KpiImprovementService;
use PHPUnit\Framework\TestCase;

class KpiImprovementServiceTest extends TestCase
{
    public function test_daily_outreach_gap_uses_next_threshold_and_expected_count(): void
    {
        $metric = new KpiMetric([
            'code' => 'daily_outreach',
            'score_rules' => [
                '5' => 100,
                '4' => 90,
                '3' => 80,
                '2' => 70,
                '1' => 0,
            ],
        ]);

        $result = (new KpiImprovementService())->build(
            $metric,
            4,
            [
                'actual_value' => 365,
                'target_value' => 400,
                'evidence' => [
                    'mtd_completed' => 365,
                    'expected_to_date' => 400,
                ],
            ]
        );

        $this->assertSame(5, $result['next_score']);
        $this->assertSame(100.0, $result['next_threshold']);
        $this->assertSame(35, $result['gap_value']);
        $this->assertStringContainsString(
            'Complete 35 more verified outreach actions',
            $result['improvement_line']
        );
    }

    public function test_conversion_gap_uses_integer_booking_gap(): void
    {
        $metric = new KpiMetric([
            'code' => 'lead_conversion',
            'score_rules' => [
                '5' => 40,
                '4' => 35,
                '3' => 30,
                '2' => 25,
                '1' => 0,
            ],
        ]);

        $result = (new KpiImprovementService())->build(
            $metric,
            2,
            [
                'evidence' => [
                    'booked_leads' => 15,
                    'total_leads' => 59,
                ],
            ]
        );

        $this->assertSame(3, $result['next_score']);
        $this->assertSame(30.0, $result['next_threshold']);
        $this->assertSame(3, $result['gap_value']);
        $this->assertStringContainsString(
            'Convert 3 more eligible incoming leads',
            $result['improvement_line']
        );
    }

    public function test_five_out_of_five_asks_to_maintain_current_performance(): void
    {
        $metric = new KpiMetric([
            'code' => 'monthly_target',
            'score_rules' => [
                '5' => 100,
                '4' => 90,
                '3' => 80,
                '2' => 70,
                '1' => 0,
            ],
        ]);

        $result = (new KpiImprovementService())->build(
            $metric,
            5,
            []
        );

        $this->assertNull($result['next_score']);
        $this->assertSame(
            'Maintain current performance to retain 5/5.',
            $result['improvement_line']
        );
    }
}
