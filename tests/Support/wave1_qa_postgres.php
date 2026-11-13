<?php

require __DIR__.'/../../vendor/autoload.php';
$environment = Dotenv\Dotenv::parse(file_get_contents(__DIR__.'/../../.env'));
$host = $environment['DB_HOST'] ?? '';
$port = $environment['DB_PORT'] ?? '5432';
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || !ctype_digit((string) $port)) {
    throw new RuntimeException('Local loopback PostgreSQL is required.');
}
$admin = new PDO(
    "pgsql:host={$host};port={$port};dbname=".$environment['DB_DATABASE'].';connect_timeout=5',
    $environment['DB_USERNAME'],
    $environment['DB_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$admin->exec('BEGIN READ ONLY');
$identity = $admin->query("SELECT current_database() AS database, inet_server_addr()::text AS address,
    inet_server_port() AS port, current_setting('transaction_read_only') AS read_only,
    rolcreatedb, rolcreaterole FROM pg_roles WHERE rolname = current_user")->fetch(PDO::FETCH_ASSOC);
$admin->rollBack();
echo json_encode($identity).PHP_EOL;
if (($argv[1] ?? '') !== 'create') {
    exit(0);
}
if (!$identity['rolcreatedb'] || !$identity['rolcreaterole']) {
    throw new RuntimeException('Both CREATEDB and CREATEROLE privileges are required.');
}
$database = 'accretion_wave1_qa';
$role = 'accretion_wave1_qa_role';
$check = $admin->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
$check->execute([$database]);
$roleCheck = $admin->prepare('SELECT 1 FROM pg_roles WHERE rolname = ?');
$roleCheck->execute([$role]);
if ($check->fetchColumn() || $roleCheck->fetchColumn()) {
    throw new RuntimeException('QA database or role already exists; refusing to overwrite it.');
}
$root = __DIR__.'/.wave1-qa-runtime';
if (!is_file($root.'/ISOLATED_WAVE1_QA')) {
    throw new RuntimeException('Marked QA directory is required.');
}
$secret = ['host' => $host, 'port' => $port, 'database' => $database, 'username' => $role, 'password' => bin2hex(random_bytes(32))];
if (file_put_contents($root.'/postgres-secret.json', json_encode($secret), LOCK_EX) === false) {
    throw new RuntimeException('Unable to store the ignored QA secret.');
}
$admin->exec('CREATE ROLE '.$role.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION PASSWORD '.$admin->quote($secret['password']));
$admin->exec('CREATE DATABASE '.$database.' OWNER '.$role);
$admin->exec('REVOKE CONNECT ON DATABASE '.$database.' FROM PUBLIC');
echo 'Created isolated QA database and restricted QA role. Password stored only in ignored local secret.'.PHP_EOL;
