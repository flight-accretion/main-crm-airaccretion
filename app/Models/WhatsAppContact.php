<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppContact extends Model
{
    protected $table = 'whatsapp_contacts';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'normalized_phone',
        'raw_phone',
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
        return $this->hasOne(
            WhatsAppConversation::class,
            'contact_id'
        );
    }
}
