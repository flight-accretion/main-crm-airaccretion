<?php

// Controller/view smoke checks run in read-only transactions on the local database.
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\RideController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UpcomingFollowUpController;
use App\Http\Controllers\PaymentReviewController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\VoucherController;

function pageBaselineP10(string $class): string
{
    $process = proc_open(['git', 'show', 'HEAD:app/Http/Controllers/' . $class . '.php'],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
    $source = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Cannot load page baseline.');
    }
    $baseline = 'P10PageBaseline' . $class;
    $source = preg_replace('/class ' . $class . '\\b/', 'class ' . $baseline, $source, 1);
    eval(substr($source, strpos($source, '<?php') + 5));
    return 'App\\Http\\Controllers\\' . $baseline;
}

$host = config('database.connections.' . config('database.default') . '.host');
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Smoke checks require a local database.');
}
config(['cache.default' => 'array', 'session.driver' => 'array']);
$user = App\Models\User::with('userType')
    ->whereHas('userType', fn ($query) => $query->where('user_type', App\Models\UserType::SUPER_ADMIN))->firstOrFail();
auth()->setUser($user);
app('view')->share('errors', new Illuminate\Support\ViewErrorBag());

$checks = [
    ['Lead list', ClientController::class, 'index', '/admin/lead', []],
    ['Customer search', ClientController::class, 'indexClient', '/admin/client', ['search' => 'p10-no-match']],
    ['Today/upcoming follow-ups', UpcomingFollowUpController::class, 'index', '/admin/upcoming-follow-up', []],
    ['Upcoming rides', RideController::class, 'upcomingRides', '/admin/rides/upcoming-ride', []],
    ['Ride calendar', RideController::class, 'getCalendarEvents', '/admin/rides/api/calendar-events', [
        'from_date' => '2026-10-01', 'to_date' => '2026-10-31',
    ]],
    ['Payment approval', PaymentReviewController::class, 'index', '/admin/account/payment-review', []],
    ['Voucher list', VoucherController::class, 'index', '/admin/vouchers', []],
    ['Invoice list', InvoiceController::class, 'index', '/admin/account/invoices', []],
    ['Payment report', ReportController::class, 'index', '/admin/report', []],
    ['Sales report', ReportController::class, 'salesReport', '/admin/report/sales', ['month' => 9, 'year' => 2026]],
    ['Vendor report', ReportController::class, 'vendorReport', '/admin/report/vendor-payments', []],
    ['Profit/loss report', ReportController::class, 'profitLossReport', '/admin/report/profit-loss', []],
    ['KPI report', ReportController::class, 'kpiReport', '/admin/report/kpi', []],
    ['Client form/country dropdown', ClientController::class, 'create', '/admin/lead/create', []],
];

$failures = 0;
foreach ($checks as [$label, $controller, $method, $uri, $filters]) {
    DB::beginTransaction();
    DB::statement('SET TRANSACTION READ ONLY');
    try {
        $request = Request::create($uri, 'GET', $filters);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session')->driver());
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $response = app($controller)->$method($request);
        $parityKeys = [
            'Today/upcoming follow-ups' => ['UpcomingFollowUpController', 'arrFollowUps', 'id'],
            'Payment approval' => ['PaymentReviewController', 'payments', 'lead_id'],
            'KPI report' => ['ReportController', 'kpiData', 'representative_id'],
        ];
        if (isset($parityKeys[$label])) {
            [$baselineName, $dataKey, $sortKey] = $parityKeys[$label];
            $baselineClass = pageBaselineP10($baselineName);
            $baselineResponse = app($baselineClass)->$method($request);
            $before = collect($baselineResponse->getData()[$dataKey])->sortBy($sortKey)->values();
            $after = collect($response->getData()[$dataKey])->sortBy($sortKey)->values();
            if ($before->toJson() !== $after->toJson()) {
                echo 'Parity row counts: ' . $before->count() . ' / ' . $after->count() . "\n";
                $afterByLead = $after->keyBy('lead_id');
                foreach ($before as $oldRow) {
                    if (!method_exists($oldRow, 'toArray')) {
                        break;
                    }
                    $newRow = $afterByLead->get($oldRow->lead_id);
                    $newValues = $newRow ? $newRow->toArray() : [];
                    $fields = array_keys(array_filter($oldRow->toArray(),
                        fn ($value, $key) => json_encode($value) !== json_encode($newValues[$key] ?? null), ARRAY_FILTER_USE_BOTH));
                    if ($fields) {
                        echo 'Parity differing fields: ' . implode(', ', $fields) . "\n";
                        break;
                    }
                }
                throw new RuntimeException('Data differs from committed baseline.');
            }
            echo 'PASS: ' . $label . ' data unchanged' . "\n";
        }
        if ($response instanceof Illuminate\Contracts\View\View) {
            $response->render();
        } elseif ($response->getStatusCode() !== 200) {
            throw new RuntimeException('HTTP status ' . $response->getStatusCode());
        }
        echo 'PASS: ' . $label . "\n";
    } catch (Throwable $error) {
        $failures++;
        echo 'FAIL: ' . $label . ': ' . $error->getMessage() . "\n";
    } finally {
        DB::rollBack();
    }
}
DB::beginTransaction();
DB::statement('SET TRANSACTION READ ONLY');
try {
    $ride = App\Models\LeadRide::whereHas('enquiry.vouchers')->firstOrFail();
    $response = app(RideController::class)->getRideDetails($ride->id);
    if ($response->getStatusCode() !== 200 || isset($response->getData(true)['error'])) {
        throw new RuntimeException('Ride details did not return successful data.');
    }
    echo "PASS: Direct ride details\n";
} catch (Throwable $error) {
    $failures++;
    echo 'FAIL: Direct ride details: ' . $error->getMessage() . "\n";
} finally {
    DB::rollBack();
}
exit($failures ? 1 : 0);
