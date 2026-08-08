<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrCallTypeUser extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','ivr_call_type_id','user_id','priority','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function callType() { return $this->belongsTo(IvrCallType::class, 'ivr_call_type_id'); }
}
