<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use Carbon\Carbon;

class SalesResponseTimeKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private SalesKpiLeadScopeService $leadScope,
        private SalesKpiCommunicationService $communications
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $leads = $this->leadScope
            ->incoming($user, $asOf)
            ->get();

        if ($leads->isEmpty()) {
            return [
                'actual_value' => 0,
                'target_value' => 15,
                'achievement_percent' => null,
                'evidence' => [
                    'eligible_leads' => 0,
                    'responded' => 0,
                    'unresponded' => 0,
                    'average_response_minutes' => 0,
                    'median_response_minutes' => 0,
                    'note' => 'No eligible incoming leads yet.',
                ],
            ];
        }

        $scoringMinutes = [];
        $actualMinutes = [];
        $responded = 0;
        $unresponded = 0;

        foreach ($leads as $lead) {
            $createdAt = Carbon::parse($lead->created_at);
            $responseAt = $this->communications->firstResponseAt(
                $lead,
                $user,
                $createdAt
            );

            if (!$responseAt) {
                $unresponded++;
                $scoringMinutes[] = 61.0;
                continue;
            }

            $responded++;
            $minutes = max(0, $createdAt->diffInSeconds($responseAt) / 60);
            $actualMinutes[] = $minutes;
            $scoringMinutes[] = min(61.0, $minutes);
        }

        $average = collect($scoringMinutes)->avg() ?: 0.0;
        $median = collect($actualMinutes)->median() ?: 0.0;
        $within15 = collect($actualMinutes)
            ->filter(fn ($minutes) => $minutes <= 15)
            ->count();

        return [
            'actual_value' => round($average, 2),
            'target_value' => 15,
            'achievement_percent' => null,
            'evidence' => [
                'eligible_leads' => $leads->count(),
                'responded' => $responded,
                'unresponded' => $unresponded,
                'within_15_minutes' => $within15,
                'average_response_minutes' => round($average, 2),
                'median_response_minutes' => round($median, 2),
                'note' => sprintf(
                    'Average first response = %.2f min; Median = %.2f min; Unresponded = %d.',
                    $average,
                    $median,
                    $unresponded
                ),
            ],
        ];
    }
}
