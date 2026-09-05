<?php

namespace Tests\Unit;

use App\Http\Controllers\RideController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeadTrackingController;
use App\Mail\RefundMail;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadRide;
use App\Models\LeadVendorPayment;
use App\Models\NotificationMaster;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorRefund;
use App\Models\Voucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorRefundFromRideStatusTest extends TestCase
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

        Storage::fake('public');
        config()->set('services.whatscrm.api_url', null);
        config()->set('services.whatscrm.api_token', null);

        $this->createSchema();
        $this->actingAs($this->createAccountsUser());
    }

    public function test_partial_vendor_payment_refund_is_calculated_from_paid_amount(): void
    {
        [$ride, $leadVendorPayment] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        $response = $this->saveVendorRefund($ride->id, [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => '10000.00',
            'refund_amount' => '20000.00',
            'refund_type' => 'Bank Transfer',
            'refund_date' => '2026-08-20',
            'refund_reason' => 'Partial vendor payment cancellation settlement.',
            'refund_proof' => UploadedFile::fake()->create('vendor-refund.pdf', 100, 'application/pdf'),
        ]);

        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('settled', $payload['status']);
        $this->assertSame('paid', $payload['payment_status']);
        $this->assertSame(30000.0, (float) $payload['total_paid']);
        $this->assertSame(20000.0, (float) $payload['total_refunded']);
        $this->assertSame(10000.0, (float) $payload['net_paid_to_vendor']);
        $this->assertSame(0.0, (float) $payload['balance_amount']);

        $this->assertDatabaseHas('vendor_refunds', [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 20000,
            'refund_type' => 'Bank Transfer',
            'no_refund_required' => false,
        ]);

        $this->assertDatabaseHas('lead_vendor_payments', [
            'id' => $leadVendorPayment->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_vendor_refund_sends_email_to_vendor_and_account_notification_members(): void
    {
        Mail::fake();

        [$ride, $leadVendorPayment, $lead, $vendor] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        NotificationMaster::create([
            'mobile_number' => '9893995795',
            'contact_country_code' => '+91',
            'email_id' => 'suraj.jaiswal@example.test',
            'status' => 1,
        ]);

        $response = $this->saveVendorRefund($ride->id, [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => '10000.00',
            'refund_amount' => '20000.00',
            'refund_type' => 'Bank Transfer',
            'refund_date' => '2026-08-20',
            'refund_reason' => 'Vendor returned cancellation overpayment.',
            'refund_proof' => UploadedFile::fake()->create('vendor-refund.pdf', 100, 'application/pdf'),
        ]);

        $this->assertTrue($response->getData(true)['success']);

        app()->terminate();

        Mail::assertSent(RefundMail::class, function (RefundMail $mail) use ($vendor) {
            return $mail->hasTo($vendor->email)
                && $mail->template === 'emails.refund-vendor';
        });

        Mail::assertSent(RefundMail::class, function (RefundMail $mail) {
            return $mail->hasTo('suraj.jaiswal@example.test')
                && $mail->template === 'emails.refund-vendor';
        });
    }

    public function test_partial_vendor_refund_received_amount_can_be_less_than_refund_due(): void
    {
        [$ride, $leadVendorPayment] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 50000,
        ]);

        try {
            $response = $this->saveVendorRefund($ride->id, [
                'lead_vendor_payment_id' => $leadVendorPayment->id,
                'cancellation_amount' => '10000.00',
                'refund_amount' => '15000.00',
                'refund_type' => 'Bank Transfer',
                'refund_date' => '2026-08-20',
                'refund_reason' => 'Vendor sent part of the refundable amount.',
                'refund_proof' => UploadedFile::fake()->create('vendor-refund.pdf', 100, 'application/pdf'),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->fail(
                'Partial vendor refund received amount should be accepted. Validation errors: '
                . json_encode($e->errors())
            );
        }

        $payload = $response->getData(true);

        $leadVendorPayment
            ->refresh()
            ->load([
                'vendorPayments',
                'vendorRefunds',
            ]);

        $this->assertTrue($payload['success']);
        $this->assertSame('partial_refund', $payload['status']);
        $this->assertSame('paid', $payload['payment_status']);
        $this->assertSame(50000.0, (float) $payload['total_paid']);
        $this->assertSame(15000.0, (float) $payload['total_refunded']);
        $this->assertSame(35000.0, (float) $payload['net_paid_to_vendor']);
        $this->assertSame(0.0, (float) $payload['balance_amount']);
        $this->assertSame(25000.0, (float) $leadVendorPayment->vendor_refund_due);
        $this->assertSame('partial_refund', $leadVendorPayment->vendor_refund_status);

        $this->assertDatabaseHas('vendor_refunds', [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 15000,
            'refund_type' => 'Bank Transfer',
            'no_refund_required' => false,
        ]);
    }

    public function test_zero_vendor_refund_received_amount_saves_cancellation_without_payment_proof(): void
    {
        [$ride, $leadVendorPayment] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        try {
            $response = $this->saveVendorRefund($ride->id, [
                'lead_vendor_payment_id' => $leadVendorPayment->id,
                'cancellation_amount' => '10000.00',
                'refund_amount' => '0.00',
                'refund_reason' => 'Cancellation saved before vendor refund is received.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->fail(
                'Zero vendor refund received amount should save cancellation without proof. Validation errors: '
                . json_encode($e->errors())
            );
        }

        $payload = $response->getData(true);

        $leadVendorPayment
            ->refresh()
            ->load([
                'vendorPayments',
                'vendorRefunds',
            ]);

        $this->assertTrue($payload['success']);
        $this->assertSame('refund_pending', $payload['status']);
        $this->assertSame('paid', $payload['payment_status']);
        $this->assertSame(30000.0, (float) $payload['total_paid']);
        $this->assertSame(0.0, (float) $payload['total_refunded']);
        $this->assertSame(30000.0, (float) $payload['net_paid_to_vendor']);
        $this->assertSame(0.0, (float) $payload['balance_amount']);
        $this->assertSame(20000.0, (float) $leadVendorPayment->vendor_refund_due);
        $this->assertSame('refund_pending', $leadVendorPayment->vendor_refund_status);

        $this->assertDatabaseHas('vendor_refunds', [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 0,
            'refund_type' => null,
            'refund_proof' => null,
            'no_refund_required' => false,
        ]);
    }

    public function test_zero_due_after_existing_vendor_refund_updates_latest_refund_without_duplicate(): void
    {
        [$ride, $leadVendorPayment, $lead, $vendor] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        $existingRefund = VendorRefund::create([
            'lead_id' => $lead->id,
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'vendor_id' => $vendor->id,
            'ride_id' => $ride->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 20000,
            'refund_date' => '2026-08-19',
            'refund_type' => 'Bank Transfer',
            'refund_reason' => 'Initial vendor refund.',
            'refund_proof' => 'vendor-refunds/initial.pdf',
            'no_refund_required' => false,
            'created_by' => auth()->id(),
        ]);

        $response = $this->saveVendorRefund($ride->id, [
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'cancellation_amount' => '10000.00',
            'refund_amount' => '0.00',
            'refund_date' => '2026-08-20',
            'refund_reason' => 'Reconfirmed vendor settlement.',
        ]);

        $payload = $response->getData(true);
        $existingRefund->refresh();

        $this->assertTrue($payload['success']);
        $this->assertSame($existingRefund->id, $payload['vendor_refund_id']);
        $this->assertSame(1, VendorRefund::where('lead_vendor_payment_id', $leadVendorPayment->id)->count());
        $this->assertSame(20000.0, (float) $existingRefund->refund_amount);
        $this->assertSame('Reconfirmed vendor settlement.', $existingRefund->refund_reason);
        $this->assertSame('Bank Transfer', $existingRefund->refund_type);
        $this->assertSame('vendor-refunds/initial.pdf', $existingRefund->refund_proof);
    }

    public function test_lead_tracking_vendor_summary_uses_refund_adjusted_amounts(): void
    {
        [$ride, $leadVendorPayment, $lead, $vendor] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        VendorRefund::create([
            'lead_id' => $lead->id,
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'vendor_id' => $vendor->id,
            'ride_id' => $ride->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 20000,
            'refund_date' => '2026-08-20',
            'refund_type' => 'Bank Transfer',
            'refund_reason' => 'Vendor returned overpaid amount.',
            'refund_proof' => 'vendor-refunds/refund.pdf',
            'no_refund_required' => false,
            'created_by' => auth()->id(),
        ]);

        $summary = $this->invokePrivateMethod(
            app(LeadTrackingController::class),
            'getVendorPaymentSummary',
            [$lead]
        );

        $vendorRow = $summary['vendor_payments']->first();

        $this->assertSame(10000.0, (float) $summary['total']);
        $this->assertSame(30000.0, (float) $summary['gross_paid']);
        $this->assertSame(20000.0, (float) $summary['refunded']);
        $this->assertSame(10000.0, (float) $summary['paid']);
        $this->assertSame(0.0, (float) $summary['balance']);
        $this->assertSame(0.0, (float) $summary['refund_due']);

        $this->assertSame(70000.0, (float) $vendorRow->original_vendor_amount);
        $this->assertSame(10000.0, (float) $vendorRow->adjusted_vendor_payable_amount);
        $this->assertSame(30000.0, (float) $vendorRow->gross_paid_amount);
        $this->assertSame(20000.0, (float) $vendorRow->refunded_amount);
        $this->assertSame(10000.0, (float) $vendorRow->net_paid_amount);
        $this->assertSame(0.0, (float) $vendorRow->balance_amount);
        $this->assertSame(0.0, (float) $vendorRow->refund_due_amount);
        $this->assertSame('paid', $vendorRow->display_payment_status);
    }

    public function test_invoice_vendor_summary_uses_refund_adjusted_amounts(): void
    {
        [$ride, $leadVendorPayment, $lead, $vendor] = $this->createVendorPaymentScenario([
            'vendor_amount' => 70000,
            'paid_amount' => 30000,
        ]);

        $voucher = Voucher::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 1,
            'created_by' => auth()->id(),
        ]);

        $leadVendorPayment->update([
            'voucher_id' => $voucher->id,
        ]);

        VendorRefund::create([
            'lead_id' => $lead->id,
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'vendor_id' => $vendor->id,
            'ride_id' => $ride->id,
            'cancellation_amount' => 10000,
            'refund_amount' => 20000,
            'refund_date' => '2026-08-20',
            'refund_type' => 'Bank Transfer',
            'refund_reason' => 'Vendor returned overpaid amount.',
            'refund_proof' => 'vendor-refunds/refund.pdf',
            'no_refund_required' => false,
            'created_by' => auth()->id(),
        ]);

        $voucher->load([
            'vendorPayments.vendor',
            'vendorPayments.vendorPayments',
            'vendorPayments.vendorRefunds',
        ]);

        $summary = $this->invokePrivateMethod(
            app(InvoiceController::class),
            'getVendorInformation',
            [$voucher]
        );

        $vendorRow = $summary['vendors'][0];

        $this->assertSame(10000.0, (float) $summary['totalVendorCost']);
        $this->assertSame(30000.0, (float) $summary['totalGrossPaid']);
        $this->assertSame(20000.0, (float) $summary['totalRefunded']);
        $this->assertSame(10000.0, (float) $summary['totalPaid']);
        $this->assertSame(0.0, (float) $summary['totalBalance']);
        $this->assertSame(0.0, (float) $summary['totalRefundDue']);

        $this->assertSame(70000.0, (float) $vendorRow['original_amount']);
        $this->assertSame(10000.0, (float) $vendorRow['total_amount']);
        $this->assertSame(30000.0, (float) $vendorRow['gross_paid_amount']);
        $this->assertSame(20000.0, (float) $vendorRow['refunded_amount']);
        $this->assertSame(10000.0, (float) $vendorRow['paid_amount']);
        $this->assertSame(0.0, (float) $vendorRow['balance']);
        $this->assertSame(0.0, (float) $vendorRow['refund_due_amount']);
        $this->assertSame('paid', $vendorRow['payment_status']);
    }

    private function saveVendorRefund(string $rideId, array $data)
    {
        $file = $data['refund_proof'] ?? null;
        unset($data['refund_proof']);

        $request = Request::create(
            '/admin/ride-status/' . $rideId . '/save-vendor-refund',
            'POST',
            $data
        );

        if ($file) {
            $request->files->set('refund_proof', $file);
        }

        return app(RideController::class)
            ->saveVendorRefundFromRideStatus($request, $rideId);
    }

    private function invokePrivateMethod(object $object, string $method, array $arguments = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function createAccountsUser(): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => UserType::ACCOUNTS_MANAGER,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Accounts User',
            'email' => 'accounts@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createVendorPaymentScenario(array $overrides): array
    {
        $client = Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Test Customer',
            'email' => 'customer@example.test',
            'contact_number' => '9123456780',
            'alternate_number' => null,
        ]);

        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => auth()->id(),
            'service_ids' => [],
            'product_ids' => [],
        ]);

        $ride = LeadRide::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-09-15 10:00:00',
            'to_date' => '2026-09-15 11:00:00',
            'from_place' => 'Indore',
            'to_place' => 'Ujjain',
        ]);

        $vendor = Vendor::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test Vendor ' . Str::random(6),
            'email' => Str::uuid() . '@vendor.example',
            'contact_number' => '9876543210',
            'city_id' => (string) Str::uuid(),
            'address' => 'Test address',
            'status' => 1,
        ]);

        $leadVendorPayment = LeadVendorPayment::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'vendor_id' => $vendor->id,
            'total_service_amount' => $overrides['vendor_amount'],
            'total_vendor_service_amount' => $overrides['vendor_amount'],
            'payment_status' => 'partial',
        ]);

        VendorPayment::create([
            'id' => (string) Str::uuid(),
            'lead_vendor_payment_id' => $leadVendorPayment->id,
            'payment_method' => 'Bank Transfer',
            'paid_amount' => $overrides['paid_amount'],
            'paid_date' => '2026-08-18',
            'narration' => 'Partial vendor payment',
            'status' => 1,
        ]);

        return [$ride, $leadVendorPayment, $lead, $vendor];
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
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
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
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

        Schema::create('notification_masters', function (Blueprint $table) {
            $table->id();
            $table->string('mobile_number')->nullable();
            $table->string('email_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('contact_country_code')->nullable();
            $table->uuid('country_id')->nullable();
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
    }
}
