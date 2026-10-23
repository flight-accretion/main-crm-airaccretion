<?php
// Render production views using synthetic data only; never connect to the CRM database.
use App\Http\Controllers\ServiceController;
use App\Models\{Service, Product, ExtraService, User, UserType};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app) {
    $app['config']->set('database.default', 'fixture');
    $app['config']->set('database.connections.fixture', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $app['config']->set('cache.default', 'array');
    $app['config']->set('session.driver', 'array');
    $app['config']->set('app.url', 'http://crm.test');
    $app['config']->set('settings.currency_symbol', "\u{20B9}");
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\URL::forceRootUrl('http://crm.test');
if (DB::connection()->getDatabaseName() !== ':memory:') throw new RuntimeException('Synthetic database only.');
$dir = $argv[1] ?? sys_get_temp_dir().'/services-design';
if (!is_dir($dir)) mkdir($dir, 0777, true);
config(['view.compiled' => $dir.'/compiled']);
if (!is_dir($dir.'/compiled')) mkdir($dir.'/compiled');
$app->forgetInstance('blade.compiler'); app('view.engine.resolver')->forget('blade');
view()->getFinder()->prependLocation(__DIR__.'/views');
view()->share('errors', new Illuminate\Support\ViewErrorBag());
Schema::create('products', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('product'); $t->integer('status')->default(1); $t->timestamps(); });
Schema::create('services', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('service'); $t->text('product_ids'); $t->integer('service_amount'); $t->decimal('fees_percent', 5, 2); $t->string('description')->nullable(); $t->text('booking_email_note')->nullable(); $t->text('terms_and_conditions')->nullable(); $t->integer('status'); $t->timestamps(); });
Schema::create('extra_services', function (Blueprint $t) { $t->uuid('id')->primary(); $t->string('extra_service'); $t->integer('extra_service_amount'); $t->string('description')->nullable(); $t->string('usage_scope')->default('customer'); $t->integer('status')->default(1); $t->timestamps(); });
Schema::create('service_extra_service', function (Blueprint $t) { $t->uuid('service_id'); $t->uuid('extra_service_id'); $t->timestamps(); });
$actor = (new User())->forceFill(['id' => (string) Str::uuid(), 'name' => 'Catalog administrator']);
$actor->setRelation('userType', new UserType(['user_type' => UserType::SUPER_ADMIN])); auth()->setUser($actor);
$product = Product::forceCreate(['id' => (string) Str::uuid(), 'product' => 'Gangtok to Bagdogra helicopter']);
$extras = collect();
foreach (['Airport pickup' => 3500, 'Priority boarding' => 1200, 'Birthday decoration' => 4000] as $name => $amount) $extras->push(ExtraService::forceCreate(['id' => (string) Str::uuid(), 'extra_service' => $name, 'extra_service_amount' => $amount, 'usage_scope' => 'customer', 'status' => 1]));
for ($i = 0; $i < 28; $i++) {
    $service = Service::create(['id' => (string) Str::uuid(), 'service' => $i === 0 ? 'Gangtok To Bagdogra By Helicopter - 8 People' : ($i === 1 ? 'Bagdogra To Gangtok By Helicopter - 5 People' : 'Helicopter transfer '.$i), 'product_ids' => [$product->id], 'service_amount' => $i === 0 ? 0 : 118000, 'fees_percent' => 18, 'status' => $i === 1 ? 0 : 1, 'description' => 'Shared helicopter transfer, Gangtok to Bagdogra.', 'booking_email_note' => 'Please carry a valid photo ID.', 'terms_and_conditions' => '<ol><li>Existing weather terms.</li><li>Reporting instructions.</li></ol>']);
    if ($i < 3) $service->extraServices()->attach($extras->take(2)->pluck('id'));
}
$controller = app(ServiceController::class);
$request = Illuminate\Http\Request::create('/admin/services', 'GET');
app()->instance('request', $request);
file_put_contents($dir.'/index.html', $controller->index($request)->render());
file_put_contents($dir.'/create.html', $controller->create()->render());
$first = Service::where('service_amount', 0)->firstOrFail();
file_put_contents($dir.'/edit.html', $controller->edit($first)->render());
$details = [];
foreach (Service::all() as $service) $details[$service->id] = ['success' => true, 'id' => $service->id, 'html' => view('admin.pages.services.partials.details', compact('service'))->render()];
file_put_contents($dir.'/details.json', json_encode($details));
echo 'PASS: synthetic service views rendered in '.$dir.PHP_EOL;
