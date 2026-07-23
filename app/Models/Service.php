<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'service',
        'description',
        'service_amount',
        'terms_and_conditions',
        'product_ids',
        'status'
    ];

    protected $casts = [
        'product_ids' => 'array',
    ];

    public function extraServices()
    {
        return $this->belongsToMany(ExtraService::class, 'service_extra_service', 'service_id', 'extra_service_id')
            ->withTimestamps();
    }

    public function vendors()
    {
        // Get vendors that have this service in their service_ids
        return Vendor::where('status', 1)->get()->filter(function($vendor) {
            $serviceIds = $vendor->service_ids ?? [];
            return in_array($this->id, $serviceIds);
        });
    }
    
    public function getProducts()
    {
        $productIds = $this->product_ids ?? [];
        $productIds = is_array($productIds) ? $productIds : json_decode($productIds, true) ?? [];
        $productIds = array_filter($productIds, function ($id) {
            return is_string($id) || is_numeric($id);
        });
        if (empty($productIds)) {
            return collect();
        }
        return Product::whereIn('id', $productIds)->get();
    }

    // Accessor for products attribute
    public function getProductsAttribute()
    {
        return $this->getProducts();
    }

    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
