<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;

class SalesLeadConversionKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private SalesKpiLeadScopeService $leadScope
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $leadIds = $this->leadScope
            ->incoming($user, $asOf)
            ->pluck('id');

        $totalLeads = $leadIds->count();

        $bookedLeadIds = $totalLeads > 0
            ? LeadFollowup::query()
                ->whereIn('lead_id', $leadIds)
                ->whereHas('paymentAuditTrail', function ($query) {
                    $query
                        ->where('payment_status', 1)
                        ->where('paid_amount', '>', 0);
                })
                ->pluck('lead_id')
                ->unique()
            : collect();

        $booked = $bookedLeadIds->count();
        $conversion = $totalLeads > 0
            ? ($booked / $totalLeads) * 100
            : 0.0;

        return [
            'actual_value' => $booked,
            'target_value' => $totalLeads,
            'achievement_percent' => $conversion,
            'evidence' => [
                'total_leads' => $totalLeads,
                'booked_leads' => $booked,
                'conversion_rate' => round($conversion, 2),
                'note' => sprintf(
                    'Total Leads = %d, Booked = %d, Conversion = %.2f%%',
                    $totalLeads,
                    $booked,
                    $conversion
                ),
            ],
        ];
    }
}
