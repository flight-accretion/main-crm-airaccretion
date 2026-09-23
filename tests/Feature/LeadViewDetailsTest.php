<?php

namespace Tests\Feature;

use App\Http\Controllers\ClientController;
use App\Models\Client;
use App\Models\ExtraService;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadViewDetailsTest extends TestCase
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

    public function test_lead_view_shows_add_followup_services_and_history_data(): void
    {
        $admin = $this->createUser(UserType::SUPER_ADMIN, 'View Admin');
        $representative = $this->createUser(UserType::SALES_EXECUTIVE, 'Pallavi Singh');

        $product = Product::create([
            'id' => (string) Str::uuid(),
            'product' => 'Air Ambulance',
            'status' => 1,
        ]);

        $service = Service::create([
            'id' => (string) Str::uuid(),
            'service' => 'Helicopter Charter',
            'service_amount' => 12000,
            'status' => 1,
        ]);

        $extraService = ExtraService::create([
            'id' => (string) Str::uuid(),
            'extra_service' => 'Ground Transport',
            'extra_service_amount' => 1500,
            'usage_scope' => ExtraService::SCOPE_CUSTOMER,
            'status' => 1,
        ]);

        DB::table('service_extra_service')->insert([
            'service_id' => $service->id,
            'extra_service_id' => $extraService->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'IVR Lead 1509789786',
            'contact_number' => '1509789786',
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $representative->id,
            'service_ids' => null,
            'product_ids' => [$product->id],
            'number_of_passengers' => 3,
            'occasion' => 'Medical Transfer',
            'description' => 'Lead received automatically from IVR.',
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDay(),
            'followup_note' => 'First followup history note',
            'status' => LeadFollowup::STATUS_ACTIVE,
            'followed_by' => $representative->id,
            'service_ids' => [$service->id],
            'extra_service_ids' => [$extraService->id],
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => now()->addDays(2),
            'followup_note' => 'Latest followup history note',
            'status' => LeadFollowup::STATUS_PARTIAL_PAYMENT_RECEIVED,
            'followed_by' => $representative->id,
            'service_ids' => [$service->id],
            'extra_service_ids' => [$extraService->id],
            'total_amount' => 13500,
            'service_amount' => 12000,
            'received_amount' => 5000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        $html = app(ClientController::class)
            ->viewLead($lead)
            ->render();

        $this->assertStringContainsString('Services and Extra Services', $html);
        $this->assertStringContainsString('Products', $html);
        $this->assertStringContainsString('Air Ambulance', $html);
        $this->assertStringContainsString('Number OF Passengers', $html);
        $this->assertStringContainsString('Medical Transfer', $html);
        $this->assertStringContainsString('Helicopter Charter', $html);
        $this->assertStringContainsString('Ground Transport', $html);
        $this->assertStringContainsString('Call Notes', $html);
        $this->assertStringContainsString('First followup history note', $html);
        $this->assertStringContainsString('Latest followup history note', $html);

        $this->assertStringNotContainsString('Latest Follow-up Summary', $html);
        $this->assertStringNotContainsString('Trip Itinerary', $html);
        $this->assertStringNotContainsString('Services &amp; Costing', $html);
        $this->assertStringNotContainsString('Services & Costing', $html);
    }

    private function createUser(string $role, string $name): User
    {
        $userType = UserType::create([
            'id' => (string) Str::uuid(),
            'user_type' => $role,
            'status' => 1,
        ]);

        return User::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'email' => Str::slug($name) . '@example.test',
            'password' => bcrypt('password'),
            'user_type_id' => $userType->id,
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->uuid('parent_id')->nullable();
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
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('country_id')->nullable();
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
            $table->unsignedBigInteger('country_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();
            $table->text('address')->nullable();
            $table->text('description')->nullable();
            $table->date('date_of_birth')->nullable();
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

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->text('description')->nullable();
            $table->decimal('service_amount', 15, 2)->default(0);
            $table->decimal('fees_percent', 8, 2)->default(0);
            $table->json('product_ids')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('extra_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('extra_service');
            $table->text('description')->nullable();
            $table->decimal('extra_service_amount', 15, 2)->default(0);
            $table->string('usage_scope')->default(ExtraService::SCOPE_CUSTOMER);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('service_extra_service', function (Blueprint $table) {
            $table->uuid('service_id');
            $table->uuid('extra_service_id');
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
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
            $table->uuid('lead_id')->nullable();
            $table->timestamp('from_date')->nullable();
            $table->timestamp('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->integer('followup_recording_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->string('file')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('total_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->decimal('service_amount', 15, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->decimal('received_amount', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->string('contact_outcome')->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id')->nullable();
            $table->decimal('paid_amount', 15, 2)->nullable();
            $table->timestamp('paid_date')->nullable();
            $table->string('payment_method')->nullable();
            $table->text('narration')->nullable();
            $table->integer('payment_status')->default(0);
            $table->string('file')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->nullable();
            $table->uuid('from_user_id')->nullable();
            $table->uuid('to_user_id')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('response_note')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamps();
        });
    }
}
