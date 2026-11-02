<?php

namespace App\Services\Rides;

use App\Models\LeadFollowup;
use App\Models\LeadRefund;
use App\Models\LeadVendorPayment;
use App\Models\PaymentAuditTrail;
use Illuminate\Support\Collection;

class RideRefundStatusService
{
    public function forLeadIds(Collection $leadIds): array
    {
        $ids = $leadIds->filter()->unique()->values()->all();

        if (empty($ids)) {
            return [];
        }

        $followups = LeadFollowup::query()
            ->whereIn('lead_id', $ids)
            ->get(['id', 'lead_id']);

        $followupIds = $followups->pluck('id')->all();
        $followupLeadMap = $followups->pluck('lead_id', 'id');

        $refunds = empty($followupIds)
            ? collect()
            : LeadRefund::query()
                ->whereIn('lead_followup_id', $followupIds)
                ->get()
                ->groupBy(function ($refund) use ($followupLeadMap) {
                    return (string) $followupLeadMap->get(
                        $refund->lead_followup_id
                    );
                });

        $payments = empty($followupIds)
            ? collect()
            : PaymentAuditTrail::query()
                ->whereIn('lead_followup_id', $followupIds)
                ->where(
                    'payment_status',
                    PaymentAuditTrail::STATUS_APPROVED
                )
                ->get(['lead_followup_id', 'paid_amount'])
                ->groupBy(function ($payment) use ($followupLeadMap) {
                    return (string) $followupLeadMap->get(
                        $payment->lead_followup_id
                    );
                });

        $vendorPayments = LeadVendorPayment::query()
            ->with(['vendorPayments', 'vendorRefunds'])
            ->whereIn('lead_id', $ids)
            ->get()
            ->groupBy('lead_id');

        $result = [];

        foreach ($ids as $leadId) {
            $key = (string) $leadId;

            $approvedAmount = round(
                (float) $payments->get($key, collect())->sum('paid_amount'),
                2
            );

            $result[$key] = [
                'customer' => $this->customerStatus(
                    $refunds->get($key, collect()),
                    $approvedAmount
                ),

                'vendor' => $this->vendorStatus(
                    $vendorPayments->get($key, collect())
                ),
            ];
        }

        return $result;
    }

    private function display(
        string $label,
        string $tone = 'neutral',
        float $due = 0
    ): array {
        return [
            'label' => $label,
            'tone' => $tone,
            'due' => round(max(0, $due), 2),
        ];
    }

    private function customerStatus(
        Collection $refunds,
        float $approvedAmount
    ): array {
        if ($refunds->isEmpty()) {
            return $approvedAmount <= 0
                ? $this->display('No Payment Received')
                : $this->display('Assessment Pending', 'warning');
        }

        // Current customer workflow stores a single LeadRefund
        // per follow-up. Prefer the most recent record for display.
        $refund = $refunds->sortByDesc('created_at')->first();

        $amount = round((float) $refund->refund_amount, 2);
        $status = (int) $refund->status;

        if ($amount <= 0) {
            return $this->display('No Refund Required');
        }

        switch ($status) {
            case 0:
                return $this->display(
                    'Refund Pending',
                    'warning',
                    $amount
                );

            case 1:
                return $this->display(
                    'Refund Processing',
                    'info',
                    $amount
                );

            case 2:
                return $this->display('Refunded', 'success');

            case 3:
                return $this->display('Refund Cancelled');

            default:
                return $this->display(
                    'Assessment Pending',
                    'warning'
                );
        }
    }

    private function vendorStatus(Collection $payments): array
    {
        if ($payments->isEmpty()) {
            return $this->display('No Vendor Settlement');
        }

        $totalDue = 0;
        $totalRefunded = 0;
        $hasSettlement = false;
        $hasUncompletedRecord = false;

        foreach ($payments as $payment) {
            if ($payment->cancellation_amount === null) {
                continue;
            }

            $hasSettlement = true;

            // Existing LeadVendorPayment financial accessors.
            $totalDue += (float) $payment->vendor_refund_due;
            $totalRefunded += (float) $payment->total_refunded;

            foreach ($payment->vendorRefunds as $refund) {
                if (
                    (int) $refund->status !==
                    \App\Models\VendorRefund::STATUS_COMPLETED
                    &&
                    !$refund->no_refund_required
                ) {
                    $hasUncompletedRecord = true;
                }
            }
        }

        if (!$hasSettlement) {
            return $this->display('Assessment Pending', 'warning');
        }

        $totalDue = round($totalDue, 2);

        if ($totalDue > 0) {
            return $totalRefunded > 0
                ? $this->display(
                    'Partially Refunded',
                    'info',
                    $totalDue
                )
                : $this->display(
                    'Refund Pending',
                    'warning',
                    $totalDue
                );
        }

        if ($hasUncompletedRecord) {
            return $this->display('Refund Processing', 'info');
        }

        if ($totalRefunded > 0) {
            return $this->display('Refunded', 'success');
        }

        return $this->display('No Refund Required');
    }
}