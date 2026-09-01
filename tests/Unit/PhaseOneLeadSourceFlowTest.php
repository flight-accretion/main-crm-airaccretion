<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\EmailLeadLog;
use App\Models\EmailLeadProductUserAssignment;
use App\Models\IvrCallLog;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\EmailLeadAllocationService;
use App\Services\EmailLeadService;
use App\Services\IvrLeadService;
use App\Services\LeadAllocationService;
use App\Services\WhatCrmMessageIngestionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseOneLeadSourceFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 8, 24, 11, 0, 0)
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
        config()->set('whatcrm.ai_auto_dispatch', false);
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

    public function test_default_office_window_closes_after_720_pm(): void
    {
        $settings = LeadAllocationSetting::getActiveSettings();

        $this->assertSame('10:30', $settings->office_start_time);
        $this->assertSame('19:20', $settings->office_end_time);
        $this->assertTrue(
            app(LeadAllocationService::class)->isOfficeOpenForDebug(
                $settings,
                Carbon::create(2026, 8, 24, 19, 20, 0)
            )
        );
        $this->assertFalse(
            app(LeadAllocationService::class)->isOfficeOpenForDebug(
                $settings,
                Carbon::create(2026, 8, 24, 19, 21, 0)
            )
        );
    }

    public function test_email_source_lead_assigns_immediately_and_stores_service_date_when_office_is_open(): void
    {
        $salesperson = $this->createSalesUser(
            'Available Retail User'
        );

        $this->makeAvailable($salesperson);
        $product = $this->createProduct('Yacht in Goa');
        $this->assignProductToUser($product, $salesperson);

        $result = app(EmailLeadService::class)->process([
            'message_id' => 'email-phase-one-1',
            'uid' => 'uid-phase-one-1',
            'sender_email' => 'website@example.test',
            'recipient_email' => 'sales@example.test',
            'subject' => 'Website enquiry',
            'received_at' => now(),
            'body' => implode(PHP_EOL, [
                'Name: Email Customer',
                'Phone No: 9876543210',
                'Services: Yacht in Goa',
                'Departure Date: 2026-09-15',
                'Departure Time: 09:30',
                'Passenger: 3',
            ]),
        ]);

        $lead = Lead::findOrFail($result['lead_id']);

        $this->assertSame('created_assigned', $result['status']);
        $this->assertSame($salesperson->id, $lead->representative_user_id);
        $this->assertDatabaseMissing(
            'lead_allocation_queue',
            ['lead_id' => $lead->id]
        );
        $this->assertDatabaseHas(
            'email_lead_logs',
            [
                'lead_id' => $lead->id,
                'processing_status' => 'lead_created_assigned',
            ]
        );
        $this->assertDatabaseCount('lead_followups', 1);

        $ride = DB::table('lead_rides')
            ->where('lead_id', $lead->id)
            ->first();

        $this->assertNotNull($ride);
        $this->assertSame('Goa', $ride->from_place);
        $this->assertSame(
            '2026-09-15 09:30:00',
            Carbon::parse($ride->from_date)->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-09-15 09:30:00',
            Carbon::parse($ride->to_date)->format('Y-m-d H:i:s')
        );
    }

    public function test_email_source_lead_after_720_pm_stays_queued_until_office_start(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 24, 19, 21, 0)
        );

        $salesperson = $this->createSalesUser(
            'Next Morning Retail User'
        );

        $this->makeAvailable($salesperson);
        $this->createProduct('Yacht in Goa');
        $emptyProduct = $this->createProduct('Empty');
        $this->assignProductToUser($emptyProduct, $salesperson);

        $result = app(EmailLeadService::class)->process([
            'message_id' => 'email-phase-one-queue',
            'uid' => 'uid-phase-one-queue',
            'sender_email' => 'website@example.test',
            'recipient_email' => 'sales@example.test',
            'subject' => 'Late enquiry',
            'received_at' => now(),
            'body' => implode(PHP_EOL, [
                'Name: Late Customer',
                'Phone No: 9876543211',
                'Services: Yacht in Goa',
                'Departure Date: 2026-09-16',
                'Passenger: 2',
            ]),
        ]);

        $lead = Lead::findOrFail($result['lead_id']);

        $this->assertSame('created_queued', $result['status']);
        $this->assertNull($lead->representative_user_id);
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followed_by' => null,
                'status' => 1,
            ]
        );
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
                'status' => 'queued',
            ]
        );

        Carbon::setTestNow(
            Carbon::create(2026, 8, 25, 10, 30, 0)
        );

        SalespersonAvailability::query()
            ->where('user_id', $salesperson->id)
            ->update(['last_response_at' => now()]);

        $processed = app(LeadAllocationService::class)
            ->processPendingLeads();

        $lead->refresh();

        $this->assertSame(1, $processed['processed']);
        $this->assertSame($salesperson->id, $lead->representative_user_id);
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
                'status' => 'assigned',
                'assigned_to' => $salesperson->id,
            ]
        );
    }

    public function test_whatcrm_message_source_stores_service_date_on_created_lead(): void
    {
        $salesperson = $this->createSalesUser(
            'WhatsApp Retail User'
        );

        $this->makeAvailable($salesperson);
        $product = $this->createProduct('Yacht in Goa');
        $this->assignProductToUser($product, $salesperson);

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'message_id' => 'wamid.PHASE1-RIDE',
                'chat_id' => 'phase1-chat',
                'number' => '919876543212',
                'name' => 'WhatsApp Customer',
                'message' => 'Need yacht details',
                'service' => 'Yacht in Goa',
                'date' => '2026-09-20',
                'city' => 'Goa',
                'guest' => 4,
                'direction' => 'incoming',
                'message_at' => now()->toIso8601String(),
            ]);

        $ride = DB::table('lead_rides')
            ->where('lead_id', $result['lead_id'])
            ->first();

        $this->assertNotNull($ride);
        $this->assertSame('Goa', $ride->from_place);
        $this->assertSame(
            '2026-09-20 00:00:00',
            Carbon::parse($ride->from_date)->format('Y-m-d H:i:s')
        );
    }

    public function test_whatcrm_message_without_identified_product_keeps_crm_fields_empty_and_defaults_ride_data(): void
    {
        $emptyProductUser = $this->createSalesUser(
            'Empty Product User'
        );

        $emptyProduct = $this->createProduct('Empty');
        $mappedRetailProduct = $this->createProduct('Yacht in Goa');

        $this->assignProductToUser($emptyProduct, $emptyProductUser);
        $this->assignProductToUser($mappedRetailProduct, $emptyProductUser);
        $this->makeAvailable($emptyProductUser);

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'message_id' => 'wamid.PHASE1-UNKNOWN',
                'chat_id' => 'phase1-chat-unknown',
                'number' => '919876543213',
                'name' => 'Unknown WhatsApp Customer',
                'message' => 'Hi',
                'direction' => 'incoming',
                'message_at' => now()->toIso8601String(),
            ]);

        $lead = Lead::findOrFail($result['lead_id']);
        $ride = DB::table('lead_rides')
            ->where('lead_id', $lead->id)
            ->first();

        $this->assertSame([], $lead->product_ids_array);
        $this->assertSame([], $lead->service_ids_array);
        $this->assertSame($emptyProductUser->id, $lead->representative_user_id);
        $this->assertNotNull($ride);
        $this->assertSame('NA', $ride->from_place);
        $this->assertSame('NA', $ride->to_place);
        $this->assertSame(
            '2026-08-24 11:00:00',
            Carbon::parse($ride->from_date)->format('Y-m-d H:i:s')
        );
    }

    public function test_ivr_source_lead_after_hours_defaults_active_status_and_ride_data(): void
    {
        Carbon::setTestNow(
            Carbon::create(2026, 8, 24, 19, 21, 0)
        );

        $callLog = IvrCallLog::create([
            'id' => (string) Str::uuid(),
            'provider_call_id' => 'ivr-phase-one-queued',
            'cli' => '9876543214',
            'normalized_phone' => '9876543214',
            'raw_dtmf' => '3',
            'dial_status' => 'timeout',
            'call_start_at' => now(),
            'processing_status' => 'received',
        ]);

        $result = app(IvrLeadService::class)
            ->processCallLog($callLog);

        $lead = Lead::findOrFail($result['lead_id']);
        $ride = DB::table('lead_rides')
            ->where('lead_id', $lead->id)
            ->first();

        $this->assertSame('created_queued', $result['status']);
        $this->assertNull($lead->representative_user_id);
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followed_by' => null,
                'status' => 1,
            ]
        );
        $this->assertNotNull($ride);
        $this->assertSame('NA', $ride->from_place);
        $this->assertSame('NA', $ride->to_place);
        $this->assertSame(
            '2026-08-24 19:21:00',
            Carbon::parse($ride->from_date)->format('Y-m-d H:i:s')
        );
    }

    public function test_email_unmapped_charter_product_uses_configured_charter_team_before_retail_fallback(): void
    {
        $charterUser = $this->createSalesUser(
            'Configured Charter User'
        );
        $retailUser = $this->createSalesUser(
            'Retail Empty User'
        );

        $mappedCharterProduct = $this->createProduct(
            'Char Dham Yatra'
        );
        $requestedCharterProduct = $this->createProduct(
            'Flower Shower'
        );

        EmailLeadProductUserAssignment::create([
            'user_id' => $charterUser->id,
            'product_id' => $mappedCharterProduct->id,
            'is_active' => true,
        ]);

        $this->makeAvailable($charterUser);
        $this->makeAvailable($retailUser);

        $lead = $this->createLeadWithEmailLog(
            $requestedCharterProduct,
            'Flower Shower proposal'
        );

        $salesperson = app(EmailLeadAllocationService::class)
            ->pickSalesperson(
                $lead,
                LeadAllocationSetting::getActiveSettings()
            );

        $this->assertNotNull($salesperson);
        $this->assertSame($charterUser->id, $salesperson->id);
    }

    public function test_legacy_whatcrm_lead_api_can_be_disabled_without_removing_route(): void
    {
        config()->set('whatcrm.token', 'shared-secret');
        config()->set('whatcrm.legacy_lead_api_enabled', false);

        $response = $this
            ->withHeaders(['token' => 'shared-secret'])
            ->postJson(
                '/api/whatsapp-leads',
                [
                    'name' => 'Legacy Customer',
                    'number' => '9876543219',
                    'service' => 'Yacht in Goa',
                ]
            );

        $response->assertStatus(410);
        $response->assertJson([
            'success' => false,
            'message' => 'Legacy WhatCRM lead API is disabled. Use /api/whatcrm/messages.',
        ]);
    }

    private function createSalesUser(string $name): User
    {
        $userType = UserType::query()
            ->firstOrCreate(
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
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function makeAvailable(User $user): void
    {
        SalespersonAvailability::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'state' => 'available',
            'is_available' => true,
            'is_opted_in' => true,
            'last_response_at' => now(),
        ]);
    }

    private function createProduct(string $name): Product
    {
        return Product::create([
            'id' => (string) Str::uuid(),
            'product' => $name,
            'status' => 1,
        ]);
    }

    private function assignProductToUser(
        Product $product,
        User $user
    ): void {
        EmailLeadProductUserAssignment::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);
    }

    private function createLeadWithEmailLog(
        Product $product,
        string $serviceName
    ): Lead {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Routing Customer',
            'contact_number' => '9876543218',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => null,
            'product_ids' => [$product->id],
        ]);

        EmailLeadLog::create([
            'id' => (string) Str::uuid(),
            'message_id' => (string) Str::uuid(),
            'imap_uid' => (string) random_int(1000, 9999),
            'sender_email' => 'website@example.test',
            'recipient_email' => 'sales@example.test',
            'subject' => 'Lead',
            'customer_name' => 'Routing Customer',
            'customer_phone' => '9876543218',
            'service_name' => $serviceName,
            'passenger_count' => 1,
            'email_body' => $serviceName,
            'parsed_data' => [],
            'lead_id' => $lead->id,
            'processing_status' => 'lead_created_queued',
            'received_at' => now(),
        ]);

        return $lead;
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

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('message_id')->nullable();
            $table->string('imap_uid')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('recipient_email')->nullable();
            $table->string('subject')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('service_name')->nullable();
            $table->date('departure_date')->nullable();
            $table->string('departure_time')->nullable();
            $table->integer('passenger_count')->nullable();
            $table->text('email_body')->nullable();
            $table->json('parsed_data')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->nullable();
            $table->text('processing_message')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('followup_created_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id')->nullable();
            $table->uuid('ivr_call_type_id')->nullable();
            $table->string('call_type_code')->nullable();
            $table->string('dni')->nullable();
            $table->string('cli')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('raw_dtmf')->nullable();
            $table->string('agent_name')->nullable();
            $table->string('agent_number')->nullable();
            $table->string('dial_status')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('call_end_at')->nullable();
            $table->integer('duration_sec')->nullable();
            $table->integer('og_duration_sec')->nullable();
            $table->text('voice_url')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('initial_followup_created_at')->nullable();
            $table->string('processing_status')->nullable();
            $table->text('processing_message')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        $this->createWhatsAppTables();
    }

    private function createWhatsAppTables(): void
    {
        Schema::create('whatsapp_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('normalized_phone', 30)->unique();
            $table->string('raw_phone', 50)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('contact_id');
            $table->uuid('lead_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('whatcrm_chat_id')->nullable();
            $table->string('status', 30)->default('open');
            $table->text('last_message')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->uuid('lead_followup_id')->nullable();
            $table->uuid('ai_reply_batch_id')->nullable();
            $table->timestamp('ai_processed_at')->nullable();
            $table->string('provider_message_id')->nullable()->unique();
            $table->string('direction', 20);
            $table->string('sender_type', 30);
            $table->uuid('sender_user_id')->nullable();
            $table->string('message_type', 30)->default('text');
            $table->text('body')->nullable();
            $table->string('provider_status', 50)->nullable();
            $table->timestamp('message_at')->nullable();
            $table->timestamp('crm_read_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_ai_agent_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_reply_enabled')->default(false);
            $table->string('provider', 50)->default('openai');
            $table->string('model')->default('gpt-4o-mini');
            $table->text('prompt')->nullable();
            $table->text('api_key_encrypted')->nullable();
            $table->unsignedInteger('buffer_seconds')->default(4);
            $table->unsignedInteger('context_message_limit')->default(10000);
            $table->timestamps();
        });

        Schema::create('whatsapp_ai_reply_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->string('status', 30)->default('pending');
            $table->timestamp('process_after')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->uuid('response_message_id')->nullable();
            $table->uuid('assigned_user_id')->nullable();
            $table->string('detected_product')->nullable();
            $table->text('error')->nullable();
            $table->json('message_ids')->nullable();
            $table->timestamps();
        });
    }
}
