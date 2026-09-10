<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;

class KpiImprovementService
{
    public function build(
        KpiMetric $metric,
        int $score,
        array $resolved
    ): array {
        if ($score >= 5) {
            return [
                'next_score' => null,
                'next_threshold' => null,
                'gap_value' => 0,
                'improvement_line' => 'Maintain current performance to retain 5/5.',
            ];
        }

        $nextScore = min(5, $score + 1);
        $threshold = $this->threshold($metric, $nextScore);

        return match ($metric->code) {
            'daily_outreach' => $this->dailyOutreach($nextScore, $threshold, $resolved),
            'lead_conversion' => $this->leadConversion($nextScore, $threshold, $resolved),
            'monthly_target' => $this->monthlyTarget($nextScore, $threshold, $resolved),
            'response_time' => $this->responseTime($nextScore, $threshold, $resolved),
            'followup_sla' => $this->followupSla($nextScore, $threshold, $resolved),
            'payment_collection' => $this->paymentCollection($nextScore, $threshold, $resolved),
            default => $this->generic($metric, $nextScore, $threshold, $resolved),
        };
    }

    private function threshold(KpiMetric $metric, int $score): float
    {
        $rules = (array) $metric->score_rules;

        return (float) ($rules[(string) $score] ?? $rules[$score] ?? 0);
    }

    private function dailyOutreach(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $completed = (int) ($evidence['mtd_completed'] ?? $resolved['actual_value'] ?? 0);
        $expected = (int) ($evidence['expected_to_date'] ?? $resolved['target_value'] ?? 0);
        $required = $expected > 0
            ? (int) ceil($expected * ($threshold / 100))
            : 0;
        $gap = max(0, $required - $completed);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => $gap,
            'improvement_line' => sprintf(
                'Complete %d more verified outreach action%s to reach %s%% of the current rolling target and move to %d/5.',
                $gap,
                $gap === 1 ? '' : 's',
                $this->number($threshold),
                $nextScore
            ),
        ];
    }

    private function leadConversion(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $booked = (int) ($evidence['booked_leads'] ?? $resolved['actual_value'] ?? 0);
        $total = (int) ($evidence['total_leads'] ?? $resolved['target_value'] ?? 0);
        $requiredBooked = $total > 0
            ? (int) ceil($total * ($threshold / 100))
            : 0;
        $gap = max(0, $requiredBooked - $booked);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => $gap,
            'improvement_line' => $total <= 0
                ? 'There are no eligible incoming leads yet for this KPI.'
                : sprintf(
                    'Convert %d more eligible incoming lead%s to bookings to reach at least %s%% and move to %d/5.',
                    $gap,
                    $gap === 1 ? '' : 's',
                    $this->number($threshold),
                    $nextScore
                ),
        ];
    }

    private function monthlyTarget(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $achieved = (float) ($evidence['achieved_amount'] ?? $resolved['actual_value'] ?? 0);
        $target = (float) ($evidence['target_amount'] ?? $resolved['target_value'] ?? 0);
        $requiredAmount = $target * ($threshold / 100);
        $gap = max(0, $requiredAmount - $achieved);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => round($gap, 2),
            'improvement_line' => $target <= 0
                ? 'No active monthly target is configured for this KPI.'
                : sprintf(
                    'Achieve Rs %s more in approved sales to reach %s%% of target and move to %d/5.',
                    number_format($gap, 0),
                    $this->number($threshold),
                    $nextScore
                ),
        ];
    }

    private function responseTime(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $average = (float) ($evidence['average_response_minutes'] ?? $resolved['actual_value'] ?? 0);
        $gap = max(0, $average - $threshold);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => round($gap, 2),
            'improvement_line' => sprintf(
                'Reduce the average qualifying response time by %s minutes to %s minutes or less to move to %d/5.',
                $this->number($gap),
                $this->number($threshold),
                $nextScore
            ),
        ];
    }

    private function followupSla(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $within = (int) ($evidence['within_4_hours'] ?? $resolved['actual_value'] ?? 0);
        $total = (int) ($evidence['eligible_pending_cases'] ?? $resolved['target_value'] ?? 0);
        $required = $total > 0
            ? (int) ceil($total * ($threshold / 100))
            : 0;
        $gap = max(0, $required - $within);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => $gap,
            'improvement_line' => $total <= 0
                ? 'There are no eligible pending follow-up cases yet for this KPI.'
                : sprintf(
                    'Complete %d more eligible pending follow-up%s within 4 hours to reach %s%% and move to %d/5.',
                    $gap,
                    $gap === 1 ? '' : 's',
                    $this->number($threshold),
                    $nextScore
                ),
        ];
    }

    private function paymentCollection(
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $evidence = $resolved['evidence'] ?? [];
        $eligible = (int) ($evidence['eligible_payment_customers'] ?? $resolved['target_value'] ?? 0);
        $compliant = (int) ($resolved['actual_value'] ?? 0);
        $required = $eligible > 0
            ? (int) ceil($eligible * ($threshold / 100))
            : 0;
        $gap = max(0, $required - $compliant);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => $gap,
            'improvement_line' => $eligible <= 0
                ? 'There are no eligible payment customers yet for this KPI.'
                : sprintf(
                    'Move %d more eligible payment customer%s into a compliant state by collecting full payment or properly following up the balance after partial payment to reach %s%% and move to %d/5.',
                    $gap,
                    $gap === 1 ? '' : 's',
                    $this->number($threshold),
                    $nextScore
                ),
        ];
    }

    private function generic(
        KpiMetric $metric,
        int $nextScore,
        float $threshold,
        array $resolved
    ): array {
        $actual = (float) ($resolved['achievement_percent'] ?? $resolved['actual_value'] ?? 0);

        if ($metric->direction === 'lower_better') {
            $gap = max(0, $actual - $threshold);

            return [
                'next_score' => $nextScore,
                'next_threshold' => $threshold,
                'gap_value' => round($gap, 2),
                'improvement_line' => sprintf(
                    'Reduce this KPI result to %s or better to move to %d/5.',
                    $this->number($threshold),
                    $nextScore
                ),
            ];
        }

        $gap = max(0, $threshold - $actual);

        return [
            'next_score' => $nextScore,
            'next_threshold' => $threshold,
            'gap_value' => round($gap, 2),
            'improvement_line' => sprintf(
                'Reach at least %s%% on this KPI to move to %d/5.',
                $this->number($threshold),
                $nextScore
            ),
        ];
    }

    private function number(float $value): string
    {
        if (abs($value - round($value)) < 0.0001) {
            return number_format($value, 0);
        }

        return rtrim(
            rtrim(number_format($value, 2, '.', ''), '0'),
            '.'
        );
    }
}
