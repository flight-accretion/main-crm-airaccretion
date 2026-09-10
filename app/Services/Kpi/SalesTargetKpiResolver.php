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
        int $workingDaysPerMonth
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

        $achieved = (float) $target->updateAchievedAmount();
        $targetAmount = (float) $target->target_amount;
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
