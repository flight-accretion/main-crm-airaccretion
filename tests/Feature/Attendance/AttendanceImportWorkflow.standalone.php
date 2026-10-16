<?php

require __DIR__ . '/AttendanceImportCompatibility.standalone.php';

use App\Http\Controllers\AttendanceImportController;
use App\Models\AttendanceImport;
use App\Models\AttendanceRecord;
use App\Models\AttendanceShiftPolicy;
use App\Models\User;
use App\Services\Attendance\AttendanceImportParser;
use App\Services\Attendance\AttendanceShiftResolver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// Explicit services only. Do not bootstrap Laravel, providers, .env, or live connections.
$temporary = sys_get_temp_dir() . '/attendance-import-' . bin2hex(random_bytes(6));
mkdir($temporary, 0700, true);
$container->instance('config', new Illuminate\Config\Repository([
    'database' => ['default' => 'default', 'connections' => ['default' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]],
    'filesystems' => ['default' => 'local', 'disks' => ['local' => ['driver' => 'local', 'root' => $temporary]]],
]));
$files = new Illuminate\Filesystem\Filesystem();
$container->instance('files', $files);
$container->instance('filesystem', new Illuminate\Filesystem\FilesystemManager($container));
$session = new Illuminate\Session\Store('attendance-test', new Illuminate\Session\ArraySessionHandler(120));
$session->start();
$container->instance('session', $session);
$container->instance('session.store', $session);
$routes = new Illuminate\Routing\RouteCollection();
foreach (['index' => '', 'sample' => '/sample', 'preview' => '/preview', 'confirm' => '/confirm'] as $name => $suffix) {
    $route = new Illuminate\Routing\Route('GET', '/admin/attendance/import' . $suffix, fn () => null);
    $route->name('admin.attendance.import.' . $name);
    $routes->add($route);
}
$request = Request::create('http://attendance.test/admin/attendance/import');
$container->instance('request', $request);
$url = new Illuminate\Routing\UrlGenerator($routes, $request);
$container->instance('url', $url);
$container->instance(Illuminate\Contracts\Routing\UrlGenerator::class, $url);
$redirector = new Illuminate\Routing\Redirector($url);
$redirector->setSession($session);
$container->instance('redirect', $redirector);
$events = new Illuminate\Events\Dispatcher($container);
Illuminate\Database\Eloquent\Model::setEventDispatcher($events);
Illuminate\Database\Eloquent\Model::clearBootedModels();
$resolver = new Illuminate\View\Engines\EngineResolver();
$compiler = new Illuminate\View\Compilers\BladeCompiler($files, $temporary);
$resolver->register('blade', fn () => new Illuminate\View\Engines\CompilerEngine($compiler));
$views = new Illuminate\View\Factory($resolver, new Illuminate\View\FileViewFinder($files, [dirname(__DIR__, 3) . '/resources/views']), $events);
$views->setContainer($container);
$views->addNamespace('pagination', dirname(__DIR__, 3) . '/vendor/laravel/framework/src/Illuminate/Pagination/resources/views');
Illuminate\Pagination\AbstractPaginator::viewFactoryResolver(fn () => $views);
$container->instance('view', $views);
$container->instance(Illuminate\Contracts\View\Factory::class, $views);
$container->instance(Illuminate\Contracts\Routing\ResponseFactory::class, new Illuminate\Routing\ResponseFactory($views, $redirector));
$container['validator']->setPresenceVerifier(new Illuminate\Validation\DatabasePresenceVerifier($database->getDatabaseManager()));

