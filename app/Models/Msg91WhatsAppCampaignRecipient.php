<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppCampaignRecipient extends Model
{
    use UsesUuidPrimaryKey;
    protected $table = 'msg91_whatsapp_campaign_recipients';
    protected $guarded = [];
    protected $casts = ['rendered_variables' => 'array', 'accepted_at' => 'datetime', 'sent_at' => 'datetime', 'delivered_at' => 'datetime', 'read_at' => 'datetime', 'next_retry_at' => 'datetime'];
    public function campaign() { return $this->belongsTo(Msg91WhatsAppCampaign::class, 'campaign_id'); }
    public function lead() { return $this->belongsTo(Lead::class); }
    public function client() { return $this->belongsTo(Client::class); }
}
