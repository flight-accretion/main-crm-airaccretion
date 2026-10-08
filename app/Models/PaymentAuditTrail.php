<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class PaymentAuditTrail extends Model
{
    use HasFactory;

    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;
    public const STATUS_FULL_PAYMENT_RECEIVED_SUBMITTED = 3;
    public const STATUS_PARTIAL_PAYMENT_RECEIVED_SUBMITTED = 4;
    public const FINAL_REVIEW_STATUSES = [
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $table = 'payment_audit_trail';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'lead_followup_id',
        'paid_amount',
        'paid_date',
        'payment_method',
        //'file',
        'narration',
        'payment_status',
        'created_by',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'paid_date' => 'datetime',
    ];

    // Relationships
    public function leadFollowup()
    {
        return $this->belongsTo(LeadFollowup::class, 'lead_followup_id');
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            self::STATUS_FULL_PAYMENT_RECEIVED_SUBMITTED => 'Full Payment Received',
            self::STATUS_PARTIAL_PAYMENT_RECEIVED_SUBMITTED => 'Partial Payment Received',
        ];
    }

    public function statusLabel(string $fallback = 'Pending'): string
    {
        return self::statusLabels()[(int) $this->payment_status] ?? $fallback;
    }
}
