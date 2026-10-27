<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SalesDashboardCompactDesignTest extends TestCase
{
    public function test_compact_dashboard_keeps_live_sales_data_and_existing_workflow_links(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root . '/resources/views/admin/pages/dashboards/sales-dashboard.blade.php');

        $this->assertStringContainsString('crm-sales-dashboard', $view);
        $this->assertStringContainsString('compact-sales-scope', $view);
        $this->assertStringContainsString('admin.kpi.outreach.index', $view);
        $this->assertStringContainsString('admin.upcoming-follow-up.index', $view);
        $this->assertStringContainsString('admin.leads.follow-up.create', $view);
        $this->assertStringContainsString('sales-dashboard-legacy', $view);
    }

    public function test_dashboard_controller_provides_safe_outreach_summary_values(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/app/Http/Controllers/DashboardController.php');

        $this->assertStringContainsString('todayFreshLeadCount', $source);
        $this->assertStringContainsString('outreachCompletedToday', $source);
        $this->assertStringContainsString('outreachPendingCount', $source);
        $this->assertStringContainsString('outreachTarget', $source);
    }
}
