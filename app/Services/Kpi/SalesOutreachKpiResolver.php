<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\KpiOutreachAssignment;
use App\Models\User;
use Carbon\Carbon;

class SalesOutreachKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private KpiWorkingDayService $workingDays
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $stats = $this->workingDays->stats(
            $user,
            $asOf,
            $workingDaysPerMonth
        );

        $dailyTarget = (int) ($metric->target_value ?: 50);
        $expectedToDate = $stats['working_days_elapsed'] * $dailyTarget;
        $monthlyTarget = $stats['working_days_total'] * $dailyTarget;
        $monthStart = $asOf->copy()->startOfMonth();

        $mtd = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [
                $monthStart,
                $asOf->copy()->endOfDay(),
            ])
            ->count();

        $today = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $asOf->toDateString())
            ->count();

        $achievement = $expectedToDate > 0
            ? ($mtd / $expectedToDate) * 100
            : 100.0;

        $remainingCalls = max(0, $monthlyTarget - $mtd);
        $requiredPerRemainingDay = $stats['working_days_remaining'] > 0
            ? (int) ceil($remainingCalls / $stats['working_days_remaining'])
            : $remainingCalls;

        return [
            'actual_value' => $mtd,
            'target_value' => $expectedToDate,
            'achievement_percent' => $achievement,
            'evidence' => [
                'today_completed' => $today,
                'mtd_completed' => $mtd,
                'expected_to_date' => $expectedToDate,
                'monthly_target' => $monthlyTarget,
                'working_days_elapsed' => $stats['working_days_elapsed'],
                'working_days_total' => $stats['working_days_total'],
                'working_days_remaining' => $stats['working_days_remaining'],
                'remaining_monthly_calls' => $remainingCalls,
                'required_per_remaining_working_day' => $requiredPerRemainingDay,
            ],
        ];
    }
}
