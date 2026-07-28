<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadAllocationQueue extends Model
{
    use HasFactory;

    protected $table = 'lead_allocation_queue';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'lead_id',
        'assigned_to',
        'status',
        'reason',
        'attempt_count',
        'queued_at',
        'processed_at',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'processed_at' => 'datetime',
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

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function salesperson()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
