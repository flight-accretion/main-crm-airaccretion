<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class VendorPayment extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_vendor_payment_id',
        'payment_method',
        'paid_amount',
        'receipt',
        'paid_date',
        'narration',
        'status',
    ];

    protected $casts = [
        'paid_amount' => 'decimal:2',
        'paid_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = Str::uuid()->toString();
            }
        });
    }

    /**
     * Relation to LeadVendorPayment
     */
    public function leadVendorPayment()
    {
        return $this->belongsTo(LeadVendorPayment::class, 'lead_vendor_payment_id');
    }
}