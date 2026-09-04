<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\EmailLeadProductUserAssignment;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAllocationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstagramLeadApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 3, 16, 31, 0));

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('whatcrm.token', 'shared-secret');
        config()->set('whatcrm.assignment_webhook', null);
        config()->set('whatcrm.assignment_customer_message_enabled', false);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_instagram_lead_api_accepts_array_payload_and_creates_queued_lead(): void
    {
        $response = $this
            ->withHeaders(['token' => 'shared-secret'])
            ->postJson('/api/instagram-leads', [[
                'number' => '8411026436',
                'name' => 'Eshaan',
                'IG' => 'IG-1938520776843001',
                'service' => 'Helicopter Ride in Mumbai',
                'date' => '04-SEP-2026',
                'occassion' => 'none',
                'guest' => '5',
                'type' => 'retail',
                'id' => 1,
                'createdAt' => '2026-09-03T11:01:51.314Z',
                'updatedAt' => '2026-09-03T11:04:11.325Z',
            ]]);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'queued',
                'existing_lead' => false,
            ]);

        $lead = Lead::query()
            ->with('client')
            ->findOrFail($response->json('lead_id'));

        $this->assertSame('Eshaan', $lead->client->name);
        $this->assertSame('8411026436', $lead->client->contact_number);
        $this->assertSame(5, $lead->number_of_passengers);
        $this->assertSame('none', $lead->occasion);
        $this->assertStringContainsString(
            'Lead received automatically from Instagram.',
            $lead->description
        );
        $this->assertStringContainsString(
            'Instagram ID: IG-1938520776843001',
            $lead->description
        );

        $this->assertDatabaseHas('whatsapp_lead_integrations', [
            'lead_id' => $lead->id,
            'external_id' => 'IG-1938520776843001',
            'phone' => '8411026436',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('lead_allocation_queue', [
            'lead_id' => $lead->id,
            'status' => 'queued',
            'reason' => 'instagram_retail_waiting',
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => null,
            'status' => 1,
        ]);

        $ride = DB::table('lead_rides')
            ->where('lead_id', $lead->id)
            ->first();

        $this->assertNotNull($ride);
        $this->assertSame('Mumbai', $ride->from_place);
        $this->assertSame('NA', $ride->to_place);
        $this->assertSame(
            '2026-09-04 00:00:00',
            Carbon::parse($ride->from_date)->format('Y-m-d H:i:s')
        );
    }

    public function test_queued_instagram_lead_updates_source_followup_when_later_assigned(): void
    {
        $salesperson =
            $this->createSalesUser(
                'Instagram Retail User'
            );

        $emptyProduct =
            $this->createProduct('Empty');

        $this->assignProductToUser(
            $emptyProduct,
            $salesperson
        );

        $response = $this
            ->withHeaders(['token' => 'shared-secret'])
            ->postJson('/api/instagram-leads', [[
                'number' => '8411026437',
                'name' => 'Queued Instagram Customer',
                'IG' => 'IG-1938520776843002',
                'service' => 'Helicopter Ride in Mumbai',
                'guest' => '2',
                'type' => 'retail',
            ]]);

        $lead = Lead::findOrFail(
            $response->json('lead_id')
        );

        $this->assertNull(
            $lead->representative_user_id
        );

        $this->makeAvailable($salesperson);

        $result =
            app(LeadAllocationService::class)
                ->processPendingLeads();

        $lead->refresh();

        $this->assertSame(1, $result['processed']);
        $this->assertSame(
            $salesperson->id,
            $lead->representative_user_id
        );
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $salesperson->id,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('whatsapp_lead_integrations', [
            'lead_id' => $lead->id,
            'status' => 'assigned',
            'assigned_user_id' => $salesperson->id,
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
            $table->string('contact_number')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->boolean('is_private')->default(false);
            $table->integer('is_airambulance')->default(0);
            $table->json('user_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->json('product_ids')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('number_of_passengers')->nullable();
            $table->text('description')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
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
            $table->string('total_time')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_allocation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_start_time')->default('10:30');
            $table->string('office_end_time')->default('19:20');
            $table->integer('popup_interval_minutes')->default(120);
            $table->integer('minimum_leads_before_popup')->default(1);
            $table->boolean('auto_allocation_enabled')->default(true);
            $table->string('allocation_method')->default('balanced');
            $table->timestamps();
        });

        Schema::create('salesperson_availability', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('state')->default('offline');
            $table->boolean('is_available')->default(false);
            $table->boolean('is_opted_in')->default(false);
            $table->timestamp('last_popup_at')->nullable();
            $table->timestamp('last_response_at')->nullable();
            $table->timestamps();
            $table->unique('user_id');
        });

        Schema::create('lead_allocation_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('assigned_to')->nullable();
            $table->string('status')->default('queued');
            $table->string('reason')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique('lead_id');
        });

        Schema::create('lead_allocation_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('salesperson_id')->nullable();
            $table->string('action');
            $table->string('result')->nullable();
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::create('email_lead_product_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('product_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('whatsapp_lead_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->uuid('product_id')->nullable();
            $table->string('phone', 30);
            $table->string('external_id')->nullable()->unique();
            $table->string('status', 50)->default('received');
            $table->uuid('assigned_user_id')->nullable();
            $table->boolean('callback_sent')->default(false);
            $table->unsignedInteger('callback_attempts')->default(0);
            $table->text('callback_error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('assignment_message_sent_at')->nullable();
            $table->text('assignment_message_error')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('initial_followup_created_at')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamps();
        });

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('followup_created_at')->nullable();
            $table->timestamps();
        });
    }

    private function createSalesUser(
        string $name
    ): User {
        $userType =
            UserType::query()
                ->firstOrCreate(
                    [
                        'user_type' =>
                            UserType::SALES_EXECUTIVE,
                    ],
                    [
                        'id' =>
                            (string) \Illuminate\Support\Str::uuid(),

                        'status' =>
                            1,
                    ]
                );

        return User::forceCreate([
            'id' =>
                (string) \Illuminate\Support\Str::uuid(),
            'name' => $name,
            'email' =>
                \Illuminate\Support\Str::uuid()
                . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createProduct(
        string $name
    ): Product {
        return Product::create([
            'id' =>
                (string) \Illuminate\Support\Str::uuid(),
            'product' => $name,
            'status' => 1,
        ]);
    }

    private function assignProductToUser(
        Product $product,
        User $user
    ): void {
        EmailLeadProductUserAssignment::create([
            'id' =>
                (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);
    }

    private function makeAvailable(
        User $user
    ): void {
        SalespersonAvailability::create([
            'id' =>
                (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now(),
        ]);
    }
}
