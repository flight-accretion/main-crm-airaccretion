@php
    $tabRole = optional(auth()->user()?->userType)->user_type;
    $tabOversight = in_array($tabRole, array_merge(\App\Models\UserType::ADMIN_ROLES,[\App\Models\UserType::HR]), true);
    $tabDepartments = $tabOversight ? ['sales'=>'Sales','operations'=>'Operations'] : [$department => ucfirst($department)];
    if(in_array($tabRole, \App\Models\UserType::ADMIN_ROLES, true)) $tabDepartments['accounts'] = 'Accounts';
    $tabPeriod = ['preset' => $filter['preset'], 'lead_source' => $filter['lead_source'] ?? 'all'];
    if ($filter['preset'] === 'custom') {
        $tabPeriod['from_date'] = $filter['from']->toDateString();
        $tabPeriod['to_date'] = $filter['to']->toDateString();
    }
@endphp
<nav class="kpi-department-tabs" aria-label="KPI department">
    @foreach($tabDepartments as $tabKey=>$tabLabel)
        @php $tabRoute = !$tabOversight && $tabKey === 'operations' ? 'admin.operations.kpi' : 'admin.kpi.index'; @endphp
        <a href="{{ route($tabRoute,array_merge($tabPeriod,['department'=>$tabKey])) }}" {{ $department === $tabKey ? 'aria-current=page' : '' }}>{{ $tabLabel }}</a>
    @endforeach
</nav>
