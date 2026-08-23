<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatCrmAgentMapping extends Model
{
    protected $table = 'whatcrm_agent_mappings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'whatcrm_agent_id',
        'whatcrm_agent_name',
        'crm_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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

    public function crmUser()
    {
        return $this->belongsTo(
            User::class,
            'crm_user_id'
        );
    }
}
