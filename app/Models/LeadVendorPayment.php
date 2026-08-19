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

    public function getTotalPaidAttribute()
{
    if ($this->relationLoaded('vendorPayments')) {

        return (float)
            $this->vendorPayments
                ->sum('paid_amount');
    }

    return (float)
        $this->vendorPayments()
            ->sum('paid_amount');
}

public function getCancellationAmountAttribute()
{
    /*
    |--------------------------------------------------------------------------
    | Latest cancellation settlement wins
    |--------------------------------------------------------------------------
    |
    | Cancellation Amount means the FINAL vendor liability after cancellation.
    |
    | Example:
    | Original vendor cost = ₹70,000
    | Cancellation amount = ₹20,000
    | Final payable = ₹20,000
    |
    */

    $refund =
        $this->relationLoaded('vendorRefunds')
            ? $this->vendorRefunds
                ->sortByDesc('created_at')
                ->first()
            : $this->vendorRefunds()
                ->latest('created_at')
                ->first();


    if (!$refund) {

        return null;
    }


    return (float)
        ($refund->cancellation_amount ?? 0);
}


public function getTotalRefundedAttribute()
{
    if ($this->relationLoaded('vendorRefunds')) {

        return (float)
            $this->vendorRefunds
                ->sum('refund_amount');
    }

    return (float)
        $this->vendorRefunds()
            ->sum('refund_amount');
}

public function getAdjustedVendorPayableAttribute()
{
    /*
    |--------------------------------------------------------------------------
    | No cancellation settlement
    |--------------------------------------------------------------------------
    */

    if ($this->cancellation_amount === null) {

        return max(
            0,
            (float)
            $this->total_vendor_service_amount
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cancellation settlement exists
    |--------------------------------------------------------------------------
    */

    return max(
        0,
        (float)
        $this->cancellation_amount
    );
}


public function getNetVendorCostAttribute()
{
    /*
    |--------------------------------------------------------------------------
    | What amount is finally retained by / payable to vendor
    |--------------------------------------------------------------------------
    */

    return max(
        0,
        $this->adjusted_vendor_payable
    );
}


public function getAvailableRefundAttribute()
{
    /*
    |--------------------------------------------------------------------------
    | Actual overpayment that vendor must return
    |--------------------------------------------------------------------------
    |
    | Paid ₹70,000
    | Cancellation liability ₹20,000
    | Already refunded ₹10,000
    |
    | Remaining refund =
    | 70,000 - 20,000 - 10,000
    | = ₹40,000
    |
    */

    return max(
        0,
        $this->total_paid
        -
        $this->adjusted_vendor_payable
        -
        $this->total_refunded
    );
}


public function getVendorRefundStatusAttribute()
{
    $totalPaid =
        $this->total_paid;

    $totalRefunded =
        $this->total_refunded;

    $adjustedPayable =
        $this->adjusted_vendor_payable;

    $refundDue =
        max(
            0,
            $totalPaid
            -
            $adjustedPayable
            -
            $totalRefunded
        );


    /*
    |--------------------------------------------------------------------------
    | No settlement yet
    |--------------------------------------------------------------------------
    */

    if (
        $this->cancellation_amount === null
    ) {

        return 'none';
    }


    /*
    |--------------------------------------------------------------------------
    | Nothing was paid and cancellation liability is zero
    |--------------------------------------------------------------------------
    */

    if (
        $totalPaid <= 0
        &&
        $adjustedPayable <= 0
    ) {

        return 'settled';
    }


    /*
    |--------------------------------------------------------------------------
    | We still owe vendor money
    |--------------------------------------------------------------------------
    */

    if (
        $totalPaid < $adjustedPayable
    ) {

        return 'vendor_payment_pending';
    }


    /*
    |--------------------------------------------------------------------------
    | Vendor owes us money
    |--------------------------------------------------------------------------
    */

    if ($refundDue > 0) {

        if ($totalRefunded > 0) {

            return 'partial_refund';
        }

        return 'refund_pending';
    }


    /*
    |--------------------------------------------------------------------------
    | Financial position reconciled
    |--------------------------------------------------------------------------
    */

    return 'settled';
}

    public function vendorRefunds()
{
    return $this->hasMany(
        VendorRefund::class,
        'lead_vendor_payment_id'
    );
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

        /*
        |--------------------------------------------------------------------------
        | Delete payment details
        |--------------------------------------------------------------------------
        */

        foreach ($lvp->paymentDetails as $detail) {

            try {

                $detail->delete();

            } catch (\Throwable $e) {

                Log::error(
                    'Failed deleting LeadVendorPaymentDetail',
                    [
                        'lead_vendor_payment_id' =>
                            $lvp->id,

                        'detail_id' =>
                            $detail->id ?? null,

                        'exception' =>
                            $e->getMessage(),
                    ]
                );

                throw $e;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Delete VendorPayment records
        |--------------------------------------------------------------------------
        */

        foreach ($lvp->vendorPayments as $vp) {

            try {

                $vp->delete();

            } catch (\Throwable $e) {

                Log::error(
                    'Failed deleting VendorPayment',
                    [
                        'lead_vendor_payment_id' =>
                            $lvp->id,

                        'vendor_payment_id' =>
                            $vp->id ?? null,

                        'exception' =>
                            $e->getMessage(),
                    ]
                );

                throw $e;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Delete VendorRefund records
        |--------------------------------------------------------------------------
        |
        | Normally VoucherController reassigns these before an old
        | LeadVendorPayment is deleted.
        |
        | This is cleanup protection for genuine deletion.
        |
        */

        foreach ($lvp->vendorRefunds as $refund) {

            try {

                $refund->delete();

            } catch (\Throwable $e) {

                Log::error(
                    'Failed deleting VendorRefund',
                    [
                        'lead_vendor_payment_id' =>
                            $lvp->id,

                        'vendor_refund_id' =>
                            $refund->id ?? null,

                        'exception' =>
                            $e->getMessage(),
                    ]
                );

                throw $e;
            }
        }
    });
}
}
