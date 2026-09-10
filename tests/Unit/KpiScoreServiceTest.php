<?php

namespace Tests\Unit;

use App\Services\Kpi\KpiScoreService;
use PHPUnit\Framework\TestCase;

class KpiScoreServiceTest extends TestCase
{
    public function test_it_returns_whole_metric_scores(): void
    {
        $service = new KpiScoreService();
        $rules = [
            '5' => 100,
            '4' => 90,
            '3' => 80,
            '2' => 70,
            '1' => 0,
        ];

        $this->assertSame(5, $service->scoreHigherIsBetter(120, $rules));
        $this->assertSame(5, $service->scoreHigherIsBetter(100, $rules));
        $this->assertSame(4, $service->scoreHigherIsBetter(95, $rules));
        $this->assertSame(3, $service->scoreHigherIsBetter(85, $rules));
        $this->assertSame(2, $service->scoreHigherIsBetter(75, $rules));
        $this->assertSame(1, $service->scoreHigherIsBetter(69.99, $rules));
    }

    public function test_it_bands_weighted_overall_score_to_whole_five_scale(): void
    {
        $service = new KpiScoreService();

        $this->assertSame(5, $service->displayOverallScore(4.50));
        $this->assertSame(4, $service->displayOverallScore(4.49));
        $this->assertSame(3, $service->displayOverallScore(3.49));
        $this->assertSame(2, $service->displayOverallScore(2.49));
        $this->assertSame(1, $service->displayOverallScore(1.49));
    }
}
