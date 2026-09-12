<?php

namespace Tests\Unit;

use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use Tests\TestCase;

class LeadAiScoreTemperatureTest extends TestCase
{
    public function test_display_temperature_is_derived_from_score_not_stored_temperature(): void
    {
        $setting =
            new LeadAiScoringSetting([
                'cold_max' => 39,
                'neutral_max' => 69,
            ]);

        $score =
            new LeadAiScore([
                'score' => 55,
                'temperature' => 'hot',
            ]);

        $this->assertSame(
            'neutral',
            $score->displayTemperature($setting)
        );
    }

    public function test_temperature_thresholds_use_configured_setting_values(): void
    {
        $setting =
            new LeadAiScoringSetting([
                'cold_max' => 10,
                'neutral_max' => 80,
            ]);

        $this->assertSame('cold', $setting->temperatureFor(10));
        $this->assertSame('neutral', $setting->temperatureFor(11));
        $this->assertSame('neutral', $setting->temperatureFor(80));
        $this->assertSame('hot', $setting->temperatureFor(81));
    }
}
