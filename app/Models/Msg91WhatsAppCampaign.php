<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppCampaign extends Model
{
    use UsesUuidPrimaryKey;
    protected $table = 'msg91_whatsapp_campaigns';
    protected $guarded = [];
    protected $casts = ['selection_snapshot' => 'array', 'cancelled_at' => 'datetime'];
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function sender() { return $this->belongsTo(Msg91WhatsAppSender::class, 'sender_id'); }
    public function template() { return $this->belongsTo(Msg91WhatsAppTemplate::class, 'template_id'); }
    public function recipients() { return $this->hasMany(Msg91WhatsAppCampaignRecipient::class, 'campaign_id'); }
}
