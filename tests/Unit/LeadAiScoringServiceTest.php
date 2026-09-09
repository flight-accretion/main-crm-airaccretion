<?php

namespace Tests\Unit;

use App\Jobs\ProcessLeadAiScore;
use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadAiScoringSetting;
use App\Models\EmailLeadLog;
use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAiOpenAiClient;
use App\Services\LeadAiScoringService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadAiScoringServiceTest extends TestCase
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
        $this->activeSetting();
    }

    public function test_first_score_payload_uses_latest_ten_eligible_history_items(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $current = null;

        for ($i = 1; $i <= 12; $i++) {
            $current =
                $this->followup(
                    $lead,
                    $user,
                    "Eligible follow-up {$i}",
                    null,
                    Carbon::create(2026, 9, 8, 9, 0, 0)
                        ->addMinutes($i)
                );
        }

        $score =
            LeadAiScore::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'followup_id' => $current->id,
                'status' => 'pending',
            ]);

        $capturedPayload = null;

        $this->mock(
            LeadAiOpenAiClient::class,
            function ($mock) use (&$capturedPayload) {
                $mock
                    ->shouldReceive('analyse')
                    ->once()
                    ->withArgs(
                        function ($setting, $payload) use (&$capturedPayload) {
                            $capturedPayload =
                                $payload;

                            return $setting instanceof LeadAiScoringSetting
                                && is_array($payload);
                        }
                    )
                    ->andReturn($this->aiResult());
            }
        );

        app(LeadAiScoringService::class)
            ->process($score->id);

        $this->assertArrayHasKey(
            'bootstrap_history',
            $capturedPayload
        );

        $this->assertArrayNotHasKey(
            'interaction',
            $capturedPayload
        );

        $this->assertCount(
            10,
            $capturedPayload['bootstrap_history']
        );

        $this->assertSame(
            'Eligible follow-up 3',
            $capturedPayload['bootstrap_history'][0]['note']
        );

        $this->assertSame(
            'Eligible follow-up 12',
            $capturedPayload['bootstrap_history'][9]['note']
        );

        $score->refresh();

        $this->assertSame(
            'completed',
            $score->status
        );

        $this->assertSame(
            LeadAiScoringService::PROMPT_VERSION,
            $score->prompt_version
        );

        $this->assertSame(
            7,
            $score->thinking_tokens
        );
    }

    public function test_approved_payment_prevents_queueing_new_ai_score(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $paidFollowup =
            $this->followup(
                $lead,
                $user,
                'Payment received for review.',
                null,
                Carbon::create(2026, 9, 8, 9, 0, 0)
            );

        PaymentAuditTrail::create([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $paidFollowup->id,
            'paid_amount' => 1,
            'payment_status' => 1,
        ]);

        $newFollowup =
            $this->followup(
                $lead,
                $user,
                'Customer asked another question.',
                null,
                Carbon::create(2026, 9, 8, 10, 0, 0)
            );

        $result =
            app(LeadAiScoringService::class)
                ->queueForFollowup($newFollowup);

        $this->assertNull($result);
        $this->assertSame(0, LeadAiScore::query()->count());
    }

    public function test_queue_dispatches_processing_job_after_database_commit(): void
    {
        Queue::fake();

        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $followup =
            $this->followup(
                $lead,
                $user,
                'Customer asked for available charter options.',
                null,
                Carbon::create(2026, 9, 8, 9, 0, 0)
            );

        $score =
            app(LeadAiScoringService::class)
                ->queueForFollowup($followup);

        $this->assertNotNull($score);

        Queue::assertPushed(
            ProcessLeadAiScore::class,
            function (ProcessLeadAiScore $job) use ($score) {
                return $job->scoreId === $score->id
                    && ($job->afterCommit ?? false) === true;
            }
        );
    }

    public function test_process_marks_score_skipped_when_lead_becomes_booked_before_job_runs(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $followup =
            $this->followup(
                $lead,
                $user,
                'Customer asked for payment details.',
                null,
                Carbon::create(2026, 9, 8, 9, 0, 0)
            );

        $score =
            LeadAiScore::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'followup_id' => $followup->id,
                'status' => 'pending',
            ]);

        PaymentAuditTrail::create([
            'id' => (string) Str::uuid(),
            'lead_followup_id' => $followup->id,
            'paid_amount' => 1,
            'payment_status' => 1,
        ]);

        $this->mock(
            LeadAiOpenAiClient::class,
            function ($mock) {
                $mock
                    ->shouldReceive('analyse')
                    ->never();
            }
        );

        app(LeadAiScoringService::class)
            ->process($score->id);

        $score->refresh();

        $this->assertSame('skipped', $score->status);
        $this->assertSame(1, $score->attempt_count);
        $this->assertNull($score->last_error);
        $this->assertNotNull($score->processed_at);
    }

    public function test_non_active_lead_status_prevents_queueing_new_ai_score(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $followup =
            $this->followup(
                $lead,
                $user,
                'Customer cancelled the enquiry.',
                null,
                Carbon::create(2026, 9, 8, 9, 0, 0),
                2
            );

        $result =
            app(LeadAiScoringService::class)
                ->queueForFollowup($followup);

        $this->assertNull($result);
        $this->assertSame(0, LeadAiScore::query()->count());
    }

    public function test_email_source_fact_is_sent_to_ai_payload(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        EmailLeadLog::create([
            'id' => (string) Str::uuid(),
            'message_id' => 'email-source-score-test',
            'sender_email' => 'customer@example.test',
            'lead_id' => $lead->id,
            'processing_status' => 'assigned',
            'received_at' => Carbon::create(2026, 9, 8, 8, 30, 0),
        ]);

        $current =
            $this->followup(
                $lead,
                $user,
                'Lead received automatically from Email.',
                null,
                Carbon::create(2026, 9, 8, 9, 0, 0)
            );

        $score =
            LeadAiScore::create([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'followup_id' => $current->id,
                'status' => 'pending',
            ]);

        $capturedPayload = null;

        $this->mock(
            LeadAiOpenAiClient::class,
            function ($mock) use (&$capturedPayload) {
                $mock
                    ->shouldReceive('analyse')
                    ->once()
                    ->withArgs(
                        function ($setting, $payload) use (&$capturedPayload) {
                            $capturedPayload =
                                $payload;

                            return $setting instanceof LeadAiScoringSetting
                                && is_array($payload);
                        }
                    )
                    ->andReturn($this->aiResult());
            }
        );

        app(LeadAiScoringService::class)
            ->process($score->id);

        $this->assertSame(
            'email',
            $capturedPayload['crm']['lead_source'] ?? null
        );

        $this->assertTrue(
            $capturedPayload['crm']['email_source_lead'] ?? false
        );
    }

    private function activeSetting(): void
    {
        LeadAiScoringSetting::create([
            'id' => (string) Str::uuid(),
            'enabled' => true,
            'auto_analyse' => true,
            'cold_max' => 39,
            'neutral_max' => 69,
        ]);
    }

    private function aiResult(): array
    {
        return [
            'score' => 55,
            'confidence' => 80,
            'score_reason' => 'Customer has moderate buying intent.',
            'summary' => [
                'Customer is still evaluating.',
            ],
            'score_change_reason' => 'Initial score from history.',
            'actions' => [
                [
                    'channel' => 'call',
                    'action' => 'Confirm requirement',
                    'script' => 'May I confirm your requirement?',
                ],
                [
                    'channel' => 'whatsapp',
                    'action' => 'Ask for date',
                    'script' => 'Please share your preferred date.',
                ],
                [
                    'channel' => 'call',
                    'action' => 'Check budget',
                    'script' => 'May I understand your budget range?',
                ],
            ],
            'next_commitment' => 'confirm_requirement',
            'state' => [
                'customer_ghosting' => false,
            ],
            'model' => 'gemini-test',
            'provider' => 'gemini',
            'usage' => [
                'input_tokens' => 100,
                'cached_input_tokens' => 10,
                'output_tokens' => 50,
                'thinking_tokens' => 7,
                'total_tokens' => 150,
            ],
            'processing_ms' => 25,
        ];
    }

    private function salesUser(): User
    {
        $type =
            UserType::create([
                'id' => (string) Str::uuid(),
                'user_type' => UserType::SALES_EXECUTIVE,
                'status' => 1,
            ]);

        return User::create([
            'name' => 'Sales User',
            'email' => Str::uuid() . '@example.test',
            'password' => 'secret',
            'user_type_id' => $type->id,
            'status' => 1,
        ]);
    }

    private function followup(
        Lead $lead,
        User $user,
        string $note,
        ?string $contactOutcome,
        Carbon $createdAt,
        int $status = 1
    ): LeadFollowup {
        return LeadFollowup::withoutEvents(
            function () use (
                $lead,
                $user,
                $note,
                $contactOutcome,
                $createdAt,
                $status
            ) {
                $followup =
                    new LeadFollowup([
                        'id' => (string) Str::uuid(),
                        'lead_id' => $lead->id,
                        'followup_note' => $note,
                        'status' => $status,
                        'followed_by' => $user->id,
                        'contact_outcome' => $contactOutcome,
                    ]);

                $followup->created_at =
                    $createdAt;

                $followup->updated_at =
                    $createdAt;

                $followup->save();

                return $followup;
            }
        );
    }

    private function createSchema(): void
    {
        Schema::create('user_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->uuid('parent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->uuid('user_type_id')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('representative_user_id')->nullable();
            $table->json('service_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->unsignedInteger('number_of_passengers')->nullable();
            $table->string('occasion')->nullable();
            $table->timestamps();
        });

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('message_id');
            $table->string('sender_email');
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->string('contact_outcome', 30)->nullable();
            $table->boolean('customer_not_picked_up')->default(false);
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->unsignedInteger('followup_recording_id')->nullable();
            $table->decimal('total_amount', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('lead_ai_scores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id')->index();
            $table->uuid('followup_id')->unique();
            $table->uuid('previous_score_id')->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->string('temperature', 20)->nullable();
            $table->unsignedSmallInteger('score')->nullable();
            $table->unsignedSmallInteger('confidence')->nullable();
            $table->text('score_reason')->nullable();
            $table->json('summary')->nullable();
            $table->text('suggested_action')->nullable();
            $table->text('score_change_reason')->nullable();
            $table->json('actions_json')->nullable();
            $table->string('next_commitment', 80)->nullable();
            $table->json('state_json')->nullable();
            $table->string('input_hash', 64)->nullable()->index();
            $table->string('model')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('prompt_version', 50)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('cached_input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('thinking_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->unsignedInteger('processing_ms')->nullable();
            $table->uuid('analysed_by')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lead_ai_scoring_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('enabled')->default(false);
            $table->boolean('auto_analyse')->default(true);
            $table->string('model')->nullable();
            $table->text('prompt')->nullable();
            $table->unsignedSmallInteger('cold_max')->default(39);
            $table->unsignedSmallInteger('neutral_max')->default(69);
            $table->uuid('ai_model_profile_id')->nullable();
            $table->uuid('ai_agent_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->integer('payment_status')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('product')->nullable();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service')->nullable();
        });

        Schema::create('lead_rides', function (Blueprint $table) {
            $table->id();
            $table->uuid('lead_id');
            $table->dateTime('from_date')->nullable();
            $table->dateTime('to_date')->nullable();
            $table->string('from_place')->nullable();
            $table->string('to_place')->nullable();
            $table->timestamps();
        });
    }
}
