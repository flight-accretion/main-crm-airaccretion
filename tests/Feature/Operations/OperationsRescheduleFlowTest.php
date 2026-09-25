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

class OperationsRescheduleFlowTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.default', 'operations_reschedule_testing');
        config()->set('database.connections.operations_reschedule_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('operations_reschedule_testing');
        DB::reconnect('operations_reschedule_testing');
        DB::setDefaultConnection('operations_reschedule_testing');

        Carbon::setTestNow(Carbon::create(2026, 9, 24, 12, 0, 0));

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('operations_reschedule_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('operations_reschedule_testing');

        parent::tearDown();
    }

    public function test_operations_reschedule_shows_ride_status_actions_without_company_tax_columns(): void
    {
        $viewer = $this->user('Ops Viewer', UserType::OPERATIONS_EXECUTIVE);
        $leadId = $this->lead('Reschedule Customer', '9876543210', $viewer->id);
        $rideId = $this->ride($leadId, '2026-10-01 10:00:00', '2026-10-01 10:30:00');

        $this->followup($leadId, 7, 12500, 2500);
        $this->voucher($leadId);
        $this->operationCase($leadId, $rideId, 'pending');

        $response = $this
            ->actingAs($viewer)
            ->get(route('admin.operations.reschedules.index'));

        $response->assertOk();
        $response->assertSee('Operations Reschedule');
        $response->assertSee('operations-reschedule-table', false);
        $response->assertSee('Original Service Date');
        $response->assertSee('Reschedule Customer');
        $response->assertSee('9876543210');
        $response->assertSee('View');
        $response->assertSee('Edit');
        $response->assertSee('Cancel');
        $response->assertDontSee('<th data-priority="7">Company Name</th>', false);
        $response->assertDontSee('<th data-priority="8">GST Number</th>', false);
    }

    public function test_operations_reschedule_edit_updates_trip_data_and_completes_pending_case(): void
    {
        $viewer = $this->user('Ops Editor', UserType::OPERATIONS_EXECUTIVE);
        $leadId = $this->lead('Editable Customer', '9876500000', $viewer->id);
        $rideId = $this->ride($leadId, '2026-10-01 10:00:00', '2026-10-01 10:30:00');
        $caseId = $this->operationCase($leadId, $rideId, 'pending');

        $this->followup($leadId, 7, 15000, 5000);
        $this->voucher($leadId);

        $response = $this
            ->actingAs($viewer)
            ->post(route('admin.operations.reschedules.update', $rideId), [
                'from_date' => '2026-10-05 14:00',
                'to_date' => '2026-10-05 14:45',
                'from_place' => 'Indore Airport',
                'to_place' => 'Ujjain Helipad',
                'total_time' => '0.75',
                'no_date' => '0',
            ]);

        $response->assertRedirect(route('admin.operations.reschedules.index'));

        $this->assertDatabaseHas('lead_rides', [
            'id' => $rideId,
            'from_date' => '2026-10-05 14:00:00',
            'to_date' => '2026-10-05 14:45:00',
            'from_place' => 'Indore Airport',
            'to_place' => 'Ujjain Helipad',
            'total_time' => '0.75',
            'is_tba' => 0,
        ]);

        $this->assertDatabaseHas('operation_cases', [
            'id' => $caseId,
            'type' => 'reschedule',
            'status' => 'completed',
            'completed_by' => $viewer->id,
        ]);
    }

    public function test_operations_reschedule_cancel_uses_ride_status_cancel_and_completes_pending_case(): void
    {
        $viewer = $this->user('Ops Canceller', UserType::OPERATIONS_EXECUTIVE);
        $leadId = $this->lead('Cancel Customer', '9876511111', $viewer->id);
        $rideId = $this->ride($leadId, '2026-10-02 10:00:00', '2026-10-02 10:30:00');
        $caseId = $this->operationCase($leadId, $rideId, 'pending');

        $this->followup($leadId, 7, 18000, 6000);
        $this->voucher($leadId);

        $response = $this
            ->actingAs($viewer)
            ->post(route('admin.operations.reschedules.cancel', $rideId), [
                'total_amount' => 18000,
            ]);

        $response->assertRedirect(route('admin.operations.reschedules.index'));

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $leadId,
            'status' => 2,
            'followup_note' => 'Ride has been cancelled.',
            'followed_by' => $viewer->id,
            'total_amount' => 18000,
        ]);

        $this->assertDatabaseHas('operation_cases', [
            'id' => $caseId,
            'type' => 'reschedule',
            'status' => 'completed',
            'completed_by' => $viewer->id,
        ]);

        $this->assertDatabaseHas('operation_cases', [
            'lead_id' => $leadId,
            'type' => 'cancelled',
            'status' => 'pending',
        ]);
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
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
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
            $table->uuid('service_address_id')->nullable();
            $table->boolean('is_tba')->default(false);
            $table->decimal('total_time', 8, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('parent_followup_id')->nullable();
            $table->uuid('operation_case_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('followed_by')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->decimal('service_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->json('service_details')->nullable();
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

        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_case_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
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
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('voucher_id')->nullable();
            $table->string('invoice_id')->nullable();
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

    private function lead(string $customerName, string $phone, string $salesUserId): string
    {
        $clientId = (string) Str::uuid();
        $leadId = (string) Str::uuid();

        DB::table('clients')->insert([
            'id' => $clientId,
            'name' => $customerName,
            'company_name' => 'Hidden Company',
            'gst_number' => 'GST-HIDDEN',
            'contact_number' => $phone,
            'alternate_number' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leads')->insert([
            'id' => $leadId,
            'client_id' => $clientId,
            'representative_user_id' => $salesUserId,
            'service_ids' => null,
            'product_ids' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $leadId;
    }

    private function ride(string $leadId, string $fromDate, string $toDate): string
    {
        $rideId = (string) Str::uuid();

        DB::table('lead_rides')->insert([
            'id' => $rideId,
            'lead_id' => $leadId,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'from_place' => 'Old From',
            'to_place' => 'Old To',
            'service_address_id' => null,
            'is_tba' => 0,
            'total_time' => '0.50',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $rideId;
    }

    private function followup(
        string $leadId,
        int $status,
        float $totalAmount,
        float $receivedAmount
    ): void {
        DB::table('lead_followups')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'parent_followup_id' => null,
            'operation_case_id' => null,
            'next_followup_date' => now(),
            'followup_note' => 'Reschedule needed',
            'status' => $status,
            'followed_by' => null,
            'service_ids' => null,
            'extra_service_ids' => null,
            'total_amount' => $totalAmount,
            'received_amount' => $receivedAmount,
            'service_amount' => null,
            'discount_amount' => null,
            'service_details' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function voucher(string $leadId): void
    {
        DB::table('vouchers')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'operation_team_user_id' => null,
            'extra_upload' => null,
            'naration' => null,
            'status' => 1,
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function operationCase(string $leadId, string $rideId, string $status): string
    {
        $caseId = (string) Str::uuid();

        DB::table('operation_cases')->insert([
            'id' => $caseId,
            'lead_id' => $leadId,
            'type' => 'reschedule',
            'status' => $status,
            'assigned_to' => null,
            'created_by' => null,
            'completed_by' => null,
            'opened_at' => now(),
            'completed_at' => null,
            'next_followup_at' => null,
            'note' => 'Customer requested reschedule',
            'metadata' => json_encode([
                'ride_id' => $rideId,
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $caseId;
    }
}
