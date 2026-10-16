@extends('admin.layouts.header')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/dashboards/kpi-dashboard.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/responsive/kpi-dashboard-mobile.css') }}?v=1">
@php
    $team = collect($team ?? [])->values();
    $matrixRows = $team->flatMap(fn ($row) => collect($row['metrics'] ?? []))->groupBy('code')->map(fn ($metrics) => $metrics->first())->sortBy('sort_order');
    $departmentTitle = ['sales' => ' Sales', 'operations' => 'Operations', 'accounts' => 'Accounts'][$department ?? 'sales'] ?? ucfirst($department);
    $presentations = $team->map(function ($row) {
        $configured = $row['configured'] ?? false;
        $score = $row['overall_score'] ?? null;
        $weakest = collect($row['metrics'] ?? [])->sort(fn ($a, $b) => ($a['score'] <=> $b['score']) ?: ($b['weightage'] <=> $a['weightage']))->first();
        return [
            'initials' => collect(preg_split('/\\s+/u', trim($row['user']->name ?? '')))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode(''),
            'weakest' => $weakest['name'] ?? 'Not configured',
            'status' => !$configured ? 'Not configured' : ($score >= 4 ? 'On track' : ($score >= 3 ? 'Watch' : 'Below')),
            'tone' => !$configured ? 'neutral' : ($score >= 4 ? 'green' : ($score >= 3 ? 'amber' : 'red')),
        ];
    });
    $metricSearch = $matrixRows->map(function ($meta, $code) use ($team) {
        return collect([$meta['name'] ?? '', $meta['description'] ?? '', $meta['rating_labels'] ?? []])
            ->merge($team->map(fn ($row) => collect($row['metrics'] ?? [])->firstWhere('code', $code))->map(fn ($metric) => [$metric['score'] ?? '', $metric['display_actual'] ?? '']))
            ->flatten()->implode(' ');
    });
