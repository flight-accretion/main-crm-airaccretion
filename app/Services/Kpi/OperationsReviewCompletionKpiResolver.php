<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Carbon\Carbon;

class OperationsReviewCompletionKpiResolver
    implements KpiMetricResolverInterface
{
    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth,
        ?Carbon $from = null
    ): array {

        $periodStart =
            ($from ?: $asOf->copy()->startOfMonth())
                ->copy()
                ->startOfDay();

        $periodEnd =
            $asOf
                ->copy()
                ->endOfDay();

        $cases =
            OperationCase::query()

                ->where(
                    'type',
                    OperationCase::TYPE_REVIEW
                )

                ->where(
                    'assigned_to',
                    $user->id
                )

                ->whereBetween(
                    'opened_at',
                    [
                        $periodStart,
                        $periodEnd
                    ]
                )

                ->get([
                    'id'
                ]);

        $caseIds =
            $cases->pluck('id');


        $done =
            $caseIds->isEmpty()
                ? 0
                : OperationCaseActivity::query()

                    ->whereIn(
                        'operation_case_id',
                        $caseIds
                    )

                    ->where(
                        'action',
                        'review_completed'
                    )

                    ->where(
                        'created_at',
                        '<=',
                        $periodEnd
                    )

                    ->distinct(
                        'operation_case_id'
                    )

                    ->count(
                        'operation_case_id'
                    );


        $cancelled =
            $caseIds->isEmpty()
                ? 0
                : OperationCaseActivity::query()

                    ->whereIn(
                        'operation_case_id',
                        $caseIds
                    )

                    ->where(
                        'action',
                        'review_cancelled'
                    )

                    ->where(
                        'created_at',
                        '<=',
                        $periodEnd
                    )

                    ->distinct(
                        'operation_case_id'
                    )

                    ->count(
                        'operation_case_id'
                    );


        $resolved =
            $done + $cancelled;

        $total =
            $cases->count();

        $pending =
            max(
                0,
                $total - $resolved
            );


        $achievement =
            $resolved > 0

                ? (
                    $done
                    /
                    $resolved
                ) * 100

                : 0;


        return [

            'actual_value' =>
                round(
                    $achievement,
                    4
                ),

            'target_value' =>
                (float)
                (
                    $metric->target_value
                    ?: 100
                ),

            'achievement_percent' =>
                round(
                    $achievement,
                    4
                ),

            'evidence' => [

                'eligible_review_cases' =>
                    $total,

                'review_resolved_cases' =>
                    $resolved,

                'review_completed_cases' =>
                    $done,

                'review_cancelled_cases' =>
                    $cancelled,

                'review_pending_cases' =>
                    $pending,
            ],
        ];
    }
}