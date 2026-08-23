<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserType;
use App\Services\WhatCrmOutboundMessageService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatCrmOutboundMessageServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_sends_text_message_to_whatcrm_and_stores_outgoing_chat_message(): void
    {
        $agent = $this->createSalesUser('CRM Agent');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-OUT-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $result = app(WhatCrmOutboundMessageService::class)
            ->sendText([
                'number' => '9876543215',
                'name' => 'Outbound Customer',
                'message' => 'Hello from CRM',
                'agent_user_id' => $agent->id,
            ]);

        $this->assertTrue($result['success']);
        $this->assertSame(
            'wamid.CRM-OUT-1',
            $result['provider_message_id']
        );

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $request->method() === 'POST'
                && str_contains(
                    $request->url(),
                    'https://web.airaccretion.com/api/v1/send-message?token=test-token'
                )
                && $payload['messageObject']['messaging_product'] === 'whatsapp'
                && $payload['messageObject']['to'] === '+919876543215'
                && $payload['messageObject']['type'] === 'text'
                && $payload['messageObject']['text']['body'] === 'Hello from CRM';
        });

        $this->assertDatabaseHas(
            'whatsapp_contacts',
            [
                'name' => 'Outbound Customer',
                'normalized_phone' => '9876543215',
            ]
        );

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.CRM-OUT-1',
                'direction' => 'outgoing',
                'sender_type' => 'agent',
                'sender_user_id' => $agent->id,
                'body' => 'Hello from CRM',
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
    }
}
