<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use App\Services\Accounts\AccountsHomeService;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class AccountsHomeTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('vouchers', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->timestamps(); });
        Schema::create('invoices', function ($t) { $t->uuid('id')->primary(); $t->uuid('voucher_id'); $t->integer('status'); $t->timestamps(); });
        Schema::create('lead_rides', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->timestamp('from_date')->nullable(); });
        Schema::create('lead_vendor_payments', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->decimal('total_vendor_service_amount')->default(0); $t->timestamps(); });
        Schema::create('vendor_payments', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_vendor_payment_id'); $t->decimal('paid_amount'); $t->date('paid_date')->nullable(); $t->integer('status')->default(1); });
        Schema::create('vendor_refunds', function ($t) { $t->uuid('id')->primary(); $t->uuid('lead_vendor_payment_id'); $t->decimal('cancellation_amount'); $t->decimal('refund_amount'); $t->timestamp('refund_date')->nullable(); $t->integer('status')->nullable(); $t->timestamps(); });
    }

    public function test_empty_dashboard_has_no_sample_values_or_removed_sections(): void
    {
        $actor = $this->createUserWithRole('Accounts viewer', UserType::ACCOUNTS_EXECUTIVE);
        $this->actingAs($actor);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $data = app(AccountsHomeService::class)->forMonth(now()->startOfMonth());
        $this->assertSame(0, $data['pending']['payments']);
        $this->assertNull($data['approval']['percent']);
        $this->assertCount(14, $data['chart']);
        $response = $this->get(route('admin.account.dashboard'))->assertOk();
        $html = $response->getContent();
        foreach (['Invoice pending', 'Invoices generated / finalized', 'Approvals within 10 minutes'] as $label) $response->assertSee($label);
        foreach (['GST info missing', 'Invoices blocked', 'Pending transfers to sales', 'QUICK LINKS', 'COMPLIANCE', 'Preview only', '91%'] as $label) $response->assertDontSee($label);
        $this->assertSame(8, substr_count($html, 'class="ah-tile '));
        if ($dir = getenv('CRM_ACCOUNTS_HOME_PREVIEW_DIR')) file_put_contents($dir.'/empty.html', $html);
    }

    public function test_real_money_sums_use_approved_receipts_and_actual_refunds(): void
    {
        $actor = $this->createUserWithRole('Finance', UserType::ACCOUNTS_MANAGER);
        $lead = $this->createLeadFor($actor);
        $this->receipt($lead, 1000, 1, now()->subMinutes(10));
        $this->receipt($lead, 900, 2, now()->subMinutes(30));
        $pending = $this->receipt($lead, 200, null, now()->subDays(4));
        DB::table('lead_refunds')->insert(['id' => Str::uuid(), 'lead_followup_id' => $pending, 'refund_amount' => 120, 'status' => 1, 'created_at' => now()]);
        DB::table('lead_refunds')->insert(['id' => Str::uuid(), 'lead_followup_id' => $pending, 'refund_amount' => 50, 'status' => 2, 'refund_date' => now()]);
        $payment = (string) Str::uuid();
        DB::table('lead_vendor_payments')->insert(['id' => $payment, 'lead_id' => $lead, 'total_vendor_service_amount' => 1000]);
        DB::table('vendor_payments')->insert(['id' => Str::uuid(), 'lead_vendor_payment_id' => $payment, 'paid_amount' => 300, 'paid_date' => now()->toDateString(), 'status' => 1]);
        DB::table('vendor_refunds')->insert(['id' => Str::uuid(), 'lead_vendor_payment_id' => $payment, 'cancellation_amount' => 100, 'refund_amount' => 20, 'refund_date' => now(), 'status' => 1]);
        $data = app(AccountsHomeService::class)->forMonth(now()->startOfMonth());
        $this->assertEquals(1000, $data['money']['collected']);
        $this->assertEquals(300, $data['money']['vendor_paid']);
        $this->assertEquals(300, $data['money']['exposure']);
        $this->assertEquals(670, $data['money']['net']);
        $this->assertSame(1, $data['pending']['payments']);
        $this->assertEquals(200, $data['pending']['payment_amount']);
        $this->assertSame(1, $data['pending']['vendor_refunds']);
        $this->assertEquals(1000, array_sum(array_column($data['chart'], 'amount')));
        $previous = app(AccountsHomeService::class)->forMonth(now()->subMonth()->startOfMonth());
        $this->assertEquals(0, $previous['money']['collected']);
        $this->assertEquals(0, $previous['money']['net']);
        $this->assertSame(1, $previous['pending']['payments'], 'Current queue backlog must not disappear when changing month');
        $this->actingAs($actor);
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $rendered = $this->get(route('admin.account.dashboard'))->assertOk();
        if ($dir = getenv('CRM_ACCOUNTS_HOME_PREVIEW_DIR')) {
            file_put_contents($dir.'/index.html', $rendered->getContent());
            file_put_contents($dir.'/previous.html', $this->get(route('admin.account.dashboard', ['month' => now()->subMonth()->format('Y-m')]))->getContent());
        }
    }

    public function test_ten_minute_approval_measures_from_submission_not_audit_creation(): void
    {
        $actor = $this->createUserWithRole('SLA', UserType::ACCOUNTS_EXECUTIVE);
        $lead = $this->createLeadFor($actor);
        $this->receipt($lead, 100, 1, now()->subMinutes(10));
        $this->receipt($lead, 100, 1, now()->subMinutes(10)->subSecond());
        $data = app(AccountsHomeService::class)->forMonth(now()->startOfMonth());
        $this->assertSame(50.0, $data['approval']['percent']);
        $this->assertSame(2, $data['approval']['count']);
    }

    public function test_invoice_pending_excludes_existing_drafts_and_gst_is_not_a_blocker(): void
    {
        $actor = $this->createUserWithRole('Invoices', UserType::ACCOUNTS_MANAGER);
        foreach ([null, 1, 2] as $status) {
            $lead = $this->createLeadFor($actor);
            DB::table('lead_followups')->insert(['id' => Str::uuid(), 'lead_id' => $lead, 'status' => 5, 'received_amount' => 100, 'created_at' => now()]);
            $voucher = (string) Str::uuid();
            DB::table('vouchers')->insert(['id' => $voucher, 'lead_id' => $lead]);
            if ($status) DB::table('invoices')->insert(['id' => Str::uuid(), 'voucher_id' => $voucher, 'status' => $status, 'created_at' => now()]);
        }
        $data = app(AccountsHomeService::class)->forMonth(now()->startOfMonth());
        $this->assertSame(1, $data['pending']['invoices']);
        $this->assertSame(2, $data['invoices']['generated']);
        $this->assertSame(1, $data['invoices']['finalized']);
    }

    public function test_dashboard_is_read_only_and_role_gated_and_month_validated(): void
    {
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        foreach (array_merge(UserType::ACCOUNTS_ROLES, UserType::ADMIN_ROLES, UserType::OPERATIONS_ROLES, [UserType::HR, UserType::SALES_EXECUTIVE]) as $role) {
            $actor = $this->createUserWithRole($role, $role);
            $allowed = in_array($role, array_merge(UserType::ACCOUNTS_ROLES, UserType::ADMIN_ROLES), true);
            $this->actingAs($actor)->get(route('admin.account.dashboard'))->assertStatus($allowed ? 200 : 403);
        }
        $this->actingAs($this->createUserWithRole('Validate', UserType::ACCOUNTS_EXECUTIVE));
        $this->getJson(route('admin.account.dashboard', ['month' => '2026-99']))->assertStatus(422);
        DB::enableQueryLog(); DB::flushQueryLog();
        app(AccountsHomeService::class)->forMonth(now());
        $writes = array_filter(DB::getQueryLog(), fn ($q) => preg_match('/^\s*(insert|update|delete|alter|create|drop)\b/i', $q['query']));
        DB::disableQueryLog();
        $this->assertSame([], array_values($writes));
    }

    public function test_vendor_overdue_balance_uses_final_cancellation_liability(): void
    {
        $actor = $this->createUserWithRole('Vendor accounts', UserType::ACCOUNTS_EXECUTIVE);
        $lead = $this->createLeadFor($actor);
        DB::table('lead_rides')->insert(['id' => Str::uuid(), 'lead_id' => $lead, 'from_date' => now()->subDay()]);
        $id = (string) Str::uuid();
        DB::table('lead_vendor_payments')->insert(['id' => $id, 'lead_id' => $lead, 'total_vendor_service_amount' => 1000]);
        DB::table('vendor_payments')->insert(['id' => Str::uuid(), 'lead_vendor_payment_id' => $id, 'paid_amount' => 200]);
        $data = app(AccountsHomeService::class)->forMonth(now());
        $this->assertSame(1, $data['pending']['vendor_overdue']);
        $this->assertEquals(800, $data['pending']['vendor_overdue_amount']);
        DB::table('vendor_refunds')->insert(['id' => Str::uuid(), 'lead_vendor_payment_id' => $id, 'cancellation_amount' => 200, 'refund_amount' => 0, 'status' => 1]);
        $data = app(AccountsHomeService::class)->forMonth(now());
        $this->assertSame(0, $data['pending']['vendor_overdue']);
        $this->assertEquals(0, $data['pending']['vendor_overdue_amount']);
        $this->assertSame(1, $data['pending']['vendor_refunds'], 'Zero-due manual settlement remains in its queue');
    }

    public function test_pending_payments_are_grouped_and_exceptional_matches_approved_money(): void
    {
        $actor = $this->createUserWithRole('Queues', UserType::ACCOUNTS_EXECUTIVE);
        $lead = $this->createLeadFor($actor);
        $this->receipt($lead, 10, null, now()->subDays(2));
        $this->receipt($lead, 20, null, now()->subDay());
        $data = app(AccountsHomeService::class)->forMonth(now());
        $this->assertSame(1, $data['pending']['payments']);
        $this->assertEquals(30, $data['pending']['payment_amount']);
        $this->receipt($lead, 5500, 1, now()->subMinute());
        $data = app(AccountsHomeService::class)->forMonth(now());
        $this->assertSame(1, $data['pending']['exceptional']);
        $this->assertEquals(500, $data['pending']['exceptional_amount']);
    }

    private function receipt(string $lead, int $amount, ?int $status, $created): string
    {
        $id = (string) Str::uuid();
        DB::table('lead_followups')->insert(['id' => $id, 'lead_id' => $lead, 'status' => 3, 'received_amount' => $amount, 'total_amount' => 5000, 'created_at' => $created]);
        if ($status) DB::table('payment_audit_trail')->insert(['id' => Str::uuid(), 'lead_followup_id' => $id, 'paid_amount' => $amount, 'payment_status' => $status, 'paid_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }
}
