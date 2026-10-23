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

class LeadListControllerIntegrationTest extends LeadListFixtureTestCase
{

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

    public function test_main_lead_download_route_is_only_for_hr_and_super_admin(): void
    {
        $route = app('router')->getRoutes()->getByName('admin.leads.export');
        foreach (['super','hr','admin','operations','manager','own'] as $role) {
            $allowed = true;
            foreach ($route->gatherMiddleware() as $middleware) {
                if (!str_starts_with($middleware, 'role:')) continue;
                try {
                    (new \App\Http\Middleware\RoleMiddleware())->handle($this->request([], $role), fn () => response('allowed'), ...explode(',', substr($middleware, 5)));
                } catch (HttpExceptionInterface $error) {
                    $this->assertSame(403, $error->getStatusCode());
                    $allowed = false;
                }
            }
            $this->assertSame(in_array($role, ['super','hr'], true), $allowed);
        }
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
