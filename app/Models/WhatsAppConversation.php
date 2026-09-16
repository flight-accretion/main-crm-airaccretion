<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'contact_id',
        'lead_id',
        'assigned_user_id',
        'whatcrm_chat_id',
        'status',
        'last_message',
        'last_message_at',
        'conversation_owner',
        'ai_state',
        'last_conversation_activity_at',
        'last_customer_message_at',
        'last_ai_message_at',
        'human_handoff_at',
        'handoff_reason',
        'handoff_priority',
        'human_summary',
        'activity_version',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'ai_state' => 'array',
        'last_conversation_activity_at' => 'datetime',
        'last_customer_message_at' => 'datetime',
        'last_ai_message_at' => 'datetime',
        'human_handoff_at' => 'datetime',
        'activity_version' => 'integer',
        'unread_count' => 'integer',
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

    public function contact()
    {
        return $this->belongsTo(
            WhatsAppContact::class,
            'contact_id'
        );
    }

    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }

    public function assignedUser()
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }

    public function messages()
    {
        return $this->hasMany(
            WhatsAppMessage::class,
            'conversation_id'
        );
    }
}
