<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrAgent extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','vi_agent_name','vi_agent_number','mapped_user_id','is_active','remarks','created_by','updated_by'];
    protected $casts = ['is_active' => 'boolean'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }

    public function mappedUser() { return $this->belongsTo(User::class, 'mapped_user_id'); }
}
