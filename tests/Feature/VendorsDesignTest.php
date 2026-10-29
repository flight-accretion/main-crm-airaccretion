<?php

namespace Tests\Feature;

use App\Models\{Vendor, User, UserType};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;
use Tests\TestCase;

class VendorsDesignTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'vendors_test', 'database.connections.vendors_test'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>''], 'cache.default'=>'array']);
        DB::purge('vendors_test');
        foreach (['vendors','countries','states','cities','products','services','extra_services','users','user_types'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->uuid('id')->primary(); $t->integer('status')->default(1); $t->timestamps();
                if (in_array($table,['countries','states','cities','users','vendors'])) $t->string('name');
                if ($table==='states') $t->uuid('country_id');
                if ($table==='cities') $t->uuid('state_id');
                if ($table==='countries') { $t->string('phonecode')->default('91'); $t->string('isd_code')->default('91'); }
                if ($table==='products') $t->string('product');
                if ($table==='services') { $t->string('service'); $t->text('product_ids')->nullable(); }
                if ($table==='extra_services') { $t->string('extra_service'); $t->text('description')->nullable(); $t->integer('extra_service_amount')->default(0); }
                if (in_array($table,['users','vendors'])) $t->string('email')->nullable();
                if ($table==='user_types') $t->string('user_type');
                if ($table==='users') $t->uuid('user_type_id');
                if ($table==='vendors') {
                    $t->uuid('city_id')->nullable(); $t->string('contact_number'); $t->text('address')->nullable(); $t->text('map_link')->nullable(); $t->text('bank_details')->nullable(); $t->string('profile_image')->nullable();
                    foreach (['product_ids','service_ids','extra_service_ids'] as $column) $t->text($column)->nullable();
                }
            });
        }
        view()->getFinder()->prependLocation(base_path('tests/Fixtures/kpi-tools/views'));
        $this->actor(UserType::SUPER_ADMIN);
    }

    private function actor(string $role): void
    {
        $id=(string)Str::uuid(); DB::table('user_types')->insert(['id'=>$id,'user_type'=>$role]);
        $this->actingAs(User::forceCreate(['id'=>(string)Str::uuid(),'name'=>'Vendor user','email'=>Str::uuid().'@example.test','user_type_id'=>$id,'status'=>1]));
    }
    private function vendor(array $values=[]): Vendor
    {
        return Vendor::create(array_merge(['id'=>(string)Str::uuid(),'name'=>'Aviation partner','email'=>'partner@example.test','contact_number'=>'+91-9876543210','product_ids'=>[],'service_ids'=>[],'extra_service_ids'=>[]],$values));
    }
    public function test_redesign_has_real_summary_separate_email_and_existing_actions(): void
    {
        $this->vendor(); $this->vendor(['name'=>'Hotel partner','status'=>0,'email'=>null]);
        $response=$this->get(route('admin.vendors.index'))->assertOk()->assertSee('data-vendor-catalog',false)
            ->assertSee('<th>Email</th>',false)->assertDontSee('<th>Type</th>',false)->assertDontSee('Open follow-ups')
            ->assertSee('Deactivate')->assertSee('Activate')->assertSee('view-vendor-btn',false)->assertSee('edit-vendor-btn',false)
            ->assertSee('id="filter-section" hidden',false)->assertSee('No email on file')
            ->assertViewHas('vendorSummary',fn($s)=>$s['total']===2 && $s['active']===1 && $s['inactive']===1 && $s['no_email']===1);
        foreach (['vendor_name','email','product_id','service_id','status'] as $name) $response->assertSee('name="'.$name.'"',false);
        $response->assertSee('id="add-vendor-form-element"',false)->assertSee('name="bank_details"',false)->assertSee('id="edit-vendor-form"',false);
        $this->get(route('admin.vendors.index',['status'=>'0','vendor_name'=>'Hotel']))->assertOk()->assertViewHas('vendors',fn($v)=>$v->total()===1);
    }
    public function test_status_view_update_and_permissions_remain(): void
    {
        $v=$this->vendor();
        $this->getJson(route('admin.vendors.view-modal',$v))->assertOk()->assertJsonPath('vendor.id',$v->id);
        $this->getJson(route('admin.vendors.edit',$v))->assertOk()->assertJsonPath('vendor.id',$v->id);
        $this->patch(route('admin.vendors.toggle-status',$v))->assertRedirect(route('admin.vendors.index'));
        $this->assertEquals(0,$v->fresh()->status);
        $this->patch(route('admin.vendors.toggle-status',$v))->assertRedirect(route('admin.vendors.index'));
        $this->assertEquals(1,$v->fresh()->status);
        $extra=(string)Str::uuid(); DB::table('extra_services')->insert(['id'=>$extra,'extra_service'=>'Boarding assistance']);
        $this->putJson(route('admin.vendors.update',$v),['name'=>'Updated partner','email'=>'new@example.test','country_code'=>'+91','contact_number'=>'9123456789','address'=>'Airport road','bank_details'=>'Bank account 123','extra_service_ids'=>[$extra]])
            ->assertOk()->assertJsonPath('success',true);
        $this->assertDatabaseHas('vendors',['id'=>$v->id,'name'=>'Updated partner','contact_number'=>'+91-9123456789']);
        $this->post(route('admin.vendors.store'),['name'=>'New partner','email'=>'newpartner@example.test','country_code'=>'+44','contact_number'=>'7778889999','extra_service_ids'=>[$extra]])
            ->assertRedirect(route('admin.vendors.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vendors',['name'=>'New partner','contact_number'=>'+44-7778889999']);
        $this->post(route('admin.vendors.store'),['name'=>'Invalid 123'])->assertSessionHasErrors(['name','contact_number']);
        $this->actor(UserType::OPERATIONS_EXECUTIVE); $this->get(route('admin.vendors.index'))->assertOk();
        $this->actor(UserType::SALES_EXECUTIVE); $this->get(route('admin.vendors.index'))->assertForbidden();
        $this->patch(route('admin.vendors.toggle-status',$v))->assertForbidden();
    }
}
