<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Country extends Model
{
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;
    protected $casts = ['id' => 'string'];
    protected $fillable = [
        'id',
        'name',
        'status',
        'alpha_code',
        'symbol',
        'currency_code',
        'isd_code',
    ];

    public function states()
    {
        return $this->hasMany(State::class);
    }

    public function cities()
    {
        return $this->hasMany(City::class);
    }
     public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->id = $model->id ?: Str::uuid()->toString();
        });
    }
}
