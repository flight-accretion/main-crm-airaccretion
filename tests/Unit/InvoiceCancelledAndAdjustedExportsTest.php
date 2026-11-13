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
use App\Models\LeadRefund;
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
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

class InvoiceCancelledAndAdjustedExportsTest extends TestCase
{
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $connection = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];
        $app->afterBootstrapping(LoadConfiguration::class, static function ($app) use ($connection): void {
            $app['config']->set('database.default', 'sqlite');
            $app['config']->set('database.connections', ['sqlite' => $connection]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createSchema();
        (require base_path('database/migrations/2026_10_24_130000_add_finalized_date_to_invoices_table.php'))->up();
    }

    public function test_empty_sales_report_download_contains_only_headings(): void
    {
        $export = new \App\Exports\SalesReportExport(['_allowed_representative_ids' => []]);
        $csv = \Maatwebsite\Excel\Facades\Excel::raw($export, 'Csv');
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));

        $this->assertCount(1, $lines);
        $this->assertSame($export->headings(), str_getcsv($lines[0]));
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

    public function test_invoice_preview_has_discounted_total_and_approved_payment_balance(): void
    {
        Schema::create('operation_cases', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_id');
            $table->string('type');
            $table->text('metadata')->nullable();
            $table->timestamps();
        });
        $this->actingAs($this->createAdminUser());
        $scenario = $this->createPaidCancelledScenario(8500);
        $scenario['followup']->update([
            'status' => LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
            'service_amount' => 18000,
            'discount_amount' => 1000,
            'total_amount' => 17000,
            'service_details' => [['original_amount' => 18000, 'discount_amount' => 1000, 'final_amount' => 17000]],
        ]);
        $controller = app(InvoiceController::class);
        $method = new \ReflectionMethod($controller, 'getComprehensiveInvoiceData');
        $data = $method->invoke($controller, $scenario['voucher']->fresh());
        $this->assertSame(17000.0, (float) $data['payment']['total_amount']);
        $this->assertSame(8500.0, (float) $data['payment']['received_amount']);
        $this->assertSame(8500.0, (float) $data['payment']['balance']);
        $preview = $controller->previewHTML($scenario['voucher']->id);
        $this->assertSame(200, $preview->getStatusCode(), $preview->getContent());
        $this->assertStringContainsString('17,000', $preview->getContent());
        $this->assertStringContainsString('8,500', $preview->getContent());
        $this->assertStringContainsString('Gross service amount:', $preview->getContent());
        $this->assertStringContainsString('18,000.00', $preview->getContent());
        $this->assertStringContainsString('1,000.00', $preview->getContent());
        $this->assertStringContainsString('Outstanding balance:', $preview->getContent());
        config()->set('dompdf.options.enable_remote', false);
        config()->set('dompdf.options.enable_font_subsetting', false);
        $beforePayments = DB::table('payment_audit_trail')->orderBy('id')->get()->toJson();
        $beforeFollowups = DB::table('lead_followups')->orderBy('id')->get()->toJson();
        $pdf = $controller->generatePDF($scenario['voucher']->id);
        $this->assertSame(200, $pdf->getStatusCode(), $pdf->getContent());
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('application/pdf', $pdf->headers->get('Content-Type'));
        $regenerated = $controller->generateInvoice(
            Request::create('/', 'POST'), $scenario['voucher']->id
        );
        $this->assertTrue($regenerated->getData(true)['success']);
        $after = $method->invoke($controller, $scenario['voucher']->fresh());
        $this->assertSame($data['payment']['total_amount'], $after['payment']['total_amount']);
        $this->assertSame($data['payment']['balance'], $after['payment']['balance']);
        $this->assertSame($beforePayments, DB::table('payment_audit_trail')->orderBy('id')->get()->toJson());
        $this->assertSame($beforeFollowups, DB::table('lead_followups')->orderBy('id')->get()->toJson());
    }

    public function test_http_operations_pricing_revision_is_denied_without_financial_writes(): void
    {
        config()->set('session.driver', 'array');
        $scenario = $this->createPaidCancelledScenario();
        $user = $this->createAdminUser();
        $user->userType()->update(['user_type' => UserType::OPERATIONS_MANAGER]);
        $scenario['lead']->forceFill(['representative_user_id' => $user->id])->save();
        $this->actingAs($user->fresh());
        $before = DB::table('lead_followups')->orderBy('id')->get()->toJson();
        $payments = DB::table('payment_audit_trail')->orderBy('id')->get()->toJson();
        $lead = DB::table('leads')->where('id', $scenario['lead']->id)->first();

        $this->postJson(route('admin.leads.follow-up.store', $scenario['lead']), [
            'status' => LeadFollowup::STATUS_SALES_FOLLOW_UP,
            'pricing_action' => 'revise',
            'total_amount' => 0,
            'notes' => 'Unauthorized pricing attempt',
        ])->assertForbidden();

        $this->assertSame($before, DB::table('lead_followups')->orderBy('id')->get()->toJson());
        $this->assertSame($payments, DB::table('payment_audit_trail')->orderBy('id')->get()->toJson());
        $this->assertEquals($lead, DB::table('leads')->where('id', $scenario['lead']->id)->first());
    }

    public function test_http_admin_cannot_reopen_a_completed_ride_with_a_followup(): void
    {
        config()->set('session.driver', 'array');
        Schema::create('service_addresses', function (Blueprint $table) {
            $table->string('id')->primary();
        });
        $this->actingAs($this->createAdminUser());
        $scenario = $this->createPaidCancelledScenario();
        $scenario['followup']->update(['status' => LeadFollowup::STATUS_RIDE_COMPLETED]);
        $before = DB::table('lead_followups')->orderBy('id')->get()->toJson();
        $response = $this->postJson(route('admin.leads.follow-up.store', $scenario['lead']), [
            'status' => LeadFollowup::STATUS_ACTIVE,
            'pricing_action' => 'preserve',
            'notes' => 'Attempt to reopen completed ride',
            'next_followup_date' => now()->addDay()->format('Y-m-d H:i'),
        ], ['X-Requested-With' => 'XMLHttpRequest']);
        $response->assertStatus(422)->assertJsonPath('message', 'A completed ride cannot change business status through a follow-up.');
        $this->assertSame($before, DB::table('lead_followups')->orderBy('id')->get()->toJson());
    }

    public function test_http_hr_cannot_submit_sales_pricing_revision(): void
    {
        config()->set('session.driver', 'array');
        $scenario = $this->createPaidCancelledScenario();
        $user = $this->createAdminUser();
        $user->userType()->update(['user_type' => UserType::HR]);
        $this->actingAs($user->fresh());
        $before = LeadFollowup::count();

        $this->postJson(route('admin.leads.follow-up.store', $scenario['lead']), [
            'status' => LeadFollowup::STATUS_SALES_FOLLOW_UP,
            'pricing_action' => 'revise',
            'total_amount' => 0,
        ])->assertForbidden();

        $this->assertSame($before, LeadFollowup::count());
    }

    public function test_hr_followup_queue_does_not_load_discarded_followup_collections(): void
    {
        Schema::create((new \App\Models\LeadAiScoringSetting())->getTable(), function (Blueprint $table) {
            $table->string('id')->primary();
            $table->boolean('enabled');
            $table->timestamps();
        });
        DB::table((new \App\Models\LeadAiScoringSetting())->getTable())->insert(['id' => 'test', 'enabled' => false]);
        Schema::create((new \App\Models\LeadAiScore())->getTable(), function (Blueprint $table) {
            $table->string('lead_id');
            $table->timestamps();
        });
        $scenario = $this->createPaidCancelledScenario();
        $user = $this->createAdminUser();
        $user->userType()->update(['user_type' => UserType::HR]);
        $this->actingAs($user->fresh());
        $scenario['followup']->update(['status' => LeadFollowup::STATUS_ACTIVE, 'next_followup_date' => '2026-11-12 09:00:00']);
        $template = (array) DB::table('lead_followups')->where('id', $scenario['followup']->id)->first();
        for ($index = 0; $index < 200; $index++) {
            DB::table('lead_followups')->insert(array_replace($template, ['id' => (string) Str::uuid()]));
        }
        DB::enableQueryLog();
        try {
            $response = app(\App\Http\Controllers\UpcomingFollowUpController::class)->index(
                Request::create('/admin/upcoming-follow-up', 'GET', ['from_date' => '2026-11-12'])
            );
            $this->assertCount(1, $response->getData()['arrFollowUps']);
            $reads = collect(DB::getQueryLog())->filter(fn ($entry) =>
                str_contains($entry['query'], 'from "lead_followups"')
            );
            $this->assertCount(4, $reads);
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_google_chat_reply_notifies_manager_owner_once(): void
    {
        Schema::create('lead_chat_notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['user_id', 'lead_id', 'conversation_id', 'message_id', 'type', 'title', 'body'] as $column) {
                $table->string($column);
            }
            $table->timestamps();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
        });
        $manager = $this->createAdminUser();
        $manager->userType()->update(['user_type' => UserType::SALES_MANAGER]);
        $lead = new Lead();
        $lead->forceFill(['id' => 'test-lead', 'representative_user_id' => $manager->id]);
        $conversation = new \App\Models\LeadChatConversation();
        $conversation->forceFill(['id' => 'test-conversation']);
        $message = new \App\Models\LeadChatMessage();
        $message->forceFill(['id' => 'test-message', 'body' => 'Test reply']);
        $service = app(\App\Services\LeadChat\LeadChatNotificationService::class);
        $service->notifyGoogleMessage($lead, $conversation, $message, 'Fixture Sender');
        $service->notifyGoogleMessage($lead, $conversation, $message, 'Fixture Sender');

        $this->assertSame(1, DB::table('lead_chat_notifications')->count());
        $row = DB::table('lead_chat_notifications')->first();
        $this->assertSame($manager->id, $row->user_id);
        $this->assertSame($message->id, $row->message_id);
        $this->assertSame($conversation->id, $row->conversation_id);
        $this->actingAs($manager->fresh());
        $controller = app(\App\Http\Controllers\LeadChatNotificationController::class);
        $response = $controller->index();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $response->getData(true)['unread_count']);
        $this->assertSame($row->id, $response->getData(true)['notifications'][0]['id']);
        $notification = \App\Models\LeadChatNotification::findOrFail($row->id);
        $notification->user_id = 'different-user';
        try {
            $controller->markRead($notification);
            $this->fail('A user must not mark another user notification read.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_pricing_revision_migration_preserves_legacy_null_and_rolls_back(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->dropColumn('pricing_revision');
        });
        $scenario = $this->createPaidCancelledScenario();
        require_once base_path('database/migrations/2026_11_12_162509_add_pricing_revision_to_lead_followups_table.php');
        $migration = new \AddPricingRevisionToLeadFollowupsTable();
        $migration->up();
        $this->assertTrue(Schema::hasColumn('lead_followups', 'pricing_revision'));
        $this->assertNull(DB::table('lead_followups')->where('id', $scenario['followup']->id)->value('pricing_revision'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('lead_followups', 'pricing_revision'));
    }

    public function test_november_incentive_report_reconciles_sale_without_configured_target(): void
    {
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('lead_followup_id');
            $table->integer('status');
            $table->decimal('refund_amount', 12, 2);
        });
        $scenario = $this->createPaidCancelledScenario(10000);
        $person = $this->createAdminUser();
        $scenario['lead']->forceFill(['representative_user_id' => $person->id])->save();
        $scenario['followup']->update([
            'status' => LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
            'total_amount' => 35000,
        ]);
        DB::table('payment_audit_trail')->where('lead_followup_id', $scenario['followup']->id)
            ->update(['paid_date' => '2026-11-04 10:00:00']);

        $service = app(\App\Services\TargetIncentiveReportService::class);
        foreach ([true, false] as $singlePerson) {
            $report = $service->build(collect([$person]), collect(), 2026, 11, $singlePerson);
            $this->assertCount(1, $report['summary']);
            $this->assertCount(1, $report['detail']);
            $this->assertSame('No configured incentive', collect($report['detail']->first())->last());
        }
    }

    public function test_profit_loss_streams_201_bookings_without_duplicates_and_matches_accounts(): void
    {
        $this->actingAs($this->createAdminUser());
        $scenario = $this->createPaidCancelledScenario(10000);
        $leadTemplate = (array) DB::table('leads')->where('id', $scenario['lead']->id)->first();
        $followupTemplate = (array) DB::table('lead_followups')->where('id', $scenario['followup']->id)->first();
        $paymentTemplate = (array) DB::table('payment_audit_trail')->where('lead_followup_id', $followupTemplate['id'])->first();
        $leadRows = $followupRows = $paymentRows = [];
        for ($index = 1; $index <= 200; $index++) {
            $leadId = (string) Str::uuid();
            $followupId = (string) Str::uuid();
            $leadRows[] = array_replace($leadTemplate, ['id' => $leadId]);
            $followupRows[] = array_replace($followupTemplate, [
                'id' => $followupId, 'lead_id' => $leadId, 'total_amount' => 35000 + $index,
            ]);
            $paymentRows[] = array_replace($paymentTemplate, [
                'id' => (string) Str::uuid(), 'lead_followup_id' => $followupId,
            ]);
        }
        foreach (['leads' => $leadRows, 'lead_followups' => $followupRows, 'payment_audit_trail' => $paymentRows] as $table => $rows) {
            foreach (array_chunk($rows, 25) as $batch) {
                DB::table($table)->insert($batch);
            }
        }

        $filters = ['from_payment_date' => '2026-08-01', 'to_payment_date' => '2026-08-31'];
        $rows = collect(iterator_to_array((new ProfitLossReportExport($filters))->generator()));
        $screen = app(ReportController::class)->profitLossReport(
            Request::create('/admin/report/profit-loss', 'GET', $filters)
        )->getData()['profitLossData'];
        $this->assertCount(201, $rows);
        $this->assertCount(201, $rows->pluck('booking_revenue_amount')->unique());
        $this->assertCount(201, $screen);
        foreach (['booking_revenue_amount', 'client_received_amount', 'vendor_amount', 'profit_loss'] as $field) {
            $this->assertSame((float) $screen->sum($field), (float) $rows->sum($field));
        }
        $summary = app(\App\Services\Reports\ProfitLossSummaryService::class);
        $august = \Carbon\Carbon::parse('2026-08-01');
        $financial = $summary->forPeriod($august, $august->copy()->endOfMonth());
        $this->assertSame(201, $financial['lead_count']);
        $this->assertSame((float) $screen->sum('booking_revenue_amount'), $financial['booking_revenue']);
        $owner = app(\App\Services\Owner\OwnerDashboardService::class);
        $health = new \ReflectionMethod($owner, 'health');
        $money = ['vendor_paid' => 0, 'customer_refunded' => 0, 'net' => 0, 'collected' => 0];
        $kpis = $health->invoke($owner, $money, $money, $august, $financial, $financial);
        $this->assertSame((float) $screen->sum('profit_loss'), $kpis['booking_profit']);
        $this->assertSame($financial['commission'], $kpis['realized_profit']);
        $csv = \Maatwebsite\Excel\Facades\Excel::raw(new ProfitLossReportExport($filters), 'Csv');
        $csvRows = array_map('str_getcsv', preg_split('/\r?\n/', trim($csv)));
        $file = tempnam(sys_get_temp_dir(), 'crm-profit-loss-');
        try {
            file_put_contents($file, \Maatwebsite\Excel\Facades\Excel::raw(new ProfitLossReportExport($filters), 'Xlsx'));
            $workbook = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
            $xlsxRows = $workbook->getActiveSheet()->toArray();
            $workbook->disconnectWorksheets();
            $this->assertCount(202, $csvRows);
            $this->assertCount(202, $xlsxRows);
            foreach ([$csvRows, $xlsxRows] as $decodedRows) {
                foreach ([1 => 'booking_revenue_amount', 2 => 'client_received_amount', 4 => 'vendor_amount', 5 => 'profit_loss'] as $column => $field) {
                    $total = collect(array_slice($decodedRows, 1))->sum(
                        fn (array $row) => (float) str_replace(',', '', $row[$column])
                    );
                    $this->assertSame((float) $screen->sum($field), (float) $total);
                }
            }
        } finally {
            unlink($file);
        }
        $this->assertSame(0, iterator_count((new ProfitLossReportExport(
            $filters + ['_allowed_representative_ids' => []]
        ))->generator()));
    }

    public function test_exceptional_dashboard_moves_overpayment_to_added_sales_once(): void
    {
        Schema::table('lead_followups', function (Blueprint $table) {
            $table->boolean('added_to_sales')->default(false);
        });
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('lead_followup_id');
        });
        $scenario = $this->createPaidCancelledScenario(40000);
        $followup = $scenario['followup'];
        $followup->update(['total_amount' => 35000]);
        $user = $this->createAdminUser();
        $user->userType()->update(['user_type' => UserType::ACCOUNTS_MANAGER]);
        $this->actingAs($user->fresh());
        $controller = app(\App\Http\Controllers\ExceptionalController::class);
        $request = Request::create('/admin/account/exceptional', 'GET');

        $before = $controller->index($request)->getData();
        $this->assertCount(1, $before['payments']);
        $this->assertSame(0, $before['addedToSalesCount']);
        $response = $controller->addToSales(Request::create('/admin/account/exceptional', 'POST'), $followup->id);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $this->assertSame(5000.0, (float) $response->getData(true)['extra_added']);
        $this->assertSame(40000.0, (float) $followup->fresh()->total_amount);
        $after = $controller->index($request)->getData();
        $this->assertCount(0, $after['payments']);
        $this->assertSame(1, $after['addedToSalesCount']);
        $this->assertSame(422, $controller->addToSales(
            Request::create('/admin/account/exceptional', 'POST'), $followup->id
        )->getStatusCode());
    }

    public function test_exceptional_dashboard_uses_authoritative_zero_price(): void
    {
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('lead_followup_id');
        });
        $scenario = $this->createPaidCancelledScenario(8500);
        $scenario['followup']->update(['total_amount' => 0, 'pricing_revision' => true]);
        $this->actingAs($this->createAdminUser());

        $rows = app(\App\Http\Controllers\ExceptionalController::class)
            ->index(Request::create('/admin/account/exceptional'))->getData()['payments'];

        $this->assertCount(1, $rows);
        $this->assertSame(0.0, $rows->first()->total_amount);
        $this->assertSame(-8500.0, $rows->first()->balance);
    }

    public function test_invoice_discount_reconciles_gross_and_net_voucher_without_double_discount(): void
    {
        $scenario = $this->createPaidCancelledScenario(8500);
        $scenario['followup']->update([
            'total_amount' => 17000,
            'service_details' => [['original_amount' => 18000, 'discount_amount' => 1000]],
        ]);
        $controller = app(InvoiceController::class);
        $method = new \ReflectionMethod($controller, 'reconciledInvoiceDiscount');
        $expected = ['gross' => 18000.0, 'discount' => 1000.0, 'net' => 17000.0];
        foreach ([18000.0, 17000.0] as $voucherTotal) {
            $this->assertSame($expected, $method->invoke($controller, $scenario['lead'], $voucherTotal));
        }
        $this->assertNull($method->invoke($controller, $scenario['lead'], 16000.0));
    }

    public function test_operations_due_today_excludes_overdue_future_closed_and_out_of_scope_cases(): void
    {
        Schema::create('operation_cases', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('team');
            $table->string('status');
            $table->timestamp('next_followup_at')->nullable();
        });
        DB::table('operation_cases')->insert([
            ['id' => 'undated', 'team' => 'mine', 'status' => 'open', 'next_followup_at' => null],
            ['id' => 'today', 'team' => 'mine', 'status' => 'open', 'next_followup_at' => '2026-11-12 00:00:00'],
            ['id' => 'overdue', 'team' => 'mine', 'status' => 'open', 'next_followup_at' => '2026-11-11 23:59:59'],
            ['id' => 'future', 'team' => 'mine', 'status' => 'open', 'next_followup_at' => '2026-11-13 00:00:00'],
            ['id' => 'closed', 'team' => 'mine', 'status' => 'completed', 'next_followup_at' => null],
            ['id' => 'other-team', 'team' => 'other', 'status' => 'open', 'next_followup_at' => null],
        ]);
        $service = app(\App\Services\Operations\OperationsHomeService::class);
        $method = new \ReflectionMethod($service, 'whereDueToday');
        $today = \Carbon\Carbon::parse('2026-11-12');
        $query = DB::table('operation_cases')->where('team', 'mine')->where('status', 'open');

        $this->assertSame(['today', 'undated'], $method->invoke(
            $service, $query, $today, $today->copy()->addDay()
        )->orderBy('id')->pluck('id')->all());
    }

    public function test_review_queue_counts_match_scoped_segment_queries(): void
    {
        Schema::create('operation_cases', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('type')->default('review');
            $table->string('status')->default('pending');
            $table->string('lead_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('assigned_to')->nullable();
            $table->timestamp('opened_at');
        });
        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->string('operation_case_id');
            $table->timestamps();
        });
        Schema::create((new \App\Models\ReviewConversation())->getTable(), function (Blueprint $table) {
            $table->string('operation_case_id');
            $table->timestamp('initial_message_sent_at')->nullable();
            $table->boolean('customer_replied');
            $table->string('sentiment')->nullable();
        });
        DB::table('operation_cases')->insert([
            ['id' => 'assigned', 'assigned_to' => 'manager', 'opened_at' => '2026-11-10 00:00:00'],
            ['id' => 'unassigned', 'assigned_to' => null, 'opened_at' => '2026-11-10 00:00:00'],
            ['id' => 'outside', 'assigned_to' => 'other', 'opened_at' => '2026-11-10 00:00:00'],
        ]);
        DB::table((new \App\Models\ReviewConversation())->getTable())->insert([
            'operation_case_id' => 'assigned',
            'initial_message_sent_at' => '2026-11-11 00:00:00',
            'customer_replied' => false,
        ]);
        $scope = \App\Models\OperationCase::query()->where(function ($query) {
            $query->where('assigned_to', 'manager')->orWhereNull('assigned_to');
        });
        $service = app(\App\Services\Operations\ReviewQueueService::class);
        $summary = $service->summary($scope);
        $this->assertSame(2, $summary['all']);
        foreach (['waiting' => 'assigned', 'not_sent' => 'unassigned', 'unassigned' => 'unassigned'] as $segment => $id) {
            $filtered = clone $scope;
            $service->filter($filtered, $segment);
            $this->assertSame([$id], $filtered->pluck('id')->all());
            $this->assertSame(1, $summary[$segment]);
        }
        $this->actingAs($this->createAdminUser());
        $response = app(\App\Http\Controllers\OperationsDashboardController::class)->queue(
            Request::create('/admin/operations/queue/review', 'GET', [
                'segment' => 'unassigned', 'search' => 'no-matching-review', 'from_date' => '2027-01-01',
                'status' => 'in_progress',
            ]),
            'review'
        );
        $this->assertSame(['unassigned'], $response->getData()['cases']->pluck('id')->all());
        $this->assertSame(1, $response->getData()['summary']['unassigned']);
    }

    public function test_refund_summary_counts_pending_vendor_payments_once_and_combines_approval(): void
    {
        Schema::table('vendor_refunds', function (Blueprint $table) {
            $table->integer('status')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->integer('status');
            $table->string('approval_status');
        });
        $this->createPaidCancelledScenario();
        $vendorRefund = (array) DB::table('vendor_refunds')->first();
        DB::table('vendor_refunds')->where('id', $vendorRefund['id'])->update(['approval_status' => 'pending']);
        $vendorRefund['id'] = (string) Str::uuid();
        $vendorRefund['approval_status'] = 'pending';
        DB::table('vendor_refunds')->insert($vendorRefund);
        DB::table('lead_refunds')->insert([
            ['id' => 'waiting', 'status' => 1, 'approval_status' => 'pending'],
            ['id' => 'approved', 'status' => 1, 'approval_status' => 'approved'],
            ['id' => 'complete', 'status' => 2, 'approval_status' => 'pending'],
        ]);

        $summary = app(\App\Services\Operations\RefundSummaryService::class)->summary();
        $this->assertSame(2, $summary['customer_pending']);
        $this->assertSame(1, $summary['vendor_pending']);
        $this->assertSame(2, $summary['waiting_approval']);
        $this->actingAs($this->createAdminUser());
        $vendorPage = app(\App\Http\Controllers\OperationsVendorRefundController::class)->index(
            Request::create('/vendor-refunds', 'GET')
        );
        $this->assertSame($summary, $vendorPage->getData()['refundSummary']);
    }

    public function test_attendance_workbook_uses_native_dates_and_rejects_outside_range(): void
    {
        $workbook = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $workbook->getActiveSheet();
        $sheet->setTitle('Attendance Data');
        $sheet->fromArray([
            ['Date', 'Day', 'Name', 'In', 'Out', 'Status'],
            [\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(new \DateTimeImmutable('2026-10-05')), 'Monday', 'Test Employee', 0.375, 3, 'MIS'],
            ['06/10/2026', 'Tuesday', 'Test Employee', '09:00', '17:00', 'P'],
            ['07/10/2026', 'Wednesday', 'Test Employee', null, null, 'A'],
        ]);
        $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('m/d/yy');
        $workbook->createSheet()->setTitle('Instructions')->setCellValue('A1', 'Enter attendance in the first sheet.');
        $file = tempnam(sys_get_temp_dir(), 'crm-attendance-');

        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($workbook))->save($file);
            $parser = new \App\Services\Attendance\AttendanceImportParser();
            $parsed = $parser->parse($file, \Carbon\Carbon::parse('2026-10-01'), \Carbon\Carbon::parse('2026-10-31'));
            $this->assertSame(['2026-10-05', '2026-10-06', '2026-10-07'], array_column($parsed['rows'], 'attendance_date'));
            $this->assertSame('09:00:00', $parsed['rows'][0]['in_time']);
            $this->assertNull($parsed['rows'][0]['out_time']);
            $this->assertSame(['MIS', 'P', 'A'], array_column($parsed['rows'], 'raw_status'));

            $november = \Carbon\Carbon::parse('2026-11-04');
            try {
                $parser->parse($file, $november, $november);
                $this->fail('An October-only workbook must not import November attendance.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                $this->assertStringContainsString(
                    'No attendance rows were found inside the selected range.',
                    $exception->errors()['excel_file'][0]
                );
            }
        } finally {
            $workbook->disconnectWorksheets();
            unlink($file);
        }
    }

    public function test_city_endpoint_returns_only_active_cities_in_selected_country(): void
    {
        DB::table('cities')->insert([
            ['id' => 'active', 'country_id' => 'india', 'name' => 'Mumbai', 'status' => true],
            ['id' => 'inactive', 'country_id' => 'india', 'name' => 'Inactive city', 'status' => false],
            ['id' => 'other', 'country_id' => 'other', 'name' => 'Other city', 'status' => true],
        ]);

        $controller = app(\App\Http\Controllers\ClientController::class);
        $response = $controller->getCitiesByCountry('india');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([['id' => 'active', 'name' => 'Mumbai']], $response->getData(true));
        $this->assertSame([], $controller->getCitiesByCountry('null')->getData(true));
    }

    public function test_accounts_can_save_and_replace_customer_refund_screenshot(): void
    {
        $scenario = $this->createPaidCancelledScenario();
        $user = $this->createAdminUser();
        $user->userType()->update(['user_type' => UserType::ACCOUNTS_MANAGER]);
        $this->actingAs($user->fresh());

        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_followup_id');
            $table->decimal('original_amount', 12, 2);
            $table->decimal('refund_amount', 12, 2);
            $table->string('refund_type')->nullable();
            $table->date('refund_date')->nullable();
            $table->text('refund_reason')->nullable();
            $table->string('refund_proof')->nullable();
            $table->unsignedTinyInteger('status');
            $table->timestamps();
        });

        config()->set('session.driver', 'array');
        $testStorage = sys_get_temp_dir().'/crm-refund-'.Str::uuid();
        $root = $testStorage.'/app/public';
        mkdir($root, 0777, true);
        $disk = app('filesystem')->createLocalDriver(['driver' => 'local', 'root' => $root]);
        \Illuminate\Support\Facades\Storage::set('public', $disk);
        $originalStorage = storage_path();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/P8sAAAAASUVORK5CYII=');
        $payload = [
            'followup_id' => $scenario['followup']->id,
            'original_amount' => 70000,
            'refund_amount' => 5000,
            'refund_type' => 'Bank Transfer',
            'refund_date' => '2026-08-20',
        ];
        foreach (['proof.png', 'replacement.png'] as $name) {
            $file = \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $png);
            $response = $this->post(route('admin.refunds.store'), $payload + ['refund_proof' => $file]);
            $response->assertOk();
            $refund = LeadRefund::findOrFail($response->json('refund_id'));
            $this->assertNotEmpty($refund->refund_proof);
            $this->assertSame($png, file_get_contents($root.'/'.$refund->refund_proof));
            $this->app->useStoragePath($testStorage);
            try {
                $download = $this->get(route('admin.refunds.download', $refund->id));
                $download->assertOk();
                $this->assertSame($png, file_get_contents($download->baseResponse->getFile()->getPathname()));
            } finally {
                $this->app->useStoragePath($originalStorage);
            }
        }
        $this->assertSame(1, LeadRefund::count());
        $user->userType()->update(['user_type' => UserType::HR]);
        $this->actingAs($user->fresh());
        $this->get(route('admin.refunds.download', $refund->id))->assertForbidden();
    }

    public function test_empty_vendor_report_leaves_datatables_body_empty(): void
    {
        $this->actingAs($this->createAdminUser());
        $request = Request::create('/admin/report/vendor-payments', 'GET', ['status' => 'unpaid']);
        app()->instance('request', $request);
        $response = app(ReportController::class)->vendorReport($request);
        $data = $response->getData();
        $this->assertCount(0, $data['rows']);

        $html = view('admin.report.partials.vendor_report_redesign', $data)->render();
        $this->assertMatchesRegularExpression('/id="vendor-report-table".*?<tbody>\s*<\/tbody>/s', $html);
        $this->assertStringContainsString('No vendor payments match these filters.', $html);
        $this->assertStringNotContainsString('@selected', $html);
        $this->assertStringContainsString('value="unpaid" selected', $html);
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

    public function test_vendor_report_generates_matching_csv_and_xlsx_rows(): void
    {
        $this->createPaidCancelledScenario();
        $filters = ['search' => 'Cancelled'];
        $csv = \Maatwebsite\Excel\Facades\Excel::raw(new VendorReportExport($filters), 'Csv');
        $csvRows = array_map('str_getcsv', preg_split('/\r?\n/', trim($csv)));
        $this->assertCount(2, $csvRows);
        $this->assertContains('Cancelled Customer', $csvRows[1]);
        $this->assertContains('10,000.00', $csvRows[1]);

        $xlsx = \Maatwebsite\Excel\Facades\Excel::raw(new VendorReportExport($filters), 'Xlsx');
        $file = tempnam(sys_get_temp_dir(), 'crm-vendor-');
        try {
            file_put_contents($file, $xlsx);
            $workbook = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($file);
            $rows = $workbook->getActiveSheet()->toArray();
            $workbook->disconnectWorksheets();
            $this->assertSame($csvRows, array_map(
                fn (array $row) => array_map(fn ($value) => (string) $value, $row),
                $rows
            ));
        } finally {
            unlink($file);
        }
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

    public function test_booking_profit_uses_chargeable_booking_value_when_customer_payment_is_partial(): void
    {
        $this->actingAs($this->createAdminUser());
        $this->createPaidCancelledScenario(40000);

        $report = app(ReportController::class)
            ->profitLossReport(Request::create('/admin/report/profit-loss', 'GET'));
        $reportRow = $report->getData()['profitLossData']->first();

        $this->assertSame(70000.0, (float) $reportRow->booking_revenue_amount);
        $this->assertSame(40000.0, (float) $reportRow->client_received_amount);
        $this->assertSame(10000.0, (float) $reportRow->vendor_amount);
        $this->assertSame(60000.0, (float) $reportRow->profit_loss);
        $this->assertSame(85.71, round((float) $reportRow->profit_loss_percent, 2));

        $exportRow = (new ProfitLossReportExport([]))->collection()->first();
        $this->assertSame(70000.0, (float) $exportRow->booking_revenue_amount);
        $this->assertSame(40000.0, (float) $exportRow->client_received_amount);
        $this->assertSame(60000.0, (float) $exportRow->profit_loss);
    }

    public function test_customer_refund_reduces_booking_profit_without_changing_cash_received(): void
    {
        $scenario = $this->createPaidCancelledScenario(40000);
        Schema::create('lead_refunds', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('lead_followup_id');
            $table->decimal('refund_amount', 12, 2);
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });

        LeadRefund::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $scenario['followup']->id,
            'refund_amount' => 5000,
            'status' => 2,
        ]);

        $financial = app(\App\Services\Reports\ProfitLossSummaryService::class)
            ->forFollowup($scenario['followup']->fresh());

        $this->assertSame(40000.0, $financial['cash_received']);
        $this->assertSame(40000.0, $financial['earned_revenue']);
        $this->assertSame(65000.0, $financial['chargeable_revenue']);
        $this->assertSame(10000.0, $financial['vendor_cost']);
        $this->assertSame(55000.0, $financial['booking_profit']);
        $this->assertSame(30000.0, $financial['profit'], 'Existing cash-based profit remains available to existing callers.');

        $paidDate = PaymentAuditTrail::where('lead_followup_id', $scenario['followup']->id)->value('paid_date');
        $period = app(\App\Services\Reports\ProfitLossSummaryService::class)->forPeriod(
            \Carbon\Carbon::parse($paidDate)->startOfMonth(),
            \Carbon\Carbon::parse($paidDate)->endOfMonth()
        );
        $this->assertSame(55000.0, $period['booking_profit']);
        $this->assertSame(30000.0, $period['commission'], 'Realized cash profit stays available alongside booking profit.');
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

    private function createPaidCancelledScenario(float $customerPaymentAmount = 70000): array
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
            'paid_amount' => $customerPaymentAmount,
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
            $table->string('name')->nullable();
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
            $table->boolean('pricing_revision')->nullable();
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

    public function test_note_only_sales_followup_preserves_booking_profit(): void
    {
        $lead = $this->createPartiallyPaidBooking();

        $this->addBookingFollowup($lead, LeadFollowup::STATUS_SALES_FOLLOW_UP, 0, '2026-09-02');
        $this->assertBookingFinancials($lead, 35000.0, 10000.0);
    }

    public function test_note_only_close_followup_preserves_booking_profit(): void
    {
        $lead = $this->createPartiallyPaidBooking();
        $this->addBookingFollowup($lead, LeadFollowup::STATUS_CLOSE_FOLLOW_UP, 0, '2026-09-03');
        $this->assertBookingFinancials($lead, 35000.0, 10000.0);
    }

    public function test_priced_sales_add_on_increases_booking_profit(): void
    {
        $lead = $this->createPartiallyPaidBooking();

        $this->addBookingFollowup($lead, LeadFollowup::STATUS_SALES_FOLLOW_UP, 40000, '2026-09-02');
        $this->assertBookingFinancials($lead, 40000.0, 15000.0);
    }

    public function test_legacy_note_after_authoritative_zero_keeps_zero_booking_revenue(): void
    {
        $lead = $this->createPartiallyPaidBooking();
        $this->addBookingFollowup($lead, LeadFollowup::STATUS_SALES_FOLLOW_UP, 0, '2026-09-02');
        $revision = LeadFollowup::where('lead_id', $lead->id)->latest('created_at')->firstOrFail();
        $revision->update(['pricing_revision' => true]);
        $this->assertBookingFinancials($lead, 0.0, -25000.0);

        $this->addBookingFollowup($lead, LeadFollowup::STATUS_CLOSE_FOLLOW_UP, 0, '2026-09-03');
        $this->assertBookingFinancials($lead, 0.0, -25000.0);
    }

    private function createPartiallyPaidBooking(): Lead
    {
        $scenario = $this->createPaidCancelledScenario(10000);
        $scenario['followup']->update([
            'status' => LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
            'total_amount' => 35000,
        ]);
        VendorRefund::where('lead_vendor_payment_id', $scenario['lead_vendor_payment']->id)
            ->update(['cancellation_amount' => 25000]);

        return $scenario['lead'];
    }

    private function addBookingFollowup(Lead $lead, int $status, int $total, string $date): void
    {
        LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => $status,
            'total_amount' => $total,
            'received_amount' => 0,
            'service_ids' => [],
            'extra_service_ids' => [],
            'service_details' => [],
            'created_at' => $date.' 10:00:00',
            'updated_at' => $date.' 10:00:00',
        ]);
    }

    private function assertBookingFinancials(Lead $lead, float $revenue, float $profit): void
    {
        $summary = app(\App\Services\Reports\ProfitLossSummaryService::class);
        $cohort = app(\App\Services\Sales\SalesBookingCohortService::class);
        $augustStart = \Carbon\Carbon::parse('2026-08-01');
        $augustEnd = \Carbon\Carbon::parse('2026-08-31');
        $septemberStart = \Carbon\Carbon::parse('2026-09-01');
        $septemberEnd = \Carbon\Carbon::parse('2026-09-30');

        $this->assertTrue($cohort->leadIdsForPeriod($augustStart, $augustEnd)->contains($lead->id));
        $this->assertFalse($cohort->leadIdsForPeriod($septemberStart, $septemberEnd)->contains($lead->id));
        $financial = $summary->forPeriod($augustStart, $augustEnd);
        $latest = LeadFollowup::where('lead_id', $lead->id)->latest('created_at')->firstOrFail();
        $this->assertSame(10000.0, $summary->forFollowup($latest)['cash_received']);
        $this->assertSame(1, $financial['lead_count']);
        $this->assertSame(25000.0, $financial['vendor_cost']);
        $this->assertSame($profit, $financial['booking_profit']);
        $this->assertSame($revenue, $financial['booking_revenue']);
    }
