<?php

namespace App\Services\Attendance;

use App\Models\AttendanceShiftPolicy;
use App\Models\AttendanceUserShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceShiftResolver
{
    public function resolveForDate(
        User $user,
        Carbon $date
    ): AttendanceShiftPolicy {
        $date =
            $date
                ->copy()
                ->startOfDay();

        $assignment =
            AttendanceUserShiftAssignment::query()
                ->with('shiftPolicy')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereDate(
                    'effective_from',
                    '<=',
                    $date->toDateString()
                )
                ->where(
                    function ($query) use ($date) {
                        $query
                            ->whereNull('effective_to')
                            ->orWhereDate(
                                'effective_to',
                                '>=',
                                $date->toDateString()
                            );
                    }
                )
                ->latest('effective_from')
                ->first();

        if (
            $assignment
            &&
            $assignment->shiftPolicy
        ) {
            return $assignment->shiftPolicy;
        }

        return $this->defaultPolicy();
    }

    public function resolveForDates(
        User $user,
        Collection $dates
    ): Collection {
        $normalizedDates =
            $dates
                ->map(
                    fn ($date) =>
                        $date instanceof Carbon
                            ? $date
                                ->copy()
                                ->startOfDay()
                            : Carbon::parse(
                                $date
                            )
                                ->startOfDay()
                )
                ->sortBy(
                    fn (Carbon $date) =>
                        $date->toDateString()
                )
                ->values();

        if (
            $normalizedDates
                ->isEmpty()
        ) {
            return collect();
        }

        $from =
            $normalizedDates
                ->first()
                ->toDateString();

        $to =
            $normalizedDates
                ->last()
                ->toDateString();

        $assignments =
            AttendanceUserShiftAssignment::query()
                ->with('shiftPolicy')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereDate(
                    'effective_from',
                    '<=',
                    $to
                )
                ->where(
                    function ($query) use ($from) {
                        $query
                            ->whereNull('effective_to')
                            ->orWhereDate(
                                'effective_to',
                                '>=',
                                $from
                            );
                    }
                )
                ->orderBy(
                    'effective_from'
                )
                ->get();

        $defaultPolicy =
            $this->defaultPolicy();

        return $normalizedDates
            ->mapWithKeys(
                function (
                    Carbon $date
                ) use (
                    $assignments,
                    $defaultPolicy
                ) {
                    $dateKey =
                        $date->toDateString();

                    $assignment =
                        $assignments
                            ->filter(
                                function (
                                    AttendanceUserShiftAssignment $assignment
                                ) use (
                                    $date
                                ) {
                                    $starts =
                                        $assignment
                                            ->effective_from
                                            ->lte(
                                                $date
                                            );

                                    $ends =
                                        !$assignment
                                            ->effective_to
                                        ||
                                        $assignment
                                            ->effective_to
                                            ->gte(
                                                $date
                                            );

                                    return
                                        $starts
                                        &&
                                        $ends
                                        &&
                                        $assignment
                                            ->shiftPolicy;
                                }
                            )
                            ->sortByDesc(
                                fn (
                                    AttendanceUserShiftAssignment $assignment
                                ) =>
                                    $assignment
                                        ->effective_from
                                        ->toDateString()
                            )
                            ->first();

                    return [
                        $dateKey =>
                            $assignment
                            &&
                            $assignment
                                ->shiftPolicy
                                ? $assignment
                                    ->shiftPolicy
                                : $defaultPolicy,
                    ];
                }
            );
    }

    private function defaultPolicy(): AttendanceShiftPolicy
    {
        $policy =
            AttendanceShiftPolicy::query()
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'is_default',
                    true
                )
                ->first();

        if ($policy) {
            return $policy;
        }

        return new AttendanceShiftPolicy([
            'name' =>
                'Legacy Config Office Time',

            'start_time' =>
                config(
                    'kpi.attendance.shift_start',
                    '10:30'
                ),

            'end_time' =>
                null,

            'grace_minutes' =>
                (int)
                config(
                    'kpi.attendance.grace_minutes',
                    15
                ),

            'is_default' =>
                true,

            'is_active' =>
                true,
        ]);
    }
}
