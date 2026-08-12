<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

class EmailLeadParserService
{
    public function parse(
        string $body
    ): array {
        $fields = [];

        $lines = preg_split(
            '/\r\n|\r|\n/',
            $body
        );

        foreach ($lines as $line) {
            $line = trim($line);

            if (
                $line === ''
                ||
                !str_contains(
                    $line,
                    ':'
                )
            ) {
                continue;
            }

            [$key, $value] = array_pad(
                explode(
                    ':',
                    $line,
                    2
                ),
                2,
                ''
            );

            $key = $this->normalizeKey(
                $key
            );

            $value = trim(
                $value
            );

            if ($key !== '') {
                $fields[$key] = $value;
            }
        }

        $phone =
            $fields['phone_no']
            ?? $fields['phone']
            ?? $fields['mobile']
            ?? $fields['contact_no']
            ?? null;

        $phone = $this->normalizePhone(
            $phone
        );

        $service =
            $fields['services']
            ?? $fields['service']
            ?? null;

        $departureDate =
            $fields['departuredate']
            ?? $fields['departure_date']
            ?? null;

        if ($departureDate) {
            try {
                $departureDate = Carbon::parse(
                    $departureDate
                )->toDateString();
            } catch (\Throwable $e) {
                $departureDate = null;
            }
        }

        $passenger =
            $fields['passenger']
            ?? $fields['passengers']
            ?? $fields['pax']
            ?? 1;

        return [
            'name' =>
                $fields['name']
                ?? null,

            'phone' =>
                $phone,

            'service' =>
                $service,

            'departure_date' =>
                $departureDate,

            'departure_time' =>
                $fields['departuretime']
                ?? $fields['departure_time']
                ?? null,

            'passenger_count' =>
                max(
                    1,
                    (int) $passenger
                ),

            /*
             * Keep every field in case
             * a new website form adds fields later.
             */
            'all_fields' => $fields,

            /*
             * Keep complete readable email.
             */
            'original_message' => trim(
                $body
            ),
        ];
    }

    private function normalizeKey(
        string $key
    ): string {
        $key = Str::lower(
            trim($key)
        );

        $key = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $key
        );

        return trim(
            $key,
            '_'
        );
    }

    public function normalizePhone(
        ?string $phone
    ): ?string {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace(
            '/\D+/',
            '',
            $phone
        );

        if (!$digits) {
            return null;
        }

        /*
         * Indian phone matching:
         * keep last 10 digits.
         */
        if (strlen($digits) > 10) {
            $digits = substr(
                $digits,
                -10
            );
        }

        return $digits;
    }
}