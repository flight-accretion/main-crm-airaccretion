<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class State extends Model
{
    use HasFactory;

    protected $keyType = 'string';
public $incrementing = false;
protected $casts = ['id' => 'string'];
    protected $fillable = [
        'country_id',
        'name',
        'status',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
}
