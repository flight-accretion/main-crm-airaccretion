<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FollowUpStatus extends Model
{
    use HasFactory;

    protected $table = 'follow_up_status';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'title',
        'description',
        'status',
        'created_at'
    ];
}
