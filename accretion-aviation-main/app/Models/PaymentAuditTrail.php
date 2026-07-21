<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class PaymentAuditTrail extends Model
{
    use HasFactory;

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
}
