<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAiNoResponseService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadAiNoResponseServiceTest extends TestCase
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

    public function test_counts_structured_no_answers_until_meaningful_customer_response(): void
    {
        $user = $this->salesUser();

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'representative_user_id' => $user->id,
        ]);

        $this->followup(
            $lead,
            $user,
            'Customer asked for aircraft options.',
            false,
            '2026-09-08 09:00:00'
        );

        foreach (
            [
                '2026-09-08 10:00:00',
                '2026-09-08 11:00:00',
                '2026-09-08 12:00:00',
            ] as $createdAt
        ) {
            $this->followup(
                $lead,
                $user,
                'Customer did not pick up / respond.',
                true,
                $createdAt
            );
        }

        $this->assertSame(
            3,
            app(LeadAiNoResponseService::class)
                ->consecutiveNoResponseCount($lead)
        );

        $this->followup(
            $lead,
            $user,
            'Customer called back and asked for availability.',
            false,
            '2026-09-08 13:00:00'
        );

        $this->assertSame(
            0,
            app(LeadAiNoResponseService::class)
                ->consecutiveNoResponseCount($lead)
        );
    }

    public function test_system_followups_do_not_reset_no_answer_sequence(): void
    {
        $user = $this->salesUser();

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'representative_user_id' => $user->id,
        ]);

        $this->followup(
            $lead,
            $user,
            'Customer did not pick up / respond.',
            true,
            '2026-09-08 10:00:00'
        );

        $this->systemFollowup(
            $lead,
            'System updated payment status.',
            '2026-09-08 10:30:00'
        );

        $this->followup(
            $lead,
            $user,
            'Customer did not pick up / respond.',
            true,
            '2026-09-08 11:00:00'
        );

        $this->assertSame(
            2,
            app(LeadAiNoResponseService::class)
                ->consecutiveNoResponseCount($lead)
        );
    }

    public function test_current_unflagged_followup_returns_zero_even_when_prior_attempts_were_unanswered(): void
    {
        $user = $this->salesUser();

        $lead = Lead::create([
            'id' => (string) Str::uuid(),
            'representative_user_id' => $user->id,
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
            'Customer replied: please call after lunch.',
            false,
            '2026-09-08 11:00:00'
        );

        $this->assertSame(
            0,
            app(LeadAiNoResponseService::class)
                ->consecutiveNoResponseCount($lead, $current)
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

    private function systemFollowup(
        Lead $lead,
        string $note,
        string $createdAt
    ): LeadFollowup {
        $followup = new LeadFollowup([
            'id' => (string) Str::uuid(),
            'lead_id' => $lead->id,
            'followup_note' => $note,
            'status' => 1,
            'followed_by' => null,
            'customer_not_picked_up' => false,
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
            $table->timestamps();
        });
    }
}
