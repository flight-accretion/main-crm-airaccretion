<?php

namespace Tests\Feature\Kpi;

use App\Models\{LeadFollowup, UserType};
use App\Services\SidebarPendingCounts;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class SidebarPendingCountsTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['vouchers' => 'lead_id', 'invoices' => 'voucher_id', 'lead_vendor_payments' => 'lead_id'] as $name => $foreign) {
            Schema::create($name, function (Blueprint $table) use ($foreign) {
                $table->uuid('id')->primary();
                $table->uuid($foreign)->nullable();
                $table->integer('status')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('vendor_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id')->nullable();
            $table->integer('status')->nullable();
        });
    }

    private function followup(string $lead, int $status = 3, int $amount = 100, int $age = 1): string
    {
        $id = (string) Str::uuid();
        DB::table('lead_followups')->insert(['id' => $id, 'lead_id' => $lead, 'status' => $status,
            'received_amount' => $amount, 'created_at' => now()->subDays($age), 'updated_at' => now()]);
        return $id;
    }

    public function test_payment_badge_counts_pending_leads_not_followups_or_reviewed_payments(): void
    {
        $actor = $this->createUserWithRole('Reviewer', UserType::ACCOUNTS_EXECUTIVE);
        $lead = $this->createLeadFor($actor);
        $this->followup($lead);
        $this->followup($lead, 4);
        foreach ([1, 2] as $status) {
            $id = $this->followup($this->createLeadFor($actor));
            DB::table('payment_audit_trail')->insert(['id' => (string) Str::uuid(), 'lead_followup_id' => $id, 'payment_status' => $status]);
        }
        $this->followup($this->createLeadFor($actor), 3, 0);
        $this->followup($this->createLeadFor($actor), 1);
        $this->assertSame(1, app(SidebarPendingCounts::class)->countFor($actor, 'payment_approval'));
    }

    public function test_invoice_badge_excludes_existing_invoices_and_ineligible_vouchers(): void
    {
        $actor = $this->createUserWithRole('Accountant', UserType::ACCOUNTS_EXECUTIVE);
        foreach (['pending', 'draft', 'finalized', 'cancelled', 'unpaid', 'missing-client'] as $kind) {
            $lead = $this->createLeadFor($actor);
            $this->followup($lead, 3, $kind === 'unpaid' ? 0 : 100, 2);
            $this->followup($lead, $kind === 'cancelled' ? 2 : LeadFollowup::STATUS_CONFIRMED, 0, 0);
            $voucher = (string) Str::uuid();
            DB::table('vouchers')->insert(['id' => $voucher, 'lead_id' => $lead]);
            if (in_array($kind, ['draft', 'finalized'], true)) {
                DB::table('invoices')->insert(['id' => (string) Str::uuid(), 'voucher_id' => $voucher, 'status' => $kind === 'draft' ? 1 : 2]);
            }
            if ($kind === 'missing-client') DB::table('leads')->where('id', $lead)->update(['client_id' => null]);
        }
        $this->assertSame(1, app(SidebarPendingCounts::class)->countFor($actor, 'invoice'));
    }

    public function test_refund_badges_count_notified_customer_rows_and_pending_vendor_rows(): void
    {
        $actor = $this->createUserWithRole('Operations', UserType::OPERATIONS_MANAGER);
        $lead = $this->createLeadFor($actor);
        foreach ([0, 1, 2] as $status) {
            DB::table('lead_refunds')->insert(['id' => (string) Str::uuid(), 'lead_followup_id' => $this->followup($lead), 'status' => $status]);
        }
        $payment = (string) Str::uuid();
        DB::table('lead_vendor_payments')->insert(['id' => $payment, 'lead_id' => $lead]);
        foreach ([null, 1, 2] as $status) {
            DB::table('vendor_refunds')->insert(['id' => (string) Str::uuid(), 'lead_vendor_payment_id' => $payment, 'status' => $status]);
        }
        $service = app(SidebarPendingCounts::class);
        $this->assertSame(1, $service->countFor($actor, 'customer_refund'));
        $this->assertSame(1, $service->countFor($actor, 'vendor_refund'));
        $accounts = $this->createUserWithRole('Accounts', UserType::ACCOUNTS_MANAGER);
        $this->assertSame(2, $service->countFor($accounts, 'account_vendor_refund'));
    }

    public function test_hidden_modules_do_not_query_or_expose_counts(): void
    {
        $service = app(SidebarPendingCounts::class);
        foreach ([UserType::HR, UserType::SALES_EXECUTIVE, UserType::OPERATIONS_EXECUTIVE] as $role) {
            $actor = $this->createUserWithRole($role, $role);
            $actor->load('userType');
            DB::enableQueryLog(); DB::flushQueryLog();
            $this->assertNull($service->countFor($actor, 'invoice'));
            if ($role !== UserType::OPERATIONS_EXECUTIVE) $this->assertNull($service->countFor($actor, 'customer_refund'));
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
        }
    }

    public function test_counts_are_reused_within_a_request_but_not_between_requests(): void
    {
        $actor = $this->createUserWithRole('Accountant', UserType::ACCOUNTS_MANAGER);
        $actor->load('userType');
        $service = app(SidebarPendingCounts::class);
        $this->assertSame(0, $service->countFor($actor, 'payment_approval'));
        $this->followup($this->createLeadFor($actor));
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertSame(0, $service->countFor($actor, 'payment_approval'));
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(1, (new SidebarPendingCounts)->countFor($actor, 'payment_approval'));
    }

    public function test_zero_and_unauthorized_counts_are_not_rendered(): void
    {
        $actor = $this->createUserWithRole('Accountant', UserType::ACCOUNTS_MANAGER);
        $this->actingAs($actor);
        $this->assertStringNotContainsString('data-pending-queue', view('admin.layouts.partials.pending-count', ['queue' => 'invoice'])->render());
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $this->actingAs($sales);
        $this->assertStringNotContainsString('data-pending-queue', view('admin.layouts.partials.pending-count', ['queue' => 'payment_approval'])->render());
    }

    public function test_count_failure_does_not_block_page_rendering(): void
    {
        $actor = $this->createUserWithRole('Accountant', UserType::ACCOUNTS_MANAGER);
        $this->actingAs($actor);
        Schema::drop('invoices');
        $this->assertStringNotContainsString('data-pending-queue', view('admin.layouts.partials.pending-count', ['queue' => 'invoice'])->render());
    }
}
