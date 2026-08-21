<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Services\CallSummaryIntegrationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallSummaryIntegrationServiceTest extends TestCase
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

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
    }

    public function test_recording_id_updates_existing_followup_for_same_lead(): void
    {
        $user = User::create([
            'name' => 'Pallavi Singh',
            'email' => 'pallavi@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Charter Customer',
            'contact_number' => '6282131599',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $user->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-08-14 10:00:00',
            'followup_note' => 'Initial active lead follow-up',
            'followed_by' => $user->id,
            'status' => 1,
        ]);

        $service = app(CallSummaryIntegrationService::class);

        $first = $service->receive([
            'phone_number' => '6282131599',
            'summary' => 'Customer asked for a revised quotation.',
            'followup_date' => '2026-08-15 11:30:00',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => '1001',
        ]);

        $createdFollowup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->where('followup_recording_id', 1001)
            ->firstOrFail();

        $this->assertSame('followup_created', $first->status);
        $this->assertSame(1001, $first->followup_recording_id);
        $this->assertSame(1, (int) $createdFollowup->status);

        $second = $service->receive([
            'phone_number' => '6282131599',
            'summary' => 'Customer confirmed interest and wants an updated quotation today.',
            'followup_date' => '2026-08-16 12:15:00',
            'call_start_at' => '2026-08-14 16:11:00',
            'call_end_at' => '2026-08-14 16:19:20',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'incoming',
            'sentiment_score' => 90,
            'followup_recording_id' => 1001,
        ]);

        $createdFollowup->refresh();

        $this->assertSame('followup_updated', $second->status);
        $this->assertSame($createdFollowup->id, $second->followup_id);
        $this->assertSame(
            1,
            LeadFollowup::query()
                ->where('lead_id', $lead->id)
                ->where('followup_recording_id', 1001)
                ->count()
        );
        $this->assertSame(
            'Customer confirmed interest and wants an updated quotation today.',
            $createdFollowup->followup_note
        );
        $this->assertSame(
            '2026-08-16 12:15:00',
            $createdFollowup->next_followup_date->format('Y-m-d H:i:s')
        );
    }

    public function test_supplied_lead_id_matches_when_phone_belongs_to_lead(): void
    {
        $user = User::create([
            'name' => 'Pallavi Singh',
            'email' => 'pallavi-lead-id@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Skyrack Customer',
            'contact_number' => '+91-6282131599',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $user->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-08-14 10:00:00',
            'followup_note' => 'Existing follow-up',
            'followed_by' => $user->id,
            'status' => 1,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'lead_id' => $lead->id,
            'phone_number' => '6282131599',
            'summary' => 'Customer asked Skyrack to send a revised quotation.',
            'followup_date' => '2026-08-15 11:30:00',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => 2001,
        ]);

        $this->assertSame('followup_created', $integration->status);
        $this->assertSame($lead->id, $integration->lead_id);
        $this->assertSame('provided_lead_id', $integration->match_method);
        $this->assertSame(100, (int) $integration->match_score);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followup_recording_id' => 2001,
            'followup_note' => 'Customer asked Skyrack to send a revised quotation.',
            'status' => 1,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->integer('status')->default(1);
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
