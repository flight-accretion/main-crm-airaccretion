<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadRefund extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_followup_id',
        'original_amount',
        'refund_amount',
        'refund_type',
        'refund_date',
        'refund_reason',
        'refund_proof',
        'status',
        'refund_invoice_id',
        'remarks',
    ];

    protected $casts = [
        'id' => 'string',
        'lead_followup_id' => 'string',
        'original_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_date' => 'datetime',
        'refund_invoice_id' => 'string',
        'remarks' => 'string',
    ];
    /**
     * Get the lead follow-up associated with the refund.
     */
    public function leadFollowup(): BelongsTo
    {
        return $this->belongsTo(LeadFollowup::class, 'lead_followup_id');
    }

    /**
     * Get the original voucher through leadFollowup
     */
    public function voucher()
    {
        return $this->hasOneThrough(
            Voucher::class,
            LeadFollowup::class,
            'id',
            'lead_id',
            'lead_followup_id',
            'lead_id'
        );
    }

    /**
     * Get the original invoice through voucher
     */
    public function originalInvoice()
    {
        return $this->hasOneThrough(
            Invoice::class,
            LeadFollowup::class,
            'id',
            'voucher_id',
            'lead_followup_id',
            'lead_id'
        )->whereHas('voucher', function($query) {
            $query->whereColumn('vouchers.lead_id', 'lead_followups.lead_id');
        });
    }
}



