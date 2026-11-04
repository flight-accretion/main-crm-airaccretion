<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\User;
use App\Models\UserType;
use App\Services\Rides\RideRefundStatusService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerRefundTwoStepFlowTest extends TestCase
{
    private string $connection;
    private User $operationsUser;
    private LeadRide $ride;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = DB::getDefaultConnection();
        config()->set('database.default', 'customer_refund_two_step_testing');
        config()->set('database.connections.customer_refund_two_step_testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('customer_refund_two_step_testing');
        DB::reconnect('customer_refund_two_step_testing');
        DB::setDefaultConnection('customer_refund_two_step_testing');

        $this->schema();
        [$this->operationsUser, $this->ride] = $this->fixture(UserType::OPERATIONS_EXECUTIVE);
    }

    protected function tearDown(): void
    {
        DB::disconnect('customer_refund_two_step_testing');
        DB::setDefaultConnection($this->connection);
        DB::purge('customer_refund_two_step_testing');
        parent::tearDown();
    }

    public function test_assessment_rejects_a_cancellation_charge_greater_than_approved_customer_payments(): void
    {
        $this->actingAs($this->operationsUser)
            ->postJson(route('admin.rides.customer-refund.two-step.assessment', $this->ride->id), [
                'cancellation_amount' => '35000.00',
                'reason' => 'Supplier cancellation charge',
                'refund_due' => '999999.99',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cancellation_amount');

        $this->assertDatabaseCount('customer_refund_settlements', 0);
    }

    public function test_partial_payment_preserves_the_remaining_balance_and_rejects_over_refund(): void
    {
        Storage::fake('public');
        $this->actingAs($this->operationsUser)
            ->postJson(route('admin.rides.customer-refund.two-step.assessment', $this->ride->id), [
                'cancellation_amount' => '5000.00', 'reason' => 'Cancelled by customer',
            ])->assertOk()->assertJsonPath('settlement.refund_due', '25000.00');

        $this->actingAs($this->operationsUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.rides.customer-refund.two-step.payment', $this->ride->id), [
                'request_id' => (string) Str::uuid(), 'amount' => '10000.00',
                'refund_type' => 'UPI Payment', 'refund_date' => '2026-11-03',
                'refund_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])->assertOk()->assertJsonPath('settlement.label', 'Partially Refunded')
              ->assertJsonPath('settlement.remaining', '15000.00');

        $this->actingAs($this->operationsUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.rides.customer-refund.two-step.payment', $this->ride->id), [
                'request_id' => (string) Str::uuid(), 'amount' => '15000.01',
                'refund_type' => 'UPI Payment', 'refund_date' => '2026-11-03',
                'refund_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])->assertStatus(422);

        $this->assertDatabaseCount('customer_refund_transactions', 1);
        $timeline = app(RideRefundStatusService::class)->forLeadIds(collect([$this->ride->lead_id]));
        $this->assertSame('Partially Refunded', $timeline[$this->ride->lead_id]['customer']['label']);
        $this->assertSame(15000.0, $timeline[$this->ride->lead_id]['customer']['due']);
    }

    public function test_payment_request_id_is_idempotent_only_for_the_same_payment_payload(): void
    {
        Storage::fake('public');
        $this->actingAs($this->operationsUser)
            ->postJson(route('admin.rides.customer-refund.two-step.assessment', $this->ride->id), [
                'cancellation_amount' => '5000.00', 'reason' => 'Cancelled by customer',
            ])->assertOk();

        $requestId = (string) Str::uuid();
        $payload = [
            'request_id' => $requestId, 'amount' => '10000.00',
            'refund_type' => 'UPI Payment', 'refund_date' => '2026-11-03',
            'remarks' => 'First installment',
        ];

        $this->actingAs($this->operationsUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.rides.customer-refund.two-step.payment', $this->ride->id), $payload + [
                'refund_proof' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            ])->assertOk();

        $this->actingAs($this->operationsUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.rides.customer-refund.two-step.payment', $this->ride->id), $payload + [
                'refund_proof' => UploadedFile::fake()->create('retry.pdf', 100, 'application/pdf'),
            ])->assertOk()->assertJsonPath('duplicate', true);

        $this->actingAs($this->operationsUser)
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('admin.rides.customer-refund.two-step.payment', $this->ride->id), array_merge($payload, [
                'amount' => '9000.00',
                'refund_proof' => UploadedFile::fake()->create('changed.pdf', 100, 'application/pdf'),
            ]))->assertStatus(409);

        $this->assertDatabaseCount('customer_refund_transactions', 1);
    }

    public function test_sales_user_is_denied_even_when_posting_directly(): void
    {
        [$salesUser, $ride] = $this->fixture(UserType::SALES_EXECUTIVE);

        $this->actingAs($salesUser)
            ->postJson(route('admin.rides.customer-refund.two-step.assessment', $ride->id), [
                'cancellation_amount' => '1.00', 'reason' => 'Unauthorized request',
            ])
            ->assertForbidden();
    }

    private function fixture(string $role): array
    {
        $type = UserType::create(['id' => (string) Str::uuid(), 'user_type' => $role]);
        $user = User::create([
            'name' => $role . ' Tester', 'email' => Str::uuid() . '@example.test',
            'password' => bcrypt('secret'), 'user_type_id' => $type->id, 'status' => 1,
        ]);
        $lead = Lead::create(['id' => (string) Str::uuid()]);
        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(), 'lead_id' => $lead->id, 'status' => LeadFollowup::STATUS_CANCELLED,
        ]);
        $ride = LeadRide::withoutEvents(fn () => LeadRide::create([
            'id' => (string) Str::uuid(), 'lead_id' => $lead->id,
        ]));
        DB::table('payment_audit_trail')->insert([
            'id' => (string) Str::uuid(), 'lead_followup_id' => $followup->id,
            'payment_status' => 1, 'paid_amount' => 30000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$user, $ride];
    }

    private function schema(): void
    {
        Schema::create('user_types', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('user_type');
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('name'); $t->string('email'); $t->string('password');
            $t->uuid('user_type_id')->nullable(); $t->boolean('status')->default(true); $t->rememberToken(); $t->timestamps();
        });
        Schema::create('leads', function (Blueprint $t) { $t->uuid('id')->primary(); $t->timestamps(); });
        Schema::create('lead_followups', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->integer('status'); $t->timestamp('next_followup_date')->nullable(); $t->timestamps(); });
        Schema::create('lead_rides', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->timestamps(); });
        Schema::create('payment_audit_trail', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_followup_id'); $t->string('payment_status'); $t->decimal('paid_amount', 14, 2); $t->timestamps(); });
        Schema::create('lead_vendor_payments', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id'); $t->timestamps(); });
        Schema::create('lead_refunds', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_followup_id'); $t->decimal('original_amount', 14, 2)->nullable(); $t->decimal('refund_amount', 14, 2)->nullable(); $t->string('refund_type')->nullable(); $t->date('refund_date')->nullable(); $t->text('refund_reason')->nullable(); $t->string('refund_proof')->nullable(); $t->integer('status')->default(0); $t->timestamps(); });
        Schema::create('customer_refund_settlements', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('lead_id')->unique(); $t->uuid('lead_followup_id'); $t->uuid('lead_refund_id')->unique(); $t->decimal('approved_paid', 14, 2); $t->decimal('cancellation_amount', 14, 2); $t->decimal('refund_due', 14, 2); $t->text('reason'); $t->uuid('assessed_by'); $t->timestamp('assessed_at'); $t->timestamps(); });
        Schema::create('customer_refund_transactions', function (Blueprint $t) { $t->uuid('id')->primary(); $t->uuid('settlement_id'); $t->uuid('request_id')->unique(); $t->decimal('amount', 14, 2); $t->string('refund_type'); $t->date('refund_date'); $t->string('refund_proof'); $t->text('remarks')->nullable(); $t->uuid('created_by'); $t->timestamp('reversed_at')->nullable(); $t->uuid('reversed_by')->nullable(); $t->text('reversal_reason')->nullable(); $t->timestamps(); });
    }
}
