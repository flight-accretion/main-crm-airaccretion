<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IvrSyncLog extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id','from_date','to_date','status','records_fetched','records_created','duplicate_calls','repeat_leads','errors','message','started_at','finished_at'];
    protected $casts = ['from_date'=>'date','to_date'=>'date','started_at'=>'datetime','finished_at'=>'datetime'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) { if (empty($model->id)) $model->id = (string) Str::uuid(); });
    }
}
