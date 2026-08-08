<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrCallType extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','code','name','category','assignment_mode','is_active','sort_order','created_by','updated_by'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }

    public function users() { return $this->hasMany(IvrCallTypeUser::class, 'ivr_call_type_id')->where('is_active', true); }
    public function dtmfRules() { return $this->hasMany(IvrDtmfRule::class, 'ivr_call_type_id'); }
}
