<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppInboxSendTest extends TestCase
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

    public function test_inbox_user_can_send_message_for_selected_conversation(): void
    {
        $agent = $this->createUser(
            UserType::SUPER_ADMIN,
            'Super Admin Sender'
        );
        $conversationId = $this->createConversation(
            $agent,
            '9876543218',
            'Send Customer'
        );

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.WEB-SEND-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $this->actingAs($agent);

        $this
            ->postJson(
                route(
                    'admin.whatsapp.send',
                    [
                        'conversation' => $conversationId,
                    ]
                ),
                [
                    'message_type' => 'text',
                    'message' => 'Hello from the CRM inbox',
                ]
            )
            ->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return str_contains(
                $request->url(),
                'https://web.airaccretion.com/api/v1/send-message?token=test-token'
            )
                && $payload['messageObject']['to'] === '+919876543218'
                && $payload['messageObject']['type'] === 'text'
                && $payload['messageObject']['text']['body']
                    === 'Hello from the CRM inbox'
                && $payload['messageObject']['text']['pass'] === 'yes'
                && $payload['messageObject']['text']['assigned']
                    === 'Super Admin Sender';
        });

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.WEB-SEND-1',
                'direction' => 'outgoing',
                'sender_user_id' => $agent->id,
                'body' => 'Hello from the CRM inbox',
            ]
        );
    }

    private function createUser(
        string $role,
        string $name
    ): User {
        $type = UserType::query()
            ->firstOrCreate(
                [
                    'user_type' => $role,
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

    private function createConversation(
        User $assignedUser,
        string $phone,
        string $name
    ): string {
        $contactId = (string) Str::uuid();
        $conversationId = (string) Str::uuid();

        DB::table('whatsapp_contacts')->insert([
            'id' => $contactId,
            'name' => $name,
            'normalized_phone' => $phone,
            'raw_phone' => '+91 ' . $phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('whatsapp_conversations')->insert([
            'id' => $conversationId,
            'contact_id' => $contactId,
            'assigned_user_id' => $assignedUser->id,
            'status' => 'open',
            'last_message_at' => now(),
            'unread_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $conversationId;
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