public function test_fractional_second_payment_is_counted_in_december(): void
{
    $lead = $this->createPartiallyPaidBooking();

    PaymentAuditTrail::query()
        ->whereIn(
            'lead_followup_id',
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->select('id')
        )
        ->update([
            'paid_date' => '2026-12-31 23:59:59.500000',
        ]);

    $cohort = app(
        \App\Services\Sales\SalesBookingCohortService::class
    );

    $december = $cohort->leadIdsForPeriod(
        \Carbon\Carbon::parse('2026-12-01'),
        \Carbon\Carbon::parse('2026-12-31')
    );

    $january = $cohort->leadIdsForPeriod(
        \Carbon\Carbon::parse('2027-01-01'),
        \Carbon\Carbon::parse('2027-01-31')
    );

    $this->assertTrue($december->contains($lead->id));
    $this->assertFalse($january->contains($lead->id));
}

public function test_midnight_payment_is_counted_in_january(): void
{
    $lead = $this->createPartiallyPaidBooking();

    PaymentAuditTrail::query()
        ->whereIn(
            'lead_followup_id',
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->select('id')
        )
        ->update([
            'paid_date' => '2027-01-01 00:00:00.000000',
        ]);

    $cohort = app(
        \App\Services\Sales\SalesBookingCohortService::class
    );

    $december = $cohort->leadIdsForPeriod(
        \Carbon\Carbon::parse('2026-12-01'),
        \Carbon\Carbon::parse('2026-12-31')
    );

    $january = $cohort->leadIdsForPeriod(
        \Carbon\Carbon::parse('2027-01-01'),
        \Carbon\Carbon::parse('2027-01-31')
    );

    $this->assertFalse($december->contains($lead->id));
    $this->assertTrue($january->contains($lead->id));
}


