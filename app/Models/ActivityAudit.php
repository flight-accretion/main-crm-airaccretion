<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityAudit extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'actor_id',
        'actor_name',
        'actor_role',
        'module',
        'action',
        'entity_id',
        'entity_type',
        'lead_id',
        'client_id',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];
}
