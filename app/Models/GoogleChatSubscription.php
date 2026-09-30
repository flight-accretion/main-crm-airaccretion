<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoogleChatSubscription extends Model
{
    public $incrementing = false;

    protected $keyType =
        'string';


    protected $fillable = [

        'id',

        'google_name',

        'target_resource',

        'expire_time',

        'last_renewed_at',

        'status',

        'last_error',
    ];


    protected $casts = [

        'expire_time' =>
            'datetime',

        'last_renewed_at' =>
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