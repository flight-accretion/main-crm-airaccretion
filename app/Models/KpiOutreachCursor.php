<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiOutreachCursor extends Model
{
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'last_pool_id',
    ];

    protected $casts = [
        'last_pool_id' => 'integer',
    ];
}
