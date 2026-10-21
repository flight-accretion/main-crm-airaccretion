<?php

namespace Tests\Feature\Kpi;

use App\Models\OperationCase;
use App\Models\OperationCaseActivity;
use App\Models\UserType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationsWorkSummaryTest extends KpiFeatureTestCase
{
    private $ops;
    private $other;
    private $hr;
    private string $compiled;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::create('lead_rides', function (\Illuminate\Database\Schema\Blueprint $table) { $table->uuid('id')->primary(); $table->uuid('lead_id'); $table->dateTime('from_date')->nullable(); });
        $this->compiled=sys_get_temp_dir().'/ops-detail-'.\Illuminate\Support\Str::uuid();mkdir($this->compiled);
        config(['view.compiled'=>$this->compiled]);app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
        (require base_path('database/migrations/2026_09_23_160000_create_operation_cases_table.php'))->up();
        (require base_path('database/migrations/2026_09_23_160100_create_operation_case_activities_table.php'))->up();
        $this->ops=$this->createUserWithRole('Working Operations',UserType::OPERATIONS_EXECUTIVE);
        $this->other=$this->createUserWithRole('Second Operations',UserType::OPERATIONS_EXECUTIVE);
        $this->hr=$this->createUserWithRole('Work HR',UserType::HR);
        config(['session.driver'=>'array']);
        $done=$this->case('review','completed');
        $this->event($done,$this->ops);$this->event($done,$this->ops);$this->event($done,$this->other);
        $active=$this->case('customer_call','in_progress');$this->event($active,$this->ops);
        $pending=$this->case('refund','pending');$this->event($pending,$this->other);
        $system=$this->case('customer_call','pending');$this->event($system,null);
        $old=$this->case('reschedule','completed');$this->event($old,$this->ops,now()->subDay());
    }

    private function case(string $type,string $status): OperationCase
    {
        return OperationCase::create(['lead_id'=>$this->createLeadFor($this->ops),'type'=>$type,'status'=>$status,'opened_at'=>now()->subDays(4),'completed_at'=>$status==='completed'?now():null]);
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->compiled);
        parent::tearDown();
    }

    private function event(OperationCase $case,$actor,$date=null): void
    {
        DB::table('operation_case_activities')->insert(['id'=>\Illuminate\Support\Str::uuid(),'operation_case_id'=>$case->id,'lead_id'=>$case->lead_id,'user_id'=>$actor?->id,'action'=>'followup_saved','to_status'=>$case->status,'created_at'=>$date?:now(),'updated_at'=>$date?:now()]);
    }

    private function request(array $filters=[]): Request
    {
        $path=array_intersect(['work_status','work_type','page'],array_keys($filters)) ? '/admin/operations/work-details' : '/admin/operations';
        $request=Request::create($path,'GET',$filters);$request->setLaravelSession(app('session.store'));app()->instance('request',$request);$request->setUserResolver(fn()=>$this->hr);auth()->setUser($this->hr);return $request;
    }

    public function test_summary_counts_unique_human_work_and_respects_period_and_employee(): void
    {
        $controller=app(\App\Http\Controllers\OperationsDashboardController::class);
        $data=$controller->index($this->request())->getData();
        $this->assertSame(['completed'=>1,'in_progress'=>1,'pending'=>1,'total'=>3],$data['summary']);
        $this->assertSame(false,array_key_exists('recentActivity',$data));
        $rows=collect($data['breakdown'])->keyBy('user_id');
        $this->assertSame(2,$rows[$this->ops->id]['total']);$this->assertSame(2,$rows[$this->other->id]['total']);
        $this->assertSame(1,$data['typeSummary']['review']['completed']);
        $own=$controller->index($this->request(['operations_user_id'=>$this->ops->id]))->getData();
        $this->assertSame(2,$own['summary']['total']);$this->assertSame(1,count($own['breakdown']));
        $yesterday=$controller->index($this->request(['date_filter'=>'yesterday']))->getData();
        $this->assertSame(1,$yesterday['summary']['total']);$this->assertSame(1,$yesterday['summary']['completed']);
        $all=$controller->index($this->request(['date_filter'=>'']))->getData();$this->assertSame(4,$all['summary']['total']);
        $filtered=$controller->index($this->request(['type'=>'refund','status'=>'pending']))->getData();$this->assertSame(1,$filtered['summary']['pending']);
        $none=$controller->index($this->request(['from_date'=>'2020-01-01','to_date'=>'2020-01-02']))->getData();$this->assertSame(0,$none['summary']['total']);
    }

    public function test_summary_render_has_no_activity_log_and_keeps_hr_read_only(): void
    {
        $compiled=sys_get_temp_dir().'/ops-work-'.\Illuminate\Support\Str::uuid();mkdir($compiled);config(['view.compiled'=>$compiled]);app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        try{
            $html=app(\App\Http\Controllers\OperationsDashboardController::class)->index($this->request())->render();
            $this->assertSame(true,str_contains($html,'Operations Work Done'));$this->assertSame(true,str_contains($html,'Per-employee breakdown'));
            $this->assertSame(false,str_contains($html,'Recent Activity'));$this->assertSame(false,str_contains($html,'operations-activity-table'));
            $this->assertSame(true,str_contains($html,'Working Operations'));$this->assertSame(false,str_contains($html,route('admin.operations.queue','review')));
            if(getenv('CRM_OPS_PREVIEW_DIR'))file_put_contents(getenv('CRM_OPS_PREVIEW_DIR').'/work-summary.html',$html);
        }finally{(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled);}
    }

    public function test_query_count_does_not_grow_per_case_or_employee(): void
    {
        $measure=function(){DB::enableQueryLog();DB::flushQueryLog();app(\App\Http\Controllers\OperationsDashboardController::class)->index($this->request());$count=count(DB::getQueryLog());DB::disableQueryLog();return $count;};
        $first=$measure();for($i=0;$i<15;$i++){$user=$this->createUserWithRole('Extra '.$i,UserType::OPERATIONS_EXECUTIVE);$this->event($this->case('review','pending'),$user);}
        $this->assertSame($first,$measure());
    }

    public function test_new_crm_activity_is_visible_on_next_summary_and_card_request(): void
    {
        $controller=app(\App\Http\Controllers\OperationsDashboardController::class);
        $before=$controller->index($this->request())->getData()['summary']['total'];
        $this->event($this->case('review','completed'),$this->ops);
        $after=$controller->index($this->request())->getData()['summary'];
        $this->assertSame($before+1,$after['total']);
        $details=$controller->workDetails($this->request(['work_status'=>'total']))->getData(true);
        $this->assertSame($after['total'],$details['total']);
    }

    public function test_card_details_match_unique_summary_and_selected_employee(): void
    {
        $controller=app(\App\Http\Controllers\OperationsDashboardController::class);
        $summary=$controller->index($this->request())->getData()['summary'];
        foreach(['completed','in_progress','pending','total'] as $status){
            $response=$controller->workDetails($this->request(['work_status'=>$status]));
            $payload=$response->getData(true);
            $this->assertSame(true,$response->headers->hasCacheControlDirective('no-store'));
            $this->assertSame($summary[$status],$payload['total']);
            $this->assertSame(true,str_contains($payload['html'],'data-operations-case'));
            $this->assertSame(false,str_contains($payload['html'],'/case/'));
            if(getenv('CRM_OPS_PREVIEW_DIR'))file_put_contents(getenv('CRM_OPS_PREVIEW_DIR').'/work-detail-'.$status.'.json',json_encode($payload));
        }
        $category=$controller->workDetails($this->request(['work_type'=>'review','work_status'=>'completed']))->getData(true);
        $this->assertSame(1,$category['total']);
        $person=$controller->workDetails($this->request(['operations_user_id'=>$this->ops->id,'work_status'=>'pending']))->getData(true);
        $this->assertSame(0,$person['total']);$this->assertSame(true,str_contains($person['html'],'No matching cases'));
        if(getenv('CRM_OPS_PREVIEW_DIR'))file_put_contents(getenv('CRM_OPS_PREVIEW_DIR').'/work-detail-empty.json',json_encode($person));
        $yesterday=$controller->workDetails($this->request(['date_filter'=>'yesterday','work_type'=>'reschedule']))->getData(true);
        $this->assertSame(1,$yesterday['total']);
    }

    public function test_details_are_paginated_and_keep_filters_without_duplicate_cases(): void
    {
        for($i=0;$i<28;$i++){$case=$this->case('review','pending');$this->event($case,$this->ops);$this->event($case,$this->ops);}
        $controller=app(\App\Http\Controllers\OperationsDashboardController::class);
        $first=$controller->workDetails($this->request(['work_type'=>'review','work_status'=>'pending','operations_user_id'=>$this->ops->id]))->getData(true);
        $this->assertSame(28,$first['total']);$this->assertSame(25,substr_count($first['html'],'data-operations-case='));
        $this->assertSame(true,str_contains($first['html'],'work_type=review'));
        $second=$controller->workDetails($this->request(['work_type'=>'review','work_status'=>'pending','operations_user_id'=>$this->ops->id,'page'=>2]))->getData(true);
        $this->assertSame(3,substr_count($second['html'],'data-operations-case='));
        $path=getenv('CRM_OPS_PREVIEW_DIR');if($path){file_put_contents($path.'/work-detail-pending.json',json_encode($first));file_put_contents($path.'/work-detail-page2.json',json_encode($second));}
    }

    public function test_detail_filters_validate_and_customer_markup_is_escaped(): void
    {
        $controller=app(\App\Http\Controllers\OperationsDashboardController::class);
        foreach([['work_status'=>'bad'],['work_type'=>'vendor_followup'],['page'=>0],['from_date'=>'2026-10-20','to_date'=>'2026-10-01']] as $input){
            $rejected=false;
            try{$controller->workDetails($this->request($input));}catch(\Illuminate\Validation\ValidationException $error){$rejected=true;}
            $this->assertSame(true,$rejected);
        }
        DB::table('clients')->update(['name'=>'<script>alert(1)</script>']);
        $html=$controller->workDetails($this->request(['work_status'=>'total']))->getData(true)['html'];
        $this->assertSame(false,str_contains($html,'<script>'));$this->assertSame(true,str_contains($html,'&lt;script&gt;'));
    }

    public function test_completed_by_duration_and_exports_share_the_case_selection(): void
    {
        \Illuminate\Support\Facades\Schema::create('services', function (\Illuminate\Database\Schema\Blueprint $table) { $table->uuid('id')->primary(); $table->string('service'); });
        OperationCase::where('type', 'review')->update(['completed_by' => $this->ops->id]);
        $controller = app(\App\Http\Controllers\OperationsDashboardController::class);
        $data = $controller->index($this->request(['completed_by' => $this->ops->id]))->getData();
        $this->assertSame(1, $data['summary']['total']);
        $this->assertSame(1, $data['rows']->total());
        $details = $controller->workDetails($this->request(['completed_by' => $this->ops->id, 'work_status' => 'completed']))->getData(true);
        foreach (['Completed by', 'Working Operations', 'Elapsed duration', '96h'] as $text) $this->assertSame(true, str_contains($details['html'], $text));
        DB::table('clients')->update(['name' => '=UNTRUSTED()']);
        $export = new \App\Exports\OperationsWorkExport($data['filters'], $data['caseTypes'], 'completed', null);
        $this->assertSame(1, $export->query()->count());
        $this->assertSame("'=UNTRUSTED()", $export->map($export->query()->first())[0]);
        foreach ([\Maatwebsite\Excel\Excel::CSV, \Maatwebsite\Excel\Excel::XLSX] as $format) {
            $bytes = \Maatwebsite\Excel\Facades\Excel::raw($export, $format);
            $this->assertSame(true, strlen($bytes) > 100);
        }
        $empty = $controller->workDetails($this->request(['completed_by' => $this->other->id]))->getData(true);
        $this->assertSame(0, $empty['total']);
    }
}
