<?php

namespace App\Services\Attendance;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AttendanceCalendarService
{
    private array $schedules = [];
    private array $months = [];
    private array $tables = [];
    private array $legacyHolidays = [];

    private function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    public function schedules(User $user): Collection
    {
        return $this->schedules[$user->id] ??= $this->hasTable('attendance_week_off_schedules')
            ? DB::table('attendance_week_off_schedules')->where('user_id', $user->id)
                ->orderByDesc('effective_from')->get()->map(function ($row) {
                    $row->weekdays = json_decode($row->weekdays, true) ?: [];
                    return $row;
                })
            : collect();
    }

    public function month(int $year, int $month): ?object
    {
        $key = "$year-$month";
        if (!array_key_exists($key, $this->months)) {
            $row = $this->hasTable('attendance_calendar_months')
                ? DB::table('attendance_calendar_months')->where(compact('year', 'month'))->first() : null;
            if ($row) $row->dates = json_decode($row->dates, true) ?: [];
            $this->months[$key] = $row;
        }
        return $this->months[$key];
    }

    public function assertApprovedSwapsCompatible(User $user): void
    {
        if (!$this->hasTable('attendance_week_off_swaps')) return;
        foreach (DB::table('attendance_week_off_swaps')->where('user_id', $user->id)->get() as $swap) {
            $worked = Carbon::parse($swap->worked_date); $off = Carbon::parse($swap->off_date);
            $workedDates = $this->summary($user, $worked->year, $worked->month, false)['dates']->map->toDateString();
            $offDates = $this->summary($user, $off->year, $off->month, false)['dates']->map->toDateString();
            if ($workedDates->contains($swap->worked_date) || !$offDates->contains($swap->off_date)) {
                throw ValidationException::withMessages(['work_date' => 'This calendar change conflicts with an HR-approved swap. Undo that swap before changing these dates.']);
            }
        }
    }

    public function summary(User $user, int $year, int $month, bool $withSwaps = true): array
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();
        $schedules = $this->schedules($user);
        $monthPolicy = $this->month($year, $month);
        $holidays = collect($monthPolicy->dates ?? []);
        // Retain existing explicitly approved calendar exclusions, without the old 22-day cap.
        if ($this->hasTable('kpi_working_days')) {
            $key = "$year-$month";
            $legacy = $this->legacyHolidays[$key] ??= DB::table('kpi_working_days')
                ->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString())
                ->where('is_working_day', false)->pluck('work_date')->map(fn ($date) => Carbon::parse($date)->toDateString());
            $holidays = $holidays->merge($legacy);
        }
        $personal = $this->hasTable('kpi_user_non_working_days')
            ? DB::table('kpi_user_non_working_days')->where('user_id', $user->id)
                ->whereDate('work_date', '>=', $start->toDateString())->whereDate('work_date', '<=', $end->toDateString())
                ->pluck('work_date')->map(fn ($date) => Carbon::parse($date)->toDateString()) : collect();
        $holidays = $holidays->unique()->values();
        $weekly = collect();
        $all = collect(CarbonPeriod::create($start, $end));
        foreach ($all as $date) {
            $schedule = $schedules->first(fn ($row) => $row->effective_from <= $date->toDateString());
            $days = $schedule ? $schedule->weekdays : [0];
            if (in_array($date->dayOfWeek, $days, true)) $weekly->push($date->toDateString());
        }
        $excluded = $weekly->merge($holidays)->merge($personal)->unique()->flip();
        $working = $all->reject(fn ($date) => $excluded->has($date->toDateString()))->keyBy->toDateString();
        $swaps = collect();
        if ($withSwaps && $this->hasTable('attendance_week_off_swaps')) {
            $swaps = DB::table('attendance_week_off_swaps')->where('user_id', $user->id)
                ->where(function ($query) use ($start, $end) {
                    $query->whereBetween('worked_date', [$start->toDateString(), $end->toDateString()])
                        ->orWhereBetween('off_date', [$start->toDateString(), $end->toDateString()]);
                })->get();
            foreach ($swaps as $swap) {
                if ($swap->worked_date >= $start->toDateString() && $swap->worked_date <= $end->toDateString()) {
                    $working->put($swap->worked_date, Carbon::parse($swap->worked_date)->startOfDay());
                }
                $working->forget($swap->off_date);
            }
        }
        return [
            'month_days' => $all->count(), 'working_days' => $working->count(),
            'week_off_days' => $weekly->count(), 'holiday_days' => $holidays->count(),
            'overlap_days' => $weekly->intersect($holidays)->count(),
            'dates' => $working->sortKeys()->values(), 'swaps' => $swaps,
        ];
    }
}
