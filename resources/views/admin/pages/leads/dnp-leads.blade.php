@extends('admin.layouts.header')
@section('content')
@php $tableProductNames = app(\App\Services\LeadTableProducts::class)->forLeads($dnpLeads); @endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/lead-queues.css') }}?v=1">
<div class="crm-kpi-tool crm-lead-queue" data-kpi-tool data-lead-queue>
    @php($isHr=auth()->user()->userType->user_type === \App\Models\UserType::HR)
    @php($isExecutive=auth()->user()->userType->user_type === \App\Models\UserType::SALES_EXECUTIVE)
    @php($showStaffBreakdown=in_array(auth()->user()->userType->user_type,array_merge(\App\Models\UserType::ADMIN_ROLES,[\App\Models\UserType::HR,\App\Models\UserType::SALES_MANAGER,\App\Models\UserType::SENIOR_SALES_MANAGER]),true))
    <header class="lq-heading"><div><h3>DNP Leads</h3><p>Did-not-pick leads awaiting a retry call</p></div>
        @unless($isHr)<button type="button" id="export-dnp" class="ti-btn" data-export-dnp="{{ route('admin.leads.dnp.export') }}"><i class="ri-download-line"></i> Export</button>@endunless
    </header>
    <div class="lq-stats">
        <div class="lq-stat lq-total"><strong>{{ number_format($queueSummary['total']) }}</strong><span>Total DNP leads</span></div>
        <div class="lq-stat lq-hot"><strong>{{ $queueSummary['oldest'] ?? '-' }} <small>days</small></strong><span>Oldest lead</span></div>
        <div class="lq-stat lq-today"><strong>{{ $queueSummary['scheduled'] }}</strong><span>Retry scheduled</span></div>
        <div class="lq-stat lq-aging"><span>Lead age distribution</span>
        @foreach($queueSummary['buckets'] as $bucket=>$count)<div class="lq-age-line"><span>{{ $bucket }} days</span><b>{{ $queueSummary['percentages'][$bucket] }}%</b><small>{{ $count }} leads</small></div>@endforeach
        @if($queueSummary['unknown'])<small>{{ $queueSummary['unknown'] }} with unknown created date</small>@endif
        </div>
    </div>
    <form method="GET" action="{{ route('admin.leads.dnp') }}" id="filter-form" class="lq-filters">
        @foreach(['from_date'=>'From service date','to_date'=>'To service date','name'=>'Name','email'=>'Email','phone'=>'Phone'] as $field=>$label)
        <div><label for="dnp-{{ $field }}">{{ $label }}</label><input id="dnp-{{ $field }}" name="{{ $field }}" type="{{ in_array($field,['from_date','to_date']) ? 'date' : 'text' }}" value="{{ request($field) }}"></div>
        @endforeach
        @unless($isExecutive)<div><label for="dnp-staff">Staff</label><select id="dnp-staff" name="representative_user_id"><option value="">All staff</option>@foreach($staff as $person)<option value="{{ $person->id }}" {{ request('representative_user_id') === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>@endforeach</select></div>@endunless
        <div class="lq-filter-actions"><a class="ti-btn" href="{{ route('admin.leads.dnp') }}" title="Reset filters" aria-label="Reset filters"><i class="ri-refresh-line"></i></a><button class="ti-btn ti-btn-primary">Apply Filters</button></div>
    </form>
    @if($showStaffBreakdown)
    <section class="lq-staff"><h2>Pending DNP by staff</h2>
        <table><thead><tr><th>Employee</th><th>Total pending</th><th>0-30 days</th><th>31-90 days</th><th>90+ days</th>@if($queueSummary['unknown'])<th>Unknown age</th>@endif</tr></thead><tbody>
        @forelse($queueSummary['staff'] as $person)<tr><td data-label="Employee">{{ $person['name'] }}</td><td data-label="Total pending">{{ $person['total'] }}</td>
        @foreach(['0-30','31-90','90+'] as $bucket)<td data-label="{{ $bucket }} days">{{ $person[$bucket] }} <small>{{ $person['total'] ? round($person[$bucket]/$person['total']*100,1) : 0 }}%</small></td>@endforeach
        @if($queueSummary['unknown'])<td data-label="Unknown age">{{ $person['unknown'] }}</td>@endif</tr>
        @empty<tr><td colspan="{{ $queueSummary['unknown'] ? 6 : 5 }}">No pending DNP leads.</td></tr>@endforelse
        </tbody></table>
    </section>@endif
    <section class="lq-list"><header class="lq-heading"><h2>DNP list</h2><small>Age from lead-created date</small></header>
    @include('admin.pages.leads.partials.queue-table-controls')
    <table data-queue-table><thead><tr>
    @foreach(['#','Client','Phone','Assigned','Next follow-up','Age','Product','Action'] as $heading)<th scope="col">@if($heading !== 'Action')<button type="button" data-queue-sort="{{ $loop->index }}">{{ $heading }} <i class="ri-arrow-up-down-line" aria-hidden="true"></i></button>@else{{ $heading }}@endif</th>@endforeach
    </tr></thead><tbody>
    @foreach($dnpLeads as $lead)
        @php($next=$queueSummary['followups']->get($lead->id)?->next_followup_date)
        @php($age=$queueSummary['ages'][$lead->id] ?? null)
        <tr data-queue-row data-id="{{ $lead->id }}"><td data-label="#">{{ $loop->iteration }}</td><td data-label="Client"><strong>{{ $lead->client?->name ?? '-' }}</strong></td><td data-label="Phone">{{ $lead->client?->contact_number ?? '-' }}</td><td data-label="Assigned">{{ $lead->representative?->name ?? 'Unassigned' }}</td>
        <td data-label="Next follow-up" data-order="{{ $next?->timestamp ?? 0 }}"><span class="lq-pill {{ $next ? 'lq-scheduled' : 'lq-score-hot' }}">{{ $next?->format('d M Y, H:i') ?? 'Not set' }}</span></td>
        <td data-label="Age" data-order="{{ $age ?? -1 }}"><span class="lq-pill {{ $age !== null && $age > 90 ? 'lq-score-hot' : 'lq-score-neutral' }}">{{ $age === null ? 'Unknown' : $age.' days' }}</span></td>
        <td data-label="Product">{{ ($tableProductNames[$lead->id] ?? '') ?: 'N/A' }}</td>
        <td data-label="Action"><div class="lq-actions"><a class="ti-btn" href="{{ $isHr ? route('admin.clients.view',$lead->client_id) : route('admin.leads.view',$lead->id) }}" title="View lead" aria-label="View lead"><i class="ri-eye-line"></i></a>
        @unless($isHr)<a class="ti-btn lq-edit" href="{{ route('admin.leads.edit',$lead->id) }}" title="Edit lead" aria-label="Edit lead"><i class="ri-edit-line"></i></a>@endunless</div></td></tr>
    @endforeach
    </tbody></table>
    @include('admin.pages.leads.partials.queue-table-footer')
    </section>
    @include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1"></script>
<script src="{{ asset('assets/admin/js/crm/lead-queues.js') }}?v=1"></script>
@endpush
