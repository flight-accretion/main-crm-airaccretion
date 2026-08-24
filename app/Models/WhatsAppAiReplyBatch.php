<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppAiReplyBatch extends Model
{
    protected $table = 'whatsapp_ai_reply_batches';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'conversation_id',
        'status',
        'process_after',
        'locked_at',
        'processed_at',
        'response_message_id',
        'assigned_user_id',
        'detected_product',
        'error',
        'message_ids',
    ];

    protected $casts = [
        'process_after' => 'datetime',
        'locked_at' => 'datetime',
        'processed_at' => 'datetime',
        'message_ids' => 'array',
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

    public function assignedUser()
    {
        return $this->belongsTo(
            User::class,
            'assigned_user_id'
        );
    }
}