public function test_historical_followup_does_not_use_future_booking_price(): void
{
    $lead = $this->createPartiallyPaidBooking();

    $this->addBookingFollowup(
        $lead,
        LeadFollowup::STATUS_SALES_FOLLOW_UP,
        40000,
        '2026-09-01'
    );

    $this->addBookingFollowup(
        $lead,
        LeadFollowup::STATUS_SALES_FOLLOW_UP,
        0,
        '2026-09-03'
    );

    $historical = LeadFollowup::query()
        ->where('lead_id', $lead->id)
        ->whereDate('created_at', '2026-09-03')
        ->firstOrFail();

    $this->addBookingFollowup(
        $lead,
        LeadFollowup::STATUS_SALES_FOLLOW_UP,
        55000,
        '2026-09-04'
    );

    $summary = app(
        \App\Services\Reports\ProfitLossSummaryService::class
    );

    $historicalFinancial = $summary->forFollowup($historical);

    $this->assertSame(
        40000.0,
        $historicalFinancial['chargeable_revenue']
    );

    $this->assertSame(
        15000.0,
        $historicalFinancial['booking_profit']
    );

    $current = LeadFollowup::query()
        ->where('lead_id', $lead->id)
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->firstOrFail();

    $currentFinancial = $summary->forFollowup($current);

    $this->assertSame(
        55000.0,
        $currentFinancial['chargeable_revenue']
    );

    $this->assertSame(
        30000.0,
        $currentFinancial['booking_profit']
    );
}


}
