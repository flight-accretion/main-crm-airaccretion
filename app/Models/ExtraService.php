<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraService extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    public const SCOPE_CUSTOMER = 'customer';
    public const SCOPE_VENDOR   = 'vendor';
    public const SCOPE_BOTH     = 'both';

    protected $fillable = [
        'id',
        'extra_service_id',
        'extra_service',
        'description',
        'extra_service_amount',
        'status',
        'usage_scope',
    ];

    public function service()
    {
        return $this->services();
    }

    public function services()
    {
        return $this->belongsToMany(
            Service::class,
            'service_extra_service',
            'extra_service_id',
            'service_id'
        )->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Customer extra-service master
    |--------------------------------------------------------------------------
    */

    public function scopeCustomerVisible($query)
    {
        return $query->whereIn(
            'usage_scope',
            [
                self::SCOPE_CUSTOMER,
                self::SCOPE_BOTH,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Vendor compatible master
    |--------------------------------------------------------------------------
    */

    public function scopeVendorVisible($query)
    {
        return $query->whereIn(
            'usage_scope',
            [
                self::SCOPE_VENDOR,
                self::SCOPE_BOTH,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NEW vendor-only services
    |--------------------------------------------------------------------------
    */

    public function scopeVendorOnly($query)
    {
        return $query->where(
            'usage_scope',
            self::SCOPE_VENDOR
        );
    }

    public function vendors()
    {
        return Vendor::where('status', 1)
            ->get()
            ->filter(function ($vendor) {

                $extraServiceIds =
                    $vendor->extra_service_ids ?? [];

                return in_array(
                    $this->id,
                    $extraServiceIds
                );
            });
    }
}