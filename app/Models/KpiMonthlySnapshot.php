<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiMonthlySnapshot extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'template_id',
        'year',
        'month',
        'weighted_raw_score',
        'overall_score',
        'finalized_at',
        'finalized_by',
    ];

    protected $casts = [
        'weighted_raw_score' => 'float',
        'overall_score' => 'integer',
        'finalized_at' => 'datetime',
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
