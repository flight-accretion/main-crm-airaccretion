<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\LeadRide;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Services\SkyrackLeadPayloadBuilder;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkyrackLeadSyncTest extends TestCase
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
        config()->set('services.skyrack.leads_api_url', 'https://call.skyrack.ai/api/v1/leads');
        config()->set('services.skyrack.leads_api_token', 'test-token');
        config()->set('services.skyrack.enabled', true);
        config()->set('services.skyrack.timeout', 10);
        config()->set('services.skyrack.backfill_limit', 2);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_payload_contains_uuid_and_crm_lead_code(): void
    {
        $lead = $this->createLead([
            'lead_id' => '43ea9443-5080-46dc-bd2c-3c3854d79876',
            'crm_lead_code' => 'CRM-1001',
            'client_name' => 'Shiv Gupta',
            'client_email' => 'rohan@skyrack.ai',
            'client_phone' => '+91-9669309788',
            'representative_name' => 'Rohan Gupta',
            'representative_phone' => '8839786863',
            'service_name' => 'Charted Books',
            'service_date' => '2026-09-15 09:30:00',
            'followup_status' => 1,
        ]);

        $payload = app(SkyrackLeadPayloadBuilder::class)->build($lead);

        $this->assertSame([
            'lead_id' => '43ea9443-5080-46dc-bd2c-3c3854d79876',
            'crm_lead_code' => 'CRM-1001',
            'client_name' => 'Shiv Gupta',
            'client_email' => 'rohan@skyrack.ai',
            'client_phone' => '9669309788',
            'Sales_Executive_name' => 'Rohan Gupta',
            'Sales_Executive_number' => '8839786863',
            'service_date' => '2026-09-15',
            'service_name' => 'Charted Books',
            'lead_status' => 'Active',
        ], $payload);
    }

    public function test_queueing_same_lead_keeps_one_pending_sync(): void
    {
        $lead = $this->createLead([
            'crm_lead_code' => 'CRM-1002',
            'client_name' => 'Duplicate Check',
            'client_phone' => '9669300000',
        ]);

        $service = app(SkyrackLeadSyncService::class);

        $service->queueLead($lead, 'created');
        $service->queueLead($lead, 'updated');

        $rows = DB::table('skyrack_lead_syncs')
            ->where('lead_id', $lead->id)
            ->get();

        $this->assertCount(1, $rows);
        $this->assertSame('pending', $rows->first()->status);
        $this->assertSame('updated', $rows->first()->reason);
    }

    public function test_first_success_queues_existing_backfill_once(): void
    {
        $currentLead = $this->createLead([
            'crm_lead_code' => 'CRM-1003',
            'client_name' => 'Current Lead',
            'client_phone' => '9669300001',
            'created_at' => '2026-08-20 10:00:00',
        ]);
        $latestExisting = $this->createLead([
            'crm_lead_code' => 'CRM-1004',
            'client_name' => 'Latest Existing',
            'client_phone' => '9669300002',
            'created_at' => '2026-08-19 10:00:00',
        ]);
        $secondLatestExisting = $this->createLead([
            'crm_lead_code' => 'CRM-1005',
            'client_name' => 'Second Existing',
            'client_phone' => '9669300003',
            'created_at' => '2026-08-18 10:00:00',
        ]);
        $olderLead = $this->createLead([
            'crm_lead_code' => 'CRM-1006',
            'client_name' => 'Older Existing',
            'client_phone' => '9669300004',
            'created_at' => '2026-08-17 10:00:00',
        ]);

        $service = app(SkyrackLeadSyncService::class);
        $service->queueLead($currentLead, 'created');

        Http::fake([
            'https://call.skyrack.ai/api/v1/leads' => Http::response(['ok' => true], 200),
        ]);

        $result = $service->processPending(1);

        $this->assertSame(1, $result['synced']);
        $this->assertSame(2, $result['backfill_queued']);
        $this->assertDatabaseHas('skyrack_lead_syncs', [
            'lead_id' => $latestExisting->id,
            'reason' => 'initial_backfill',
        ]);
        $this->assertDatabaseHas('skyrack_lead_syncs', [
            'lead_id' => $secondLatestExisting->id,
            'reason' => 'initial_backfill',
        ]);
        $this->assertDatabaseMissing('skyrack_lead_syncs', [
            'lead_id' => $olderLead->id,
            'reason' => 'initial_backfill',
        ]);

        $service->processPending(1);

        $this->assertSame(
            1,
            DB::table('skyrack_lead_sync_states')
                ->where('key', 'initial_backfill_queued_at')
                ->count()
        );
        $this->assertSame(
            3,
            DB::table('skyrack_lead_syncs')->count()
        );
    }

    private function createLead(array $overrides = []): Lead
    {
        return Lead::withoutEvents(function () use ($overrides) {
            $now = $overrides['created_at'] ?? '2026-08-20 09:00:00';

            $user = User::forceCreate([
                'id' => (string) Str::uuid(),
                'name' => $overrides['representative_name'] ?? 'Rohan Gupta',
                'email' => Str::uuid() . '@example.test',
                'password' => 'secret',
                'contact_number' => $overrides['representative_phone'] ?? '8839786863',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $client = Client::create([
                'id' => (string) Str::uuid(),
                'name' => $overrides['client_name'] ?? 'Test Client',
                'email' => $overrides['client_email'] ?? null,
                'contact_number' => $overrides['client_phone'] ?? '9669309788',
                'alternate_number' => null,
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $product = Product::create([
                'id' => (string) Str::uuid(),
                'product' => $overrides['product_name'] ?? 'Charter',
                'status' => 1,
            ]);

            $service = Service::create([
                'id' => (string) Str::uuid(),
                'service' => $overrides['service_name'] ?? 'Charter',
                'product_ids' => [$product->id],
                'status' => 1,
            ]);

            $lead = Lead::forceCreate([
                'id' => $overrides['lead_id'] ?? (string) Str::uuid(),
                'crm_lead_code' => $overrides['crm_lead_code'] ?? null,
                'client_id' => $client->id,
                'representative_user_id' => $user->id,
                'product_ids' => [$product->id],
                'service_ids' => [$service->id],
                'number_of_passengers' => 1,
                'description' => 'Test lead',
                'occasion' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            LeadRide::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'from_date' => $overrides['service_date'] ?? '2026-09-15 09:30:00',
                'to_date' => $overrides['service_date'] ?? '2026-09-15 10:30:00',
                'from_place' => 'Delhi',
                'to_place' => 'Mumbai',
            ]);

            LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'next_followup_date' => '2026-08-21 10:00:00',
                'followup_note' => 'Initial followup',
                'followed_by' => $user->id,
                'status' => $overrides['followup_status'] ?? 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return $lead;
        });
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
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
            $table->json('product_ids')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('crm_lead_code', 50)->nullable()->unique();
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
            $table->integer('status')->default(1);
            $table->uuid('followed_by')->nullable();
            $table->timestamps();
        });

        Schema::create('skyrack_lead_syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->unique();
            $table->string('status', 30)->default('pending');
            $table->string('reason', 50)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_payload_hash', 64)->nullable();
            $table->text('last_error')->nullable();
            $table->json('last_payload')->nullable();
            $table->json('last_response')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
        });

        Schema::create('skyrack_lead_sync_states', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value')->nullable();
            $table->timestamps();
        });
    }
}
