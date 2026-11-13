<?php

use Illuminate\Foundation\Bootstrap\LoadConfiguration;

require_once __DIR__.'/../../vendor/autoload.php';

$qaRoot = getenv('CRM_WAVE1_QA_ROOT');
if (!$qaRoot || !is_dir($qaRoot) || !is_file($qaRoot.'/ISOLATED_WAVE1_QA')) {
    throw new RuntimeException('A dedicated marked QA directory is required.');
}
$qaRoot = realpath($qaRoot);
$database = $qaRoot.'/database.sqlite';
$postgres = is_file($qaRoot.'/postgres-secret.json')
    ? json_decode(file_get_contents($qaRoot.'/postgres-secret.json'), true, 512, JSON_THROW_ON_ERROR)
    : null;
if ($postgres && ($postgres['database'] !== 'accretion_wave1_qa'
    || $postgres['username'] !== 'accretion_wave1_qa_role'
    || !in_array($postgres['host'], ['127.0.0.1', 'localhost', '::1'], true))) {
    throw new RuntimeException('Invalid isolated PostgreSQL identity.');
}
if (!is_file($database)) {
    throw new RuntimeException('Create the disposable SQLite file before bootstrap.');
}

putenv('APP_CONFIG_CACHE='.$qaRoot.'/config.php');
putenv('APP_SERVICES_CACHE='.$qaRoot.'/services.php');
putenv('APP_PACKAGES_CACHE='.$qaRoot.'/packages.php');
$app = require __DIR__.'/../../bootstrap/app.php';
$app->addAbsoluteCachePathPrefix($qaRoot);
$app->loadEnvironmentFrom('__wave1_qa_no_environment_file__');
$app->useStoragePath($qaRoot.'/storage');
$app->afterBootstrapping(LoadConfiguration::class, static function ($app) use ($qaRoot, $database, $postgres): void {
    $config = $app['config'];
    $config->set('app.env', 'testing');
    $config->set('app.debug', true);
    $config->set('app.url', 'http://127.0.0.1:8001');
    $config->set('app.key', 'base64:'.base64_encode(hash('sha256', 'wave1-disposable-qa', true)));
    $connectionName = $postgres ? 'pgsql' : 'sqlite';
    $connection = $postgres
        ? array_merge($postgres, ['driver' => 'pgsql', 'charset' => 'utf8', 'prefix' => '', 'schema' => 'public', 'sslmode' => 'prefer'])
        : ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => false];
    $config->set('database.default', $connectionName);
    $config->set('database.connections', [$connectionName => $connection]);
    $config->set('database.redis', []);
    $config->set('cache.default', 'file');
    $config->set('cache.stores.file.path', $qaRoot.'/storage/framework/cache/data');
    $config->set('session.driver', 'file');
    $config->set('session.files', $qaRoot.'/storage/framework/sessions');
    $config->set('session.cookie', 'wave1_isolated_qa');
    $config->set('session.secure', false);
    $config->set('session.domain', null);
    $config->set('view.compiled', $qaRoot.'/storage/framework/views');
    $config->set('logging.default', 'single');
    $config->set('logging.channels.single.path', $qaRoot.'/storage/logs/laravel.log');
    $config->set('queue.default', 'null');
    $config->set('queue.connections', ['null' => ['driver' => 'null']]);
    $config->set('mail.default', 'log');
    $config->set('mail.mailers', ['log' => ['transport' => 'log']]);
    $config->set('services', []);
    $config->set('broadcasting.default', 'log');
    $config->set('filesystems.default', 'local');
    $config->set('filesystems.disks', [
        'local' => ['driver' => 'local', 'root' => $qaRoot.'/storage/app'],
        'public' => ['driver' => 'local', 'root' => $qaRoot.'/storage/app/public', 'url' => '/storage', 'visibility' => 'public'],
    ]);
});
$app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\RegisterProviders::class, static function ($app): void {
    \Illuminate\Support\Facades\Http::fake(static function () {
        throw new RuntimeException('Outbound HTTP is disabled in Wave 1 QA.');
    });
});
$app->booted(static function () use ($app): void {
    if (PHP_SAPI !== 'cli-server' || ($_SERVER['SERVER_PORT'] ?? '') != 8001) {
        return;
    }
    $app['router']->get('/__qa/identity', static function () {
        $connection = app('db')->connection();
        return response()->json(['database' => $connection->getDatabaseName(),
            'role' => $connection->selectOne('SELECT current_user AS role')->role,
            'outbound' => 'disabled', 'connections' => array_keys(config('database.connections'))]);
    });
    $app['router']->get('/__qa/as/{role}', static function ($role) {
        abort_unless(in_array($role, ['super-admin', 'admin', 'owner', 'sales-manager', 'sales-executive', 'operations', 'accounts', 'hr'], true), 404);
        $user = \App\Models\User::where('email', $role.'@wave1.invalid')->firstOrFail();
        auth()->login($user);
        return redirect('/admin/upcoming-follow-up');
    })->middleware('web');
});

return $app;
