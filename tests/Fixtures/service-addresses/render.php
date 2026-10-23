<?php
// Isolated synthetic rendering: no CRM database or notifications are used.
use App\Http\Controllers\ServiceAddressController;
use App\Models\ServiceAddress;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, URL};
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
    $app['config']->set('database.default', 'fixture');
    $app['config']->set('database.connections.fixture', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $app['config']->set('cache.default', 'array'); $app['config']->set('session.driver', 'array');
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
URL::forceRootUrl('http://crm.test');
if (DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('Synthetic database only.');
$dir = $argv[1] ?? sys_get_temp_dir().'/service-addresses-design';
if (!is_dir($dir)) mkdir($dir, 0777, true);
config(['view.compiled' => $dir.'/compiled']);
if (!is_dir($dir.'/compiled')) mkdir($dir.'/compiled');
$app->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
view()->getFinder()->prependLocation($root.'/tests/Fixtures/services/views');
view()->share('errors', new Illuminate\Support\ViewErrorBag());
foreach (['products','services','countries','states','cities','service_addresses'] as $table) {
    Schema::create($table, function (Blueprint $t) use ($table) {
        $t->uuid('id')->primary(); $t->integer('status')->default(1); $t->timestamps();
        if ($table === 'products') $t->string('product');
        if ($table === 'services') { $t->string('service'); $t->text('product_ids'); }
        if (in_array($table, ['countries','states','cities'])) $t->string('name');
        if ($table === 'countries') $t->string('phonecode')->default('91');
        if ($table === 'states') $t->uuid('country_id');
        if ($table === 'cities') $t->uuid('state_id');
        if ($table === 'service_addresses') { $t->uuid('product_id'); $t->uuid('service_id'); $t->uuid('city_id'); $t->text('address'); $t->string('contact_person_name'); $t->string('contact_number'); $t->text('map_link')->nullable(); }
    });
}
$ids = array_map(fn () => (string) Str::uuid(), range(1,5));
DB::table('products')->insert(['id'=>$ids[0], 'product'=>'Gangtok to Bagdogra helicopter']);
DB::table('services')->insert(['id'=>$ids[1], 'service'=>'Gangtok helicopter - 8 people', 'product_ids'=>json_encode([$ids[0]])]);
DB::table('countries')->insert(['id'=>$ids[2], 'name'=>'India']);
DB::table('states')->insert(['id'=>$ids[3], 'name'=>'West Bengal', 'country_id'=>$ids[2]]);
DB::table('cities')->insert(['id'=>$ids[4], 'name'=>'Bagdogra', 'state_id'=>$ids[3]]);
for ($i=0; $i<23; $i++) ServiceAddress::create(['id'=>(string)Str::uuid(), 'product_id'=>$ids[0], 'service_id'=>$ids[1], 'city_id'=>$ids[4], 'address'=>$i===0 ? 'Bagdogra International Airport' : 'Reporting helipad '.$i, 'contact_person_name'=>$i===1 ? '' : 'Ground manager '.$i, 'contact_number'=>'+91-9876543210', 'map_link'=>'https://maps.google.com/?q=airport', 'status'=>$i===1 ? 0 : 1]);
$request = Illuminate\Http\Request::create('/admin/service-addresses', 'GET'); app()->instance('request', $request);
$controller = app(ServiceAddressController::class);
file_put_contents($dir.'/index.html', $controller->index($request)->render());
$records = [];
foreach (ServiceAddress::with('product','service','city.state.country')->get() as $address) {
    $records[$address->id] = ['view'=>$controller->viewModal($address->id)->getData(true), 'edit'=>$controller->getEditDetails($address->id)->getData(true)];
}
file_put_contents($dir.'/records.json', json_encode($records));
echo 'PASS synthetic address page rendered in '.$dir.PHP_EOL;
