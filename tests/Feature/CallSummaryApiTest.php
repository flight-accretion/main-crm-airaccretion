<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
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

    public function test_rejected_call_summary_log_entries_can_be_replayed(): void
    {
        $agent = User::create([
            'name' => 'Samarpit Sharma',
            'email' => 'samarpit-replay@example.test',
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

        $logPath = storage_path(
            'logs/call-summary-replay-test.log'
        );

        @unlink($logPath);
        file_put_contents(
            $logPath,
            '[2026-09-03 13:36:04] production.WARNING: Call Summary API validation failed. '
            . json_encode([
                'errors' => [
                    'direction' => [
                        'direction must be incoming or outgoing.',
                    ],
                ],
                'payload_debug' => [
                    'keys' => [
                        'phone_number',
                        'summary',
                        'followup_date',
                        'call_start_at',
                        'call_end_at',
                        'agent_name',
                        'direction',
                        'sentiment_score',
                        'followup_recording_id',
                    ],
                    'lead_id' => null,
                    'phone_number' => '8655388628',
                    'agent_name' => 'Samarpit Sharma',
                    'direction' => 'unknown',
                    'call_start_at' => '2026-09-03 13:34:37',
                    'call_end_at' => '2026-09-03 13:34:56',
                    'followup_recording_id_present' => true,
                    'followup_recording_id' => 980,
                    'summary_present' => true,
                    'summary_length' => strlen($summary),
                    'summary_preview' => $summary,
                    'followup_date_present' => true,
                    'followup_date' => null,
                    'possible_summary_fields' => [
                        'summary',
                    ],
                    'possible_date_fields' => [
                        'followup_date',
                    ],
                ],
            ])
            . PHP_EOL
        );

        $this
            ->artisan(
                'call-summary:replay-rejected',
                [
                    '--path' => $logPath,
                    '--phone' => '8655388628',
                ]
            )
            ->assertExitCode(0);

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

        @unlink($logPath);
    }

    public function test_rejected_call_summary_replay_scans_all_recent_log_days_without_phone_filter(): void
    {
        $agent = User::create([
            'name' => 'Samarpit Sharma',
            'email' => 'samarpit-all-replay@example.test',
            'password' => 'secret',
            'status' => 1,
            'contact_number' => '9109152175',
        ]);

        $firstLead = $this->createIvrLeadForCallSummaryReplay(
            '8655388628',
            $agent
        );
        $secondLead = $this->createIvrLeadForCallSummaryReplay(
            '9699509168',
            $agent
        );

        $firstSummary =
            'First missed Skyrack summary for an IVR customer.';
        $secondSummary =
            'Second missed Skyrack summary for another IVR customer.';

        $firstLogPath = storage_path(
            'logs/laravel-2099-01-02.log'
        );
        $secondLogPath = storage_path(
            'logs/laravel-2099-01-01.log'
        );

        @unlink($firstLogPath);
        @unlink($secondLogPath);

        $this->writeRejectedCallSummaryLogLine(
            $firstLogPath,
            '8655388628',
            1980,
            $firstSummary
        );
        $this->writeRejectedCallSummaryLogLine(
            $secondLogPath,
            '9699509168',
            1981,
            $secondSummary
        );

        try {
            $this
                ->artisan(
                    'call-summary:replay-rejected',
                    [
                        '--date' => '2099-01-02',
                        '--days' => 2,
                        '--limit' => 10,
                    ]
                )
                ->assertExitCode(0);

            $this->assertDatabaseHas(
                'call_summary_integrations',
                [
                    'normalized_phone' => '8655388628',
                    'lead_id' => $firstLead->id,
                    'status' => 'followup_created',
                    'followup_recording_id' => 1980,
                    'summary' => $firstSummary,
                ]
            );

            $this->assertDatabaseHas(
                'call_summary_integrations',
                [
                    'normalized_phone' => '9699509168',
                    'lead_id' => $secondLead->id,
                    'status' => 'followup_created',
                    'followup_recording_id' => 1981,
                    'summary' => $secondSummary,
                ]
            );
        } finally {
            @unlink($firstLogPath);
            @unlink($secondLogPath);
        }
    }

    public function test_rejected_call_summary_replay_is_scheduled(): void
    {
        $schedule = new Schedule();
        $kernel = app(\App\Console\Kernel::class);
        $method = new \ReflectionMethod($kernel, 'schedule');
        $method->setAccessible(true);
        $method->invoke($kernel, $schedule);

        $event = collect($schedule->events())
            ->first(
                fn ($event) => str_contains(
                    $event->command,
                    'call-summary:replay-rejected --days=7 --limit=25'
                )
            );

        $this->assertNotNull(
            $event,
            'Rejected call-summary log replay must be scheduled for automatic recovery.'
        );

        $this->assertSame('*/5 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    private function createIvrLeadForCallSummaryReplay(
        string $phone,
        User $agent
    ): Lead {
        $client = Client::create([
            'id' => (string) Str::uuid(),
            'name' => 'IVR Lead ' . $phone,
            'contact_number' => $phone,
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

        return $lead;
    }

    private function writeRejectedCallSummaryLogLine(
        string $logPath,
        string $phone,
        int $recordingId,
        string $summary
    ): void {
        file_put_contents(
            $logPath,
            '[2099-01-02 13:36:04] production.WARNING: Call Summary API validation failed. '
            . json_encode([
                'errors' => [
                    'direction' => [
                        'direction must be incoming or outgoing.',
                    ],
                ],
                'payload_debug' => [
                    'keys' => [
                        'phone_number',
                        'summary',
                        'followup_date',
                        'call_start_at',
                        'call_end_at',
                        'agent_name',
                        'direction',
                        'sentiment_score',
                        'followup_recording_id',
                    ],
                    'lead_id' => null,
                    'phone_number' => $phone,
                    'agent_name' => 'Samarpit Sharma',
                    'direction' => 'unknown',
                    'call_start_at' => '2099-01-02 13:34:37',
                    'call_end_at' => '2099-01-02 13:34:56',
                    'followup_recording_id_present' => true,
                    'followup_recording_id' => $recordingId,
                    'summary_present' => true,
                    'summary_length' => strlen($summary),
                    'summary_preview' => $summary,
                    'followup_date_present' => true,
                    'followup_date' => null,
                    'possible_summary_fields' => [
                        'summary',
                    ],
                    'possible_date_fields' => [
                        'followup_date',
                    ],
                ],
            ])
            . PHP_EOL
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
