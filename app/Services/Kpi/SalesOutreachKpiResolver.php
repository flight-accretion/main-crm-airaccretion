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
    int $workingDaysPerMonth,
    ?Carbon $from = null
): array {
        $stats = $this->workingDays->stats(
            $user,
            $asOf,
            $workingDaysPerMonth
        );

        $dailyTarget = max(
            0,
            (int) round(
                (float) ($metric->target_value ?? 0)
            )
        );

        /*
        |--------------------------------------------------------------------------
        | KPI configuration guard
        |--------------------------------------------------------------------------
        |
        | Do not silently invent a target of 50.
        | The KPI target must come from KPI Management.
        |
        */
        if ($dailyTarget <= 0) {
            return [
                'actual_value' => 0,
                'target_value' => 0,
                'achievement_percent' => 0,

                'evidence' => [
                    'configuration_error' =>
                        'Daily outreach target is not configured for this KPI metric.',

                    'today_completed' => 0,
                    'mtd_completed' => 0,
                    'expected_to_date' => 0,
                    'monthly_target' => 0,
                    'working_days_elapsed' => 0,
                    'working_days_total' => $workingDaysPerMonth,
                    'working_days_remaining' => $workingDaysPerMonth,
                    'remaining_monthly_calls' => 0,
                    'required_per_remaining_working_day' => 0,
                ],
            ];
        }
        $expectedToDate = $stats['working_days_elapsed'] * $dailyTarget;
        $monthlyTarget = $stats['working_days_total'] * $dailyTarget;
     $periodStart = ($from ?: $asOf->copy()->startOfMonth())
    ->copy()
    ->startOfDay();

        $periodEnd = $asOf->copy()->endOfDay();

        $periodCompleted = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereBetween('completed_at', [
                $periodStart,
                $periodEnd,
            ])
            ->count();

            $periodWorkingDays = $stats['dates']
    ->filter(function (Carbon $date) use (
        $periodStart,
        $periodEnd
    ) {
        return $date->betweenIncluded(
            $periodStart->copy()->startOfDay(),
            $periodEnd->copy()->startOfDay()
        );
    })
    ->count();

$expectedForPeriod =
    $periodWorkingDays * $dailyTarget;

        $today = KpiOutreachAssignment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('completed_at', $asOf->toDateString())
            ->count();

    $achievement = $expectedForPeriod > 0
    ? ($periodCompleted / $expectedForPeriod) * 100
    : 0.0;

        $remainingCalls = max(0, $monthlyTarget - $mtd);
        $requiredPerRemainingDay = $stats['working_days_remaining'] > 0
            ? (int) ceil($remainingCalls / $stats['working_days_remaining'])
            : $remainingCalls;

        return [
            'actual_value' => $periodCompleted,
'target_value' => $expectedForPeriod,
            'achievement_percent' => $achievement,
            'evidence' => [
                'today_completed' => $today,
                'mtd_completed' => $periodCompleted,
                'expected_to_date' => $expectedForPeriod,
                'period_completed' => $periodCompleted,
                'period_working_days' => $periodWorkingDays,
                'period_target' => $expectedForPeriod,
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
