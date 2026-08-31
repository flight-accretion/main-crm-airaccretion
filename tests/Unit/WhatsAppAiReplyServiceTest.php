<?php

namespace Tests\Unit;

use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use App\Models\WhatsAppAiAgentSetting;
use App\Services\WhatCrmMessageIngestionService;
use App\Services\WhatsAppAiReplyService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppAiReplyServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 8, 23, 18, 45, 0)
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
        config()->set(
            'whatcrm.send_message_url',
            'https://web.airaccretion.com/api/v1/send-message'
        );
        config()->set('whatcrm.send_message_token', 'test-token');
        config()->set('whatcrm.default_country_code', '91');
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

    public function test_due_buffer_generates_ai_reply_assigns_detected_product_agent_and_sends_to_whatcrm(): void
    {
        $productAgent = $this->createSalesUser('Helicopter Agent');
        $this->makeAvailable($productAgent);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'product' => 'Gangtok To Bagdogra By Helicopter',
            'status' => 1,
        ]);

        EmailLeadProductUserAssignment::create([
            'user_id' => $productAgent->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $setting = WhatsAppAiAgentSetting::create([
            'enabled' => true,
            'auto_reply_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt' => 'Reply for Accretion Aviation and detect the product.',
            'buffer_seconds' => 10,
            'context_message_limit' => 2,
        ]);
        $setting->setApiKey('openai-key');
        $setting->save();

        Http::fake([
            'https://api.openai.com/v1/responses' =>
                Http::response(
                    [
                        'output_text' => json_encode([
                            'reply' =>
                                'Yes, we can help with the Gangtok to Bagdogra helicopter.',
                            'product' =>
                                'Gangtok To Bagdogra By Helicopter',
                        ]),
                        'output' => [
                            [
                                'content' => [
                                    [
                                        'type' => 'output_text',
                                        'text' => json_encode([
                                            'reply' =>
                                                'Yes, we can help with the Gangtok to Bagdogra helicopter.',
                                            'product' =>
                                                'Gangtok To Bagdogra By Helicopter',
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                    200
                ),
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.AI-OUT-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $result = app(WhatCrmMessageIngestionService::class)
            ->process([
                'message_id' => 'wamid.AI-IN-1',
                'chat_id' => 'ai-chat-1',
                'number' => '+91 98765 43219',
                'customer_name' => 'AI Customer',
                'message' => 'Need Gangtok to Bagdogra helicopter tomorrow',
                'message_type' => 'text',
                'direction' => 'incoming',
                'message_at' => now()->toIso8601String(),
                'status' => 'delivered',
            ]);

        DB::table('whatsapp_messages')->insert([
            [
                'id' => (string) Str::uuid(),
                'conversation_id' => $result['conversation_id'],
                'lead_followup_id' => null,
                'ai_reply_batch_id' => null,
                'ai_processed_at' => now()->subDay(),
                'provider_message_id' => 'wamid.AI-OLD-1',
                'direction' => 'incoming',
                'sender_type' => 'customer',
                'sender_user_id' => null,
                'message_type' => 'text',
                'body' => 'Very old message outside context window',
                'provider_status' => 'delivered',
                'message_at' => now()->subDays(2),
                'crm_read_at' => null,
                'raw_payload' => json_encode([]),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(2),
            ],
            [
                'id' => (string) Str::uuid(),
                'conversation_id' => $result['conversation_id'],
                'lead_followup_id' => null,
                'ai_reply_batch_id' => null,
                'ai_processed_at' => now()->subHour(),
                'provider_message_id' => 'wamid.AI-OLD-2',
                'direction' => 'incoming',
                'sender_type' => 'customer',
                'sender_user_id' => null,
                'message_type' => 'text',
                'body' => 'Previous context says 4 passengers',
                'provider_status' => 'delivered',
                'message_at' => now()->subHour(),
                'crm_read_at' => null,
                'raw_payload' => json_encode([]),
                'created_at' => now()->subHour(),
                'updated_at' => now()->subHour(),
            ],
        ]);

        $this->assertDatabaseHas(
            'whatsapp_ai_reply_batches',
            [
                'conversation_id' => $result['conversation_id'],
                'status' => 'pending',
            ]
        );

        Carbon::setTestNow(now()->addSeconds(11));

        $summary = app(WhatsAppAiReplyService::class)
            ->processDue();

        $this->assertSame(1, $summary['processed']);

        $lead = Lead::find($result['lead_id']);

        $this->assertNotNull($lead);
        $this->assertSame(
            [$product->id],
            $lead->product_ids
        );
        $this->assertSame(
            $productAgent->id,
            $lead->representative_user_id
        );

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.AI-OUT-1',
                'direction' => 'outgoing',
                'sender_type' => 'agent',
                'sender_user_id' => $productAgent->id,
                'body' =>
                    'Yes, we can help with the Gangtok to Bagdogra helicopter.',
            ]
        );

        $this->assertDatabaseHas(
            'whatsapp_ai_reply_batches',
            [
                'conversation_id' => $result['conversation_id'],
                'status' => 'sent',
                'assigned_user_id' => $productAgent->id,
                'detected_product' =>
                    'Gangtok To Bagdogra By Helicopter',
            ]
        );

        Http::assertSent(function ($request) {
            if (
                $request->url()
                    !== 'https://api.openai.com/v1/responses'
            ) {
                return false;
            }

            $payload = $request->data();

            return $request->hasHeader(
                    'Authorization',
                    'Bearer openai-key'
                )
                && $payload['model'] === 'gpt-4o-mini'
                && str_contains(
                    data_get($payload, 'input.0.content.0.text', ''),
                    'Need Gangtok to Bagdogra helicopter tomorrow'
                )
                && str_contains(
                    data_get($payload, 'input.0.content.0.text', ''),
                    'Previous context says 4 passengers'
                )
                && !str_contains(
                    data_get($payload, 'input.0.content.0.text', ''),
                    'Very old message outside context window'
                );
        });

        Http::assertSent(function ($request) {
            if (
                !str_contains(
                    $request->url(),
                    'https://web.airaccretion.com/api/v1/send-message'
                )
            ) {
                return false;
            }

            $payload = $request->data();
            $expectedPayload = [
                'messageObject' => [
                    'messaging_product' => 'whatsapp',
                    'to' => '919876543219',
                    'type' => 'text',
                    'text' => [
                        'body' =>
                            'Yes, we can help with the Gangtok to Bagdogra helicopter.',
                    ],
                ],
            ];

            return $payload === $expectedPayload;
        });
    }

    public function test_ai_qualification_updates_lead_service_date_guests_and_whatsapp_note(): void
    {
        $productAgent = $this->createSalesUser('Charter Agent');
        $this->makeAvailable($productAgent);

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'product' => 'Helicopter Charters',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Helicopter Charter Indore to Ujjain',
            'service_amount' => 0,
            'product_ids' => [$product->id],
            'status' => 1,
        ]);

        EmailLeadProductUserAssignment::create([
            'user_id' => $productAgent->id,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $setting = WhatsAppAiAgentSetting::create([
            'enabled' => true,
            'auto_reply_enabled' => true,
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'prompt' => 'Reply for Accretion Aviation and detect lead details.',
            'buffer_seconds' => 4,
            'context_message_limit' => 20,
        ]);
        $setting->setApiKey('openai-key');
        $setting->save();

        Http::fake([
            'https://api.openai.com/v1/responses' =>
                Http::response([
                    'output_text' => json_encode([
                        'reply' =>
                            'Thank you Abhishek, our team will share the exact itinerary shortly.',
                        'product' => 'Helicopter Charters',
                        'service' =>
                            'Helicopter Charter Indore to Ujjain',
                        'service_date' => '07-Sep-2026',
                        'guests' => 2,
                        'route' => 'Indore to Ujjain',
                    ]),
                ], 200),
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response([
                    'success' => true,
                    'metaResponse' => [
                        'messages' => [
                            [
                                'id' => 'wamid.AI-QUALIFIED-OUT',
                                'message_status' => 'accepted',
                            ],
                        ],
                    ],
                ], 200),
        ]);

        $messages = [
            'Hi',
            'Indore to ujjain temple in helicopter',
            'From indore to ujjain round trip',
            '2',
            '07/09/26',
            'Early morning bhasma aarti',
        ];

        $result = null;

        foreach ($messages as $index => $body) {
            Carbon::setTestNow(
                Carbon::create(2026, 8, 31, 5, 25, 0)
                    ->addMinutes($index)
            );

            $result = app(WhatCrmMessageIngestionService::class)
                ->process([
                    'message_id' =>
                        'wamid.AI-QUALIFIED-IN-' . $index,
                    'chat_id' => 'ai-qualified-chat',
                    'number' => '+91 98765 43220',
                    'customer_name' => 'Abhishek Zula',
                    'message' => $body,
                    'message_type' => 'text',
                    'direction' => 'incoming',
                    'message_at' => now()->toIso8601String(),
                    'status' => 'delivered',
                ]);
        }

        Carbon::setTestNow(
            Carbon::create(2026, 8, 31, 5, 36, 0)
        );

        $summary = app(WhatsAppAiReplyService::class)
            ->processDue();

        $this->assertSame(1, $summary['processed']);

        $lead = Lead::with('rideSegments')
            ->findOrFail($result['lead_id']);

        $this->assertSame([$product->id], $lead->product_ids_array);
        $this->assertSame([$service->id], $lead->service_ids_array);
        $this->assertSame(2, $lead->number_of_passengers);

        $ride = $lead->rideSegments->first();

        $this->assertNotNull($ride);
        $this->assertSame(
            '2026-09-07',
            $ride->from_date->toDateString()
        );
        $this->assertSame('Indore', $ride->from_place);
        $this->assertSame('Ujjain', $ride->to_place);

        $this->assertStringContainsString(
            'Customer: Abhishek Zula',
            $lead->description
        );
        $this->assertStringContainsString(
            'Product: Helicopter Charters',
            $lead->description
        );
        $this->assertStringContainsString(
            'Service: Helicopter Charter Indore to Ujjain',
            $lead->description
        );
        $this->assertStringContainsString(
            'Date: 07-Sep-2026',
            $lead->description
        );
        $this->assertStringContainsString(
            'Guests: 2',
            $lead->description
        );
        $this->assertStringContainsString(
            'Indore to ujjain temple in helicopter',
            $lead->description
        );
        $this->assertStringContainsString(
            'Early morning bhasma aarti',
            $lead->description
        );
    }

    private function createSalesUser(string $name): User
    {
        $type = UserType::query()
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
            'user_type_id' => $type->id,
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

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->uuid('service_address_id')->nullable();
            $table->boolean('is_tba')->default(false);
            $table->integer('total_time')->nullable();
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

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->text('description')->nullable();
            $table->integer('service_amount')->default(0);
            $table->decimal('fees_percent', 5, 2)->default(0);
            $table->text('terms_and_conditions')->nullable();
            $table->json('product_ids')->nullable();
            $table->integer('status')->default(1);
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

        Schema::create('whatcrm_agent_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('whatcrm_agent_id')->nullable();
            $table->string('whatcrm_agent_name')->nullable();
            $table->uuid('crm_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

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
