<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model
{
    use HasFactory;

protected $keyType = 'string';
public $incrementing = false;
protected $casts = ['id' => 'string'];
    protected $fillable = [
        'id',
        'name',
        'lat',
        'lng',
        'country_id',
        'state_id',
        'timezone',
        'utc',
        'status',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }
}
