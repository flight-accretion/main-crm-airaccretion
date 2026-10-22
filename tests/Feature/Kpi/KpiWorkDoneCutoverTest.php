<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KpiWorkDoneCutoverTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::table('kpi_outreach_assignments', function ($table) {
            $table->string('completion_type')->nullable();
        });
    }

public function test_historical_lead_work_before_cutover_is_marked_unavailable(): void
{
    config([
        'kpi.activity_event_cutover_at'
            => '2026-09-16 20:00:00',
    ]);

    $sales =
        $this->createUserWithRole(
            'Sales Executive',
            \App\Models\UserType::SALES_EXECUTIVE
        );

    $this->actingAs($sales)
        ->get(
            '/admin/kpi/work-done'
            . '?date_type=custom'
            . '&from_date=2026-09-10'
            . '&to_date=2026-09-10'
        )
        ->assertOk()
        ->assertSee(
            'Lead Work data is not available'
        );
}

}
