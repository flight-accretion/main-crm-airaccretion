<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\Hr\HrDashboardController;
use App\Http\Controllers\KpiDashboardController;
use App\Models\OperationCase;
use App\Models\UserType;
use App\Services\Hr\HrDashboardDataService;
use App\Services\Kpi\KpiDashboardFilterService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HrDashboardDynamicTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'kpi.activity_event_cutover_at' => null]);
        Schema::create('operation_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->string('type');
            $table->string('status');
            $table->uuid('assigned_to')->nullable();
            $table->uuid('completed_by')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_followup_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
        Schema::create('operation_case_activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('operation_case_id');
            $table->uuid('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('attendance_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('lead_rides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->timestamp('from_date')->nullable();
            $table->timestamps();
        });
    }

    public function test_imported_attendance_includes_sales_and_missing_is_not_absent(): void
    {
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $ops = $this->createUserWithRole('Operations', UserType::OPERATIONS_EXECUTIVE);
        $missing = $this->createUserWithRole('Missing', UserType::HR);
        $this->attendance($sales->id, '2026-09-17', 'P');
        $this->attendance($ops->id, '2026-09-17', 'A');
        $data = app(HrDashboardDataService::class)->attendance([$sales->id, $ops->id, $missing->id], now()->startOfDay(), now()->endOfDay());
        $this->assertSame(1, $data['summary']['present']);
        $this->assertSame(2, $data['summary']['recorded']);
        $this->assertSame(1, $data['summary']['missing']);
        $this->assertSame('Present', $data['users'][$sales->id]['label']);
        $this->assertSame('Absent', $data['users'][$ops->id]['label']);
        $this->assertSame('Not Available', $data['users'][$missing->id]['label']);
    }

    public function test_custom_attendance_counts_employee_days_not_unique_people(): void
    {
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $this->attendance($sales->id, '2026-09-16', 'P');
        $this->attendance($sales->id, '2026-09-17', 'CL');
        $data = app(HrDashboardDataService::class)->attendance([$sales->id], Carbon::parse('2026-09-15')->startOfDay(), now()->endOfDay());
        $this->assertSame('1 / 2 days', $data['users'][$sales->id]['label']);
        $this->assertSame(1, $data['summary']['missing']);
    }

    public function test_operations_work_is_date_filtered_and_has_separate_overdue_count(): void
    {
        $ops = $this->createUserWithRole('Ops', UserType::OPERATIONS_EXECUTIVE);
        $other = $this->createUserWithRole('Other Ops', UserType::OPERATIONS_EXECUTIVE);
        $case = $this->case($ops->id, 'review', '2026-09-17 10:00:00', 'in_progress');
        DB::table('operation_case_activities')->insert(['id' => Str::uuid(), 'operation_case_id' => $case, 'user_id' => $ops->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->case($ops->id, 'vendor_followup', '2026-09-17 12:00:00');
        $reviewDone = $this->case($ops->id, 'review', '2026-09-17 12:00:00');
        DB::table('operation_cases')->where('id', $reviewDone)->update(['metadata' => json_encode(['review_status' => 'completed'])]);
        $this->case($ops->id, 'customer_call', '2026-09-16 12:00:00');
        $this->case($ops->id, 'customer_call', '2026-09-18 12:00:00');
        $this->case($other->id, 'review', '2026-09-17 10:00:00');
        $completed = $this->case($ops->id, 'customer_call', '2026-09-17 10:00:00');
        DB::table('operation_cases')->where('id', $completed)->update(['completed_by' => $ops->id, 'completed_at' => now(), 'status' => 'completed']);
        $before = DB::table('operation_cases')->count();
        $row = app(HrDashboardDataService::class)->operationsWork([$ops->id], now()->startOfDay(), now()->endOfDay())[$ops->id];
        $this->assertSame(1, $row['completed']);
        $this->assertSame(1, $row['active']);
        $this->assertSame(3, $row['pending']);
        $this->assertSame(1, $row['review']);
        $this->assertSame(1, $row['vendor']);
        $this->assertSame(1, $row['overdue']);
        $this->assertNull($row['outreach']);
        $this->assertSame($before, DB::table('operation_cases')->count());
    }

    public function test_summary_defaults_today_counts_distinct_non_cancelled_service_leads(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $lead = $this->createLeadFor($sales);
        $cancelled = $this->createLeadFor($sales);
        foreach ([$lead, $lead, $cancelled] as $id) {
            DB::table('lead_rides')->insert(['id' => Str::uuid(), 'lead_id' => $id, 'from_date' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        // A historical cancellation must not hide a subsequently reactivated lead.
        $this->followup($lead, 2, '2026-09-16 10:00:00');
        $this->followup($lead, 1, '2026-09-17 10:00:00');
        $this->followup($cancelled, 2, '2026-09-17 10:00:00');
        $data = $this->dashboard($hr);
        $this->assertSame('today', $data['filter']['preset']);
        $this->assertSame(1, $data['wantService']);
        $this->assertSame(1, $data['todayFollowups']);
        $this->assertSame(2, $data['monthLeads']);
        $this->assertSame(1, $data['departmentRows']['sales']->count());
    }

    public function test_dashboard_keeps_all_employees_paginates_and_reuses_kpi_scores(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        for ($i = 0; $i < 12; $i++) {
            $this->createUserWithRole('Sales ' . $i, UserType::SALES_EXECUTIVE);
        }
        $data = $this->dashboard($hr);
        $this->assertSame(12, $data['departmentRows']['sales']->total());
        $this->assertCount(10, $data['departmentRows']['sales']);
        $this->assertSame('Not Available', $data['departmentRows']['sales'][0]['attendance']['label']);
        $this->assertSame('Not configured', $data['departmentRows']['sales'][0]['status']);
        $page2 = $this->dashboard($hr, ['sales_page' => 2]);
        $this->assertCount(2, $page2['departmentRows']['sales']);
    }

    public function test_single_person_kpi_navigation_enforces_scope(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $sales = $this->createUserWithRole('One Sales', UserType::SALES_EXECUTIVE);
        $other = $this->createUserWithRole('Other Sales', UserType::SALES_EXECUTIVE);
        $request = Request::create('/admin/kpi', 'GET', ['user_id' => $sales->id, 'department' => 'sales', 'preset' => 'today']);
        $request->setUserResolver(fn () => $hr);
        $view = app()->call([app(KpiDashboardController::class), 'index'], ['request' => $request]);
        $this->assertCount(1, $view->getData()['team']);
        $this->assertSame($sales->id, $view->getData()['team'][0]['user']->id);
        $this->assertFalse($view->getData()['canSwitchDepartment']);
        $request->setUserResolver(fn () => $other);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app()->call([app(KpiDashboardController::class), 'index'], ['request' => $request]);
    }

    public function test_yesterday_and_custom_dates_propagate_and_multi_month_targets_are_not_prorated(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $yesterday = $this->dashboard($hr, ['preset' => 'yesterday']);
        $this->assertSame('2026-09-16', $yesterday['filter']['from']->toDateString());
        $custom = $this->dashboard($hr, ['preset' => 'custom', 'from_date' => '2026-08-01', 'to_date' => '2026-09-17']);
        $this->assertFalse($custom['targetAvailable']);
        $this->assertNull($custom['pendingManualKpis']);
    }

    public function test_invalid_custom_range_is_rejected(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->dashboard($hr, ['preset' => 'custom', 'from_date' => '2026-09-17', 'to_date' => '2026-09-16']);
    }

    public function test_configured_kpi_score_is_calculated_by_existing_engine(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $ops = $this->createUserWithRole('Ops', UserType::OPERATIONS_EXECUTIVE);
        $template = (string) Str::uuid();
        $metric = (string) Str::uuid();
        DB::table('kpi_templates')->insert(['id' => $template, 'name' => 'Operations', 'department' => 'operations', 'active' => true]);
        DB::table('kpi_metrics')->insert(['id' => $metric, 'template_id' => $template, 'code' => 'operations_training', 'name' => 'Training', 'weightage' => 100, 'measurement_type' => 'manual_rating', 'active' => true]);
        DB::table('kpi_user_assignments')->insert(['id' => Str::uuid(), 'user_id' => $ops->id, 'template_id' => $template, 'effective_from' => '2026-09-01', 'active' => true]);
        DB::table('kpi_manual_values')->insert(['id' => Str::uuid(), 'user_id' => $ops->id, 'metric_id' => $metric, 'year' => 2026, 'month' => 9, 'rating' => 4, 'entered_by' => $hr->id]);
        $row = $this->dashboard($hr)['departmentRows']['operations'][0];
        $this->assertSame(4, $row['score']);
        $this->assertSame('Training', $row['weakest']);
        $this->assertSame('On track', $row['status']);
    }

    public function test_sales_user_cannot_read_hr_dashboard_data(): void
    {
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->dashboard($sales);
    }

    public function test_sales_active_work_uses_latest_status_and_bulk_lookup(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $active = $this->createLeadFor($sales);
        $cancelled = $this->createLeadFor($sales);
        foreach ([$active, $cancelled] as $lead) {
            $this->insertKpiEvent($sales, $lead, 'note_added', '2026-09-17 10:00:00', $lead);
            $this->followup($lead, 1, '2026-09-17 09:00:00');
        }
        $this->followup($cancelled, 2, '2026-09-17 11:00:00');
        $row = $this->dashboard($hr)['departmentRows']['sales'][0];
        $this->assertSame(1, $row['work']['active']);
        $this->assertSame(0, $row['work']['completed']);
    }

    public function test_unavailable_sales_history_does_not_display_fabricated_zero(): void
    {
        config(['kpi.activity_event_cutover_at' => '2026-09-16 20:00:00']);
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $row = $this->dashboard($hr, ['preset' => 'custom', 'from_date' => '2026-09-10', 'to_date' => '2026-09-10'])['departmentRows']['sales'][0];
        $this->assertNull($row['work']['active']);
        $this->assertNull($row['work']['completed']);
        $this->assertSame(0, $row['work']['outreach']);
    }

    public function test_old_due_followup_does_not_reintroduce_a_cancelled_or_rescheduled_lead(): void
    {
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $sales = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        foreach ([2, 1] as $latestStatus) {
            $lead = $this->createLeadFor($sales);
            $this->followup($lead, 1, '2026-09-16 10:00:00');
            DB::table('lead_followups')->where('lead_id', $lead)->update(['next_followup_date' => '2026-09-17 12:00:00']);
            $this->followup($lead, $latestStatus, '2026-09-17 10:00:00');
            DB::table('lead_followups')->where('lead_id', $lead)->where('created_at', '2026-09-17 10:00:00')->update(['next_followup_date' => '2026-09-18 12:00:00']);
        }
        $this->assertSame(0, $this->dashboard($hr)['todayFollowups']);
    }

    private function dashboard($user, array $query = []): array
    {
        $request = Request::create('/admin/hr', 'GET', $query);
        $request->setUserResolver(fn () => $user);
        return app()->call([app(HrDashboardController::class), 'index'], ['request' => $request])->getData();
    }

    private function attendance(string $user, string $date, string $status): void
    {
        DB::table('attendance_records')->insert(['id' => Str::uuid(), 'user_id' => $user, 'attendance_date' => $date, 'raw_status' => $status, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function case(string $user, string $type, string $date, string $status = 'pending'): string
    {
        $id = (string) Str::uuid();
        DB::table('operation_cases')->insert(['id' => $id, 'lead_id' => Str::uuid(), 'assigned_to' => $user, 'type' => $type, 'status' => $status, 'next_followup_at' => $date, 'opened_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    private function followup(string $lead, int $status, string $created): void
    {
        DB::table('lead_followups')->insert(['id' => Str::uuid(), 'lead_id' => $lead, 'status' => $status, 'created_at' => $created, 'updated_at' => $created]);
    }
}
