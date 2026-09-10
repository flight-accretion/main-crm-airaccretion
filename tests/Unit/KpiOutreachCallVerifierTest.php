<?php

namespace Tests\Unit;

use App\Models\CallSummaryIntegration;
use App\Models\IvrAgent;
use App\Models\IvrCallLog;
use App\Models\KpiOutreachAssignment;
use App\Models\User;
use App\Services\Kpi\KpiOutreachCallVerifier;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class KpiOutreachCallVerifierTest extends TestCase
{
    private string $prefix;

    private string $originalConnection;

    private array $createdTables = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        Carbon::setTestNow(Carbon::create(2026, 9, 10, 11, 0, 0));
        $this->prefix = 'kpi-verifier-' . Str::lower(Str::random(8));

        $this->ensureTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupRows();

        foreach (array_reverse($this->createdTables) as $table) {
            Schema::dropIfExists($table);
        }

        Carbon::setTestNow();
        $this->restoreDefaultDatabase();

        parent::tearDown();
    }

    public function test_no_answer_call_requires_matching_salesperson_phone_and_outbound_code(): void
    {
        config()->set('kpi.outreach.outbound_ivr_codes', ['OUTBOUND_TEST']);

        $user = $this->user();
        $assignment = $this->assignment('6501112201');

        IvrAgent::create([
            'vi_agent_name' => $this->prefix . '-agent',
            'vi_agent_number' => '9811112201',
            'mapped_user_id' => $user->id,
            'is_active' => true,
        ]);

        IvrCallLog::create([
            'provider_call_id' => $this->prefix . '-wrong-code',
            'call_type_code' => 'INBOUND_TEST',
            'normalized_phone' => '6501112201',
            'agent_name' => $this->prefix . '-agent',
            'agent_number' => '9811112201',
            'dial_status' => 'no_answer',
            'call_start_at' => now()->subMinutes(4),
        ]);

        IvrCallLog::create([
            'provider_call_id' => $this->prefix . '-connected',
            'call_type_code' => 'OUTBOUND_TEST',
            'normalized_phone' => '6501112201',
            'agent_name' => $this->prefix . '-agent',
            'agent_number' => '9811112201',
            'dial_status' => 'success',
            'call_start_at' => now()->subMinutes(3),
        ]);

        $expected = IvrCallLog::create([
            'provider_call_id' => $this->prefix . '-no-answer',
            'call_type_code' => 'OUTBOUND_TEST',
            'normalized_phone' => '6501112201',
            'agent_name' => $this->prefix . '-agent',
            'agent_number' => '+91 98111 12201',
            'dial_status' => 'no_answer',
            'call_start_at' => now()->subMinutes(2),
        ]);

        $actual = app(KpiOutreachCallVerifier::class)->noAnswerCall(
            $assignment,
            $user
        );

        $this->assertNotNull($actual);
        $this->assertSame($expected->id, $actual->id);
    }

    public function test_connected_call_requires_outgoing_summary_after_assignment(): void
    {
        $user = $this->user();
        $assignment = $this->assignment('6501112202');

        CallSummaryIntegration::create([
            'call_fingerprint' => $this->prefix . '-before',
            'phone_number' => '6501112202',
            'normalized_phone' => '6501112202',
            'summary' => 'Older summary before assignment.',
            'call_start_at' => now()->subMinutes(20),
            'call_end_at' => now()->subMinutes(19),
            'agent_name' => $this->prefix . '-agent',
            'direction' => 'outgoing',
            'agent_user_id' => $user->id,
        ]);

        CallSummaryIntegration::create([
            'call_fingerprint' => $this->prefix . '-incoming',
            'phone_number' => '6501112202',
            'normalized_phone' => '6501112202',
            'summary' => 'Incoming summary should not count.',
            'call_start_at' => now()->subMinutes(4),
            'call_end_at' => now()->subMinutes(3),
            'agent_name' => $this->prefix . '-agent',
            'direction' => 'incoming',
            'agent_user_id' => $user->id,
        ]);

        $expected = CallSummaryIntegration::create([
            'call_fingerprint' => $this->prefix . '-outgoing',
            'phone_number' => '6501112202',
            'normalized_phone' => '6501112202',
            'summary' => 'Connected outgoing summary.',
            'call_start_at' => now()->subMinutes(2),
            'call_end_at' => now()->subMinute(),
            'agent_name' => $this->prefix . '-agent',
            'direction' => 'outgoing',
            'agent_user_id' => $user->id,
        ]);

        $actual = app(KpiOutreachCallVerifier::class)->connectedCall(
            $assignment,
            $user
        );

        $this->assertNotNull($actual);
        $this->assertSame($expected->id, $actual->id);
    }

    private function user(): User
    {
        $user = new User([
            'name' => 'Verifier Sales',
            'email' => $this->prefix . '@example.test',
            'status' => 1,
        ]);

        $user->id = (string) Str::uuid();

        return $user;
    }

    private function assignment(string $phone): KpiOutreachAssignment
    {
        return new KpiOutreachAssignment([
            'normalized_phone' => $phone,
            'assigned_at' => now()->subMinutes(10),
        ]);
    }

    private function ensureTables(): void
    {
        if (!Schema::hasTable('ivr_agents')) {
            Schema::create('ivr_agents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('vi_agent_name', 150)->unique();
                $table->string('vi_agent_number', 20)->nullable()->unique();
                $table->uuid('mapped_user_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('remarks')->nullable();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->timestamps();
            });

            $this->createdTables[] = 'ivr_agents';
        }

        if (!Schema::hasTable('ivr_call_logs')) {
            Schema::create('ivr_call_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('provider_call_id', 150)->unique();
                $table->uuid('ivr_call_type_id')->nullable();
                $table->string('call_type_code', 50)->nullable();
                $table->string('dni', 50)->nullable();
                $table->string('cli', 50)->nullable();
                $table->string('normalized_phone', 20)->nullable();
                $table->string('raw_dtmf', 150)->nullable();
                $table->string('agent_name', 150)->nullable();
                $table->string('agent_number', 20)->nullable();
                $table->string('dial_status', 100)->nullable();
                $table->timestamp('call_start_at')->nullable();
                $table->timestamp('call_end_at')->nullable();
                $table->integer('duration_sec')->nullable();
                $table->integer('og_duration_sec')->nullable();
                $table->text('voice_url')->nullable();
                $table->uuid('lead_id')->nullable();
                $table->string('processing_status', 50)->default('received');
                $table->text('processing_message')->nullable();
                $table->timestamp('initial_followup_created_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });

            $this->createdTables[] = 'ivr_call_logs';
        }

        if (!Schema::hasTable('call_summary_integrations')) {
            Schema::create('call_summary_integrations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('call_fingerprint', 64)->unique();
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

            $this->createdTables[] = 'call_summary_integrations';
        }

        foreach ([
            'ivr_agents.vi_agent_number',
            'ivr_call_logs.agent_number',
        ] as $column) {
            [$table, $name] = explode('.', $column);

            if (!Schema::hasColumn($table, $name)) {
                $this->markTestSkipped(
                    sprintf('Required column %s.%s is missing.', $table, $name)
                );
            }
        }
    }

    private function cleanupRows(): void
    {
        if (Schema::hasTable('call_summary_integrations')) {
            CallSummaryIntegration::query()
                ->where('call_fingerprint', 'like', $this->prefix . '%')
                ->delete();
        }

        if (Schema::hasTable('ivr_call_logs')) {
            IvrCallLog::query()
                ->where('provider_call_id', 'like', $this->prefix . '%')
                ->delete();
        }

        if (Schema::hasTable('ivr_agents')) {
            IvrAgent::query()
                ->where('vi_agent_name', 'like', $this->prefix . '%')
                ->delete();
        }
    }

    private function useIsolatedDatabase(): void
    {
        $this->originalConnection = DB::getDefaultConnection();

        config()->set('database.connections.kpi_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('kpi_testing');
        DB::setDefaultConnection('kpi_testing');
    }

    private function restoreDefaultDatabase(): void
    {
        DB::disconnect('kpi_testing');
        DB::setDefaultConnection($this->originalConnection);
        DB::purge('kpi_testing');
    }
}
