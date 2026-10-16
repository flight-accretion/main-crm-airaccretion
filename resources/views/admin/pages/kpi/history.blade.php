@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/history.css') }}?v=1">
@php
    $hasTarget = $rows->contains(fn ($row) => $row['target'] !== null);
    $scored = $rows->whereNotNull('score');
    $latest = $rows->last();
    $money = fn ($value) => $value === null ? 'Not assigned' : "\u{20B9}" . number_format($value, 2);
    $statMoney = fn ($value) => "\u{20B9}" . ($value >= 100000 ? number_format($value/100000,1).'L' : number_format($value,0));
@endphp
<div class="crm-kpi-tool crm-history" data-kpi-tool>
    <div class="hp-page-heading">
        <div><h1>Historic Performance</h1><p>KPI and target history</p></div>
        <button type="button" class="hp-button" data-open-history-download @if($employees->isEmpty()) disabled @endif><i class="ri-download-2-line" aria-hidden="true"></i> Download</button>
    </div>
    @if($errors->any())<div class="hp-error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('admin.kpi.history.index') }}" class="hp-filters">
        <label>Department<select name="department" class="form-control" data-history-department><option value="">All departments</option>@foreach(['sales'=>'Sales','operations'=>'Operations','accounts'=>'Accounts'] as $value=>$label)<option value="{{ $value }}" {{ ($filters['department'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
        <label>Employee<select name="employee_id" class="form-control" data-history-employee>@forelse($employees as $employee)<option value="{{ $employee->id }}" {{ $selectedEmployee?->id === $employee->id ? 'selected' : '' }}>{{ $employee->name }}{{ (int)$employee->status !== 1 ? ' (Inactive)' : '' }}</option>@empty<option value="">No employees</option>@endforelse</select></label>
        <label>From month<input type="month" name="from" class="form-control" value="{{ $from->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></label>
        <label>To month<input type="month" name="to" class="form-control" value="{{ $to->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></label>
        <button class="hp-button hp-primary" type="submit">Apply Filters</button>
    </form>
    @if($selectedEmployee)
        <div class="hp-person"><div><span class="hp-avatar">{{ mb_strtoupper(mb_substr($selectedEmployee->name,0,1)) }}</span><strong>{{ $selectedEmployee->name }}</strong><span>{{ $selectedEmployee->userType?->user_type }}</span></div><span>{{ $from->format('M Y') }} - {{ $to->format('M Y') }}</span></div>
        <div class="hp-stats {{ $hasTarget ? '' : 'hp-two-stats' }}">
            <div class="hp-stat"><span>Average KPI</span><strong>{{ $scored->isEmpty() ? 'N/A' : number_format($scored->avg('score'),1).' / 5' }}</strong><small>Recorded months only</small></div>
            <div class="hp-stat"><span>Latest KPI</span><strong>{{ $latest['score'] === null ? 'N/A' : $latest['score'].' / 5' }}</strong><small>{{ $latest['label'] }} &middot; {{ $latest['source'] }}</small></div>
            @if($hasTarget)
                <div class="hp-stat"><span>Total target</span><strong>{{ $statMoney($rows->sum('target')) }}</strong><small>Assigned months in selected period</small></div>
                <div class="hp-stat"><span>Sales achieved</span><strong>{{ $statMoney($rows->sum('achieved')) }}</strong><small>{{ $rows->sum('target') > 0 ? number_format($rows->sum('achieved')/$rows->sum('target')*100,1).'% of target' : 'No positive target amount' }}</small></div>
            @endif
        </div>
        <section class="hp-section"><div class="hp-section-heading"><div><h2>KPI history</h2><p>Monthly overall score</p></div><span class="hp-badge">Out of 5</span></div><div data-history-chart="kpi" class="hp-chart"></div><div class="hp-chart-foot"><span><b class="hp-dot"></b>Overall KPI</span><span>Missing months are not scored</span></div></section>
        @if($hasTarget)
            <section class="hp-section"><div class="hp-section-heading"><div><h2>Target history</h2><p>Monthly target and sales achieved</p></div><div class="hp-legend"><span><b class="hp-swatch hp-target"></b>Target</span><span><b class="hp-swatch hp-achieved"></b>Achieved</span></div></div><div data-history-chart="target" class="hp-chart"></div><p class="hp-chart-foot">Amounts in INR lakh</p></section>
        @endif
        <section class="hp-section"><div class="hp-section-heading"><h2>Monthly breakdown</h2><span>{{ $rows->count() }} months</span></div>
            <table class="hp-history-table"><thead><tr><th>Month</th><th>KPI score</th><th>Score source</th>@if($hasTarget)<th>Target</th><th>Achieved</th><th>Achievement</th>@endif<th>Period status</th></tr></thead><tbody>
            @foreach($rows->reverse() as $row)
                <tr><td data-label="Month">{{ $row['label'] }}</td><td data-label="KPI score">{{ $row['score'] === null ? 'Not available' : $row['score'].' / 5' }}</td><td data-label="Score source"><span class="hp-badge {{ $row['source'] === 'Finalized' ? 'hp-finalized' : '' }}">{{ $row['source'] }}</span></td>
                @if($hasTarget)<td data-label="Target">{{ $money($row['target']) }}</td><td data-label="Achieved">{{ $row['achieved'] === null ? '-' : $money($row['achieved']) }}</td><td data-label="Achievement">{{ $row['achievement'] === null ? '-' : number_format($row['achievement'],1).'%' }}</td>@endif
                <td data-label="Period status"><span class="hp-badge {{ $row['period_status'] === 'Month to date' ? 'hp-mtd' : '' }}">{{ $row['period_status'] }}</span></td></tr>
            @endforeach
            </tbody></table>
        </section>
        <script type="application/json" data-history-rows>@json($rows->values())</script>
    @else
        <div class="hp-empty">No employees available in this department.</div>
    @endif
    <dialog class="hp-download" data-history-download aria-labelledby="hp-history-download-title">
        <form method="GET" action="{{ route('admin.kpi.history.download') }}">
            <header><h2 id="hp-history-download-title">Download history</h2><button type="button" data-close-history-download class="hp-icon" aria-label="Close"><i class="ri-close-line" aria-hidden="true"></i></button></header>
            <div class="hp-download-body">
                <input type="hidden" name="department" value="{{ $filters['department'] ?? '' }}">
                <label>Download for<select class="form-control" name="scope"><option value="selected">Individual employee</option><option value="all">All employees in department</option></select></label>
                <label data-export-employee>Employee<select class="form-control" name="employee_id">@foreach($employees as $employee)<option value="{{ $employee->id }}" {{ $selectedEmployee?->id === $employee->id ? 'selected' : '' }}>{{ $employee->name }}</option>@endforeach</select></label>
                <div class="hp-download-dates"><label>From month<input type="month" class="form-control" name="from" value="{{ $from->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></label><label>To month<input type="month" class="form-control" name="to" value="{{ $to->format('Y-m') }}" max="{{ now()->format('Y-m') }}" required></label></div>
                <label>Data to include<select class="form-control" name="dataset"><option value="both">KPI and Target history</option><option value="kpi">KPI history</option><option value="target">Target history</option></select></label>
                <fieldset><legend>File type</legend><label><input type="radio" name="format" value="xlsx" checked> Excel (.xlsx)</label><label><input type="radio" name="format" value="csv"> CSV</label></fieldset>
            </div>
            <footer><button type="button" class="hp-button" data-close-history-download>Cancel</button><button class="hp-button hp-primary" type="submit"><i class="ri-download-2-line" aria-hidden="true"></i> Download</button></footer>
        </form>
    </dialog>
    @include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1" defer></script>
<script src="{{ asset('assets/admin/js/crm/kpi-history.js') }}?v=1" defer></script>
@endpush
