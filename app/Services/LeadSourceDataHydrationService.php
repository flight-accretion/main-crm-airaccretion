<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadRide;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadSourceDataHydrationService
{
    public function hydrate(
        Lead $lead,
        array $data
    ): void {
        $this->createRideIfServiceDateAvailable(
            $lead,
            $data
        );
    }

    private function createRideIfServiceDateAvailable(
        Lead $lead,
        array $data
    ): void {
        if (!Schema::hasTable('lead_rides')) {
            return;
        }

        $parsedServiceDate = $this->parseServiceDate($data);
        $places = $this->places($data);

        $existingRide =
            $lead
                ->rideSegments()
                ->orderBy('created_at')
                ->first();

        if ($existingRide) {
            if (
                !$parsedServiceDate
                && !$places['from']
                && !$places['to']
            ) {
                return;
            }

            if ($parsedServiceDate) {
                $existingRide->from_date =
                    $parsedServiceDate;
                $existingRide->to_date =
                    $parsedServiceDate->copy();
            }

            if ($places['from']) {
                $existingRide->from_place =
                    $places['from'];
            }

            if ($places['to']) {
                $existingRide->to_place =
                    $places['to'];
            }

            $existingRide->save();

            return;
        }

        $serviceDate =
            $parsedServiceDate
            ?: Carbon::now('Asia/Kolkata');

        LeadRide::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => $serviceDate,
            'to_date' => $serviceDate->copy(),
            'from_place' => $places['from'] ?: 'NA',
            'to_place' => $places['to'] ?: 'NA',
            'service_address_id' => null,
            'is_tba' => false,
            'total_time' => null,
        ]);
    }

    private function parseServiceDate(array $data): ?Carbon
    {
        $date = $this->firstNonEmpty(
            $data,
            [
                'service_date',
                'date',
                'departure_date',
                'travel_date',
                'ride_date',
                'from_date',
            ]
        );

        if (!$date) {
            return null;
        }

        if ($date instanceof DateTimeInterface) {
            return Carbon::instance($date);
        }

        if ($this->isAmbiguousDate((string) $date)) {
            return null;
        }

        $time = $this->firstNonEmpty(
            $data,
            [
                'service_time',
                'time',
                'departure_time',
                'travel_time',
                'ride_time',
                'from_time',
            ]
        );

        $dateTimeText = trim(
            (string) $date
            . ' '
            . (is_scalar($time) ? (string) $time : '')
        );

        try {
            return Carbon::parse($dateTimeText);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function places(array $data): array
    {
        $from = $this->cleanPlace(
            $this->firstNonEmpty(
                $data,
                [
                    'from_place',
                    'origin',
                    'from',
                    'departure_city',
                    'pickup_city',
                ]
            )
        );

        $to = $this->cleanPlace(
            $this->firstNonEmpty(
                $data,
                [
                    'to_place',
                    'destination',
                    'to',
                    'arrival_city',
                    'drop_city',
                ]
            )
        );

        $route = $this->cleanPlace(
            $this->firstNonEmpty(
                $data,
                [
                    'route',
                    'travel_route',
                    'city_route',
                ]
            )
        );

        if ($route) {
            [$routeFrom, $routeTo] = $this->splitRoute($route);
            $from = $from ?: $routeFrom;
            $to = $to ?: $routeTo;
        }

        $city = $this->cleanPlace(
            $this->firstNonEmpty(
                $data,
                [
                    'city',
                    'service_city',
                    'location',
                ]
            )
        );

        if ($city && !$from && !$to) {
            [$routeFrom, $routeTo] = $this->splitRoute($city);

            if ($routeFrom && $routeTo) {
                $from = $routeFrom;
                $to = $routeTo;
            } else {
                $from = $city;
            }
        }

        if (!$from && !$to) {
            $from = $this->cityFromService(
                $this->firstNonEmpty(
                    $data,
                    [
                        'service',
                        'service_name',
                    ]
                )
            );
        }

        return [
            'from' => $from,
            'to' => $to,
        ];
    }

    private function splitRoute(?string $route): array
    {
        if (!$route) {
            return [null, null];
        }

        if (
            preg_match(
                '/^\s*(.+?)\s*(?:->|\bto\b)\s*(.+?)\s*$/i',
                $route,
                $matches
            )
        ) {
            return [
                $this->cleanPlace($matches[1]),
                $this->cleanPlace($matches[2]),
            ];
        }

        return [null, null];
    }

    private function cityFromService($service): ?string
    {
        $service = $this->cleanPlace($service);

        if (!$service) {
            return null;
        }

        if (
            preg_match(
                '/\bin\s+([a-z][a-z\s.\-]*)$/i',
                $service,
                $matches
            )
        ) {
            return $this->cleanPlace($matches[1]);
        }

        return null;
    }

    private function isAmbiguousDate(string $date): bool
    {
        $date = Str::lower(trim($date));

        if ($date === '') {
            return true;
        }

        if (
            preg_match(
                '/\b(today|tomorrow|tonight|next|weekend|day after)\b/',
                $date
            )
        ) {
            return true;
        }

        if (preg_match('/^\d{1,2}(st|nd|rd|th)?$/', $date)) {
            return true;
        }

        if (preg_match('/^\d{1,2}\s*[-\/]\s*\d{1,2}$/', $date)) {
            return true;
        }

        return false;
    }

    private function firstNonEmpty(array $data, array $keys)
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if ($value instanceof DateTimeInterface) {
                return $value;
            }

            if (
                is_scalar($value)
                && trim((string) $value) !== ''
            ) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function cleanPlace($value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\s+/', ' ', $value) ?: '';
        $value = trim($value, " \t\n\r\0\x0B,.-");

        return $value === '' ? null : $value;
    }
}
