<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoogleChatEvent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'delivery_id', 'event_type', 'event_time', 'payload',
        'status', 'attempts', 'last_error', 'attempted_at', 'processed_at', 'processed_count'];
    protected $casts = ['payload' => 'array', 'event_time' => 'datetime',
        'attempted_at' => 'datetime', 'processed_at' => 'datetime', 'processed_count' => 'integer'];

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            $event->id = $event->id ?: (string) Str::uuid();
        });
    }
}
