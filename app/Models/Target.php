<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'sales_executive_id',
        'assigned_by',
        'year',
        'month',
        'target_amount',
        'achieved_amount',
        'description',
        'status'
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer'
    ];

    /**
     * Get the sales executive that owns the target
     */
    public function salesExecutive()
    {
        return $this->belongsTo(User::class, 'sales_executive_id');
    }

    /**
     * Get the user who assigned this target
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Get the month name
     */
    public function getMonthNameAttribute()
    {
        $months = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December'
        ];
        return $months[$this->month] ?? '';
    }

    /**
     * Scope to filter targets by year and month
     */
    public function scopeByPeriod($query, $year, $month = null)
    {
        $query->where('year', $year);
        if ($month) {
            $query->where('month', $month);
        }
        return $query;
    }

    /**
     * Scope to filter active targets
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Calculate and update achieved amount from approved payments
     */
    public function updateAchievedAmount()
    {
        // Achieved = Sum of Total Amount (latest followup per lead with payment in this month) - refunds
        // Find leads with approved payments in the target month/year (based on paid_date)
        $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $this->year)
            ->whereMonth('paid_date', $this->month)
            ->pluck('lead_followup_id')->unique();
        $paidLeadIds = \App\Models\LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($query) {
                $query->where('representative_user_id', $this->sales_executive_id);
            })
            ->pluck('lead_id')->unique();

        // Get ALL followups for those paid leads
        $allFollowups = \App\Models\LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($query) {
                $query->where('representative_user_id', $this->sales_executive_id);
            })
            ->get();

        // Calculate per-lead: Sales Amount = max(0, Total Amount - Refund Amount)
        // Filter to qualifying statuses FIRST, then take latest (matches Sales Report)
        $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) {
            $qualifying = $group->filter(function ($f) {
                return in_array($f->status, [2, 5, 7, 8]);
            });
            return $qualifying->sortByDesc('created_at')->first();
        })->filter()->sum(function ($f) {
            $allFollowupIdsForLead = \App\Models\LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
            $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->whereIn('status', [1, 2])->sum('refund_amount');
            return max(0, (float) $f->total_amount - $refund);
        });

        $this->update(['achieved_amount' => $achievedAmount]);
        return $achievedAmount;
    }
}
