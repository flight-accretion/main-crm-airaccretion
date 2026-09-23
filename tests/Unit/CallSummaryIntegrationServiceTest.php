<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\IvrAgent;
use App\Models\IvrCallLog;
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

    public function test_dnp_summary_without_text_creates_no_answer_followup(): void
    {
        $user = User::create([
            'name' => 'Pallavi Singh',
            'email' => 'pallavi-dnp@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'DNP Customer',
            'contact_number' => '9000000099',
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
            'followup_note' => 'Existing active follow-up',
            'followed_by' => $user->id,
            'status' => 1,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000099',
            'followup_date' => null,
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:10:30',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'outgoing',
            'sentiment_score' => null,
            'followup_recording_id' => 4001,
            'dnp' => true,
        ]);

        $this->assertSame('followup_created', $integration->status);
        $this->assertTrue($integration->is_dnp);
        $this->assertSame('Customer did not pick up the call.', $integration->summary);

        $followup = LeadFollowup::query()
            ->where('lead_id', $lead->id)
            ->where('followup_recording_id', 4001)
            ->firstOrFail();

        $this->assertSame('Customer did not pick up the call.', $followup->followup_note);
        $this->assertSame(LeadFollowup::CONTACT_OUTCOME_NO_ANSWER, $followup->contact_outcome);
        $this->assertTrue($followup->customer_not_picked_up);
    }

    public function test_agent_phone_is_primary_agent_mapping_key(): void
    {
        $actualAgent = User::create([
            'name' => 'Sourav Namdeo',
            'email' => 'sourav-agent-phone@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        User::create([
            'name' => 'Wrong Agent',
            'email' => 'wrong-agent-phone@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        IvrAgent::create([
            'vi_agent_name' => 'Sourav SkyRack',
            'vi_agent_number' => '9876500000',
            'mapped_user_id' => $actualAgent->id,
            'is_active' => true,
        ]);

        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Agent Phone Customer',
            'contact_number' => '9000000199',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $actualAgent->id,
        ]);

        LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => '2026-08-14 10:00:00',
            'followup_note' => 'Existing active follow-up',
            'followed_by' => $actualAgent->id,
            'status' => 1,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000199',
            'agent_phone' => '+91 98765 00000',
            'summary' => 'Customer requested pricing from Sourav.',
            'followup_date' => '2026-08-15 11:30:00',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'agent_name' => 'Wrong Agent',
            'direction' => 'outgoing',
            'sentiment_score' => 82,
            'followup_recording_id' => 4101,
        ]);

        $this->assertSame('followup_created', $integration->status);
        $this->assertSame($actualAgent->id, $integration->agent_user_id);
        $this->assertSame('+91 98765 00000', $integration->agent_phone);
        $this->assertSame('9876500000', $integration->normalized_agent_phone);
        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $actualAgent->id,
            'followup_recording_id' => 4101,
            'followup_note' => 'Customer requested pricing from Sourav.',
        ]);
    }

    public function test_ivr_lead_id_is_not_trusted_when_call_phone_belongs_to_another_customer(): void
    {
        $user = User::create([
            'name' => 'Pallavi Singh',
            'email' => 'pallavi-stale-ivr@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $wrongClient = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Wrong Customer',
            'contact_number' => '9000000001',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $wrongLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $wrongClient->id,
            'representative_user_id' => $user->id,
        ]);

        $correctClient = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Correct Customer',
            'contact_number' => '9000000002',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $correctLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $correctClient->id,
            'representative_user_id' => $user->id,
        ]);

        IvrCallLog::create([
            'id' => (string) Str::uuid(),
            'cli' => '9000000002',
            'normalized_phone' => '9000000002',
            'agent_name' => 'Pallavi Singh',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'lead_id' => $wrongLead->id,
        ]);

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000002',
            'summary' => 'Correct customer asked for a revised quotation.',
            'followup_date' => '2026-08-15 11:30:00',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => 3001,
        ]);

        $this->assertSame('ambiguous_match', $integration->status);
        $this->assertSame('ivr_lead_id_phone_mismatch', $integration->match_method);
        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $wrongLead->id,
            'followup_note' => 'Correct customer asked for a revised quotation.',
        ]);
        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $correctLead->id,
            'followup_note' => 'Correct customer asked for a revised quotation.',
        ]);
    }

    public function test_phone_fallback_does_not_write_summary_when_same_phone_belongs_to_multiple_customers(): void
    {
        $user = User::create([
            'name' => 'Pallavi Singh',
            'email' => 'pallavi-duplicate-phone@example.test',
            'password' => 'secret',
            'status' => 1,
        ]);

        $firstClient = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'First Duplicate Phone Customer',
            'contact_number' => '9000000003',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $secondClient = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'Second Duplicate Phone Customer',
            'contact_number' => '9000000003',
            'alternate_number' => null,
            'status' => 1,
        ]);

        $firstLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $firstClient->id,
            'representative_user_id' => $user->id,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $secondLead = Lead::create([
            'id' => (string) Str::uuid(),
            'client_id' => $secondClient->id,
            'representative_user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$firstLead, $secondLead] as $lead) {
            LeadFollowup::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'next_followup_date' => '2026-08-14 10:00:00',
                'followup_note' => 'Existing active follow-up',
                'followed_by' => $user->id,
                'status' => 1,
            ]);
        }

        $integration = app(CallSummaryIntegrationService::class)->receive([
            'phone_number' => '9000000003',
            'summary' => 'Duplicate phone caller asked for callback.',
            'followup_date' => '2026-08-15 11:30:00',
            'call_start_at' => '2026-08-14 16:10:00',
            'call_end_at' => '2026-08-14 16:18:20',
            'agent_name' => 'Pallavi Singh',
            'direction' => 'incoming',
            'sentiment_score' => 82,
            'followup_recording_id' => 3002,
        ]);

        $this->assertSame('ambiguous_match', $integration->status);
        $this->assertSame('active_lead_phone_ambiguous', $integration->match_method);
        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $firstLead->id,
            'followup_note' => 'Duplicate phone caller asked for callback.',
        ]);
        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $secondLead->id,
            'followup_note' => 'Duplicate phone caller asked for callback.',
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
            $table->string('contact_outcome', 30)->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
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
            $table->string('vi_agent_number')->nullable();
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
            $table->string('agent_phone', 50)->nullable();
            $table->string('normalized_agent_phone', 20)->nullable();
            $table->string('direction', 20);
            $table->decimal('sentiment_score', 5, 2)->nullable();
            $table->boolean('is_dnp')->default(false);
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