$database->schema()->drop('attendance_user_mappings');
require dirname(__DIR__, 3) . '/database/migrations/2026_09_18_193505_create_attendance_import_tables.php';
(new CreateAttendanceImportTables())->up();
$database->schema()->table('attendance_imports', fn ($table) => $table->string('file_hash', 64)->nullable()->index());
$database->schema()->create('users', function ($table) {
    $table->string('id')->primary(); $table->string('name'); $table->integer('status');
    $table->string('user_type_id')->nullable(); $table->timestamps();
});
$database->schema()->create('user_types', function ($table) { $table->string('id'); $table->string('user_type'); });
$actor = User::create(['name' => 'Charul Garg', 'status' => 1]);
$policyResolver = new class extends AttendanceShiftResolver {
    public int $batchCalls = 0;
    public int $singleCalls = 0;
    public function resolveForDate(User $user, Carbon $date): AttendanceShiftPolicy {
        $this->singleCalls++;
        return new AttendanceShiftPolicy(['name' => 'Test office', 'start_time' => '10:30:00', 'end_time' => '19:30:00', 'grace_minutes' => 0]);
    }
    public function resolveForDates(User $user, Illuminate\Support\Collection $dates): Illuminate\Support\Collection {
        $this->batchCalls++;
        return $dates->mapWithKeys(fn ($date) => [Carbon::parse($date)->toDateString() => new AttendanceShiftPolicy(['name' => 'Test office', 'start_time' => '10:30:00', 'end_time' => '19:30:00', 'grace_minutes' => 0])]);
    }
};
class AttendanceTestRequest extends Request {
    public function validate(array $rules, array $messages = []) { return app('validator')->make($this->all(), $rules, $messages)->validate(); }
}
function confirmAttendance(AttendanceImport $import, User $actor, AttendanceShiftResolver $resolver) {
    $parsed = (new AttendanceImportParser())->parse(Storage::disk('local')->path($import->stored_path), Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
    $request = new AttendanceTestRequest(['import_id' => $import->id, 'mappings' => array_fill_keys(array_column($parsed['employees'], 'mapping_key'), $actor->id)]);
    $request->setUserResolver(fn () => $actor);
    return (new AttendanceImportController())->confirm($request, new AttendanceImportParser(), $resolver);
}
function makeImport(string $path, User $actor, ?string $hash = null): AttendanceImport {
    return AttendanceImport::create([
        'from_date' => '2026-09-01', 'to_date' => '2026-09-30', 'original_filename' => 'renamed.csv',
        'stored_path' => $path, 'file_hash' => $hash, 'file_type' => 'csv', 'status' => 'previewed', 'uploaded_by' => $actor->id,
    ]);
}
$controller = new AttendanceImportController();
check('CSV and XLSX download responses round trip through real parser', function () use ($controller, $temporary) {
    foreach (['csv', 'xlsx'] as $format) {
        $response = $controller->downloadSample(Request::create('/sample?format=' . $format));
        ob_start(); $response->sendContent(); $bytes = ob_get_clean();
        $path = $temporary . '/sample.' . $format;
        file_put_contents($path, $bytes);
        $sheet = PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();
        same(['Date', 'Day', 'Name', 'In', 'Out', 'Status'], array_slice($sheet->toArray()[0], 0, 6));
        same(6, count($sheet->toArray()[0]));
        $parsed = (new AttendanceImportParser())->parse($path, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
        same(3, count($parsed['rows']));
        same(['P', 'MIS', 'A'], array_column($parsed['rows'], 'raw_status'));
    }
});
check('legacy null hash confirmation preserves real paycode and never saves name mapping', function () use ($actor, $policyResolver, $database) {
    $database->table('attendance_records')->insert(['id' => (string) Str::uuid(), 'user_id' => $actor->id, 'attendance_date' => '2026-09-01', 'paycode' => 'REAL-1', 'source_import_id' => (string) Str::uuid()]);
    $import = makeImport('sample.csv', $actor);
    makeImport('sample.csv', $actor);
    confirmAttendance($import, $actor, $policyResolver);
    same('completed', $import->fresh()->status);
    same(64, strlen($import->fresh()->file_hash));
    same('REAL-1', AttendanceRecord::whereDate('attendance_date', '2026-09-01')->value('paycode'));
    same(0, $database->table('attendance_user_mappings')->count());
    same(3, AttendanceRecord::count());
    same('MIS', AttendanceRecord::whereDate('attendance_date', '2026-09-02')->value('raw_status'));
});
check('two previews of same bytes and range cannot both complete', function () use ($actor, $policyResolver) {
    $import = AttendanceImport::where('status', 'previewed')->firstOrFail();
    try { confirmAttendance($import, $actor, $policyResolver); }
    catch (Illuminate\Validation\ValidationException $e) {
        same('previewed', $import->fresh()->status);
        same(3, AttendanceRecord::count());
        return;
    }
    throw new RuntimeException('Duplicate preview was confirmed');
});
check('confirmation resolves policy batch once per employee and keeps snapshots', function () use ($policyResolver) {
    same(1, $policyResolver->batchCalls);
    same(0, $policyResolver->singleCalls);
    same('Test office', AttendanceRecord::whereDate('attendance_date', '2026-09-01')->value('resolved_shift_policy_name'));
    same('10:30:00', AttendanceRecord::whereDate('attendance_date', '2026-09-01')->value('resolved_shift_start_time'));
});
check('hash duplicate scope includes selected range and completed status only', function () use ($controller) {
    $hash = AttendanceImport::where('status', 'completed')->value('file_hash');
    invoke($controller, 'rejectCompletedDuplicate', $hash, '2026-09-02', '2026-09-30');
    invoke($controller, 'rejectCompletedDuplicate', str_repeat('a', 64), '2026-09-01', '2026-09-30');
    try { invoke($controller, 'rejectCompletedDuplicate', $hash, '2026-09-01', '2026-09-30'); }
    catch (Illuminate\Validation\ValidationException $e) { return; }
    throw new RuntimeException('Completed identical hash/range accepted');
});
check('preview rejects renamed identical content before storing another upload', function () use ($controller, $temporary, $actor) {
    $before = AttendanceImport::count();
    $request = new AttendanceTestRequest(['from_date' => '2026-09-01', 'to_date' => '2026-09-30']);
    $request->files->set('excel_file', new Illuminate\Http\UploadedFile($temporary . '/sample.csv', 'different-name.csv', 'text/csv', null, true));
    $request->setUserResolver(fn () => $actor);
    try { $controller->preview($request, new AttendanceImportParser()); }
    catch (Illuminate\Validation\ValidationException $e) {
        same(true, strpos(implode(' ', array_merge(...array_values($e->errors()))), 'already been imported') !== false);
        same($before, AttendanceImport::count());
        return;
    }
    throw new RuntimeException('Duplicate preview accepted');
});
check('legacy completed uploads without hashes still block identical readable files', function () use ($controller, $actor, $temporary) {
    $source = file_get_contents($temporary . '/sample.csv');
    Storage::disk('local')->put('legacy.csv', $source);
    $legacy = AttendanceImport::create(['from_date' => '2026-08-01', 'to_date' => '2026-08-31',
        'original_filename' => 'legacy.csv', 'stored_path' => 'legacy.csv', 'status' => 'completed',
        'uploaded_by' => $actor->id]);
    try { invoke($controller, 'rejectCompletedDuplicate', hash('sha256', $source), '2026-08-01', '2026-08-31'); }
    catch (Illuminate\Validation\ValidationException $error) {
        same(hash('sha256', $source), $legacy->fresh()->file_hash);
        return;
    }
    throw new RuntimeException('Legacy duplicate without a hash was accepted');
});

check('PostgreSQL advisory lock uses shared hash/range identity and signed integer keys', function () use ($controller, $container) {
    $manager = $container['db'];
    $probe = new class {
        public array $calls = [];
        public function connection() { return $this; }
        public function getDriverName() { return 'pgsql'; }
        public function select($sql, $bindings) { $this->calls[] = [$sql, $bindings]; return []; }
    };
    Illuminate\Support\Facades\DB::swap($probe);
    try {
        invoke($controller, 'lockImportFileRange', str_repeat('f', 64), '2026-09-01', '2026-09-30');
        invoke($controller, 'lockImportFileRange', str_repeat('f', 64), '2026-09-01', '2026-09-30');
        invoke($controller, 'lockImportFileRange', str_repeat('f', 64), '2026-09-02', '2026-09-30');
        same($probe->calls[0], $probe->calls[1]);
        same(false, $probe->calls[0][1] === $probe->calls[2][1]);
        same('SELECT pg_advisory_xact_lock(?, ?)', $probe->calls[0][0]);
        foreach ($probe->calls[0][1] as $key) same(true, is_int($key) && $key >= -2147483648 && $key <= 2147483647);
    } finally { Illuminate\Support\Facades\DB::swap($manager); }
});
check('history detail is read only and selects only source import records', function () use ($controller) {
    $import = AttendanceImport::where('status', 'completed')->whereDate('from_date', '2026-09-01')->firstOrFail();
    $before = AttendanceRecord::count();
    $view = $controller->show(new AttendanceTestRequest(), $import);
    same($import->id, $view->getData()['selectedImport']->id);
    same(3, $view->getData()['selectedRecords']->total());
    same($before, AttendanceRecord::count());
});

// Generate a static rendering of the real page for browser checks, without the live CRM layout.
check('Blade content renders for history and read-only detail', function () use ($controller, $compiler, $temporary, $session) {
    $import = AttendanceImport::where('status', 'completed')->whereDate('from_date', '2026-09-01')->firstOrFail();
    $data = $controller->show(new AttendanceTestRequest(), $import)->getData();
    $data['errors'] = new Illuminate\Support\ViewErrorBag();
    $data['__env'] = app('view');
    $source = file_get_contents(dirname(__DIR__, 3) . '/resources/views/admin/pages/attendance/import.blade.php');
    $source = str_replace("@extends('admin.layouts.header')", '', $source);
    $source = str_replace(['@section(\'content\')', '@endsection', "@push('scripts')", '@endpush'], '', $source);
    extract($data);
    ob_start();
    try { eval('?>' . $compiler->compileString($source)); $html = ob_get_contents(); }
    finally { ob_end_clean(); }
    same(true, strpos($html, 'Miss punch') !== false);
    file_put_contents($temporary . '/page.html', '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>' . $html . '</body></html>');
    echo 'BROWSER_FIXTURE=' . $temporary . '/page.html' . PHP_EOL;
});
$files->deleteDirectory($temporary . '/attendance-imports');
echo "Temporary test artifacts: {$temporary}\n";
exit($failed ? 1 : 0);
