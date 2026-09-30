<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class GoogleChatIdentity extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'google_user_name', 'user_id', 'verified_at'];
    protected $casts = ['verified_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $identity) {
            $identity->id = $identity->id ?: (string) Str::uuid();
        });
    }
}
