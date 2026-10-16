<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use App\Models\KpiMetric;
use App\Models\UserType;
use App\Http\Controllers\AttendanceCalendarController;
use Illuminate\Http\Request;
use App\Services\Attendance\AttendanceCalendarService;
use App\Services\Kpi\SalesAttendanceKpiResolver;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Feature\Kpi\KpiFeatureTestCase;

class AttendanceCalendarTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-31 23:59:59'));
        Schema::create('attendance_week_off_schedules', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('user_id'); $t->date('effective_from');
            $t->json('weekdays'); $t->uuid('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('attendance_calendar_months', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->integer('year'); $t->integer('month');
            $t->string('mode'); $t->json('dates'); $t->uuid('updated_by')->nullable(); $t->timestamps();
        });
        Schema::create('attendance_week_off_swaps', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('user_id'); $t->date('worked_date');
            $t->date('off_date'); $t->uuid('approved_by'); $t->string('note')->nullable(); $t->timestamps();
        });
        Schema::create('attendance_calendar_audits', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('action'); $t->uuid('user_id')->nullable();
            $t->uuid('actor_id'); $t->json('details'); $t->timestamps();
        });
    }

    private function schedule($user, array $days, string $from = '2026-10-01'): void
    {
        DB::table('attendance_week_off_schedules')->insert([
            'id' => Str::uuid(), 'user_id' => $user->id, 'effective_from' => $from,
            'weekdays' => json_encode($days), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function record($user, string $date, string $status): void
    {
        AttendanceRecord::create(['user_id' => $user->id, 'paycode' => 'E1',
            'attendance_date' => $date, 'raw_status' => $status]);
    }

    public function test_employee_specific_weekoffs_and_holidays_overlap_only_once(): void
    {
        $user = $this->createUserWithRole('Tuesday Off', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [2]);
        DB::table('attendance_calendar_months')->insert(['id' => Str::uuid(), 'year' => 2026,
            'month' => 10, 'mode' => 'additional', 'dates' => json_encode(['2026-10-06', '2026-10-07'])]);
        $summary = app(AttendanceCalendarService::class)->summary($user, 2026, 10);
        $this->assertSame(26, $summary['working_days']);
        $this->assertSame(4, $summary['week_off_days']);
        $this->assertSame(1, $summary['overlap_days']);
        $this->assertSame(true, $summary['dates']->contains(fn ($date) => $date->toDateString() === '2026-10-04'));
    }

    public function test_weekoff_changes_are_effective_dated(): void
    {
        $user = $this->createUserWithRole('Schedule Changes', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]); $this->schedule($user, [2], '2026-10-15');
        $dates = app(AttendanceCalendarService::class)->summary($user, 2026, 10)['dates']->map->toDateString()->all();
        $this->assertSame(false, in_array('2026-10-11', $dates));
        $this->assertSame(true, in_array('2026-10-18', $dates));
        $this->assertSame(false, in_array('2026-10-20', $dates));
    }

    public function test_only_approved_swap_moves_a_working_day_and_can_cross_months(): void
    {
        $user = $this->createUserWithRole('Swapped', UserType::SALES_EXECUTIVE); $this->schedule($user, [0]);
        $this->record($user, '2026-10-04', 'P');
        $before = app(AttendanceCalendarService::class)->summary($user, 2026, 10);
        $this->assertSame(27, $before['working_days']);
        DB::table('attendance_week_off_swaps')->insert(['id' => Str::uuid(), 'user_id' => $user->id,
            'worked_date' => '2026-10-04', 'off_date' => '2026-11-02', 'approved_by' => $user->id]);
        $this->assertSame(28, app(AttendanceCalendarService::class)->summary($user, 2026, 10)['working_days']);
        $this->assertSame(24, app(AttendanceCalendarService::class)->summary($user, 2026, 11)['working_days']);
    }

    public function test_presence_counts_mis_and_does_not_reward_unapproved_extra_day(): void
    {
        $user = $this->createUserWithRole('Presence', UserType::SALES_EXECUTIVE); $this->schedule($user, [0]);
        $this->record($user, '2026-10-01', 'MIS'); $this->record($user, '2026-10-02', 'P');
        $this->record($user, '2026-10-03', 'A'); $this->record($user, '2026-10-04', 'P');
        $result = app(SalesAttendanceKpiResolver::class)->resolve($user, new KpiMetric(['target_value' => 100]), Carbon::parse('2026-10-04')->endOfDay(), 22);
        $this->assertSame(66.6667, $result['achievement_percent']);
        $this->assertSame(2, $result['evidence']['present_days']);
        $this->assertSame(1, $result['evidence']['miss_punch_days']);
        $this->assertSame(1, $result['evidence']['off_day_worked']);
    }

    public function test_complete_presence_is_100_and_unknown_or_missing_data_is_provisional(): void
    {
        $user = $this->createUserWithRole('Complete', UserType::SALES_EXECUTIVE); $this->schedule($user, []);
        $this->record($user, '2026-10-01', 'MIS'); $this->record($user, '2026-10-02', 'P');
        $resolver = app(SalesAttendanceKpiResolver::class);
        $metric = new KpiMetric(['target_value' => 100]);
        $this->assertSame(100.0, $resolver->resolve($user, $metric, Carbon::parse('2026-10-02')->endOfDay(), 22)['achievement_percent']);
        $this->record($user, '2026-10-04', 'POH');
        $result = $resolver->resolve($user, $metric, Carbon::parse('2026-10-04')->endOfDay(), 22);
        $this->assertSame(true, $result['evidence']['is_provisional']);
        $this->assertSame(0, $result['evidence']['absent_days']);
        $this->assertSame(1, $result['evidence']['missing_uploaded_days']);
        $this->assertSame(1, $result['evidence']['ignored_status_days']);
    }

    public function test_hr_can_save_weekoff_schedule_with_audit(): void
    {
        $hr = $this->createUserWithRole('Calendar HR', UserType::HR);
        $employee = $this->createUserWithRole('Employee', UserType::SALES_EXECUTIVE);
        $request = Request::create('/', 'POST', ['user_id' => $employee->id, 'effective_from' => '2026-10-01', 'weekdays' => [2]]);
        $request->setUserResolver(fn () => $hr);
        app(AttendanceCalendarController::class)->weekoffs($request);
        $this->assertSame(1, DB::table('attendance_week_off_schedules')->count());
        $this->assertSame(1, DB::table('attendance_calendar_audits')->count());
    }

    public function test_sales_cannot_change_calendar_even_by_direct_controller_call(): void
    {
        $user = $this->createUserWithRole('Unauthorized', UserType::SALES_EXECUTIVE);
        $request = Request::create('/', 'POST', ['user_id' => $user->id, 'effective_from' => '2026-10-01', 'weekdays' => [2]]);
        $request->setUserResolver(fn () => $user);
        try { app(AttendanceCalendarController::class)->weekoffs($request); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $this->assertSame(403, $error->getStatusCode());
            $this->assertSame(0, DB::table('attendance_week_off_schedules')->count());
            return;
        }
        $this->assertSame('denied', 'allowed');
    }

    public function test_swap_requires_actual_off_day_presence_and_different_working_day(): void
    {
        $hr = $this->createUserWithRole('HR Swap', UserType::HR);
        $user = $this->createUserWithRole('Swap Employee', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]);
        $request = Request::create('/', 'POST', ['user_id' => $user->id, 'worked_date' => '2026-10-04', 'off_date' => '2026-10-05']);
        $request->setUserResolver(fn () => $hr);
        try { app(AttendanceCalendarController::class)->swaps($request); }
        catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertSame(0, DB::table('attendance_week_off_swaps')->count());
            $this->record($user, '2026-10-04', 'MIS');
            app(AttendanceCalendarController::class)->swaps($request);
            $this->assertSame(1, DB::table('attendance_week_off_swaps')->count());
            return;
        }
        $this->assertSame('denied missing presence', 'allowed');
    }

    public function test_holiday_dates_outside_selected_month_are_rejected(): void
    {
        $hr = $this->createUserWithRole('HR Holidays', UserType::HR);
        $request = Request::create('/', 'POST', ['year' => 2026, 'month' => 10, 'mode' => 'additional', 'dates' => ['2026-11-01']]);
        $request->setUserResolver(fn () => $hr);
        try { app(AttendanceCalendarController::class)->holidays($request); }
        catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertSame(0, DB::table('attendance_calendar_months')->count());
            return;
        }
        $this->assertSame('date rejected', 'accepted');
    }

    public function test_calendar_change_cannot_silently_invalidate_an_approved_swap(): void
    {
        $hr = $this->createUserWithRole('HR Guard', UserType::HR);
        $user = $this->createUserWithRole('Guard Employee', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]); $this->record($user, '2026-10-04', 'P');
        $request = Request::create('/', 'POST', ['user_id' => $user->id, 'worked_date' => '2026-10-04', 'off_date' => '2026-10-05']);
        $request->setUserResolver(fn () => $hr);
        app(AttendanceCalendarController::class)->swaps($request);
        $change = Request::create('/', 'POST', ['user_id' => $user->id, 'effective_from' => '2026-10-01', 'weekdays' => [2]]);
        $change->setUserResolver(fn () => $hr);
        try { app(AttendanceCalendarController::class)->weekoffs($change); }
        catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertSame('[0]', DB::table('attendance_week_off_schedules')->value('weekdays'));
            $this->assertSame(1, DB::table('attendance_calendar_audits')->count());
            return;
        }
        $this->assertSame('invalid change rejected', 'accepted');
    }

    public function test_inclusive_off_list_does_not_subtract_weekoffs_twice(): void
    {
        $user = $this->createUserWithRole('Inclusive Employee', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]);
        DB::table('attendance_calendar_months')->insert(['id' => Str::uuid(), 'year' => 2026, 'month' => 10,
            'mode' => 'inclusive', 'dates' => json_encode(['2026-10-04', '2026-10-11', '2026-10-18', '2026-10-25', '2026-10-26'])]);
        $this->assertSame(26, app(AttendanceCalendarService::class)->summary($user, 2026, 10)['working_days']);
    }

    public function test_additive_migration_and_rollback_preserve_existing_imports(): void
    {
        foreach (['attendance_calendar_audits', 'attendance_week_off_swaps', 'attendance_calendar_months', 'attendance_week_off_schedules'] as $table) Schema::drop($table);
        Schema::create('attendance_imports', function (Blueprint $table) { $table->uuid('id')->primary(); });
        $id = (string) Str::uuid(); DB::table('attendance_imports')->insert(['id' => $id]);
        require_once database_path('migrations/2026_10_15_120000_add_attendance_calendars_and_import_hash.php');
        $migration = new \AddAttendanceCalendarsAndImportHash();
        $migration->up();
        $this->assertSame(true, Schema::hasColumn('attendance_imports', 'file_hash'));
        $this->assertSame(null, DB::table('attendance_imports')->value('file_hash'));
        $migration->down();
        $this->assertSame(false, Schema::hasTable('attendance_week_off_swaps'));
        $this->assertSame($id, DB::table('attendance_imports')->value('id'));
    }

    public function test_real_kpi_dashboard_scores_complete_presence_five_out_of_five(): void
    {
        $user = $this->createUserWithRole('Five Star', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]);
        $calendar = app(AttendanceCalendarService::class)->summary($user, 2026, 10);
        foreach ($calendar['dates'] as $date) $this->record($user, $date->toDateString(), 'MIS');
        $template = \App\Models\KpiTemplate::create(['name' => 'Attendance Test', 'department' => 'sales',
            'working_days_per_month' => 22, 'active' => true]);
        KpiMetric::create(['template_id' => $template->id, 'code' => 'attendance', 'name' => 'Attendance',
            'measurement_type' => 'automatic', 'source_key' => 'sales_attendance', 'direction' => 'higher_better',
            'target_value' => 95, 'weightage' => 100, 'active' => true,
            'score_rules' => ['5' => 100, '4' => 95, '3' => 90, '2' => 85, '1' => 0]]);
        \App\Models\KpiUserAssignment::create(['user_id' => $user->id, 'template_id' => $template->id,
            'effective_from' => '2026-10-01', 'active' => true]);
        $result = app(\App\Services\Kpi\KpiDashboardService::class)->forUser($user, Carbon::parse('2026-10-31')->endOfDay());
        $this->assertSame(5, $result['metrics'][0]['score']);
        $this->assertSame(100.0, $result['metrics'][0]['achievement_percent']);
        $this->assertSame(27, $result['metrics'][0]['evidence']['monthly_working_days']);
        $this->assertSame(false, $result['metrics'][0]['evidence']['is_provisional']);
        $this->assertSame(true, str_contains($result['metrics'][0]['display_actual'], '27 / 27 present'));
    }

    public function test_future_records_do_not_enter_coverage_or_off_day_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->createUserWithRole('Future Records', UserType::SALES_EXECUTIVE);
        $this->record($user, '2026-10-30', 'P');
        $result = app(SalesAttendanceKpiResolver::class)->resolve($user, new KpiMetric(), Carbon::parse('2026-10-31'), 22);
        $this->assertSame(null, $result['evidence']['attendance_coverage_through']);
        $this->assertSame(0, $result['evidence']['off_day_worked']);
        $this->assertSame(0, $result['evidence']['present_days']);
    }

    public function test_future_month_keeps_planned_calendar_but_has_no_elapsed_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00'));
        $user = $this->createUserWithRole('Future Calendar', UserType::SALES_EXECUTIVE);
        $result = app(SalesAttendanceKpiResolver::class)->resolve($user, new KpiMetric(), Carbon::parse('2026-11-30'), 22);
        $this->assertSame(25, $result['evidence']['monthly_working_days']);
        $this->assertSame(0, $result['evidence']['working_days_elapsed']);
    }

    public function test_no_imports_do_not_issue_a_shift_query_per_day(): void
    {
        $user = $this->createUserWithRole('No Records', UserType::SALES_EXECUTIVE);
        DB::enableQueryLog(); DB::flushQueryLog();
        app(SalesAttendanceKpiResolver::class)->resolve($user, new KpiMetric(), Carbon::parse('2026-10-31'), 22);
        $queries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'attendance_user_shift_assignments'));
        DB::disableQueryLog();
        $this->assertSame(0, $queries->count());
    }

    public function test_legacy_nonworking_date_write_cannot_invalidate_approved_swap(): void
    {
        $hr = $this->createUserWithRole('Legacy HR', UserType::HR);
        $user = $this->createUserWithRole('Legacy Swap', UserType::SALES_EXECUTIVE);
        $this->schedule($user, [0]); $this->record($user, '2026-10-04', 'P');
        DB::table('attendance_week_off_swaps')->insert(['id' => Str::uuid(), 'user_id' => $user->id,
            'worked_date' => '2026-10-04', 'off_date' => '2026-10-05', 'approved_by' => $hr->id]);
        $request = Request::create('/', 'POST', ['user_id' => $user->id, 'work_date' => '2026-10-05', 'reason_type' => 'approved_off']);
        $request->setUserResolver(fn () => $hr);
        try { app(\App\Http\Controllers\KpiManagementController::class)->saveUserNonWorkingDay($request); }
        catch (\Illuminate\Validation\ValidationException $error) {
            $this->assertSame(0, DB::table('kpi_user_non_working_days')->count());
            return;
        }
        $this->assertSame('legacy conflicting change rejected', 'accepted');
    }

    public function test_real_import_preview_confirm_and_presence_kpi_work_together(): void
    {
        Schema::create('attendance_imports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            foreach (['from_date', 'to_date', 'period_from', 'period_to'] as $field) $t->date($field)->nullable();
            foreach (['original_filename', 'stored_path', 'file_type', 'file_hash', 'uploaded_by'] as $field) $t->string($field)->nullable();
            $t->string('status')->default('previewed');
            foreach (['total_employees', 'total_records', 'created_records', 'updated_records'] as $field) $t->integer($field)->default(0);
            $t->timestamp('confirmed_at')->nullable(); $t->timestamps();
        });
        Schema::create('attendance_user_mappings', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('paycode')->unique(); $t->uuid('user_id');
            $t->string('employee_name')->nullable(); $t->uuid('updated_by')->nullable(); $t->timestamps();
        });
        $directory = sys_get_temp_dir().'/crm-attendance-integration-'.Str::uuid();
        mkdir($directory);
        config(['filesystems.disks.local.root' => $directory, 'session.driver' => 'array']);
        app('filesystem')->forgetDisk('local');
        $hr = $this->createUserWithRole('Import HR', UserType::HR);
        $employee = $this->createUserWithRole('Amit Sharma', UserType::SALES_EXECUTIVE);
        auth()->setUser($hr);
        $controller = app(\App\Http\Controllers\AttendanceImportController::class);
        try {
            $template = $controller->downloadSample(Request::create('/sample', 'GET', ['format' => 'csv']));
            ob_start(); $template->sendContent(); $csv = ob_get_clean();
            $path = $directory.'/sample.csv'; file_put_contents($path, $csv);
            $request = Request::create('/admin/attendance/import/preview', 'POST', ['from_date' => '2026-09-01', 'to_date' => '2026-09-03']);
            $request->files->set('excel_file', new \Illuminate\Http\UploadedFile($path, 'attendance.csv', 'text/csv', null, true));
            $request->setUserResolver(fn () => $hr);
            $request->setLaravelSession(app('session.store')); app()->instance('request', $request);
            $response = $controller->preview($request, app(\App\Services\Attendance\AttendanceImportParser::class));
            $payload = $response->getData(true);
            $this->assertSame(3, $payload['total_records']);
            $this->assertSame($employee->id, $payload['employees'][0]['user_id']);
            $confirm = Request::create('/admin/attendance/import/confirm', 'POST', [
                'import_id' => $payload['import_id'], 'mappings' => [$payload['employees'][0]['mapping_key'] => $employee->id],
            ]);
            $confirm->setUserResolver(fn () => $hr);
            $confirm->setLaravelSession(app('session.store')); app()->instance('request', $confirm);
            $controller->confirm($confirm, app(\App\Services\Attendance\AttendanceImportParser::class), app(\App\Services\Attendance\AttendanceShiftResolver::class));
            $this->assertSame(3, AttendanceRecord::where('user_id', $employee->id)->count());
            $this->assertSame(0, DB::table('attendance_user_mappings')->count());
            $result = app(SalesAttendanceKpiResolver::class)->resolve($employee, new KpiMetric(), Carbon::parse('2026-09-03')->endOfDay(), 22);
            $this->assertSame(66.6667, $result['achievement_percent']);
            $this->assertSame(1, $result['evidence']['miss_punch_days']);
            $this->assertSame('completed', DB::table('attendance_imports')->value('status'));
        } finally {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($directory);
        }
    }
}
