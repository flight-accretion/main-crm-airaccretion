<?php

namespace App\Services\Accounts;

use App\Models\{Invoice, LeadFollowup, LeadRefund, LeadVendorPayment, PaymentAuditTrail, VendorPayment, VendorRefund, Voucher};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountsHomeService
{
    public function forMonth(Carbon $selected): array
    {
        $month = $selected->copy()->startOfMonth();
        $nextMonth = $month->copy()->addMonth();
        $pendingReceipts = LeadFollowup::where('received_amount', '>', 0)
            ->whereIn('status', [LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED, LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED])
            ->whereDoesntHave('paymentAuditTrail', fn ($q) => $q->whereIn('payment_status', PaymentAuditTrail::FINAL_REVIEW_STATUSES));
        $customer = LeadRefund::where('status', 1);
        $pending = [
            'payments' => (clone $pendingReceipts)->distinct()->count('lead_id'),
            'payment_amount' => (float) (clone $pendingReceipts)->sum('received_amount'),
            'payment_oldest' => (clone $pendingReceipts)->min('created_at'),
            'customer_refunds' => (clone $customer)->distinct()->count('lead_followup_id'),
            'customer_amount' => (float) (clone $customer)->sum('refund_amount'),
            'customer_oldest' => (clone $customer)->min('created_at'),
            'vendor_overdue' => 0, 'vendor_overdue_amount' => 0.0,
            'vendor_refunds' => 0, 'vendor_refund_amount' => 0.0,
        ];

        // Reuse cancellation-adjusted balances, not the old payment_status label.
        LeadVendorPayment::with(['vendorPayments', 'vendorRefunds', 'lead.rideSegments'])
            ->chunkById(200, function ($payments) use (&$pending) {
                foreach ($payments as $payment) {
                    $departure = $payment->lead?->rideSegments->whereNotNull('from_date')->min('from_date');
                    if ($departure && Carbon::parse($departure)->lt(today()) && $payment->vendor_payment_balance > 0) {
                        $pending['vendor_overdue']++;
                        $pending['vendor_overdue_amount'] += (float) $payment->vendor_payment_balance;
                    }
                    if ($payment->vendorRefunds->contains(fn ($r) => (int) $r->status !== VendorRefund::STATUS_COMPLETED)) {
                        $pending['vendor_refunds']++;
                        $pending['vendor_refund_amount'] += (float) $payment->vendor_refund_due;
                    }
                }
            });

        $pending['invoices'] = Voucher::whereHas('lead.client')
            ->whereHas('lead.leadFollowups', fn ($q) => $q->where('received_amount', '>', 0))
            ->whereHas('lead.leadFollowups', fn ($q) => $q->where('status', LeadFollowup::STATUS_CONFIRMED)
                ->whereRaw('lead_followups.created_at = (select max(last_invoice_status.created_at) from lead_followups as last_invoice_status where last_invoice_status.lead_id = lead_followups.lead_id)'))
            ->whereDoesntHave('invoice')->count();

        // Match the Exceptional page's approved-total preference and historical fallback.
        $exceptions = DB::table('lead_followups as f')->select('f.lead_id')
            ->selectRaw('SUM(COALESCE(f.received_amount, 0)) as submitted')
            ->selectRaw('(select amount.total_amount from lead_followups as amount where amount.lead_id = f.lead_id and amount.total_amount > 0 order by amount.created_at desc, amount.id desc limit 1) as total')
            ->selectRaw('(select SUM(a.paid_amount) from payment_audit_trail as a join lead_followups as paid_followup on paid_followup.id = a.lead_followup_id where paid_followup.lead_id = f.lead_id and a.payment_status = 1) as approved')
            ->groupBy('f.lead_id')->get()->filter(fn ($row) => (float) $row->total > 0 && ((float) $row->approved > 0 ? (float) $row->approved : (float) $row->submitted) > (float) $row->total);
        $pending['exceptional'] = $exceptions->count();
        $pending['exceptional_amount'] = $exceptions->sum(fn ($row) => ((float) $row->approved > 0 ? (float) $row->approved : (float) $row->submitted) - (float) $row->total);

        $invoices = [
            'generated' => Invoice::whereIn('status', [1, 2])->where('created_at', '>=', $month)->where('created_at', '<', $nextMonth)->count(),
            'finalized' => Invoice::where('status', 2)->where('created_at', '>=', $month)->where('created_at', '<', $nextMonth)->count(),
        ];
        $decisions = DB::table('payment_audit_trail as a')->join('lead_followups as f', 'f.id', '=', 'a.lead_followup_id')
            ->where('a.payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->where('a.updated_at', '>=', $month)->where('a.updated_at', '<', $nextMonth)
            ->whereNotNull('f.created_at')->whereColumn('a.updated_at', '>=', 'f.created_at')
            ->get(['f.created_at as submitted_at', 'a.updated_at as approved_at']);
        $seconds = $decisions->map(fn ($row) => Carbon::parse($row->submitted_at)->diffInSeconds(Carbon::parse($row->approved_at)));
        $approval = [
            'count' => $seconds->count(),
            'percent' => $seconds->isEmpty() ? null : round($seconds->filter(fn ($value) => $value <= 600)->count() / $seconds->count() * 100, 1),
            'average_minutes' => $seconds->isEmpty() ? null : round($seconds->avg() / 60, 1),
        ];
        $money = $this->cashForMonth($month);
        $previous = $this->cashForMonth($month->copy()->subMonth());
        $money['exposure'] = $pending['customer_amount'] + $pending['vendor_refund_amount'];
        $chartEnd = $month->isSameMonth(now()) ? today() : $month->copy()->endOfMonth()->startOfDay();
        $chartStart = $chartEnd->copy()->subDays(13);
        $daily = PaymentAuditTrail::where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->where('paid_date', '>=', $chartStart)->where('paid_date', '<', $chartEnd->copy()->addDay())
            ->selectRaw('DATE(paid_date) as day, SUM(paid_amount) as amount')->groupByRaw('DATE(paid_date)')->pluck('amount', 'day');
        $chart = [];
        for ($day = $chartStart->copy(); $day->lte($chartEnd); $day->addDay()) {
            $chart[] = ['date' => $day->toDateString(), 'label' => $day->format('j'), 'amount' => (float) ($daily[$day->toDateString()] ?? 0)];
        }
        return compact('month', 'pending', 'invoices', 'approval', 'money', 'previous', 'chart', 'chartStart', 'chartEnd');
    }

    private function cashForMonth(Carbon $month): array
    {
        $next = $month->copy()->addMonth();
        $collected = (float) PaymentAuditTrail::where('payment_status', PaymentAuditTrail::STATUS_APPROVED)
            ->where('paid_date', '>=', $month)->where('paid_date', '<', $next)->sum('paid_amount');
        $payments = VendorPayment::where('status', 1)->where('paid_date', '>=', $month->toDateString())->where('paid_date', '<', $next->toDateString());
        $vendorPaid = (float) (clone $payments)->sum('paid_amount');
        $customerRefunded = (float) LeadRefund::where('status', 2)->where('refund_date', '>=', $month)->where('refund_date', '<', $next)->sum('refund_amount');
        $vendorReceived = (float) VendorRefund::where('refund_amount', '>', 0)->where('refund_date', '>=', $month)->where('refund_date', '<', $next)->sum('refund_amount');
        return ['collected' => $collected, 'vendor_paid' => $vendorPaid, 'vendor_payments' => (clone $payments)->count(),
            'customer_refunded' => $customerRefunded, 'vendor_received' => $vendorReceived,
            'net' => $collected - $vendorPaid - $customerRefunded + $vendorReceived];
    }
}
