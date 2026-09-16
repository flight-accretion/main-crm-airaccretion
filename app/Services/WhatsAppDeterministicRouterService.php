<?php

namespace App\Services;

use Illuminate\Support\Str;

class WhatsAppDeterministicRouterService
{
    public function route(string $message, array $state = []): array
    {
        $text = $this->normalize($message);
        $updates = [];

        if ($this->containsAny($text, [
            'call me',
            'call back',
            'connect me',
            'speak to',
            'talk to',
            'human',
            'agent',
            'representative',
            'whatsapp me',
        ])) {
            return [
                'handoff' => true,
                'reason' => 'customer_requested_human',
                'priority' => 'P2',
                'updates' => $updates,
            ];
        }

        if ($this->containsAny($text, [
            'payment link',
            'pay now',
            'advance payment',
            'send payment',
            'make payment',
        ])) {
            $updates['initial_payment_intent'] = true;
        }

        if ($this->containsAny($text, [
            'book',
            'booking',
            'confirm',
            'reserve',
            'finalize',
        ])) {
            $updates['initial_booking_intent'] = true;
        }

        $family = $this->serviceFamily($text);

        if ($family) {
            $updates['service_family'] = $family;
        }

        return [
            'handoff' => false,
            'reason' => null,
            'priority' => null,
            'updates' => $updates,
        ];
    }

    private function serviceFamily(string $text): ?string
    {
        if (
            $this->containsAny($text, [
                'air ambulance',
                'medical flight',
                'patient transfer',
                'hospital transfer',
                'icu',
                'stretcher',
                'organ transfer',
                'medical evacuation',
            ])
        ) {
            return 'air_ambulance';
        }

        if ($this->containsAny($text, ['yacht', 'cruise'])) {
            return 'yacht';
        }

        if ($this->containsAny($text, ['speed boat', 'speedboat'])) {
            return 'speed_boat';
        }

        if ($this->containsAny($text, ['hot air balloon', 'balloon ride'])) {
            return 'hot_air_balloon';
        }

        if (
            $this->containsAny($text, [
                'chardham',
                'char dham',
                'vaishnodevi',
                'vaishno devi',
                'ujjain',
                'omkareshwar',
                'kedarnath',
            ])
        ) {
            return 'pilgrimage';
        }

        if (
            $this->containsAny($text, [
                'private jet',
                'private aircraft',
                'aircraft charter',
                'plane charter',
            ])
        ) {
            return 'private_aircraft_charter';
        }

        if (
            $this->containsAny($text, [
                'helicopter charter',
                'heli charter',
                'charter helicopter',
            ])
        ) {
            return 'private_helicopter_charter';
        }

        if (
            $this->containsAny($text, [
                'plane ride',
                'airplane ride',
                'flight ride',
            ])
        ) {
            return 'plane_joyride';
        }

        if (
            !$this->containsAny($text, ['helicopter tour'])
            && $this->containsAny($text, [
                'helicopter ride',
                'helicopter joyride',
                'heli ride',
            ])
        ) {
            return 'helicopter_joyride';
        }

        return null;
    }

    private function normalize(string $text): string
    {
        return trim(
            preg_replace('/\s+/', ' ', Str::lower($text)) ?: ''
        );
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (Str::contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
