<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkyrackLeadCancellationRequest extends Model
{
    protected $fillable = [
        'integration', 'request_id', 'lead_id', 'actor_key', 'payload_hash',
        'state', 'http_code', 'response_json', 'completed_at',
    ];

    protected $casts = [
        'response_json' => 'array',
        'completed_at' => 'datetime',
    ];
}
