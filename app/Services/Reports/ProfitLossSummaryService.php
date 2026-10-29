<?php

namespace App\Services\Reports;

use App\Models\LeadFollowup;
use App\Models\LeadRefund;
use App\Models\PaymentAuditTrail;
use App\Services\Sales\SalesBookingCohortService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * One accounting definition of booking profitability.
 * Cash received can be greater than a booking's agreed value; that excess is
 * tracked separately and must never inflate earned revenue or profit.
 */
class ProfitLossSummaryService
{
    public function __construct(private SalesBookingCohortService $cohort)
    {
    }

    public function forFollowup(LeadFollowup $latest): array
    {
        $followupIds = LeadFollowup::query()
            ->where('lead_id', $latest->lead_id)
            ->pluck('id');

        $cashReceived = (float) PaymentAuditTrail::query()
            ->whereIn('lead_followup_id', $followupIds)
            ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->sum('paid_amount');

        // Some lightweight installations and tests do not include the optional
        // refund module yet. In that case there is no approved refund to apply.
        $customerRefunded = Schema::hasTable('lead_refunds')
            ? (float) LeadRefund::query()
                ->whereIn('lead_followup_id', $followupIds)
                ->whereIn('status', [1, 2])
                ->sum('refund_amount')
            : 0.0;

        $chargeableRevenue = max(0, (float) $latest->total_amount - $customerRefunded);
        $earnedRevenue = min($cashReceived, $chargeableRevenue);
        $overpayment = max(0, $cashReceived - $earnedRevenue);

        $vendorCost = 0.0;
        $vendorNames = [];
        foreach ($latest->enquiry?->leadVendorPayments ?? collect() as $vendorPayment) {
            $vendorCost += round((float) $vendorPayment->adjusted_vendor_payable, 2);
            if ($vendorPayment->vendor?->name) {
                $vendorNames[] = $vendorPayment->vendor->name;
            }
        }

        $profit = $earnedRevenue - $vendorCost;
        $margin = $earnedRevenue > 0 ? ($profit / $earnedRevenue) * 100 : 0.0;

        return [
            'has_approved_payment' => $cashReceived > 0,
            'cash_received' => round($cashReceived, 2),
            'customer_refunded' => round($customerRefunded, 2),
            'earned_revenue' => round($earnedRevenue, 2),
            'overpayment' => round($overpayment, 2),
            'vendor_cost' => round($vendorCost, 2),
            'profit' => round($profit, 2),
            'margin' => round($margin, 2),
            'vendor_names' => array_values(array_unique(array_filter($vendorNames))),
        ];
    }

    public function forPeriod(Carbon $from, Carbon $to, ?array $representativeIds = null): array
    {
        $leadIds = $this->cohort->leadIdsForPeriod($from, $to, $representativeIds);
        if ($leadIds->isEmpty()) {
            return $this->emptyResult();
        }

        $latestFollowups = LeadFollowup::query()
            ->with(['enquiry.leadVendorPayments.vendor'])
            ->whereIn('lead_id', $leadIds)
            ->whereIn('status', [2, 3, 4, 5, 6, 7, 8])
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lead_id')
            ->map(fn ($rows) => $rows->first())
            ->filter();

        $rows = $latestFollowups
            ->map(fn ($followup) => $this->forFollowup($followup))
            ->filter(fn ($row) => $row['has_approved_payment']);

        $revenue = (float) $rows->sum('earned_revenue');
        $vendorCost = (float) $rows->sum('vendor_cost');
        $profit = $revenue - $vendorCost;

        return [
            'revenue' => round($revenue, 2),
            'vendor_cost' => round($vendorCost, 2),
            'commission' => round($profit, 2),
            'margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0.0,
            'lead_count' => $rows->count(),
        ];
    }

    private function emptyResult(): array
    {
        return [
            'revenue' => 0.0,
            'vendor_cost' => 0.0,
            'commission' => 0.0,
            'margin' => 0.0,
            'lead_count' => 0,
        ];
    }
}
