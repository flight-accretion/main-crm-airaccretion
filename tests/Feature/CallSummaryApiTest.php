<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallSummaryApiTest extends TestCase
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
        config()->set('call_summary.token', 'test-token');

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_unknown_direction_from_skyrack_is_accepted_and_saved_to_followup(): void
    {
        $agent = User::create([
            'name' => 'Samarpit Sharma',
            'email' => 'samarpit@example.test',
            'password' => 'secret',
            'status' => 1,
            'contact_number' => '9109152175',
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'IVR Lead 8655388628',
            'contact_number' => '8655388628',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $lead = Lead::withoutEvents(function () use ($client, $agent) {
            return Lead::create([
                'id' => (string) Str::uuid(),
                'client_id' => $client->id,
                'representative_user_id' => $agent->id,
                'service_ids' => null,
                'product_ids' => null,
                'number_of_passengers' => 1,
                'description' => 'Lead received automatically from VI CPaaS IVR.',
                'occasion' => null,
            ]);
        });

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-09-03 13:05:06',
            'followup_note' => 'New VI IVR lead received.',
            'followed_by' => $agent->id,
            'status' => 1,
        ]);

        $summary =
            'Very short follow-up call. Salesperson (Samarpit) called a customer who had previously enquired about a Mumbai helicopter tour.';

        $response = $this
            ->withHeader('Authorization', 'Bearer test-token')
            ->postJson(
                '/api/call-summaries',
                [
                    'phone_number' => '8655388628',
                    'summary' => $summary,
                    'followup_date' => null,
                    'call_start_at' => '2026-09-03 13:34:37',
                    'call_end_at' => '2026-09-03 13:34:56',
                    'agent_name' => 'Samarpit Sharma',
                    'direction' => 'unknown',
                    'sentiment_score' => null,
                    'followup_recording_id' => 980,
                ]
            );

        $response
            ->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'followup_created',
                'lead_id' => $lead->id,
                'followup_recording_id' => 980,
            ]);

        $this->assertDatabaseHas(
            'call_summary_integrations',
            [
                'phone_number' => '8655388628',
                'normalized_phone' => '8655388628',
                'direction' => 'unknown',
                'lead_id' => $lead->id,
                'status' => 'followup_created',
                'followup_recording_id' => 980,
                'summary' => $summary,
            ]
        );

        $this->assertDatabaseHas(
            'lead_followups',
            [
                'lead_id' => $lead->id,
                'followup_recording_id' => 980,
                'followup_note' => $summary,
                'followed_by' => $agent->id,
            ]
        );
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
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
            $table->text('service_ids')->nullable();
            $table->text('product_ids')->nullable();
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
            $table->string('file')->nullable();
            $table->integer('status')->default(0);
            $table->uuid('followed_by')->nullable();
            $table->timestamps();

            $table->unique(
                [
                    'lead_id',
                    'followup_recording_id',
                ],
                'lead_followups_lead_recording_unique'
            );
        });

        Schema::create('ivr_agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('vi_agent_name')->nullable();
            $table->uuid('mapped_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ivr_call_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_call_id')->nullable();
            $table->string('call_type_code')->nullable();
            $table->string('dni')->nullable();
            $table->string('cli')->nullable();
            $table->string('normalized_phone')->nullable();
            $table->string('agent_name')->nullable();
            $table->timestamp('call_start_at')->nullable();
            $table->timestamp('call_end_at')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->timestamps();
        });

        Schema::create('call_summary_integrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('call_fingerprint', 64)->unique();
            $table->unsignedBigInteger('followup_recording_id')->nullable();
            $table->string('phone_number', 50);
            $table->string('normalized_phone', 20)->nullable();
            $table->text('summary');
            $table->timestamp('followup_date')->nullable();
            $table->timestamp('call_start_at');
            $table->timestamp('call_end_at');
            $table->string('agent_name', 150);
            $table->string('normalized_agent_name', 150)->nullable();
            $table->string('direction', 20);
            $table->decimal('sentiment_score', 5, 2)->nullable();
            $table->uuid('ivr_call_log_id')->nullable();
            $table->uuid('lead_id')->nullable();
            $table->uuid('agent_user_id')->nullable();
            $table->uuid('followup_id')->nullable();
            $table->integer('match_score')->nullable();
            $table->string('match_method', 100)->nullable();
            $table->string('status', 50)->default('received');
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }
}
