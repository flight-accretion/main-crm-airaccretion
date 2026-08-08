<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrDtmfRule extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','ivr_call_type_id','dtmf_value','name','category','match_values','assignment_mode','is_default','is_active','sort_order','created_by','updated_by'];
    protected $casts = ['match_values' => 'array','is_default' => 'boolean','is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }

    public function callType() { return $this->belongsTo(IvrCallType::class, 'ivr_call_type_id'); }
    public function users() { return $this->hasMany(IvrDtmfRuleUser::class, 'ivr_dtmf_rule_id')->where('is_active', true); }
}
