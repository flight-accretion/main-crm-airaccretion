<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiAgent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'agent_type',
        'ai_model_profile_id',
        'prompt',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (
                empty(
                    $model->id
                )
            ) {
                $model->id =
                    (string) Str::uuid();
            }
        });
    }


    public function modelProfile()
    {
        return $this->belongsTo(
            AiModelProfile::class,
            'ai_model_profile_id'
        );
    }


    public function whatsappSettings()
    {
        return $this->hasMany(
            WhatsAppAiAgentSetting::class,
            'ai_agent_id'
        );
    }


    public function leadScoringSettings()
    {
        return $this->hasMany(
            LeadAiScoringSetting::class,
            'ai_agent_id'
        );
    }


    public function isReady(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        $profile =
            $this->modelProfile;

        return
            $profile
            &&
            $profile->isReady();
    }


    public function isInUse(): bool
    {
        return
            $this
                ->whatsappSettings()
                ->exists()
            ||
            $this
                ->leadScoringSettings()
                ->exists();
    }
}