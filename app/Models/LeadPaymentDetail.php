<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadPaymentDetail extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'lead_payment_id',
        'service_id',
        'amount',
        'is_extra_service',
        'status'
    ];

    protected $casts = [
        'is_extra_service' => 'boolean',
        'amount' => 'decimal:2'
    ];

    public function leadPayment()
    {
        return $this->belongsTo(LeadPayment::class, 'lead_payment_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class)->where('is_extra_service', false);
    }

    public function extraService()
    {
        return $this->belongsTo(ExtraService::class, 'service_id')->where('is_extra_service', true);
    }

    // Helper method to get the actual service/extra service
    public function getServiceAttribute()
    {
        if ($this->is_extra_service) {
            return ExtraService::find($this->service_id);
        } else {
            return Service::find($this->service_id);
        }
    }
}
