<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationsLeadAssignment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'operations_user_id',
        'assigned_by',
        'assigned_at',
        'unassigned_at',
        'is_active',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'unassigned_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($assignment) {
            if (empty($assignment->id)) {
                $assignment->id = (string) Str::uuid();
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function operationsUser()
    {
        return $this->belongsTo(User::class, 'operations_user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
