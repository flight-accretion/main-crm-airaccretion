<?php

namespace App\Services\Kpi;

use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\KpiTemplate;
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

    /*
     * No eligible data must never produce a perfect KPI score.
     *
     * Examples:
     * Response Time:
     * actual = 0 minutes because there were no enquiries.
     *
     * Payment:
     * achievement = 100 because denominator was zero.
     *
     * Follow-Up:
     * achievement = 100 because there were no eligible cases.
     *
     * Approved business rule:
     * KPI scale is always 1/5 to 5/5.
     * No eligible data = 1/5.
     */
    if (
        $this->hasNoEligibleData(
            (string) $metric->code,
            $resolved
        )
    ) {

        $score = 1;

    } else {

        $score =
            $metric->direction === 'lower_better'
                ? $this->scores->scoreLowerIsBetter(
                    (float) (
                        $resolved['actual_value']
                        ?? 0
                    ),
                    (array) $metric->score_rules
                )
                : $this->scores->scoreHigherIsBetter(
                    (float) (
                        $resolved['achievement_percent']
                        ?? 0
                    ),
                    (array) $metric->score_rules
                );
    }

} else {

    [$score, $resolved] =
        $this->manualMetric(
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
                'sort_order' => (int) $metric->sort_order,
                'score_rules' => (array) $metric->score_rules,
                'rating_labels' => $this->ratingLabels($metric->code, (array) $metric->score_rules),
                'direction' => $metric->direction,
                'display_actual' =>
    $this->hasNoEligibleData(
        (string) $metric->code,
        $resolved
    )
        ? '0'
        : $this->displayActual(
            (string) $metric->code,
            $resolved
        ),
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

private function hasNoEligibleData(
    string $code,
    array $resolved
): bool {
    $evidence =
        $resolved['evidence']
        ?? [];

    return match ($code) {

        'lead_conversion' =>
            (int) (
                $evidence['total_leads']
                ?? 0
            ) === 0,


        'response_time' =>
            (int) (
                $evidence['eligible_leads']
                ?? 0
            ) === 0,


        'followup_sla' =>
            (int) (
                $evidence[
                    'eligible_pending_cases'
                ]
                ?? 0
            ) === 0,


        'payment_collection' =>
            (int) (
                $evidence[
                    'eligible_payment_customers'
                ]
                ?? 0
            ) === 0,
'attendance' =>
    (int) (
        $evidence[
            'eligible_scheduled_days'
        ]
        ?? 0
    ) === 0,

        default =>
            false,
    };
}

/**
 * Return the KPI matrix definition independently
 * of any employee assignment/activity.
 *
 * This guarantees that all Retail KPI rows remain
 * visible on the dashboard.
 */
public function matrixDefinition(
    string $department,
    Carbon $asOf
): array {
    /*
     * First use the current active template from
     * KPI Management.
     */
    $template = KpiTemplate::query()
        ->with([
            'metrics' => function ($query) {
                $query
                    ->where('active', true)
                    ->orderBy('sort_order');
            },
        ])
        ->where('department', $department)
        ->where('active', true)
        ->whereDate(
            'effective_from',
            '<=',
            $asOf->toDateString()
        )
        ->where(function ($query) use ($asOf) {

            $query
                ->whereNull('effective_to')
                ->orWhereDate(
                    'effective_to',
                    '>=',
                    $asOf->toDateString()
                );

        })
        ->orderByDesc('effective_from')
        ->first();

    if (
        $template
        &&
        $template->metrics->isNotEmpty()
    ) {

        return $template
            ->metrics
            ->map(
                fn (KpiMetric $metric) =>
                    $this->matrixMetric(
                        $metric
                    )
            )
            ->values()
            ->all();
    }


    /*
     * Fallback:
     * use config/kpi.php default definition.
     *
     * This prevents an empty KPI Dashboard when
     * the database template has not yet been synced.
     */
    $templates =
        (array) config(
            'kpi.default_templates',
            []
        );

    $definition =
        $templates[$department]
        ?? collect($templates)
            ->first(
                fn ($item) =>
                    (
                        $item['department']
                        ?? null
                    )
                    === $department
            );

    if (
        !$definition
        ||
        empty(
            $definition['metrics']
        )
    ) {
        return [];
    }

    return collect(
        $definition['metrics']
    )
        ->sortBy('sort_order')
        ->map(function (array $data) {

            /*
             * Temporary model only.
             * Nothing is saved to database.
             */
            $metric =
                new KpiMetric();

            $metric->forceFill([
                'id' =>
                    'default-'
                    . (
                        $data['code']
                        ?? 'metric'
                    ),

                'code' =>
                    $data['code']
                    ?? '',

                'name' =>
                    $data['name']
                    ?? '',

                'description' =>
                    $data['description']
                    ?? null,

                'weightage' =>
                    $data['weightage']
                    ?? 0,

                'measurement_type' =>
                    $data[
                        'measurement_type'
                    ]
                    ?? 'automatic',

                'source_key' =>
                    $data['source_key']
                    ?? null,

                'target_value' =>
                    $data['target_value']
                    ?? 0,

                'direction' =>
                    $data['direction']
                    ?? 'higher_better',

                'score_rules' =>
                    $data['score_rules']
                    ?? [],

                'sort_order' =>
                    $data['sort_order']
                    ?? 0,

                'active' =>
                    true,
            ]);

            return $this->matrixMetric(
                $metric
            );
        })
        ->values()
        ->all();
}


/**
 * Build one permanent matrix row.
 *
 * The empty_metric payload is used when an employee
 * has no active KPI assignment/data.
 */
private function matrixMetric(
    KpiMetric $metric
): array {
    $resolved =
        $this->emptyResolvedMetric(
            $metric
        );

    $improvement =
        $this->improvements->build(
            $metric,
            1,
            $resolved
        );

    $fallback = [
        'id' => $metric->id,

        'code' =>
            (string) $metric->code,

        'name' =>
            (string) $metric->name,

        'description' =>
            $metric->description,

        /*
         * Minimum valid KPI rating.
         */
        'score' => 1,

        'weightage' =>
            (float) $metric->weightage,

        'actual_value' => 0,

        'target_value' =>
            (float) (
                $resolved[
                    'target_value'
                ]
                ?? 0
            ),

        'achievement_percent' =>
            0,

        'evidence' =>
            $resolved['evidence']
            ?? [],

        'sort_order' =>
            (int) $metric->sort_order,

        'score_rules' =>
            (array) $metric->score_rules,

        'rating_labels' =>
            $this->ratingLabels(
                (string) $metric->code,
                (array) $metric->score_rules
            ),

        'direction' =>
            $metric->direction,

        /*
         * Requirement:
         * No employee data = show 0.
         */
        'display_actual' =>
            '0',

        'next_score' =>
            $improvement[
                'next_score'
            ]
            ?? 2,

        'next_threshold' =>
            $improvement[
                'next_threshold'
            ]
            ?? null,

        'gap_value' =>
            $improvement[
                'gap_value'
            ]
            ?? 0,

        'improvement_line' =>
            $this->emptyImprovementLine(
                (string) $metric->code
            ),
    ];

    return [
        'id' => $metric->id,

        'code' =>
            (string) $metric->code,

        'name' =>
            (string) $metric->name,

        'description' =>
            $metric->description,

        'weightage' =>
            (float) $metric->weightage,

        'sort_order' =>
            (int) $metric->sort_order,

        'score_rules' =>
            (array) $metric->score_rules,

        'rating_labels' =>
            $this->ratingLabels(
                (string) $metric->code,
                (array) $metric->score_rules
            ),

        'direction' =>
            $metric->direction,

        /*
         * Used when this particular employee does
         * not have KPI data.
         */
        'empty_metric' =>
            $fallback,
    ];
}


/**
 * Safe zero-data representation for every Retail KPI.
 */
private function emptyResolvedMetric(
    KpiMetric $metric
): array {
    $code =
        (string) $metric->code;

    return match ($code) {

        'daily_outreach' => [
            'actual_value' => 0,

            'target_value' =>
                (float) (
                    $metric->target_value
                    ?: 50
                ),

            'achievement_percent' =>
                0,

            'evidence' => [
                'today_completed' => 0,
                'mtd_completed' => 0,
                'expected_to_date' => 0,
                'monthly_target' => 0,
            ],
        ],


        'lead_conversion' => [
            'actual_value' => 0,
            'target_value' => 0,
            'achievement_percent' => 0,

            'evidence' => [
                'total_leads' => 0,
                'booked_leads' => 0,
                'conversion_rate' => 0,
            ],
        ],


        'monthly_target' => [
            'actual_value' => 0,
            'target_value' => 0,
            'achievement_percent' => 0,

            'evidence' => [
                'target_amount' => 0,
                'achieved_amount' => 0,
                'remaining_amount' => 0,
            ],
        ],


        'response_time' => [
            'actual_value' => 0,

            'target_value' =>
                (float) (
                    $metric->target_value
                    ?: 15
                ),

            'achievement_percent' =>
                0,

            'evidence' => [
                'eligible_leads' => 0,
                'responded' => 0,
                'unresponded' => 0,
                'average_response_minutes' => 0,
                'median_response_minutes' => 0,
            ],
        ],


        'followup_sla' => [
            'actual_value' => 0,
            'target_value' => 0,
            'achievement_percent' => 0,

            'evidence' => [
                'eligible_pending_cases' => 0,
                'within_4_hours' => 0,
                'outside_4_hours_or_missing' => 0,
                'achievement_percent' => 0,
            ],
        ],


        'payment_collection' => [
            'actual_value' => 0,
            'target_value' => 0,
            'achievement_percent' => 0,

            'evidence' => [
                'eligible_payment_customers' => 0,
                'full_payment_count' => 0,
                'partial_payment_count' => 0,
                'partial_with_followup_count' => 0,
                'partial_without_followup_count' => 0,
                'unpaid_count' => 0,
                'approved_received_total' => 0,
                'pending_balance_total' => 0,
                'achievement_percent' => 0,
            ],
        ],


       'attendance' => [

    'actual_value' =>
        0,

    'target_value' =>
        (float) (
            $metric
                ->target_value
            ?: 95
        ),

    'achievement_percent' =>
        0,

    'evidence' => [

        'eligible_scheduled_days' =>
            0,

        'punctual_days' =>
            0,

        'late_days' =>
            0,

        'absent_days' =>
            0,

        'missing_uploaded_days' =>
            0,

        'latest_attendance_date' =>
            null,

        'attendance_coverage_through' =>
            null,

        'shift_start' =>
            null,

        'grace_minutes' =>
            null,

        'punctual_cutoff' =>
            null,

        'shift_policy_breakdown' =>
            [],

        'punctuality_percent' =>
            0,
    ],
],


        default => [
            'actual_value' => 0,

            'target_value' =>
                (float) (
                    $metric->target_value
                    ?: 0
                ),

            'achievement_percent' => 0,

            'evidence' => [],
        ],
    };
}


/**
 * Deterministic Laravel guidance for an employee
 * who currently has no KPI activity.
 */
private function emptyImprovementLine(
    string $code
): string {
    return match ($code) {

        'daily_outreach' =>
            'Complete verified Daily Outreach actions to improve this KPI score.',

        'lead_conversion' =>
            'Convert eligible incoming leads into successful bookings to improve this KPI score.',

        'monthly_target' =>
            'Generate approved sales against your assigned monthly target to improve this KPI score.',

        'response_time' =>
            'Respond to eligible customer enquiries faster; 15 minutes or less is the 5/5 target.',

        'followup_sla' =>
            'Complete eligible pending follow-ups within 4 hours to improve this KPI score.',

        'payment_collection' =>
            'Collect full payment or complete timely balance follow-up after partial payment to improve this KPI score.',

        'attendance' =>
            'Maintain punctual attendance on scheduled working days to improve this KPI score.',

        default =>
            'Improve the current KPI result to reach the next score.',
    };
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

    private function ratingLabels(string $code, array $rules): array
    {
        $configured = config("kpi.rating_labels.{$code}");

        if (is_array($configured)) {
            return $configured;
        }

        foreach ((array) config('kpi.default_templates', []) as $template) {
            foreach (($template['metrics'] ?? []) as $metric) {
                if (($metric['code'] ?? null) === $code) {
                    return $metric['score_labels'] ?? $this->numericLabels($rules);
                }
            }
        }

        return $this->numericLabels($rules);
    }

    private function numericLabels(array $rules): array
    {
        $labels = [];

        foreach ([5, 4, 3, 2, 1] as $score) {
            $labels[$score] = isset($rules[(string) $score])
                ? (string) $rules[(string) $score]
                : (isset($rules[$score]) ? (string) $rules[$score] : '-');
        }

        return $labels;
    }
}
