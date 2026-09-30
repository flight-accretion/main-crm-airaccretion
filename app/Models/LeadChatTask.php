<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadChatTask extends Model
{
    public const STATUS_ACTIVE =
        'active';

    public const STATUS_COMPLETED =
        'completed';


    public const PRIORITY_LOW =
        'low';

    public const PRIORITY_NORMAL =
        'normal';

    public const PRIORITY_HIGH =
        'high';

    public const PRIORITY_URGENT =
        'urgent';


    public $incrementing =
        false;

    protected $keyType =
        'string';


    protected $fillable = [

        'id',

        'conversation_id',

        'lead_id',

        'title',

        'description',

        'priority',

        'status',

        'assigned_role',

        'assigned_user_id',

        'due_at',

        'created_by',

        'completed_by',

        'completed_at',
    ];


    protected $casts = [

        'due_at' =>
            'datetime',

        'completed_at' =>
            'datetime',
    ];


    protected static function booted(): void
    {
        static::creating(
            function (self $model) {

                if (
                    empty(
                        $model->id
                    )
                ) {

                    $model->id =
                        (string)
                        Str::uuid();
                }
            }
        );
    }


    public function lead()
    {
        return $this->belongsTo(
            Lead::class,
            'lead_id'
        );
    }


    public function creator()
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }


    public function completedBy()
    {
        return $this->belongsTo(
            User::class,
            'completed_by'
        );
    }
}