@endphp
<div class="crm-kpi-dashboard">
    <header class="kpi-heading">
        <div><h1>KPI Dashboard</h1><p>{{ $departmentTitle }} · {{ $filter['from']->format('d M Y') }} - {{ $filter['to']->format('d M Y') }}</p></div>
        <div class="kpi-heading-actions">
            @if($showOperationsManualEntry ?? false)<button type="button" id="operationsKpiManualEntryButton" class="kpi-button"><i class="ri-edit-line" aria-hidden="true"></i>Enter Manual KPI</button>@endif
            <button type="button" id="kpiImproveAllButton" class="kpi-button"><i class="ri-line-chart-line" aria-hidden="true"></i>How to Improve Score</button>
        </div>
    </header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @include('admin.pages.kpi.partials.department-tabs')
    <section class="kpi-filters" aria-label="KPI filters">
        <form
            method="GET"
            action="{{ $filterAction ?? route('admin.kpi.index') }}"
            class="kpi-filter-form"
        >
            @if(!empty($filter['user_id']))
                <input type="hidden" name="user_id" value="{{ $filter['user_id'] }}">
            @endif
            <input type="hidden" name="department" value="{{ $department }}">

            <div class="kpi-filter-field">

                <label class="ti-form-label">
                    Date
                </label>

                <select
                    name="preset"
                    id="kpi-date-type"
                    class="form-control"
                >

                    <option
                        value="this_month"
                        {{ ($filter['preset'] ?? 'this_month') === 'this_month' ? 'selected' : '' }}
                    >
                        This Month
                    </option>

                    <option
                        value="today"
                        {{ ($filter['preset'] ?? '') === 'today' ? 'selected' : '' }}
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        {{ ($filter['preset'] ?? '') === 'yesterday' ? 'selected' : '' }}
                    >
                        Yesterday
                    </option>

                    <option
                        value="custom"
                        {{ ($filter['preset'] ?? '') === 'custom' ? 'selected' : '' }}
                    >
                        Custom Date
                    </option>

                </select>

            </div>


            <div
                class="kpi-filter-field kpi-custom-date-field"
                style="{{ ($filter['preset'] ?? 'this_month') === 'custom' ? '' : 'display:none' }}"
            >

                <label class="ti-form-label">
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    @if(($filter['preset'] ?? 'this_month') !== 'custom') disabled @endif
                    class="form-control"
                    value="{{ request('from_date', $filter['from']->toDateString()) }}"
                >

            </div>


            <div
                class="kpi-filter-field kpi-custom-date-field"
                style="{{ ($filter['preset'] ?? 'this_month') === 'custom' ? '' : 'display:none' }}"
            >

                <label class="ti-form-label">
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    @if(($filter['preset'] ?? 'this_month') !== 'custom') disabled @endif
                    class="form-control"
                    value="{{ request('to_date', $filter['to']->toDateString()) }}"
                >

            </div>


            <div class="kpi-filter-field">

                <button
                    type="submit"
                    class="ti-btn ti-btn-primary w-full"
                >
                    Filter
                </button>

            </div>

        </form>
    </section>
    <section class="kpi-summary" aria-label="Team summary">
        <header class="kpi-section-heading"><h2>Team summary</h2><span>{{ $team->count() }} people</span></header>
        <table class="kpi-summary-table"><thead><tr><th>Person</th><th>Overall KPI</th><th>Weakest metric</th><th>Trend</th><th>Status</th></tr></thead><tbody>
            @forelse($team as $personIndex => $row)
                @php $presentation = $presentations[$personIndex]; @endphp
                <tr class="{{ $personIndex === 0 ? 'is-selected' : '' }}" data-select-person="{{ $personIndex }}">
                    <td><button type="button" class="kpi-person-select" data-select-person="{{ $personIndex }}" aria-pressed="{{ $personIndex === 0 ? 'true' : 'false' }}"><span class="kpi-avatar">{{ $presentation['initials'] }}</span><strong>{{ $row['user']->name ?? '-' }}</strong></button></td>
                    <td>@if($row['configured'] ?? false)<span class="kpi-score-summary"><span class="kpi-tag kpi-{{ $presentation['tone'] }}">{{ (int) ($row['overall_score'] ?? 1) }}/5</span><meter class="kpi-meter-{{ $presentation['tone'] }}" min="0" max="5" value="{{ (int) ($row['overall_score'] ?? 1) }}">{{ (int) ($row['overall_score'] ?? 1) }}/5</meter></span>@else<span class="kpi-tag kpi-neutral">Not configured</span>@endif</td>
                    <td>{{ $presentation['weakest'] }}</td><td class="kpi-muted">N/A</td><td><span class="kpi-tag kpi-{{ $presentation['tone'] }}">{{ $presentation['status'] }}</span></td>
                </tr>
            @empty<tr><td colspan="5" class="kpi-empty">No KPI users available</td></tr>@endforelse
        </tbody></table>
    </section>
    <section class="kpi-details" aria-label="Metric detail">
        <header class="kpi-section-heading"><h2>Metric detail</h2><span>{{ $filter['from']->format('d M') }} - {{ $filter['to']->format('d M Y') }}</span></header>
        <div class="kpi-people" aria-label="Select employee">
            @foreach($team as $personIndex => $row)
                <button type="button" class="kpi-person-chip" data-select-person="{{ $personIndex }}" aria-pressed="{{ $personIndex === 0 ? 'true' : 'false' }}"><span class="kpi-avatar">{{ $presentations[$personIndex]['initials'] }}</span>{{ $row['user']->name ?? '-' }}<span class="kpi-tag kpi-{{ $presentations[$personIndex]['tone'] }}">{{ ($row['configured'] ?? false) ? ((int) ($row['overall_score'] ?? 1) . '/5') : 'N/A' }}</span></button>
            @endforeach
        </div>
        <div class="kpi-search-tools"><label>Search<input type="search" id="kpi-dashboard-search" placeholder="Search scope, score, result, employee"></label><label>Rows<select id="kpi-dashboard-page-size"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="all">All</option></select></label><span id="kpi-dashboard-count" aria-live="polite"></span></div>
        @forelse($team as $personIndex => $row)
            @include('admin.pages.kpi.partials.person-metrics', ['presentation' => $presentations[$personIndex]])
        @empty<p class="kpi-empty">No KPI template has been assigned.</p>@endforelse
        <nav id="kpi-dashboard-pagination" class="kpi-pagination" aria-label="Metric pagination"></nav>
    </section>
    <nav class="kpi-mobile-nav" aria-label="Mobile navigation"><a data-kpi-bottom="dashboard"><i class="ri-home-4-line" aria-hidden="true"></i><span>Dashboard</span></a><a href="{{ route('admin.clients.index') }}"><i class="ri-user-3-line" aria-hidden="true"></i><span>Leads</span></a><a data-kpi-bottom="followup"><i class="ri-time-line" aria-hidden="true"></i><span>Follow-up</span></a><a href="{{ route('admin.kpi.index') }}" aria-current="page"><i class="ri-bar-chart-line" aria-hidden="true"></i><span>KPI</span></a><button type="button" id="kpi-more-toggle" aria-controls="kpi-mobile-more" aria-expanded="false"><i class="ri-apps-line" aria-hidden="true"></i><span>More</span></button></nav>
    <nav class="kpi-mobile-more" id="kpi-mobile-more" aria-label="More navigation" hidden></nav>
</div>
<script type="application/json" id="kpiImprovementData">@json($improvementUsers ?? [])</script>
@include('admin.pages.kpi.partials.metric-score-modal')
@if($showOperationsManualEntry ?? false)
    @include('admin.pages.kpi.partials.operations-manual-entry-modal')
@endif
<script src="{{ asset('assets/admin/js/crm/kpi-dashboard.js') }}?v=2" defer></script>
@endsection
