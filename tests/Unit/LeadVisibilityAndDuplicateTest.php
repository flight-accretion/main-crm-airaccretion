<?php

namespace Tests\Unit;

use App\Http\Controllers\ClientController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\User;
use App\Models\UserType;
use App\Services\ActiveLeadService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadVisibilityAndDuplicateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

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

    public function test_view_leads_includes_manual_ride_lead_without_service_ids(): void
    {
        $superAdmin = $this->createUser(UserType::SUPER_ADMIN, 'Super Admin');
        $lead = $this->createLeadWithClient([
            'name' => 'Anup Agrawal',
            'contact_number' => '+91-9437938761',
        ], [
            'representative_user_id' => $superAdmin->id,
            'service_ids' => null,
            'product_ids' => [(string) Str::uuid()],
        ]);

        DB::table('lead_rides')->insert([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'from_date' => now()->addDay(),
            'to_date' => now()->addDay()->addHour(),
            'from_place' => 'Gangtok',
            'to_place' => 'Bagdogra',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin);

        $view = app(ClientController::class)->index(
            Request::create('/admin/lead', 'GET', ['search' => '9437938761'])
        );

        $visibleLeadIds = $view
            ->getData()['leads']
            ->pluck('id')
            ->all();

        $this->assertContains($lead->id, $visibleLeadIds);
    }

    public function test_admin_can_filter_unassigned_leads_with_na_staff_filter(): void
    {
        $superAdmin = $this->createUser(UserType::SUPER_ADMIN, 'Super Admin');
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Sales User');

        $unassignedLead = $this->createLeadWithClient([
            'name' => 'Unassigned Customer',
            'contact_number' => '+91-9437938700',
        ], [
            'representative_user_id' => null,
        ]);

        $this->createLeadWithClient([
            'name' => 'Assigned Customer',
            'contact_number' => '+91-9437938701',
        ], [
            'representative_user_id' => $salesperson->id,
        ]);

        $this->actingAs($superAdmin);

        $view = app(ClientController::class)->index(
            Request::create('/admin/lead', 'GET', [
                'representative_user_id' => 'na',
            ])
        );

        $data = $view->getData();
        $visibleLeadIds = $data['leads']
            ->pluck('id')
            ->all();

        $this->assertSame([$unassignedLead->id], $visibleLeadIds);
        $this->assertTrue($data['canFilterUnassignedLeads']);
    }

    public function test_initiated_lead_blocks_duplicate_active_lead_lookup(): void
    {
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Sales User');
        $lead = $this->createLeadWithClient([
            'name' => 'Anup Agrawal',
            'contact_number' => '+91-9437938761',
        ], [
            'representative_user_id' => $salesperson->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Initial lead created',
            'followed_by' => $salesperson->id,
            'status' => 0,
        ]);

        $foundLead = app(ActiveLeadService::class)
            ->findByPhone('+91-9437938761');

        $this->assertNotNull($foundLead);
        $this->assertSame($lead->id, $foundLead->id);
    }

    public function test_full_payment_received_lead_blocks_duplicate_active_lead_lookup(): void
    {
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Sales User');
        $lead = $this->createLeadWithClient([
            'name' => 'Paid Lead Customer',
            'contact_number' => '+91-9437938762',
        ], [
            'representative_user_id' => $salesperson->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Full payment received',
            'followed_by' => $salesperson->id,
            'status' => 3,
        ]);

        $foundLead = app(ActiveLeadService::class)
            ->findByPhone('+91-9437938762');

        $this->assertNotNull($foundLead);
        $this->assertSame($lead->id, $foundLead->id);
    }

    public function test_cancelled_and_confirmed_leads_do_not_block_duplicate_active_lead_lookup(): void
    {
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Sales User');
        $cancelledLead = $this->createLeadWithClient([
            'name' => 'Cancelled Lead Customer',
            'contact_number' => '+91-9437938763',
        ], [
            'representative_user_id' => $salesperson->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $cancelledLead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Lead cancelled',
            'followed_by' => $salesperson->id,
            'status' => 2,
        ]);

        $confirmedLead = $this->createLeadWithClient([
            'name' => 'Confirmed Lead Customer',
            'contact_number' => '+91-9437938764',
        ], [
            'representative_user_id' => $salesperson->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $confirmedLead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'Ride confirmed',
            'followed_by' => $salesperson->id,
            'status' => 5,
        ]);

        $this->assertNull(
            app(ActiveLeadService::class)
                ->findByPhone('+91-9437938763')
        );
        $this->assertNull(
            app(ActiveLeadService::class)
            ->findByPhone('+91-9437938764')
        );
    }

    public function test_manual_assignment_creates_today_followup_when_lead_has_no_followup(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);

        Carbon::setTestNow(Carbon::create(2026, 8, 31, 12, 0, 0));

        $superAdmin = $this->createUser(UserType::SUPER_ADMIN, 'Super Admin');
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Akshita Borakar');
        $product = Product::create([
            'id' => (string) Str::uuid(),
            'product' => 'Empty',
            'status' => 1,
        ]);

        $lead = $this->createLeadWithClient([
            'name' => 'Manual WhatsApp Customer',
            'contact_number' => '+91-9437938765',
        ], [
            'representative_user_id' => null,
            'product_ids' => [$product->id],
        ]);

        $response = $this
            ->actingAs($superAdmin)
            ->put(
                route('admin.leads.update', $lead),
                [
                    'name' => 'Manual WhatsApp Customer',
                    'email' => null,
                    'contact_country_code' => '+91',
                    'contact_number' => '9437938765',
                    'whatsapp_country_code' => '+91',
                    'alternate_number' => null,
                    'country_id' => null,
                    'address' => null,
                    'company_name' => null,
                    'gst_number' => null,
                    'city' => null,
                    'date_of_birth' => null,
                    'number_of_passengers' => 1,
                    'occasion' => null,
                    'trips' => [
                        [
                            'id' => '',
                            'from_date' => '2026-09-01 10:00',
                            'to_date' => '2026-09-01 11:00',
                            'from_place' => 'Indore',
                            'to_place' => 'Delhi',
                        ],
                    ],
                    'product_ids' => [$product->id],
                    'service_ids' => [],
                    'next_followup_date' => null,
                    'representative_user_id' => $salesperson->id,
                    'requirement_description' => 'Assigned manually from super admin.',
                ]
            );

        $response->assertRedirect(
            route('admin.leads.edit', $lead)
        );

        $lead->refresh();

        $this->assertSame(
            $salesperson->id,
            $lead->representative_user_id
        );
        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followed_by' => $salesperson->id,
                'status' => 1,
            ]
        );

        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->firstOrFail();

        $this->assertSame(
            '2026-08-31',
            $followup->next_followup_date->toDateString()
        );
    }

    private function createUser(string $role, string $name): User
    {
        $type = UserType::query()->create([
            'id' => (string) Str::uuid(),
            'user_type' => $role,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function createLeadWithClient(array $clientData, array $leadData = []): Lead
    {
        $client = Client::create(array_merge([
            'id' => (string) Str::uuid(),
            'name' => 'Test Client',
            'contact_number' => '+91-9000000000',
            'status' => 1,
        ], $clientData));

        return Lead::create(array_merge([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => null,
            'service_ids' => null,
            'product_ids' => null,
            'number_of_passengers' => 1,
            'description' => 'Manual lead',
        ], $leadData));
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
            $table->string('company_name')->nullable();
            $table->string('gst_number')->nullable();
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->uuid('country_id')->nullable();
            $table->uuid('city_id')->nullable();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->date('date_of_birth')->nullable();
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

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->json('user_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('field_name');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->integer('payment_status')->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }
}
