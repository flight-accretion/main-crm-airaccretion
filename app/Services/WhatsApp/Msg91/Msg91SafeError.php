<?php

namespace App\Services\WhatsApp\Msg91;

class Msg91SafeError
{
    public static function message(array $payload, string $fallback): string
    {
        foreach (['errors.0.message', 'errors.0', 'errors.message', 'errors', 'error.message', 'error_description', 'data.message', 'message'] as $path) {
            $value = data_get($payload, $path);
            if (!is_scalar($value) || trim((string) $value) === '') continue;
            $value = strip_tags((string) $value);
            $key = (string) config('services.msg91_campaigns.auth_key');
            if ($key !== '') $value = str_replace($key, '[redacted]', $value);
            $value = preg_replace('/authorization\s*:\s*Bearer\s+\S+/i', 'authorization=[redacted]', $value);
            $value = preg_replace('/(authkey|authorization|password|access[_ -]?token|api[_ -]?key)\s*[:=]\s*\S+/i', '$1=[redacted]', $value);
            return mb_substr(trim(preg_replace('/[\x00-\x20\x7f]+/', ' ', $value)), 0, 300);
        }
        return $fallback;
    }
}
