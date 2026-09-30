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
            'sales_attendance' => app(SalesAttendanceKpiResolver::class),

            'operations_service_timeliness' => app(OperationsServiceTimelinessKpiResolver::class),
            'operations_positive_review' => app(OperationsReviewCompletionKpiResolver::class),
            'operations_customer_media' => app(OperationsImageCollectionKpiResolver::class),
            'operations_payment_compliance' => app(OperationsPaymentComplianceKpiResolver::class),
            'operations_voucher_timeliness' => app(OperationsVoucherTimelinessKpiResolver::class),

            // Reuse the existing attendance engine. The data source is employee attendance,
            // not Sales-specific even though the original resolver class kept that name.
            'operations_attendance' => app(SalesAttendanceKpiResolver::class),

            default => throw new \RuntimeException(
                "No KPI resolver registered for source_key: {$sourceKey}"
            ),
        };
    }
}
