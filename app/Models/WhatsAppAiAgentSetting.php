<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WhatsAppAiAgentSetting extends Model
{
    protected $table = 'whatsapp_ai_agent_settings';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'enabled',
        'auto_reply_enabled',
        'provider',
        'model',
        'prompt',
        'api_key_encrypted',
        'buffer_seconds',
        'context_message_limit',
    ];

    protected $hidden = [
        'api_key_encrypted',
    ];

    protected $appends = [
        'api_key_status',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'auto_reply_enabled' => 'boolean',
        'buffer_seconds' => 'integer',
        'context_message_limit' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public static function active(): self
    {
        $defaults = [
            'enabled' => false,
            'auto_reply_enabled' => false,
            'provider' => 'openai',
            'model' => self::defaultModel(),
            'prompt' => self::defaultPrompt(),
            'buffer_seconds' => 10,
        ];

        if (Schema::hasColumn(
            (new self())->getTable(),
            'context_message_limit'
        )) {
            $defaults['context_message_limit'] =
                self::defaultContextMessageLimit();
        }

        $setting = self::query()->firstOrCreate(
            [],
            $defaults
        );

        if ($setting->provider !== 'openai') {
            $setting->forceFill([
                'provider' => 'openai',
                'model' => self::defaultModel(),
            ])->save();
        }

        return $setting;
    }

    public static function defaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    public static function defaultPrompt(): string
    {
        return implode(PHP_EOL, [
            'You are the Accretion Aviation WhatsApp assistant.',
            'Reply briefly and helpfully.',
            'Detect the customer requested product from the conversation.',
            'Return JSON only with keys: reply, product.',
            'Use product as N/A when the product is unclear.',
        ]);
    }

    public static function defaultContextMessageLimit(): int
    {
        return 10000;
    }

    public function contextMessageLimit(): int
    {
        $limit = (int) (
            $this->context_message_limit
            ?: self::defaultContextMessageLimit()
        );

        return max(1, min(100000, $limit));
    }

    public function setApiKey(?string $apiKey): void
    {
        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            return;
        }

        $this->api_key_encrypted = Crypt::encryptString($apiKey);
    }

    public function clearApiKey(): void
    {
        $this->api_key_encrypted = null;
    }

    public function apiKey(): ?string
    {
        if (empty($this->api_key_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString(
                $this->api_key_encrypted
            );
        } catch (\Throwable $exception) {
            return null;
        }
    }

    public function isReady(): bool
    {
        return (bool) $this->enabled
            && (bool) $this->auto_reply_enabled
            && $this->provider === 'openai'
            && !empty($this->apiKey());
    }

    public function getApiKeyStatusAttribute(): string
    {
        return $this->apiKey() ? 'configured' : 'missing';
    }
}
