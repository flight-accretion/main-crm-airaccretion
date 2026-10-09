<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class CrmDebugLog
{
    public static function info(string $message, array $context = []): void
    {
        if (!config('crm.debug_logs', false)) {
            return;
        }

        Log::info($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (!config('crm.debug_logs', false)) {
            return;
        }

        Log::debug($message, $context);
    }
}
