<?php

namespace App\Support;

class EmailTextNormalizer
{
    public static function toUtf8(string $value, ?string $charset = null): string
    {
        $charset = trim((string) $charset, " \t\n\r\0\x0B\"'");
        if ($charset !== '' && !in_array(strtoupper($charset), ['DEFAULT', 'UTF-8', 'UTF8', 'US-ASCII', 'ASCII'], true)) {
            try {
                $value = mb_convert_encoding($value, 'UTF-8', $charset);
            } catch (\ValueError $error) {
                // Unknown charset: use the same conservative fallback as missing headers.
            }
        }
        if (!mb_check_encoding($value, 'UTF-8')) {
            // Windows-1252 covers common legacy Western email bytes, including 0x96.
            $value = mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        }
        // PostgreSQL text and JSON values cannot contain a NUL character.
        return str_replace("\0", '', $value);
    }

    public static function normalize($value)
    {
        if (is_string($value)) {
            return self::toUtf8($value);
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[is_string($key) ? self::toUtf8($key) : $key] = self::normalize($item);
            }
            return $result;
        }
        return $value;
    }
}
