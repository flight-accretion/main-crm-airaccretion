<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Attendance Verification</title>
<link rel="stylesheet" href="{{ asset('assets/admin/css/style.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin/iconfonts/tabler-icons/iconfont/tabler-icons.min.css') }}">
<style>
@font-face{font-family:boxicons;src:url("{{ asset('assets/admin/css/boxicons-AQ5N6WP7.woff2') }}") format('woff2');font-weight:normal;font-style:normal}
body{margin:0;font-family:'Segoe UI',sans-serif;background:#f4f5f7;color:#1b1f27}#sidebar{position:fixed;inset:0 auto 0 0;width:220px;background:white;border-right:1px solid #e4e7ec;padding:22px;box-sizing:border-box}#sidebar a{display:block;padding:9px 0;color:inherit;text-decoration:none;font-size:13px}.main-content{margin-left:220px;min-width:0}.app-header{padding:20px 24px;background:#fff;border-bottom:1px solid #e4e7ec;font-size:13px}.hidden{display:none!important}a{color:#1d5bd8}button,input,select{font:inherit}@media(max-width:767px){#sidebar{display:none}.main-content{margin-left:0}.app-header{padding:16px}}
</style></head><body>
<aside id="sidebar"><strong>Accretion Aviation</strong><nav>
<a class="side-menu__item" href="/admin/hr">HR Dashboard</a>
<a class="side-menu__item" href="/admin/lead">View Leads</a>
<a class="side-menu__item" href="/admin/today-follow-up">Today's Follow-up</a>
<a class="side-menu__item" href="/admin/kpi">Sales KPI</a>
<a class="side-menu__item" href="/admin/attendance/import">Attendance Import</a>
<a class="side-menu__item" href="/admin/attendance/settings">Attendance Settings</a>
</nav></aside><div class="main-content"><header style="position:static;padding:20px 24px;background:white;border-bottom:1px solid #e4e7ec">Workspace / Attendance</header>@yield('content')</div>@stack('scripts')
</body></html>
