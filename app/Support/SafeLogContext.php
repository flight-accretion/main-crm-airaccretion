<?php

namespace App\Support;

use Throwable;

class SafeLogContext
{
    private const BLOCKED_KEYS = [
        'token',
        'access_token',
        'authorization',
        'payload',
        'raw_payload',
        'body',
        'message',
        'text',
        'summary',
        'response_body',
        'response',
        'trace',
    ];

    public static function exception(Throwable $e, array $context = []): array
    {
        return self::httpFailure($context + [
            'error' => substr($e->getMessage(), 0, 500),
            'exception' => class_basename($e),
        ]);
    }

    public static function httpFailure(array $context = []): array
    {
        return self::sanitize($context);
    }

    public static function mask(array $context): array
    {
        return self::sanitize($context);
    }

    private static function sanitize(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);

            if (in_array($normalizedKey, self::BLOCKED_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = self::sanitize($value);
                continue;
            }

            if (is_string($value)) {
                $safe[$key] = self::sanitizeString($normalizedKey, $value);
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }

    private static function sanitizeString(string $key, string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        if (str_contains($key, 'phone') || str_contains($key, 'number')) {
            $digits = preg_replace('/\D+/', '', $trimmed);

            if ($digits && strlen($digits) >= 6) {
                return str_repeat('*', max(0, strlen($digits) - 4)) . substr($digits, -4);
            }
        }

        if (str_contains($key, 'email')) {
            return preg_replace('/(^.).*(@.*$)/', '$1***$2', $trimmed) ?: '***';
        }

        return substr($trimmed, 0, 500);
    }
}
