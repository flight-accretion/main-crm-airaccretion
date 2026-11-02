<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppSenderTemplateBinding extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'msg91_whatsapp_sender_template_bindings';
    protected $guarded = [];
    protected $casts = ['last_synced_at' => 'datetime', 'provider_meta' => 'array'];

    public function sender() { return $this->belongsTo(Msg91WhatsAppSender::class, 'sender_id'); }
    public function template() { return $this->belongsTo(Msg91WhatsAppTemplate::class, 'template_id'); }
    public function isApprovedFresh(): bool { return $this->provider_status === 'approved' && $this->last_synced_at && $this->last_synced_at->gte(now()->subMinutes((int) config('services.msg91_campaigns.template_stale_minutes', 20))); }
}
