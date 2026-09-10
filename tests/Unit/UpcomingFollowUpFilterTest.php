<?php

namespace Tests\Unit;

use App\Http\Controllers\UpcomingFollowUpController;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadFollowup;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Tests\TestCase;

class UpcomingFollowUpFilterTest extends TestCase
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

        $this->createSchema();
    }

    public function test_product_filter_matches_service_stored_on_lead_when_followup_has_no_service_ids(): void
    {
        $admin = $this->createUser(UserType::ADMIN, 'Admin User');
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Samarpit Sharma');

        $matchingProduct = $this->createProduct('Helicopter');
        $otherProduct = $this->createProduct('Plane');

        $matchingService = $this->createService('Gangtok To Bagdogra By Helicopter', $matchingProduct);
        $otherService = $this->createService('Plane Ride in Mumbai', $otherProduct);

        $matchingLead = $this->createLeadWithFollowup(
            'Matching Customer',
            $salesperson,
            [$matchingService->id],
            null,
            '2026-09-10 10:00:00'
        );

        $this->createLeadWithFollowup(
            'Other Customer',
            $salesperson,
            [$otherService->id],
            null,
            '2026-09-10 11:00:00'
        );

        $this->actingAs($admin);

        $response = app(UpcomingFollowUpController::class)->index(
            Request::create('/admin/upcoming-follow-up', 'GET', [
                'from_date' => '2026-09-10',
                'product_id' => $matchingProduct->id,
            ])
        );

        $this->assertInstanceOf(View::class, $response);

        $rows = $response->getData()['arrFollowUps'];

        $this->assertCount(1, $rows);
        $this->assertSame($matchingLead->id, $rows->first()->lead_id);
    }

    public function test_lead_score_filter_matches_latest_ai_score_temperature(): void
    {
        $admin = $this->createUser(UserType::ADMIN, 'Admin User');
        $salesperson = $this->createUser(UserType::SALES_EXECUTIVE, 'Samarpit Sharma');

        $product = $this->createProduct('Helicopter');
        $service = $this->createService('Gangtok To Bagdogra By Helicopter', $product);

        $hotLead = $this->createLeadWithFollowup(
            'Hot Customer',
            $salesperson,
            [$service->id],
            [$service->id],
            '2026-09-10 10:00:00'
        );

        $coldLead = $this->createLeadWithFollowup(
            'Cold Customer',
            $salesperson,
            [$service->id],
            [$service->id],
            '2026-09-10 11:00:00'
        );

        $this->createScore($hotLead, 'hot', 88);
        $this->createScore($coldLead, 'cold', 25);

        $this->actingAs($admin);

        $response = app(UpcomingFollowUpController::class)->index(
            Request::create('/admin/upcoming-follow-up', 'GET', [
                'from_date' => '2026-09-10',
                'lead_score_temperature' => 'hot',
            ])
        );

        $this->assertInstanceOf(View::class, $response);

        $rows = $response->getData()['arrFollowUps'];

        $this->assertCount(1, $rows);
        $this->assertSame($hotLead->id, $rows->first()->lead_id);
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->integer('status')->default(1);
            $table->uuid('parent_id')->nullable();
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

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service');
            $table->decimal('service_amount', 15, 2)->default(0);
            $table->json('product_ids')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id')->nullable();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->unsignedInteger('number_of_passengers')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(1);
            $table->uuid('followed_by')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_ai_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->index();
            $table->uuid('followup_id')->nullable();
            $table->uuid('previous_score_id')->nullable();
            $table->string('status', 20)->default('completed');
            $table->string('temperature', 20)->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->timestamps();
        });
    }

    private function createUser(string $role, string $name): User
    {
        $userType = UserType::forceCreate([
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

    private function createProduct(string $name): Product
    {
        return Product::forceCreate([
            'id' => (string) Str::uuid(),
            'product' => $name,
            'status' => 1,
        ]);
    }

    private function createService(string $name, Product $product): Service
    {
        return Service::forceCreate([
            'id' => (string) Str::uuid(),
            'service' => $name,
            'service_amount' => 10000,
            'product_ids' => [$product->id],
            'status' => 1,
        ]);
    }

    private function createLeadWithFollowup(
        string $clientName,
        User $representative,
        array $leadServiceIds,
        ?array $followupServiceIds,
        string $nextFollowupAt
    ): Lead {
        $client = Client::forceCreate([
            'id' => (string) Str::uuid(),
            'name' => $clientName,
            'email' => Str::slug($clientName) . '@example.test',
            'contact_number' => '+91-9999999999',
        ]);

        $lead = Lead::forceCreate([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $representative->id,
            'service_ids' => $leadServiceIds,
            'product_ids' => [],
            'number_of_passengers' => 2,
            'created_at' => '2026-09-09 09:00:00',
            'updated_at' => '2026-09-09 09:00:00',
        ]);

        LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 1,
            'followup_note' => 'Follow up',
            'next_followup_date' => $nextFollowupAt,
            'service_ids' => $followupServiceIds,
            'created_at' => $nextFollowupAt,
            'updated_at' => $nextFollowupAt,
        ]);

        return $lead;
    }

    private function createScore(Lead $lead, string $temperature, int $score): LeadAiScore
    {
        return LeadAiScore::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'status' => 'completed',
            'temperature' => $temperature,
            'score' => $score,
            'created_at' => '2026-09-10 09:00:00',
            'updated_at' => '2026-09-10 09:00:00',
        ]);
    }
}
