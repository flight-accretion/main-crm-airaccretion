<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeadAllocationSetting extends Model
{
    use HasFactory;

    protected $table = 'lead_allocation_settings';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'office_start_time',
        'office_end_time',
        'popup_interval_minutes',
        'minimum_leads_before_popup',
        'auto_allocation_enabled',
        'allocation_method',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public static function getActiveSettings()
    {
        $settings = self::query()->first();

        if (!$settings) {
            return self::create([
                'office_start_time' => '10:30',
                'office_end_time' => '19:30',
                'popup_interval_minutes' => 120,
                'minimum_leads_before_popup' => 1,
                'auto_allocation_enabled' => true,
                'allocation_method' => 'balanced',
            ]);
        }

        if ((int) ($settings->popup_interval_minutes ?? 0) < 120) {
            $settings->popup_interval_minutes = 120;
            $settings->save();
        }

        return $settings;
    }
}
