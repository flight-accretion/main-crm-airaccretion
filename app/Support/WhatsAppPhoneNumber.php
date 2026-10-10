<?php

namespace App\Support;

use InvalidArgumentException;

class WhatsAppPhoneNumber
{
    public static function e164(
        ?string $number,
        ?string $countryCode = null,
        bool $withPlus = true
    ): string {
        $digits = preg_replace('/\D+/', '', (string) $number);

        if ($digits === '') {
            throw new InvalidArgumentException('WhatsApp number is invalid.');
        }

        $countryDigits = self::countryDigits($countryCode);

        if (
            $countryCode !== null
            && !str_starts_with($digits, $countryDigits)
            && strlen($digits) < 11
            && strlen($countryDigits . $digits) >= 11
            && strlen($countryDigits . $digits) <= 15
        ) {
            $digits = $countryDigits . $digits;
        } elseif (strlen($digits) === 10) {
            $digits = $countryDigits . $digits;
        }

        if (strlen($digits) < 11 || strlen($digits) > 15) {
            throw new InvalidArgumentException('WhatsApp number must include a valid country code.');
        }

        return ($withPlus ? '+' : '') . $digits;
    }

    public static function interaktParts(
        ?string $number,
        ?string $countryCode = null
    ): array {
        $raw = trim((string) $number);
        $digits = preg_replace('/\D+/', '', $raw);

        if ($digits === '') {
            throw new InvalidArgumentException('WhatsApp number is invalid.');
        }

        if (preg_match('/^\s*(\+\d{1,4})[\s-]+(.+)$/', $raw, $matches)) {
            return [
                'country_code' => $matches[1],
                'phone_number' => preg_replace('/\D+/', '', $matches[2]),
            ];
        }

        $countryDigits = self::countryDigits($countryCode);

        if (strlen($digits) === 10) {
            return [
                'country_code' => '+' . $countryDigits,
                'phone_number' => $digits,
            ];
        }

        if (str_starts_with($digits, $countryDigits)) {
            return [
                'country_code' => '+' . $countryDigits,
                'phone_number' => substr($digits, strlen($countryDigits)),
            ];
        }

        return [
            'country_code' => '+' . $countryDigits,
            'phone_number' => $digits,
        ];
    }

    public static function countryDigits(?string $countryCode = null): string
    {
        $digits = preg_replace('/\D+/', '', (string) $countryCode);

        if ($digits === '') {
            $digits = trim(
                (string) config('whatcrm.default_country_code', '91'),
                '+'
            );
        }

        return $digits !== '' ? $digits : '91';
    }
}
