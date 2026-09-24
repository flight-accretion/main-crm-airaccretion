<?php

namespace Tests\Unit;

use Tests\TestCase;

class KpiDashboardTableUxTest extends TestCase
{
    public function test_kpi_dashboard_has_department_filter_and_scalable_table_controls(): void
    {
        $source = file_get_contents(
            resource_path('views/admin/pages/kpi/dashboard.blade.php')
        );

        $this->assertStringContainsString('name="department"', $source);
        $this->assertStringContainsString('id="kpi-dashboard-search"', $source);
        $this->assertStringContainsString('id="kpi-dashboard-page-size"', $source);
        $this->assertStringContainsString('data-kpi-dashboard-table', $source);
        $this->assertStringContainsString('initializeKpiDashboardTable', $source);
    }

    public function test_kpi_detail_and_outreach_tables_use_crm_datatable_hooks(): void
    {
        $workDone = file_get_contents(
            resource_path('views/admin/pages/kpi/work-done.blade.php')
        );
        $workDoneTable = file_get_contents(
            resource_path('views/admin/pages/kpi/partials/work-done-table.blade.php')
        );
        $outreachTable = file_get_contents(
            resource_path('views/admin/pages/kpi/partials/outreach-table.blade.php')
        );
        $dailyActivity = file_get_contents(
            resource_path('views/admin/pages/kpi/daily-activity.blade.php')
        );

        $this->assertStringContainsString('initializeKpiDetailTable', $workDone);
        $this->assertStringContainsString('kpi-detail-datatable', $workDoneTable);
        $this->assertStringContainsString('kpi-outreach-datatable', $outreachTable);
        $this->assertStringContainsString('kpi-daily-activity-datatable', $dailyActivity);
    }
}
