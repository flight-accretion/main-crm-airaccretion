<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\User;
use Carbon\Carbon;

class OperationsImageCollectionKpiResolver
    implements KpiMetricResolverInterface
{
    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth,
        ?Carbon $from = null
    ): array {

        /*
        |--------------------------------------------------------------------------
        | KPI PERIOD
        |--------------------------------------------------------------------------
        |
        | Default:
        | start of selected month -> selected/as-of date
        |
        | If custom From date is supplied:
        | From -> As Of
        |
        */
        $periodStart =
            (
                $from
                    ?: $asOf
                        ->copy()
                        ->startOfMonth()
            )
                ->copy()
                ->startOfDay();


        $periodEnd =
            $asOf
                ->copy()
                ->endOfDay();


        /*
        |--------------------------------------------------------------------------
        | REVIEW CASES ASSIGNED TO THIS OPERATIONS USER
        |--------------------------------------------------------------------------
        |
        | Image Collection belongs to the Review workflow.
        |
        | Attribution is based on OperationCase.assigned_to.
        |
        */
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
                        $periodEnd,
                    ]
                )

                ->get([
                    'id',
                ]);


        $totalCases =
            $cases->count();


        $caseIds =
            $cases->pluck(
                'id'
            );


        /*
        |--------------------------------------------------------------------------
        | IMAGE COLLECTION DONE
        |--------------------------------------------------------------------------
        |
        | KPI SUCCESS
        |
        */
        $completed =
            $caseIds->isEmpty()

                ? 0

                : OperationCaseActivity::query()

                    ->whereIn(
                        'operation_case_id',
                        $caseIds
                    )

                    ->where(
                        'action',
                        'image_collection_completed'
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


        /*
        |--------------------------------------------------------------------------
        | IMAGE COLLECTION CANCELLED
        |--------------------------------------------------------------------------
        |
        | KPI FAILURE
        |
        | This means:
        |
        | Customer did not provide image/video.
        |
        | It does NOT mean booking/Lead/OperationCase itself was cancelled.
        |
        */
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
                        'image_collection_cancelled'
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


        /*
        |--------------------------------------------------------------------------
        | RESOLVED IMAGE TASKS
        |--------------------------------------------------------------------------
        |
        | Done + Cancelled
        |
        */
        $resolved =
            $completed
            +
            $cancelled;


        /*
        |--------------------------------------------------------------------------
        | STILL PENDING
        |--------------------------------------------------------------------------
        |
        | Pending is displayed but NOT counted as cancelled until Operations
        | actually selects Cancel.
        |
        */
        $pending =
            max(
                0,
                $totalCases
                -
                $resolved
            );


        /*
        |--------------------------------------------------------------------------
        | KPI ACHIEVEMENT
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | Done      = 6
        | Cancelled = 4
        |
        | 6 / 10 = 60%
        |
        */
        $achievement =
            $resolved > 0

                ? (
                    $completed
                    /
                    $resolved
                )
                *
                100

                : 0.0;


        /*
        |--------------------------------------------------------------------------
        | RESULT
        |--------------------------------------------------------------------------
        */
        return [

            'actual_value' =>
                round(
                    $achievement,
                    4
                ),


            /*
             * Current Operations KPI target is 60%.
             *
             * Keep metric target configurable through kpi_metrics.
             */
            'target_value' =>
                (float)
                (
                    $metric->target_value
                    ?: 60
                ),


            'achievement_percent' =>
                round(
                    $achievement,
                    4
                ),


            'evidence' => [

                /*
                 * All Review cases opened/assigned during period.
                 */
                'eligible_review_cases' =>
                    $totalCases,


                /*
                 * Done + Cancel
                 */
                'image_collection_resolved_cases' =>
                    $resolved,


                /*
                 * KPI success count.
                 */
                'image_collection_completed_cases' =>
                    $completed,


                /*
                 * KPI failure count.
                 */
                'image_collection_cancelled_cases' =>
                    $cancelled,


                /*
                 * Not yet resolved.
                 */
                'image_collection_pending_cases' =>
                    $pending,
            ],
        ];
    }
}