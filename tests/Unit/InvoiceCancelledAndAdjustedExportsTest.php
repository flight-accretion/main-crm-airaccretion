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
