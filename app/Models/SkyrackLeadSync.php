<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SkyrackLeadSync extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'status',
        'reason',
        'attempt_count',
        'last_payload_hash',
        'last_error',
        'last_payload',
        'last_response',
        'synced_at',
        'next_attempt_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'last_payload' => 'array',
        'last_response' => 'array',
        'synced_at' => 'datetime',
        'next_attempt_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
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
