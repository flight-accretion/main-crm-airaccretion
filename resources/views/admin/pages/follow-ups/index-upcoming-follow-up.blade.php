@extends('admin.layouts.header')
@section('content')
@php $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRelatedLeads($arrFollowUps, 'enquiry'); @endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/lead-queues.css') }}?v=1">
<div class="crm-kpi-tool crm-lead-queue" data-kpi-tool data-lead-queue>
    <header class="lq-heading"><div><h3>Today's Follow-up</h3><p>{{ $fromDate->format('d M Y') }}</p></div>
    <button type="button" class="ti-btn" data-export-followups><i class="ri-download-line"></i> Export</button></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if(session('error'))<p role="alert">{{ session('error') }}</p>@endif
    @if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="lq-stats">
        @foreach([['total','Total follow-ups'],['hot','HOT leads'],['no_date','No follow-up date'],['overdue','Overdue follow-ups']] as [$key,$label])
            <div class="lq-stat lq-{{ $key }}"><strong>{{ number_format($queueSummary[$key]) }}</strong><span>{{ $label }}</span></div>
        @endforeach
    </div>
    <form method="GET" action="{{ route('admin.upcoming-follow-up.index') }}" id="filter-form" class="lq-filters">
        <div><label for="from-date">Next follow-up date</label><input id="from-date" type="date" name="from_date" value="{{ $fromDate->format('Y-m-d') }}"></div>
        <div><label for="product_id">Product</label><select id="product_id" name="product_id"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></div>
        <div><label for="lead_score_temperature">Lead score</label><select id="lead_score_temperature" name="lead_score_temperature"><option value="">All scores</option>@foreach(['hot','neutral','cold'] as $temperature)<option value="{{ $temperature }}" {{ request('lead_score_temperature') === $temperature ? 'selected' : '' }}>{{ ucfirst($temperature) }}</option>@endforeach</select></div>
        @if(auth()->user()->userType->user_type !== \App\Models\UserType::SALES_EXECUTIVE)
        <div><label for="representative_user_id">Staff</label><select id="representative_user_id" name="representative_user_id"><option value="">Team (All)</option>
        @if(in_array(auth()->user()->userType->user_type, [\App\Models\UserType::SALES_MANAGER,\App\Models\UserType::SENIOR_SALES_MANAGER],true))<option value="{{ auth()->id() }}" {{ request('representative_user_id') === auth()->id() ? 'selected' : '' }}>{{ auth()->user()->name }} (You)</option>@endif
        @foreach($assignedExecutives as $person)<option value="{{ $person->id }}" {{ request('representative_user_id') === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>@endforeach</select></div>@endif
        <div class="lq-filter-actions"><a class="ti-btn" href="{{ route('admin.upcoming-follow-up.index') }}" title="Reset filters" aria-label="Reset filters"><i class="ri-refresh-line"></i></a><button class="ti-btn ti-btn-primary">Apply Filters</button></div>
    </form>
    <nav class="lq-tabs" aria-label="Follow-up filter">
        @foreach(['all'=>'All follow-ups','today'=>'Selected date','overdue'=>'Overdue','no_date'=>'No follow-up date'] as $key=>$label)<button type="button" data-queue-tab="{{ $key }}" aria-pressed="{{ $key === 'all' ? 'true' : 'false' }}">{{ $label }} <span>{{ $queueSummary[$key === 'all' ? 'total' : $key] }}</span></button>@endforeach
    </nav>
    <section class="lq-list"><header class="lq-heading"><h2>Follow-ups</h2><small>{{ number_format($queueSummary['total']) }} leads</small></header>
    @include('admin.pages.leads.partials.queue-table-controls')
    <table data-queue-table id="followUpTable"><thead><tr>
        @foreach(['#','Lead score','Lead','Phone','Representative','Next follow-up','Product','Created','Service date','Action'] as $heading)<th scope="col">@if($heading !== 'Action')<button type="button" data-queue-sort="{{ $loop->index }}">{{ $heading }} <i class="ri-arrow-up-down-line" aria-hidden="true"></i></button>@else{{ $heading }}@endif</th>@endforeach
    </tr></thead><tbody>
    @foreach($arrFollowUps as $row)
        @php
            $lead=$row->enquiry;
            $score=$lead?->latestAiScore;
            $temperature=$score ? strtolower((string)$score->displayTemperature($leadAiScoringSetting)) : '';
            $bucket=!$row->next_followup_date ? 'no_date' : ($row->next_followup_date->toDateString() === $fromDate->toDateString() ? 'today' : 'overdue');
            $serviceDate=$lead?->rideSegments->first()?->from_date;
        @endphp
        <tr data-queue-row data-id="{{ $lead?->id }}" data-bucket="{{ $bucket }}" class="{{ $bucket === 'overdue' ? 'lq-overdue-row' : '' }}">
            <td data-label="#">{{ $loop->iteration }}</td>
            <td data-label="Lead score" data-order="{{ $score?->score ?? -1 }}">
            @if($score && $score->status === 'completed' && $temperature)<span class="lq-pill lq-score-{{ $temperature }}">{{ strtoupper($temperature) }} {{ $score->score }}</span>
            @elseif($score && in_array($score->status,['pending','processing'],true))<span class="lq-pill">Analysing</span>
            @elseif($score && $score->status === 'failed')<span class="lq-pill lq-score-hot">Failed</span>@else - @endif</td>
            <td data-label="Lead" ><strong>{{ $lead?->client?->name ?? '-' }}</strong></td>

            <!-- <td data-label="Phone">{{ $lead?->client?->contact_number ?? '-' }}</td> -->

          

@php
    $lead = $row->enquiry;
    $score = $lead?->latestAiScore;

    $temperature = $score
        ? strtolower((string) $score->displayTemperature($leadAiScoringSetting))
        : '';

    $bucket = !$row->next_followup_date
        ? 'no_date'
        : (
            $row->next_followup_date->toDateString() === $fromDate->toDateString()
                ? 'today'
                : 'overdue'
        );

    $serviceDate = $lead?->rideSegments->first()?->from_date;

    // Same phone formatter used by the Leads page.
    $phone = \App\Support\LeadPhonePresentation::forNumber(
        $lead?->client?->contact_number
    );
@endphp



<td
    data-label="Phone"
    class="leads-phone"
    data-sort-value="{{ $phone['copy'] ?? $phone['display'] }}"
>
    @if($phone['dial'])
        <a
            class="leads-phone-link"
            href="{{ $phone['dial'] }}"
            data-lead-phone="{{ $phone['copy'] }}"
            title="{{ $phone['display'] }}"
        >
            {{ $phone['display'] }}
        </a>
    @else
        {{ $phone['display'] }}
    @endif
</td>



            <td data-label="Representative">{{ $lead?->representative?->name ?? 'Unassigned' }}</td>
            
            <td data-label="Next follow-up" data-order="{{ $row->next_followup_date?->timestamp ?? 0 }}"><span class="lq-pill {{ $bucket === 'overdue' ? 'lq-score-hot' : 'lq-scheduled' }}">{{ $row->next_followup_date ? ($bucket === 'today' ? $row->next_followup_date->format('H:i') : $row->next_followup_date->format('d M Y, H:i')) : 'Not set' }}</span></td>
                <td data-label="Product">{{ ($tableProductNames[$lead?->id] ?? '') ?: 'N/A' }}</td>
            <td data-label="Created" data-order="{{ $lead?->created_at?->timestamp ?? 0 }}">{{ $lead?->created_at?->format('d M Y') ?? '-' }}</td>
            <td data-label="Service date" data-order="{{ $serviceDate?->timestamp ?? 0 }}">{{ $serviceDate?->format('d M Y') ?? '-' }}</td>
            <td data-label="Action" >@if($lead)<a class="ti-btn" target="_blank" href="{{ auth()->user()->userType->user_type === \App\Models\UserType::HR ? route('admin.clients.view',$lead->client_id) : route('admin.leads.follow-up.create',$lead->id) }}">Open</a>@endif</td>
        </tr>
    @endforeach
    </tbody></table>
    @include('admin.pages.leads.partials.queue-table-footer')
    </section>
    @include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')


<script>
(function () {
    const followUpTable = document.getElementById('followUpTable');

    if (!followUpTable) return;

    followUpTable.addEventListener('click', async function (event) {
        const link = event.target.closest('.leads-phone-link');

        if (!link || !followUpTable.contains(link)) return;

        // Mobile: use the native phone dialer.
        if (window.matchMedia('(max-width: 767px)').matches) {
            return;
        }

        // Desktop: copy the phone number instead.
        event.preventDefault();

        const phone = link.dataset.leadPhone;

        if (!phone) return;

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(phone);
            } else {
                const input = document.createElement('textarea');
                input.value = phone;
                input.style.position = 'fixed';
                input.style.opacity = '0';

                document.body.appendChild(input);
                input.select();

                const copied = document.execCommand('copy');
                input.remove();

                if (!copied) {
                    throw new Error('Clipboard copy failed');
                }
            }

            const originalTitle = link.title;
            link.title = 'Copied!';

            setTimeout(function () {
                link.title = originalTitle;
            }, 1500);
        } catch (error) {
            console.error('Phone copy failed:', error);
        }
    });
})();
</script>

<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1"></script>
<script src="{{ asset('assets/admin/js/crm/lead-queues.js') }}?v=1"></script>
@endpush
