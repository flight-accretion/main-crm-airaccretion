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
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
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
