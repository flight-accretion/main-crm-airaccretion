<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use Carbon\Carbon;

class OperationsPaymentComplianceKpiResolver implements KpiMetricResolverInterface
{
    public function __construct(
        private OperationsVoucherKpiEvidenceService $evidence
    ) {}

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth,
        ?Carbon $from = null
    ): array {
        $periodStart = ($from ?: $asOf->copy()->startOfMonth())
            ->copy()
            ->startOfDay();

        $periodEnd = $asOf->copy()->endOfDay();

        $vouchers = $this->evidence->vouchersForUser(
            $user,
            $periodStart,
            $periodEnd
        );

        $confirmedAtByLead = $this->evidence->confirmedAtByLead(
            $vouchers->pluck('lead_id')
        );

        $eligible = $vouchers->count();
        $compliant = 0;
        $missingPayment = 0;
        $voucherBeforePayment = 0;

        foreach ($vouchers as $voucher) {
            $confirmedAtRaw = $confirmedAtByLead[(string) $voucher->lead_id] ?? null;

            if (!$confirmedAtRaw) {
                $missingPayment++;
                continue;
            }

            $confirmedAt = Carbon::parse($confirmedAtRaw);
            $voucherCreatedAt = Carbon::parse($voucher->created_at);

            if ($confirmedAt->lte($voucherCreatedAt)) {
                $compliant++;
            } else {
                $voucherBeforePayment++;
            }
        }

        $achievement = $eligible > 0
            ? ($compliant / $eligible) * 100
            : 0.0;

        return [
            'actual_value' => round($achievement, 4),
            'target_value' => 100,
            'achievement_percent' => round($achievement, 4),
            'evidence' => [
                'eligible_vouchers' => $eligible,
                'payment_before_voucher_compliant' => $compliant,
                'voucher_before_confirmed_payment' => $voucherBeforePayment,
                'voucher_without_confirmed_payment' => $missingPayment,
            ],
        ];
    }
}
