<?php

namespace App\Support;

use Illuminate\Support\Collection;

class ArrayIdNormalizer
{
    public static function normalize($value): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            return self::clean($value);
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        if (is_string($decoded)) {
            $decoded = json_decode(stripslashes($decoded), true);
        }

        if (is_array($decoded)) {
            return self::clean($decoded);
        }

        $decoded = json_decode(stripslashes($value), true);

        return is_array($decoded) ? self::clean($decoded) : [];
    }

    private static function clean(array $ids): array
    {
        $ids = array_filter($ids, function ($id) {
            return (is_string($id) || is_numeric($id)) && trim((string) $id) !== '';
        });

        return array_values(array_unique(array_map('strval', $ids)));
    }
}
