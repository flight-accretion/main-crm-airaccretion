<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;

class SalesFollowupSlaKpiResolver implements KpiMetricResolverInterface
{
    private const PENDING_STATUSES = [
        1,
        4,
        6,
        7,
        8,
    ];

    public function __construct(
        private SalesKpiCommunicationService $communications
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $monthStart = $asOf->copy()->startOfMonth();

        $due = LeadFollowup::query()
            ->with('enquiry')
            ->whereHas('enquiry', function ($query) use ($user) {
                $query->where('representative_user_id', $user->id);
            })
            ->whereIn('status', self::PENDING_STATUSES)
            ->whereNotNull('next_followup_date')
            ->whereBetween('next_followup_date', [
                $monthStart,
                $asOf,
            ])
            ->orderBy('next_followup_date')
            ->get();

        $total = $due->count();
        $withinFourHours = 0;

        foreach ($due as $followup) {
            $lead = $followup->enquiry;

            if (!$lead) {
                continue;
            }

            $dueAt = Carbon::parse($followup->next_followup_date);
            $activityAt = $this->communications->firstResponseAt(
                $lead,
                $user,
                $dueAt
            );

            if (
                $activityAt
                && $dueAt->diffInMinutes($activityAt) <= 240
            ) {
                $withinFourHours++;
            }
        }

        $achievement = $total > 0
            ? ($withinFourHours / $total) * 100
            : 100.0;

        return [
            'actual_value' => $withinFourHours,
            'target_value' => $total,
            'achievement_percent' => $achievement,
            'evidence' => [
                'eligible_pending_cases' => $total,
                'within_4_hours' => $withinFourHours,
                'outside_4_hours_or_missing' => max(0, $total - $withinFourHours),
                'achievement_percent' => round($achievement, 2),
                'note' => sprintf(
                    '%d of %d pending cases were followed up within 4 hours.',
                    $withinFourHours,
                    $total
                ),
            ],
        ];
    }
}
