<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppCampaignEvent extends Model
{
    use UsesUuidPrimaryKey;
    protected $table = 'msg91_whatsapp_campaign_events';
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'occurred_at' => 'datetime'];
}
