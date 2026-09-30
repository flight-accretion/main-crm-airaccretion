<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatNotification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'user_id',

        'lead_id',

        'conversation_id',

        'message_id',

        'task_id',

        'type',

        'title',

        'body',

        'read_at',

        'cleared_at',
    ];


    protected $casts = [

        'read_at' =>
            'datetime',

        'cleared_at' =>
            'datetime',
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
}