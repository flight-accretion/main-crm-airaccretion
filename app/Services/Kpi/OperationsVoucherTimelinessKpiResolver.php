<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\User;
use Carbon\Carbon;

class OperationsVoucherTimelinessKpiResolver implements KpiMetricResolverInterface
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
        $targetMinutes = max(1, (int) ($metric->target_value ?: 15));

        $vouchers = $this->evidence->vouchersForUser(
            $user,
            $periodStart,
            $periodEnd
        );

        $confirmedAtByLead = $this->evidence->confirmedAtByLead(
            $vouchers->pluck('lead_id')
        );

        $eligible = 0;
        $withinTarget = 0;
        $sent = 0;
        $unsent = 0;
        $durations = [];

        foreach ($vouchers as $voucher) {
            $confirmedAtRaw = $confirmedAtByLead[(string) $voucher->lead_id] ?? null;

            if (!$confirmedAtRaw) {
                continue;
            }

            $eligible++;
            $confirmedAt = Carbon::parse($confirmedAtRaw);

            if ($voucher->customer_sent_at) {
                $finish = Carbon::parse($voucher->customer_sent_at);
                $sent++;
            } else {
                $finish = $periodEnd->copy();
                $unsent++;
            }

            $minutes = $finish->gte($confirmedAt)
                ? $confirmedAt->diffInSeconds($finish) / 60
                : 0;

            $durations[] = $minutes;

            if ($voucher->customer_sent_at && $minutes <= $targetMinutes) {
                $withinTarget++;
            }
        }

        $averageMinutes = count($durations) > 0
            ? array_sum($durations) / count($durations)
            : 0.0;

        return [
            'actual_value' => round($averageMinutes, 4),
            'target_value' => $targetMinutes,
            'achievement_percent' => $eligible > 0
                ? round(($withinTarget / $eligible) * 100, 4)
                : 0,
            'evidence' => [
                'eligible_vouchers' => $eligible,
                'sent_vouchers' => $sent,
                'unsent_vouchers' => $unsent,
                'within_target_minutes' => $withinTarget,
                'target_minutes' => $targetMinutes,
                'average_minutes' => round($averageMinutes, 2),
            ],
        ];
    }
}