<?php

namespace Tests\Unit;

use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\UserType;
use App\Services\LeadAiContactSignalService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadAiContactSignalServiceTest extends TestCase
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

    public function test_structured_no_answer_followups_count_until_meaningful_engagement(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $this->followup(
            $lead,
            $user,
            'Customer asked for options.',
            null,
            '2026-09-08 09:00:00'
        );

        foreach (
            [
                '2026-09-08 10:00:00',
                '2026-09-08 11:00:00',
                '2026-09-08 12:00:00',
            ]
            as $createdAt
        ) {
            $this->followup(
                $lead,
                $user,
                'Customer did not pick up / respond.',
                LeadFollowup::CONTACT_OUTCOME_NO_ANSWER,
                $createdAt
            );
        }

        $signals =
            app(LeadAiContactSignalService::class)
                ->build($lead);

        $this->assertSame(
            3,
            $signals['consecutive_no_response_attempts']
        );

        $this->assertTrue(
            $signals['customer_ghosting_candidate']
        );

        $this->followup(
            $lead,
            $user,
            'Customer called back and asked for availability.',
            null,
            '2026-09-08 13:00:00'
        );

        $signals =
            app(LeadAiContactSignalService::class)
                ->build($lead);

        $this->assertSame(
            0,
            $signals['consecutive_no_response_attempts']
        );

        $this->assertFalse(
            $signals['customer_ghosting_candidate']
        );
    }

    public function test_free_text_no_answer_without_structured_outcome_does_not_count(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $this->followup(
            $lead,
            $user,
            'no answer',
            null,
            '2026-09-08 10:00:00'
        );

        $signals =
            app(LeadAiContactSignalService::class)
                ->build($lead);

        $this->assertSame(
            0,
            $signals['consecutive_no_response_attempts']
        );

        $this->assertFalse(
            $signals['customer_ghosting_candidate']
        );
    }

    public function test_signals_can_be_bounded_to_the_followup_being_scored(): void
    {
        $user =
            $this->salesUser();

        $lead =
            Lead::create([
                'id' => (string) Str::uuid(),
                'representative_user_id' => $user->id,
            ]);

        $firstNoAnswer =
            $this->followup(
                $lead,
                $user,
                'Customer did not pick up / respond.',
                LeadFollowup::CONTACT_OUTCOME_NO_ANSWER,
                '2026-09-08 10:00:00'
            );

        $this->followup(
            $lead,
            $user,
            'Customer did not pick up / respond.',
            LeadFollowup::CONTACT_OUTCOME_NO_ANSWER,
            '2026-09-08 11:00:00'
        );

        $signals =
            app(LeadAiContactSignalService::class)
                ->build(
                    $lead,
                    $firstNoAnswer
                );

        $this->assertSame(
            1,
            $signals['consecutive_no_response_attempts']
        );

        $this->assertFalse(
            $signals['customer_ghosting_candidate']
        );
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
        string $createdAt
    ): LeadFollowup {
        $followup =
            new LeadFollowup([
                'id' => (string) Str::uuid(),
                'lead_id' => $lead->id,
                'followup_note' => $note,
                'status' => 1,
                'followed_by' => $user->id,
                'contact_outcome' => $contactOutcome,
            ]);

        $followup->created_at =
            Carbon::parse($createdAt);

        $followup->updated_at =
            Carbon::parse($createdAt);

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
            $table->integer('status')->nullable();
            $table->uuid('followed_by')->nullable();
            $table->unsignedInteger('followup_recording_id')->nullable();
            $table->timestamps();
        });
    }
}
