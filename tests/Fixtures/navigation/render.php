<?php
$root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory=$argv[1] ?? sys_get_temp_dir().'/crm-navigation';if(!is_dir($directory))mkdir($directory,0777,true);
config(['view.compiled'=>$directory.'/compiled','cache.default'=>'array']);if(!is_dir(config('view.compiled')))mkdir(config('view.compiled'));
app()->forgetInstance('blade.compiler');app('view.engine.resolver')->forget('blade');
$header=file_get_contents($root.'/resources/views/admin/layouts/header.blade.php');
$start=strpos($header,'<aside class="app-sidebar"');$sidebar=substr($header,$start,strpos($header,'</aside>',$start)-$start+8);
$roles=['hr'=>App\Models\UserType::HR,'super'=>App\Models\UserType::SUPER_ADMIN,'admin'=>App\Models\UserType::ADMIN];
foreach(array_merge(App\Models\UserType::SALES_ROLES,App\Models\UserType::OPERATIONS_ROLES,App\Models\UserType::ACCOUNTS_ROLES) as $role)$roles[strtolower(str_replace(' ','-',$role))]=$role;
foreach($roles as $key=>$userType){
    $actor=new App\Models\User(['name'=>'Preview User']);$actor->id='actor';$actor->setRelation('userType',new App\Models\UserType(['user_type'=>$userType]));auth()->setUser($actor);
    $request=Illuminate\Http\Request::create('http://nav.test/admin/attendance/settings');$request->setUserResolver(fn()=>$actor);app()->instance('request',$request);
    $html=Illuminate\Support\Facades\Blade::render($sidebar,compact('userType'));
    $html=str_replace(['http://127.0.0.1:8000','http://localhost'],['http://nav.test','http://nav.test'],$html);
    $doc='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/admin/iconfonts/RemixIcons/fonts/remixicon.css"><link rel="stylesheet" href="/assets/admin/css/crm/navigation.css"><style>body{margin:0;background:#f4f5f7;font:14px Arial}.app-sidebar{width:260px;background:white;min-height:900px}.main-sidebar-header{padding:20px}.header-logo img{width:125px}.header-logo img:not(:first-child){display:none}.slide-left,.slide-right{display:none}ul{list-style:none;padding:0;margin:0}a{color:#34465c;text-decoration:none}#test-more{display:block;padding:12px}#test-more-panel{padding:16px;background:white}.crm-navigation-ready #test-more-panel{max-width:400px}.test-more-button{margin:12px}</style></head><body>'.$html.'<button class="test-more-button" id="test-more" aria-controls="test-more-panel" aria-expanded="false">More</button><nav id="test-more-panel" hidden></nav><script>document.getElementById("test-more").onclick=()=>{const panel=document.getElementById("test-more-panel");panel.hidden=!panel.hidden;};</script></body></html>';
    file_put_contents($directory.'/'.$key.'.html',$doc);echo 'RENDER '.$key.PHP_EOL;
    $department=in_array($userType,App\Models\UserType::OPERATIONS_ROLES,true)?'operations':(in_array($userType,App\Models\UserType::ACCOUNTS_ROLES,true)?'accounts':'sales');
    $request=Illuminate\Http\Request::create('http://nav.test/admin/kpi','GET',['preset'=>'custom','from_date'=>'2026-09-01','to_date'=>'2026-09-30','lead_source'=>'manual','user_id'=>'employee-to-reset']);app()->instance('request',$request);
    $filter=['preset'=>'custom','lead_source'=>'manual','from'=>\Carbon\Carbon::parse('2026-09-01'),'to'=>\Carbon\Carbon::parse('2026-09-30')];
    $tabs=Illuminate\Support\Facades\Blade::render(file_get_contents($root.'/resources/views/admin/pages/kpi/partials/department-tabs.blade.php'),compact('department','filter'));
    $dom=new DOMDocument();@$dom->loadHTML($tabs);$links=$dom->getElementsByTagName('a');$labels=[];
    foreach($links as $link){$labels[]=$link->textContent;parse_str(parse_url($link->getAttribute('href'),PHP_URL_QUERY),$params);if(($params['from_date']??'')!=='2026-09-01'||isset($params['user_id']))throw new RuntimeException('Department tab lost filters or retained employee.');}
    $expected=in_array($userType,App\Models\UserType::ADMIN_ROLES,true)?['Sales','Operations','Accounts']:($userType===App\Models\UserType::HR?['Sales','Operations']:[ucfirst($department)]);
    if($labels!==$expected)throw new RuntimeException('Department access changed for '.$userType);
    echo 'PASS department tabs '.$key.PHP_EOL;
}
