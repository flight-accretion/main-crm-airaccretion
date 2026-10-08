<?php

namespace App\Support;

class PhoneNormalizer
{
    public static function digits($value): string
    {
        if ($value === null) {
            return '';
        }

        return preg_replace('/\D/', '', (string) $value) ?? '';
    }
}
