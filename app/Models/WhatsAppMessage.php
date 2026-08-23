<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'lead_followup_id',
        'provider_message_id',
        'direction',
        'sender_type',
        'sender_user_id',
        'message_type',
        'body',
        'provider_status',
        'message_at',
        'crm_read_at',
        'raw_payload',
    ];

    protected $casts = [
        'message_at' => 'datetime',
        'crm_read_at' => 'datetime',
        'raw_payload' => 'array',
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

    public function conversation()
    {
        return $this->belongsTo(
            WhatsAppConversation::class,
            'conversation_id'
        );
    }

    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_user_id'
        );
    }

    public function leadFollowup()
    {
        return $this->belongsTo(
            LeadFollowup::class,
            'lead_followup_id'
        );
    }
}
