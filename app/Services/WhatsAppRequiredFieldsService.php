<?php

namespace App\Services;

class WhatsAppRequiredFieldsService
{
    public function required(?string $family): array
    {
        return match ($family) {
            'private_aircraft_charter',
            'private_helicopter_charter' => [
                'origin',
                'destination',
                'date',
                'passengers',
            ],
            'air_ambulance' => [
                'origin',
                'destination',
                'patient_location',
                'date_or_urgency',
            ],
            'helicopter_joyride',
            'plane_joyride',
            'hot_air_balloon',
            'yacht' => [
                'city',
                'date',
                'passengers',
                'occasion',
            ],
            'speed_boat' => [
                'city',
                'date',
                'passengers',
            ],
            'pilgrimage' => [
                'product_name',
                'date',
                'passengers',
            ],
            default => [],
        };
    }

    public function missing(array $state): array
    {
        return array_values(
            array_filter(
                $this->required($state['service_family'] ?? null),
                fn ($field) => !isset($state[$field]) || $state[$field] === ''
            )
        );
    }

    public function isQualified(array $state): bool
    {
        return !empty($state['service_family'])
            && count($this->missing($state)) === 0;
    }
}
