<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\SalesAmountCalculator;
use App\Services\Sales\SalesBookingCohortService;
use Carbon\Carbon;

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
        'status',
        'incentive_enabled',
        'incentive_multiplier',
        'incentive_team_multiplier',
        'incentive_basis',
        'incentive_product_ids',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'year' => 'integer',
        'month' => 'integer',
        'incentive_enabled' => 'boolean',
        'incentive_multiplier' => 'decimal:8',
        'incentive_team_multiplier' => 'decimal:8',
        'incentive_product_ids' => 'array',
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
    // 1. Resolve the selected target month.
    $from = \Carbon\Carbon::create(
        (int) $this->year,
        (int) $this->month,
        1
    )->startOfMonth();

    $to = $from->copy()->endOfMonth();

    // 2. Find bookings whose FIRST approved payment
    // falls within this target month.
    // Only include bookings owned by this employee.
    $paidLeadIds = app(SalesBookingCohortService::class)
        ->leadIdsForPeriod(
            $from,
            $to,
            [(string) $this->sales_executive_id]
        );

    // 3. Fetch all follow-ups for the qualifying bookings.
    $allFollowups = \App\Models\LeadFollowup::whereIn(
        'lead_id',
        $paidLeadIds
    )
        ->whereHas('enquiry', function ($query) {
            $query->where(
                'representative_user_id',
                $this->sales_executive_id
            );
        })
        ->get();

    // 4. Calculate achieved sales amount.
    // Use only the latest qualifying follow-up per lead.
    $achievedAmount = $allFollowups
        ->groupBy('lead_id')
        ->map(function ($group) {
            $qualifying = $group->filter(function ($followup) {
                return in_array(
                    (int) $followup->status,
                    \App\Models\LeadFollowup::salesAmountStatuses(),
                    true
                );
            });

            return $qualifying
                ->sortBy([
                    ['created_at', 'desc'],
                    ['id', 'desc'],
                ])
                ->first();
        })
        ->filter()
        ->sum(function ($followup) {

            // Collect all follow-up IDs for this booking.
            $allFollowupIdsForLead = \App\Models\LeadFollowup::where(
                'lead_id',
                $followup->lead_id
            )->pluck('id');

            // Deduct applicable processed/completed refunds.
            $refund = \App\Models\LeadRefund::whereIn(
                'lead_followup_id',
                $allFollowupIdsForLead
            )
                ->whereIn('status', [1, 2])
                ->sum('refund_amount');

            // Reuse the existing CRM sales calculation.
            return SalesAmountCalculator::salesAmountForFollowup(
                $followup,
                (float) $refund
            );
        });

    // 5. Save the updated achieved amount.
    $this->update([
        'achieved_amount' => round((float) $achievedAmount, 2),
    ]);

    return round((float) $achievedAmount, 2);
}
}
