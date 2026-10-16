@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-tools.css') }}?v=3">
<div class="crm-kpi-tool crm-ops-tool crm-ops-work" data-kpi-tool data-work-details-url="{{ route('admin.operations.work-details') }}" data-work-filters="{{ json_encode(array_merge($filters,['date_filter'=>$filters['date_filter'] ?? ''])) }}">
    <header class="page-header"><h3>Operations Work Done</h3><p>{{ $filters['from_date'] ? \Carbon\Carbon::parse($filters['from_date'])->format('d M Y') : 'All dates' }}@if($filters['to_date'] && $filters['to_date'] !== $filters['from_date']) - {{ \Carbon\Carbon::parse($filters['to_date'])->format('d M Y') }}@endif</p></header>
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form method="GET" action="{{ route('admin.operations.index') }}" class="kt-filter op-work-filter">
        <div><label for="ops-work-date">Date</label><select id="ops-work-date" name="date_filter">
            @foreach(['today'=>'Today','yesterday'=>'Yesterday','monthly'=>'This month','custom'=>'Custom dates',''=>'All dates'] as $value=>$label)<option value="{{ $value }}" {{ (string)$filters['date_filter'] === (string)$value ? 'selected' : '' }}>{{ $label }}</option>@endforeach
        </select></div>
        <div><label for="ops-work-from">From date</label><input id="ops-work-from" type="date" name="from_date" value="{{ $filters['from_date'] }}"></div>
        <div><label for="ops-work-to">To date</label><input id="ops-work-to" type="date" name="to_date" value="{{ $filters['to_date'] }}"></div>
        <div><label for="ops-work-user">Operations employee</label><select id="ops-work-user" name="operations_user_id"><option value="">All employees</option>@foreach($operationUsers as $user)<option value="{{ $user->id }}" {{ $filters['operations_user_id'] === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
        <div class="op-actions"><a class="ti-btn" href="{{ route('admin.operations.index') }}" aria-label="Reset filters" title="Reset filters"><i class="ri-refresh-line"></i></a><button class="ti-btn ti-btn-primary">Filter</button></div>
    </form>
     <section class="op-section">
        <header class="op-section-head"><h2>Per-employee breakdown</h2><small>Unique cases per employee</small></header>
        <table aria-label="Operations work by employee"><thead><tr><th>Employee</th><th>Completed</th><th>In progress</th><th>Pending</th><th>Total</th></tr></thead><tbody>
        @forelse($breakdown as $row)
            <tr><td data-label="Employee">{{ $row['name'] }}</td>@foreach(['completed'=>'Completed','in_progress'=>'In progress','pending'=>'Pending','total'=>'Total'] as $key=>$label)<td data-label="{{ $label }}">{{ $row[$key] }}</td>@endforeach</tr>
        @empty<tr><td colspan="5" class="op-empty">No employee work recorded.</td></tr>@endforelse
        </tbody></table>
    </section>
    <section class="op-section">
        <header class="op-section-head"><h2>Cases worked</h2><small>Current status</small></header>
        <div class="op-work-stats">
        @foreach(['completed'=>'Completed','in_progress'=>'In progress','pending'=>'Pending','total'=>'Total'] as $key=>$label)
            <button type="button" class="op-work-stat op-work-{{ $key }}" data-work-summary="{{ $key }}" data-work-select="{{ $key }}" aria-controls="operations-work-details" aria-pressed="false"><strong>{{ number_format($summary[$key]) }}</strong><span>{{ $label }}</span></button>
        @endforeach
        </div>
        @if($summary['total'] > 0)
        <div class="wd-composition" aria-label="Operations case composition">
            <div class="wd-bar">@foreach(['completed','in_progress','pending'] as $key)<span class="op-work-fill-{{ $key }}" style="width:{{ 100*$summary[$key]/$summary['total'] }}%"></span>@endforeach</div>
            <div class="wd-legend">@foreach(['completed'=>'Completed','in_progress'=>'In progress','pending'=>'Pending'] as $key=>$label)<span>{{ round(100*$summary[$key]/$summary['total'],1) }}% {{ $label }}</span>@endforeach</div>
        </div>
        @else<p class="op-empty" role="status">No employee work recorded for this period.</p>@endif
    </section>
    <section class="op-section">
        <h2>Work by category</h2>
        <div class="op-work-categories">
        @foreach($typeSummary as $type=>$counts)
            <article class="op-work-category"><h2>{{ ucwords(str_replace('_',' ',$type)) }}</h2><button type="button" class="op-category-total" data-work-select="total" data-work-category="{{ $type }}" aria-label="{{ ucwords(str_replace('_',' ',$type)) }} cases worked" aria-controls="operations-work-details" aria-pressed="false"><strong>{{ $counts['total'] }}</strong><small>Cases worked</small></button>
                <dl>@foreach(['completed'=>'Completed','in_progress'=>'In progress','pending'=>'Pending'] as $key=>$label)<div><dt>{{ $label }}</dt><dd><button type="button" class="op-category-count" data-work-select="{{ $key }}" data-work-category="{{ $type }}" aria-label="{{ ucwords(str_replace('_',' ',$type)) }} - {{ $label }}" aria-controls="operations-work-details" aria-pressed="false">{{ $counts[$key] }}</button></dd></div>@endforeach</dl>
            </article>
        @endforeach
        </div>
    </section>
    <section class="op-section op-work-details" id="operations-work-details" hidden aria-labelledby="operations-work-details-title">
        <header class="op-section-head"><h2 id="operations-work-details-title" tabindex="-1">Case details</h2><button type="button" class="ti-btn" data-close-work-details aria-label="Close case details" title="Close case details"><i class="ri-close-line"></i></button></header>
        <div data-work-details-body aria-live="polite"></div>
    </section>
   
    @include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1"></script>
<script src="{{ asset('assets/admin/js/crm/operations-work.js') }}?v=2"></script>
@endpush
