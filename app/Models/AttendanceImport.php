<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceImport extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'from_date',
        'to_date',
        'period_from',
        'period_to',
        'original_filename',
        'stored_path',
        'file_type',
        'status',
        'total_employees',
        'total_records',
        'created_records',
        'updated_records',
        'uploaded_by',
        'confirmed_at',
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'period_from' => 'date',
        'period_to' => 'date',
        'confirmed_at' => 'datetime',
        'total_employees' => 'integer',
        'total_records' => 'integer',
        'created_records' => 'integer',
        'updated_records' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function uploadedBy()
    {
        return $this->belongsTo(
            User::class,
            'uploaded_by'
        );
    }
}
