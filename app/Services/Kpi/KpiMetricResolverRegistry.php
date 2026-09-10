<?php

namespace App\Services\Kpi;

class KpiMetricResolverRegistry
{
    public function resolver(string $sourceKey): KpiMetricResolverInterface
    {
        return match ($sourceKey) {
            'sales_outreach' => app(SalesOutreachKpiResolver::class),
            'sales_conversion' => app(SalesLeadConversionKpiResolver::class),
            'sales_target' => app(SalesTargetKpiResolver::class),
            'sales_response_time' => app(SalesResponseTimeKpiResolver::class),
            'sales_followup_sla' => app(SalesFollowupSlaKpiResolver::class),
            'sales_payment_collection' => app(SalesPaymentCollectionKpiResolver::class),
            default => throw new \RuntimeException(
                "No KPI resolver registered for source_key: {$sourceKey}"
            ),
        };
    }
}
