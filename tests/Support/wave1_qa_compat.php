<?php

$connection = $app['db']->connection();
$assertQa = static function () use ($connection): void {
    $identity = $connection->selectOne('SELECT current_database() AS database, current_user AS role, inet_server_addr()::text AS address');
    if (array_keys(config('database.connections')) !== ['pgsql']
        || $identity->database !== 'accretion_wave1_qa'
        || $identity->role !== 'accretion_wave1_qa_role'
        || !in_array($identity->address, ['127.0.0.1', '127.0.0.1/32', '::1', '::1/128'], true)) {
        throw new RuntimeException('QA compatibility isolation check failed.');
    }
};
$assertQa();
$passengerMigration = '2025_09_08_182000_make_lead_passengers_name_nullable';
if ($connection->table('migrations')->where('migration', '2025_06_24_123012_change_status_to_integer_in_vendors_table')->exists()
    && !$connection->table('migrations')->where('migration', $passengerMigration)->exists()
    && \Illuminate\Support\Facades\Schema::hasTable('lead_passengers')) {
    $column = $connection->selectOne("SELECT data_type, character_maximum_length, is_nullable, column_default
        FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'lead_passengers' AND column_name = 'name'");
    echo json_encode(['migration' => $passengerMigration, 'column_before' => $column,
        'row_count' => $connection->table('lead_passengers')->count()]).PHP_EOL;
    if (!$column || $column->data_type !== 'character varying' || $column->character_maximum_length != 255) {
        throw new RuntimeException('Unexpected passenger name type.');
    }
    $connection->transaction(static function () use ($connection, $assertQa, $passengerMigration): void {
        $assertQa();
        $connection->statement('ALTER TABLE lead_passengers ALTER COLUMN name DROP NOT NULL');
        $assertQa();
        $connection->table('migrations')->insert(['migration' => $passengerMigration,
            'batch' => ((int) $connection->table('migrations')->max('batch')) + 1]);
    });
    $entry = ['migration' => $passengerMigration, 'effect' => 'lead_passengers.name varchar(255) nullable; default preserved', 'applied_at' => gmdate('c')];
    file_put_contents(getenv('CRM_WAVE1_QA_ROOT').'/compatibility.jsonl', json_encode($entry).PHP_EOL, FILE_APPEND | LOCK_EX);
    echo json_encode($entry).PHP_EOL;
    return;
}
$migration = '2025_06_24_123012_change_status_to_integer_in_vendors_table';
if ($connection->table('migrations')->where('migration', $migration)->exists()) {
    echo 'Compatibility migration already applied.'.PHP_EOL;
    return;
}
$column = $connection->selectOne("SELECT data_type, is_nullable, column_default FROM information_schema.columns
    WHERE table_schema = 'public' AND table_name = 'vendors' AND column_name = 'status'");
$values = $connection->select('SELECT status::text AS status, COUNT(*) AS count FROM vendors GROUP BY status');
echo json_encode(['migration' => $migration, 'column_before' => $column, 'value_counts' => $values]).PHP_EOL;
if (!$column || !in_array($column->data_type, ['boolean', 'integer', 'smallint'], true)) {
    throw new RuntimeException('Unexpected vendor status column type.');
}
$connection->transaction(static function () use ($connection, $assertQa, $migration, $column): void {
    $assertQa();
    if ($column->data_type === 'boolean') {
        $connection->statement('ALTER TABLE vendors ALTER COLUMN status DROP DEFAULT');
    }
    $assertQa();
    $connection->statement('ALTER TABLE vendors ALTER COLUMN status TYPE INTEGER USING status::integer');
    $assertQa();
    $connection->statement('ALTER TABLE vendors ALTER COLUMN status SET DEFAULT 1');
    $assertQa();
    $connection->table('migrations')->insert([
        'migration' => $migration,
        'batch' => ((int) $connection->table('migrations')->max('batch')) + 1,
    ]);
});
$entry = ['migration' => $migration, 'applied_at' => gmdate('c'), 'effect' => 'vendors.status integer default 1; nullability preserved'];
file_put_contents(getenv('CRM_WAVE1_QA_ROOT').'/compatibility.jsonl', json_encode($entry).PHP_EOL, FILE_APPEND | LOCK_EX);
echo json_encode($entry).PHP_EOL;
