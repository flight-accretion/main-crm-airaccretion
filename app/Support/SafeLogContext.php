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
        'body',
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
                $safe[$key] = substr($value, 0, 500);
                continue;
            }

            $safe[$key] = $value;
        }

        return $safe;
    }
}
