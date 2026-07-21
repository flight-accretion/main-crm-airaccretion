<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';
    public $incrementing = false; // since we're using UUID
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_id',
        'company_name',
        'voucher_id',
        'gst_number',
        'billing_address',
        'status',
    ];

    /**
     * Relationships
     */

    // Each invoice belongs to a voucher
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
