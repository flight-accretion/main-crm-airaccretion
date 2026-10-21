<?php

namespace App\Http\Controllers;

use App\Models\LeadRefund;
use App\Models\LeadVendorPayment;
use App\Models\VendorRefund;
use App\Services\ActivityAuditService;
use App\Support\RefundApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Approve/reject tracking for customer and vendor refunds. Decisions are recorded only;
 * the existing "Mark as done" / completion actions are unchanged and not gated.
 */
class RefundApprovalController extends Controller
{
    public function customer(Request $request, string $refundId)
    {
        $data = $this->validated($request, 'lead_refunds');
        $refund = LeadRefund::findOrFail($refundId);
        abort_if((int) $refund->status === 2, 422, 'Completed refunds cannot be re-approved.');

        $old = $refund->only(['approval_status', 'approval_note']);
        $refund->update($this->decision($data));
        app(ActivityAuditService::class)->record('customer_refund', 'approval_'.$refund->approval_status, $refund,
            $old, $refund->only(['approval_status', 'approval_note']), ['lead_followup_id' => $refund->lead_followup_id]);

        return $this->respond($request, $refund->approval_status);
    }

    public function vendor(Request $request, LeadVendorPayment $vendorPayment)
    {
        $data = $this->validated($request, 'vendor_refunds');
        $open = $vendorPayment->vendorRefunds()->where(function ($query) {
            $query->whereNull('status')->orWhere('status', '!=', VendorRefund::STATUS_COMPLETED);
        });
        abort_unless((clone $open)->exists(), 422, 'No open vendor refund to approve.');

        $old = ['approval_status' => RefundApproval::aggregate((clone $open)->pluck('approval_status'))];
        DB::transaction(fn () => (clone $open)->update($this->decision($data)));
        app(ActivityAuditService::class)->record('vendor_refund', 'approval_'.$this->status($data['decision']), $vendorPayment,
            $old, ['approval_status' => $this->status($data['decision']), 'approval_note' => $data['note'] ?? null],
            ['lead_id' => $vendorPayment->lead_id, 'lead_vendor_payment_id' => $vendorPayment->id]);

        return $this->respond($request, $this->status($data['decision']));
    }

    private function validated(Request $request, string $table): array
    {
        abort_unless(RefundApproval::canDecide(Auth::user()), 403);
        abort_unless(RefundApproval::available($table), 503, 'Refund approval tracking is not migrated yet.');

        return $request->validate([
            'decision' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:1000|required_if:decision,reject',
        ]);
    }

    private function decision(array $data): array
    {
        return [
            'approval_status' => $this->status($data['decision']),
            'approval_note' => $data['note'] ?? null,
            'approval_decided_by' => Auth::id(),
            'approval_decided_at' => now(),
        ];
    }

    private function status(string $decision): string
    {
        return $decision === 'approve' ? RefundApproval::APPROVED : RefundApproval::REJECTED;
    }

    private function respond(Request $request, string $status)
    {
        $message = 'Refund '.($status === RefundApproval::APPROVED ? 'approved' : 'rejected').'.';
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message,
                'approval_status' => $status, 'approval_label' => RefundApproval::label($status)]);
        }
        return back()->with('success', $message);
    }
}
