<?php

namespace Tests\Feature;

use App\Http\Controllers\ClientController;
use App\Models\LeadAiScoringSetting;
use App\Models\User;
use App\Models\UserType;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class LeadListControllerIntegrationTest extends TestCase
{
    private string $originalConnection;
    private string $compiledViews;
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiledViews=sys_get_temp_dir().'/crm-lead-render-'.\Illuminate\Support\Str::uuid();mkdir($this->compiledViews);
        config(['view.compiled'=>$this->compiledViews]);app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
        $this->originalConnection = DB::getDefaultConnection();
        config(['database.default'=>'lead_controller_testing','database.connections.lead_controller_testing'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'cache.default'=>'array','session.driver'=>'array', 'logging.channels.single.path'=>sys_get_temp_dir().'/crm-lead-integration-test.log']);
        DB::purge('lead_controller_testing'); DB::setDefaultConnection('lead_controller_testing');
        Carbon::setTestNow(Carbon::create(2026,10,15,12));
        Schema::create('user_types',function(Blueprint $t){$t->string('id')->primary();$t->string('user_type');$t->string('parent_id')->nullable();});
        Schema::create('users',function(Blueprint $t){$t->string('id')->primary();$t->string('name');$t->string('email')->nullable();$t->string('user_type_id');$t->integer('status')->default(1);$t->timestamps();});
        Schema::create('clients',function(Blueprint $t){$t->string('id')->primary();foreach(['name','company_name','gst_number','email','contact_number','alternate_number','country_id','city_id','date_of_birth','address'] as $field)$t->string($field)->nullable();$t->integer('status')->default(1);});
        foreach(['countries','cities'] as $table)Schema::create($table,function(Blueprint $t){$t->string('id')->primary();$t->string('name');});
        Schema::create('leads',function(Blueprint $t){$t->string('id')->primary();$t->string('client_id');$t->string('representative_user_id')->nullable();$t->text('service_ids')->nullable();$t->text('product_ids')->nullable();$t->timestamps();});
        Schema::create('lead_rides',function(Blueprint $t){$t->string('id')->primary();$t->string('lead_id');foreach(['from_date','to_date','from_place','to_place'] as $field)$t->string($field)->nullable();});
        Schema::create('lead_followups',function(Blueprint $t){$t->string('id')->primary();$t->string('lead_id');$t->integer('status');$t->dateTime('next_followup_date')->nullable();$t->string('followed_by')->nullable();$t->text('followup_note')->nullable();$t->string('file')->nullable();$t->timestamps();});
        Schema::create('lead_ai_scores',function(Blueprint $t){$t->string('id')->primary();$t->string('lead_id');$t->string('status');$t->integer('score')->nullable();$t->string('temperature')->nullable();$t->timestamps();});
        Schema::create('lead_ai_scoring_settings',function(Blueprint $t){$t->string('id')->primary();$t->integer('cold_max');$t->integer('neutral_max');$t->timestamps();});
        Schema::create('lead_allocation_queue',function(Blueprint $t){$t->string('id')->primary();$t->string('lead_id');$t->string('status');});
        Schema::create('payment_audit_trail',function(Blueprint $t){$t->string('id')->primary();$t->string('lead_followup_id');$t->integer('payment_status');$t->decimal('paid_amount',15,2);});
        Schema::create('sales_executive_assignments',function(Blueprint $t){$t->string('id')->primary();$t->string('manager_id');$t->string('sales_executive_id');$t->integer('status');});
        Schema::create('services',function(Blueprint $t){$t->string('id')->primary();$t->string('service');$t->integer('status')->default(1);});
        Schema::create('products',function(Blueprint $t){$t->string('id')->primary();$t->string('product');$t->integer('status')->default(1);});
        foreach(['super'=>UserType::SUPER_ADMIN,'admin'=>UserType::ADMIN,'hr'=>UserType::HR,'operations'=>UserType::OPERATIONS_EXECUTIVE,'manager'=>UserType::SALES_MANAGER,'own'=>UserType::SALES_EXECUTIVE,'team'=>UserType::SALES_EXECUTIVE,'outside'=>UserType::SALES_EXECUTIVE] as $key=>$role){
            DB::table('user_types')->insert(['id'=>$key,'user_type'=>$role]);$user=new User(['name'=>ucfirst($key),'user_type_id'=>$key,'status'=>1]);$user->id=$key;$user->save();$this->users[$key]=$user;
        }
        foreach(['own','team'] as $member)DB::table('sales_executive_assignments')->insert(['id'=>$member,'manager_id'=>'manager','sales_executive_id'=>$member,'status'=>1]);
        foreach(['a'=>'own','b'=>'team','c'=>'outside','d'=>null] as $id=>$owner){DB::table('clients')->insert(['id'=>$id,'name'=>'Customer '.$id,'contact_number'=>'919876543210']);DB::table('leads')->insert(['id'=>$id,'client_id'=>$id,'representative_user_id'=>$owner,'service_ids'=>'[]','product_ids'=>'[]','created_at'=>$id==='c'?'2026-10-02':'2026-10-01','updated_at'=>'2026-10-02']);}
        foreach([['a-old','a','2026-10-14 09:00:00','2026-10-01'],['a-new','a','2026-10-16 09:00:00','2026-10-12'],['b-old','b','2026-10-15 09:00:00','2026-10-01'],['b-new','b',null,'2026-10-12'],['c','c','2026-10-15 23:59:59','2026-10-01']] as [$id,$lead,$date,$created])DB::table('lead_followups')->insert(['id'=>$id,'lead_id'=>$lead,'status'=>1,'next_followup_date'=>$date,'created_at'=>$created,'followup_note'=>'Note '.$id]);
        foreach([['a-old','a',99,'completed','2026-10-01'],['a-new','a',50,'completed','2026-10-12'],['b','b',81,'completed','2026-10-12'],['c','c',99,'pending','2026-10-12']] as [$id,$lead,$score,$status,$date])DB::table('lead_ai_scores')->insert(['id'=>$id,'lead_id'=>$lead,'score'=>$score,'status'=>$status,'temperature'=>'hot','created_at'=>$date]);
        DB::table('lead_ai_scoring_settings')->insert(['id'=>'setting','cold_max'=>10,'neutral_max'=>80,'created_at'=>'2026-10-01']);
        foreach(['a','d'] as $lead)DB::table('lead_allocation_queue')->insert(['id'=>$lead,'lead_id'=>$lead,'status'=>'queued']);
        auth()->setUser($this->users['super']);
    }

    protected function tearDown(): void
    {
        (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($this->compiledViews);
        Carbon::setTestNow();DB::disconnect('lead_controller_testing');DB::setDefaultConnection($this->originalConnection);config(['database.default'=>$this->originalConnection]);parent::tearDown();
    }

    private function request(array $filters, string $role='super'): Request
    {
        auth()->setUser($this->users[$role]);$request=Request::create('/admin/lead','GET',$filters);$request->setLaravelSession(app('session.store'));$request->setUserResolver(fn()=>$this->users[$role]);app()->instance('request',$request);return $request;
    }

    private function data(array $filters=[],string $role='super'): array
    {
        return app(ClientController::class)->index($this->request($filters,$role))->getData();
    }

    private function exportNames(array $filters=[],string $role='super'): array
    {
        $response=app(ClientController::class)->exportLeads($this->request(array_merge($filters,['format'=>'csv']),$role));
        $this->assertSame(true,$response instanceof StreamedResponse);ob_start();$response->sendContent();$csv=ob_get_clean();$lines=explode("\n",trim($csv));array_shift($lines);
        return array_map(fn($line)=>str_getcsv($line)[1],array_filter($lines));
    }

    public function test_real_controller_hot_filter_and_counts_respect_scope_and_other_filters(): void
    {
        $data=$this->data(['lead_queue'=>'hot']);$this->assertSame(['b'],$data['leads']->pluck('id')->all());
        $this->assertSame(['all'=>4,'hot'=>1,'queued'=>1,'unassigned'=>1,'no_followup'=>2],$data['leadQueueCounts']);
        $this->assertSame(80,$data['leadAiScoringSetting']->neutral_max);
        $data=$this->data(['lead_queue'=>'hot','representative_user_id'=>'own']);$this->assertSame(0,$data['leads']->total());$this->assertSame(1,$data['leadQueueCounts']['all']);
    }

    public function test_list_and_csv_apply_queue_date_and_created_filters(): void
    {
        foreach([['lead_queue'=>'hot'],['lead_queue'=>'queued'],['lead_queue'=>'no_followup'],['from_followup_date'=>'2026-10-15','to_followup_date'=>'2026-10-15'],['followup_period'=>'today'],['from_created_date'=>'2026-10-02']] as $filters){
            $expected=$this->data($filters)['leads']->map(fn($lead)=>$lead->client->name)->sort()->values()->all();$actual=collect($this->exportNames($filters))->sort()->values()->all();$this->assertSame($expected,$actual);
        }
        $this->assertSame(['Customer b'],$this->exportNames(['lead_queue'=>'hot']));
        $this->assertSame(['Customer d'],$this->exportNames(['lead_queue'=>'queued']));
        $this->assertSame(['Customer c'],$this->exportNames(['from_followup_date'=>'2026-10-15','to_followup_date'=>'2026-10-15']));
    }

    public function test_exports_and_counts_do_not_expand_sales_permissions(): void
    {
        $this->assertSame(['a'],$this->data([],'own')['leads']->pluck('id')->all());
        $this->assertSame([],$this->exportNames(['lead_queue'=>'hot'],'own'));
        $data=$this->data([],'manager');$this->assertSame(2,$data['leadQueueCounts']['all']);$this->assertSame(1,$data['leadQueueCounts']['hot']);
        $this->assertSame(['Customer b'],$this->exportNames(['lead_queue'=>'hot'],'manager'));
    }

    public function test_queue_is_forbidden_on_the_actual_export_endpoint(): void
    {
        $this->expectException(HttpExceptionInterface::class);$this->exportNames(['lead_queue'=>'queued'],'own');
    }

    public function test_invalid_followup_dates_propagate_validation_instead_of_exporting_unfiltered_data(): void
    {
        $this->expectException(ValidationException::class);$this->exportNames(['from_followup_date'=>'2026-10-16','to_followup_date'=>'2026-10-15']);
    }

    public function test_reading_without_settings_never_creates_ai_settings_or_jobs(): void
    {
        DB::table('lead_ai_scoring_settings')->delete();$data=$this->data(['lead_queue'=>'hot']);
        $this->assertSame(0,LeadAiScoringSetting::count());$this->assertSame(69,$data['leadAiScoringSetting']->neutral_max);
    }

    public function test_allocation_and_search_are_preserved_in_exports(): void
    {
        $this->assertSame(['Customer d'],$this->exportNames(['allocation_status'=>'queued']));
        $this->assertSame(['Customer c'],$this->exportNames(['search'=>'Customer c']));
    }

    public function test_controller_data_renders_the_real_table_with_completed_ai_scores(): void
    {
        $data = $this->data(['lead_queue' => 'hot']);
        // The parent list view supplies this presentation flag to its table partial.
        $data['canWriteLeads'] = true;
        $html = view('admin.pages.leads.partials.list-table', $data)->render();
        $this->assertSame(true, str_contains($html, 'Customer b'));
        $this->assertSame(true, str_contains($html, 'Hot'));
        $this->assertSame(false, str_contains($html, 'Customer a'));
    }

    public function test_phone_cells_format_only_the_display_and_do_not_change_saved_data(): void
    {
        DB::table('clients')->where('id','a')->update(['contact_number'=>'9558235555']);
        DB::table('clients')->where('id','b')->update(['contact_number'=>'+44 20 7946 0958']);
        DB::table('clients')->where('id','c')->update(['contact_number'=>null]);
        DB::table('clients')->where('id','d')->update(['contact_number'=>'N/A']);
        $data=$this->data([],'hr');$data['canWriteLeads']=false;
        $html=view('admin.pages.leads.partials.list-table',$data)->render();
        $this->assertSame(true,str_contains($html,'href="tel:+919558235555"'));
        $this->assertSame(true,str_contains($html,'+91 9558235555'));
        $this->assertSame(true,str_contains($html,'+44 20 7946 0958'));
        $this->assertSame(2,substr_count($html,'data-lead-phone='));
        $this->assertSame('9558235555',DB::table('clients')->where('id','a')->value('contact_number'));
        if(getenv('CRM_PHONE_PREVIEW_PATH'))file_put_contents(getenv('CRM_PHONE_PREVIEW_PATH'),$html);
    }

    public function test_xlsx_uses_the_same_hot_filter_as_the_list_and_csv(): void
    {
        $response = app(ClientController::class)->exportLeads($this->request(['lead_queue' => 'hot']));
        $this->assertSame(true, $response instanceof StreamedResponse);
        ob_start();
        try {
            $response->sendContent();
            $bytes = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $path = tempnam(sys_get_temp_dir(), 'crm-leads-xlsx-');
        try {
            file_put_contents($path, $bytes);
            $zip = new \ZipArchive();
            $this->assertSame(true, $zip->open($path));
            $strings = $zip->getFromName('xl/sharedStrings.xml');
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertSame(true, str_contains($strings, 'Customer b'));
            $this->assertSame(false, str_contains($strings, 'Customer a'));
            $this->assertSame(2, substr_count($sheet, '<row '));
        } finally {
            @unlink($path);
        }
    }

    private function createTransferTable(): void
    {
        Schema::create('lead_transfers', function (Blueprint $table) {
            $table->string('id')->primary();
            foreach (['lead_id','from_user_id','to_user_id','requested_by','status'] as $field) $table->string($field);
            $table->text('reason')->nullable(); $table->timestamps();
        });
    }

    public function test_request_popup_returns_only_limited_search_results_and_existing_actions(): void
    {
        $this->createTransferTable();
        $controller = app(\App\Http\Controllers\LeadTransferController::class);
        $response = $controller->search($this->request(['search'=>'customer'], 'own'));
        $data = $response->getData(true);
        $this->assertSame(4, count($data['leads']));
        $this->assertSame(true, $response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame(true, $response->headers->hasCacheControlDirective('private'));
        $rows = collect($data['leads'])->keyBy('id');
        $this->assertSame('offer', $rows['a']['action']);
        $this->assertSame('request', $rows['c']['action']);
        $this->assertSame('claim', $rows['d']['action']);
        $this->assertSame(route('admin.leads.transfer.claim','d'), $rows['d']['action_url']);
        $this->assertSame('assign', $controller->search($this->request(['search'=>'Customer d']))->getData(true)['leads'][0]['action']);
        $this->assertSame(['id','name','phone','products','service_date','owner','own','action','action_url'], array_keys($rows['c']));
        $this->assertSame(0, count($controller->search($this->request(['search'=>'Does not exist'],'own'))->getData(true)['leads']));
        $service = app(\App\Services\LeadTransferService::class);
        $service->requestForSelf(\App\Models\Lead::findOrFail('c'),$this->users['own'],'Popup request');
        $this->assertSame('outside', \App\Models\Lead::find('c')->representative_user_id);
        $this->assertSame(1, $service->pendingActionCountFor($this->users['outside']));
        $rows = collect($controller->search($this->request(['search'=>'Customer c'],'own'))->getData(true)['leads']);
        $this->assertSame('pending', $rows->first()['action']);
        $service->requestFromOwner(\App\Models\Lead::findOrFail('a'),$this->users['team'],$this->users['own'],'Offer');
        $this->assertSame(1, $service->pendingActionCountFor($this->users['team']));
        $this->assertSame(2, $service->pendingActionCountFor($this->users['super']));
        $this->assertSame('assign', $controller->search($this->request(['search'=>'Customer b']))->getData(true)['leads'][0]['action']);
        DB::table('clients')->where('id','a')->update(['contact_number'=>'+91 (888) 777-6655']);
        $this->assertSame('a',$controller->search($this->request(['search'=>'888777'],'own'))->getData(true)['leads'][0]['id']);
    }

    public function test_request_popup_rejects_blank_wildcard_short_search_and_other_roles(): void
    {
        $controller = app(\App\Http\Controllers\LeadTransferController::class);
        foreach (['','ab','   ','%%%','cus_','cus\\','12345'] as $term) {
            try { $controller->search($this->request(['search'=>$term],'own')); }
            catch (ValidationException $error) { continue; }
            $this->assertSame('search rejected', 'search allowed');
        }
        foreach (['hr','admin','operations'] as $role) {
            try { $controller->search($this->request(['search'=>'Customer'],$role)); }
            catch (HttpExceptionInterface $error) { $this->assertSame(403,$error->getStatusCode()); continue; }
            $this->assertSame('role denied','role allowed');
        }
    }

    public function test_request_popup_caps_results_and_keeps_query_count_constant(): void
    {
        $this->createTransferTable();
        $controller = app(\App\Http\Controllers\LeadTransferController::class);
        $request = $this->request(['search'=>'customer'],'own'); $request->user()->load('userType');
        DB::enableQueryLog(); DB::flushQueryLog(); $controller->search($request); $small = count(DB::getQueryLog());
        for ($i=0;$i<25;$i++) {
            $id='extra-'.$i; DB::table('clients')->insert(['id'=>$id,'name'=>'Customer '.$i,'contact_number'=>'9876543210']);
            DB::table('leads')->insert(['id'=>$id,'client_id'=>$id,'representative_user_id'=>'outside','product_ids'=>'[]','created_at'=>now()]);
        }
        DB::flushQueryLog(); $data = $controller->search($request)->getData(true); $large = count(DB::getQueryLog()); DB::disableQueryLog();
        $this->assertSame(20,count($data['leads'])); $this->assertSame($small,$large);
        $this->assertSame(20,count($controller->search($this->request(['search'=>'987654'],'own'))->getData(true)['leads']));
    }

    public function test_hr_can_export_main_leads_but_dnp_and_mutations_remain_view_only(): void
    {
        $this->assertSame(['Customer b'],$this->exportNames(['lead_queue'=>'hot'],'hr'));
        foreach (['admin.leads.export'=>true,'admin.leads.dnp'=>true,'admin.leads.dnp.export'=>false,'admin.leads.update'=>false,'admin.clients.follow-up.store'=>false,'admin.leads.request-search'=>false] as $name=>$allowed) {
            $route=app('router')->getRoutes()->getByName($name); $request=$this->request([],'hr'); $passed=true;
            foreach ($route->gatherMiddleware() as $middleware) {
                if (!str_starts_with($middleware,'role:')) continue;
                try { (new \App\Http\Middleware\RoleMiddleware())->handle($request,fn()=>response('allowed'),...explode(',',substr($middleware,5))); }
                catch (HttpExceptionInterface $error) { $this->assertSame(403,$error->getStatusCode()); $passed=false; break; }
            }
            $this->assertSame($allowed,$passed);
        }
        $response=app(ClientController::class)->exportLeads($this->request(['lead_queue'=>'hot'],'hr'));
        $this->assertSame(true,$response instanceof StreamedResponse);
        ob_start(); $response->sendContent(); $bytes=ob_get_clean(); $this->assertSame('PK',substr($bytes,0,2));
    }

    public function test_actual_hr_views_show_download_but_no_dnp_write_or_request_controls(): void
    {
        $compiled = sys_get_temp_dir().'/crm-lead-views-'.\Illuminate\Support\Str::uuid();
        mkdir($compiled);
        config(['view.compiled'=>$compiled]);
        app()->forgetInstance('blade.compiler');
        try {
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi/views'));
        view()->share('errors',new \Illuminate\Support\ViewErrorBag());
        $data=$this->data([],'hr');
        $html=view('admin.pages.leads.index-lead',$data)->render();
        $this->assertSame(true,str_contains($html,'id="leads-download"'));
        $this->assertSame(false,str_contains($html,'id="leads-request"'));
        DB::table('clients')->where('id','a')->update(['id'=>'client-a']);
        DB::table('leads')->where('id','a')->update(['client_id'=>'client-a']);
        $method=new \ReflectionMethod(ClientController::class,'dnpQueueView');
        $method->setAccessible(true);
        $html=$method->invoke(app(ClientController::class),\App\Models\Lead::with(['client','representative'])->get(),collect(),collect())->render();
        $this->assertSame(false,str_contains($html,'id="export-dnp"'));
        $this->assertSame(false,str_contains($html,'aria-label="Edit lead"'));
        $this->assertSame(true,str_contains($html,route('admin.clients.view','client-a')));
        } finally {
            (new \Illuminate\Filesystem\Filesystem())->deleteDirectory($compiled);
        }
    }
}
