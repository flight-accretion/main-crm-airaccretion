<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OperationCase extends Model
{
    public const TYPE_CUSTOMER_CALL = 'customer_call';
    public const TYPE_REVIEW = 'review';
    public const TYPE_RESCHEDULE = 'reschedule';
    public const TYPE_REFUND = 'refund';
    public const TYPE_CANCELLED = 'cancelled';

    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'type',
        'status',
        'assigned_to',
        'created_by',
        'completed_by',
        'opened_at',
        'completed_at',
        'next_followup_at',
        'note',
        'metadata',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public static function validTypes(): array
    {
        return [
            self::TYPE_CUSTOMER_CALL,
            self::TYPE_REVIEW,
            self::TYPE_RESCHEDULE,
            self::TYPE_REFUND,
            self::TYPE_CANCELLED,
        ];
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function activities()
    {
        return $this->hasMany(OperationCaseActivity::class, 'operation_case_id');
    }

    public function leadFollowups()
    {
        return $this->hasMany(LeadFollowup::class, 'operation_case_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reviewConversation()
{
    return $this->hasOne(
        ReviewConversation::class,
        'operation_case_id'
    );
}

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
