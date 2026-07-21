<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class LeadVendorPaymentDetail extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_vendor_payment_id',
        'service_id',
        'service_amount',
        'vendor_service_amount',
        'is_extra_service',
        'status'
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
    public function payment()
    {
        return $this->belongsTo(LeadVendorPayment::class, 'lead_vendor_payment_id');
    }
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function extraService()
    {
        return $this->belongsTo(ExtraService::class, 'service_id');
    }
    
    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($detail) {
            try {
                Log::info('LeadVendorPaymentDetail model deleted', [
                    'id' => $detail->id,
                    'lead_vendor_payment_id' => $detail->lead_vendor_payment_id ?? null,
                ]);
            } catch (\Throwable $e) {
                // avoid breaking delete flow if logging fails
            }
        });
    }

}
