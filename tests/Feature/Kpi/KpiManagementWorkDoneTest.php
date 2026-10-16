<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use App\Services\Kpi\KpiWorkDoneService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class KpiManagementWorkDoneTest extends KpiFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::table('kpi_outreach_assignments', function ($table) {
            $table->string('completion_type')->nullable();
        });
    }

    public function test_hr_has_all_kpi_management_routes_but_sales_is_denied(): void
    {
        $hr = $this->createUserWithRole('Management HR', UserType::HR);
        $sales = $this->createUserWithRole('Sales User', UserType::SALES_EXECUTIVE);
        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'admin/kpi-management')) continue;
            $roles = collect($route->gatherMiddleware())->first(fn ($name) => str_starts_with($name, 'role:'));
            $arguments = explode(',', substr($roles, 5));
            $request = \Illuminate\Http\Request::create('/'.$route->uri());
            $request->setUserResolver(fn () => $hr);
            $response = (new \App\Http\Middleware\RoleMiddleware())->handle($request, fn () => response('allowed'), ...$arguments);
            $this->assertSame(200, $response->getStatusCode());
            $request->setUserResolver(fn () => $sales);
            try { (new \App\Http\Middleware\RoleMiddleware())->handle($request, fn () => response('allowed'), ...$arguments); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
                $this->assertSame(403, $error->getStatusCode()); continue;
            }
            $this->assertSame('sales denied', 'allowed');
        }
    }

    public function test_breakdown_uses_real_person_events_with_existing_team_scope(): void
    {
        config(['kpi.activity_event_cutover_at' => null]);
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $first = $this->createUserWithRole('First', UserType::SALES_EXECUTIVE);
        $second = $this->createUserWithRole('Second', UserType::SALES_EXECUTIVE);
        $lead = $this->createLeadFor($first);
        $this->insertKpiEvent($first, $lead, 'note_added', now()->toDateTimeString(), 'note-one');
        DB::table('lead_followups')->insert(['id' => \Illuminate\Support\Str::uuid(), 'lead_id' => $lead, 'status' => 1, 'created_at' => now()]);
        $filters = ['from' => now()->startOfDay(), 'to' => now()->endOfDay(), 'user_id' => null];
        $service = app(KpiWorkDoneService::class);
        $data = $service->dashboard($hr, $filters, true);
        $this->assertSame(1, $data['summary']['active']);
        $rows = collect($data['breakdown'])->keyBy(fn ($row) => $row['user']->id);
        $this->assertSame(1, $rows[$first->id]['active']);
        $this->assertSame(0, $rows[$second->id]['total']);
        $own = $service->dashboard($first, $filters, true);
        $this->assertSame(1, count($own['breakdown']));
        $this->assertSame($first->id, $own['breakdown'][0]['user']->id);
        $this->assertSame(null, $rows[$first->id]['outreach_target']);
    }

    public function test_breakdown_respects_history_cutover_without_fabricated_lead_counts(): void
    {
        config(['kpi.activity_event_cutover_at' => '2026-09-18']);
        $user = $this->createUserWithRole('Sales', UserType::SALES_EXECUTIVE);
        $data = app(KpiWorkDoneService::class)->dashboard($user, ['from' => Carbon::parse('2026-09-01'), 'to' => Carbon::parse('2026-09-02'), 'user_id' => null], true);
        $this->assertSame(null, $data['breakdown'][0]['total']);
        $this->assertSame(0, $data['breakdown'][0]['outreach_completed']);
    }

    public function test_hr_management_and_work_done_render_with_real_controller_data(): void
    {
        $hr = $this->createUserWithRole('HR Designer', UserType::HR);
        $sales = $this->createUserWithRole('Sales Employee', UserType::SALES_EXECUTIVE);
        $this->createUserWithRole('Operations Employee', UserType::OPERATIONS_EXECUTIVE);
        $automation = app(\App\Services\Kpi\KpiTemplateAutomationService::class);
        $automation->syncDepartment('sales', $hr->id);
        $automation->syncDepartment('operations', $hr->id);
        auth()->setUser($hr);
        $compiled = sys_get_temp_dir().'/crm-kpi-tools-'.\Illuminate\Support\Str::uuid();
        mkdir($compiled);
        config(['view.compiled' => $compiled, 'session.driver' => 'array', 'kpi.activity_event_cutover_at' => null]);
        app()->forgetInstance('blade.compiler');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        $request = \Illuminate\Http\Request::create('/admin/kpi-management', 'GET');
        $request->setUserResolver(fn () => $hr); $request->setLaravelSession(app('session.store')); app()->instance('request', $request);
        try {
            $manage = app(\App\Http\Controllers\KpiManagementController::class)->index($automation)->render();
            $this->assertSame(true, str_contains($manage, 'km-metric'));
            $this->assertSame(true, str_contains($manage, 'Manual KPI Value'));
            $this->assertSame(true, str_contains($manage, 'Save template'));
            $this->assertSame(false, str_contains($manage, 'remain Super Admin actions'));
            $this->assertSame(false, str_contains($manage, '@selected'));
            $work = app(\App\Http\Controllers\KpiWorkDoneController::class)->index($request, app(KpiWorkDoneService::class))->render();
            $this->assertSame(true, str_contains($work, 'Per-executive breakdown'));
            $this->assertSame(true, str_contains($work, 'Sales Employee'));
            if (getenv('CRM_KPI_PREVIEW_DIR')) {
                $directory = getenv('CRM_KPI_PREVIEW_DIR'); if (!is_dir($directory)) mkdir($directory, 0777, true);
                foreach (['management' => $manage, 'work-done' => $work] as $name => $html) {
                    file_put_contents($directory.'/'.$name.'.html', $html);
                }
            }
        } finally { (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled); }
    }

    public function test_per_person_lead_buckets_keep_completion_precedence_and_nonadditive_team_counts(): void
    {
        config(['kpi.activity_event_cutover_at' => null]);
        $hr = $this->createUserWithRole('HR', UserType::HR);
        $first = $this->createUserWithRole('First', UserType::SALES_EXECUTIVE);
        $second = $this->createUserWithRole('Second', UserType::SALES_EXECUTIVE);
        $lead = $this->createLeadFor($first);
        foreach ([$first,$second] as $i => $user) $this->insertKpiEvent($user, $lead, 'note_added', now()->toDateTimeString(), 'shared-'.$i);
        $this->insertKpiEvent($first, $lead, 'status_changed', now()->toDateTimeString(), 'completed');
        DB::table('kpi_activity_events')->where('event_key', 'test:completed')->update(['new_value' => '3']);
        DB::table('lead_followups')->insert(['id' => \Illuminate\Support\Str::uuid(), 'lead_id' => $lead, 'status' => 1, 'created_at' => now()]);
        $data = app(KpiWorkDoneService::class)->dashboard($hr, ['from'=>now()->startOfDay(),'to'=>now()->endOfDay(),'user_id'=>null], true);
        $this->assertSame(1, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['completed']);
        $this->assertSame(2, collect($data['breakdown'])->sum('total'));
    }

    public function test_hr_can_save_template_metric_and_manual_value_and_target_reflects_configuration(): void
    {
        $hr = $this->createUserWithRole('HR Editor', UserType::HR);
        $sales = $this->createUserWithRole('Configured', UserType::SALES_EXECUTIVE);
        $automation = app(\App\Services\Kpi\KpiTemplateAutomationService::class);
        $automation->syncDepartment('sales', $hr->id);
        $controller = app(\App\Http\Controllers\KpiManagementController::class);
        $template = \App\Models\KpiTemplate::first();
        $request = \Illuminate\Http\Request::create('/', 'POST', ['name'=>'Updated by HR','department'=>'sales',
            'working_days_per_month'=>22,'active'=>1]);
        $request->setUserResolver(fn () => $hr);
        $controller->updateTemplate($request, $template);
        $this->assertSame('Updated by HR', $template->fresh()->name);
        $metric = $template->metrics()->where('source_key','sales_outreach')->first();
        $data = $metric->toArray(); $data['target_value']=75;
        foreach ([5,4,3,2,1] as $score) $data['score_'.$score]=$metric->score_rules[$score];
        $request = \Illuminate\Http\Request::create('/', 'POST', $data); $request->setUserResolver(fn () => $hr);
        $controller->updateMetric($request, $metric);
        $this->assertSame(75.0, (float) $metric->fresh()->target_value);
        $request = \Illuminate\Http\Request::create('/', 'POST', ['user_id'=>$sales->id,'metric_id'=>$metric->id,
            'year'=>2026,'month'=>9,'value'=>80,'note'=>'HR test']); $request->setUserResolver(fn () => $hr);
        $controller->saveManualValue($request);
        $this->assertSame($hr->id, DB::table('kpi_manual_values')->value('entered_by'));
        $data = app(KpiWorkDoneService::class)->dashboard($sales, ['from'=>now()->startOfDay(),'to'=>now()->endOfDay(),'user_id'=>null], true);
        $this->assertSame(75, $data['breakdown'][0]['outreach_target']);
    }

    public function test_breakdown_query_count_does_not_grow_per_employee(): void
    {
        config(['kpi.activity_event_cutover_at'=>null]);
        $hr = $this->createUserWithRole('HR Performance', UserType::HR);
        $this->createUserWithRole('First', UserType::SALES_EXECUTIVE);
        $filters = ['from'=>now()->startOfDay(),'to'=>now()->endOfDay(),'user_id'=>null];
        $service=app(KpiWorkDoneService::class);
        $hr->loadMissing('userType');
        DB::enableQueryLog(); DB::flushQueryLog(); $service->dashboard($hr,$filters,true); $first=count(DB::getQueryLog());
        for($i=0;$i<10;$i++)$this->createUserWithRole('Employee '.$i,UserType::SALES_EXECUTIVE);
        DB::flushQueryLog();$service->dashboard($hr,$filters,true);$second=count(DB::getQueryLog());DB::disableQueryLog();
        $this->assertSame($first,$second);
    }
}
