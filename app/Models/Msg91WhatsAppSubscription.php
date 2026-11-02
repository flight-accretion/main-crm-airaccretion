<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppSubscription extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'msg91_whatsapp_subscriptions';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'audit_meta' => 'array'];

    public function user() { return $this->belongsTo(User::class); }
    public function activatedBy() { return $this->belongsTo(User::class, 'activated_by'); }
    public function isActive(): bool { return $this->status === 'active' && (!$this->expires_at || $this->expires_at->isFuture()); }
}
