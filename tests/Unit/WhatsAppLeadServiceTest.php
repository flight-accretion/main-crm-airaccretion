<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationQueue;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAllocationService;
use App\Services\WhatsAppLeadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Service;

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

    public function test_unmapped_whatcrm_product_without_empty_mapping_stays_queued(): void
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
            'queued',
            $response['status']
        );
        $this->assertNull(
            $response['agent_user_id']
        );
        $this->assertNull(
            $lead->representative_user_id
        );
        $this->assertSame(
            [$retailProduct->id],
            $lead->product_ids_array
        );
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
                'status' => 'queued',
                'reason' => 'whatsapp_retail_waiting',
            ]
        );
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followed_by' => null,
                'status' => 1,
            ]
        );
    }

    public function test_unmatched_whatcrm_message_assigns_to_user_mapped_to_empty_product(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Mapped Empty Product User'
            );

        $emptyProduct =
            $this->createProduct(
                'Empty'
            );

        $mappedRetailProduct =
            $this->createProduct(
                'Yacht in Goa'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $emptyProductUser->id,
            'product_id' =>
                $emptyProduct->id,
            'is_active' =>
                true,
        ]);

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $emptyProductUser->id,
            'product_id' =>
                $mappedRetailProduct->id,
            'is_active' =>
                true,
        ]);

        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Unknown Need Customer',
                    'number' => '9876543228',
                    'message' => 'Please call me with details',
                    'guest' => 1,
                    'external_id' => 'WA-EMPTY-MAPPED-1',
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
            [],
            $lead->product_ids_array
        );
        $this->assertSame(
            [],
            $lead->service_ids_array
        );
        $this->assertDatabaseMissing(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
            ]
        );
        $this->assertDatabaseHas(
            'lead_rides',
            [
                'lead_id' => $lead->id,
                'from_place' => 'NA',
                'to_place' => 'NA',
            ]
        );
    }

    public function test_assigned_whatcrm_lead_sends_representative_handoff_message_to_customer(): void
    {
        $salesperson =
            $this->createSalesUser(
                'Samarpit Sharma',
                '9109152175'
            );

        $this->makeAvailable($salesperson);

        $retailProduct =
            $this->createProduct(
                'Retail Tour'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $salesperson->id,
            'product_id' =>
                $retailProduct->id,
            'is_active' =>
                true,
        ]);

        config()->set(
            'whatcrm.send_message_url',
            'https://web.airaccretion.com/api/v1/send-message'
        );
        config()->set('whatcrm.send_message_token', 'test-token');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.ASSIGNMENT-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Assigned Customer',
                    'number' => '9876543210',
                    'service' => 'Retail Tour',
                    'guest' => 2,
                    'external_id' => 'WA-HANDOFF-1',
                ]);

        $this->assertSame('assigned', $response['status']);

        Http::assertSent(function ($request) {
            if (
                !str_contains(
                    $request->url(),
                    'https://web.airaccretion.com/api/v1/send-message?token=test-token'
                )
            ) {
                return false;
            }

            return $request->data() === [
                'messageObject' => [
                    'messaging_product' => 'whatsapp',
                    'to' => '919876543210',
                    'type' => 'text',
                    'text' => [
                        'body' =>
                            'Our representative Samarpit Sharma (9109152175) will call you shortly.',
                    ],
                ],
            ];
        });

        $integration = DB::table('whatsapp_lead_integrations')
            ->where('external_id', 'WA-HANDOFF-1')
            ->first();

        $this->assertNotNull($integration);
        $this->assertNotNull(
            $integration->assignment_message_sent_at
        );
        $this->assertNull(
            $integration->assignment_message_error
        );

        $this->assertDatabaseHas(
            'whatsapp_messages',
            [
                'provider_message_id' => 'wamid.ASSIGNMENT-1',
                'direction' => 'outgoing',
                'sender_type' => 'agent',
                'sender_user_id' => $salesperson->id,
                'body' =>
                    'Our representative Samarpit Sharma (9109152175) will call you shortly.',
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

    public function test_unmapped_whatcrm_charter_keyword_stays_queued_for_charter_team(): void
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
            'queued',
            $response['status']
        );
        $this->assertSame(
            $charterProduct->id,
            $response['product_id']
        );
        $this->assertNull(
            $response['agent_user_id']
        );
        $this->assertNull(
            $lead->representative_user_id
        );
        $this->assertDatabaseHas(
            'lead_allocation_queue',
            [
                'lead_id' => $lead->id,
                'status' => 'queued',
                'reason' => 'whatsapp_charter_waiting',
            ]
        );
    }

    public function test_unmapped_charter_product_uses_configured_charter_team_before_retail_fallback(): void
    {
        $charterUser =
            $this->createSalesUser(
                'Configured Charter User'
            );

        $emptyProductUser =
            $this->createSalesUser(
                'Retail Empty Product User'
            );

        $mappedCharterProduct =
            $this->createProduct(
                'Char Dham Yatra'
            );

        $requestedCharterProduct =
            $this->createProduct(
                'Flower Shower'
            );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $charterUser->id,
            'product_id' =>
                $mappedCharterProduct->id,
            'is_active' =>
                true,
        ]);

        $this->makeAvailable($charterUser);
        $this->makeAvailable($emptyProductUser);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Flower Shower Customer',
                    'number' => '9876543224',
                    'service' => 'Flower Shower proposal',
                    'guest' => 2,
                    'external_id' =>
                        'WA-CHARTER-UNMAPPED-TEAM-1',
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
            $requestedCharterProduct->id,
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

    public function test_queued_whatcrm_lead_sends_handoff_message_when_later_assigned(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Later Empty Product User',
                '9988776655'
            );

        $emptyProduct =
            $this->createProduct(
                'Empty'
            );

        $this->createProduct(
            'Retail Tour'
        );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $emptyProductUser->id,
            'product_id' =>
                $emptyProduct->id,
            'is_active' =>
                true,
        ]);

        $response =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Queued Handoff Customer',
                    'number' => '9876543212',
                    'service' => 'Retail Tour',
                    'guest' => 1,
                    'external_id' => 'WA-HANDOFF-QUEUED-1',
                ]);

        $this->assertSame(
            'queued',
            $response['status']
        );

        config()->set(
            'whatcrm.send_message_url',
            'https://web.airaccretion.com/api/v1/send-message'
        );
        config()->set('whatcrm.send_message_token', 'test-token');

        Http::fake([
            'https://web.airaccretion.com/api/v1/send-message*' =>
                Http::response(
                    [
                        'success' => true,
                        'metaResponse' => [
                            'messages' => [
                                [
                                    'id' => 'wamid.ASSIGNMENT-QUEUED-1',
                                    'message_status' => 'accepted',
                                ],
                            ],
                        ],
                    ],
                    200
                ),
        ]);

        $this->makeAvailable($emptyProductUser);

        $result =
            app(LeadAllocationService::class)
                ->processPendingLeads();

        $this->assertSame(
            1,
            $result['processed']
        );

        Http::assertSent(function ($request) {
            return str_contains(
                    $request->url(),
                    'https://web.airaccretion.com/api/v1/send-message?token=test-token'
                )
                && $request->data() === [
                    'messageObject' => [
                        'messaging_product' => 'whatsapp',
                        'to' => '919876543212',
                        'type' => 'text',
                        'text' => [
                            'body' =>
                                'Our representative Later Empty Product User (9988776655) will call you shortly.',
                        ],
                    ],
                ];
        });

        $integration = DB::table('whatsapp_lead_integrations')
            ->where('external_id', 'WA-HANDOFF-QUEUED-1')
            ->first();

        $this->assertNotNull($integration);
        $this->assertNotNull(
            $integration->assignment_message_sent_at
        );
        $this->assertNull(
            $integration->assignment_message_error
        );
    }

    public function test_queued_unmapped_whatcrm_product_later_assigns_to_empty_product_salesperson(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Later Empty Product User'
            );

        $emptyProduct =
            $this->createProduct(
                'Empty'
            );

        $this->createProduct(
            'Retail Tour'
        );

        EmailLeadProductUserAssignment::create([
            'user_id' =>
                $emptyProductUser->id,
            'product_id' =>
                $emptyProduct->id,
            'is_active' =>
                true,
        ]);

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
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $response['lead_id'],
                'followed_by' => null,
                'status' => 1,
            ]
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
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followed_by' => $emptyProductUser->id,
                'status' => 1,
            ]
        );
    }

    public function test_whatcrm_duplicate_uses_existing_lead_until_ride_is_cancelled_or_confirmed(): void
    {
        $salesperson =
            $this->createSalesUser(
                'Existing Lead Owner'
            );

        $paidLead =
            $this->createLeadWithFollowupStatus(
                '9876543244',
                $salesperson,
                3
            );

        $paidResponse =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Paid Existing Customer',
                    'number' => '9876543244',
                    'message' => 'Need one more update',
                    'external_id' => 'WA-DUPLICATE-PAID',
                ]);

        $this->assertSame(
            'existing_lead',
            $paidResponse['status']
        );
        $this->assertTrue(
            $paidResponse['existing_lead']
        );
        $this->assertSame(
            $paidLead->id,
            $paidResponse['lead_id']
        );
        $this->assertSame(
            1,
            Lead::query()
                ->whereHas('client', function ($query) {
                    $query->where(
                        'contact_number',
                        '9876543244'
                    );
                })
                ->count()
        );

        $confirmedLead =
            $this->createLeadWithFollowupStatus(
                '9876543245',
                $salesperson,
                5
            );

        $confirmedResponse =
            app(WhatsAppLeadService::class)
                ->process([
                    'name' => 'Confirmed Existing Customer',
                    'number' => '9876543245',
                    'message' => 'Need a new booking',
                    'external_id' => 'WA-DUPLICATE-CONFIRMED',
                ]);

        $this->assertFalse(
            $confirmedResponse['existing_lead']
        );
        $this->assertNotSame(
            $confirmedLead->id,
            $confirmedResponse['lead_id']
        );
        $this->assertSame(
            2,
            Lead::query()
                ->whereHas('client', function ($query) {
                    $query->where(
                        'contact_number',
                        '9876543245'
                    );
                })
                ->count()
        );
    }

    public function test_identified_whatcrm_product_populates_related_services_on_lead(): void
{
    $product =
        $this->createProduct(
            'Private Plane Charters'
        );

    $service =
        $this->createService(
            'Private Plane Charter Service',
            [
                $product->id,
            ]
        );

    $response =
        app(
            WhatsAppLeadService::class
        )->process([
            'name' =>
                'Charter Customer',

            'number' =>
                '9876543299',

            'service' =>
                'Private Plane Charters',

            'guest' =>
                2,

            'external_id' =>
                'WA-PRODUCT-SERVICE-1',
        ]);

    $lead =
        Lead::query()
            ->where(
                'id',
                $response['lead_id']
            )
            ->firstOrFail();

    $this->assertSame(
        [
            $product->id,
        ],
        $lead->product_ids_array
    );

    $this->assertSame(
        [
            $service->id,
        ],
        $lead->service_ids_array
    );
}

    private function createSalesUser(
        string $name,
        ?string $contactNumber = null
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
            'contact_number' => $contactNumber,
            'status' => 1,
        ]);
    }

    private function createLeadWithFollowupStatus(
        string $phone,
        User $salesperson,
        int $status
    ): Lead {
        $client =
            Client::create([
                'id' => (string) Str::uuid(),
                'name' => 'Existing WhatsApp Customer',
                'contact_number' => $phone,
                'status' => 1,
            ]);

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $salesperson->id,
                'service_ids' => null,
                'product_ids' => null,
                'number_of_passengers' => 1,
                'description' => 'Existing lead',
            ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Existing latest status',
            'followed_by' => $salesperson->id,
            'status' => $status,
        ]);

        return $lead;
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

    private function createService(
    string $name,
    array $productIds
): Service {

    return Service::create([
        'id' =>
            (string) Str::uuid(),

        'service' =>
            $name,

        'product_ids' =>
            $productIds,

        'status' =>
            1,
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
            $table->json('user_ids')->nullable();
            $table->timestamps();
        });

        Schema::create(
    'services',
    function (Blueprint $table) {

        $table
            ->uuid('id')
            ->primary();

        $table
            ->string('service');

        $table
            ->text('description')
            ->nullable();

        $table
            ->decimal(
                'service_amount',
                12,
                2
            )
            ->nullable();

        $table
            ->decimal(
                'fees_percent',
                8,
                2
            )
            ->nullable();

        $table
            ->text('terms_and_conditions')
            ->nullable();

        $table
            ->json('product_ids')
            ->nullable();

        $table
            ->integer('status')
            ->default(1);

        $table->timestamps();
    }
);

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
    }
}
