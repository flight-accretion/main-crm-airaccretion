<?php

$app = require __DIR__.'/wave1_qa_bootstrap.php';
if (PHP_SAPI === 'cli') {
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $connection = $app['db']->connection();
    $driver = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isolated = $driver === 'pgsql'
        ? array_keys(config('database.connections')) === ['pgsql']
            && $connection->getDatabaseName() === 'accretion_wave1_qa'
            && $connection->selectOne('SELECT current_user AS role')->role === 'accretion_wave1_qa_role'
        : array_keys(config('database.connections')) === ['sqlite']
            && realpath($connection->getDatabaseName()) === realpath(getenv('CRM_WAVE1_QA_ROOT').'/database.sqlite');
    if (!$isolated) {
        throw new RuntimeException('QA database isolation check failed.');
    }
    echo json_encode(['driver' => $driver, 'database' => $connection->getDatabaseName(), 'outbound' => 'disabled']).PHP_EOL;
    if (($argv[1] ?? '') === 'compat') {
        require __DIR__.'/wave1_qa_compat.php';
    }
    if (($argv[1] ?? '') === 'seed') {
        require __DIR__.'/wave1_qa_seed.php';
    }
    if (($argv[1] ?? '') === 'queue-fixtures') {
        require __DIR__.'/wave1_qa_queue_fixtures.php';
    }
    if (($argv[1] ?? '') === 'audit') {
        $lead = \App\Models\Lead::whereHas('client', fn ($query) => $query->where('email', 'browser@wave1.invalid'))->firstOrFail();
        $latest = $lead->leadFollowups()->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();
        echo json_encode(['latest_status' => $latest->status, 'pricing_revision' => $latest->pricing_revision,
            'stored_total' => $latest->total_amount,
            'financials' => app(\App\Services\Reports\ProfitLossSummaryService::class)->forFollowup($latest),
            'pricing_migration_applied' => $connection->table('migrations')->where('migration', '2026_11_12_162509_add_pricing_revision_to_lead_followups_table')->exists(),
        ]).PHP_EOL;
    }
    if (($argv[1] ?? '') === 'release-fixtures' && $driver === 'pgsql') {
        require __DIR__.'/wave1_qa_release_fixtures.php';
    }
    if (($argv[1] ?? '') === 'migrate') {
        exit($app->make(\Illuminate\Contracts\Console\Kernel::class)->call('migrate', ['--force' => true]));
    }
    if (($argv[1] ?? '') === 'status') {
        $applied = $connection->table('migrations')->orderBy('migration')->pluck('migration')->all();
        foreach (glob(__DIR__.'/../../database/migrations/*.php') as $migration) {
            $name = basename($migration, '.php');
            if (!in_array($name, $applied, true)) {
                echo 'FIRST_PENDING='.$name.PHP_EOL;
                break;
            }
        }
    }
    exit(0);
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(assets|js|css|images)/#', $path)) {
    $public = realpath(__DIR__.'/../../public');
    $file = realpath($public.$path);
    if ($file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file)) {
        $extensions = ['js' => 'application/javascript', 'css' => 'text/css', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'woff2' => 'font/woff2', 'woff' => 'font/woff'];
        header('Content-Type: '.($extensions[pathinfo($file, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
        readfile($file);
        return;
    }
}
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
