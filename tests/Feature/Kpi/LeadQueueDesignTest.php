<?php

namespace Tests\Feature\Kpi;

use App\Http\Controllers\UpcomingFollowUpController;
use App\Models\Lead;
use App\Models\LeadFollowup;
use App\Models\UserType;
use App\Services\LeadQueuePresentationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadQueueDesignTest extends KpiFeatureTestCase
{
    private $sales;
    private $outside;
    private $manager;
    private $hr;
    private array $ids = [];
    private string $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver'=>'array','cache.default'=>'array']);
        Schema::table('lead_followups',fn ($t)=>$t->text('service_ids')->nullable());
        Schema::create('services',function($t){$t->uuid('id')->primary();$t->string('service');$t->text('product_ids')->nullable();$t->integer('status')->default(1);});
        Schema::create('products',function($t){$t->uuid('id')->primary();$t->string('product');$t->integer('status')->default(1);});
        Schema::create('lead_rides',function($t){$t->uuid('id')->primary();$t->uuid('lead_id');$t->dateTime('from_date')->nullable();$t->dateTime('to_date')->nullable();});
        Schema::create('lead_ai_scores',function($t){$t->uuid('id')->primary();$t->uuid('lead_id');$t->string('status');$t->integer('score')->nullable();$t->string('temperature')->nullable();$t->timestamps();});
        Schema::create('lead_ai_scoring_settings',function($t){$t->uuid('id')->primary();$t->integer('cold_max');$t->integer('neutral_max');$t->timestamps();});
        $this->sales=$this->createUserWithRole('Queue Sales',UserType::SALES_EXECUTIVE);
        $this->outside=$this->createUserWithRole('Outside Sales',UserType::SALES_EXECUTIVE);
        $this->manager=$this->createUserWithRole('Queue Manager',UserType::SALES_MANAGER);
        $this->hr=$this->createUserWithRole('Queue HR',UserType::HR);
        DB::table('sales_executive_assignments')->insert(['id'=>Str::uuid(),'manager_id'=>$this->manager->id,'sales_executive_id'=>$this->sales->id,'status'=>1]);
        DB::table('lead_ai_scoring_settings')->insert(['id'=>Str::uuid(),'cold_max'=>39,'neutral_max'=>69,'created_at'=>now(),'updated_at'=>now()]);
        $this->serviceId=(string)Str::uuid();
        DB::table('services')->insert(['id'=>$this->serviceId,'service'=>'Call Not Connected','status'=>1]);
        foreach ([30,31,91] as $i=>$age) {
            $lead=$this->lead($this->sales,'Queue Customer '.$i,$age);
            $this->ids[]=$lead;
            $this->followup($lead,$i===0?now()->setTime(13,0):($i===1?now()->subDay():null));
        }
        $outside=$this->lead($this->outside,'Outside Customer',200);$this->followup($outside,now());
        DB::table('lead_ai_scores')->insert(['id'=>Str::uuid(),'lead_id'=>$this->ids[0],'status'=>'completed','score'=>90,'temperature'=>'hot','created_at'=>now(),'updated_at'=>now()]);
    }

    private function lead($owner,string $name,int $age): string
    {
        $id=$this->createLeadFor($owner,$name,now()->subDays($age)->toDateTimeString());
        DB::table('leads')->where('id',$id)->update(['service_ids'=>json_encode([$this->serviceId])]);
        return $id;
    }

    private function followup(string $lead,$date,int $status=1): void
    {
        DB::table('lead_followups')->insert(['id'=>Str::uuid(),'lead_id'=>$lead,'status'=>$status,'next_followup_date'=>$date,'created_at'=>now(),'updated_at'=>now()]);
    }

    private function request($user,array $data=[]): Request
    {
        auth()->setUser($user);$request=Request::create('/admin/upcoming-follow-up','GET',$data);
        $request->setLaravelSession(app('session.store'));app()->instance('request',$request);$request->setUserResolver(fn ()=>$user);return $request;
    }

    public function test_followup_cards_use_full_scoped_data_and_exclude_latest_cancelled(): void
    {
        $controller=app(UpcomingFollowUpController::class);
        $data=$controller->index($this->request($this->sales))->getData();
        $this->assertSame(['total'=>3,'today'=>1,'hot'=>1,'no_date'=>1,'overdue'=>1],$data['queueSummary']);
        $this->assertSame(3,$controller->index($this->request($this->manager))->getData()['queueSummary']['total']);
        $this->assertSame(4,$controller->index($this->request($this->hr))->getData()['queueSummary']['total']);
        foreach ([UserType::ADMIN,UserType::SUPER_ADMIN] as $role) {
            $admin=$this->createUserWithRole($role,$role);
            $this->assertSame(4,$controller->index($this->request($admin))->getData()['queueSummary']['total']);
        }
        $denied=$controller->index($this->request($this->sales,['representative_user_id'=>$this->outside->id]))->getData();
        $this->assertSame(0,$denied['queueSummary']['total']);
        DB::table('lead_followups')->insert(['id'=>Str::uuid(),'lead_id'=>$this->ids[0],'status'=>LeadFollowup::STATUS_CANCELLED,'next_followup_date'=>null,'created_at'=>now()->addMinute(),'updated_at'=>now()->addMinute()]);
        $this->assertSame(2,$controller->index($this->request($this->sales))->getData()['queueSummary']['total']);
    }

    public function test_created_date_age_boundaries_percentages_and_staff_totals(): void
    {
        $leads=Lead::with('representative')->whereIn('id',$this->ids)->get();
        $summary=app(LeadQueuePresentationService::class)->dnp($leads);
        $this->assertSame(['0-30'=>1,'31-90'=>1,'90+'=>1],$summary['buckets']);
        $this->assertSame(['0-30'=>33.3,'31-90'=>33.3,'90+'=>33.3],$summary['percentages']);
        $this->assertSame(91,$summary['oldest']);
        $this->assertSame(1,$summary['scheduled']);
        $this->assertSame(3,$summary['staff'][0]['total']);
        $summary=app(LeadQueuePresentationService::class)->dnp(collect());
        $this->assertSame(0,$summary['total']);$this->assertSame(null,$summary['oldest']);
        $this->assertSame(0,$summary['percentages']['90+']);
    }

    public function test_queue_views_render_role_actions_and_real_records(): void
    {
        for($i=0;$i<12;$i++){$id=$this->lead($this->sales,'Extra Customer '.$i,10);$this->followup($id,now()->setTime(14,0));}
        $compiled=sys_get_temp_dir().'/queue-views-'.Str::uuid();mkdir($compiled);
        config(['view.compiled'=>$compiled]);app()->forgetInstance('blade.compiler');
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        try {
            foreach(['sales'=>$this->sales,'manager'=>$this->manager,'hr'=>$this->hr] as $role=>$actor){
                $request=$this->request($actor);$follow=app(UpcomingFollowUpController::class)->index($request)->render();
                $this->assertSame(true,str_contains($follow,'Overdue follow-ups'));
                $this->assertSame(false,str_contains($follow,'Distinct services today'));
                $visible=\App\Helpers\getRepresentativeIds($actor);
                $leads=Lead::with(['client','representative','leadFollowups'])->when($visible,fn($query)=>$query->whereIn('representative_user_id',$visible))->get();
                $method=new \ReflectionMethod(\App\Http\Controllers\ClientController::class,'dnpQueueView');$method->setAccessible(true);
                $dnp=$method->invoke(app(\App\Http\Controllers\ClientController::class),$leads,collect(),collect([$this->sales,$this->outside]))->render();
                $this->assertSame($role!=='sales',str_contains($dnp,'Pending DNP by staff'));
                $this->assertSame($role!=='hr',str_contains($dnp,'data-export-dnp'));
                // Lead editing now happens inside the unified follow-up workspace;
                // the legacy table action must stay removed for every role.
                $this->assertFalse(str_contains($dnp,'aria-label="Edit lead"'));
                $this->assertSame($role==='hr',str_contains($dnp,'Outside Customer'));
                if(getenv('CRM_QUEUE_PREVIEW_DIR')){file_put_contents(getenv('CRM_QUEUE_PREVIEW_DIR').'/follow-'.$role.'.html',$follow);file_put_contents(getenv('CRM_QUEUE_PREVIEW_DIR').'/dnp-'.$role.'.html',$dnp);}
            }
        }finally{(new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled);}
    }

    public function test_summary_and_services_query_count_is_batched(): void
    {
        $service=app(LeadQueuePresentationService::class);
        $measure=function() use($service){$leads=Lead::with(['representative','leadFollowups'])->where('representative_user_id',$this->sales->id)->get();DB::enableQueryLog();DB::flushQueryLog();$service->dnp($leads);$names=$service->serviceNames($leads);$count=count(DB::getQueryLog());DB::disableQueryLog();$this->assertSame('Call Not Connected',$names[$leads->first()->id][0]);return $count;};
        $first=$measure();for($i=0;$i<20;$i++)$this->lead($this->sales,'Batch '.$i,15);
        $this->assertSame($first,$measure());$this->assertSame(2,$first);
    }

    public function test_followup_table_shows_products_without_changing_status_or_payments(): void
    {
        DB::table('clients')->update(['contact_number' => null]);
        $product = (string) Str::uuid();
        DB::table('products')->insert(['id' => $product, 'product' => 'Archived Helicopter Product', 'status' => 0]);
        DB::table('leads')->whereIn('id', $this->ids)->update(['product_ids' => json_encode([$product])]);
        $before = DB::table('lead_followups')->orderBy('id')->get()->toJson();
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
        foreach ([$this->sales, $this->manager, $this->hr, $this->createUserWithRole('Product Super Admin', UserType::SUPER_ADMIN)] as $actor) {
            $html = app(UpcomingFollowUpController::class)->index($this->request($actor))->render();
            $this->assertStringContainsString('data-label="Product">Archived Helicopter Product', $html);
            $this->assertStringNotContainsString('data-label="Service"', $html);
            $this->assertStringContainsString('Service date', $html);
        }
        $this->assertSame($before, DB::table('lead_followups')->orderBy('id')->get()->toJson());
        $this->assertSame(0, DB::table('payment_audit_trail')->count());
    }

    public function test_age_exactly_ninety_is_not_over_ninety_and_missing_dates_stay_unknown(): void
    {
        $at90=Lead::findOrFail($this->ids[0]);$at90->created_at=now()->subDays(90);$at90->setRelation('representative',$this->sales);
        $future=Lead::findOrFail($this->ids[1]);$future->created_at=now()->addDay();$future->setRelation('representative',$this->sales);
        $missing=Lead::findOrFail($this->ids[2]);$missing->created_at=null;$missing->setRelation('representative',$this->sales);
        $summary=app(LeadQueuePresentationService::class)->dnp(collect([$at90,$future,$missing]));
        $this->assertSame(['0-30'=>1,'31-90'=>1,'90+'=>0],$summary['buckets']);
        $this->assertSame(1,$summary['unknown']);$this->assertSame(0,$summary['ages'][$future->id]);
    }
}
