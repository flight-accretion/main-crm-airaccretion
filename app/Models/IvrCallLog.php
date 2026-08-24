<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrCallLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','provider_call_id','ivr_call_type_id','call_type_code','dni','cli','normalized_phone','raw_dtmf','agent_name','agent_number','dial_status','call_start_at','call_end_at','duration_sec','og_duration_sec','voice_url','lead_id','processing_status','processing_message','initial_followup_created_at','raw_payload'];
    protected $casts = ['call_start_at'=>'datetime','call_end_at'=>'datetime','initial_followup_created_at'=>'datetime','raw_payload'=>'array'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }

    public function callType() { return $this->belongsTo(IvrCallType::class, 'ivr_call_type_id'); }
    public function lead() { return $this->belongsTo(Lead::class, 'lead_id'); }
}
