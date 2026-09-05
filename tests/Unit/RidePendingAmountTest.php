<?php

namespace Tests\Unit;

use App\Http\Controllers\RideController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\PaymentAuditTrail;
use App\Models\Voucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RidePendingAmountTest extends TestCase
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

    public function test_upcoming_ride_detail_uses_last_valid_total_after_cancel_then_approve(): void
    {
        $ride = $this->createCancelThenApproveScenario();

        $response = app(RideController::class)
            ->getRideDetails($ride->id);

        $payload = $response->getData(true);

        $this->assertSame('0.00', $payload['pending_amount']);
    }

    private function createCancelThenApproveScenario(): LeadRide
    {
        $client = Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => 'Imran Mohammed yakub Qureshi',
            'contact_number' => '+91-9167003882',
            'status' => 1,
        ]);

        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'service_ids' => [],
            'product_ids' => [],
            'number_of_passengers' => 3,
        ]);

        $ride = LeadRide::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => '2026-09-12 00:00:00',
            'to_date' => '2026-09-12 00:00:00',
            'from_place' => 'mumbai',
            'to_place' => 'mumbai',
        ]);

        Voucher::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 1,
        ]);

        $paymentFollowup = LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 3,
            'followup_note' => 'Full payment received',
            'total_amount' => 39695,
            'received_amount' => 39695,
            'service_ids' => [],
            'extra_service_ids' => [],
            'created_at' => '2026-09-05 11:57:59',
            'updated_at' => '2026-09-05 11:57:59',
        ]);

        PaymentAuditTrail::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $paymentFollowup->id,
            'paid_amount' => 39695,
            'paid_date' => '2026-09-05 11:57:59',
            'payment_method' => 'Online Payment',
            'narration' => 'Payment approved after review',
            'payment_status' => 1,
        ]);

        LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'parent_followup_id' => $paymentFollowup->id,
            'lead_id' => $lead->id,
            'status' => 8,
            'followup_note' => 'Payment Approved (from history)',
            'total_amount' => null,
            'received_amount' => 39695,
            'service_ids' => [],
            'extra_service_ids' => [],
            'created_at' => '2026-09-05 12:01:32',
            'updated_at' => '2026-09-05 12:01:32',
        ]);

        return $ride;
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
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
            $table->uuid('lead_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->string('file')->nullable();
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
            $table->integer('status')->default(1);
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
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->decimal('vendor_service_amount', 15, 2)->nullable();
            $table->boolean('is_extra_service')->default(false);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('extra_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('extra_service')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }
}
