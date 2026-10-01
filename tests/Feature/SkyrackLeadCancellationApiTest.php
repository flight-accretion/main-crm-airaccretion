<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Services\Kpi\KpiActivityRecorder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SkyrackLeadCancellationApiTest extends TestCase
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
        config()->set('services.skyrack.token', 'test-token');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_call_summary_payload_can_cancel_lead_and_store_reason_note(): void
    {
        $agent = User::create([
            'name' => 'Deepak',
            'email' => 'deepak@example.test',
            'password' => 'secret',
            'status' => 1,
            'contact_number' => '9876500000',
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Skyrack Customer',
            'contact_number' => '9876543210',
            'status' => 1,
        ]);

        $lead = Lead::withoutEvents(function () use ($client, $agent) {
            return Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $agent->id,
                'number_of_passengers' => 1,
                'description' => 'Lead from Skyrack.',
            ]);
        });

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-09-24 10:00:00',
            'followup_note' => 'Existing active follow-up',
            'followed_by' => $agent->id,
            'status' => LeadFollowup::STATUS_ACTIVE,
        ]);

        $payload = [
            'phone_number' => '9876543210',
            'agent_phone' => '9876500000',
            'agent_name' => 'Deepak',
            'direction' => 'outgoing',
            'call_start_at' => '2026-09-23 11:15:10',
            'call_end_at' => '2026-09-23 11:15:40',
            'followup_recording_id' => 12345,
            'reason' => 'Customer not interested',
        ];

        $this->app->instance(KpiActivityRecorder::class, new class {
            public function recordSalesFollowup(): void
            {
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/skyrack/leads/cancel', $payload);

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Lead cancelled successfully.',
                'data' => [
                    'lead_id' => $lead->id,
                    'status' => 'cancelled',
                    'already_cancelled' => false,
                    'source' => 'skyrack',
                ],
            ]);

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'status' => LeadFollowup::STATUS_CANCELLED,
            'followed_by' => $agent->id,
            'followup_note' => 'Lead cancelled from Skyrack. Reason: Customer not interested',
        ]);

        $retry = $this
            ->withHeader('Authorization', 'Bearer test-token')
            ->postJson('/api/skyrack/leads/cancel', $payload);

        $retry->assertOk();
        $this->assertSame(2, LeadFollowup::where('lead_id', $lead->id)->count());
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('user_type_id')->nullable();
            $table->integer('status')->default(1);
            $table->string('contact_number')->nullable();
            $table->timestamps();
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('alternate_number')->nullable();
            $table->integer('status')->default(1);
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

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('parent_followup_id')->nullable();
            $table->unsignedBigInteger('followup_recording_id')->nullable();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->string('file')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('extra_service_ids')->nullable();
            $table->decimal('service_amount', 12, 2)->nullable();
            $table->decimal('discount_amount', 12, 2)->nullable();
            $table->json('service_details')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_followup_id')->nullable();
            $table->integer('payment_status')->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('skyrack_lead_cancellation_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('integration', 64)->default('skyrack');
            $table->uuid('request_id');
            $table->uuid('lead_id')->index();
            $table->string('actor_key', 128);
            $table->char('payload_hash', 64);
            $table->string('state', 24)->default('processing');
            $table->unsignedSmallInteger('http_code')->nullable();
            $table->json('response_json')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['integration', 'request_id'], 'skyrack_cancel_request_unique');
        });
    }
}
