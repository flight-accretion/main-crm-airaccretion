<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lead_id',
        'operation_case_id',
        'ai_agent_id',
        'whatsapp_conversation_id',
        'customer_phone',
        'status',
        'sentiment',
        'intent',
        'customer_replied',
        'needs_human',
        'reminder_count',
        'initial_message_sent_at',
        'last_reminder_at',
        'next_reminder_at',
        'review_link_sent_at',
        'operations_notified_at',
        'completed_at',
        'ai_state',
    ];

    protected $casts = [
        'customer_replied' => 'boolean',
        'needs_human' => 'boolean',
        'initial_message_sent_at' => 'datetime',
        'last_reminder_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'review_link_sent_at' => 'datetime',
        'operations_notified_at' => 'datetime',
        'completed_at' => 'datetime',
        'ai_state' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }

    public function operationCase()
    {
        return $this->belongsTo(OperationCase::class, 'operation_case_id');
    }

    public function aiAgent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }

    public function whatsappConversation()
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }
}
