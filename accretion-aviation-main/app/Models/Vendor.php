<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends Model
{
    use HasFactory;

    protected $table = 'vendors';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'name',
        'email',
        'contact_number',
        'city_id',
        'address',
        'profile_image',
        'map_link',
        'status',
        'product_ids',
        'service_ids',
        'extra_service_ids',
        'bank_details',
    ];

    public $timestamps = true;

    protected $casts = [
        'city_id' => 'string',
        'product_ids' => 'array',
        'service_ids' => 'array',
        'extra_service_ids' => 'array',
    ];

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    // Relationship methods (return relationship instances)
    public function products()
    {
        return Product::whereIn('id', $this->product_ids ?? [])->get();
    }

    public function services()
    {
        return Service::whereIn('id', $this->service_ids ?? [])->get();
    }

    public function extraServices()
    {
        return ExtraService::whereIn('id', $this->extra_service_ids ?? [])->get();
    }

}
