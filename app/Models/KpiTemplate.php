<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiTemplate extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'department',
        'working_days_per_month',
        'active',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'working_days_per_month' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function metrics()
    {
        return $this
            ->hasMany(KpiMetric::class, 'template_id')
            ->orderBy('sort_order');
    }
}
