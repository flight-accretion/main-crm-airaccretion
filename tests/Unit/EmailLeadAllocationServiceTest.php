<?php

namespace Tests\Unit;

use App\Models\EmailLeadLog;
use App\Models\EmailLeadProductUserAssignment;
use App\Models\Lead;
use App\Models\LeadAllocationSetting;
use App\Models\Product;
use App\Models\SalespersonAvailability;
use App\Models\User;
use App\Models\UserType;
use App\Services\EmailLeadAllocationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmailLeadAllocationServiceTest extends TestCase
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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_email_retail_product_assigns_to_empty_product_salesperson(): void
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

        $lead =
            $this->createLeadWithEmailLog(
                $retailProduct,
                'Retail Tour'
            );

        $salesperson =
            app(EmailLeadAllocationService::class)
                ->pickSalesperson(
                    $lead,
                    LeadAllocationSetting::getActiveSettings()
                );

        $this->assertNotNull($salesperson);
        $this->assertSame(
            $emptyProductUser->id,
            $salesperson->id
        );
    }

    public function test_email_charter_keyword_maps_to_related_crm_product_and_charter_team(): void
    {
        $charterUser =
            $this->createSalesUser(
                'Charter Team User'
            );

        $emptyProductUser =
            $this->createSalesUser(
                'Empty Product User'
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

        Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => (string) Str::uuid(),
            'representative_user_id' => $charterUser->id,
        ]);

        $lead =
            $this->createLeadWithEmailLog(
                null,
                'Dham booking'
            );

        $salesperson =
            app(EmailLeadAllocationService::class)
                ->pickSalesperson(
                    $lead,
                    LeadAllocationSetting::getActiveSettings()
                );

        $this->assertNotNull($salesperson);
        $this->assertSame(
            $charterUser->id,
            $salesperson->id
        );
    }

    public function test_email_charter_keyword_without_mapping_falls_back_to_empty_product_salesperson(): void
    {
        $emptyProductUser =
            $this->createSalesUser(
                'Available Empty Product User'
            );

        $this->createProduct(
            'Char Dham Yatra'
        );

        $this->makeAvailable($emptyProductUser);

        $lead =
            $this->createLeadWithEmailLog(
                null,
                'Dham booking'
            );

        $salesperson =
            app(EmailLeadAllocationService::class)
                ->pickSalesperson(
                    $lead,
                    LeadAllocationSetting::getActiveSettings()
                );

        $this->assertNotNull($salesperson);
        $this->assertSame(
            $emptyProductUser->id,
            $salesperson->id
        );
    }

    private function createSalesUser(
        string $name
    ): User {
        $userType =
            UserType::query()
                ->firstOrCreate(
                    [
                        'user_type' =>
                            UserType::SALES_EXECUTIVE,
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
            'status' => 1,
        ]);
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

    private function createLeadWithEmailLog(
        ?Product $product,
        string $serviceName
    ): Lead {
        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => (string) Str::uuid(),
                'representative_user_id' => null,
                'product_ids' => $product
                    ? [$product->id]
                    : null,
            ]);

        EmailLeadLog::create([
            'id' => (string) Str::uuid(),
            'message_id' => (string) Str::uuid(),
            'imap_uid' => (string) random_int(1000, 9999),
            'sender_email' => 'noreply@example.test',
            'recipient_email' => 'sales@example.test',
            'subject' => 'Lead',
            'customer_name' => 'Customer',
            'customer_phone' => '9876543210',
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
            $table->integer('status')->default(1);
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
    }
}
