<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LeadVendorPayment extends Model
{
    use HasFactory;

    public $incrementing = false; // UUID primary key
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'voucher_id',
        'lead_id',
        'vendor_id',
        'total_service_amount',
        'total_vendor_service_amount',
        'payment_status'
    ];

    /**
     * Relationships
     */

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function paymentDetails()
    {
        return $this->hasMany(LeadVendorPaymentDetail::class, 'lead_vendor_payment_id');
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }

    public function vendorPayments()
    {
        return $this->hasMany(VendorPayment::class, 'lead_vendor_payment_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($lvp) {
            foreach ($lvp->paymentDetails as $detail) {
                try {
                    $detail->delete();
                } catch (\Throwable $e) {
                    Log::error('Failed deleting LeadVendorPaymentDetail', [
                        'lead_vendor_payment_id' => $lvp->id,
                        'detail_id' => $detail->id ?? null,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            // Likewise delete vendorPayments model instances so any child
            // cleanup runs on those models as well.
            foreach ($lvp->vendorPayments as $vp) {
                try {
                   

                    $vp->delete();
                } catch (\Throwable $e) {
                    Log::error('Failed deleting VendorPayment', [
                        'lead_vendor_payment_id' => $lvp->id,
                        'vendor_payment_id' => $vp->id,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting VendorPayment', [
                        'lead_vendor_payment_id' => $lvp->id,
                        'vendor_payment_id' => $vp->id ?? null,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        });
    }
}
