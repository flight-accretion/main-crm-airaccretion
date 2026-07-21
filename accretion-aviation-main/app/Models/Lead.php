<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Log;

class Lead extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'client_id',
        'representative_user_id',
        'service_ids',
        'product_ids',
        'number_of_passengers',
        'description',
        'occasion'
    ];

    protected $casts = [
        'service_ids' => 'array',
        'product_ids' => 'array',
    ];

    /**
     * Get service IDs as array, handling both string and array formats
     */
    public function getServiceIdsArrayAttribute()
    {
        $serviceIds = $this->service_ids;
        if (is_string($serviceIds)) {
            $serviceIds = json_decode($serviceIds, true) ?? [];
        }
        return is_array($serviceIds) ? $serviceIds : [];
    }

    /**
     * Get product IDs as array, handling both string and array formats
     */
    public function getProductIdsArrayAttribute()
    {
        $productIds = $this->product_ids;
        if (is_string($productIds)) {
            $productIds = json_decode($productIds, true) ?? [];
        }
        return is_array($productIds) ? $productIds : [];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function representative()
    {
        return $this->belongsTo(User::class, 'representative_user_id');
    }

    public function rideSegments()
    {
        return $this->hasMany(LeadRide::class, 'lead_id');
    }

    public function getProductsAttribute()
    {
        return Product::whereIn('id', $this->product_ids_array)->get();
    }
    public function products()
    {
        return Product::whereIn('id', $this->product_ids_array)->get();
    }
      public function getProductNamesAttribute()
    {
        $productIds = $this->product_ids_array;
        if (empty($productIds)) {
            return [];
        }

        return Product::whereIn('id', $productIds)->pluck('product')->toArray();
    }

    public function leadFollowups()
    {
        return $this->hasMany(LeadFollowup::class, 'lead_id');
    }

    public function leadAuditTrails()
    {
        return $this->hasMany(LeadAuditTrail::class, 'lead_id');
    }

    public function leadVendorPayments()
    {
        return $this->hasMany(LeadVendorPayment::class, 'lead_id');
    }

    public function getLatestFollowupAttribute()
    {
        return $this->leadFollowups()->orderByDesc('next_followup_date')->first();
    }
    public function latestFollowup()
    {
        return $this->hasOne(LeadFollowup::class, 'lead_id')->latest('created_at');
    }

    public function getNextFollowupAttribute()
    {
        return $this->leadFollowups()
            ->where('next_followup_date', '>=', now())
            ->orderBy('next_followup_date', 'asc')
            ->first();
    }
    public function getServiceNamesAttribute()
    {
        // First try to get services from the lead itself
        $serviceIds = $this->service_ids_array;

        if (!empty($serviceIds)) {
            $services = Service::whereIn('id', $serviceIds)
                              ->where('status', 1)
                              ->pluck('service')
                              ->toArray();
            if (!empty($services)) {
                return $services;
            }
        }

        // Fallback: get services from followups if lead has no services
        $serviceNames = [];
        $followups = $this->leadFollowups;

        foreach ($followups as $followup) {
            if (!empty($followup->service_ids)) {
                $followupServiceIds = is_string($followup->service_ids) ? json_decode($followup->service_ids, true) : $followup->service_ids;
                if (is_array($followupServiceIds)) {
                    $services = Service::whereIn('id', $followupServiceIds)
                                      ->where('status', 1)
                                      ->pluck('service')
                                      ->toArray();
                    $serviceNames = array_merge($serviceNames, $services);
                }
            }
        }

        return array_unique($serviceNames);
    }

    public function getRideDatesAttribute()
    {
        $rideSegments = $this->rideSegments;
        if ($rideSegments->isEmpty()) {
            return null;
        }

        $fromDate = $rideSegments->min('from_date');
        $toDate = $rideSegments->max('to_date');

        if (!$fromDate || !$toDate) {
            return null;
        }

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate
        ];
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class);
    }

    public function rides()
    {
        return $this->hasMany(LeadRide::class);
    }

    public function passengers()
    {
        return $this->hasMany(LeadPassenger::class);
    }

    public function preVoucherPassengers()
    {
        return $this->passengers()->whereNull('voucher_id');
    }

    public function generatePassengerRegistrationToken()
    {
        // Create or update a passenger registration record for this lead
        $passenger = $this->passengers()->whereNull('voucher_id')->first();
        
        if (!$passenger) {
            $passenger = new LeadPassenger([
                'id' => \Illuminate\Support\Str::uuid(),
                'lead_id' => $this->id,
                'name' => '', // Will be filled during registration
                'is_handler' => false,
                'is_additional_person' => false
            ]);
            $passenger->save();
        }

        // If a valid token already exists, reuse it instead of regenerating on every call
        try {
            if (method_exists($passenger, 'isTokenValid') && $passenger->isTokenValid()) {
                return $passenger->registration_token;
            }
        } catch (\Throwable $e) {
            // In unexpected cases, fall through to generate a new token
        }

        return $passenger->generateRegistrationToken();
    }

    public function getPassengerRegistrationLink()
    {
        $passenger = $this->passengers()->whereNull('voucher_id')->whereNotNull('registration_token')->first();
        if ($passenger && $passenger->isTokenValid()) {
            return $passenger->getRegistrationLink();
        }
        return null;
    }

    /**
     * Ensure related models are removed when a Lead is deleted.
     * We perform explicit deletes to avoid relying on DB foreign keys and to
     * make sure nested relations (like payment audit trail, refunds, voucher data)
     * are also cleaned up.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($lead) {
            Log::info('Lead deleting started', ['lead_id' => $lead->id]);

            // Delete followups -> which may have payment audit trail and refunds
            try {
                $followupsCount = $lead->leadFollowups->count();
            } catch (\Throwable $e) {
                $followupsCount = null;
            }
            Log::info('Lead followups count', ['lead_id' => $lead->id, 'count' => $followupsCount]);

            foreach ($lead->leadFollowups as $followup) {
                try {
                    // Payment audit trails attached to followup
                    $followup->paymentAuditTrail()->delete();
                    Log::info('Deleted paymentAuditTrail for followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting paymentAuditTrail for followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id, 'exception' => $e->getMessage()]);
                }

                try {
                    \App\Models\LeadRefund::where('lead_followup_id', $followup->id)->delete();
                    Log::info('Deleted refunds for followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting refunds for followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id, 'exception' => $e->getMessage()]);
                }

                try {
                    $followup->delete();
                    Log::info('Deleted followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting followup', ['lead_id' => $lead->id, 'followup_id' => $followup->id, 'exception' => $e->getMessage()]);
                }
            }

            // Delete vouchers and nested voucher data
            try {
                $vouchersCount = $lead->vouchers->count();
            } catch (\Throwable $e) {
                $vouchersCount = null;
            }
            Log::info('Lead vouchers count', ['lead_id' => $lead->id, 'count' => $vouchersCount]);

            foreach ($lead->vouchers as $voucher) {
                try {
                    $voucher->passengers()->delete();
                    Log::info('Deleted voucher passengers', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting voucher passengers', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'exception' => $e->getMessage()]);
                }

                // Delete vendorPayments as model instances so their deleting
                // handlers run and remove LeadVendorPaymentDetail entries.
                try {
                    foreach ($voucher->vendorPayments as $lvp) {
                        try {
                            Log::info('Deleting LeadVendorPayment via voucher deletion', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'lvp_id' => $lvp->id]);
                            $lvp->delete();
                            Log::info('Deleted LeadVendorPayment via voucher deletion', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'lvp_id' => $lvp->id]);
                        } catch (\Throwable $e) {
                            Log::error('Failed deleting LeadVendorPayment via voucher deletion', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'lvp_id' => $lvp->id ?? null, 'exception' => $e->getMessage()]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed iterating voucher vendorPayments', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'exception' => $e->getMessage()]);
                }

                try {
                    if ($voucher->payment) {
                        // delete the model instance so model events fire
                        $voucher->payment->delete();
                        Log::info('Deleted voucher payment', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed deleting voucher payment', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'exception' => $e->getMessage()]);
                }

                try {
                    if ($voucher->invoice) {
                        $voucher->invoice->delete();
                        Log::info('Deleted voucher invoice', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed deleting voucher invoice', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'exception' => $e->getMessage()]);
                }

                try {
                    $voucher->delete();
                    Log::info('Deleted voucher', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id]);
                } catch (\Throwable $e) {
                    Log::error('Failed deleting voucher', ['lead_id' => $lead->id, 'voucher_id' => $voucher->id, 'exception' => $e->getMessage()]);
                }
            }

            // Delete vendor payments that reference the lead directly
            foreach ($lead->leadVendorPayments as $lvp) {
                try {
                    // Delete payment details via model instances where possible
                    foreach ($lvp->paymentDetails as $detail) {
                        try {
                            $detail->delete();
                            Log::info('Deleted LeadVendorPaymentDetail via lead deletion', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'detail_id' => $detail->id]);
                        } catch (\Throwable $e) {
                            Log::error('Failed deleting LeadVendorPaymentDetail via lead deletion', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'detail_id' => $detail->id ?? null, 'exception' => $e->getMessage()]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed iterating paymentDetails for lvp', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'exception' => $e->getMessage()]);
                }

                try {
                    // Delete vendorPayments (VendorPayment models)
                    foreach ($lvp->vendorPayments as $vp) {
                        try {
                            $vp->delete();
                            Log::info('Deleted VendorPayment via lead deletion', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'vendor_payment_id' => $vp->id]);
                        } catch (\Throwable $e) {
                            Log::error('Failed deleting VendorPayment via lead deletion', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'vendor_payment_id' => $vp->id ?? null, 'exception' => $e->getMessage()]);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed iterating vendorPayments for lvp', ['lead_id' => $lead->id, 'lvp_id' => $lvp->id, 'exception' => $e->getMessage()]);
                }

                try {
                    $lvp->delete();
                } catch (\Throwable $e) {
                }
            }

            // Other direct relations
            try {
                $lead->rideSegments()->delete();
                Log::info('Deleted rideSegments relation (query)', ['lead_id' => $lead->id]);
            } catch (\Throwable $e) {
                Log::error('Failed deleting rideSegments', ['lead_id' => $lead->id, 'exception' => $e->getMessage()]);
            }
            try {
                $lead->rides()->delete();
                Log::info('Deleted rides relation (query)', ['lead_id' => $lead->id]);
            } catch (\Throwable $e) {
                Log::error('Failed deleting rides', ['lead_id' => $lead->id, 'exception' => $e->getMessage()]);
            }
            try {
                $lead->passengers()->delete();
                Log::info('Deleted passengers relation (query)', ['lead_id' => $lead->id]);
            } catch (\Throwable $e) {
                Log::error('Failed deleting passengers', ['lead_id' => $lead->id, 'exception' => $e->getMessage()]);
            }
            try {
                $lead->leadAuditTrails()->delete();
                Log::info('Deleted leadAuditTrails relation (query)', ['lead_id' => $lead->id]);
            } catch (\Throwable $e) {
                Log::error('Failed deleting leadAuditTrails', ['lead_id' => $lead->id, 'exception' => $e->getMessage()]);
            }

            Log::info('Lead deleting finished', ['lead_id' => $lead->id]);
        });
    }
}
