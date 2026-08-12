<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EmailLeadLog extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'message_id',
        'imap_uid',
        'sender_email',
        'recipient_email',
        'subject',
        'customer_name',
        'customer_phone',
        'service_name',
        'departure_date',
        'departure_time',
        'passenger_count',
        'email_body',
        'parsed_data',
        'lead_id',
        'processing_status',
        'processing_message',
        'received_at',
        'processed_at',
        'followup_created_at',
    ];

    protected $casts = [
        'parsed_data' => 'array',
        'departure_date' => 'date',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'followup_created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }
}