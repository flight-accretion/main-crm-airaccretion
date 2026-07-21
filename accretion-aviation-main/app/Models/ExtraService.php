<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtraService extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        // 'service_id',
        'extra_service_id', // Reference to original extra service
        'extra_service',
        'description',
        'extra_service_amount',
        'status'
    ];

    
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function vendors()
    {
        // Get vendors that have this extra service in their extra_service_ids
        return Vendor::where('status', 1)->get()->filter(function($vendor) {
            $extraServiceIds = $vendor->extra_service_ids ?? [];
            return in_array($this->id, $extraServiceIds);
        });
    }
}