<?php

namespace Tests\Feature;

use App\Http\Controllers\TargetController;
use App\Models\Target;
use App\Models\User;
use App\Models\UserType;
use App\Services\TargetIncentiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TargetIncentivePersistenceTest extends TestCase
{
    private array $staff = [];
    private string $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'session.driver'=>'array']);
        DB::purge('sqlite');
        Schema::create('user_types', function (Blueprint $t) { $t->string('id')->primary(); $t->string('user_type'); });
        Schema::create('users', function (Blueprint $t) { $t->string('id')->primary(); $t->string('name'); $t->string('user_type_id'); $t->integer('status')->default(1); $t->timestamps(); });
        Schema::create('products', function (Blueprint $t) { $t->string('id')->primary(); $t->string('product'); });
        require_once base_path('database/migrations/2025_09_06_160536_create_targets_table.php');
        require_once base_path('database/migrations/2026_10_14_180000_add_incentive_settings_to_targets.php');
        (new \CreateTargetsTable())->up(); (new \AddIncentiveSettingsToTargets())->up();
        Schema::create('sales_executive_assignments', function (Blueprint $t) { $t->string('id')->primary(); $t->string('manager_id'); $t->string('sales_executive_id'); $t->integer('status'); });
        foreach (['hr'=>UserType::HR,'admin'=>UserType::ADMIN,'manager'=>UserType::SALES_MANAGER,'operations'=>UserType::OPERATIONS_EXECUTIVE,'sales'=>UserType::SALES_EXECUTIVE] as $key=>$role) {
            $roleId=(string) Str::uuid(); DB::table('user_types')->insert(['id'=>$roleId,'user_type'=>$role]);
            $this->staff[$key]=User::create(['name'=>ucfirst($key),'user_type_id'=>$roleId,'status'=>1]);
        }
        $this->product=(string) Str::uuid(); DB::table('products')->insert(['id'=>$this->product,'product'=>'Charter']);
        auth()->setUser($this->staff['hr']);
    }

    private function request(array $overrides=[]): Request
    {
        return Request::create('/admin/targets','POST',array_merge(['sales_executive_id'=>$this->staff['operations']->id,'year'=>2026,'month'=>10,'target_amount'=>0,'status'=>'active','incentive_enabled'=>1,'incentive_multiplier'=>'0.002','incentive_basis'=>'company','incentive_all_products'=>0,'incentive_product_ids'=>[$this->product]],$overrides));
    }

    public function test_company_settings_are_persisted_for_operations_without_changing_target_amount(): void
    {
        $request=$this->request();$request->setLaravelSession(app('session.store'));
        (new TargetController(new TargetIncentiveService()))->store($request);
        $target=Target::firstOrFail();
        $this->assertEquals(0,$target->target_amount);
        $this->assertEquals(['0.00200000','company',[$this->product]],[$target->incentive_multiplier,$target->incentive_basis,$target->incentive_product_ids]);
    }

    public function test_team_has_separate_manager_rates_and_does_not_update_members(): void
    {
        DB::table('sales_executive_assignments')->insert(['id'=>(string)Str::uuid(),'manager_id'=>$this->staff['manager']->id,'sales_executive_id'=>$this->staff['sales']->id,'status'=>1]);
        $request=$this->request(['sales_executive_id'=>$this->staff['manager']->id,'incentive_basis'=>'team','incentive_team_multiplier'=>'0.005']);$request->setLaravelSession(app('session.store'));
        (new TargetController(new TargetIncentiveService()))->store($request);
        $target=Target::firstOrFail();$this->assertEquals('0.00500000',$target->incentive_team_multiplier);
        $result=(new TargetIncentiveService())->calculate($target,collect([['owner'=>$this->staff['manager']->id,'products'=>[],'amount'=>500000],['owner'=>$this->staff['sales']->id,'products'=>[],'amount'=>800000]]));
        $this->assertEquals(5000,$result['incentive']);
        $this->assertEquals(0,Target::where('sales_executive_id',$this->staff['sales']->id)->count());
    }

    public function test_operations_cannot_use_individual_incentives(): void
    {
        $this->expectException(ValidationException::class);
        (new TargetController(new TargetIncentiveService()))->store($this->request(['incentive_basis'=>'individual']));
    }

    public function test_admin_cannot_inject_incentive_configuration(): void
    {
        auth()->setUser($this->staff['admin']);$this->expectException(HttpException::class);
        (new TargetController(new TargetIncentiveService()))->store($this->request());
    }

    public function test_existing_records_default_to_disabled_incentives(): void
    {
        $target=Target::create(['id'=>(string)Str::uuid(),'sales_executive_id'=>$this->staff['sales']->id,'assigned_by'=>$this->staff['hr']->id,'year'=>2026,'month'=>10,'target_amount'=>1000000]);
        $this->assertFalse($target->fresh()->incentive_enabled);
        $this->assertEquals(0,(new TargetIncentiveService())->forTarget($target->fresh())['incentive']);
    }
}
