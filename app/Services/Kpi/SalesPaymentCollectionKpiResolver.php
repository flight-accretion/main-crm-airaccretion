<?php

namespace App\Services\Kpi;

use App\Models\KpiMetric;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Carbon\Carbon;

class SalesPaymentCollectionKpiResolver implements KpiMetricResolverInterface
{
    public function resolve(
        User $user,
        KpiMetric $metric,
        Carbon $asOf,
        int $workingDaysPerMonth
    ): array {
        $monthStart = $asOf->copy()->startOfMonth();

        $leads = Lead::query()
            ->with([
                'leadFollowups.paymentAuditTrail',
            ])
            ->where('representative_user_id', $user->id)
            ->where(function ($query) use ($monthStart, $asOf) {
                $query
                    ->whereHas('leadFollowups', function ($followups) use ($monthStart, $asOf) {
                        $followups
                            ->whereIn('status', LeadFollowup::salesAmountStatuses())
                            ->whereBetween('created_at', [
                                $monthStart,
                                $asOf,
                            ]);
                    })
                    ->orWhereHas('leadFollowups.paymentAuditTrail', function ($payments) use ($monthStart, $asOf) {
                        $payments
                            ->where('payment_status', 1)
                            ->whereBetween('paid_date', [
                                $monthStart,
                                $asOf,
                            ]);
                    });
            })
            ->get();

        $eligible = 0;
        $full = 0;
        $partial = 0;
        $partialTracked = 0;
        $partialUntracked = 0;
        $unpaid = 0;
        $approvedTotal = 0.0;
        $pendingTotal = 0.0;

        foreach ($leads as $lead) {
            $qualifying = $lead->leadFollowups
                ->filter(fn ($followup) => in_array(
                    (int) $followup->status,
                    LeadFollowup::salesAmountStatuses(),
                    true
                ))
                ->sortByDesc('created_at');

            $latest = $qualifying->first();

            if (!$latest) {
                continue;
            }

            $bookedTotal = max(0, (float) ($latest->total_amount ?? 0));

            if ($bookedTotal <= 0) {
                continue;
            }

            $eligible++;

            $approvedPayments = $lead->leadFollowups
                ->flatMap(fn ($followup) => $followup->paymentAuditTrail)
                ->filter(fn ($payment) => (int) $payment->payment_status === 1);

            $received = (float) $approvedPayments->sum('paid_amount');
            $approvedTotal += $received;

            $pending = max(0, $bookedTotal - $received);
            $pendingTotal += $pending;

            if ($received + 0.01 >= $bookedTotal) {
                $full++;
                continue;
            }

            if ($received > 0) {
                $partial++;

                $lastPaymentAt = $approvedPayments
                    ->sortByDesc('paid_date')
                    ->first()?->paid_date;

                $hasScheduledNext = !empty($latest->next_followup_date)
                    && (
                        !$lastPaymentAt
                        || Carbon::parse($latest->next_followup_date)
                            ->greaterThanOrEqualTo(Carbon::parse($lastPaymentAt))
                    );

                $hasLaterFollowup = false;

                if ($lastPaymentAt) {
                    $hasLaterFollowup = $lead->leadFollowups
                        ->where('followed_by', $user->id)
                        ->filter(fn ($followup) => Carbon::parse($followup->created_at)
                            ->greaterThan(Carbon::parse($lastPaymentAt)))
                        ->isNotEmpty();
                }

                if ($hasScheduledNext || $hasLaterFollowup) {
                    $partialTracked++;
                } else {
                    $partialUntracked++;
                }

                continue;
            }

            $unpaid++;
        }

        $compliant = $full + $partialTracked;
        $achievement = $eligible > 0
            ? ($compliant / $eligible) * 100
            : 100.0;

        return [
            'actual_value' => $compliant,
            'target_value' => $eligible,
            'achievement_percent' => $achievement,
            'evidence' => [
                'eligible_payment_customers' => $eligible,
                'full_payment_count' => $full,
                'partial_payment_count' => $partial,
                'partial_with_followup_count' => $partialTracked,
                'partial_without_followup_count' => $partialUntracked,
                'unpaid_count' => $unpaid,
                'approved_received_total' => round($approvedTotal, 2),
                'pending_balance_total' => round($pendingTotal, 2),
                'achievement_percent' => round($achievement, 2),
                'note' => sprintf(
                    'Payments: Full=%d, Partial=%d (%d tracked / %d untracked), Unpaid=%d.',
                    $full,
                    $partial,
                    $partialTracked,
                    $partialUntracked,
                    $unpaid
                ),
            ],
        ];
    }
}
