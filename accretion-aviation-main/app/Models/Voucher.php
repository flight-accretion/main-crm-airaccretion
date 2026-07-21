<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Voucher extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'operation_team_user_id',
        'extra_upload',
        'naration',
        'status',
        'created_by',
        'registration_token'
    ];



    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function passengers()
    {
        return $this->hasMany(LeadPassenger::class);
    }

    /**
     * Alias expected by some controllers: leadPassengers
     */
    public function leadPassengers()
    {
        return $this->hasMany(LeadPassenger::class);
    }



    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function operationTeam()
    {
        return $this->belongsTo(User::class, 'operation_team_user_id');
    }

    public function vendor()
    {
        return $this->belongsTo(\App\Models\Vendor::class);
    }

    public function vendorPayments()
    {
        return $this->hasMany(LeadVendorPayment::class, 'voucher_id');
    }

    public function operationTeamUser()
    {
        return $this->belongsTo(User::class, 'operation_team_user_id');
    }

    public function paymentDetails()
    {
        return $this->hasManyThrough(
            LeadPaymentDetail::class,
            LeadPayment::class,
            'voucher_id', // Foreign key on lead_payments table
            'lead_payment_id', // Foreign key on lead_payment_details table
            'id', // Local key on vouchers table
            'id' // Local key on lead_payments table
        );
    }

    public function servicePaymentDetails()
    {
        return $this->hasManyThrough(
            LeadPaymentDetail::class,
            LeadPayment::class,
            'voucher_id',
            'lead_payment_id',
            'id',
            'id'
        )->where('lead_payment_details.is_extra_service', false);
    }

    public function extraServicePaymentDetails()
    {
        return $this->hasManyThrough(
            \App\Models\LeadPaymentDetail::class,
            \App\Models\LeadPayment::class,
            'voucher_id',
            'lead_payment_id',
            'id',
            'id'
        )->where('lead_payment_details.is_extra_service', true);
    }

    public function payment()
    {
        return $this->hasOne(\App\Models\LeadPayment::class, 'voucher_id');
    }

    /**
     * Each voucher may have one invoice.
     */
    public function invoice()
    {
        return $this->hasOne(\App\Models\Invoice::class, 'voucher_id', 'id');
    }

    // Helper methods to get related data
    public function getSelectedProducts()
    {
        if (empty($this->product_ids)) {
            return collect();
        }
        return \App\Models\Product::whereIn('id', $this->product_ids)->get();
    }

    public function getSelectedVendors()
    {
        if (empty($this->vendor_ids)) {
            return collect();
        }
        return \App\Models\Vendor::whereIn('id', $this->vendor_ids)->get();
    }

    // Get services from payment details
    public function getSelectedServices()
    {
        return $this->servicePaymentDetails()->with('service')->get()->pluck('service');
    }

    /**
     * Access related Service models through payment details.
     * Provides plural 'services' and singular 'service' alias for existing code expectations.
     */
    public function services()
    {
        // LeadPaymentDetail -> service_id refers to App\Models\Service
        return $this->hasManyThrough(
            \App\Models\Service::class,
            \App\Models\LeadPaymentDetail::class,
            'lead_payment_id', // Foreign key on lead_payment_details -> but we need LeadPayment relation, so we'll join via paymentDetails
            'id', // Local key on services
            'id', // Local key on vouchers
            'service_id' // Local key on lead_payment_details referencing service
        );
    }

    // alias for compatibility where code expects 'service' relation name
    public function service()
    {
        return $this->services();
    }

    // Get extra services from payment details
    public function getSelectedExtraServices()
    {
        return $this->extraServicePaymentDetails()->with('extraService')->get()->pluck('extraService');
    }

    public function registrationLink()
    {
        if (empty($this->registration_token)) return null;
        return route('voucher.register.form', ['voucher' => $this->id, 'token' => $this->registration_token]);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($voucher) {
            try {
                $voucher->passengers()->delete();
            } catch (\Throwable $e) {
            }

            try {
                // Iterate and delete each LeadVendorPayment model so its model events run
                foreach ($voucher->vendorPayments as $lvp) {
                    try {
                        $lvp->delete();
                    } catch (\Throwable $e) {
                    }
                }
            } catch (\Throwable $e) {
            }

            try {
                if ($voucher->payment) {
                    $voucher->payment()->delete();
                }
            } catch (\Throwable $e) {
            }

            try {
                if ($voucher->invoice) {
                    $voucher->invoice()->delete();
                }
            } catch (\Throwable $e) {
            }
        });
    }
}
