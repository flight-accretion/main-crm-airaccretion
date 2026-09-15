<?php

namespace Tests\Unit;

use App\Models\LeadFollowup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClearHiddenTodayFollowupsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('next_followup_date')->nullable();
            $table->text('followup_note')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    public function test_dry_run_reports_stale_cancelled_lead_followup_without_mutating(): void
    {
        $leadId = (string) Str::uuid();
        $staleFollowup = $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_ACTIVE,
            '2026-09-15 11:00:00',
            '2026-09-14 09:00:00'
        );
        $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_CANCELLED,
            null,
            '2026-09-14 10:00:00'
        );

        $this->artisan(
            'leads:clear-hidden-today-followups',
            ['--date' => '2026-09-15']
        )
            ->expectsOutput('Dry run: yes')
            ->expectsOutput('Stale due follow-ups found: 1')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-09-15 11:00:00',
            LeadFollowup::query()->find($staleFollowup->id)->next_followup_date->format('Y-m-d H:i:s')
        );
    }

    public function test_apply_clears_stale_due_date_when_latest_status_is_hidden(): void
    {
        $leadId = (string) Str::uuid();
        $staleFollowup = $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_ACTIVE,
            '2026-09-15 11:00:00',
            '2026-09-14 09:00:00'
        );
        $cancelledFollowup = $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_CANCELLED,
            null,
            '2026-09-14 10:00:00'
        );

        $this->artisan(
            'leads:clear-hidden-today-followups',
            [
                '--date' => '2026-09-15',
                '--apply' => true,
            ]
        )
            ->expectsOutput('Dry run: no')
            ->expectsOutput('Stale due follow-ups found: 1')
            ->expectsOutput('Due dates cleared: 1')
            ->assertExitCode(0);

        $this->assertNull(
            LeadFollowup::query()->find($staleFollowup->id)->next_followup_date
        );
        $this->assertSame(
            LeadFollowup::STATUS_CANCELLED,
            (int) LeadFollowup::query()->find($cancelledFollowup->id)->status
        );
    }

    public function test_apply_does_not_clear_reopened_lead_due_date(): void
    {
        $leadId = (string) Str::uuid();
        $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_CANCELLED,
            null,
            '2026-09-14 09:00:00'
        );
        $currentOpenFollowup = $this->createFollowup(
            $leadId,
            LeadFollowup::STATUS_ACTIVE,
            '2026-09-15 11:00:00',
            '2026-09-14 10:00:00'
        );

        $this->artisan(
            'leads:clear-hidden-today-followups',
            [
                '--date' => '2026-09-15',
                '--apply' => true,
            ]
        )
            ->expectsOutput('Stale due follow-ups found: 0')
            ->expectsOutput('Due dates cleared: 0')
            ->assertExitCode(0);

        $this->assertSame(
            '2026-09-15 11:00:00',
            LeadFollowup::query()->find($currentOpenFollowup->id)->next_followup_date->format('Y-m-d H:i:s')
        );
    }

    private function createFollowup(
        string $leadId,
        int $status,
        ?string $nextFollowupDate,
        string $createdAt
    ): LeadFollowup {
        return LeadFollowup::query()->forceCreate([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'status' => $status,
            'next_followup_date' => $nextFollowupDate,
            'followup_note' => 'Test follow-up',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
