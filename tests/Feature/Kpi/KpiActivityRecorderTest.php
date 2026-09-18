<?php

namespace Tests\Feature\Kpi;

use App\Models\LeadFollowup;
use App\Services\Kpi\KpiActivityRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KpiActivityRecorderTest extends KpiFeatureTestCase
{
    public function test_human_followup_events_are_recorded_once_for_notes_and_real_status_changes(): void
    {
        $actor = $this->createUserWithRole(
            'KPI Sales Agent',
            \App\Models\UserType::SALES_EXECUTIVE
        );
        $leadId = $this->createLeadFor($actor);

        $followup = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'followup_note' => 'Called customer and shared quotation.',
            'status' => 3,
            'followed_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recorder = app(KpiActivityRecorder::class);

        $recorder->recordSalesFollowup($followup, 1, $actor);
        $recorder->recordSalesFollowup($followup, 1, $actor);

        $this->assertSame(
            1,
            DB::table('kpi_activity_events')
                ->where('event_type', 'note_added')
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('kpi_activity_events')
                ->where('event_type', 'status_changed')
                ->count()
        );

        $this->assertDatabaseHas('kpi_activity_events', [
            'department' => 'sales',
            'user_id' => $actor->id,
            'entity_type' => 'lead',
            'entity_id' => $leadId,
            'event_type' => 'status_changed',
            'old_value' => '1',
            'new_value' => '3',
        ]);
    }

    public function test_same_or_missing_previous_status_does_not_create_status_change_event(): void
    {
        $actor = $this->createUserWithRole(
            'KPI Sales Agent Two',
            \App\Models\UserType::SALES_EXECUTIVE
        );
        $leadId = $this->createLeadFor($actor);

        $sameStatus = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'followup_note' => '',
            'status' => 1,
            'followed_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $noPreviousStatus = LeadFollowup::create([
            'id' => (string) Str::uuid(),
            'lead_id' => $leadId,
            'followup_note' => '',
            'status' => 2,
            'followed_by' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recorder = app(KpiActivityRecorder::class);
        $recorder->recordSalesFollowup($sameStatus, 1, $actor);
        $recorder->recordSalesFollowup($noPreviousStatus, null, $actor);

        $this->assertSame(
            0,
            DB::table('kpi_activity_events')
                ->where('event_type', 'status_changed')
                ->count()
        );
    }
}
