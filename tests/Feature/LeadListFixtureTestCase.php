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

abstract class LeadListFixtureTestCase extends TestCase
{
    private string $originalConnection;
    private string $compiledViews;
    protected array $users = [];

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

    protected function request(array $filters, string $role='super'): Request
    {
        auth()->setUser($this->users[$role]);$request=Request::create('/admin/lead','GET',$filters);$request->setLaravelSession(app('session.store'));$request->setUserResolver(fn()=>$this->users[$role]);app()->instance('request',$request);return $request;
    }

    protected function data(array $filters=[],string $role='super'): array
    {
        return app(ClientController::class)->index($this->request($filters,$role))->getData();
    }

    protected function exportNames(array $filters=[],string $role='super'): array
    {
        $response=app(ClientController::class)->exportLeads($this->request(array_merge($filters,['format'=>'csv']),$role));
        $this->assertSame(true,$response instanceof StreamedResponse);ob_start();$response->sendContent();$csv=ob_get_clean();$lines=explode("\n",trim($csv));array_shift($lines);
        return array_map(fn($line)=>str_getcsv($line)[1],array_filter($lines));
    }

}
