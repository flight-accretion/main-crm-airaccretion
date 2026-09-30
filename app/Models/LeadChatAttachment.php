<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatAttachment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';


    protected $fillable = [

        'id',

        'message_id',

        'uploaded_by',

        'file_name',

        'disk',

        'path',

        'mime_type',

        'size_bytes',
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


    public function message()
    {
        return $this->belongsTo(
            LeadChatMessage::class,
            'message_id'
        );
    }
}