<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SalespersonAvailability extends Model
{
    use HasFactory;

    protected $table = 'salesperson_availability';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'state',
        'is_available',
        'is_opted_in',
        'last_popup_at',
        'last_response_at',
    ];

    protected $casts = [
        'is_available' => 'boolean',
        'is_opted_in' => 'boolean',
        'last_popup_at' => 'datetime',
        'last_response_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
