<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefundSettlement extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'lead_id', 'lead_followup_id', 'lead_refund_id',
        'approved_paid', 'cancellation_amount', 'refund_due',
        'reason', 'assessed_by', 'assessed_at',
    ];

    protected $casts = [
        'approved_paid' => 'decimal:2',
        'cancellation_amount' => 'decimal:2',
        'refund_due' => 'decimal:2',
        'assessed_at' => 'datetime',
    ];

    public function transactions()
    {
        return $this->hasMany(CustomerRefundTransaction::class, 'settlement_id');
    }
}
