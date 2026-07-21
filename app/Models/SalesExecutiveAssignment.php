<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalesExecutiveAssignment extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'manager_id',
        'sales_executive_id',
        'assigned_date',
        'notes',
        'status'
    ];

    protected $casts = [
        'assigned_date' => 'datetime',
        'status' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the manager who assigned the sales executive
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the assigned sales executive
     */
    public function salesExecutive()
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }

    /**
     * Scope to get active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    /**
     * Scope to get assignments for a specific manager
     */
    public function scopeForManager($query, $managerId)
    {
        return $query->where('manager_id', $managerId);
    }

    /**
     * Get sales executives assigned to a specific manager
     */
    public static function getSalesExecutivesForManager($managerId)
    {
        return self::active()
            ->forManager($managerId)
            ->with('salesExecutive.userType')
            ->get()
            ->pluck('salesExecutive');
    }

    /**
     * Check if a sales executive is assigned to a manager
     */
    public static function isAssigned($managerId, $salesExecutiveId)
    {
        return self::active()
            ->where('manager_id', $managerId)
            ->where('sales_executive_id', $salesExecutiveId)
            ->exists();
    }
}