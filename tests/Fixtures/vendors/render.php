<?php
// Synthetic fixture only: never use the CRM database or external delivery services.
use App\Http\Controllers\VendorController;
use App\Models\Vendor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, URL};
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php'; $app = require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function($app) {
    $app['config']->set('database.default','fixture');
    $app['config']->set('database.connections.fixture',['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
    $app['config']->set('cache.default','array'); $app['config']->set('session.driver','array');
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); URL::forceRootUrl('http://crm.test');
if (DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('Synthetic database only.');
$dir=$argv[1] ?? sys_get_temp_dir().'/vendors-design'; if (!is_dir($dir)) mkdir($dir,0777,true);
config(['view.compiled'=>$dir.'/compiled']); if (!is_dir($dir.'/compiled')) mkdir($dir.'/compiled');
$app->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
view()->getFinder()->prependLocation(__DIR__.'/views'); view()->share('errors',new Illuminate\Support\ViewErrorBag());
foreach (['vendors','countries','states','cities','products','services','extra_services'] as $table) {
    Schema::create($table,function(Blueprint $t) use ($table) {
        $t->uuid('id')->primary(); $t->integer('status')->default(1); $t->timestamps();
        if (in_array($table,['vendors','countries','states','cities'])) $t->string('name');
        if ($table==='countries') { $t->string('phonecode')->default('91'); $t->string('isd_code')->default('91'); }
        if ($table==='states') $t->uuid('country_id');
        if ($table==='cities') $t->uuid('state_id');
        if ($table==='products') $t->string('product');
        if ($table==='services') { $t->string('service'); $t->integer('service_amount')->default(3500); $t->text('product_ids'); }
        if ($table==='extra_services') { $t->string('extra_service'); $t->string('description')->nullable(); $t->integer('extra_service_amount')->default(1200); }
        if ($table==='vendors') {
            $t->uuid('city_id')->nullable(); $t->string('email')->nullable(); $t->string('contact_number'); $t->text('address')->nullable(); $t->text('bank_details')->nullable(); $t->text('map_link')->nullable(); $t->string('profile_image')->nullable();
            foreach (['product_ids','service_ids','extra_service_ids'] as $column) $t->text($column)->nullable();
        }
    });
}
$ids=array_map(fn()=>(string)Str::uuid(),range(1,6));
DB::table('products')->insert(['id'=>$ids[0],'product'=>'Aviation services']);
DB::table('services')->insert(['id'=>$ids[1],'service'=>'Private plane ride','product_ids'=>json_encode([$ids[0]])]);
DB::table('countries')->insert(['id'=>$ids[2],'name'=>'India']);
DB::table('states')->insert(['id'=>$ids[3],'name'=>'Maharashtra','country_id'=>$ids[2]]);
DB::table('cities')->insert(['id'=>$ids[4],'name'=>'Mumbai','state_id'=>$ids[3]]);
DB::table('extra_services')->insert(['id'=>$ids[5],'extra_service'=>'Boarding assistance']);
for ($i=0;$i<24;$i++) Vendor::create(['id'=>(string)Str::uuid(),'name'=>$i===0 ? 'AERODYNE AIR SERVICES PRIVATE LIMITED' : 'Partner '.$i,'email'=>$i===1 ? null : 'partner'.$i.'@example.test','contact_number'=>'+91-9820405847','city_id'=>$ids[4],'address'=>'Airport road','bank_details'=>'Bank account 123','map_link'=>'https://maps.google.com/?q=airport','product_ids'=>[$ids[0]],'service_ids'=>[$ids[1]],'extra_service_ids'=>[$ids[5]],'status'=>$i===1 ? 0 : 1]);
$controller=app(VendorController::class); $records=[];
foreach (Vendor::all() as $vendor) $records[$vendor->id]=['view'=>$controller->viewModal($vendor)->getData(true),'edit'=>$controller->edit($vendor)->getData(true)];
foreach (['index'=>[], 'filtered'=>['status'=>'0','vendor_name'=>'Partner']] as $file=>$query) {
    $request=Illuminate\Http\Request::create('/admin/vendors','GET',$query); app()->instance('request',$request);
    file_put_contents($dir.'/'.$file.'.html',$controller->index($request)->render());
}
file_put_contents($dir.'/records.json',json_encode($records)); echo 'PASS synthetic vendor views rendered in '.$dir.PHP_EOL;
