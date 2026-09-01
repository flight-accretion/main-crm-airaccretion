<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\RepairUnassignedNaLeadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepairUnassignedNaLeadServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 9, 1, 11, 0, 0)
        );

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        config()->set('whatcrm.assignment_webhook', null);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        Cache::flush();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dry_run_reports_duplicate_na_lead_without_deleting(): void
    {
        $salesperson = $this->createSalesUser('Active Owner');
        $goodLead = $this->createLeadWithClient(
            [
                'name' => 'Duplicate Customer',
                'contact_number' => '+91-9000000001',
            ],
            [
                'representative_user_id' => $salesperson->id,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]
        );
        $badLead = $this->createLeadWithClient(
            [
                'name' => 'Duplicate Customer',
                'contact_number' => '9000000001',
            ],
            [
                'representative_user_id' => null,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        $this->createActiveFollowup($goodLead, $salesperson);

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: false);

        $this->assertSame(2, Lead::query()->count());
        $this->assertSame(1, $result['would_delete_duplicates']);
        $this->assertSame(0, $result['deleted_duplicates']);
        $this->assertDatabaseHas('leads', ['id' => $badLead->id]);
    }

    public function test_commit_deletes_only_unused_duplicate_na_lead_when_active_lead_exists(): void
    {
        $salesperson = $this->createSalesUser('Active Owner');
        $goodLead = $this->createLeadWithClient(
            [
                'name' => 'Duplicate Customer',
                'contact_number' => '+91-9000000002',
            ],
            [
                'representative_user_id' => $salesperson->id,
                'description' => 'Good active lead',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(3),
            ]
        );
        $badLead = $this->createLeadWithClient(
            [
                'name' => 'Duplicate Customer',
                'contact_number' => '9000000002',
            ],
            [
                'representative_user_id' => null,
                'description' => 'Lead received automatically but duplicated.',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ]
        );

        $this->createActiveFollowup($goodLead, $salesperson);

        DB::table('email_lead_logs')->insert([
            'id' => (string) Str::uuid(),
            'message_id' => 'old-email-duplicate',
            'customer_phone' => '9000000002',
            'sender_email' => 'website@example.test',
            'lead_id' => $badLead->id,
            'processing_status' => 'lead_created_queued',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: true);

        $this->assertSame(1, $result['deleted_duplicates']);
        $this->assertDatabaseMissing('leads', ['id' => $badLead->id]);
        $this->assertDatabaseHas('leads', ['id' => $goodLead->id]);
        $this->assertDatabaseHas('email_lead_logs', [
            'message_id' => 'old-email-duplicate',
            'lead_id' => $goodLead->id,
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $goodLead->id,
            'status' => 1,
            'followed_by' => $salesperson->id,
        ]);
        $this->assertTrue(
            LeadFollowup::query()
                ->where('lead_id', $goodLead->id)
                ->where(
                    'followup_note',
                    'like',
                    'Merged old duplicate N/A lead%'
                )
                ->exists()
        );
    }

    public function test_duplicate_na_lead_with_business_activity_is_skipped_not_deleted(): void
    {
        $salesperson = $this->createSalesUser('Active Owner');
        $goodLead = $this->createLeadWithClient(
            [
                'name' => 'Unsafe Duplicate Customer',
                'contact_number' => '+91-9000000003',
            ],
            [
                'representative_user_id' => $salesperson->id,
            ]
        );
        $badLead = $this->createLeadWithClient(
            [
                'name' => 'Unsafe Duplicate Customer',
                'contact_number' => '9000000003',
            ],
            [
                'representative_user_id' => null,
            ]
        );

        $this->createActiveFollowup($goodLead, $salesperson);

        DB::table('lead_passengers')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $badLead->id,
            'name' => 'Existing Passenger',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: true);

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['deleted_duplicates']);
        $this->assertDatabaseHas('leads', ['id' => $badLead->id]);
    }

    public function test_single_na_lead_is_activated_and_assigned_to_present_empty_product_handler(): void
    {
        $salesperson = $this->createSalesUser('Empty Handler');
        $this->makeAvailable($salesperson);
        $this->assignProductToUser(
            $this->createProduct('Empty'),
            $salesperson
        );

        $lead = $this->createLeadWithClient(
            [
                'name' => 'Single Unknown Customer',
                'contact_number' => '+91-9000000004',
            ],
            [
                'representative_user_id' => null,
                'description' => 'Need details',
                'product_ids' => null,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(4),
            ]
        );

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: true);

        $lead->refresh();

        $this->assertSame(1, $result['activated']);
        $this->assertSame(1, $result['assigned']);
        $this->assertSame($salesperson->id, $lead->representative_user_id);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $salesperson->id,
            'status' => 1,
        ]);
        $this->assertDatabaseMissing('lead_allocation_queue', [
            'lead_id' => $lead->id,
        ]);
    }

    public function test_single_na_lead_is_activated_and_queued_when_empty_handler_is_not_present(): void
    {
        $salesperson = $this->createSalesUser('Absent Empty Handler');
        $this->assignProductToUser(
            $this->createProduct('Empty'),
            $salesperson
        );

        $lead = $this->createLeadWithClient(
            [
                'name' => 'Queued Unknown Customer',
                'contact_number' => '+91-9000000005',
            ],
            [
                'representative_user_id' => null,
                'description' => 'Need details',
                'product_ids' => null,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]
        );

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: true);

        $lead->refresh();

        $this->assertSame(1, $result['activated']);
        $this->assertSame(1, $result['queued']);
        $this->assertNull($lead->representative_user_id);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => null,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('lead_allocation_queue', [
            'lead_id' => $lead->id,
            'status' => 'queued',
            'reason' => 'whatsapp_retail_waiting',
        ]);
    }

    public function test_single_unassigned_lead_with_null_followup_status_is_reactivated(): void
    {
        $salesperson = $this->createSalesUser('Empty Handler');
        $this->makeAvailable($salesperson);
        $this->assignProductToUser(
            $this->createProduct('Empty'),
            $salesperson
        );

        $lead = $this->createLeadWithClient(
            [
                'name' => 'Null Status Customer',
                'contact_number' => '+91-9000000007',
            ],
            [
                'representative_user_id' => null,
                'description' => 'Need details',
                'product_ids' => null,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(5),
            ]
        );

        LeadFollowup::query()->create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => null,
            'followup_note' => 'Old invalid follow-up status',
            'followed_by' => null,
            'status' => null,
        ]);

        $result = app(RepairUnassignedNaLeadService::class)
            ->repair(days: 10, commit: true);

        $lead->refresh();
        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->firstOrFail();

        $this->assertSame(1, $result['activated']);
        $this->assertSame(1, $result['assigned']);
        $this->assertSame($salesperson->id, $lead->representative_user_id);
        $this->assertSame(1, $followup->status);
        $this->assertSame($salesperson->id, $followup->followed_by);
        $this->assertNotNull($followup->next_followup_date);
    }

    public function test_artisan_command_defaults_to_dry_run(): void
    {
        $salesperson = $this->createSalesUser('Active Owner');
        $goodLead = $this->createLeadWithClient(
            [
                'name' => 'Command Customer',
                'contact_number' => '+91-9000000006',
            ],
            [
                'representative_user_id' => $salesperson->id,
            ]
        );
        $badLead = $this->createLeadWithClient(
            [
                'name' => 'Command Customer',
                'contact_number' => '9000000006',
            ],
            [
                'representative_user_id' => null,
            ]
        );

        $this->createActiveFollowup($goodLead, $salesperson);

        $this->artisan('leads:repair-unassigned-na', ['--days' => 10])
            ->expectsOutput('Dry run: yes')
            ->expectsOutput('Would delete duplicate N/A leads: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('leads', ['id' => $badLead->id]);
    }

    private function createSalesUser(string $name): User
    {
        $type = UserType::query()->firstOrCreate(
            ['user_type' => UserType::SALES_EXECUTIVE],
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
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function makeAvailable(User $user): void
    {
        SalespersonAvailability::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now(),
            'last_popup_at' => now(),
        ]);
    }

    private function createProduct(string $name): Product
    {
        return Product::query()->create([
            'id' => (string) Str::uuid(),
            'product' => $name,
            'status' => 1,
        ]);
    }

    private function assignProductToUser(Product $product, User $user): void
    {
        EmailLeadProductUserAssignment::query()->create([
            'id' => (string) Str::uuid(),
            'product_id' => $product->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    private function createLeadWithClient(
        array $clientData,
        array $leadData = []
    ): Lead {
        $client = Client::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Test Client',
            'contact_number' => '+91-9000000000',
            'status' => 1,
        ], $clientData));

        return Lead::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => null,
            'service_ids' => null,
            'product_ids' => null,
            'number_of_passengers' => 1,
            'description' => 'Old automation lead',
            'created_at' => now(),
            'updated_at' => now(),
        ], $leadData));
    }

    private function createActiveFollowup(Lead $lead, User $user): void
    {
        LeadFollowup::query()->create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Good active lead',
            'followed_by' => $user->id,
            'status' => 1,
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
            $table->integer('status')->nullable();
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
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->json('user_ids')->nullable();
            $table->boolean('is_private')->default(false);
            $table->boolean('is_airambulance')->default(false);
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
        });

        Schema::create('email_lead_product_user_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('product_id');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('lead_allocation_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->uuid('assigned_to')->nullable();
            $table->string('status')->default('queued');
            $table->string('reason')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
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

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('message_id')->unique();
            $table->string('sender_email');
            $table->string('recipient_email')->nullable();
            $table->text('subject')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('service_name')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_time')->nullable();
            $table->integer('passenger_count')->nullable();
            $table->longText('email_body')->nullable();
            $table->json('parsed_data')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->default('received');
            $table->text('processing_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('followup_created_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id')->unique();
            $table->string('normalized_phone')->nullable();
            $table->string('raw_dtmf')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_number')->nullable();
            $table->string('dial_status')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->default('received');
            $table->timestamps();
        });

        Schema::create('whatsapp_lead_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->uuid('product_id')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('external_id')->nullable()->unique();
            $table->string('status')->default('received');
            $table->uuid('assigned_user_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('normalized_phone')->unique();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('status')->default('open');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('voucher_id')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_vendor_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        Schema::create('skyrack_lead_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->timestamps();
        });
    }
}
