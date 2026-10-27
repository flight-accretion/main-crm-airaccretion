<?php

namespace App\Services;

use App\Models\{LeadFollowup, LeadVendorPayment, PaymentAuditTrail, User, UserType, VendorRefund, Voucher};
use Illuminate\Support\Facades\Log;

class SidebarPendingCounts
{
    private array $counts = [];

    public function countFor(?User $user, string $queue): ?int
    {
        $role = $user?->userType?->user_type;
        $finance = array_merge(UserType::ADMIN_ROLES, UserType::ACCOUNTS_ROLES, UserType::OWNER_ROLES);
        $allowed = match ($queue) {
            'customer_refund' => array_merge($finance, UserType::OPERATIONS_ROLES),
            'vendor_refund' => array_merge(UserType::ADMIN_ROLES, UserType::OPERATIONS_ROLES, UserType::OWNER_ROLES),
            'account_vendor_refund', 'invoice' => $finance,
            'payment_approval' => array_merge($finance, [UserType::SENIOR_OPERATIONS_MANAGER, UserType::OPERATIONS_MANAGER]),
            default => [],
        };
        if (!in_array($role, $allowed, true)) return null;

        $key = $user->getKey().':'.$role.':'.$queue;
        if (!array_key_exists($key, $this->counts)) {
            try {
                $this->counts[$key] = $this->countQueue($queue);
            } catch (\Throwable $exception) {
                // A badge must not prevent access to the CRM if a count query fails.
                Log::warning('Sidebar pending count unavailable', ['queue' => $queue, 'error' => $exception->getMessage()]);
                $this->counts[$key] = null;
            }
        }
        return $this->counts[$key];
    }

    private function countQueue(string $queue): int
    {
        $pendingVendor = fn ($query) => $query->where(fn ($status) =>
            $status->whereNull('status')->orWhere('status', '!=', VendorRefund::STATUS_COMPLETED));

        return match ($queue) {
            // Refund Notes exposes notified, non-completed followup rows.
            'customer_refund' => LeadFollowup::whereHas('refunds', fn ($query) =>
                $query->where('status', '>=', 1)->where('status', '!=', 2))->count(),
            // Operations groups vendor refunds by vendor payment; Accounts lists individual refunds.
            'vendor_refund' => LeadVendorPayment::whereHas('vendorRefunds', $pendingVendor)->count(),
            'account_vendor_refund' => VendorRefund::where($pendingVendor)->count(),
            'payment_approval' => LeadFollowup::where('received_amount', '>', 0)
                ->whereIn('status', [LeadFollowup::STATUS_FULL_PAYMENT_RECEIVED, LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED])
                ->whereDoesntHave('paymentAuditTrail', fn ($query) => $query->whereIn('payment_status', PaymentAuditTrail::FINAL_REVIEW_STATUSES))
                ->distinct()->count('lead_id'),
            // Match invoice-list eligibility, but exclude drafts too: the requested count is not-yet-created invoices.
            'invoice' => Voucher::whereHas('lead.client')
                ->whereHas('lead.leadFollowups', fn ($query) => $query->where('received_amount', '>', 0))
                ->whereHas('lead.leadFollowups', fn ($query) => $query->where('status', LeadFollowup::STATUS_CONFIRMED)
                    ->whereRaw('lead_followups.created_at = (select max(latest_invoice_followups.created_at) from lead_followups as latest_invoice_followups where latest_invoice_followups.lead_id = lead_followups.lead_id)'))
                ->whereDoesntHave('invoice')->count(),
        };
    }
}
