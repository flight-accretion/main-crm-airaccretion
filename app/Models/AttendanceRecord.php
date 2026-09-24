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
        'resolved_shift_policy_id',
        'resolved_shift_policy_name',
        'resolved_shift_start_time',
        'resolved_shift_end_time',
        'resolved_shift_grace_minutes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'resolved_shift_grace_minutes' => 'integer',
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
