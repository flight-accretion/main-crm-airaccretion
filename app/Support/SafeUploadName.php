<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SafeUploadName
{
    public static function make(UploadedFile $file, string $prefix = ''): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $prefix = trim(preg_replace('/[^a-zA-Z0-9_-]+/', '_', $prefix), '_');

        return ($prefix !== '' ? $prefix . '_' : '') . (string) Str::uuid() . '.' . $extension;
    }
}
