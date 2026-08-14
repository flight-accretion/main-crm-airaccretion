<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CallSummaryIntegration extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'call_fingerprint',

        'phone_number',
        'normalized_phone',

        'summary',
        'followup_date',

        'call_start_at',
        'call_end_at',

        'agent_name',
        'normalized_agent_name',

        'direction',
        'sentiment_score',

        'ivr_call_log_id',
        'lead_id',

        'agent_user_id',
        'followup_id',

        'match_score',
        'match_method',

        'status',

        'attempt_count',
        'last_error',

        'processed_at',

        'payload',
    ];


    protected $casts = [

        'payload' => 'array',

        'followup_date' => 'datetime',

        'call_start_at' => 'datetime',

        'call_end_at' => 'datetime',

        'processed_at' => 'datetime',

        'sentiment_score' => 'float',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {

            if (!$model->id) {

                $model->id =
                    (string) Str::uuid();
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


    public function ivrCallLog()
    {
        return $this->belongsTo(
            IvrCallLog::class,
            'ivr_call_log_id'
        );
    }


    public function agentUser()
    {
        return $this->belongsTo(
            User::class,
            'agent_user_id'
        );
    }


    public function followup()
    {
        return $this->belongsTo(
            LeadFollowup::class,
            'followup_id'
        );
    }
}