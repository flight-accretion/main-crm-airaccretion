<?php

namespace App\Services\Operations;

use App\Models\OperationCase;
use App\Models\UserType;
use App\Models\Voucher;
use Illuminate\Support\Facades\Storage;

class InvoiceVendorBillService
{
    public function forVoucher(Voucher $voucher): array
    {
        // Only bills belonging to vendor payments
        // on this particular voucher are included.
        $paymentIds = $voucher->vendorPayments()
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($paymentIds)) {
            return [];
        }

        $user = auth()->user();
        $role = optional($user?->userType)->user_type;

        $canViewBill = $role === UserType::SUPER_ADMIN
            || $role === UserType::OWNER
            || in_array(
                $role,
                UserType::OPERATIONS_ROLES,
                true
            )
            || in_array(
                $role,
                UserType::ACCOUNTS_ROLES,
                true
            );

        return OperationCase::query()
            ->where('lead_id', $voucher->lead_id)
            ->where(
                'type',
                OperationCase::TYPE_VENDOR_FOLLOWUP
            )
            ->where(
                'metadata->kind',
                VendorBillFollowupService::KIND
            )
            ->whereIn(
                'metadata->vendor_payment_id',
                $paymentIds
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (OperationCase $case) use ($canViewBill) {
                $meta = $case->metadata ?? [];

                $reimbursement = filter_var(
                    data_get(
                        $meta,
                        'reimbursment_from_customer',
                        false
                    ),
                    FILTER_VALIDATE_BOOLEAN
                );

                $path = data_get($meta, 'bill_path');

                $hasBill = is_string($path)
                    && str_starts_with($path, 'vendor-bills/')
                    && !str_contains($path, '..')
                    && Storage::disk('local')->exists($path);

                $completed = $case->completed_at !== null
                    && $case->status ===
                        OperationCase::STATUS_COMPLETED
                    && data_get($meta, 'resolution') ===
                        'bill_collected';

                if (!$completed) {
                    $status = 'awaiting';
                } elseif ($reimbursement) {
                    $status = 'reimbursement from customer';
                } elseif ($hasBill) {
                    $status = 'done';
                } else {
                    // Inconsistent legacy or missing-file record:
                    // never claim that a bill was received.
                    $status = 'awaiting';
                }

                $url = $hasBill && $canViewBill
                    ? route(
                        'admin.operations.vendor-followups.download-bill',
                        ['case' => $case->id]
                    )
                    : null;

                return [
                    'case_id' => (string) $case->id,
                    'ride_id' => (string) data_get(
                        $meta, 'ride_id', ''
                    ),
                    'vendor_payment_id' => (string) data_get(
                        $meta, 'vendor_payment_id', ''
                    ),
                    'vendor_name' => (string) data_get(
                        $meta,
                        'vendor_name_snapshot',
                        'Vendor'
                    ),
                    'status' => $status,
                    'has_bill' => $hasBill,
                    'preview_url' => $url
                        ? $url . '?preview=1'
                        : null,
                    'download_url' => $url,
                ];
            })
            ->values()
            ->all();
    }
}
