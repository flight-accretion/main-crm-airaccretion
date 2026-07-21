<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadPayment extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'voucher_id',
        'amount',
        'discount',
        'final_amount',
        'payment_status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'discount' => 'decimal:2',
        'final_amount' => 'decimal:2'
    ];

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }

    public function paymentDetails()
    {
        return $this->hasMany(LeadPaymentDetail::class, 'lead_payment_id');
    }
}
