<?php

namespace App\Services\Kpi;

use App\Models\AttendanceRecord;
use App\Models\KpiMetric;
use App\Models\User;
use App\Services\Attendance\AttendanceShiftResolver;
use App\Services\Attendance\AttendanceCalendarService;
use Carbon\Carbon;

class SalesAttendanceKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private KpiWorkingDayService $workingDays,
        private AttendanceShiftResolver $shiftResolver,
        private AttendanceCalendarService $calendar
    ) {}

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

        $observedEnd = $periodEnd->copy()->min(now()->endOfDay());


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
                ->whereDate('attendance_date', '>=', $periodStart->toDateString())
                ->whereDate('attendance_date', '<=', $observedEnd->toDateString())
                ->orderBy(
                    'attendance_date'
                )
                ->get();


        $latestRecordDate = $records->isEmpty() ? null :
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
        $coverageDate = $latestRecordDate
            ? $latestRecordDate->copy()->min($observedEnd->copy()->startOfDay())
            : null;


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


        // Attendance uses actual dated calendars; other KPI working-day rules are unchanged.
        $scheduledDates = collect();
        $monthlyWorkingDays = 0;
        $end = $observedEnd;
        for ($month = $periodStart->copy()->startOfMonth(); $month->lte($periodEnd); $month->addMonth()) {
            $summary = $this->calendar->summary($user, $month->year, $month->month);
            $monthlyWorkingDays += $summary['working_days'];
            $scheduledDates = $scheduledDates->merge($summary['dates']);
        }
        $scheduledDates = $scheduledDates->filter(fn (Carbon $date) => $date->gte($periodStart) && $date->lte($end))->values();


        $shiftPoliciesByDate =
            $this
                ->shiftResolver
                ->resolveForDates(
                    $user,
                    $scheduledDates->filter(fn ($date) => $recordByDate->has($date->toDateString())
                        && in_array(strtoupper(trim((string) $recordByDate->get($date->toDateString())->raw_status)), ['A', 'P', 'MIS'], true))
                );

        $shiftPolicyBreakdown = [];
        $lastShiftStart = null;
        $lastGraceMinutes = null;
        $lastPunctualCutoff = null;

        $eligible = 0;
        $punctual = 0;
        $late = 0;
        $absent = 0;
        $present = 0;
        $missPunch = 0;
        $ignoredStatusDays = 0;
        $punctualityUnknown = 0;

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
            if (!in_array($status, ['A', 'P', 'MIS'], true)) {

                $nonScheduledRows++;
                $ignoredStatusDays++;

                continue;
            }


            $policy =
                $shiftPoliciesByDate
                    ->get(
                        $dateKey
                    );

            $policyContext =
                $this->policyContextForRecord(
                    $record,
                    $policy
                );

            $shiftStart =
                $policyContext['start_time'];

            $graceMinutes =
                $policyContext['grace_minutes'];

            $policyName =
                $policyContext['name'];

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
            ) {

                $absent++;
                $shiftPolicyBreakdown[
                    $policyName
                ][
                    'absent_days'
                ]++;

                continue;
            }

            $present++;
            if ($status === 'MIS') $missPunch++;
            if (!$record->in_time) {
                $punctualityUnknown++;
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

        $attendance = $eligible > 0 ? round($present / $eligible * 100, 4) : 0.0;
        $scheduledKeys = $scheduledDates->map->toDateString()->flip();
        $offDayWorked = $records->filter(fn ($record) => !$scheduledKeys->has($record->attendance_date->toDateString())
            && in_array(strtoupper(trim((string) $record->raw_status)), ['P', 'MIS'], true))->count();


        return [
            /*
             * Actual value itself is
             * presence percentage. Punctuality remains separate evidence.
             */
            'actual_value' =>
                round(
                    $attendance,
                    4
                ),

            'target_value' => 100.0,

            /*
             * IMPORTANT:
             *
             * KPI rating table is based
             * directly on presence %:
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
                    $attendance,
                    4
                ),

            'evidence' => [

                'attendance_basis' => 'presence',
                'present_days' => $present,
                'miss_punch_days' => $missPunch,
                'ignored_status_days' => $ignoredStatusDays,
                'punctuality_unknown_days' => $punctualityUnknown,
                'off_day_worked' => $offDayWorked,
                'monthly_working_days' => $monthlyWorkingDays,
                'working_days_elapsed' => $scheduledDates->count(),
                'attendance_percent' => $attendance,
                'is_provisional' => $missingUploadedDays > 0 || $ignoredStatusDays > 0,

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
                        ?->toDateString(),

                'attendance_coverage_through' =>
                    $coverageDate
                        ?->toDateString(),

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

    private function policyContextForRecord(
        AttendanceRecord $record,
        $policy
    ): array {
        if (
            $record->resolved_shift_policy_name
            ||
            $record->resolved_shift_start_time
            ||
            $record->resolved_shift_grace_minutes !== null
        ) {
            return [
                'name' =>
                    (string) (
                        $record
                            ->resolved_shift_policy_name
                        ?: optional($policy)->name
                        ?: 'Default Office Time'
                    ),

                'start_time' =>
                    substr(
                        (string) (
                            $record
                                ->resolved_shift_start_time
                            ?: optional($policy)->start_time
                            ?: '10:30'
                        ),
                        0,
                        5
                    ),

                'grace_minutes' =>
                    (int) (
                        $record
                            ->resolved_shift_grace_minutes
                        ?? optional($policy)->grace_minutes
                        ?? 15
                    ),
            ];
        }

        return [
            'name' =>
                (string) (
                    optional($policy)->name
                    ?: 'Default Office Time'
                ),

            'start_time' =>
                substr(
                    (string) (
                        optional($policy)->start_time
                        ?: '10:30'
                    ),
                    0,
                    5
                ),

            'grace_minutes' =>
                (int) (
                    optional($policy)->grace_minutes
                    ?? 15
                ),
        ];
    }
}
