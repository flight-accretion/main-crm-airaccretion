<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceRegenerationLog extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'invoice_id',
        'regenerated_by',
        'regenerated_at',
        'note',
    ];

    protected $casts = [
        'regenerated_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(
            Invoice::class,
            'invoice_id'
        );
    }

    public function regeneratedBy()
    {
        return $this->belongsTo(
            User::class,
            'regenerated_by'
        );
    }
}