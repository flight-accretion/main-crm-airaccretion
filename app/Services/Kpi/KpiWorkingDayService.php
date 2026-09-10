<?php

namespace App\Services\Kpi;

use App\Models\KpiUserNonWorkingDay;
use App\Models\KpiWorkingDay;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class KpiWorkingDayService
{
    public function workingDates(
        User $user,
        int $year,
        int $month,
        int $configuredTotal = 22
    ): Collection {
        $explicit = KpiWorkingDay::query()
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->where('is_working_day', true)
            ->orderBy('work_date')
            ->pluck('work_date')
            ->map(fn ($date) => Carbon::parse($date)->startOfDay());

        if ($explicit->isEmpty()) {
            $period = CarbonPeriod::create(
                Carbon::create($year, $month, 1)->startOfDay(),
                Carbon::create($year, $month, 1)->endOfMonth()->startOfDay()
            );

            $explicit = collect($period)
                ->filter(fn (Carbon $date) => !$date->isSunday())
                ->take($configuredTotal)
                ->values();
        }

        $excluded = KpiUserNonWorkingDay::query()
            ->where('user_id', $user->id)
            ->whereYear('work_date', $year)
            ->whereMonth('work_date', $month)
            ->pluck('work_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        return $explicit
            ->reject(fn (Carbon $date) => $excluded->has($date->toDateString()))
            ->values();
    }

    public function stats(
        User $user,
        Carbon $asOf,
        int $configuredTotal = 22
    ): array {
        $dates = $this->workingDates(
            $user,
            $asOf->year,
            $asOf->month,
            $configuredTotal
        );

        $elapsed = $dates
            ->filter(fn (Carbon $date) => $date->lte($asOf->copy()->endOfDay()))
            ->count();

        return [
            'working_days_total' => $dates->count(),
            'working_days_elapsed' => $elapsed,
            'working_days_remaining' => max(0, $dates->count() - $elapsed),
            'dates' => $dates,
        ];
    }
}
