<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMaster extends Model
{
    protected $fillable = [
        'mobile_number',
        'email_id',
        'status',
        'contact_country_code',
        'country_id',
    ];
    use HasFactory;

    public function country()
    {
        return $this->belongsTo(\App\Models\Country::class, 'country_id');
    }
}
