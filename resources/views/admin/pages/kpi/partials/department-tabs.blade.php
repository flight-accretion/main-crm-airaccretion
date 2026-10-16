@php
    $tabRole = optional(auth()->user()?->userType)->user_type;
    $tabOversight = in_array($tabRole, array_merge(\App\Models\UserType::ADMIN_ROLES,[\App\Models\UserType::HR]), true);
    $tabDepartments = $tabOversight ? ['sales'=>'Sales','operations'=>'Operations'] : [$department => ucfirst($department)];
    if(in_array($tabRole, \App\Models\UserType::ADMIN_ROLES, true)) $tabDepartments['accounts'] = 'Accounts';
    $tabPeriod = request()->only(['preset','from_date','to_date','lead_source']);
@endphp
<nav class="kpi-department-tabs" aria-label="KPI department">
    @foreach($tabDepartments as $tabKey=>$tabLabel)
        @php $tabRoute = !$tabOversight && $tabKey === 'operations' ? 'admin.operations.kpi' : 'admin.kpi.index'; @endphp
        <a href="{{ route($tabRoute,array_merge($tabPeriod,['department'=>$tabKey])) }}" {{ $department === $tabKey ? 'aria-current=page' : '' }}>{{ $tabLabel }}</a>
    @endforeach
</nav>
