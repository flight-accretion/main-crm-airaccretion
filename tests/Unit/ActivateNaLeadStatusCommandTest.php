<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ActivateNaLeadStatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(
            Carbon::create(2026, 9, 5, 13, 45, 0)
        );

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

    public function test_dry_run_reports_na_status_leads_without_changing_database(): void
    {
        $leadWithoutFollowup = $this->createLead('No Followup Customer');
        $leadWithNullStatus = $this->createLead('Null Status Customer');
        $validLead = $this->createLead('Already Active Customer');

        $nullFollowup = $this->createFollowup(
            $leadWithNullStatus,
            null,
            now()->subMinutes(5)
        );
        $this->createFollowup(
            $validLead,
            1,
            now()->subMinutes(3)
        );

        $this->artisan('leads:activate-na-status')
            ->expectsOutput('Dry run: yes')
            ->expectsOutput('N/A status leads found: 2')
            ->expectsOutput('Would create active follow-ups: 1')
            ->expectsOutput('Would update latest follow-ups: 1')
            ->expectsOutput('Created active follow-ups: 0')
            ->expectsOutput('Updated latest follow-ups: 0')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('lead_followups', [
            'lead_id' => $leadWithoutFollowup->id,
            'status' => 1,
        ]);
        $this->assertSame(
            1,
            LeadFollowup::query()
                ->where('lead_id', $leadWithNullStatus->id)
                ->whereNull('status')
                ->count()
        );
        $this->assertSame(
            $nullFollowup->id,
            LeadFollowup::query()
                ->where('lead_id', $leadWithNullStatus->id)
                ->first()
                ->id
        );
    }

    public function test_commit_updates_latest_null_status_to_active(): void
    {
        $lead = $this->createLead('Latest Null Status Customer');
        $olderFollowup = $this->createFollowup(
            $lead,
            2,
            now()->subHour()
        );
        $latestFollowup = $this->createFollowup(
            $lead,
            null,
            now()->subMinute()
        );

        $this->artisan('leads:activate-na-status', ['--commit' => true])
            ->expectsOutput('Dry run: no')
            ->expectsOutput('N/A status leads found: 1')
            ->expectsOutput('Created active follow-ups: 0')
            ->expectsOutput('Updated latest follow-ups: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lead_followups', [
            'id' => $olderFollowup->id,
            'status' => 2,
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'id' => $latestFollowup->id,
            'status' => 1,
        ]);
    }

    public function test_commit_creates_active_followup_when_lead_has_no_followups(): void
    {
        $lead = $this->createLead(
            'Missing Followup Customer',
            (string) Str::uuid()
        );

        $this->artisan('leads:activate-na-status', ['--commit' => true])
            ->expectsOutput('Dry run: no')
            ->expectsOutput('N/A status leads found: 1')
            ->expectsOutput('Created active follow-ups: 1')
            ->expectsOutput('Updated latest follow-ups: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lead_followups', [
            'lead_id' => $lead->id,
            'followed_by' => $lead->representative_user_id,
            'followup_note' =>
                'One-time repair: N/A status set to Active.',
            'status' => 1,
        ]);
    }

    public function test_commit_skips_lead_when_latest_status_is_already_valid(): void
    {
        $lead = $this->createLead('Valid Latest Status Customer');
        $olderFollowup = $this->createFollowup(
            $lead,
            null,
            now()->subHour()
        );
        $latestFollowup = $this->createFollowup(
            $lead,
            5,
            now()->subMinute()
        );

        $this->artisan('leads:activate-na-status', ['--commit' => true])
            ->expectsOutput('Dry run: no')
            ->expectsOutput('N/A status leads found: 0')
            ->expectsOutput('Created active follow-ups: 0')
            ->expectsOutput('Updated latest follow-ups: 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('lead_followups', [
            'id' => $olderFollowup->id,
            'status' => null,
        ]);
        $this->assertDatabaseHas('lead_followups', [
            'id' => $latestFollowup->id,
            'status' => 5,
        ]);
    }

    private function createLead(
        string $clientName,
        ?string $representativeUserId = null
    ): Lead
    {
        $client = Client::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $clientName,
            'contact_number' => '9000000000',
            'status' => 1,
        ]);

        return Lead::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'representative_user_id' => $representativeUserId,
            'service_ids' => null,
            'product_ids' => null,
            'number_of_passengers' => 1,
            'description' => 'Lead status repair test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createFollowup(
        Lead $lead,
        ?int $status,
        Carbon $createdAt
    ): LeadFollowup {
        return LeadFollowup::forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'next_followup_date' => null,
            'followup_note' => 'Existing follow-up',
            'followed_by' => null,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('contact_number')->nullable();
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
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->timestamps();
        });
    }
}
