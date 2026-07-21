<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class LeadFollowup extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'parent_followup_id',
        'lead_id',
        'next_followup_date',
        'followup_note',
        'status',
        'followed_by',
        'file',
        'service_ids',
        'extra_service_ids',
        'total_amount',
        'discount_amount',
        'service_amount',
        'service_details',
        'received_amount',
        'payment_method',
        'paid_date',
    ];

    protected $casts = [
        'next_followup_date' => 'datetime',
        'service_ids' => 'array',
        'extra_service_ids' => 'array',
        'service_details' => 'array',
        'paid_date' => 'date',
    ];

// 0-initiated
// 1=active
// 2=canceled
// 3=Full payment received
// 4=Partial payment received
// 5=confirm/complete
// 6=pending
// 7=reschedule
// 8=approve
// 9=reject

    public function followedBy()
    {
        return $this->belongsTo(User::class, 'followed_by');
    }

    public function parentFollowup()
    {
        return $this->belongsTo(LeadFollowup::class, 'parent_followup_id');
    }

    public function childFollowups()
    {
        return $this->hasMany(LeadFollowup::class, 'parent_followup_id');
    }

    public function enquiry()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'lead_followup_id', 'service_ids');
    }

    public function extraServices()
    {
        return $this->belongsToMany(ExtraService::class, 'lead_followup_extra_services', 'lead_followup_id', 'extra_service_id');
    }

    public function paymentAuditTrail()
    {
        return $this->hasMany(PaymentAuditTrail::class, 'lead_followup_id');
    }

    public function refunds()
    {
        return $this->hasMany(\App\Models\LeadRefund::class, 'lead_followup_id');
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($followup) {
            try {
                // Delete child followups first to avoid orphan children
                try {
                    $children = $followup->childFollowups()->get();
                    if ($children && $children->count() > 0) {
                        foreach ($children as $child) {
                            try { $child->delete(); } catch (\Throwable $_) { Log::error('Failed to delete child followup', ['child_id' => $child->id, 'parent_id' => $followup->id]); }
                        }
                    }
                } catch (\Throwable $_) { Log::info('LeadFollowup deleting: failed to fetch childFollowups', ['followup_id' => $followup->id]); }

                // Log current audit count for debugging
                try {
                    $cnt = $followup->paymentAuditTrail()->count();
                    Log::info('LeadFollowup deleting: paymentAuditTrail count', ['followup_id' => $followup->id, 'count' => $cnt]);
                } catch (\Throwable $_) { Log::info('LeadFollowup deleting: failed to count paymentAuditTrail', ['followup_id' => $followup->id]); }

                // Attempt Eloquent delete first
                try {
                    $followup->paymentAuditTrail()->delete();
                } catch (\Throwable $e) {
                    Log::error('LeadFollowup deleting: eloquent delete paymentAuditTrail failed', ['followup_id' => $followup->id, 'error' => $e->getMessage()]);
                }

                // DB fallback deletion
                try {
                    DB::table('payment_audit_trail')->where('lead_followup_id', $followup->id)->delete();
                } catch (\Throwable $e) {
                    Log::error('LeadFollowup deleting: DB fallback delete failed', ['followup_id' => $followup->id, 'error' => $e->getMessage()]);
                }

            } catch (\Throwable $e) {
                Log::error('LeadFollowup deleting: unexpected error', ['followup_id' => $followup->id, 'error' => $e->getMessage()]);
            }

            try {
                $followup->refunds()->delete();
            } catch (\Throwable $e) {
                Log::error('LeadFollowup deleting: refunds delete failed', ['followup_id' => $followup->id, 'error' => $e->getMessage()]);
            }
        });
    }

    // Helper methods to get services and extra services from JSON
    public function getServicesAttribute()
    {
        if (!empty($this->service_ids)) {
            $serviceIds = is_string($this->service_ids) ? json_decode($this->service_ids, true) : $this->service_ids;
            return Service::whereIn('id', $serviceIds)->get();
        }
        return collect();
    }

    public function getExtraServicesAttribute()
    {
        if (!empty($this->extra_service_ids)) {
            $extraServiceIds = is_string($this->extra_service_ids) ? json_decode($this->extra_service_ids, true) : $this->extra_service_ids;
            return ExtraService::whereIn('id', $extraServiceIds)->get();
        }
        return collect();
    }

    public function getServiceIdsArrayAttribute()
{
    if (empty($this->service_ids)) {
        return [];
    }

    // Handle the escaped JSON string format
    $serviceIds = is_string($this->service_ids) ?
        json_decode(stripslashes($this->service_ids), true) :
        $this->service_ids;

    return is_array($serviceIds) ? $serviceIds : [];
}

public function getExtraServiceIdsArrayAttribute()
{
    if (empty($this->extra_service_ids)) {
        return [];
    }

    // Handle the escaped JSON string format
    $extraServiceIds = is_string($this->extra_service_ids) ?
        json_decode(stripslashes($this->extra_service_ids), true) :
        $this->extra_service_ids;

    return is_array($extraServiceIds) ? $extraServiceIds : [];
}

    // Scope for filtering by service date
    public function scopeByServiceDate($query, $date)
    {
        return $query->whereHas('enquiry.rideSegments', function ($q) use ($date) {
            $q->whereDate('from_date', $date);
        });
    }

    // Scope for filtering by service name/ID
    public function scopeByService($query, $serviceName)
    {
        return $query->where(function ($q) use ($serviceName) {
            // Handle both array and JSON string formats
            $q->where('service_ids', 'like', '%' . $serviceName . '%')
              ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
        });
    }

    // Scope for filtering by payment status
    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // Get service names as comma-separated string
    public function getServiceNamesAttribute()
    {
        $serviceIds = $this->service_ids_array;
        if (empty($serviceIds)) {
            return 'N/A';
        }

        $services = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
        return implode(', ', $services);
    }

    // Get extra service names as comma-separated string
    public function getExtraServiceNamesAttribute()
    {
        $extraServiceIds = $this->extra_service_ids_array;
        if (empty($extraServiceIds)) {
            return 'N/A';
        }

        $extraServices = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
        return implode(', ', $extraServices);
    }
}
