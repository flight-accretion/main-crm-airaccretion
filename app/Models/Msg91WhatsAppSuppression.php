<?php

namespace App\Models;

use App\Models\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class Msg91WhatsAppSuppression extends Model
{
    use UsesUuidPrimaryKey;
    protected $table = 'msg91_whatsapp_suppressions';
    protected $guarded = [];
    public function createdBy() { return $this->belongsTo(User::class, 'created_by'); }
}
