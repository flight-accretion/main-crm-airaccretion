<?php

namespace App\Support;

class ValidationRules
{
    public static function optionalPhone(): array
    {
        return ['nullable', 'string', 'max:20'];
    }

    public static function requiredPhone(): array
    {
        return ['required', 'string', 'max:20'];
    }

    public static function optionalUuid(): array
    {
        return ['nullable', 'uuid'];
    }

    public static function requiredUuid(): array
    {
        return ['required', 'uuid'];
    }
}
