<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatMessage extends Model
{
    protected $attributes = ['source' => 'crm', 'message_type' => 'text'];
    public const TYPE_TEXT =
        'text';

    public const TYPE_TASK =
        'task';

    public const TYPE_SYSTEM =
        'system';


    public $incrementing =
        false;

    protected $keyType =
        'string';


    protected $fillable = [

        'id',

        'conversation_id',

        'lead_id',

        'sender_user_id',

        'message_type',

        'body',

        'reply_to_message_id',

        'task_id',

        'is_pinned',

        'pinned_by',

        'pinned_at',

        'edited_at',

        'deleted_at',

        'source',
        'google_message_name',
        'google_sender_name',
        'google_create_time',
        'google_update_time',
        'google_sync_status',
        'google_sync_error',
        'google_sync_version',
        'google_event_version',
        'google_synced_version',
        'google_sync_attempted_at',
    ];


    protected $casts = [

        'is_pinned' =>
            'boolean',

        'pinned_at' =>
            'datetime',

        'edited_at' =>
            'datetime',

        'deleted_at' =>
            'datetime',

        'google_create_time' => 'datetime',
        'google_update_time' => 'datetime',
        'google_sync_attempted_at' => 'datetime',
        'google_sync_version' => 'integer',
        'google_synced_version' => 'integer',
    ];


    protected static function booted(): void
    {
        static::creating(
            function (self $model) {

                if (
                    empty(
                        $model->id
                    )
                ) {

                    $model->id =
                        (string)
                        Str::uuid();
                }
            }
        );
    }


    public function conversation()
    {
        return $this->belongsTo(
            LeadChatConversation::class,
            'conversation_id'
        );
    }


    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }


    public function sender()
    {
        return $this->belongsTo(
            User::class,
            'sender_user_id'
        );
    }


    public function replyTo()
    {
        return $this->belongsTo(
            self::class,
            'reply_to_message_id'
        );
    }


    public function task()
    {
        return $this->belongsTo(
            LeadChatTask::class,
            'task_id'
        );
    }


    public function attachments()
    {
        return $this->hasMany(
            LeadChatAttachment::class,
            'message_id'
        );
    }


    public function reactions()
    {
        return $this->hasMany(
            LeadChatReaction::class,
            'message_id'
        );
    }
}
