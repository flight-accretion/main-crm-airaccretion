<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiOutreachBatch extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'batch_type',
        'requested_count',
        'allocated_count',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'requested_count' => 'integer',
        'allocated_count' => 'integer',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
