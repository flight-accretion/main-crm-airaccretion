<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'product',
        'vendor_id',
        'is_private',
        'is_airambulance',
        'status',
        'user_ids'
    ];

    protected $casts = [
        'user_ids' => 'array',
    ];

    public function leads()
    {
        return $this->belongsToMany(Lead::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
    
    public function services()
    {
        return Service::whereJsonContains('product_ids', $this->id)->get();
    }

    // Accessor for services attribute
    public function getServicesAttribute() // temporary fix for voucher page
    {
        return Service::whereJsonContains('product_ids', $this->id)->get();
    }

    public function getUserIdsArrayAttribute()
    {
        $userIds = $this->user_ids;
        if (is_string($userIds)) {
            $userIds = json_decode($userIds, true) ?? [];
        }

        return is_array($userIds) ? $userIds : [];
    }
}
