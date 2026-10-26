@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/targets/targets.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/responsive/targets-mobile.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/targets/incentives.css') }}?v=1">
@php
    $months = [1=>'January',2=>'February',3=>'March',4=>'April',5=>'May',6=>'June',7=>'July',8=>'August',9=>'September',10=>'October',11=>'November',12=>'December'];
    $periodLabel = $months[$month].' '.$year;
    $money = fn ($value) => $value === null ? 'Not assigned' : "\u{20B9}".number_format($value,2);
    $initials = fn ($name) => collect(preg_split('/\s+/u',trim($name)))->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part,0,1)))->implode('');
@endphp
<div class="crm-targets crm-incentives" data-base-url="{{ route($indexRoute ?? 'admin.targets.index') }}">
    <header class="target-heading"><div><h1>{{ ($isAccountReadOnly ?? false) ? 'Incentive Report' : 'Incentive' }}</h1><p>Employee incentives &middot; {{ $periodLabel }}</p></div><div class="target-heading-actions"><button type="button" class="target-button primary" data-open="target-export"><i class="ri-download-2-line" aria-hidden="true"></i>Download Incentive</button></div></header>
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="target-filter-surface"><form method="GET" action="{{ route($indexRoute ?? 'admin.targets.incentives') }}" class="target-filter-grid">
        <label>Year<select name="year">@for($i=2020;$i<=now()->year;$i++)<option value="{{ $i }}" {{ $year===$i?'selected':'' }}>{{ $i }}</option>@endfor</select></label>
        <label>Month<select name="month">@foreach($months as $value=>$label)<option value="{{ $value }}" {{ $month===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></label>
        <label>Employee<select name="person_id"><option value="">All employees</option>@foreach($assignableStaff as $person)<option value="{{ $person->id }}" {{ $salesExecutiveId===$person->id?'selected':'' }}>{{ $person->name }}</option>@endforeach</select></label>
        <input type="hidden" name="per_page" value="{{ $perPage }}"><input type="hidden" name="search" value="{{ $search }}">
        <button class="target-button primary" type="submit">Apply Filters</button>
    </form></section>
    <section class="target-table-surface"><div class="target-section-heading"><h2>Incentives &middot; {{ $periodLabel }}</h2><span>{{ $pagination->total() }} employees</span></div>
        <form class="target-table-tools incentive-table-tools" method="GET" action="{{ route($indexRoute ?? 'admin.targets.incentives') }}">
            <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="person_id" value="{{ $salesExecutiveId }}">
            <input type="search" name="search" placeholder="Search employee or role" aria-label="Search employee or role" value="{{ $search }}"><button type="submit" class="target-button" aria-label="Search employees" title="Search employees"><i class="ri-search-line" aria-hidden="true"></i></button>
            <label>Show<select name="per_page" data-incentive-page-size>@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>@endforeach</select></label>
        </form>
        <table class="target-table incentive-table"><thead><tr><th>Employee Name</th><th>Target Amount</th><th>Achieved Sales Amount</th><th>Achievement (%)</th><th>Earned Incentive</th><th>Action</th></tr></thead><tbody>
        @foreach($rows as $row)
            <tr><td data-label="Employee Name"><div class="target-person"><span class="target-avatar">{{ $initials($row['name']) }}</span><strong>{{ $row['name'] }}</strong></div><span class="target-meta">{{ $row['role'] }}</span></td>
            <td data-label="Target Amount">{{ $money($row['target']) }}</td><td data-label="Achieved Sales Amount">{{ $money($row['achieved']) }}</td><td data-label="Achievement (%)">{{ $row['achievement'] === null ? '-' : number_format($row['achievement'],2).'%' }}</td>
            <td data-label="Earned Incentive">@if($row['incentive']!==null)<strong class="target-incentive">{{ $money($row['incentive']) }}</strong><span class="target-meta">{{ $row['basis'] }}</span>@else<span class="target-tag neutral">Not configured</span>@endif</td>
            <td data-label="Action"><button type="button" class="target-button" data-download-person="{{ $row['id'] }}" aria-label="Download incentive for {{ $row['name'] }}" title="Download incentive for {{ $row['name'] }}"><i class="ri-download-2-line" aria-hidden="true"></i></button></td></tr>
        @endforeach
        </tbody></table>
        @if($rows->isEmpty())<p class="target-empty">No matching employees</p>@endif
        <footer class="target-table-footer"><span>{{ $pagination->total() ? $pagination->firstItem().'-'.$pagination->lastItem().' of '.$pagination->total() : '0 employees' }}</span><div class="incentive-pagination">@if($pagination->previousPageUrl())<a class="target-button" href="{{ $pagination->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $pagination->currentPage() }} of {{ $pagination->lastPage() }}</span>@if($pagination->nextPageUrl())<a class="target-button" href="{{ $pagination->nextPageUrl() }}">Next</a>@endif</div></footer>
    </section>
    @include('admin.targets.partials.incentive-download')
    @unless($isAccountReadOnly ?? false)
        <nav class="target-mobile-nav" aria-label="Mobile navigation"><a data-target-bottom="dashboard"><i class="ri-home-4-line" aria-hidden="true"></i>Dashboard</a><a href="{{ route('admin.clients.index') }}"><i class="ri-user-3-line" aria-hidden="true"></i>Leads</a><a data-target-bottom="followup"><i class="ri-time-line" aria-hidden="true"></i>Follow-up</a><a href="{{ route('admin.kpi.index') }}"><i class="ri-bar-chart-line" aria-hidden="true"></i>KPI</a><button id="target-more-toggle" type="button" aria-expanded="false" aria-controls="target-mobile-more"><i class="ri-apps-line" aria-hidden="true"></i>More</button></nav>
        <nav id="target-mobile-more" aria-label="More navigation" hidden></nav>
    @endunless
</div>
<script src="{{ asset('assets/admin/js/crm/targets.js') }}?v=2" defer></script>
@endsection
