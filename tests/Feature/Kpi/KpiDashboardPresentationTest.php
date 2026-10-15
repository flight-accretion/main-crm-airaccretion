<?php

namespace Tests\Feature\Kpi;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class KpiDashboardPresentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi/views'));
        view()->share('errors', new ViewErrorBag());
    }

    private function renderDashboard(array $overrides = []): string
    {
        $user = new User(['name' => 'Test Employee']);
        $user->id = 'test-user';
        $metric = ['code' => 'test', 'name' => 'Configured Metric', 'description' => 'Existing metric description', 'score' => 3, 'weightage' => 100, 'display_actual' => '38 / 50 MTD', 'rating_labels' => [5 => '100%', 4 => '90%', 3 => '80%', 2 => '70%', 1 => '<60%'], 'next_score' => 4, 'next_threshold' => 90, 'improvement_line' => 'Existing improvement guidance', 'evidence' => ['verified_count' => 38], 'sort_order' => 1];
        $team = collect([['user' => $user, 'configured' => true, 'metrics' => [$metric], 'overall_score' => 3, 'weighted_raw_score' => 2.93]]);
        return view('admin.pages.kpi.dashboard', array_merge([
            'team' => $team,
            'department' => 'sales',
            'filter' => ['preset' => 'this_month', 'from' => Carbon::parse('2026-10-01'), 'to' => Carbon::parse('2026-10-14'), 'user_id' => null],
            'canSwitchDepartment' => false,
            'showOperationsManualEntry' => false,
        ], $overrides))->render();
    }

    public function test_existing_actual_score_weight_and_guidance_are_present_without_recalculation(): void
    {
        $html = $this->renderDashboard();
        foreach (['38 / 50 MTD', '2.93', '100 weight', '3/5', 'Existing improvement guidance', 'js-kpi-score', 'kpiImproveAllButton'] as $value) $this->assertStringContainsString($value, $html);
        $this->assertStringNotContainsString('operationsKpiManualEntryButton', $html);
    }

    public function test_person_filter_and_department_switch_flag_are_preserved(): void
    {
        $html = $this->renderDashboard(['filter' => ['preset' => 'custom', 'from' => Carbon::parse('2026-10-01'), 'to' => Carbon::parse('2026-10-14'), 'user_id' => 'test-user']]);
        $this->assertStringContainsString('name="user_id" value="test-user"', $html);
        $this->assertStringContainsString('name="department" value="sales"', $html);
        $this->assertStringNotContainsString('<select name="department"', $html);
    }

    public function test_operations_manual_entry_retains_existing_action_and_period(): void
    {
        $html = $this->renderDashboard(['department' => 'operations', 'filterAction' => route('admin.operations.kpi'), 'showOperationsManualEntry' => true]);
        $this->assertStringContainsString('operationsKpiManualEntryButton', $html);
        $this->assertStringContainsString(route('admin.operations.kpi.manual-value'), $html);
        $this->assertStringContainsString('name="month"', $html);
        $this->assertStringContainsString('value="10"', $html);
    }

    public function test_unconfigured_kpi_does_not_display_fabricated_scores(): void
    {
        $user = new User(['name' => 'Unconfigured Employee']);
        $html = $this->renderDashboard(['team' => collect([['user' => $user, 'configured' => false, 'metrics' => [], 'overall_score' => null]])]);
        $this->assertStringContainsString('Not configured', $html);
        $this->assertStringContainsString('No KPI template has been assigned.', $html);
        $this->assertStringNotContainsString('data-kpi-dashboard-row data-search', $html);
    }
}
