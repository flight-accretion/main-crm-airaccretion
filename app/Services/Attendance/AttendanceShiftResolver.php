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
        $date = $date->copy()->startOfDay();

        $assignments =
            $this->assignmentsForUser($user);

        $assignment =
            $assignments
                ->filter(
                    fn (
                        AttendanceUserShiftAssignment $assignment
                    ) =>
                        $this->assignmentMatchesDate(
                            $assignment,
                            $date
                        )
                )
                ->sortByDesc(
                    fn (
                        AttendanceUserShiftAssignment $assignment
                    ) =>
                        optional(
                            $this->assignmentStart(
                                $assignment
                            )
                        )->timestamp
                        ?? 0
                )
                ->first();

        if (
            $assignment
            &&
            $assignment->shiftPolicy
        ) {
            return $assignment->shiftPolicy;
        }

        $firstAssignment =
            $assignments->first();

        if (
            $firstAssignment
            &&
            $firstAssignment->assigned_at
            &&
            $firstAssignment->shiftPolicy
            &&
            $this->assignmentStart($firstAssignment)
            &&
            $date->lt(
                $this
                    ->assignmentStart($firstAssignment)
                    ->copy()
                    ->startOfDay()
            )
        ) {
            return $firstAssignment->shiftPolicy;
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

        return $normalizedDates
            ->mapWithKeys(
                function (
                    Carbon $date
                ) use ($user) {
                    $dateKey =
                        $date->toDateString();

                    return [
                        $dateKey =>
                            $this->resolveForDate(
                                $user,
                                $date
                            ),
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

    private function assignmentsForUser(User $user): Collection
    {
        return AttendanceUserShiftAssignment::query()
            ->with('shiftPolicy')
            ->where('user_id', $user->id)
            ->get()
            ->filter(
                fn (
                    AttendanceUserShiftAssignment $assignment
                ) =>
                    (bool) $assignment->shiftPolicy
            )
            ->sortBy(
                fn (
                    AttendanceUserShiftAssignment $assignment
                ) =>
                    optional(
                        $this->assignmentStart(
                            $assignment
                        )
                    )->timestamp
                    ?? 0
            )
            ->values();
    }

    private function assignmentMatchesDate(
        AttendanceUserShiftAssignment $assignment,
        Carbon $date
    ): bool {
        $start =
            $this->assignmentStart($assignment);

        if (!$start) {
            return false;
        }

        $end =
            $this->assignmentEnd($assignment);

        return
            $start->lte($date->copy()->endOfDay())
            &&
            (
                !$end
                ||
                $end->gte($date->copy()->startOfDay())
            );
    }

    private function assignmentStart(
        AttendanceUserShiftAssignment $assignment
    ): ?Carbon {
        if ($assignment->assigned_at) {
            return $assignment->assigned_at->copy();
        }

        if ($assignment->effective_from) {
            return $assignment
                ->effective_from
                ->copy()
                ->startOfDay();
        }

        return null;
    }

    private function assignmentEnd(
        AttendanceUserShiftAssignment $assignment
    ): ?Carbon {
        if ($assignment->unassigned_at) {
            return $assignment->unassigned_at->copy();
        }

        if ($assignment->effective_to) {
            return $assignment
                ->effective_to
                ->copy()
                ->endOfDay();
        }

        return null;
    }
}
