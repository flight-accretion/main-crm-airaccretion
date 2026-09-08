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
        'score_reason',
        'summary',
        'suggested_action',
        'score_change_reason',
        'actions_json',
        'next_commitment',
        'state_json',
        'input_hash',
        'model',
        'provider',
        'prompt_version',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'total_tokens',
        'processing_ms',
        'analysed_by',
        'attempt_count',
        'last_error',
        'processed_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'confidence' => 'integer',
        'summary' => 'array',
        'actions_json' => 'array',
        'state_json' => 'array',
        'input_tokens' => 'integer',
        'cached_input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'processing_ms' => 'integer',
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
