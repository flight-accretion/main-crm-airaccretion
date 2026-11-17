<?php

$app = require __DIR__.'/wave1_qa_bootstrap.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$identity = $app['db']->selectOne('SELECT current_database() AS db, current_user AS role');
if ($identity->db !== 'accretion_wave1_qa' || $identity->role !== 'accretion_wave1_qa_role') throw new RuntimeException('Unexpected QA identity');
$app->instance('env', 'testing');
$app['db']->statement("SET application_name = 'wave2-attendance-concurrency'");
$app['db']->statement("SET lock_timeout = '20s'");
[$recordId, $userId, $version, $status] = array_slice($argv, 1);
$app['auth']->guard()->setUser(\App\Models\User::findOrFail($userId));
$request = \Illuminate\Http\Request::create(route('admin.attendance.corrections.store', $recordId), 'POST', [], [], [],
    ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
    json_encode(['version' => $version, 'raw_status' => $status, 'reason' => 'Concurrent correction evidence']));
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['pid' => $app['db']->selectOne('SELECT pg_backend_pid() AS pid')->pid,
    'status' => $response->getStatusCode(), 'raw_status' => $status,
    'message' => json_decode($response->getContent(), true)['message'] ?? null]);
