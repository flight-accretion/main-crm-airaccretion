<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadAiScore extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'followup_id',
        'previous_score_id',
        'status',
        'temperature',
        'score',
        'confidence',
        'summary',
        'suggested_action',
        'score_change_reason',
        'state_json',
        'input_hash',
        'model',
        'analysed_by',
        'attempt_count',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'confidence' => 'integer',
        'summary' => 'array',
        'state_json' => 'array',
        'attempt_count' => 'integer',
        'processed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
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

    public function followup()
    {
        return $this->belongsTo(
            LeadFollowup::class,
            'followup_id'
        );
    }

    public function previousScore()
    {
        return $this->belongsTo(
            self::class,
            'previous_score_id'
        );
    }
}