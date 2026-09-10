<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiMetricSnapshot extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'snapshot_id',
        'metric_id',
        'actual_value',
        'target_value',
        'achievement_percent',
        'score',
        'weightage',
        'weighted_score',
        'evidence',
    ];

    protected $casts = [
        'actual_value' => 'float',
        'target_value' => 'float',
        'achievement_percent' => 'float',
        'score' => 'integer',
        'weightage' => 'float',
        'weighted_score' => 'float',
        'evidence' => 'array',
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
