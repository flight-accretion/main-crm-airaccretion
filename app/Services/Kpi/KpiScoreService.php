<?php

namespace App\Services\Kpi;

class KpiScoreService
{
    public function scoreHigherIsBetter(float $achievementPercent, array $rules): int
    {
        foreach ([5, 4, 3, 2, 1] as $score) {
            $minimum = (float) ($rules[(string) $score] ?? $rules[$score] ?? 0);

            if ($achievementPercent >= $minimum) {
                return $score;
            }
        }

        return 1;
    }

    public function scoreLowerIsBetter(float $actual, array $rules): int
    {
        foreach ([5, 4, 3, 2, 1] as $score) {
            $maximum = (float) ($rules[(string) $score] ?? $rules[$score] ?? INF);

            if ($actual <= $maximum) {
                return $score;
            }
        }

        return 1;
    }

    public function weightedRaw(array $metrics): float
    {
        $weight = 0.0;
        $weighted = 0.0;

        foreach ($metrics as $metric) {
            $w = max(0, (float) ($metric['weightage'] ?? 0));
            $s = min(5, max(1, (int) ($metric['score'] ?? 1)));

            $weight += $w;
            $weighted += $s * $w;
        }

        return $weight > 0
            ? $weighted / $weight
            : 1.0;
    }

    public function displayOverallScore(float $raw): int
    {
        if ($raw >= 4.50) {
            return 5;
        }

        if ($raw >= 3.50) {
            return 4;
        }

        if ($raw >= 2.50) {
            return 3;
        }

        if ($raw >= 1.50) {
            return 2;
        }

        return 1;
    }
}
