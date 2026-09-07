<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AiModelProfile extends Model
{
    protected $table = 'ai_model_profiles';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'provider',
        'model',
        'api_key_encrypted',
        'enabled',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    protected $appends = [
        'api_key_status',
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

    public function setApiKey(
        ?string $apiKey
    ): void {
        $apiKey =
            trim(
                (string) $apiKey
            );

        if ($apiKey === '') {
            return;
        }

        $this->api_key_encrypted =
            Crypt::encryptString(
                $apiKey
            );
    }

    public function clearApiKey(): void
    {
        $this->api_key_encrypted =
            null;
    }

    public function apiKey(): ?string
    {
        if (
            empty(
                $this->api_key_encrypted
            )
        ) {
            return null;
        }

        try {
            return Crypt::decryptString(
                $this->api_key_encrypted
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getApiKeyStatusAttribute(): string
    {
        return $this->apiKey()
            ? 'configured'
            : 'missing';
    }

    public function isReady(): bool
    {
        return
            (bool) $this->enabled
            &&
            in_array(
                strtolower(
                    trim(
                        (string) $this->provider
                    )
                ),
                [
                    'openai',
                    'gemini',
                ],
                true
            )
            &&
            trim(
                (string) $this->model
            ) !== ''
            &&
            !empty(
                $this->apiKey()
            );
    }

    public function whatsappAgents()
    {
        return $this->hasMany(
            WhatsAppAiAgentSetting::class,
            'ai_model_profile_id'
        );
    }

    public function leadScoringAgents()
    {
        return $this->hasMany(
            LeadAiScoringSetting::class,
            'ai_model_profile_id'
        );
    }

    public function isInUse(): bool
    {
        return
            $this
                ->whatsappAgents()
                ->exists()
            ||
            $this
                ->leadScoringAgents()
                ->exists();
    }
}