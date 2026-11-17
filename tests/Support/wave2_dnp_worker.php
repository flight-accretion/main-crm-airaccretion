<?php

$app = require __DIR__.'/wave1_qa_bootstrap.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$identity = $app['db']->selectOne('SELECT current_database() AS db, current_user AS role');
if ($identity->db !== 'accretion_wave1_qa' || $identity->role !== 'accretion_wave1_qa_role') {
    throw new RuntimeException('Unexpected QA database.');
}
$app['db']->statement("SET application_name = 'wave2-dnp-concurrency'");
$app['db']->statement("SET lock_timeout = '20s'");
$exitCode = $kernel->call('lead:cancel-aged-dnp');
$output = $kernel->output();
if ($exitCode !== 0 || !preg_match('/; cancelled: ([01])\b/', $output, $matches)) {
    throw new RuntimeException('Unexpected DNP command result: '.$output);
}
$cancelled = $matches[1] === '1';
echo json_encode(['pid' => $app['db']->selectOne('SELECT pg_backend_pid() AS pid')->pid,
    'cancelled' => $cancelled], JSON_THROW_ON_ERROR);
