<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppTemplate extends Model
{
    use UsesUuidPrimaryKey;

    protected $table = 'msg91_whatsapp_templates';
    protected $guarded = [];
    protected $casts = ['crm_enabled' => 'boolean', 'components' => 'array', 'variable_schema' => 'array', 'last_synced_at' => 'datetime'];

    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function bindings() { return $this->hasMany(Msg91WhatsAppSenderTemplateBinding::class, 'template_id'); }
}
