<?php
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$directory = $argv[1] ?? sys_get_temp_dir().'/crm-operations-mobile';
if (!is_dir($directory)) mkdir($directory, 0777, true);
config(['view.compiled' => $directory.'/compiled', 'cache.default' => 'array']);
if (!is_dir(config('view.compiled'))) mkdir(config('view.compiled'));
app()->forgetInstance('blade.compiler');
app('view.engine.resolver')->forget('blade');
$header = file_get_contents($root.'/resources/views/admin/layouts/header.blade.php');
$start = strpos($header, '<aside class="app-sidebar"');
$sidebar = substr($header, $start, strpos($header, '</aside>', $start) - $start + 8);
$roles = ['super' => App\Models\UserType::SUPER_ADMIN, 'admin' => App\Models\UserType::ADMIN, 'hr' => App\Models\UserType::HR];
foreach (array_merge(App\Models\UserType::SALES_ROLES, App\Models\UserType::OPERATIONS_ROLES, App\Models\UserType::ACCOUNTS_ROLES) as $role) {
    $roles[strtolower(str_replace(' ', '-', $role))] = $role;
}
foreach ($roles as $name => $userType) {
    foreach (['operations' => '/admin/operations/queue/review', 'hr' => '/admin/hr'] as $area => $url) {
        $actor = new App\Models\User(['name' => 'Preview User']);
        $actor->id = 'actor';
        $actor->setRelation('userType', new App\Models\UserType(['user_type' => $userType]));
        auth()->setUser($actor);
        $request = Illuminate\Http\Request::create('http://ops-nav.test'.$url);
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(fn () => $route);
        $currentRoute = new ReflectionProperty(Illuminate\Routing\Router::class, 'current');
        $currentRoute->setAccessible(true);
        $currentRoute->setValue(app('router'), $route);
        app()->instance('request', $request);
        $isHr = $userType === App\Models\UserType::HR;
        $adminRoles = App\Models\UserType::ADMIN_ROLES;
        $operationsRoles = App\Models\UserType::OPERATIONS_ROLES;
        $canAccess = fn ($role, $roles) => in_array($role, $roles, true);
        $menu = Illuminate\Support\Facades\Blade::render($sidebar, compact('userType'));
        $nav = view('admin.layouts.partials.operations-mobile-nav')->render();
        if (!str_contains($nav, 'data-operations-mobile-navigation')) throw new RuntimeException('Missing navigation: '.$name.'/'.$area);
        $html = '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<link rel="stylesheet" href="/assets/admin/css/crm/navigation.css"><link rel="stylesheet" href="/assets/admin/iconfonts/RemixIcons/fonts/remixicon.css">'
            .'<style>body{margin:0;font:14px Arial;background:#f4f5f7}.app-sidebar{width:260px}.main-content{padding:20px}.header-logo img{width:80px} @media(max-width:767px){.app-sidebar{display:none}}</style></head><body class="crm-operations-mobile-page">'
            .$menu.'<header class="app-header"><button class="sidemenu-toggle">Sidebar</button></header><main class="main-content"><h1>Operations</h1><button id="outside">Outside</button><nav class="kt-mobile-nav">Old navigation</nav><nav class="hr-mobile-nav">Old HR navigation</nav><nav class="target-mobile-nav">Old targets navigation</nav></main>'
            .'<script src="/assets/admin/js/crm/navigation.js"></script>'.$nav.'</body></html>';
        file_put_contents($directory.'/'.$name.'-'.$area.'.html', $html);
        echo 'PASS role/page rendering '.$name.'/'.$area.PHP_EOL;
    }
}
