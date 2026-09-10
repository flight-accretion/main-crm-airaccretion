<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiManualValue extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'metric_id',
        'year',
        'month',
        'value',
        'rating',
        'note',
        'entered_by',
    ];

    protected $casts = [
        'value' => 'float',
        'rating' => 'integer',
        'year' => 'integer',
        'month' => 'integer',
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
