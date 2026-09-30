<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatReaction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'message_id',

        'user_id',

        'reaction',
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


    public function user()
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }
}