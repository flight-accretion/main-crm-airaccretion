<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RideReminderLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'id',
        'ride_id',
        'lead_id',
        'hours_before',
        'channel',
        'recipient',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'hours_before' => 'integer',
        'sent_at' => 'datetime',
    ];
}
