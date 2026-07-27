<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$leadId = 'ac7ff88b-8ec2-45e7-95b7-eb2e84a05e69';
$target = now()->addMinutes(2)->setSeconds(0)->toDateTimeString();
$db = $app['db'];
$count = $db->table('lead_rides')->where('lead_id', $leadId)->update(['from_date' => $target]);
$updatedRides = $db->table('lead_rides')->where('lead_id', $leadId)->get(['id','from_date','is_tba']);
echo "updated={$count} target={$target}\n";
foreach ($updatedRides as $r) {
    echo $r->id . ' | ' . $r->from_date . ' | is_tba=' . $r->is_tba . PHP_EOL;
}
