<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadAiScore;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAiPayloadBuilder;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadAiPayloadBuilderTest extends TestCase
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

    public function test_incremental_payload_uses_previous_state_and_newest_interaction_without_pii_or_old_coaching(): void
    {
        $user = $this->salesUser();

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'representative_user_id' => $user->id,
            'number_of_passengers' => 4,
        ]);

        $this->followup(
            $lead,
            $user,
            'Customer confirmed Jaipur to Delhi route.',
            false,
            '2026-09-08 09:00:00'
        );

        $previousFollowup = $this->followup(
            $lead,
            $user,
            'Customer asked for the payment process.',
            false,
            '2026-09-08 09:30:00'
        );

        $previousScore = LeadAiScore::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followup_id' => $previousFollowup->id,
            'status' => 'completed',
            'temperature' => 'hot',
            'score' => 82,
            'confidence' => 91,
            'score_reason' => 'Customer asked how to proceed with payment.',
            'summary' => [
                'Customer has payment intent.',
            ],
            'score_change_reason' => 'Previous buying signal was strong.',
            'actions_json' => [
                [
                    'channel' => 'call',
                    'action' => 'Ask for payment',
                    'script' => 'Please pay now.',
                ],
            ],
            'state_json' => [
                'payment_intent' => true,
                'customer_ghosting' => false,
            ],
        ]);

        $this->followup(
            $lead,
            $user,
            'Customer did not pick up / respond.',
            true,
            '2026-09-08 10:00:00'
        );

        $current = $this->followup(
            $lead,
            $user,
            "Phone: 9876543210\nEmail: buyer@example.test\nCustomer did not pick up / respond.",
            true,
            '2026-09-08 11:00:00'
        );

        $payload = app(LeadAiPayloadBuilder::class)
            ->build($lead, $current, $previousScore);

        $this->assertSame(82, $payload['previous']['score']);
        $this->assertSame(
            'Customer asked how to proceed with payment.',
            $payload['previous']['reason']
        );
        $this->assertTrue($payload['previous']['state']['payment_intent']);
        $this->assertArrayNotHasKey('actions', $payload['previous']);
        $this->assertArrayNotHasKey('summary', $payload['previous']);
        $this->assertArrayNotHasKey('confidence', $payload['previous']);
        $this->assertArrayNotHasKey('temperature', $payload['previous']);
        $this->assertArrayNotHasKey('bootstrap_history', $payload);
        $this->assertArrayHasKey('interaction', $payload);
        $this->assertTrue($payload['interaction']['customer_not_picked_up']);
        $this->assertSame(2, $payload['contact']['consecutive_no_response_attempts']);

        $encoded = json_encode($payload);

        $this->assertStringNotContainsString('9876543210', $encoded);
        $this->assertStringNotContainsString('buyer@example.test', $encoded);
    }

    public function test_first_score_payload_uses_latest_ten_eligible_followups_in_chronological_order(): void
    {
        $user = $this->salesUser();

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'representative_user_id' => $user->id,
        ]);

        $current = null;

        for ($i = 1; $i <= 12; $i++) {
            $current = $this->followup(
                $lead,
                $user,
                "Customer update {$i}",
                false,
                Carbon::create(2026, 9, 8, 9, 0, 0)
                    ->addMinutes($i)
                    ->toDateTimeString()
            );
        }

        $payload = app(LeadAiPayloadBuilder::class)
            ->build($lead, $current, null);

        $this->assertArrayNotHasKey('interaction', $payload);
        $this->assertArrayHasKey('bootstrap_history', $payload);
        $this->assertCount(10, $payload['bootstrap_history']);
        $this->assertSame(
            'Customer update 3',
            $payload['bootstrap_history'][0]['note']
        );
        $this->assertSame(
            'Customer update 12',
            $payload['bootstrap_history'][9]['note']
        );
    }

    private function salesUser(): User
    {
        $type = UserType::create([
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
        bool $customerNotPickedUp,
        string $createdAt
    ): LeadFollowup {
        $followup = new LeadFollowup([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followup_note' => $note,
            'status' => 1,
            'followed_by' => $user->id,
            'contact_outcome' => $customerNotPickedUp
                ? LeadFollowup::CONTACT_OUTCOME_NO_ANSWER
                : null,
            'customer_not_picked_up' => $customerNotPickedUp,
        ]);

        $followup->created_at = Carbon::parse($createdAt);
        $followup->updated_at = Carbon::parse($createdAt);
        $followup->save();

        return $followup;
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
            $table->string('next_commitment', 120)->nullable();
            $table->json('state_json')->nullable();
            $table->string('input_hash', 64)->nullable()->index();
            $table->string('model')->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('prompt_version', 60)->nullable();
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

        Schema::create('email_lead_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('message_id');
            $table->string('sender_email');
            $table->uuid('lead_id')->nullable();
            $table->string('processing_status')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_audit_trail', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_followup_id');
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->integer('payment_status')->nullable();
            $table->timestamps();
        });
    }
}
