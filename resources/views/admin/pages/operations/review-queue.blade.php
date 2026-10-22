@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/review-queue.css') }}">
@php
    $segments = ['all' => 'All', 'waiting' => 'Waiting on customer', 'negative' => 'Negative',
        'positive' => 'Positive', 'neutral' => 'Neutral', 'unknown' => 'Unknown', 'not_sent' => 'Not sent yet', 'unassigned' => 'Unassigned'];
    $productNames = $products->pluck('product', 'id');
    $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRelatedLeads($cases);
@endphp
<div class="review-queue">
    <header class="rq-heading"><h1>Review Queue</h1><p>Post-service reviews and image collection</p></header>
    @foreach(['success', 'error'] as $message)
        @if(session($message))<div class="rq-alert" role="status">{{ session($message) }}</div>@endif
    @endforeach
    @if($errors->any())<div class="rq-alert" role="alert">{{ $errors->first() }}</div>@endif
    <section class="rq-summary" aria-label="Queue summary">
        <div class="rq-stat"><strong>{{ number_format($summary['all']) }}</strong><span>Open review cases</span></div>
        <div class="rq-stat rq-amber"><strong>{{ number_format($summary['waiting']) }}</strong><span>Waiting on customer</span>
            @if($summary['oldest_waiting'])<small>Oldest case {{ \Carbon\Carbon::parse($summary['oldest_waiting'])->diffForHumans() }}</small>@endif</div>
        <div class="rq-stat rq-red"><strong>{{ number_format($summary['negative']) }}</strong><span>Negative sentiment</span></div>
        <div class="rq-stat"><strong>{{ $summary['average_days'] === null ? '--' : $summary['average_days'].' days' }}</strong><span>Average open case age</span></div>
    </section>
    <nav class="rq-segments" aria-label="Review filters">
        @foreach($segments as $key => $label)
            <a class="{{ $filters['segment'] === $key ? 'is-active' : '' }}" aria-current="{{ $filters['segment'] === $key ? 'page' : 'false' }}"
                href="{{ route('admin.operations.queue', ['type' => 'review'] + request()->except('type', 'segment', 'page') + ['segment' => $key]) }}">{{ $label }} <span>{{ number_format($summary[$key]) }}</span></a>
        @endforeach
    </nav>
    <form method="GET" action="{{ route('admin.operations.queue', 'review') }}" class="rq-filters">
        <input type="hidden" name="segment" value="{{ $filters['segment'] }}">
        <label>Opened from<input type="date" name="from_date" value="{{ $filters['from_date'] }}"></label>
        <label>Opened to<input type="date" name="to_date" value="{{ $filters['to_date'] }}"></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(['pending', 'in_progress'] as $status)<option value="{{ $status }}" {{ $filters['status'] === $status ? 'selected' : '' }}>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
        <label>Product<select name="product_ids"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" {{ $filters['product_ids'] === (string)$product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></label>
        <label>Service<select name="service_ids"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" {{ $filters['service_ids'] === (string)$service->id ? 'selected' : '' }}>{{ $service->service }}</option>@endforeach</select></label>
        <label>Assigned employee<select name="operations_user_id"><option value="">All employees</option>@foreach($operationUsers as $person)<option value="{{ $person->id }}" {{ $filters['operations_user_id'] === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>@endforeach</select></label>
        <label class="rq-search">Search<input type="search" name="search" value="{{ $filters['search'] }}" placeholder="Client, phone or lead"></label>
        <label>Rows<select name="per_page">@foreach([10,25,50,100] as $size)<option {{ $filters['per_page'] === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label>
        <div class="rq-filter-actions"><a class="rq-button rq-secondary" href="{{ route('admin.operations.queue', 'review') }}"><i class="ti ti-refresh" aria-hidden="true"></i> Reset</a><button class="rq-button" type="submit">Filter</button></div>
    </form>
    <section class="rq-list" aria-label="Review list">
        <div class="rq-list-heading"><h2>Review list</h2><span>{{ number_format($cases->total()) }} cases &middot; Page {{ $cases->currentPage() }} of {{ $cases->lastPage() }}</span></div>
        <table class="rq-table"><thead><tr><th>Age</th><th>Client</th><th>Product</th><th>Review status</th><th>Last touch</th><th>Assigned</th><th>Action</th></tr></thead><tbody>
            @forelse($cases as $case)
                @php
                    $state = $presenter->state($case);
                    $age = $case->opened_at ? max(0, (int)$case->opened_at->diffInDays(now())) : null;
                    $own = $case->assigned_to === auth()->id();
                    $canStart = !$case->assigned_to || $own;
                    $lead = $case->lead;
                    $stage = $state['review_resolved'] ? 'image-collection' : 'review';
                    $resolved = $state['review_resolved'] && $state['images_resolved'];
                @endphp
                <tr>
                    <td data-label="Age"><span class="rq-pill {{ $age >= 3 ? 'rq-danger' : ($age >= 1 ? 'rq-warning' : 'rq-success') }}">{{ $age === null ? 'Unknown' : ($age === 0 ? 'Today' : $age.' days') }}</span></td>
                    <td data-label="Client"><strong>{{ $lead?->client?->name ?? 'Unknown client' }}</strong><small>{{ $lead?->client?->contact_number ?: '--' }}</small></td>
                    <td data-label="Product"><span>{{ ($tableProductNames[$case->lead_id] ?? '') ?: 'Not specified' }}</span>
                        @foreach($lead?->rideSegments ?? [] as $ride)<small>{{ $ride->from_date }} @if($ride->to_date && $ride->to_date != $ride->from_date)&ndash; {{ $ride->to_date }}@endif</small>@endforeach
                    </td>
                    <td data-label="Review status"><span class="rq-sentiment rq-{{ $state['sentiment'] }}"><b aria-hidden="true">&bull;</b> {{ $state['label'] }}</span><small>{{ ucwords(str_replace('_', ' ', $case->status)) }}</small>
                        @if($state['review_resolved'])<small>Review {{ $state['review'] }} &middot; Images {{ $state['images'] }}</small>@endif</td>
                    <td data-label="Last touch">@if($state['touch']){{ \Carbon\Carbon::parse($state['touch']['at'])->format('d M Y, H:i') }}<small>{{ $state['touch']['label'] }}</small>@else -- @endif</td>
                    <td data-label="Assigned">{{ $case->assignee?->name ?? 'Unassigned' }}</td>
                    <td data-label="Action" class="rq-actions">
                        @if($canStart && $case->status === 'pending')
                            <form method="POST" action="{{ route('admin.operations.case.start', $case) }}">@csrf<button type="submit" class="rq-button">{{ $case->assigned_to ? 'Start' : 'Assign to me' }}</button></form>
                        @elseif($state['sentiment'] === 'negative' || !$own)
                            <a class="rq-button" href="{{ route('admin.operations.followups.create', $case) }}">Open review</a>
                        @elseif(!$resolved)
                            <form method="POST" action="{{ route('admin.operations.case.'.$stage.'-complete', $case) }}" onsubmit="return confirm('Confirm {{ $stage === 'review' ? 'review received? Image collection will remain open.' : 'image collection received?' }}');">@csrf<button type="submit" class="rq-button">{{ $stage === 'review' ? 'Mark review done' : 'Mark images done' }}</button></form>
                        @else<span class="rq-pill rq-success">Resolved</span>@endif
                        <button type="button" class="rq-manage" data-rq-manage="rq-case-{{ $case->id }}" aria-haspopup="dialog">Manage case</button>
                        <template id="rq-case-{{ $case->id }}">
                            <p class="rq-modal-client">{{ $lead?->client?->name ?? 'Unknown client' }} &middot; {{ $stage === 'review' ? 'Review' : 'Image collection' }}</p>
                            <a class="rq-text-link" href="{{ route('admin.operations.followups.create', $case) }}">Follow-up / notes</a>
                            @if(!$resolved)
                                <form method="POST" action="{{ route('admin.operations.case.'.$stage.'-complete', $case) }}" onsubmit="return confirm('Confirm {{ $stage === 'review' ? 'review' : 'image collection' }} received?');">@csrf<label>Completion note<input name="note" maxlength="5000"></label><button class="rq-button rq-secondary" type="submit">{{ $stage === 'review' ? 'Mark review done' : 'Mark images done' }}</button></form>
                                <form method="POST" action="{{ route('admin.operations.case.'.$stage.'-cancel', $case) }}" onsubmit="return confirm('Cancel this stage?');">@csrf<label>Cancellation reason<input name="reason" required maxlength="5000"></label><button class="rq-button rq-secondary" type="submit">{{ $stage === 'review' ? 'Cancel review' : 'Cancel image collection' }}</button></form>
                            @endif
                        </template>
                    </td>
                </tr>
            @empty<tr><td colspan="7" class="rq-empty">No review cases match these filters.</td></tr>@endforelse
        </tbody></table>
        <footer class="rq-pagination"><span>Showing {{ $cases->firstItem() ?? 0 }}&ndash;{{ $cases->lastItem() ?? 0 }} of {{ number_format($cases->total()) }}</span>{{ $cases->onEachSide(1)->links() }}</footer>
    </section>
    <dialog id="rq-manage-dialog" class="rq-modal" aria-labelledby="rq-modal-title">
        <header class="rq-modal-header"><h2 id="rq-modal-title">Manage case</h2><button type="button" class="rq-modal-close" aria-label="Close manage case" title="Close"><i class="ti ti-x" aria-hidden="true"></i></button></header>
        <div class="rq-modal-body"></div>
    </dialog>
</div>
<script src="{{ asset('assets/admin/js/crm/review-queue.js') }}" defer></script>
@endsection
