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

    public function test_sends_text_message_with_assignment_metadata_to_whatcrm(): void
    {
        $agent = $this->createSalesUser('Assigned Agent');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-ASSIGN-1',
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
                'number' => '9876543216',
                'name' => 'Assigned Customer',
                'message' => 'I can help you with this booking.',
                'agent_user_id' => $agent->id,
            ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'text'
                && $payload['messageObject']['text']['body']
                    === 'I can help you with this booking.'
                && $payload['messageObject']['text']['pass'] === 'yes'
                && $payload['messageObject']['text']['assigned']
                    === 'Assigned Agent';
        });
    }

    public function test_sends_image_message_payload_to_whatcrm_and_stores_outgoing_chat_message(): void
    {
        $agent = $this->createSalesUser('Media Agent');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-IMAGE-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $result = app(WhatCrmOutboundMessageService::class)
            ->sendMessage([
                'number' => '9876543217',
                'name' => 'Media Customer',
                'message_type' => 'image',
                'media_url' => 'https://example.test/quote.jpg',
                'caption' => 'Helicopter quote',
                'agent_user_id' => $agent->id,
            ]);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'image'
                && $payload['messageObject']['image']['link']
                    === 'https://example.test/quote.jpg'
                && $payload['messageObject']['image']['caption']
                    === 'Helicopter quote'
                && $payload['messageObject']['image']['pass'] === 'yes'
                && $payload['messageObject']['image']['assigned']
                    === 'Media Agent';
        });

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.CRM-IMAGE-1',
                'direction' => 'outgoing',
                'message_type' => 'image',
                'body' => 'Helicopter quote',
            ]
        );
    }

    public function test_sends_video_audio_contact_and_location_payloads_to_whatcrm(): void
    {
        $agent = $this->createSalesUser('Multi Type Agent');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::sequence()
                    ->push([
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-VIDEO-1',
                                ],
                            ],
                        ],
                    ])
                    ->push([
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-AUDIO-1',
                                ],
                            ],
                        ],
                    ])
                    ->push([
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-CONTACT-1',
                                ],
                            ],
                        ],
                    ])
                    ->push([
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.CRM-LOCATION-1',
                                ],
                            ],
                        ],
                    ]),
        ]);

        $service = app(WhatCrmOutboundMessageService::class);

        $service->sendMessage([
            'number' => '9876543220',
            'message_type' => 'video',
            'media_url' => 'https://example.test/video.mp4',
            'caption' => 'Ride video',
            'agent_user_id' => $agent->id,
        ]);

        $service->sendMessage([
            'number' => '9876543221',
            'message_type' => 'audio',
            'media_url' => 'https://example.test/audio.mp3',
            'agent_user_id' => $agent->id,
        ]);

        $service->sendMessage([
            'number' => '9876543222',
            'message_type' => 'contact',
            'contacts' => [
                [
                    'name' => [
                        'formatted_name' => 'Accretion Support',
                    ],
                    'phones' => [
                        [
                            'phone' => '+919999999999',
                            'type' => 'CELL',
                        ],
                    ],
                ],
            ],
            'agent_user_id' => $agent->id,
        ]);

        $service->sendMessage([
            'number' => '9876543223',
            'message_type' => 'location',
            'latitude' => '27.3314',
            'longitude' => '88.6138',
            'name' => 'Gangtok Helipad',
            'address' => 'Gangtok',
            'agent_user_id' => $agent->id,
        ]);

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'video'
                && $payload['messageObject']['video']['link']
                    === 'https://example.test/video.mp4'
                && $payload['messageObject']['video']['assigned']
                    === 'Multi Type Agent';
        });

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'audio'
                && $payload['messageObject']['audio']['link']
                    === 'https://example.test/audio.mp3'
                && $payload['messageObject']['audio']['pass'] === 'yes';
        });

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'contacts'
                && $payload['messageObject']['contacts'][0]['name']['formatted_name']
                    === 'Accretion Support'
                && $payload['messageObject']['assigned']
                    === 'Multi Type Agent';
        });

        Http::assertSent(function ($request) {
            $payload = $request->data();

            return $payload['messageObject']['type'] === 'location'
                && $payload['messageObject']['location']['latitude']
                    === 27.3314
                && $payload['messageObject']['location']['longitude']
                    === 88.6138
                && $payload['messageObject']['location']['assigned']
                    === 'Multi Type Agent';
        });
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
