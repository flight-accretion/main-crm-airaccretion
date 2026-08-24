<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Models\WhatsAppAiAgentSetting;
use App\Services\WhatCrmMessageIngestionService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatCrmMessageIngestionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 8, 22, 17, 30, 0)
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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_incoming_message_reuses_active_lead_and_creates_one_followup_once(): void
    {
        $salesperson = $this->createSalesUser('Samarpit Sharma');
        $lead = $this->createActiveLead('Rajesh Sharma', '9876543210', $salesperson);

        $payload = [
            'message_id' => 'wamid.INGEST-1',
            'chat_id' => 'chat-1',
            'number' => '+91 98765 43210',
            'customer_name' => 'Rajesh Sharma',
            'message' => 'Please call me',
            'message_type' => 'text',
            'direction' => 'incoming',
            'message_at' => '2026-08-22T17:30:00+05:30',
            'status' => 'delivered',
            'raw_payload' => ['provider' => 'whatcrm'],
        ];

        $first = app(WhatCrmMessageIngestionService::class)
            ->process($payload);
        $second = app(WhatCrmMessageIngestionService::class)
            ->process($payload);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame($lead->id, $first['lead_id']);
        $this->assertSame($salesperson->id, $first['assigned_user_id']);

        $this->assertDatabaseCount('whatsapp_contacts', 1);
        $this->assertDatabaseCount('whatsapp_conversations', 1);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseCount('lead_followups', 2);

        $this->assertDatabaseHas(
            'whatsapp_contacts',
            [
                'normalized_phone' => '9876543210',
                'name' => 'Rajesh Sharma',
            ]
        );

        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'lead_id' => $lead->id,
                'assigned_user_id' => $salesperson->id,
                'last_message' => 'Please call me',
                'unread_count' => 1,
            ]
        );

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.INGEST-1',
                'direction' => 'incoming',
                'body' => 'Please call me',
            ]
        );

        $followupId = DB::table('whatsapp_messages')
            ->where('provider_message_id', 'wamid.INGEST-1')
            ->value('lead_followup_id');

        $followup = LeadFollowup::find($followupId);

        $this->assertNotNull($followup);

        $this->assertStringContainsString(
            'WhatsApp message received.',
            $followup->followup_note
        );
        $this->assertStringContainsString(
            'Please call me',
            $followup->followup_note
        );
        $this->assertSame($salesperson->id, $followup->followed_by);
    }

    public function test_same_text_with_different_provider_ids_stores_both_messages_and_followups(): void
    {
        $salesperson = $this->createSalesUser('Repeat Owner');
        $lead = $this->createActiveLead('Repeat Customer', '9876543211', $salesperson);

        $base = [
            'chat_id' => 'chat-repeat',
            'number' => '+91 98765 43211',
            'customer_name' => 'Repeat Customer',
            'message' => 'Hello',
            'message_type' => 'text',
            'direction' => 'incoming',
            'message_at' => '2026-08-22T17:30:00+05:30',
            'status' => 'delivered',
        ];

        app(WhatCrmMessageIngestionService::class)
            ->process($base + ['message_id' => 'wamid.REPEAT-1']);
        app(WhatCrmMessageIngestionService::class)
            ->process($base + ['message_id' => 'wamid.REPEAT-2']);

        $this->assertDatabaseCount('whatsapp_messages', 2);
        $this->assertDatabaseCount('lead_followups', 3);
        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'lead_id' => $lead->id,
                'unread_count' => 2,
                'last_message' => 'Hello',
            ]
        );
    }

    public function test_outgoing_message_is_stored_without_creating_lead_or_followup(): void
    {
        $agent = $this->createSalesUser('Agent Sender');

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'message_id' => 'wamid.OUT-1',
                'chat_id' => 'chat-out',
                'number' => '+91 98765 43212',
                'customer_name' => 'Outbound Customer',
                'message' => 'Sir price is Rs 35,000.',
                'message_type' => 'text',
                'direction' => 'outgoing',
                'message_at' => '2026-08-22T17:35:00+05:30',
                'status' => 'sent',
                'agent_user_id' => $agent->id,
                'agent_name' => $agent->name,
            ]);

        $this->assertFalse($result['duplicate']);
        $this->assertNull($result['lead_id']);
        $this->assertSame($agent->id, $result['assigned_user_id']);
        $this->assertDatabaseCount('leads', 0);
        $this->assertDatabaseCount('lead_followups', 0);
        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'assigned_user_id' => $agent->id,
                'unread_count' => 0,
                'last_message' => 'Sir price is Rs 35,000.',
            ]
        );
        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'direction' => 'outgoing',
                'sender_type' => 'agent',
                'sender_user_id' => $agent->id,
            ]
        );
    }

    public function test_incoming_message_without_active_lead_creates_one_assigned_lead_and_initial_followup(): void
    {
        $salesperson = $this->createSalesUser('Available Salesperson');
        $this->makeAvailable($salesperson);

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'message_id' => 'wamid.NEW-1',
                'chat_id' => 'chat-new',
                'number' => '+91 98765 43213',
                'customer_name' => 'New Customer',
                'message' => 'Need yacht in Goa',
                'message_type' => 'text',
                'direction' => 'incoming',
                'message_at' => '2026-08-22T17:40:00+05:30',
                'status' => 'delivered',
                'service' => null,
                'city' => 'Goa',
                'guest' => 4,
            ]);

        $this->assertFalse($result['duplicate']);
        $this->assertNotNull($result['lead_id']);
        $this->assertSame($salesperson->id, $result['assigned_user_id']);
        $this->assertDatabaseCount('clients', 1);
        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseCount('lead_followups', 1);
        $this->assertDatabaseHas(
            'clients',
            [
                'name' => 'New Customer',
                'contact_number' => '9876543213',
            ]
        );
        $this->assertDatabaseHas(
            'leads',
            [
                'id' => $result['lead_id'],
                'representative_user_id' => $salesperson->id,
                'number_of_passengers' => 4,
            ]
        );
        $this->assertDatabaseHas(
            'whatsapp_conversations',
            [
                'lead_id' => $result['lead_id'],
                'assigned_user_id' => $salesperson->id,
                'unread_count' => 1,
            ]
        );
    }

    public function test_minimal_whatcrm_payload_without_message_id_is_accepted(): void
    {
        $salesperson = $this->createSalesUser('Minimal Payload Owner');
        $this->makeAvailable($salesperson);

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'name' => 'Minimal Customer',
                'number' => '+91 98765 43214',
                'message' => 'Need helicopter quotation',
            ]);

        $this->assertFalse($result['duplicate']);
        $this->assertNotNull($result['conversation_id']);
        $this->assertNotNull($result['lead_id']);

        $this->assertDatabaseHas(
            'whatsapp_contacts',
            [
                'name' => 'Minimal Customer',
                'normalized_phone' => '9876543214',
            ]
        );

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => null,
                'direction' => 'incoming',
                'body' => 'Need helicopter quotation',
            ]
        );
    }

    public function test_duplicate_unprocessed_message_is_queued_for_ai_when_agent_becomes_ready(): void
    {
        $payload = [
            'message_id' => 'wamid.AI-DUPLICATE-1',
            'chat_id' => 'chat-ai-duplicate',
            'number' => '+91 98765 43215',
            'customer_name' => 'AI Retry Customer',
            'message' => 'Need help with a charter booking',
            'message_type' => 'text',
            'direction' => 'incoming',
            'message_at' => now()->toIso8601String(),
            'status' => 'delivered',
        ];

        $first = app(WhatCrmMessageIngestionService::class)
            ->process($payload);

        $this->assertFalse($first['duplicate']);
        $this->assertSame(
            'ai_disabled_or_not_configured',
            $first['ai_status']
        );
        $this->assertDatabaseCount('whatsapp_ai_reply_batches', 0);

        $setting = WhatsAppAiAgentSetting::active();
        $setting->fill([
            'enabled' => true,
            'auto_reply_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt' => 'Reply as Accretion Aviation.',
            'buffer_seconds' => 10,
            'context_message_limit' => 10000,
        ]);
        $setting->setApiKey('openai-key');
        $setting->save();

        $second = app(WhatCrmMessageIngestionService::class)
            ->process($payload);

        $this->assertTrue($second['duplicate']);
        $this->assertSame('queued', $second['ai_status']);
        $this->assertNotNull($second['ai_reply_batch_id']);
        $this->assertDatabaseCount('whatsapp_messages', 1);
        $this->assertDatabaseHas(
            'whatsapp_ai_reply_batches',
            [
                'conversation_id' => $first['conversation_id'],
                'status' => 'pending',
            ]
        );
    }

    private function createSalesUser(string $name): User
    {
        $type = UserType::query()
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
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function createActiveLead(
        string $clientName,
        string $phone,
        User $salesperson
    ): Lead {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => $clientName,
            'contact_number' => $phone,
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $salesperson->id,
            'number_of_passengers' => 1,
            'description' => 'Existing lead',
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now(),
            'followup_note' => 'Existing active followup',
            'followed_by' => $salesperson->id,
            'status' => 1,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        return $lead;
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

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->json('user_ids')->nullable();
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
            $table->uuid('lead_id');
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
            $table->unsignedInteger('buffer_seconds')->default(10);
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
