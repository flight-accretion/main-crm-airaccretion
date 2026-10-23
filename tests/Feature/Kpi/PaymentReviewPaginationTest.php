<?php
namespace Tests\Feature\Kpi;

use App\Models\UserType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

class PaymentReviewPaginationTest extends KpiFeatureTestCase
{
    private $actor;
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default'=>'array','session.driver'=>'array']);
        $this->actor=$this->createUserWithRole('Payment reviewer',UserType::ACCOUNTS_EXECUTIVE);
        $this->actingAs($this->actor);
        Schema::table('lead_followups',function(Blueprint $t){$t->text('service_ids')->nullable();$t->text('extra_service_ids')->nullable();});
        Schema::create('lead_rides',function(Blueprint $t){$t->uuid('id')->primary();$t->uuid('lead_id');$t->dateTime('from_date')->nullable();$t->dateTime('to_date')->nullable();});
        Schema::create('services',function(Blueprint $t){$t->uuid('id')->primary();$t->string('service');});
        foreach(['countries','cities'] as $table) Schema::create($table,function(Blueprint $t){$t->uuid('id')->primary();$t->string('name');});
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
    }
    private function payment(bool $reviewed, int $age=0, ?string $lead=null): string
    {
        $lead=$lead ?? $this->createLeadFor($this->actor,'Payment customer');
        $followup=(string)Str::uuid();
        DB::table('lead_followups')->insert(['id'=>$followup,'lead_id'=>$lead,'status'=>3,'total_amount'=>1000,'received_amount'=>100,'created_at'=>now()->subDays($age),'updated_at'=>now()->subDays($age)]);
        if($reviewed) DB::table('payment_audit_trail')->insert(['id'=>(string)Str::uuid(),'lead_followup_id'=>$followup,'paid_amount'=>100,'payment_status'=>1,'created_at'=>now(),'updated_at'=>now()]);
        return $lead;
    }
    public function test_only_visible_leads_contribute_to_pagination_totals(): void
    {
        for($i=0;$i<35;$i++) $this->payment(true,1);
        $this->payment(false);$this->payment(false);
        $response=$this->get(route('admin.account.payment-review'))->assertOk()
            ->assertViewHas('payments',fn($rows)=>$rows->count()===2)
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===2 && $p->lastPage()===1);
        $response->assertDontSee('aria-label="Pagination Navigation"',false);
        if($dir=getenv('CRM_PAYMENT_PREVIEW_DIR')) file_put_contents($dir.'/index.html',$response->getContent());
    }
    public function test_reviewed_newest_records_do_not_create_empty_first_pages(): void
    {
        $old=$this->payment(false,50);
        for($i=0;$i<25;$i++) $this->payment(true);
        $this->get(route('admin.account.payment-review',['per_page'=>10]))->assertOk()
            ->assertViewHas('payments',fn($rows)=>$rows->count()===1 && $rows->first()->lead_id===$old)
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===1);
    }
    public function test_grouped_leads_and_explicit_all_status_keep_existing_rules(): void
    {
        $lead=$this->payment(false);$this->payment(false,1,$lead);
        $this->payment(true);
        $this->get(route('admin.account.payment-review'))->assertOk()
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===1)
            ->assertViewHas('payments',fn($rows)=>$rows->first()->received_amount===200.0);
        $this->get(route('admin.account.payment-review',['status'=>'']))->assertOk()
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===2)
            ->assertViewHas('payments',fn($rows)=>$rows->count()===2);
        $this->get(route('admin.account.payment-review',['payment_status'=>'pending']))->assertOk()
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===1);
    }
    public function test_reviewed_only_dataset_has_no_rows_or_pagination(): void
    {
        for($i=0;$i<25;$i++) $this->payment(true);
        $this->get(route('admin.account.payment-review'))->assertOk()
            ->assertViewHas('payments',fn($rows)=>$rows->isEmpty())
            ->assertViewHas('paymentsPaginator',fn($p)=>$p->total()===0)
            ->assertDontSee('aria-label="Pagination Navigation"',false);
    }
}
