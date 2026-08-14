<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppLeadIntegration extends Model
{

    protected $table = 'whatsapp_lead_integrations';
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'lead_id',
        'product_id',
        'phone',
        'external_id',
        'status',
        'assigned_user_id',
        'callback_sent',
        'callback_attempts',
        'callback_error',
        'payload',
        'assigned_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'callback_sent' => 'boolean',
        'callback_attempts' => 'integer',
        'assigned_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }

    public function product()
    {
        return $this->belongsTo(
            Product::class,
            'product_id'
        );
    }

    public function assignedUser()
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }
}