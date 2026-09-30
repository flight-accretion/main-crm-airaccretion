<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatRead extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'conversation_id',

        'user_id',

        'last_read_message_id',

        'last_read_at',
    ];


    protected $casts = [

        'last_read_at' =>
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
}