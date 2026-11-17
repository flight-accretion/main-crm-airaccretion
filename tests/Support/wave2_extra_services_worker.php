<?php

// Child of the opt-in PostgreSQL acceptance test. Never boot the default application.
$app = require __DIR__.'/wave1_qa_bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$identity = $app['db']->selectOne('SELECT current_database() AS db, current_user AS role');
if ($identity->db !== 'accretion_wave1_qa' || $identity->role !== 'accretion_wave1_qa_role') {
    throw new RuntimeException('Unexpected QA identity.');
}
$app->instance('env', 'testing');
$app['db']->statement("SET application_name = 'wave2-extra-concurrency'");
$app['db']->statement("SET lock_timeout = '20s'");
[$leadId, $userId, $extraId] = array_slice($argv, 1);
$app['auth']->guard()->setUser(\App\Models\User::findOrFail($userId));
$request = \Illuminate\Http\Request::create(
    route('admin.leads.extra-services.update', $leadId),
    'PUT',
    [],
    [],
    [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    json_encode(['selection_submitted' => true, 'quote_version' => hash('sha256', ''),
        'extra_service_ids' => [$extraId]], JSON_THROW_ON_ERROR)
);
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode([
    'pid' => $app['db']->selectOne('SELECT pg_backend_pid() AS pid')->pid,
    'status' => $response->getStatusCode(),
    'extra_id' => $extraId,
    'body' => json_decode($response->getContent(), true),
], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
