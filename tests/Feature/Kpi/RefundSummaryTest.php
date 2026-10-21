<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\RefundApprovalController;
use App\Models\LeadRefund;
use App\Models\UserType;
use App\Models\VendorRefund;
use App\Services\Operations\RefundSummaryService;
use App\Support\RefundApproval;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RefundSummaryTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('vendor_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->uuid('lead_vendor_payment_id')->nullable(); $table->integer('status')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps();
        });
    }

    public function test_counts_follow_existing_statuses_and_average_uses_actual_completion_dates(): void
    {
        foreach ([0, 1, 1, 2] as $status) {
            DB::table('lead_refunds')->insert(['id' => (string) Str::uuid(), 'lead_followup_id' => (string) Str::uuid(), 'status' => $status]);
        }
        foreach ([null, 1, 2, 2] as $index => $status) {
            DB::table('vendor_refunds')->insert(['id' => (string) Str::uuid(), 'status' => $status,
                'created_at' => now()->subDays(8), 'completed_at' => $status === 2 ? now()->subDays($index === 2 ? 6 : 4) : null]);
        }
        $summary = app(RefundSummaryService::class)->summary();
        $this->assertSame(2, $summary['customer_pending']);
        $this->assertSame(2, $summary['vendor_pending']);
        $this->assertSame(null, $summary['waiting_approval']);
        $this->assertSame(3.0, $summary['average_days']);
        DB::table('lead_refunds')->where('status', 1)->update(['status' => 2]);
        $this->assertSame(0, app(RefundSummaryService::class)->summary()['customer_pending']);
    }

    public function test_no_completion_evidence_displays_no_average_instead_of_zero(): void
    {
        DB::table('vendor_refunds')->insert(['id' => (string) Str::uuid(), 'status' => 2, 'created_at' => now(), 'completed_at' => null]);
        $summary = app(RefundSummaryService::class)->summary();
        $this->assertSame(null, $summary['average_days']);
        $this->assertSame(0, $summary['customer_pending']);
        $this->assertSame(0, $summary['vendor_pending']);
    }

    private function migrateApproval(): void
    {
        (require base_path('database/migrations/2026_10_21_220000_add_approval_tracking_to_refunds.php'))->up();
    }

    private function approvalRequest(array $data): Request
    {
        $request = Request::create('/admin/refunds/approval', 'POST', $data, [], [], ['HTTP_ACCEPT' => 'application/json']);
        app()->instance('request', $request);
        return $request;
    }

    public function test_new_refunds_wait_for_approval_and_legacy_rows_are_not_backfilled(): void
    {
        $legacy = (string) Str::uuid();
        DB::table('lead_refunds')->insert(['id' => $legacy, 'lead_followup_id' => (string) Str::uuid(), 'status' => 1]);
        $this->migrateApproval();
        $customer = LeadRefund::create(['id' => (string) Str::uuid(), 'lead_followup_id' => (string) Str::uuid(), 'status' => 1]);
        $payment = (string) Str::uuid();
        VendorRefund::create(['lead_vendor_payment_id' => $payment, 'status' => VendorRefund::STATUS_PENDING]);
        VendorRefund::create(['lead_vendor_payment_id' => $payment, 'status' => VendorRefund::STATUS_PENDING]);
        VendorRefund::create(['lead_vendor_payment_id' => (string) Str::uuid(), 'status' => VendorRefund::STATUS_COMPLETED]);

        $this->assertSame(RefundApproval::PENDING, $customer->fresh()->approval_status);
        $this->assertSame(null, DB::table('lead_refunds')->where('id', $legacy)->value('approval_status'));
        // One open customer refund + one vendor payment (two open rows); the completed vendor refund is excluded.
        $this->assertSame(2, app(RefundSummaryService::class)->summary()['waiting_approval']);
    }

    public function test_only_accounts_super_admin_and_operations_can_decide_and_reject_needs_a_reason(): void
    {
        $this->migrateApproval();
        $refund = LeadRefund::create(['id' => (string) Str::uuid(), 'lead_followup_id' => (string) Str::uuid(), 'status' => 1]);
        $controller = app(RefundApprovalController::class);

        foreach ([UserType::SALES_EXECUTIVE, UserType::ADMIN, UserType::HR] as $role) {
            auth()->setUser($this->createUserWithRole('Denied '.$role, $role));
            try {
                $controller->customer($this->approvalRequest(['decision' => 'approve']), $refund->id);
                $this->fail($role.' should not decide refund approvals.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        auth()->setUser($this->createUserWithRole('Accounts', UserType::ACCOUNTS_EXECUTIVE));
        try {
            $controller->customer($this->approvalRequest(['decision' => 'reject']), $refund->id);
            $this->fail('Reject without a reason should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertSame(true, array_key_exists('note', $exception->errors()));
        }

        foreach ([UserType::ACCOUNTS_EXECUTIVE, UserType::OPERATIONS_EXECUTIVE, UserType::SUPER_ADMIN] as $index => $role) {
            $user = $this->createUserWithRole('Allowed '.$role, $role);
            auth()->setUser($user);
            $decision = $index === 1 ? 'reject' : 'approve';
            $controller->customer($this->approvalRequest(['decision' => $decision, 'note' => 'Checked']), $refund->id);
            $fresh = $refund->fresh();
            $this->assertSame($decision === 'approve' ? RefundApproval::APPROVED : RefundApproval::REJECTED, $fresh->approval_status);
            $this->assertSame($user->id, $fresh->approval_decided_by);
        }
        // Tracking only: the refund's own processing status is untouched.
        $this->assertSame(1, (int) $refund->fresh()->status);
        $this->assertSame(0, app(RefundSummaryService::class)->summary()['waiting_approval']);
    }

    public function test_vendor_aggregate_prefers_pending_then_rejected_then_approved(): void
    {
        $this->assertSame(RefundApproval::PENDING, RefundApproval::aggregate(['approved', 'pending', 'rejected']));
        $this->assertSame(RefundApproval::REJECTED, RefundApproval::aggregate(['approved', 'rejected', null]));
        $this->assertSame(RefundApproval::APPROVED, RefundApproval::aggregate(['approved']));
        $this->assertSame(null, RefundApproval::aggregate([null]));
    }

    public function test_summary_reports_unavailable_before_migration(): void
    {
        $this->assertSame(null, app(RefundSummaryService::class)->summary()['waiting_approval']);
        $refund = LeadRefund::create(['id' => (string) Str::uuid(), 'lead_followup_id' => (string) Str::uuid(), 'status' => 1]);
        $this->assertSame(1, (int) $refund->fresh()->status);
    }
}
