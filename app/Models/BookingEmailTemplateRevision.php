<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BookingEmailTemplateRevision extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];
    protected $casts = ['version'=>'integer'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($revision) {
            if (!$revision->id) $revision->id = (string) Str::uuid();
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
