<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiOutreachPool extends Model
{
    protected $table = 'kpi_outreach_pool';

    protected $fillable = [
        'normalized_phone',
        'canonical_client_id',
        'display_name',
        'cooling_until',
        'last_seen_at',
    ];

    protected $casts = [
        'cooling_until' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
