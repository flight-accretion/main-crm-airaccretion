<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VendorRefund extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [
        'lead_id',
        'lead_vendor_payment_id',
        'vendor_id',
        'ride_id',

        'cancellation_amount',
        'refund_amount',
        'refund_date',
        'refund_type',
        'refund_reason',
        'refund_proof',

        'no_refund_required',

        'created_by',
    ];


    protected $casts = [
        'cancellation_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'refund_date' => 'datetime',
        'no_refund_required' => 'boolean',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (!$model->id) {
                $model->id =
                    (string) Str::uuid();
            }
        });
    }


    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }


    public function leadVendorPayment()
    {
        return $this->belongsTo(
            LeadVendorPayment::class,
            'lead_vendor_payment_id'
        );
    }


    public function vendor()
    {
        return $this->belongsTo(
            Vendor::class,
            'vendor_id'
        );
    }


    public function ride()
    {
        return $this->belongsTo(
            LeadRide::class,
            'ride_id'
        );
    }


    public function createdBy()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }
}