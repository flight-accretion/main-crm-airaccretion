<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AttendanceRecord extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'paycode',
        'attendance_date',
        'day_name',
        'in_time',
        'out_time',
        'raw_in',
        'raw_out',
        'raw_status',
        'source_import_id',
    ];

    protected $casts = [
        'attendance_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function sourceImport()
    {
        return $this->belongsTo(
            AttendanceImport::class,
            'source_import_id'
        );
    }
}