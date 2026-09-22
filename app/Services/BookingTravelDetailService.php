<?php

namespace App\Services;

use Carbon\Carbon;

class BookingTravelDetailService
{
    public function rideTime($ride): string
    {
        if (!$ride) {
            return '';
        }

        if ((bool) $ride->is_tba) {
            return 'TBA';
        }

        if (empty($ride->from_date)) {
            return '';
        }

        try {
            $date = Carbon::parse($ride->from_date);

            if ($date->format('H:i:s') === '00:00:00') {
                return '';
            }

            return $date->format('h:i A');
        } catch (\Throwable $exception) {
            return '';
        }
    }

    public function formatTotalTime($totalTime): string
    {
        if (
            $totalTime === null
            || $totalTime === ''
            || !is_numeric($totalTime)
        ) {
            return '';
        }

        $totalMinutes = (int) round(((float) $totalTime) * 60);

        if ($totalMinutes <= 0) {
            return '';
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours . ' ' . ($hours === 1 ? 'Hour' : 'Hours');
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' Min';
        }

        return implode(' ', $parts);
    }

    public function duration($serviceDuration, $ride): string
    {
        $serviceDuration = trim((string) $serviceDuration);

        if ($serviceDuration !== '') {
            return $serviceDuration;
        }

        if (!$ride) {
            return '';
        }

        return $this->formatTotalTime($ride->total_time);
    }
}
