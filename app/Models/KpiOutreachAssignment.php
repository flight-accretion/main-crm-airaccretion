<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class KpiOutreachAssignment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'pool_id',
        'user_id',
        'batch_id',
        'allocation_type',
        'normalized_phone',
        'active_phone_key',
        'status',
        'completion_type',
        'remark',
        'remark_expires_at',
        'call_summary_integration_id',
        'ivr_call_log_id',
        'created_lead_id',
        'assigned_at',
        'completed_at',
    ];

    protected $casts = [
        'remark_expires_at' => 'datetime',
        'assigned_at' => 'datetime',
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

    public function pool()
    {
        return $this->belongsTo(KpiOutreachPool::class, 'pool_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function batch()
    {
        return $this->belongsTo(KpiOutreachBatch::class, 'batch_id');
    }
}
