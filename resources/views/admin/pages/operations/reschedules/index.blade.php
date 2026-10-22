<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/reschedules.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/reschedule-cancel.css') }}">
@php
    $reasonLabels = ['client_personal' => 'Client - personal', 'client_business' => 'Client - business', 'weather' => 'Weather', 'maintenance' => 'Maintenance', 'vendor_unavailable' => 'Vendor unavailable', 'other' => 'Other'];
    $stateLabels = ['fixed' => 'Date confirmed', 'open' => 'Awaiting date', 'pending' => 'Pending reschedule', 'legacy_closed' => 'Closed - legacy record', 'cancelled' => 'Cancelled'];
    $groups = collect($rescheduleRows)->groupBy('state');
    $tableProductNames = app(\App\Services\LeadTableProducts::class)->forLeadIds(collect($rescheduleRows)->pluck('lead_id'));
@endphp
<div class="crm-reschedules">
    <header class="rc-heading"><div><h1>Rescheduled Rides</h1><p>Fixed dates, open reschedules and case history</p></div><a class="rc-button" href="{{ route('admin.operations.reschedules.index', array_merge(request()->except(['page', 'download']), ['download' => 'csv'])) }}"><i class="ri-download-line" aria-hidden="true"></i> Export</a></header>
    @if(session('success'))<div class="rc-notice" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rc-notice rc-danger" role="alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rc-notice rc-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="rc-stats" aria-label="Reschedule summary">
        <div><strong>{{ $rescheduleSummary['month'] }}</strong><span>Opened this month</span><small>Reschedule cases</small></div>
        <div><strong>{{ $rescheduleSummary['fixed'] }}</strong><span>With a fixed new date</span><small>Saved date changes</small></div>
        <div><strong>{{ $rescheduleSummary['open'] }}</strong><span>Open - no date yet</span><small>Awaiting customer date</small></div>
        <div><strong>{{ $rescheduleSummary['expiring'] }}</strong><span>Expiring within 10 days</span><small>90 days from original service date</small></div>
    </section>
    @if(config('operations.reschedule_window_automation', false))
    <div class="rc-actions"><a class="rc-button" href="{{ route('admin.operations.queue', 'customer_call') }}"><i class="ri-phone-line" aria-hidden="true"></i> Call reminder tasks</a><a class="rc-button" href="{{ route('admin.operations.reschedule-assessments.index') }}">Expiry assessments <i class="ri-arrow-right-line" aria-hidden="true"></i></a></div>
    @endif
    <nav class="rc-tabs" aria-label="Reschedule state">
        @foreach(['all' => 'All rescheduled', 'fixed' => 'With dates', 'open' => 'No date', 'expiring' => 'Expiring within 10 days', 'assessment' => 'Expired - assess', 'pending' => 'Pending updates'] as $key => $label)
            @continue($key === 'assessment' && !config('operations.reschedule_window_automation', false))
            <a href="{{ route('admin.operations.reschedules.index', array_merge(request()->except(['page', 'download']), ['segment' => $key])) }}" @if($rescheduleFilters['segment'] === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    <form class="rc-filters" method="GET" action="{{ route('admin.operations.reschedules.index') }}">
        <input type="hidden" name="segment" value="{{ $rescheduleFilters['segment'] }}">
        <label>Opened from<input type="date" name="from_date" value="{{ $rescheduleFilters['from_date'] }}"></label>
        <label>Opened to<input type="date" name="to_date" value="{{ $rescheduleFilters['to_date'] }}"></label>
        <label>Search<input type="search" name="search" placeholder="Client, phone or note" value="{{ $rescheduleFilters['search'] }}"></label>
        <label>Rows<select name="per_page">@foreach([10,20,25,50,100] as $size)<option value="{{ $size }}" {{ $rescheduleFilters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label>
        <div class="rc-actions"><a class="rc-button" href="{{ route('admin.operations.reschedules.index') }}"><i class="ri-refresh-line" aria-hidden="true"></i> Reset</a><button class="rc-button rc-primary" type="submit">Filter</button></div>
    </form>
    @foreach(['pending' => 'Pending reschedule updates', 'fixed' => 'With a fixed new date', 'open' => 'Open - no date yet', 'legacy_closed' => 'Previous reschedule cases', 'cancelled' => 'Cancelled reschedules'] as $group => $heading)
        @if($groups->has($group))
        <section class="rc-list">
            <div class="rc-section-heading"><h2>{{ $heading }}</h2><span class="rc-badge">{{ $groups[$group]->count() }} on this page</span></div>
            <table><thead><tr><th>Client</th><th>Product</th><th>Original date</th><th>{{ $group === 'open' ? 'Window expires' : 'New date' }}</th><th>{{ $group === 'open' ? 'Days left' : 'Reason' }}</th><th>{{ $group === 'open' ? 'Assigned to' : 'Updated by' }}</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>@foreach($groups[$group] as $row)
                @php $case = $row['case']; $days = $row['days_left']; @endphp
                <tr>
                    <td data-label="Client"><a class="rc-client" href="{{ route('admin.leads.view', $row['lead_id']) }}" target="_blank" rel="noopener">{{ $row['client']?->name ?: 'Not recorded' }}</a><small>{{ $row['client']?->contact_number ?: 'Not recorded' }}</small></td>
                    <td data-label="Product">{{ ($tableProductNames[$row['lead_id']] ?? '') ?: 'Not specified' }}<small>{{ $row['ride']?->from_place }}@if($row['ride']?->to_place) &rarr; {{ $row['ride']->to_place }}@endif</small>@if($row['ride']?->booking_resource_key)<small>Resource: {{ $row['ride']->booking_resource_key }}</small>@endif<small>Paid / Total: &#8377;{{ number_format($row['received_amount'], 2) }} / &#8377;{{ number_format($row['total_amount'], 2) }}</small></td>
                    <td data-label="Original date">{{ $row['original_date']?->format('d M Y · H:i') ?: 'Not recorded' }}@if($group === 'pending' && !$row['original_date'] && $row['ride']?->from_date)<small>Current: {{ $row['ride']->from_date->format('d M Y · H:i') }}</small>@endif</td>
                    <td data-label="{{ $group === 'open' ? 'Window expires' : 'New date' }}">{{ ($group === 'open' ? $row['expires_on'] : $row['new_date'])?->format('d M Y') ?: 'Not recorded' }}</td>
                    <td data-label="{{ $group === 'open' ? 'Days left' : 'Reason' }}">
                        @if($group === 'open')
                            @if($days !== null)<span class="rc-badge {{ $days <= 10 ? 'rc-danger' : 'rc-warning' }}">{{ $days < 0 ? 'Expired '.abs($days).' days ago' : $days.' days' }}</span>@else<span>Original date missing</span>@endif
                        @else{{ $reasonLabels[$row['tracking']['reason'] ?? ''] ?? 'Not recorded' }}@endif
                    </td>
                    <td data-label="{{ $group === 'open' ? 'Assigned to' : 'Updated by' }}">{{ $row['actor']?->name ?: 'Unassigned' }}<small>Opened: {{ $case->opened_at?->format('d M Y') ?: 'Not recorded' }}</small>@php $lastActivity = $case->activities->sortByDesc('created_at')->first(); @endphp @if($lastActivity)<small>Last activity: {{ $lastActivity->created_at->format('d M Y · H:i') }}</small>@endif</td>
                    <td data-label="Status"><span class="rc-badge {{ $group === 'fixed' ? 'rc-success' : '' }}">{{ $stateLabels[$group] }}</span></td>
                    <td data-label="Actions"><div class="rc-actions">
                        @if($row['id'])<button type="button" class="rc-button view-ride-btn" data-ride-id="{{ $row['id'] }}" data-hs-overlay="#ride-details-modal" onclick="resetRefundSection()" title="View ride"><i class="ri-eye-line" aria-hidden="true"></i> View</button>@else<span class="rc-badge rc-warning">Trip reference missing</span>@endif
                        @if(!$case->completed_at && $row['id'])
                            <a class="rc-button rc-primary" href="{{ route('admin.operations.reschedules.edit', $row['id']) }}">{{ $group === 'open' ? 'Set new date' : 'Edit' }}</a>
                            @if($group === 'open' && $row['client']?->contact_number)<a class="rc-button" href="tel:{{ preg_replace('/[^+0-9]/', '', $row['client']->contact_number) }}"><i class="ri-phone-line" aria-hidden="true"></i> Call</a>@endif
                            <button class="rc-button rc-cancel" type="button" data-reschedule-cancel data-cancel-url="{{ route('admin.operations.reschedules.cancel', $row['id']) }}" data-client="{{ $row['client']?->name ?: 'Not recorded' }}" data-phone="{{ $row['client']?->contact_number ?: 'Not recorded' }}" data-service="{{ $row['service'] }}" data-trip="{{ $row['ride']?->from_place }}{{ $row['ride']?->to_place ? ' → '.$row['ride']->to_place : '' }}" data-date="{{ $row['ride']?->from_date?->format('d M Y · H:i') ?: 'No date / TBA' }}" data-original-date="{{ $row['original_date']?->format('d M Y · H:i') ?: 'Not recorded' }}" data-payment="Paid / Total: ₹{{ number_format($row['received_amount'], 2) }} / ₹{{ number_format($row['total_amount'], 2) }}" aria-haspopup="dialog" aria-controls="reschedule-cancel-dialog"><i class="ri-close-circle-line" aria-hidden="true"></i> Cancel</button>
                        @endif
                    </div>
                    @if($case->activities->contains(fn($activity) => is_array(data_get($activity->metadata, 'before'))))
                    <details class="rc-row-history"><summary>Change history</summary>@foreach($case->activities->sortByDesc('created_at') as $activity)@if(is_array(data_get($activity->metadata, 'before')))<p><strong>{{ $activity->created_at->format('d M Y · H:i') }}</strong><small>{{ $activity->user?->name ?: 'Not recorded' }}</small></p><dl>@foreach(['from_date' => 'From date', 'to_date' => 'To date', 'from_place' => 'From place', 'to_place' => 'To place', 'total_time' => 'Total time'] as $field => $label)<div><dt>{{ $label }}</dt><dd>{{ data_get($activity->metadata, 'before.'.$field) ?? 'Not recorded' }} &rarr; {{ data_get($activity->metadata, 'after.'.$field) ?? 'No date / not recorded' }}</dd></div>@endforeach</dl>@if($activity->note)<p>{{ $activity->note }}</p>@endif @endif @endforeach</details>
                    @endif
                    </td>
                </tr>
            @endforeach</tbody></table>
        </section>
        @endif
    @endforeach
    @if(empty($rescheduleRows))<p class="rc-empty">No reschedule cases match these filters.</p>@endif
    @if($rideStatusPaginator->hasPages())<div class="rc-pagination">{{ $rideStatusPaginator->onEachSide(1)->links() }}</div>@endif
    <dialog id="reschedule-cancel-dialog" class="rc-cancel-dialog" aria-labelledby="rc-cancel-title" aria-describedby="rc-cancel-description">
        <header class="rc-cancel-heading"><h2 id="rc-cancel-title">Cancel ride</h2><button type="button" class="rc-button" data-close-cancel aria-label="Close cancellation popup" title="Close"><i class="ri-close-line" aria-hidden="true"></i></button></header>
        <p id="rc-cancel-description">This will cancel the ride and complete its Operations reschedule task. Any refund must be handled separately.</p>
        <dl class="rc-cancel-facts">
            @foreach(['client' => 'Client', 'phone' => 'Phone', 'service' => 'Product / Service', 'trip' => 'Route', 'originalDate' => 'Original date', 'date' => 'Current date', 'payment' => 'Payments'] as $key => $label)
            <div><dt>{{ $label }}</dt><dd data-cancel-detail="{{ $key }}"></dd></div>
            @endforeach
        </dl>
        <form method="POST" id="reschedule-cancel-form">@csrf
            <footer class="rc-cancel-footer"><button type="button" class="rc-button" data-close-cancel>Close</button><button type="submit" class="rc-button rc-confirm-cancel"><i class="ri-close-circle-line" aria-hidden="true"></i> Cancel ride</button></footer>
        </form>
    </dialog>
</div>
<script src="{{ asset('assets/admin/js/reschedule-cancel.js') }}" defer></script>
