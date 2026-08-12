<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadTransfer extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'from_user_id',
        'to_user_id',
        'requested_by',
        'status',
        'reason',
        'response_note',
        'responded_at',
        'responded_by',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transfer) {
            if (empty($transfer->id)) {
                $transfer->id = (string) Str::uuid();
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

    public function fromUser()
    {
        return $this->belongsTo(
            User::class,
            'from_user_id'
        );
    }

    public function toUser()
    {
        return $this->belongsTo(
            User::class,
            'to_user_id'
        );
    }

    public function requestedBy()
    {
        return $this->belongsTo(
            User::class,
            'requested_by'
        );
    }

    public function respondedBy()
    {
        return $this->belongsTo(
            User::class,
            'responded_by'
        );
    }
}