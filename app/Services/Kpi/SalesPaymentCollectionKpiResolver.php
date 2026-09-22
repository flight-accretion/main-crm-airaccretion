<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;

class SalesPaymentCollectionKpiResolver implements KpiMetricResolverInterface
{
    private const FULL_PAYMENT = 3;
    private const PARTIAL_PAYMENT = 4;

    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth,
        ?Carbon $from = null
    ): array {
        $from = ($from ?: $asOf->copy()->startOfMonth())
            ->copy()
            ->startOfDay();

        $to = $asOf->copy()->endOfDay();

        /*
         * Payment Collection
         *
         * Eligible:
         * Latest payment-stage status inside selected period
         * is Partial Payment or Full Payment.
         *
         * Compliant:
         * Latest payment-stage status inside selected period
         * is Full Payment.
         *
         * One lead is counted once.
         */

        $leads = Lead::query()
            ->with([
                'leadFollowups' => function ($query) use ($from, $to) {
                    $query
                        ->whereIn('status', [
                            self::FULL_PAYMENT,
                            self::PARTIAL_PAYMENT,
                        ])
                        ->whereBetween('created_at', [
                            $from,
                            $to,
                        ])
                        ->orderByDesc('created_at');
                },
            ])
            ->where('representative_user_id', $user->id)
            ->whereHas('leadFollowups', function ($query) use ($from, $to) {
                $query
                    ->whereIn('status', [
                        self::FULL_PAYMENT,
                        self::PARTIAL_PAYMENT,
                    ])
                    ->whereBetween('created_at', [
                        $from,
                        $to,
                    ]);
            })
            ->get();

        $eligible = 0;
        $full = 0;
        $partial = 0;

        foreach ($leads as $lead) {
            /*
             * Relationship is already ordered newest first,
             * but sorting again keeps the calculation deterministic.
             */
            $latestPaymentStatus = $lead->leadFollowups
                ->sortByDesc('created_at')
                ->first();

            if (!$latestPaymentStatus) {
                continue;
            }

            $status = (int) $latestPaymentStatus->status;

            if (!in_array(
                $status,
                [
                    self::FULL_PAYMENT,
                    self::PARTIAL_PAYMENT,
                ],
                true
            )) {
                continue;
            }

            /*
             * Both Partial and Full are eligible.
             */
            $eligible++;

            if ($status === self::FULL_PAYMENT) {
                $full++;
            } else {
                $partial++;
            }
        }

        /*
         * Only Full Payment is compliant.
         */
        $compliant = $full;

        $achievement = $eligible > 0
            ? ($compliant / $eligible) * 100
            : 0.0;

        return [
            'actual_value' => $compliant,
            'target_value' => $eligible,
            'achievement_percent' => $achievement,

            'evidence' => [
                'eligible_payment_customers' => $eligible,
                'full_payment_count' => $full,
                'partial_payment_count' => $partial,

                /*
                 * Legacy keys retained so existing code
                 * reading the evidence array does not break.
                 */
                'partial_with_followup_count' => 0,
                'partial_without_followup_count' => $partial,
                'unpaid_count' => 0,
                'approved_received_total' => 0,
                'pending_balance_total' => 0,

                'achievement_percent' => round(
                    $achievement,
                    2
                ),

                'note' => sprintf(
                    'Payment Collection: Full=%d, Partial=%d, Eligible=%d.',
                    $full,
                    $partial,
                    $eligible
                ),
            ],
        ];
    }
}