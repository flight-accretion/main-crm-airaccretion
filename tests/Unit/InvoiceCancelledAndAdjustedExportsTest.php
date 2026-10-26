<?php

namespace Tests\Unit;

use App\Exports\ProfitLossReportExport;
use App\Exports\VendorPaymentsExport;
use App\Exports\VendorReportExport;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ReportController;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\LeadVendorPayment;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorRefund;
use App\Models\Voucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

class InvoiceCancelledAndAdjustedExportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        (require base_path('database/migrations/2026_10_24_130000_add_finalized_date_to_invoices_table.php'))->up();
    }

    public function test_paid_cancelled_customer_ride_is_excluded_from_invoice_index(): void
    {
        $scenario = $this->createPaidCancelledScenario();

        $response = app(InvoiceController::class)
            ->index(Request::create('/admin/account/invoices', 'GET'));

        $this->assertInstanceOf(View::class, $response);

        $invoiceIds = $response
            ->getData()['invoicesData']
            ->pluck('id')
            ->all();

        $this->assertNotContains($scenario['voucher']->id, $invoiceIds);
    }

    public function test_vendor_payments_export_uses_refund_adjusted_vendor_amounts(): void
    {
        $this->createPaidCancelledScenario();

        $row = (new VendorPaymentsExport([]))
            ->collection()
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row['original_vendor_amount']);
        $this->assertSame(10000.0, (float) $row['vendor_service_cost']);
        $this->assertSame(50000.0, (float) $row['gross_paid_amount']);
        $this->assertSame(15000.0, (float) $row['refunded_amount']);
        $this->assertSame(35000.0, (float) $row['paid_amount']);
        $this->assertSame(25000.0, (float) $row['refund_due_amount']);
        $this->assertSame(0.0, (float) $row['balance_amount']);
        $this->assertSame('Full Paid', $row['status']);
    }

    public function test_invoice_finalization_saves_selected_date_and_is_idempotent(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        $controller = app(InvoiceController::class);
        $request = Request::create('/', 'POST', ['finalized_date' => '2026-10-12']);
        $this->assertTrue($controller->finalizeInvoice($request, $scenario['voucher']->id)->getData(true)['success']);
        $invoice = Invoice::where('voucher_id', $scenario['voucher']->id)->first();
        $this->assertSame('2026-10-12', $invoice->finalized_date?->toDateString());
        $this->assertSame(2, (int) $invoice->status);
        $controller->finalizeInvoice(Request::create('/', 'POST', ['finalized_date' => '2026-10-20']), $scenario['voucher']->id);
        $this->assertSame('2026-10-12', $invoice->fresh()->finalized_date?->toDateString());
        $this->assertSame(2, (int) $scenario['followup']->fresh()->status, 'Lead status must not change');
    }

    public function test_invalid_finalization_date_does_not_lock_invoice(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        $response = app(InvoiceController::class)->finalizeInvoice(Request::create('/', 'POST', ['finalized_date' => '2026-02-30']), $scenario['voucher']->id);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(1, (int) Invoice::where('voucher_id', $scenario['voucher']->id)->value('status'));
    }

    public function test_blank_gst_is_optional_and_finalized_invoice_remains_locked(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        $controller = app(InvoiceController::class);
        $request = Request::create('/', 'PUT', ['company_name' => '', 'gst_number' => '', 'billing_address' => '']);
        $this->assertTrue($controller->updateGstInfo($request, $scenario['voucher']->id)->getData(true)['success']);
        $this->assertEmpty(Invoice::where('voucher_id', $scenario['voucher']->id)->value('gst_number'));
        $controller->finalizeInvoice(Request::create('/', 'POST'), $scenario['voucher']->id);
        $this->assertSame(423, $controller->updateGstInfo($request, $scenario['voucher']->id)->getStatusCode());
    }

    public function test_generation_without_gst_preserves_payment_requirement_and_duplicate_transaction_balance(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        DB::table('invoices')->delete();
        DB::table('clients')->where('id', $scenario['client']->id)->update(['gst_number' => null, 'company_name' => null]);
        $controller = app(InvoiceController::class);
        $request = Request::create('/', 'POST', ['gst_number' => '', 'company_name' => '']);
        $this->assertTrue($controller->generateInvoice($request, $scenario['voucher']->id)->getData(true)['success']);
        $this->assertEmpty(Invoice::first()->gst_number);
        $level = DB::transactionLevel();
        $controller->generateInvoice($request, $scenario['voucher']->id);
        $this->assertSame($level, DB::transactionLevel(), 'Duplicate generation must not leak an open transaction');
        $this->assertSame(1, Invoice::count());
        DB::table('invoices')->delete();
        DB::table('payment_audit_trail')->update(['payment_status' => 2]);
        $this->assertSame(422, $controller->generateInvoice($request, $scenario['voucher']->id)->getStatusCode());
        $this->assertSame(0, Invoice::count());
    }

    public function test_invoice_created_and_finalize_dates_filter_before_pagination(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        DB::table('lead_followups')->where('id', $scenario['followup']->id)->update(['status' => 5]);
        DB::table('invoices')->where('voucher_id', $scenario['voucher']->id)->update(['created_at' => '2026-10-03 10:00:00', 'status' => 2, 'finalized_date' => '2026-10-12']);
        foreach ([['invoice_created_from' => '2026-10-03', 'invoice_created_to' => '2026-10-03'], ['finalized_from' => '2026-10-12', 'finalized_to' => '2026-10-12']] as $filters) {
            $response = app(InvoiceController::class)->index(Request::create('/', 'GET', $filters + ['status' => 'all']));
            $this->assertSame(1, $response->getData()['vouchers']->total());
        }
        $response = app(InvoiceController::class)->index(Request::create('/', 'GET', ['status' => 'all', 'finalized_to' => '2026-10-11']));
        $this->assertSame(0, $response->getData()['vouchers']->total());
    }

    public function test_invoice_redesign_renders_existing_data_and_optional_gst_without_state_column(): void
    {
        $this->actingAs($this->createAdminUser());
        $scenario = $this->createPaidCancelledScenario();
        DB::table('lead_followups')->where('id', $scenario['followup']->id)->update(['status' => 5]);
        foreach (['awaiting', 'finalized', 'legacy'] as $kind) {
            $voucher = (string) Str::uuid();
            DB::table('vouchers')->insert(['id' => $voucher, 'lead_id' => $scenario['lead']->id, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
            if ($kind !== 'awaiting') DB::table('invoices')->insert(['id' => (string) Str::uuid(), 'voucher_id' => $voucher, 'invoice_id' => 'INV-'.$kind, 'status' => 2, 'finalized_date' => $kind === 'finalized' ? '2026-10-12' : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $request = Request::create('/admin/account/invoices', 'GET', ['status' => 'all']);
        app()->instance('request', $request);
        $response = app(InvoiceController::class)->index($request);
        $html = $response->render();
        $this->assertStringContainsString('Invoice finalize date', $html);
        $this->assertStringContainsString('12 Oct 2026', $html);
        $this->assertStringContainsString('Not recorded', $html);
        $this->assertStringContainsString('+91-9876543210', $html);
        $this->assertStringNotContainsString('Blocked', $html);
        $this->assertStringNotContainsString('>State<', $html);
        $this->assertStringNotContainsString('ti-offcanvas-right', $html);
        $this->assertStringContainsString('ai-invoice-row', $html);
        $this->assertStringContainsString('name="invoice_created_from"', $html);
        $this->assertStringContainsString('name="finalized_from"', $html);
        $this->assertStringNotContainsString('@selected', $html);
        if ($directory = getenv('CRM_INVOICE_PREVIEW_DIR')) {
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/index.html', $html);
            $ajax = Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            app()->instance('request', $ajax);
            file_put_contents($directory.'/details.json', app(InvoiceController::class)->show($scenario['voucher']->id)->getContent());
            file_put_contents($directory.'/rows.json', $response->getData()['invoicesData']->toJson());
            DB::table('lead_followups')->update(['status' => 2]);
            app()->instance('request', $request);
            file_put_contents($directory.'/empty.html', app(InvoiceController::class)->index($request)->render());
        }
    }

    public function test_clearing_optional_gst_does_not_restore_the_clients_old_gst_in_the_queue(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        DB::table('lead_followups')->where('id', $scenario['followup']->id)->update(['status' => 5]);
        $controller = app(InvoiceController::class);
        $controller->updateGstInfo(Request::create('/', 'PUT', ['gst_number' => '', 'company_name' => '']), $scenario['voucher']->id);
        $row = $controller->index(Request::create('/', 'GET'))->getData()['invoicesData']->first();
        $this->assertSame('N/A', $row['invoice_gst_number']);
        $this->assertSame('N/A', $row['invoice_company_name']);
        $this->assertSame('GST-CANCEL-1', $scenario['client']->fresh()->gst_number, 'Client master must remain unchanged');
    }

    public function test_vendor_report_export_uses_refund_adjusted_vendor_amounts(): void
    {
        $this->createPaidCancelledScenario();

        $row = (new VendorReportExport([]))
            ->collection()
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row['original_vendor_amount']);
        $this->assertSame(10000.0, (float) $row['vendor_service_cost']);
        $this->assertSame(50000.0, (float) $row['gross_paid_amount']);
        $this->assertSame(15000.0, (float) $row['refunded_amount']);
        $this->assertSame(35000.0, (float) $row['paid_amount']);
        $this->assertSame(25000.0, (float) $row['refund_due_amount']);
        $this->assertSame(0.0, (float) $row['balance_amount']);
        $this->assertSame('Full Paid', $row['status']);
    }

    public function test_profit_loss_export_includes_paid_cancelled_ride_with_adjusted_vendor_amount(): void
    {
        $this->createPaidCancelledScenario();

        $row = (new ProfitLossReportExport([]))
            ->collection()
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row->client_received_amount);
        $this->assertSame(10000.0, (float) $row->vendor_amount);
        $this->assertSame(60000.0, (float) $row->profit_loss);
        $this->assertSame(85.71, round((float) $row->profit_loss_percent, 2));
    }

    public function test_vendor_report_screen_uses_refund_adjusted_vendor_amounts(): void
    {
        $this->actingAs($this->createAdminUser());
        $this->createPaidCancelledScenario();

        $response = app(ReportController::class)
            ->vendorReport(Request::create('/admin/report/vendor-payments', 'GET'));

        $this->assertInstanceOf(View::class, $response);

        $row = $response->getData()['rows']->first();

        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row->original_vendor_amount);
        $this->assertSame(10000.0, (float) $row->vendor_service_cost);
        $this->assertSame(50000.0, (float) $row->gross_paid_amount);
        $this->assertSame(15000.0, (float) $row->refunded_amount);
        $this->assertSame(35000.0, (float) $row->paid_amount);
        $this->assertSame(25000.0, (float) $row->refund_due_amount);
        $this->assertSame(0.0, (float) $row->balance_amount);
        $this->assertSame('Full Paid', $row->status);
    }

    public function test_vendor_report_filters_by_service_date_and_client_name_or_number(): void
    {
        $this->actingAs($this->createAdminUser());
        $scenario = $this->createPaidCancelledScenario();

        $response = app(ReportController::class)->vendorReport(Request::create('/admin/report/vendor-payments', 'GET', [
            'service_date_from' => '2026-09-15',
            'service_date_to' => '2026-09-15',
            'search' => '9876543210',
        ]));

        $this->assertInstanceOf(View::class, $response);
        $rows = $response->getData()['rows'];

        $this->assertCount(1, $rows);
        $this->assertSame($scenario['client']->name, $rows->first()->client_name);

        $emptyResponse = app(ReportController::class)->vendorReport(Request::create('/admin/report/vendor-payments', 'GET', [
            'service_date_from' => '2026-09-16',
            'service_date_to' => '2026-09-16',
        ]));

        $this->assertCount(0, $emptyResponse->getData()['rows']);
    }

    public function test_profit_loss_report_screen_includes_paid_cancelled_ride_with_adjusted_vendor_amount(): void
    {
        $this->actingAs($this->createAdminUser());
        $this->createPaidCancelledScenario();

        $response = app(ReportController::class)
            ->profitLossReport(Request::create('/admin/report/profit-loss', 'GET'));

        $this->assertInstanceOf(View::class, $response);

        $row = $response->getData()['profitLossData']->first();

        $this->assertNotNull($row);
        $this->assertSame(70000.0, (float) $row->client_received_amount);
        $this->assertSame(10000.0, (float) $row->vendor_amount);
        $this->assertSame(60000.0, (float) $row->profit_loss);
        $this->assertSame(85.71, round((float) $row->profit_loss_percent, 2));
    }

    private function createPaidCancelledScenario(): array
    {
        $client = Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Cancelled Customer',
            'company_name' => 'Cancelled Customer Pvt Ltd',
            'gst_number' => 'GST-CANCEL-1',
            'email' => 'cancelled@example.test',
            'contact_number' => '+91-9876543210',
            'status' => 1,
        ]);

        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => null,
            'service_ids' => [],
            'product_ids' => [],
            'number_of_passengers' => 2,
        ]);

        $ride = LeadRide::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-09-15 10:00:00',
            'to_date' => '2026-09-15 11:00:00',
            'from_place' => 'Indore',
            'to_place' => 'Ujjain',
        ]);

        $followup = LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 2,
            'total_amount' => 70000,
            'received_amount' => 70000,
            'service_ids' => [],
            'extra_service_ids' => [],
            'service_details' => [],
            'created_at' => '2026-08-21 10:00:00',
            'updated_at' => '2026-08-21 10:00:00',
        ]);

        PaymentAuditTrail::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id,
            'paid_amount' => 70000,
            'paid_date' => '2026-08-21 10:30:00',
            'payment_method' => 'Bank Transfer',
            'narration' => 'Approved customer payment before cancellation.',
            'payment_status' => 1,
        ]);

        $voucher = Voucher::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 1,
            'created_at' => '2026-08-21 11:00:00',
            'updated_at' => '2026-08-21 11:00:00',
        ]);

        Invoice::forceCreate([
            'id' => (string) Str::uuid(),
            'invoice_id' => 'INV-TEST-CANCEL-1',
            'voucher_id' => $voucher->id,
            'company_name' => $client->company_name,
            'gst_number' => $client->gst_number,
            'billing_address' => 'Test billing address',
            'status' => 1,
        ]);

        $vendor = Vendor::create([
            'id' => (string) Str::uuid(),
            'name' => 'Cancelled Ride Vendor',
            'email' => 'cancelled-vendor@example.test',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $leadVendorPayment = LeadVendorPayment::create([
            'id' => (string) Str::uuid(),
            'voucher_id' => $voucher->id,
            'lead_id' => $lead->id,
            'vendor_id' => $vendor->id,
            'total_service_amount' => 70000,
            'total_vendor_service_amount' => 70000,
            'payment_status' => 'partial',
        ]);

        VendorPayment::create([
            'id' => (string) Str::uuid(),
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'payment_method' => 'Bank Transfer',
            'paid_amount' => 50000,
            'paid_date' => '2026-08-20',
            'narration' => 'Vendor advance payment.',
            'status' => 1,
        ]);

        VendorRefund::create([
            'lead_id' => $lead->id,
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'vendor_id' => $vendor->id,
            'ride_id' => $ride->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 15000,
            'refund_date' => '2026-08-21 12:00:00',
            'refund_type' => 'Bank Transfer',
            'refund_reason' => 'Partial vendor refund after cancellation.',
            'refund_proof' => 'vendor-refunds/test.pdf',
            'no_refund_required' => false,
        ]);

        return [
            'client' => $client,
            'lead' => $lead,
            'ride' => $ride,
            'followup' => $followup,
            'voucher' => $voucher,
            'vendor' => $vendor,
            'lead_vendor_payment' => $leadVendorPayment,
        ];
    }

    private function createAdminUser(): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => UserType::ADMIN,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Admin User',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->uuid('parent_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->uuid('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('country')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('country_id')->nullable();
            $table->string('city')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->uuid('country_id')->nullable();
            $table->uuid('city_id')->nullable();
            $table->text('address')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->string('total_time')->nullable();
            $table->boolean('is_tba')->default(false);
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->integer('followup_recording_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->timestamp('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('narration')->nullable();
            $table->integer('payment_status')->default(0);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('operation_team_user_id')->nullable();
            $table->text('extra_upload')->nullable();
            $table->text('naration')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('created_by')->nullable();
            $table->string('registration_token')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('invoice_id')->unique();
            $table->uuid('voucher_id');
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->text('billing_address')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->uuid('city_id')->nullable();
            $table->text('address')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->decimal('total_service_amount', 15, 2)->nullable();
            $table->decimal('total_vendor_service_amount', 15, 2)->nullable();
            $table->string('payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_vendor_payment_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id')->nullable();
            $table->uuid('service_id')->nullable();
            $table->uuid('extra_service_id')->nullable();
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->decimal('vendor_service_amount', 15, 2)->nullable();
            $table->boolean('is_extra_service')->default(false);
            $table->string('service_name')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->string('receipt')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('narration')->nullable();
            $table->integer('status')->default(0)->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_refunds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('lead_vendor_payment_id');
            $table->uuid('vendor_id');
            $table->uuid('ride_id')->nullable();
            $table->decimal('cancellation_amount', 15, 2)->default(0);
            $table->decimal('refund_amount', 15, 2)->default(0);
            $table->dateTime('refund_date')->nullable();
            $table->string('refund_type', 100)->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->boolean('no_refund_required')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->decimal('service_amount', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('extra_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('extra_service');
            $table->decimal('extra_service_amount', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('lead_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_payment_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_payment_id')->nullable();
            $table->uuid('service_id')->nullable();
            $table->uuid('extra_service_id')->nullable();
            $table->boolean('is_extra_service')->default(false);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('lead_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_executive_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('manager_id')->nullable();
            $table->uuid('sales_executive_id')->nullable();
            $table->timestamp('assigned_date')->nullable();
            $table->text('notes')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }
}
