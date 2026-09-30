<?php

namespace App\Services\GoogleChat;

use Carbon\Carbon;

class GoogleChatTime
{
    public static function parse($value): Carbon
    {
        // Eloquent timestamp columns store local wall time without an offset.
        return Carbon::parse($value)->setTimezone(config('app.timezone', 'UTC'));
    }
}
