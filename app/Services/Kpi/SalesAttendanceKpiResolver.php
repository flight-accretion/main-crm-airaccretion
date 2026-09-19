<?php

namespace App\Services\Kpi;

use App\Models\AttendanceRecord;
use App\Models\KpiMetric;
use App\Models\User;
use App\Services\Attendance\AttendanceShiftResolver;
use Carbon\Carbon;

class SalesAttendanceKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private KpiWorkingDayService $workingDays,
        private AttendanceShiftResolver $shiftResolver
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $monthStart =
            $asOf
                ->copy()
                ->startOfMonth()
                ->startOfDay();

        $monthEnd =
            $asOf
                ->copy()
                ->endOfDay();


        /*
         * Always read consolidated
         * employee/date records.
         *
         * It does not matter whether
         * they came from daily, weekly
         * or monthly uploads.
         */
        $records =
            AttendanceRecord::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereBetween(
                    'attendance_date',
                    [
                        $monthStart
                            ->toDateString(),

                        $monthEnd
                            ->toDateString(),
                    ]
                )
                ->orderBy(
                    'attendance_date'
                )
                ->get();


        if (
            $records->isEmpty()
        ) {
            return $this
                ->emptyResult(
                    $metric
                );
        }


        $latestRecordDate =
            Carbon::parse(
                $records->max(
                    'attendance_date'
                )
            )
            ->startOfDay();


        /*
         * Never judge future dates.
         *
         * Example:
         * Attendance uploaded through 17 Sep.
         * KPI dashboard opened on 18 Sep.
         *
         * Coverage ends on 17 Sep.
         */
        $coverageDate =
            $latestRecordDate
                ->lt(
                    $asOf
                        ->copy()
                        ->startOfDay()
                )

                ? $latestRecordDate

                : $asOf
                    ->copy()
                    ->startOfDay();


        $recordByDate =
            $records
                ->keyBy(
                    fn (
                        AttendanceRecord $record
                    ) =>
                        $record
                            ->attendance_date
                            ->toDateString()
                );


        /*
         * Uses your existing KPI working day
         * and employee non-working-day logic.
         */
        $scheduledDates =
            $this
                ->workingDays
                ->workingDates(
                    $user,
                    $asOf->year,
                    $asOf->month,
                    $workingDaysPerMonth
                )
                ->filter(
                    fn (
                        Carbon $date
                    ) =>
                        $date->lte(
                            $coverageDate
                        )
                )
                ->values();


        $nonScheduledStatuses =
            array_map(
                fn ($value) =>
                    strtoupper(
                        trim(
                            (string)
                            $value
                        )
                    ),

                (array)
                config(
                    'kpi.attendance.non_scheduled_statuses',
                    [
                        'WO',
                        'HLD',
                    ]
                )
            );

        $shiftPoliciesByDate =
            $this
                ->shiftResolver
                ->resolveForDates(
                    $user,
                    $scheduledDates
                );

        $shiftPolicyBreakdown = [];
        $lastShiftStart = null;
        $lastGraceMinutes = null;
        $lastPunctualCutoff = null;

        $eligible = 0;
        $punctual = 0;
        $late = 0;
        $absent = 0;

        $missingUploadedDays = 0;
        $nonScheduledRows = 0;


        foreach (
            $scheduledDates
            as $date
        ) {

            $dateKey =
                $date
                    ->toDateString();


            /** @var AttendanceRecord|null $record */
            $record =
                $recordByDate
                    ->get(
                        $dateKey
                    );


            /*
             * Missing row is NOT automatically
             * considered absence.
             *
             * It means attendance for that
             * scheduled date has not yet been
             * supplied for that employee.
             */
            if (!$record) {

                $missingUploadedDays++;

                continue;
            }


            $status =
                strtoupper(
                    trim(
                        (string)
                        $record
                            ->raw_status
                    )
                );


            /*
             * Weekly Off / Holiday
             * does not enter denominator.
             */
            if (
                in_array(
                    $status,
                    $nonScheduledStatuses,
                    true
                )
            ) {

                $nonScheduledRows++;

                continue;
            }


            $policy =
                $shiftPoliciesByDate
                    ->get(
                        $dateKey
                    );

            $shiftStart =
                substr(
                    (string)
                    optional(
                        $policy
                    )->start_time,
                    0,
                    5
                )
                ?: '10:30';

            $graceMinutes =
                (int) (
                    optional(
                        $policy
                    )->grace_minutes
                    ?? 15
                );

            $policyName =
                (string) (
                    optional(
                        $policy
                    )->name
                    ?: 'Default Office Time'
                );

            if (
                !isset(
                    $shiftPolicyBreakdown[
                        $policyName
                    ]
                )
            ) {
                $shiftPolicyBreakdown[
                    $policyName
                ] = [
                    'shift_start' =>
                        $shiftStart,

                    'grace_minutes' =>
                        $graceMinutes,

                    'eligible_days' =>
                        0,

                    'punctual_days' =>
                        0,

                    'late_days' =>
                        0,

                    'absent_days' =>
                        0,
                ];
            }

            $eligible++;
            $shiftPolicyBreakdown[
                $policyName
            ][
                'eligible_days'
            ]++;


            /*
             * Explicit absence or no
             * valid clock-in.
             */
            if (
                $status === 'A'
                ||
                !$record->in_time
            ) {

                $absent++;
                $shiftPolicyBreakdown[
                    $policyName
                ][
                    'absent_days'
                ]++;

                continue;
            }


            /*
             * Example:
             *
             * Shift 10:30
             * Grace 15
             * Cutoff 10:45
             */
            $cutoff =
                Carbon::createFromFormat(
                    'Y-m-d H:i',
                    $dateKey
                    . ' '
                    . $shiftStart
                )
                ->addMinutes(
                    $graceMinutes
                );

            $lastShiftStart =
                $shiftStart;

            $lastGraceMinutes =
                $graceMinutes;

            $lastPunctualCutoff =
                Carbon::createFromFormat(
                    'H:i',
                    $shiftStart
                )
                    ->addMinutes(
                        $graceMinutes
                    )
                    ->format(
                        'H:i'
                    );


            $clockIn =
                Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    $dateKey
                    . ' '
                    . $record
                        ->in_time
                );


            if (
                $clockIn->lte(
                    $cutoff
                )
            ) {

                $punctual++;
                $shiftPolicyBreakdown[
                    $policyName
                ][
                    'punctual_days'
                ]++;

            } else {

                $late++;
                $shiftPolicyBreakdown[
                    $policyName
                ][
                    'late_days'
                ]++;
            }
        }


        $punctuality =
            $eligible > 0

                ? (
                    $punctual
                    /
                    $eligible
                )
                * 100

                : 0.0;


        return [
            /*
             * Actual value itself is
             * punctuality percentage.
             */
            'actual_value' =>
                round(
                    $punctuality,
                    4
                ),

            'target_value' =>
                (float) (
                    $metric
                        ->target_value
                    ?: 95
                ),

            /*
             * IMPORTANT:
             *
             * KPI rating table is based
             * directly on punctuality %:
             *
             * 100 -> 5
             * 90  -> 4
             * 80  -> 3
             * etc.
             *
             * So do NOT divide by 95.
             */
            'achievement_percent' =>
                round(
                    $punctuality,
                    4
                ),

            'evidence' => [

                'eligible_scheduled_days' =>
                    $eligible,

                'punctual_days' =>
                    $punctual,

                'late_days' =>
                    $late,

                'absent_days' =>
                    $absent,

                'missing_uploaded_days' =>
                    $missingUploadedDays,

                'non_scheduled_rows' =>
                    $nonScheduledRows,

                'latest_attendance_date' =>
                    $latestRecordDate
                        ->toDateString(),

                'attendance_coverage_through' =>
                    $coverageDate
                        ->toDateString(),

                'shift_start' =>
                    $lastShiftStart,

                'grace_minutes' =>
                    $lastGraceMinutes,

                'punctual_cutoff' =>
                    $lastPunctualCutoff,

                'shift_policy_breakdown' =>
                    $shiftPolicyBreakdown,

                'punctuality_percent' =>
                    round(
                        $punctuality,
                        2
                    ),
            ],
        ];
    }


    private function emptyResult(
        KpiMetric $metric
    ): array {
        return [
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

                'non_scheduled_rows' =>
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

                'note' =>
                    'No eligible attendance records have been uploaded for this month yet.',
            ],
        ];
    }
}
