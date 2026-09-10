<?php

namespace App\Services\Kpi;

use App\Models\KpiManualValue;
use App\Models\KpiUserAssignment;
use App\Models\User;
use Carbon\Carbon;

class KpiDashboardService
{
    public function __construct(
        private KpiMetricResolverRegistry $registry,
        private KpiScoreService $scores,
        private KpiImprovementService $improvements
    ) {}

    public function forUser(User $user, Carbon $asOf): array
    {
        $assignment = KpiUserAssignment::query()
            ->with(['template.metrics'])
            ->where('user_id', $user->id)
            ->where('active', true)
            ->whereDate('effective_from', '<=', $asOf->toDateString())
            ->where(function ($query) use ($asOf) {
                $query
                    ->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $asOf->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if (!$assignment || !$assignment->template || !$assignment->template->active) {
            return [
                'configured' => false,
                'user' => $user,
                'metrics' => [],
                'overall_score' => null,
            ];
        }

        $template = $assignment->template;
        $rows = [];

        foreach ($template->metrics->where('active', true) as $metric) {
            if ($metric->measurement_type === 'automatic') {
                $resolved = $this->registry
                    ->resolver((string) $metric->source_key)
                    ->resolve(
                        $user,
                        $metric,
                        $asOf,
                        (int) $template->working_days_per_month
                    );

                $score = $metric->direction === 'lower_better'
                    ? $this->scores->scoreLowerIsBetter(
                        (float) ($resolved['actual_value'] ?? 0),
                        (array) $metric->score_rules
                    )
                    : $this->scores->scoreHigherIsBetter(
                        (float) ($resolved['achievement_percent'] ?? 0),
                        (array) $metric->score_rules
                    );
            } else {
                [$score, $resolved] = $this->manualMetric(
                    $user,
                    $metric,
                    $asOf
                );
            }

            $improvement = $this->improvements->build(
                $metric,
                $score,
                $resolved
            );

            $rows[] = [
                'id' => $metric->id,
                'code' => $metric->code,
                'name' => $metric->name,
                'description' => $metric->description,
                'score' => $score,
                'weightage' => (float) $metric->weightage,
                'actual_value' => $resolved['actual_value'] ?? null,
                'target_value' => $resolved['target_value'] ?? null,
                'achievement_percent' => $resolved['achievement_percent'] ?? null,
                'evidence' => $resolved['evidence'] ?? [],
                'display_actual' => $this->displayActual($metric->code, $resolved),
                'next_score' => $improvement['next_score'],
                'next_threshold' => $improvement['next_threshold'],
                'gap_value' => $improvement['gap_value'],
                'improvement_line' => $improvement['improvement_line'],
            ];
        }

        $raw = $this->scores->weightedRaw($rows);

        return [
            'configured' => true,
            'user' => $user,
            'template' => $template,
            'metrics' => $rows,
            'weighted_raw_score' => $raw,
            'overall_score' => $this->scores->displayOverallScore($raw),
        ];
    }

    private function manualMetric(
        User $user,
        $metric,
        Carbon $asOf
    ): array {
        $manual = KpiManualValue::query()
            ->where('user_id', $user->id)
            ->where('metric_id', $metric->id)
            ->where('year', $asOf->year)
            ->where('month', $asOf->month)
            ->first();

        if ($metric->measurement_type === 'manual_rating') {
            $score = min(5, max(1, (int) ($manual?->rating ?: 1)));

            return [
                $score,
                [
                    'actual_value' => $manual?->rating,
                    'target_value' => 5,
                    'achievement_percent' => null,
                    'evidence' => [
                        'note' => $manual?->note,
                    ],
                ],
            ];
        }

        $actual = (float) ($manual?->value ?: 0);
        $target = (float) ($metric->target_value ?: 0);
        $achievement = $target > 0
            ? ($actual / $target) * 100
            : 0;

        $score = $metric->direction === 'lower_better'
            ? $this->scores->scoreLowerIsBetter(
                $actual,
                (array) $metric->score_rules
            )
            : $this->scores->scoreHigherIsBetter(
                $achievement,
                (array) $metric->score_rules
            );

        return [
            $score,
            [
                'actual_value' => $actual,
                'target_value' => $target,
                'achievement_percent' => $achievement,
                'evidence' => [
                    'note' => $manual?->note,
                ],
            ],
        ];
    }

    private function displayActual(string $code, array $resolved): string
    {
        $evidence = $resolved['evidence'] ?? [];

        return match ($code) {
            'daily_outreach' => sprintf(
                '%d / %d expected MTD',
                (int) ($evidence['mtd_completed'] ?? $resolved['actual_value'] ?? 0),
                (int) ($evidence['expected_to_date'] ?? $resolved['target_value'] ?? 0)
            ),
            'lead_conversion' => sprintf(
                '%d bookings / %d leads = %.2f%%',
                (int) ($evidence['booked_leads'] ?? 0),
                (int) ($evidence['total_leads'] ?? 0),
                (float) ($evidence['conversion_rate'] ?? 0)
            ),
            'monthly_target' => sprintf(
                'Rs %s / Rs %s',
                number_format((float) ($evidence['achieved_amount'] ?? 0), 0),
                number_format((float) ($evidence['target_amount'] ?? 0), 0)
            ),
            'response_time' => sprintf(
                'Average %.2f min',
                (float) ($evidence['average_response_minutes'] ?? 0)
            ),
            'followup_sla' => sprintf(
                '%d / %d within 4 hours',
                (int) ($evidence['within_4_hours'] ?? 0),
                (int) ($evidence['eligible_pending_cases'] ?? 0)
            ),
            'payment_collection' => sprintf(
                '%d compliant / %d eligible',
                (int) ($resolved['actual_value'] ?? 0),
                (int) ($evidence['eligible_payment_customers'] ?? 0)
            ),
            'attendance' => sprintf(
                '%.2f%% punctuality',
                (float) ($resolved['achievement_percent'] ?? $resolved['actual_value'] ?? 0)
            ),
            default => is_numeric($resolved['actual_value'] ?? null)
                ? (string) $resolved['actual_value']
                : '-',
        };
    }
}
