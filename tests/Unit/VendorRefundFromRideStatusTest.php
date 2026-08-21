<?php

namespace Tests\Unit;

use App\Http\Controllers\RideController;
use App\Models\Lead;
use App\Models\LeadRide;
use App\Models\LeadVendorPayment;
use App\Models\User;
use App\Models\UserType;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Models\VendorRefund;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => null,
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
