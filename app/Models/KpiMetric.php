<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiMetric extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'template_id',
        'code',
        'name',
        'description',
        'weightage',
        'measurement_type',
        'source_key',
        'target_value',
        'direction',
        'score_rules',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'score_rules' => 'array',
        'active' => 'boolean',
        'weightage' => 'float',
        'target_value' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }
}
