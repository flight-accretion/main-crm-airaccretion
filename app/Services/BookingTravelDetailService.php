<?php

namespace App\Services;

use Carbon\Carbon;

class BookingTravelDetailService
{
    /**
     * Ride time exactly as shown on the voucher: TBA flag first, then the
     * stored start time. Blank when no real time is stored.
     */
    public function rideTime($ride): string
    {
        if (!$ride) {
            return '';
        }

        if ((bool) $ride->is_tba) {
            return 'TBA';
        }

        return $this->storedStartTime($ride);
    }

    /**
     * Start time stored in Voucher Generation ("Ride Time From").
     *
     * Ignores is_tba on purpose: the Booking Email popup lets the sender
     * untick TBA for one email, and the voucher keeps the time when TBA is
     * ticked. Midnight is the "no time entered" value and returns ''.
     */
    public function storedStartTime($ride): string
    {
        $from = $this->parse($ride?->from_date ?? null);

        if (!$from || $from->format('H:i:s') === '00:00:00') {
            return '';
        }

        return $from->format('h:i A');
    }

    /**
     * The lead form's date picker fills in 12:00 when only a date is chosen,
     * so a ride that starts AND ends at exactly 12:00 has no real time yet.
     */
    public function hasDefaultTime($ride): bool
    {
        $from = $this->parse($ride?->from_date ?? null);

        if (!$from || $from->format('H:i:s') !== '12:00:00') {
            return false;
        }

        $to = $this->parse($ride?->to_date ?? null);

        return !$to || $to->format('H:i:s') === '12:00:00';
    }

    /**
     * What the Booking Email popup should show for the ride time.
     *
     * default_tba is the pre-ticked state of the TBA checkbox.
     *
     * @return array{voucher_time:string,default_tba:bool,note:string}
     */
    public function timeState($ride): array
    {
        $stored = $this->storedStartTime($ride);

        if ($ride && (bool) $ride->is_tba) {
            return [
                'voucher_time' => $stored,
                'default_tba' => true,
                'note' => 'Marked To Be Announced in the voucher.',
            ];
        }

        if ($stored === '') {
            return [
                'voucher_time' => '',
                'default_tba' => true,
                'note' => 'No ride time is set in the voucher.',
            ];
        }

        if ($this->hasDefaultTime($ride)) {
            return [
                'voucher_time' => $stored,
                'default_tba' => true,
                'note' => 'This looks like the default 12:00 time. Untick TBA only if the ride really starts then.',
            ];
        }

        return [
            'voucher_time' => $stored,
            'default_tba' => false,
            'note' => '',
        ];
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

        return $this->formatMinutes(
            (int) round(((float) $totalTime) * 60)
        );
    }

    public function formatMinutes(int $totalMinutes): string
    {
        if ($totalMinutes <= 0) {
            return '';
        }

        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;
        $parts = [];

        if ($hours > 0) {
            $parts[] =
                $hours . ' ' . ($hours === 1 ? 'Hour' : 'Hours');
        }

        if ($minutes > 0) {
            $parts[] = $minutes . ' Min';
        }

        return implode(' ', $parts);
    }

    /**
     * Service duration, then the voucher's Total Time. Blank when neither exists.
     */
    public function duration($serviceDuration, $ride): string
    {
        $serviceDuration = trim((string) $serviceDuration);

        if ($serviceDuration !== '') {
            return $serviceDuration;
        }

        if (!$ride) {
            return '';
        }

        return $this->formatTotalTime(
            $ride->total_time
        );
    }

    /**
     * Duration worked out from the ride dates.
     *
     * - Same day with a real start and end time: hours / minutes.
     * - Several days: number of days, counting both the first and last day.
     * - Otherwise '' (TBA rides, missing or default 12:00 times).
     */
    public function durationFromRideDates($ride): string
    {
        if (!$ride || (bool) $ride->is_tba) {
            return '';
        }

        $from = $this->parse($ride->from_date ?? null);
        $to = $this->parse($ride->to_date ?? null);

        if (!$from || !$to || $to->lt($from)) {
            return '';
        }

        if ($from->toDateString() !== $to->toDateString()) {
            $days = $from->copy()->startOfDay()
                ->diffInDays($to->copy()->startOfDay()) + 1;

            return $days . ' ' . ($days === 1 ? 'Day' : 'Days');
        }

        if (
            $this->storedStartTime($ride) === ''
            || $this->hasDefaultTime($ride)
            || $to->format('H:i:s') === '00:00:00'
        ) {
            return '';
        }

        return $this->formatMinutes($from->diffInMinutes($to));
    }

    /**
     * Full duration order used by the Booking Email popup:
     * service name -> voucher Total Time -> ride dates -> nothing.
     *
     * needs_confirm is true for a day count taken from a multi-day date range:
     * that range can just be the customer's travel window (for example 61
     * days), so the sender must confirm it before it is used.
     *
     * @return array{value:string,source:string,needs_confirm:bool}
     */
    public function resolveDuration($serviceDuration, $ride): array
    {
        $fromService = trim((string) $serviceDuration);

        if ($fromService !== '') {
            return ['value' => $fromService, 'source' => 'service name', 'needs_confirm' => false];
        }

        $fromTotalTime = $ride
            ? $this->formatTotalTime($ride->total_time)
            : '';

        if ($fromTotalTime !== '') {
            return ['value' => $fromTotalTime, 'source' => 'voucher total time', 'needs_confirm' => false];
        }

        $fromDates = $this->durationFromRideDates($ride);

        if ($fromDates !== '') {
            $from = $this->parse($ride->from_date ?? null);
            $to = $this->parse($ride->to_date ?? null);

            return [
                'value' => $fromDates,
                'source' => 'ride dates',
                'needs_confirm' => $from && $to
                    && $from->toDateString() !== $to->toDateString(),
            ];
        }

        return ['value' => '', 'source' => 'none', 'needs_confirm' => false];
    }

    private function parse($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
