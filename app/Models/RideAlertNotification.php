<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RideAlertNotification extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'ride_id',
        'recipient_user_id',
        'recipient_number',
        'alert_type',
        'ride_date',
        'due_at',
        'status',
        'template_name',
        'template_variables',
        'provider_message_id',
        'attempt_count',
        'last_attempt_at',
        'sent_at',
        'failed_at',
        'failure_reason',
    ];

    protected $casts = [
        'ride_date' => 'date',
        'due_at' => 'datetime',
        'template_variables' => 'array',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
