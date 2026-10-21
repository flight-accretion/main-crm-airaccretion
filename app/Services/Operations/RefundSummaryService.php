<?php

namespace App\Services\Operations;

use App\Models\LeadRefund;
use App\Models\VendorRefund;
use App\Support\RefundApproval;

class RefundSummaryService
{
    public function summary(): array
    {
        $vendor = VendorRefund::query()->where(function ($query) {
            $query->whereNull('status')->orWhere('status', '!=', VendorRefund::STATUS_COMPLETED);
        });
        $completed = VendorRefund::query()->where('status', VendorRefund::STATUS_COMPLETED)
            ->whereNotNull('completed_at')->whereColumn('completed_at', '>=', 'created_at');
        $driver = $completed->getConnection()->getDriverName();
        $duration = $driver === 'pgsql' ? 'EXTRACT(EPOCH FROM (completed_at - created_at)) / 86400.0'
            : ($driver === 'sqlite' ? '(julianday(completed_at) - julianday(created_at))'
                : 'TIMESTAMPDIFF(SECOND, created_at, completed_at) / 86400.0');
        $average = $completed->selectRaw("AVG($duration) AS average_days")->value('average_days');

        $waiting = null;
        if (RefundApproval::available('lead_refunds') && RefundApproval::available('vendor_refunds')) {
            // Open refunds only; vendor refunds are counted per vendor payment, matching the list rows.
            $waiting = LeadRefund::where('status', 1)->where('approval_status', RefundApproval::PENDING)->count()
                + (clone $vendor)->where('approval_status', RefundApproval::PENDING)->distinct()->count('lead_vendor_payment_id');
        }

        return [
            'customer_pending' => LeadRefund::where('status', 1)->count(),
            'vendor_pending' => $vendor->count(),
            'waiting_approval' => $waiting,
            'average_days' => $average === null ? null : round((float) $average, 1),
        ];
    }
}
