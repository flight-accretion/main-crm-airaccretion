<?php

namespace App\Services\Rides;

use App\Models\CustomerRefundSettlement;
use App\Models\PaymentAuditTrail;
use Illuminate\Support\Collection;

class CustomerRefundWorkflowService
{
    public static function cents($number): int
    {
        return (int) round(((float) $number) * 100);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public function approvedPaidForFollowups(Collection $followupIds): int
    {
        if ($followupIds->isEmpty()) {
            return 0;
        }

        return self::cents(
            PaymentAuditTrail::whereIn('lead_followup_id', $followupIds->all())
                ->where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
                ->sum('paid_amount')
        );
    }

    public function snapshot(CustomerRefundSettlement $settlement): array
    {
        $settlement->loadMissing('transactions');
        $due = self::cents($settlement->refund_due);
        $paid = $settlement->transactions
            ->whereNull('reversed_at')
            ->sum(fn ($t) => self::cents($t->amount));
        $balance = max(0, $due - $paid);

        $label = $due === 0 ? 'No Refund Required'
            : ($balance === 0 ? 'Refunded'
                : ($paid > 0 ? 'Partially Refunded' : 'Refund Pending'));

        return [
            'id' => $settlement->id,
            'label' => $label,
            'tone' => $due === 0 ? 'neutral' : ($balance === 0 ? 'success' : 'warning'),
            'approved_paid' => $settlement->approved_paid,
            'cancellation_amount' => $settlement->cancellation_amount,
            'refund_due' => $settlement->refund_due,
            'already_refunded' => self::money($paid),
            'remaining' => self::money($balance),
            'reason' => $settlement->reason,
            'transactions' => $settlement->transactions->sortBy('created_at')->values()->map(fn ($t) => [
                'id' => $t->id,
                'amount' => $t->amount,
                'date' => $t->refund_date ? $t->refund_date->format('Y-m-d') : null,
                'method' => $t->refund_type,
                'remarks' => $t->remarks,
                'proof_present' => !empty($t->refund_proof),
                'reversed' => $t->reversed_at !== null,
                'reversal_reason' => $t->reversal_reason,
            ])->all(),
        ];
    }

    public function statusesForLeadIds(Collection $leadIds): array
    {
        $ids = $leadIds->filter()->unique()->values()->all();
        if (!$ids) return [];

        $settlements = CustomerRefundSettlement::with('transactions')
            ->whereIn('lead_id', $ids)->get();

        $result = [];
        foreach ($settlements as $settlement) {
            $snapshot = $this->snapshot($settlement);
            $result[(string) $settlement->lead_id] = [
                'label' => $snapshot['label'],
                'tone' => $snapshot['tone'],
                'due' => (float) $snapshot['remaining'],
            ];
        }
        return $result;
    }
}
