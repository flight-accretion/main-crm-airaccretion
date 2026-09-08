<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadAiScoringSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'enabled',
        'auto_analyse',
        'model',
        'prompt',
        'cold_max',
        'neutral_max',
        'created_by',
        'updated_by',
        'ai_model_profile_id',
        'ai_agent_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_analyse' => 'boolean',
        'cold_max' => 'integer',
        'neutral_max' => 'integer',
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

    public static function active(): self
    {
        $setting = static::query()
            ->orderBy('created_at')
            ->first();

        if ($setting) {
            return $setting;
        }

        return static::create([
            'enabled' => false,
            'auto_analyse' => true,
            'model' => null,
            'prompt' => static::defaultPrompt(),
            'cold_max' => 39,
            'neutral_max' => 69,
        ]);
    }

    public static function defaultPrompt(): string
    {
        return <<<'PROMPT'
You are the lead-intelligence assistant for Accretion Aviation.

Assess the customer's CURRENT buying intent from the information supplied by CRM.

Consider stronger buying signals such as:
- confirmed service requirement
- confirmed travel/service date
- confirmed passenger count
- asking for availability
- discussing or negotiating final price
- asking about payment
- asking how to confirm the booking
- explicitly saying they want to proceed

Consider weaker or negative signals such as:
- only general information gathering
- unclear dates or requirements
- indefinite postponement
- strong price objection with no continued engagement
- explicit lack of interest
- cancellation or withdrawal

The previous AI score is context only. It is NOT binding.

Increase, decrease, or retain the score according to the NEW evidence.

Never invent customer intent or facts that are not supported by CRM data or the new follow-up.
PROMPT;
    }

    public function temperatureFor(
        int $score
    ): string {
        if ($score <= 39) {
            return 'cold';
        }

        if ($score <= 69) {
            return 'neutral';
        }

        return 'hot';
    }

    public function aiModelProfile()
{
    return $this->belongsTo(
        AiModelProfile::class,
        'ai_model_profile_id'
    );
}

public function isReady(): bool
{
    if (!$this->enabled) {
        return false;
    }

    $agent =
        $this->aiAgent;

    return
        $agent
        &&
        $agent->isReady();
}

public function aiAgent()
{
    return $this->belongsTo(
        AiAgent::class,
        'ai_agent_id'
    );
}

}
