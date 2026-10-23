<?php
$root=dirname(__DIR__,3); require $root.'/vendor/autoload.php'; $app=require $root.'/bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class,function($app) {
    $app['config']->set('database.default','fixture'); $app['config']->set('database.connections.fixture',['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
    $app['config']->set('cache.default','array'); $app['config']->set('session.driver','array');
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\URL::forceRootUrl('http://crm.test');
$dir=$argv[1] ?? sys_get_temp_dir().'/ride-status-presentation'; if(!is_dir($dir)) mkdir($dir,0777,true);
$ridesData=require __DIR__.'/cards.php'; $tableProductNames=[$ridesData[0]['lead_id']=>'Private Plane Ride'];
$cards=view('admin.account.rides.partials.status-cards',compact('ridesData','tableProductNames'))->render();
$html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/admin/css/style.css"><link rel="stylesheet" href="/assets/admin/css/crm/ride-status.css"><style>body{margin:0;background:#f4f5f7;font-family:Arial,sans-serif}main{padding:24px;margin-left:224px}@media(max-width:767px){main{margin:0;padding:14px}}</style></head><body><main class="crm-ride-status">'.$cards.'</main></body></html>';
file_put_contents($dir.'/index.html',$html); echo 'PASS synthetic Ride Status cards rendered.'.PHP_EOL;
