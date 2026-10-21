@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vendor-followups.css') }}">
<div class="vf-page">
    <header class="vf-heading"><div><h1>Vendor Follow-ups</h1><p>Vendor confirmations and follow-up history</p></div><a class="vf-button" href="{{ route('admin.operations.vendor-followups.export', request()->query()) }}"><i class="ri-download-line" aria-hidden="true"></i> Export</a></header>
    @if(session('success'))<p class="vf-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="vf-notice vf-red" role="alert">{{ $errors->first() }}</p>@endif
    <section class="vf-stats" aria-label="Vendor follow-up summary">
        @foreach(['open' => 'Open follow-ups', 'overdue' => 'Overdue', 'today' => 'Due today', 'completed_week' => 'Completed this week'] as $key => $label)
        <div><strong>{{ $counts[$key] }}</strong><span>{{ $label }}</span><small>{{ ['open' => 'All pending vendor tasks', 'overdue' => 'Past follow-up date', 'today' => now()->format('d M Y'), 'completed_week' => 'Confirmed completions only'][$key] }}</small></div>
        @endforeach
    </section>
    <nav class="vf-chips" aria-label="Follow-up filters">
        @foreach(['open' => 'Open', 'today' => 'Today', 'overdue' => 'Overdue', 'upcoming' => 'Upcoming', 'age_2_7' => 'Age 2-7 days', 'age_over_7' => 'Age >7 days', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'] as $key => $label)
        <a href="{{ route('admin.operations.vendor-followups.index', array_merge(request()->except('page'), ['bucket' => $key])) }}" @if($filters['bucket'] === $key) aria-current="page" @endif>{{ $label }} <span>{{ $counts[$key] }}</span></a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('admin.operations.vendor-followups.index') }}" class="vf-filters">
        <input type="hidden" name="bucket" value="{{ $filters['bucket'] }}">
        @foreach(['vendor_id', 'assigned_to'] as $key)@if($filters[$key])<input type="hidden" name="{{ $key }}" value="{{ $filters[$key] }}">@endif @endforeach
        <label>Search<input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Client, vendor, phone or lead ID"></label>
        <label>Rows<select name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label>
        <div class="vf-actions"><a class="vf-button" href="{{ route('admin.operations.vendor-followups.index') }}"><i class="ri-refresh-line" aria-hidden="true"></i> Reset</a><button class="vf-button vf-primary" type="submit">Filter</button></div>
    </form>
    <section class="vf-list"><div class="vf-section-heading"><h2>Follow-up list</h2><span class="vf-badge">{{ $cases->total() }} records</span></div>
        <table><thead><tr><th>Age</th><th>Client</th><th>Vendor</th><th>Purpose</th><th>Remarks</th><th>Due</th><th>Last activity</th><th>Status</th><th>Assigned</th><th>Action</th></tr></thead><tbody>
        @foreach($cases as $case)
            @php
                $resolution = data_get($case->metadata, 'resolution');
                $ageEnd = $case->completed_at ?: now();
                $age = $case->opened_at ? max(0, (int) $case->opened_at->copy()->startOfDay()->diffInDays($ageEnd->copy()->startOfDay(), false)) : null;
                $overdue = !$case->completed_at && $case->next_followup_at && $case->next_followup_at->lt(now()->startOfDay());
                $vendor = $vendorsById->get(data_get($case->metadata, 'vendor_id'));
                $status = $resolution ? ucfirst($resolution) : ucfirst(str_replace('_', ' ', $case->status));
            @endphp
            <tr class="{{ $overdue ? 'vf-overdue' : '' }}">
                <td data-label="Age"><span class="vf-badge {{ $overdue ? 'vf-red' : ($case->completed_at ? 'vf-green' : 'vf-amber') }}">{{ $age === null ? 'Not recorded' : ($age === 0 ? 'Today' : $age.'d') }}</span></td>
                <td data-label="Client"><strong>{{ $case->lead?->client?->name ?: 'Not recorded' }}</strong><small>{{ $case->lead?->client?->contact_number ?: 'Not recorded' }}</small></td>
                <td data-label="Vendor">{{ data_get($case->metadata, 'vendor_name_snapshot') ?: ($vendor?->name ?: 'Not recorded') }}@if($vendor?->contact_number)<small>{{ $vendor->contact_number }}</small>@endif</td>
                <td data-label="Purpose">{{ data_get($case->metadata, 'purpose') ?: 'Not recorded' }}</td>
                <td data-label="Remarks"><div class="vf-remarks">{{ $case->note ?: 'Not recorded' }}</div></td>
                <td data-label="Due">@if($case->completed_at)<span>&mdash;</span>@elseif($case->next_followup_at)<span class="{{ $overdue ? 'vf-text-red' : '' }}">{{ $case->next_followup_at->format('d M Y') }}</span>@if($overdue)<small class="vf-text-red">{{ (int) $case->next_followup_at->copy()->startOfDay()->diffInDays(now()->startOfDay()) }}d overdue</small>@elseif($case->next_followup_at->isToday())<small>Today</small>@endif @else Not scheduled @endif</td>
                <td data-label="Last activity">{{ $case->activities_max_created_at ? \Carbon\Carbon::parse($case->activities_max_created_at)->format('d M Y · H:i') : 'Not recorded' }}@if($case->completed_at)<small>Closed {{ $case->completed_at->format('d M Y') }}</small>@endif</td>
                <td data-label="Status"><span class="vf-badge {{ $resolution === 'cancelled' ? 'vf-red' : ($case->completed_at ? 'vf-green' : 'vf-amber') }}">{{ $status }}</span></td>
                <td data-label="Assigned">{{ $case->assignee?->name ?: 'Unassigned' }}</td>
                <td data-label="Action"><a class="vf-button {{ $canManage && !$case->completed_at ? 'vf-primary' : '' }}" href="{{ route('admin.operations.vendor-followups.show', $case) }}">{{ $canManage && !$case->completed_at ? 'Chase now' : 'View' }}</a></td>
            </tr>
        @endforeach
        </tbody></table>
        @if($cases->isEmpty())<p class="vf-empty">No vendor follow-ups match these filters.</p>@endif
        <div class="vf-pagination">{{ $cases->onEachSide(1)->links() }}</div>
    </section>
</div>
@endsection
