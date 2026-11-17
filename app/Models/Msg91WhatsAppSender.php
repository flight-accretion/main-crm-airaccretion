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

    protected static function booted(): void
    {
        static::updating(function (self $sender) {
            if (!$sender->isDirty('integrated_number')) return;
            foreach ($sender->bindings()->get() as $binding) {
                $binding->forceFill(['provider_status' => 'stale', 'last_synced_at' => null,
                    'provider_meta' => array_merge($binding->provider_meta ?? [], ['previous_integrated_number' => $sender->getOriginal('integrated_number')])])->save();
            }
            $sender->verified_at = null;
            $sender->verified_by = null;
            $sender->verification_meta = ['source' => 'crm-number-changed', 'provider_status' => 'pending_verification'];
            $sender->template_sync_status = 'pending';
            $sender->templates_synced_at = null;
            $sender->template_sync_error = null;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function bindings()
    {
        return $this->hasMany(Msg91WhatsAppSenderTemplateBinding::class, 'sender_id');
    }
    public function isVerifiedActive(): bool
    {
        return $this->status === 'active'
            && filled($this->integrated_number)
            && $this->verified_at !== null
            && $this->verified_at->gte(
                now()->subMinutes((int) config('services.msg91_campaigns.sender_verification_ttl_minutes', 15))
            );
  }
}
