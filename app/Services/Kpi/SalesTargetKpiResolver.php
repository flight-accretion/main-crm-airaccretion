<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use App\Services\TargetResolverService;
use Carbon\Carbon;

class SalesTargetKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private TargetResolverService $targets
    ) {}

   public function resolve(
    User $user,
    KpiMetric $metric,
    Carbon $asOf,
    int $workingDaysPerMonth,
    ?Carbon $from = null
): array {
        $target = $this->targets->targetForUser(
            $user->id,
            $asOf->year,
            $asOf->month
        );

        if (!$target) {
            return [
                'actual_value' => 0,
                'target_value' => 0,
                'achievement_percent' => 0,
                'evidence' => [
                    'target_amount' => 0,
                    'achieved_amount' => 0,
                    'remaining_amount' => 0,
                    'note' => 'No active monthly target is configured for this employee.',
                ],
            ];
        }

        $start = ($from ?: $asOf->copy()->startOfMonth())->copy()->startOfDay();
        $achieved = (float) app(\App\Services\TargetIncentiveService::class)
            ->ledgerForPeriod($start, $asOf)->where('owner', (string) $user->id)->sum('amount');
        $targetAmount = 0.0;
        for ($month = $start->copy()->startOfMonth(); $month->lte($asOf); $month->addMonth()) {
            $targetAmount += (float) ($this->targets->targetForUser($user->id, $month->year, $month->month)?->target_amount ?? 0);
        }
        $achievement = $targetAmount > 0
            ? ($achieved / $targetAmount) * 100
            : 0.0;

        return [
            'actual_value' => $achieved,
            'target_value' => $targetAmount,
            'achievement_percent' => $achievement,
            'evidence' => [
                'target_amount' => $targetAmount,
                'achieved_amount' => $achieved,
                'remaining_amount' => max(0, $targetAmount - $achieved),
                'achievement_percent' => round($achievement, 2),
                'note' => $achievement >= 100
                    ? 'Monthly sales target achieved.'
                    : sprintf(
                        'Target not achieved yet. Achieved %.2f%% of the monthly target.',
                        $achievement
                    ),
            ],
        ];
    }
}
