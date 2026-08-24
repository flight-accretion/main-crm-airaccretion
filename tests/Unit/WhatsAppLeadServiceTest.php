<?php

namespace Tests\Unit;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationQueue;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAllocationService;
use App\Services\WhatsAppLeadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppLeadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 8, 21, 11, 0, 0)
        );

        config()->set('database.default', 'sqlite');
        config()->set(
            'database.connections.sqlite',
            [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ]
        );
        config()->set('whatcrm.assignment_webhook', null);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_unmapped_whatcrm_product_assigns_to_empty_product_salesperson(): void
    {
        $mappedUser =
            $this->createSalesUser(
                'Mapped Product User'
            );

        $emptyProductUser =
            $this->createSalesUser(
                'Empty Product User'
            );

        $mappedProduct =
            $this->createProduct(
                'Private Jet'
            );

        $retailProduct =
            $this->createProduct(
                'Retail Tour'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $mappedUser->id,
            'product_id' =>
                $mappedProduct->id,
            'is_active' =>
                true,
        ]);

        $this->makeAvailable($mappedUser);
        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Retail Customer',
                    'number' => '9876543210',
                    'service' => 'Retail Tour',
                    'guest' => 2,
                    'external_id' => 'WA-RETAIL-1',
                ]);

        $lead =
            Lead::query()
                ->where(
                    'id',
                    $response['lead_id']
                )
                ->firstOrFail();

        $this->assertSame(
            'assigned',
            $response['status']
        );
        $this->assertSame(
            $emptyProductUser->id,
            $response['agent_user_id']
        );
        $this->assertSame(
            $emptyProductUser->id,
            $lead->representative_user_id
        );
        $this->assertSame(
            [$retailProduct->id],
            $lead->product_ids_array
        );
        $this->assertDatabaseMissing(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
            ]
        );
    }

    public function test_whatcrm_charter_keyword_maps_to_related_crm_product_and_charter_team(): void
    {
        $charterUser =
            $this->createSalesUser(
                'Charter Team User'
            );

        $emptyProductUser =
            $this->createSalesUser(
                'Retail Empty Product User'
            );

        $charterProduct =
            $this->createProduct(
                'Char Dham Yatra'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $charterUser->id,
            'product_id' =>
                $charterProduct->id,
            'is_active' =>
                true,
        ]);

        $this->makeAvailable($charterUser);
        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Dham Customer',
                    'number' => '9876543222',
                    'service' => 'Dham booking',
                    'guest' => 4,
                    'external_id' => 'WA-CHARTER-1',
                ]);

        $lead =
            Lead::query()
                ->where(
                    'id',
                    $response['lead_id']
                )
                ->firstOrFail();

        $this->assertSame(
            'assigned',
            $response['status']
        );
        $this->assertSame(
            $charterProduct->id,
            $response['product_id']
        );
        $this->assertSame(
            $charterUser->id,
            $response['agent_user_id']
        );
        $this->assertSame(
            $charterUser->id,
            $lead->representative_user_id
        );
        $this->assertSame(
            [$charterProduct->id],
            $lead->product_ids_array
        );
    }

    public function test_unmapped_whatcrm_charter_keyword_falls_back_to_empty_product_salesperson(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Retail Empty Product User'
            );

        $charterProduct =
            $this->createProduct(
                'Char Dham Yatra'
            );

        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Unmapped Dham Customer',
                    'number' => '9876543223',
                    'service' => 'Dham booking',
                    'guest' => 2,
                    'external_id' => 'WA-CHARTER-UNMAPPED-1',
                ]);

        $lead =
            Lead::query()
                ->where(
                    'id',
                    $response['lead_id']
                )
                ->firstOrFail();

        $this->assertSame(
            'assigned',
            $response['status']
        );
        $this->assertSame(
            $charterProduct->id,
            $response['product_id']
        );
        $this->assertSame(
            $emptyProductUser->id,
            $response['agent_user_id']
        );
        $this->assertSame(
            $emptyProductUser->id,
            $lead->representative_user_id
        );
    }

    public function test_queued_whatcrm_charter_product_later_assigns_to_charter_team(): void
    {
        $charterUser =
            $this->createSalesUser(
                'Later Charter Team User'
            );

        $emptyProductUser =
            $this->createSalesUser(
                'Available Retail Empty Product User'
            );

        $charterProduct =
            $this->createProduct(
                'Char Dham Yatra'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $charterUser->id,
            'product_id' =>
                $charterProduct->id,
            'is_active' =>
                true,
        ]);

        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Queued Dham Customer',
                    'number' => '9876543233',
                    'service' => 'Dham booking',
                    'guest' => 2,
                    'external_id' => 'WA-CHARTER-2',
                ]);

        $this->assertSame(
            'queued',
            $response['status']
        );
        $this->assertSame(
            $charterProduct->id,
            $response['product_id']
        );

        $queue =
            LeadAllocationQueue::query()
                ->where(
                    'lead_id',
                    $response['lead_id']
                )
                ->firstOrFail();

        $this->makeAvailable($charterUser);

        $result =
            app(LeadAllocationService::class)
                ->processPendingLeads();

        $lead =
            Lead::findOrFail(
                $response['lead_id']
            );

        $this->assertSame(
            1,
            $result['processed']
        );
        $this->assertSame(
            $charterUser->id,
            $lead->representative_user_id
        );
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'id' => $queue->id,
                'status' => 'assigned',
                'assigned_to' => $charterUser->id,
            ]
        );
    }

    public function test_queued_unmapped_whatcrm_product_later_assigns_to_empty_product_salesperson(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Later Empty Product User'
            );

        $this->createProduct(
            'Retail Tour'
        );

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Queued Retail Customer',
                    'number' => '9876543211',
                    'service' => 'Retail Tour',
                    'guest' => 1,
                    'external_id' => 'WA-RETAIL-2',
                ]);

        $this->assertSame(
            'queued',
            $response['status']
        );

        $queue =
            LeadAllocationQueue::query()
                ->where(
                    'lead_id',
                    $response['lead_id']
                )
                ->firstOrFail();

        $this->makeAvailable($emptyProductUser);

        $result =
            app(LeadAllocationService::class)
                ->processPendingLeads();

        $lead =
            Lead::findOrFail(
                $response['lead_id']
            );

        $this->assertSame(
            1,
            $result['processed']
        );
        $this->assertSame(
            $emptyProductUser->id,
            $lead->representative_user_id
        );
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'id' => $queue->id,
                'status' => 'assigned',
                'assigned_to' => $emptyProductUser->id,
            ]
        );
        $this->assertDatabaseHas(
            'whatsapp_lead_integrations',
            [
                'lead_id' => $lead->id,
                'status' => 'assigned',
                'assigned_user_id' => $emptyProductUser->id,
            ]
        );
    }

    private function createSalesUser(
        string $name
    ): User {
        $userType =
            UserType::query()
                ->firstOrCreate(
                    [
                        'user_type' => UserType::SALES_EXECUTIVE,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'status' => 1,
                    ]
                );

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createProduct(
        string $name
    ): Product {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product' => $name,
            'status' => 1,
        ]);
    }

    private function makeAvailable(
        User $user
    ): void {
        SalespersonAvailability::create([
            'user_id' => $user->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now(),
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
            $table->json('user_ids')->nullable();
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

        Schema::create('lead_allocation_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('office_start_time')->default('10:30');
            $table->string('office_end_time')->default('19:30');
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
            $table->timestamps();
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
}
