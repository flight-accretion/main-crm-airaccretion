<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'lead_id',

        'last_message_at',

        'google_space_name',
        'google_thread_name',
        'google_thread_key',
        'google_synced_at',
        'operations_user_id',
        'google_connection_status',
        'google_template_message_id',
        'google_reconciled_at',
    ];


    protected $casts = [

        'last_message_at' =>
            'datetime',
            'google_synced_at' => 'datetime',
            'google_reconciled_at' => 'datetime',
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


    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }


    public function messages()
    {
        return $this->hasMany(
            LeadChatMessage::class,
            'conversation_id'
        );
    }


    public function tasks()
    {
        return $this->hasMany(
            LeadChatTask::class,
            'conversation_id'
        );
    }
}
