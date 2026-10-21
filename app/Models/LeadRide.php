<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeadRide extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
      protected $fillable = [
        'id',
        'lead_id',
        'from_date',
        'to_date',
        'from_place',
        'to_place',
        'service_address_id',
        'is_tba',
        'total_time',
        'booking_resource_key',
    ];

    protected $casts = [
        'from_date' => 'datetime',
        'to_date' => 'datetime',
    ];

    public function save(array $options = [])
    {
        if (!config('operations.booking_conflict_checks', false) || !$this->getAttribute('booking_resource_key') || (!$this->isDirty(['from_date', 'to_date', 'is_tba', 'booking_resource_key']) && $this->exists)) {
            return parent::save($options);
        }
        return app(\App\Services\Operations\BookingConflictService::class)
            ->protectSave($this, fn() => parent::save($options));
    }
     public function enquiry()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    public function serviceAddress()
    {
        return $this->belongsTo(ServiceAddress::class, 'service_address_id');
    }
    public function lead()
{
    return $this->belongsTo(Lead::class, 'lead_id');
}
}
