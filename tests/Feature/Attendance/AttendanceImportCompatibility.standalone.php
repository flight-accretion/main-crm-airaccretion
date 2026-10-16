<?php

// Standalone: php tests/Feature/Attendance/AttendanceImportCompatibility.standalone.php
// Uses only an in-memory database; never bootstraps the CRM or reads its .env.
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

use App\Http\Controllers\AttendanceImportController;
use App\Models\User;
use App\Services\Attendance\AttendanceImportParser;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Support\Facades\Facade;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;

$container = new Container();
Container::setInstance($container);
Facade::setFacadeApplication($container);
$database = new Manager($container);
$database->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
$database->setAsGlobal();
$database->bootEloquent();
$container->instance('db', $database->getDatabaseManager());
$container->instance('validator', new Factory(new Translator(new ArrayLoader(), 'en'), $container));
$database->schema()->create('attendance_user_mappings', function ($table) {
    $table->string('paycode');
    $table->string('user_id');
});

$failed = 0;
function same($expected, $actual): void {
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function check(string $name, callable $test): void {
    global $failed;
    try { $test(); echo "PASS {$name}\n"; }
    catch (Throwable $e) { $failed++; echo "FAIL {$name}: {$e->getMessage()}\n"; }
}
function invoke(object $target, string $method, ...$arguments) {
    return (new ReflectionMethod($target, $method))->invoke($target, ...$arguments);
}
function parseAttendance(array $rows): array {
    return invoke(new AttendanceImportParser(), 'parseRows', $rows, Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'));
}

check('native labeled name and shifted headers ignore lunch', function () {
    $parsed = parseAttendance([
        ['Paycode:-', '', '', '1', 'Name:-', 'CHARUL GARG', '', 'Department:-', 'Sales'],
        ['Date', '', '', 'Day', '', 'In', 'Lunch Out', 'Lunch In', 'Out', '', '', '', '', 'Status'],
        ['02/09/2026', '', '', 'Wed', '', '10:15', '13:00', '14:00', '19:10', '', '', '', '', 'MIS'],
    ]);
    same('CHARUL GARG', $parsed['employees'][0]['file_name']);
    same('2026-09-02', $parsed['rows'][0]['attendance_date']);
    same('10:15:00', $parsed['rows'][0]['in_time']);
    same('19:10:00', $parsed['rows'][0]['out_time']);
    same('MIS', $parsed['rows'][0]['raw_status']);
});
check('flat six columns group names without real paycodes and retain all statuses', function () {
    $parsed = parseAttendance([
        ['Date', 'Day', 'Name', 'In', 'Out', 'Status'],
        ['01/09/2026', 'Tue', ' Charul  Garg ', '10:15', '19:10', 'P'],
        ['02/09/2026', 'Wed', 'CHARUL GARG', '', '', 'WO'],
        ['03/09/2026', 'Thu', 'Charul Garg', '', '', 'HLD'],
        ['04/09/2026', 'Fri', 'Charul Garg', '', '', 'Leave'],
        ['31/08/2026', 'Mon', 'Charul Garg', '', '', 'A'],
    ]);
    same(1, count($parsed['employees']));
    same(null, $parsed['employees'][0]['paycode']);
    same(4, count($parsed['rows']));
    same(['P', 'WO', 'HLD', 'Leave'], array_column($parsed['rows'], 'raw_status'));
    same(1, $parsed['outside_range_rows']);
    same(true, isset($parsed['employees'][0]['mapping_key']));
});
check('invalid DD/MM date must not roll into following month', function () {
    $parsed = parseAttendance([
        ['Paycode:-', '', '', '1', 'Charul Garg'],
        ['Date', 'Day', 'In', 'Out', 'Status'],
        ['31/09/2026', 'Wed', '', '', 'A'],
    ]);
    same([], $parsed['rows']);
});
check('conflicting employee day rows require correction before import', function () {
    try {
        parseAttendance([
            ['Date', 'Day', 'Name', 'In', 'Out', 'Status'],
            ['01/09/2026', 'Tue', 'Charul Garg', '10:15', '19:10', 'P'],
            ['01/09/2026', 'Tue', 'CHARUL GARG', '', '', 'A'],
        ]);
    } catch (Illuminate\Validation\ValidationException $e) { return; }
    throw new RuntimeException('Conflicting day rows accepted');
});
check('download templates use exact six columns and round trip', function () {
    $rows = invoke(new AttendanceImportController(), 'sampleRows');
    same(['Date', 'Day', 'Name', 'In', 'Out', 'Status'], $rows[0]);
    same(6, count($rows[1]));
    same(true, count(parseAttendance($rows)['rows']) > 0);
});
check('missing posted mapping rejected even when saved paycode exists', function () use ($database) {
    $database->table('attendance_user_mappings')->insert(['paycode' => '1', 'user_id' => 'saved-user']);
    try {
        invoke(new AttendanceImportController(), 'resolveMappings', [['paycode' => '1', 'file_name' => 'Charul']], []);
    } catch (Illuminate\Validation\ValidationException $e) { return; }
    throw new RuntimeException('Missing mapping was accepted');
});
check('ambiguous names require selection; synthetic keys cannot use saved mappings', function () use ($database) {
    $database->table('attendance_user_mappings')->insert(['paycode' => 'name-collision', 'user_id' => 'one']);
    $employees = [['paycode' => null, 'mapping_key' => 'name-collision', 'file_name' => 'Charul Garg']];
    $users = collect([(new User())->forceFill(['id' => 'one', 'name' => 'Charul Garg']), (new User())->forceFill(['id' => 'two', 'name' => 'CHARUL GARG'])]);
    $result = invoke(new AttendanceImportController(), 'buildEmployeePreview', $employees, $users);
    same(null, $result[0]['user_id']);
    $result = invoke(new AttendanceImportController(), 'buildEmployeePreview', $employees, $users->take(1));
    same('one', $result[0]['user_id']);
});

if (isset($argv[1])) {
    check('actual Bio Manager workbook', function () use ($argv) {
        $parsed = (new AttendanceImportParser())->parse($argv[1], Carbon::parse('2020-01-01'), Carbon::parse('2030-12-31'));
        same('CHARUL GARG', $parsed['employees'][0]['file_name']);
        same(true, count($parsed['rows']) > 0);
        echo 'Native workbook: ' . count($parsed['employees']) . ' employees, ' . count($parsed['rows']) . " rows\n";
    });
}
if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit($failed ? 1 : 0);
}
