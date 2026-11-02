<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppSender extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'msg91_whatsapp_senders';
    protected $guarded = [];
    protected $casts = ['verified_at' => 'datetime', 'verification_meta' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function bindings() { return $this->hasMany(Msg91WhatsAppSenderTemplateBinding::class, 'sender_id'); }
    public function isVerifiedActive(): bool { return $this->status === 'active' && $this->verified_at !== null; }
}
