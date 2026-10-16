<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\Hr\OperationsManualKpiController;
use App\Models\HrOperationsManualKpiSubmission as Submission;
use App\Models\KpiManualValue;
use App\Models\KpiMetric;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsManualAccessTest extends KpiFeatureTestCase
{
    private $hr;
    private $ops;
    private $other;
    private $metric;

    protected function setUp(): void
    {
        parent::setUp();
        require_once base_path('database/migrations/2026_09_30_183000_create_hr_operations_manual_kpi_submissions_table.php');
        (new \CreateHrOperationsManualKpiSubmissionsTable())->up();
        $this->hr = $this->createUserWithRole('Review HR', UserType::HR);
        $this->ops = $this->createUserWithRole('Self Operations', UserType::OPERATIONS_EXECUTIVE);
        $this->other = $this->createUserWithRole('Other Operations', UserType::OPERATIONS_EXECUTIVE);
        app(\App\Services\Kpi\KpiTemplateAutomationService::class)->syncDepartment('operations', $this->hr->id);
        $this->metric = KpiMetric::where('code', 'operations_alternate_options')->firstOrFail();
        config(['session.driver' => 'array']);
    }

    private function request($actor, array $data = [], string $method = 'POST'): Request
    {
        $request = Request::create('/admin/hr/operations-manual-kpi', $method, $data);
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);
        $request->setUserResolver(fn () => $actor);
        return $request;
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['user_id' => $this->ops->id, 'metric_id' => $this->metric->id,
            'year' => 2026, 'month' => 9, 'value' => 90, 'action' => 'submit'];
    }

    private function denied(callable $call, int $status = 403): void
    {
        try { $call(); } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) {
            $this->assertSame($status, $error->getStatusCode()); return;
        }
        $this->assertSame('denied', 'allowed');
    }

    public function test_self_submit_and_approval_only_apply_after_hr_review(): void
    {
        $controller = app(OperationsManualKpiController::class);
        $controller->store($this->request($this->ops, $this->payload()));
        $submission = Submission::firstOrFail();
        $this->assertSame($this->ops->id, $submission->submitted_by);
        $this->assertSame(0, KpiManualValue::count());
        $this->denied(fn () => $controller->approve($this->request($this->ops), $submission));
        $controller->approve($this->request($this->hr), $submission);
        $this->assertSame('approved', $submission->fresh()->status);
        $this->assertSame(90.0, (float) KpiManualValue::first()->value);
        $this->denied(fn () => $controller->approve($this->request($this->hr), $submission->fresh()), 422);
    }

    public function test_self_scope_and_assigned_metrics_are_enforced(): void
    {
        $controller = app(OperationsManualKpiController::class);
        $this->denied(fn () => $controller->store($this->request($this->ops, $this->payload(['user_id'=>$this->other->id]))));
        DB::table('kpi_user_assignments')->where('user_id', $this->ops->id)->update(['active'=>false]);
        $this->denied(fn () => $controller->store($this->request($this->ops, $this->payload())));
        $controller->store($this->request($this->hr, $this->payload(['action'=>'draft'])));
        $controller->store($this->request($this->hr, $this->payload(['user_id'=>$this->other->id])));
        $data = $controller->index($this->request($this->ops, [], 'GET'))->getData();
        $this->assertSame(0, $data['pendingSubmissions']->count());
        $this->assertSame(1, $data['historySubmissions']->count());
        $this->assertSame(0, $data['metrics']->count());
        $this->assertSame(1, $data['users']->count());
        $this->assertSame(0, KpiManualValue::count());
    }

    public function test_rejection_requires_reason_and_never_applies_manual_value(): void
    {
        $controller = app(OperationsManualKpiController::class);
        $controller->store($this->request($this->ops, $this->payload()));
        $submission = Submission::firstOrFail();
        $this->denied(fn () => $controller->reject($this->request($this->ops, ['review_note'=>'bad']), $submission));
        try { $controller->reject($this->request($this->hr), $submission); }
        catch (\Illuminate\Validation\ValidationException $error) { $this->assertSame(true, isset($error->errors()['review_note'])); }
        $this->assertSame('pending', $submission->fresh()->status);
        $controller->reject($this->request($this->hr, ['review_note'=>'Evidence missing']), $submission);
        $this->assertSame('rejected', $submission->fresh()->status);
        $this->assertSame('Evidence missing', $submission->fresh()->review_note);
        $this->assertSame(0, KpiManualValue::count());
    }

    public function test_routes_keep_operations_review_denied_and_allow_self_entry(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->getName() ?? '', 'admin.hr.operations-manual-kpi.')) continue;
            foreach ([$this->ops, $this->hr, $this->createUserWithRole('SA route', UserType::SUPER_ADMIN)] as $actor) {
                $allowed = true;
                foreach ($route->gatherMiddleware() as $middleware) {
                    if (!str_starts_with($middleware, 'role:')) continue;
                    try { (new \App\Http\Middleware\RoleMiddleware())->handle($this->request($actor), fn () => response('ok'), ...explode(',', substr($middleware,5))); }
                    catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { $allowed = false; }
                }
                $review = str_ends_with($route->getName(), '.approve') || str_ends_with($route->getName(), '.reject');
                $this->assertSame(!($review && $actor->id === $this->ops->id), $allowed);
            }
        }
    }

    public function test_manual_views_render_for_hr_operations_and_empty_queue(): void
    {
        $controller = app(OperationsManualKpiController::class);
        $controller->store($this->request($this->ops, $this->payload()));
        $compiled = sys_get_temp_dir().'/ops-views-'.\Illuminate\Support\Str::uuid(); mkdir($compiled);
        config(['view.compiled'=>$compiled]); app()->forgetInstance('blade.compiler');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        try {
            foreach (['hr'=>$this->hr, 'operations'=>$this->ops] as $name=>$actor) {
                auth()->setUser($actor);
                $html = $controller->index($this->request($actor, [], 'GET'))->render();
                $this->assertSame($name === 'hr', str_contains($html, 'data-reject-url'));
                $this->assertSame(false, str_contains($html, 'Other Operations') && $name === 'operations');
                $this->assertSame(true, str_contains($html, 'September'));
                if (getenv('CRM_OPS_PREVIEW_DIR')) file_put_contents(getenv('CRM_OPS_PREVIEW_DIR').'/'.$name.'.html', $html);
            }
            Submission::query()->delete();
            $html = $controller->index($this->request($this->hr, [], 'GET'))->render();
            $this->assertSame(true, str_contains($html, 'No submissions awaiting approval.'));
        } finally { (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled); }
    }

    public function test_grouping_preserves_events_and_separates_changed_notes_and_human_actions(): void
    {
        $events = collect();
        foreach ([['a',null,'same'],['b',null,'same'],['c',null,'different'],['d',$this->ops->id,'same']] as [$id,$user,$note]) {
            $event = new \App\Models\OperationCaseActivity(['id'=>$id,'user_id'=>$user,'action'=>'opened','to_status'=>'pending','note'=>$note]);
            $event->setRawAttributes($event->getAttributes()+['created_at'=>'2026-09-17 10:00:00'],true);
            $event->setRelation('operationCase', new \App\Models\OperationCase(['type'=>'customer_call'])); $events->push($event);
        }
        $groups = app(\App\Services\Operations\ActivityDisplayGroups::class)->group($events);
        $this->assertSame(3,count($groups)); $this->assertSame(2,count($groups[0]));
        $this->assertSame('b',$groups[0][1]->id);
    }

    public function test_overview_does_not_count_system_only_events_as_employee_work(): void
    {
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        $lead = $this->createLeadFor($this->ops);
        $case = \App\Models\OperationCase::create(['lead_id'=>$lead,'type'=>'customer_call','status'=>'pending','opened_at'=>now()]);
        \App\Models\OperationCaseActivity::create(['operation_case_id'=>$case->id,'lead_id'=>$lead,'action'=>'opened','to_status'=>'pending']);
        $data = app(\App\Http\Controllers\OperationsDashboardController::class)->index($this->request($this->hr,[],'GET'))->getData();
        $this->assertSame(0,$data['summary']['total']);
        $this->assertSame(false,array_key_exists('recentActivity',$data));
    }

    public function test_hr_can_open_overview_but_not_other_operations_routes(): void
    {
        $names=['index','work-details','queue','reschedules.index','reschedules.edit','reschedules.update','reschedules.cancel',
            'case.start','case.complete','case.review-complete','case.image-collection-complete','case.review-cancel',
            'case.image-collection-cancel','followups.create','followups.store','history','customer-calls.index','leads.assign','kpi','kpi.manual-value'];
        $admin=$this->createUserWithRole('Overview Admin',UserType::ADMIN);
        $super=$this->createUserWithRole('Overview Super',UserType::SUPER_ADMIN);
        $sales=$this->createUserWithRole('Overview Sales',UserType::SALES_EXECUTIVE);
        foreach($names as $name){
            $route=app('router')->getRoutes()->getByName('admin.operations.'.$name);
            $this->assertSame(false,$route===null);
            foreach([$this->hr,$this->ops,$admin,$super,$sales] as $actor){
                $allowed=true;
                foreach($route->gatherMiddleware() as $middleware){
                    if(!str_starts_with($middleware,'role:'))continue;
                    try{(new \App\Http\Middleware\RoleMiddleware())->handle($this->request($actor),fn()=>response('ok'),...explode(',',substr($middleware,5)));}
                    catch(\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error){$this->assertSame(403,$error->getStatusCode());$allowed=false;}
                }
                $expected=$actor->id!==$sales->id && ($actor->id!==$this->hr->id || in_array($name,['index','work-details'],true));
                $this->assertSame($expected,$allowed);
            }
        }
        $this->assertSame(1,collect(app('router')->getRoutes())->filter(fn($route)=>$route->getName()==='admin.operations.index')->count());
    }

    public function test_hr_sidebar_has_an_overview_only_link(): void
    {
        $source=file_get_contents(resource_path('views/admin/layouts/header.blade.php'));
        $position=strpos($source,'data-operations-overview-link');
        $start=strrpos(substr($source,0,$position),'@if ($isHr)');
        $fragment=substr($source,$start,strpos($source,'@endif',$position)-$start+strlen('@endif'));
        $compiled=app('blade.compiler')->compileString($fragment);
        foreach([true,false] as $isHr){
            ob_start();try{eval('?>'.$compiled);$html=ob_get_contents();}finally{ob_end_clean();}
            $this->assertSame($isHr,str_contains($html,'Operations Work Done'));
            $this->assertSame(false,str_contains($html,'admin/operations/queue'));
        }
    }
}
