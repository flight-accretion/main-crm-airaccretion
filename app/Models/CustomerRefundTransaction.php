<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRefundTransaction extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'settlement_id', 'request_id', 'amount', 'refund_type',
        'refund_date', 'refund_proof', 'remarks', 'created_by',
        'reversed_at', 'reversed_by', 'reversal_reason',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_date' => 'date:Y-m-d',
        'reversed_at' => 'datetime',
    ];
}
