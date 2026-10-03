<?php

namespace App\Services\Operations;

use App\Models\Lead;
use Carbon\Carbon;

class RideAlertDataResolver
{
    public function resolve(Lead $lead): array
    {
        $lead->loadMissing([
            'client',
            'rideSegments.serviceAddress',
        ]);

        $ride = $lead->rideSegments
            ->filter(fn ($segment) => !empty($segment->from_date))
            ->sortBy('from_date')
            ->first();

        $rideDate = $ride && $ride->from_date
            ? Carbon::parse($ride->from_date, 'Asia/Kolkata')->startOfDay()
            : null;

        return [
            'lead_id' => (string) $lead->id,
            'ride_id' => data_get($ride, 'id'),
            'ride_date' => $rideDate,
            'ride_time' => $this->rideTime($ride),
            'customer_name' => trim((string) optional($lead->client)->name) ?: 'Customer',
            'service_name' => $this->serviceName($lead),
            'guests' => $lead->number_of_passengers ?: 'Pending',
            'vendor' => 'Pending',
            'location' => $this->location($ride),
            'crm_link' => rtrim(
                (string) config('services.operations_ride_alert.crm_url'),
                '/'
            ) . '/admin/leads/' . $lead->id,
        ];
    }

    private function rideTime($ride): string
    {
        if (!$ride || !$ride->from_date) {
            return 'Pending Vendor Confirmation';
        }

        $from = Carbon::parse($ride->from_date, 'Asia/Kolkata');

        if ((bool) ($ride->is_tba ?? false) || $from->format('H:i') === '00:00') {
            return 'Pending Vendor Confirmation';
        }

        return $from->format('h:i A');
    }

    private function serviceName(Lead $lead): string
    {
        $names = $lead->service_names;

        if (is_array($names) && count($names) > 0) {
            return implode(', ', $names);
        }

        return 'Service';
    }

    private function location($ride): string
    {
        $parts = array_filter([
            trim((string) data_get($ride, 'from_place')),
            trim((string) data_get($ride, 'to_place')),
        ]);

        if ($parts) {
            return implode(' to ', $parts);
        }

        return trim((string) data_get($ride, 'serviceAddress.address')) ?: 'Pending';
    }
}
