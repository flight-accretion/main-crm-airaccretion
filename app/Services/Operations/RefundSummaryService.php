<?php

namespace App\Services\Operations;

use App\Models\LeadRefund;
use App\Models\VendorRefund;
use App\Support\RefundApproval;
use App\Models\LeadVendorPayment;

class RefundSummaryService
{
    
    /**
     * Customer refunds eligible to appear in Refund Notes.
     * Includes legacy notified refunds and two-step settlements.
     */
    public function visibleCustomerRefunds()
    {
        return LeadRefund::query()
            ->where(function ($query) {
                $query->where('status', '>=', 1);

                if (\Illuminate\Support\Facades\Schema::hasTable(
                    'customer_refund_settlements'
                )) {
                    $query->orWhereIn(
                        'id',
                        \App\Models\CustomerRefundSettlement::query()
                            ->select('lead_refund_id')
                    );
                }
            });
    }

    /**
     * Pending customer refunds from the same eligible set
     * used by the Customer Refunds listing.
     */
    public function pendingCustomerRefunds()
    {
        return $this->visibleCustomerRefunds()
            ->whereIn('status', [0, 1]);
    }

    /**
     * Open vendor refund payment groups.
     * One payment group is one vendor-table row.
     */
    public function pendingVendorPayments()
    {
        return LeadVendorPayment::query()
            ->whereHas('vendorRefunds', function ($query) {
                $query->where(function ($statusQuery) {
                    $statusQuery
                        ->whereNull('status')
                        ->orWhere(
                            'status',
                            '!=',
                            VendorRefund::STATUS_COMPLETED
                        );
                });
            });
    }
    public function summary(): array
    {
        $vendor = VendorRefund::query()->where(function ($query) {
            $query->whereNull('status')
                ->orWhere(
                    'status',
                    '!=',
                    VendorRefund::STATUS_COMPLETED
                );
        });

        $completed = VendorRefund::query()
            ->where('status', VendorRefund::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->whereColumn('completed_at', '>=', 'created_at');

        $driver = $completed->getConnection()->getDriverName();

        $duration = $driver === 'pgsql'
            ? 'EXTRACT(EPOCH FROM (completed_at - created_at)) / 86400.0'
            : (
                $driver === 'sqlite'
                    ? '(julianday(completed_at) - julianday(created_at))'
                    : 'TIMESTAMPDIFF(SECOND, created_at, completed_at) / 86400.0'
            );

        $average = $completed
            ->selectRaw("AVG($duration) AS average_days")
            ->value('average_days');

        $waiting = null;

        if (
            RefundApproval::available('lead_refunds')
            && RefundApproval::available('vendor_refunds')
        ) {
            // Count customer refunds awaiting approval.
          $customerWaiting = $this->pendingCustomerRefunds()
        ->where('approval_status', RefundApproval::PENDING)
        ->count();

            // Count vendor-payment groups awaiting approval,
            // not individual refund transactions.
            $vendorWaiting = (clone $vendor)
                ->where(
                    'approval_status',
                    RefundApproval::PENDING
                )
                ->distinct()
                ->count('lead_vendor_payment_id');

            $waiting = $customerWaiting + $vendorWaiting;
        }

        return [
            // Existing customer refund calculation.
            'customer_pending' => $this->pendingCustomerRefunds()->count(),

            // One row per vendor payment with an open refund.
            // Matches OperationsVendorRefundController::index().
           'vendor_pending' => $this->pendingVendorPayments()->count(),

            // Combined customer + vendor approval count.
            'waiting_approval' => $waiting,

            // Existing average refund processing duration.
            'average_days' => $average === null
                ? null
                : round((float) $average, 1),
        ];
    }
}
