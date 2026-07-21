<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Client extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'name',
        'email',
        'contact_number',
        'alternate_number',
        'country_id',
        'address',
        'description',
        'status',
        'created_by',
        'city_id',
        'date_of_birth'
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
    public function latestLead()
    {
        return $this->hasOne(Lead::class, 'client_id')->latest('id');
    }
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

     public function followups()
    {
        return $this->hasManyThrough(
            LeadFollowup::class,
            Lead::class,
            'client_id', // Foreign key on Lead table
            'lead_id', // Foreign key on LeadFollowup table
            'id', // Local key on Client table
            'id' // Local key on Lead table
        );
    }

}
