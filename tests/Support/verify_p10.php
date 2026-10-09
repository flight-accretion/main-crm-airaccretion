<?php

// Read-only parity checks against HEAD; migration tests use an isolated SQLite database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

function checkP10(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . "\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

function baselineP10(string $path, string $class): string
{
    $process = proc_open(['git', 'show', 'HEAD:' . $path],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    $source = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Cannot load baseline: ' . $error);
    }
    $baseline = 'P10Baseline' . $class;
    $source = preg_replace('/class ' . $class . '\\b/', 'class ' . $baseline, $source, 1);
    eval(substr($source, strpos($source, '<?php') + 5));
    return (str_contains($path, 'Exports/') ? 'App\\Exports\\' : 'App\\Http\\Controllers\\') . $baseline;
}

function normalizedP10($rows): array
{
    $rows = collect($rows)->map(fn ($row) => json_encode($row))->all();
    sort($rows);
    return $rows;
}

$connection = config('database.default');
$host = config('database.connections.' . $connection . '.host');
checkP10(in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
    || DB::getDriverName() === 'sqlite', 'Database connection is local');
echo 'Database driver: ' . DB::getDriverName() . "\n";

$limitBefore = config('crm.max_report_rows');
config(['crm.max_report_rows' => 1]);
$pageMethod = new ReflectionMethod(App\Http\Controllers\ReportController::class, 'reportPageRows');
$pageMethod->setAccessible(true);
$pageRows = $pageMethod->invoke(new App\Http\Controllers\ReportController(), App\Models\LeadFollowup::query());
checkP10($pageRows->pluck('lead_id')->unique()->count() === 1, 'Report limit applies to one complete lead');
checkP10($pageRows->count() === App\Models\LeadFollowup::where('lead_id', $pageRows->first()->lead_id)->count(),
    'Report limit retains all follow-ups for the selected lead');
config(['crm.max_report_rows' => $limitBefore]);

$salesDateOnly = in_array('--sales-date', $argv, true);
$exportNames = $salesDateOnly ? ['SalesReportExport'] : [
    'SalesReportExport', 'PaymentReportExport', 'ProfitLossReportExport', 'RideStatusExport',
    'VendorPaymentsExport', 'VendorReportExport',
];
foreach ($exportNames as $name) {
    $baselineClass = baselineP10('app/Exports/' . $name . '.php', $name);
    $newClass = 'App\\Exports\\' . $name;
    $filters = ['status' => ''];
    if ($salesDateOnly) {
        $paidDate = App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->whereNotNull('paid_date')->whereHas('leadFollowup', fn ($query) =>
                $query->whereIn('status', App\Models\LeadFollowup::salesAmountStatuses()))
            ->orderByDesc('paid_date')->value('paid_date');
        checkP10($paidDate !== null, 'A real approved-payment period is available');
        $period = Carbon\Carbon::parse($paidDate);
        $filters += ['month' => $period->month, 'year' => $period->year];
    }
    $before = new $baselineClass($filters);
    $after = new $newClass($filters);
    checkP10($before->headings() === $after->headings(), $name . ' headings unchanged');
    $oldRows = $before->collection();
    if ($after instanceof Maatwebsite\Excel\Concerns\FromGenerator) {
        $newRows = iterator_to_array($after->generator(), false);
    } else {
        $method = new ReflectionMethod($after, 'buildRow');
        $method->setAccessible(true);
        $newRows = $after->query()->get()->map(fn ($row) => $method->invoke($after, $row));
    }
    if ($name === 'RideStatusExport') {
        // The selected ride ID is not exported; tied timestamps can select a different ID.
        $withoutId = function ($row) {
            $values = (array) $row;
            unset($values['id']);
            return $values;
        };
        $oldRows = collect($oldRows)->map($withoutId);
        $newRows = collect($newRows)->map($withoutId);
    }
    if (normalizedP10($oldRows) !== normalizedP10($newRows)) {
        $newByLead = collect($newRows)->keyBy('lead_id');
        foreach ($oldRows as $oldRow) {
            $oldValues = (array) $oldRow;
            $newValues = (array) $newByLead->get($oldValues['lead_id'] ?? '');
            $differentFields = array_keys(array_filter($oldValues,
                fn ($value, $key) => json_encode($value) !== json_encode($newValues[$key] ?? null), ARRAY_FILTER_USE_BOTH));
            if ($differentFields) {
                echo 'Different fields: ' . implode(', ', $differentFields) . "\n";
                break;
            }
        }
    }
    checkP10(normalizedP10($oldRows) === normalizedP10($newRows),
        $name . ' row values unchanged (' . count($newRows) . ' rows)');
}

if ($salesDateOnly) {
    exit(0);
}

$baselineDashboard = baselineP10('app/Http/Controllers/DashboardController.php', 'DashboardController');
$repIds = App\Models\Lead::whereNotNull('representative_user_id')->distinct()->pluck('representative_user_id')->all();
foreach ([8, 9, 10] as $month) {
    $before = new ReflectionMethod($baselineDashboard, 'calculateAchievedAmountForRepIds');
    $after = new ReflectionMethod(App\Http\Controllers\DashboardController::class, 'calculateAchievedAmountForRepIds');
    $before->setAccessible(true);
    $after->setAccessible(true);
    checkP10(abs($before->invoke(new $baselineDashboard(), $repIds, 2026, $month)
        - $after->invoke(new App\Http\Controllers\DashboardController(), $repIds, 2026, $month)) < 0.000001,
        'Dashboard achieved amount unchanged for month ' . $month);
}

config(['cache.default' => 'array']);
$cache = app(App\Services\MasterDataCacheService::class);
$cache->clear();
DB::enableQueryLog();
$cache->activeProducts();
$cache->activeServices();
$cache->activeExtraServices();
$cache->countries();
$cache->activeUserTypes();
DB::flushQueryLog();
$cache->activeProducts();
$cache->activeServices();
$cache->activeExtraServices();
$cache->countries();
$cache->activeUserTypes();
checkP10(count(DB::getQueryLog()) === 0, 'Master dropdown second reads use cache');
$cache->clear();
DB::flushQueryLog();
$cache->activeProducts();
checkP10(count(DB::getQueryLog()) === 1, 'Cache clear reloads master data');
DB::disableQueryLog();

config(['database.default' => 'p10_test', 'database.connections.p10_test' => [
    'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
]]);
DB::purge('p10_test');
Schema::create('leads', function (Blueprint $table) {
    $table->id();
    $table->string('status');
    $table->timestamp('created_at');
    $table->string('representative_user_id');
    $table->string('crm_lead_code');
    $table->index(['status', 'created_at'], 'existing_status_created');
});
Schema::create('clients', function (Blueprint $table) {
    $table->id();
    $table->string('contact_number');
});
require dirname(__DIR__, 2) . '/database/migrations/2026_10_09_170000_add_p10_safe_performance_indexes.php';
$migration = new AddP10SafePerformanceIndexes();
$migration->up();
$indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads');
checkP10(!isset($indexes['p10_leads_status_created_idx']), 'Equivalent index under another name is skipped');
checkP10(isset($indexes['p10_leads_rep_status_created_idx']), 'Missing valid index is created');
checkP10(!isset($indexes['p10_leads_product_created_idx']), 'Missing column is safely skipped');
$count = count($indexes);
$migration->up();
checkP10(count(DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads')) === $count,
    'Migration up is idempotent');
$migration->down();
$indexes = DB::connection()->getDoctrineSchemaManager()->listTableIndexes('leads');
checkP10(isset($indexes['existing_status_created']), 'Rollback preserves existing indexes');
checkP10(!isset($indexes['p10_leads_rep_status_created_idx']), 'Rollback removes P10 indexes');
$migration->down();
echo "P10 checks completed.\n";
