<?php

namespace Tests\Feature\Operations;

use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsVendorRefundFlowTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'operations_vendor_refund_testing');
        config()->set('database.connections.operations_vendor_refund_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('operations_vendor_refund_testing');
        DB::reconnect('operations_vendor_refund_testing');
        DB::setDefaultConnection('operations_vendor_refund_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 25, 12, 0, 0));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('operations_vendor_refund_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('operations_vendor_refund_testing');

        parent::tearDown();
    }

    public function test_index_shows_vendor_columns_and_action_buttons(): void
    {
        $viewer = $this->user('Operations Vendor Refund', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Devendra test lead',
            vendorName: 'Bativala M Bhagvati Flying Charters',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'Trip cancelled',
            status: 1
        );

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.index'));

        $response->assertOk();
        $response->assertSee('Vendor Refund List');

        foreach ([
            'Vendor Name',
            'Vendor Phone',
            'Service Name',
            'Service Date',
            'Original Vendor Amount',
            'Cancellation Amount',
            'Refund Received',
            'Refund Due',
            'Refund Date',
            'Action',
        ] as $heading) {
            $response->assertSee('<th>' . $heading . '</th>', false);
        }

        $response->assertDontSee('<th>Client Name</th>', false);
        $response->assertDontSee('<th>Gross Paid</th>', false);

        $response->assertSee('Bativala M Bhagvati Flying Charters');
        $response->assertSee('9876500000');
        $response->assertSee('Helicopter Charter, Ground Transfer');
        $response->assertSee('01 Oct 2026');
        $response->assertSee('₹75,000.00');
        $response->assertSee('₹20,000.00');
        $response->assertSee('₹10,000.00');
        $response->assertSee('₹40,000.00');
        $response->assertSee('22 Aug 2026');

        $response->assertSee('openVendorRefundDrawer');
        $response->assertSee('vendor-refund-preview-modal', false);
        $response->assertSee('Confirm Mark as Done');
        $response->assertSee(
            route('admin.operations.vendor-refunds.download', $fixture['lead_vendor_payment_id']),
            false
        );
        $response->assertSee(
            route('admin.operations.vendor-refunds.preview', $fixture['lead_vendor_payment_id']),
            false
        );
    }

    public function test_multiple_refund_rows_of_one_vendor_payment_show_as_one_list_row(): void
    {
        $viewer = $this->user('Operations Group User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Grouped Customer',
            vendorName: 'Grouped Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'First part',
            status: 1
        );

        $this->addRefund($fixture, 15000, '2026-08-25', 'UPI Payment', 'Second part');

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.index'));

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), '<tr data-vendor-payment-id="')
        );
        $response->assertSee('₹25,000.00');
        $response->assertSee('25 Aug 2026');
    }

    public function test_show_returns_vendor_service_and_history_json(): void
    {
        $viewer = $this->user('Operations Drawer User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Drawer Customer',
            vendorName: 'Drawer Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'Trip cancelled',
            status: 1
        );

        $response = $this
            ->actingAs($viewer)
            ->getJson(route('admin.operations.vendor-refunds.show', $fixture['lead_vendor_payment_id']));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.vendor.name', 'Drawer Vendor');
        $response->assertJsonPath('data.vendor.email', 'vendor@example.test');
        $response->assertJsonPath('data.vendor.city', 'Mumbai');
        $response->assertJsonPath('data.vendor.bank_details', 'HDFC 12345');
        $response->assertJsonPath('data.customer.name', 'Drawer Customer');

        $response->assertJsonCount(1, 'data.services');
        $response->assertJsonPath('data.services.0.name', 'Helicopter Charter');
        $response->assertJsonPath('data.services.0.vendor_amount', 70000);
        $response->assertJsonCount(1, 'data.extra_services');
        $response->assertJsonPath('data.extra_services.0.name', 'Ground Transfer');
        $response->assertJsonPath('data.extra_services.0.vendor_amount', 5000);

        $response->assertJsonCount(1, 'data.rides');
        $response->assertJsonPath('data.rides.0.from_place', 'Mumbai');
        $response->assertJsonPath('data.service_date', '01 Oct 2026');

        $response->assertJsonPath('data.original_vendor_amount', 75000);
        $response->assertJsonPath('data.cancellation_amount', 20000);
        $response->assertJsonPath('data.gross_paid', 70000);
        $response->assertJsonPath('data.refund_received', 10000);
        $response->assertJsonPath('data.net_paid', 60000);
        $response->assertJsonPath('data.refund_due', 40000);

        $response->assertJsonCount(1, 'data.payment_history');
        $response->assertJsonPath('data.payment_history.0.amount', 70000);
        $response->assertJsonPath('data.payment_history.0.payment_method', 'Bank Transfer');

        $response->assertJsonCount(1, 'data.refund_history');
        $response->assertJsonPath('data.refund_history.0.amount', 10000);
        $response->assertJsonPath('data.refund.refund_type', 'Bank Transfer');
    }

    public function test_preview_and_download_use_the_same_vendor_refund_invoice(): void
    {
        $viewer = $this->user('Operations Invoice User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Invoice Customer',
            vendorName: 'Invoice Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'Trip cancelled',
            status: 1
        );

        $preview = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.preview', $fixture['lead_vendor_payment_id']));

        $preview->assertOk();
        $preview->assertSee('VENDOR REFUND');
        $preview->assertSee('Refund From Vendor');
        $preview->assertSee('Invoice Vendor');
        $preview->assertSee('Helicopter Charter');
        $preview->assertSee('Ground Transfer');
        $preview->assertSee('Vendor Payment History');
        $preview->assertSee('Vendor Refund History');

        $download = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.download', $fixture['lead_vendor_payment_id']));

        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'vendor-refund-' . $fixture['lead_vendor_payment_id'] . '.pdf',
            (string) $download->headers->get('content-disposition')
        );
    }

    public function test_update_saves_refund_type_date_and_reason_on_latest_refund(): void
    {
        $viewer = $this->user('Operations Save User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Save Customer',
            vendorName: 'Save Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Cash',
            refundReason: 'Old reason',
            status: 1
        );

        $response = $this
            ->actingAs($viewer)
            ->postJson(
                route('admin.operations.vendor-refunds.update', $fixture['lead_vendor_payment_id']),
                [
                    'refund_type' => 'UPI Payment',
                    'refund_date' => '2026-09-01',
                    'refund_reason' => 'Updated reason',
                ]
            );

        $response->assertOk();
        $response->assertJsonPath('success', true);

        $refund = DB::table('vendor_refunds')->where('id', $fixture['vendor_refund_id'])->first();

        $this->assertSame('UPI Payment', $refund->refund_type);
        $this->assertSame('Updated reason', $refund->refund_reason);
        $this->assertStringStartsWith('2026-09-01', (string) $refund->refund_date);
        $this->assertEquals(10000, $refund->refund_amount);
    }

    public function test_mark_done_is_blocked_while_refund_is_still_due(): void
    {
        $viewer = $this->user('Operations Blocked User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Blocked Customer',
            vendorName: 'Blocked Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 10000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'Partial refund',
            status: 1
        );

        $response = $this
            ->actingAs($viewer)
            ->postJson(route('admin.operations.vendor-refunds.mark-done', $fixture['lead_vendor_payment_id']));

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);

        $this->assertDatabaseHas('vendor_refunds', [
            'id' => $fixture['vendor_refund_id'],
            'status' => 1,
        ]);
    }

    public function test_mark_done_ajax_completes_all_pending_refunds_and_removes_row(): void
    {
        $viewer = $this->user('Operations Ajax Done User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Ajax Done Customer',
            vendorName: 'Ajax Done Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 20000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'First part',
            status: 1
        );

        $secondRefundId = $this->addRefund($fixture, 30000, '2026-08-25', 'UPI Payment', 'Balance');

        $response = $this
            ->actingAs($viewer)
            ->postJson(route('admin.operations.vendor-refunds.mark-done', $fixture['lead_vendor_payment_id']));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('vendor_payment_id', $fixture['lead_vendor_payment_id']);

        foreach ([$fixture['vendor_refund_id'], $secondRefundId] as $refundId) {
            $this->assertDatabaseHas('vendor_refunds', [
                'id' => $refundId,
                'status' => 2,
                'completed_by' => $viewer->id,
            ]);
        }

        $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.index'))
            ->assertOk()
            ->assertDontSee('Ajax Done Vendor');
    }

    public function test_mark_done_persists_completion_and_removes_refund_from_pending_operations_list(): void
    {
        $viewer = $this->user('Operations Done User', UserType::OPERATIONS_EXECUTIVE);
        $fixture = $this->vendorRefundFixture(
            customerName: 'Ready Done Customer',
            vendorName: 'Ready Done Vendor',
            paidAmount: 70000,
            cancellationAmount: 20000,
            refundAmount: 50000,
            refundDate: '2026-08-22',
            refundType: 'Bank Transfer',
            refundReason: 'Full vendor refund received',
            status: 1
        );

        $response = $this
            ->actingAs($viewer)
            ->post(route(
                'admin.operations.vendor-refunds.mark-done',
                $fixture['lead_vendor_payment_id']
            ));

        $response->assertRedirect(route('admin.operations.vendor-refunds.index'));

        $this->assertDatabaseHas('vendor_refunds', [
            'id' => $fixture['vendor_refund_id'],
            'status' => 2,
            'completed_by' => $viewer->id,
        ]);

        $index = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.vendor-refunds.index'));

        $index->assertOk();
        $index->assertDontSee('Ready Done Vendor');
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
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

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->uuid('city_id')->nullable();
            $table->text('address')->nullable();
            $table->text('bank_details')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service')->nullable();
            $table->timestamps();
        });

        Schema::create('extra_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('extra_service')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->decimal('total_service_amount', 15, 2)->default(0);
            $table->decimal('total_vendor_service_amount', 15, 2)->default(0);
            $table->integer('payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_vendor_payment_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id');
            $table->uuid('service_id')->nullable();
            $table->decimal('service_amount', 15, 2)->default(0);
            $table->decimal('vendor_service_amount', 15, 2)->default(0);
            $table->boolean('is_extra_service')->default(false);
            $table->integer('status')->nullable();
            $table->timestamps();
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_vendor_payment_id');
            $table->string('payment_method')->nullable();
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('receipt')->nullable();
            $table->date('paid_date')->nullable();
            $table->text('narration')->nullable();
            $table->integer('status')->nullable();
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
            $table->integer('status')->default(1);
            $table->timestamp('completed_at')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('type', 30);
            $table->string('status', 30)->default('pending');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    private function user(string $name, string $role): User
    {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => $role],
            [
                'id' => (string) Str::uuid(),
                'status' => 1,
            ]
        );

        return User::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    /**
     * Vendor payment worth ₹75,000 (service ₹70,000 + extra service ₹5,000),
     * with one ride segment starting 2026-10-01 and one vendor payment.
     */
    private function vendorRefundFixture(
        string $customerName,
        string $vendorName,
        float $paidAmount,
        float $cancellationAmount,
        float $refundAmount,
        string $refundDate,
        string $refundType,
        string $refundReason,
        int $status
    ): array {
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();
        $vendorId = (string) Str::uuid();
        $cityId = (string) Str::uuid();
        $serviceId = (string) Str::uuid();
        $extraServiceId = (string) Str::uuid();
        $leadVendorPaymentId = (string) Str::uuid();
        $vendorPaymentId = (string) Str::uuid();
        $vendorRefundId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => $customerName,
            'contact_number' => '9876543210',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'id' => $leadId,
            'client_id' => $clientId,
            'representative_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'from_date' => '2026-10-01 09:00:00',
            'to_date' => '2026-10-01 11:00:00',
            'from_place' => 'Mumbai',
            'to_place' => 'Pune',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cities')->insert([
            'id' => $cityId,
            'name' => 'Mumbai',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendors')->insert([
            'id' => $vendorId,
            'name' => $vendorName,
            'email' => 'vendor@example.test',
            'contact_number' => '9876500000',
            'city_id' => $cityId,
            'address' => '1 Airport Road',
            'bank_details' => 'HDFC 12345',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('services')->insert([
            'id' => $serviceId,
            'service' => 'Helicopter Charter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('extra_services')->insert([
            'id' => $extraServiceId,
            'extra_service' => 'Ground Transfer',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_vendor_payments')->insert([
            'id' => $leadVendorPaymentId,
            'voucher_id' => null,
            'lead_id' => $leadId,
            'vendor_id' => $vendorId,
            'total_service_amount' => 0,
            'total_vendor_service_amount' => 75000,
            'payment_status' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('lead_vendor_payment_details')->insert([
            [
                'id' => (string) Str::uuid(),
                'lead_vendor_payment_id' => $leadVendorPaymentId,
                'service_id' => $serviceId,
                'service_amount' => 90000,
                'vendor_service_amount' => 70000,
                'is_extra_service' => false,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'lead_vendor_payment_id' => $leadVendorPaymentId,
                'service_id' => $extraServiceId,
                'service_amount' => 6000,
                'vendor_service_amount' => 5000,
                'is_extra_service' => true,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('vendor_payments')->insert([
            'id' => $vendorPaymentId,
            'lead_vendor_payment_id' => $leadVendorPaymentId,
            'payment_method' => 'Bank Transfer',
            'paid_amount' => $paidAmount,
            'paid_date' => '2026-08-01',
            'narration' => 'Advance',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('vendor_refunds')->insert([
            'id' => $vendorRefundId,
            'lead_id' => $leadId,
            'lead_vendor_payment_id' => $leadVendorPaymentId,
            'vendor_id' => $vendorId,
            'ride_id' => null,
            'cancellation_amount' => $cancellationAmount,
            'refund_amount' => $refundAmount,
            'refund_date' => $refundDate,
            'refund_type' => $refundType,
            'refund_reason' => $refundReason,
            'refund_proof' => null,
            'no_refund_required' => false,
            'status' => $status,
            'completed_at' => null,
            'completed_by' => null,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'lead_id' => $leadId,
            'vendor_id' => $vendorId,
            'lead_vendor_payment_id' => $leadVendorPaymentId,
            'vendor_refund_id' => $vendorRefundId,
        ];
    }

    /**
     * Adds another refund transaction (Ride Status creates one row per refund).
     */
    private function addRefund(
        array $fixture,
        float $amount,
        string $date,
        string $type,
        string $reason
    ): string {
        $id = (string) Str::uuid();

        DB::table('vendor_refunds')->insert([
            'id' => $id,
            'lead_id' => $fixture['lead_id'],
            'lead_vendor_payment_id' => $fixture['lead_vendor_payment_id'],
            'vendor_id' => $fixture['vendor_id'],
            'ride_id' => null,
            'cancellation_amount' => 20000,
            'refund_amount' => $amount,
            'refund_date' => $date,
            'refund_type' => $type,
            'refund_reason' => $reason,
            'no_refund_required' => false,
            'status' => 1,
            'created_at' => now()->addMinute(),
            'updated_at' => now()->addMinute(),
        ]);

        return $id;
    }
}
