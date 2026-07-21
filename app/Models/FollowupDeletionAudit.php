<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowupDeletionAudit extends Model
{
    protected $table = 'followup_deletion_audits';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'followup_id',
        'lead_id',
        'deleted_by_user_id',
        'created_by_user_id',
        'deleted_at',
        'deletion_reason',
        'details'
    ];

    protected $casts = [
        'details' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function followup()
    {
        return $this->belongsTo(LeadFollowup::class, 'followup_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by_user_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
