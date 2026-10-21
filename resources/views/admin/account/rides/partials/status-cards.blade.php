<section class="rs-cases" aria-label="Ride cases">
@forelse($ridesData as $ride)
    <article class="rs-case">
        <div class="rs-case-header">
            <div><strong>{{ $ride['client_name'] }}</strong><small>{{ $ride['contact_number'] }}</small><small>{{ $ride['company_name'] }} &middot; GST {{ $ride['gst_number'] }}</small><small>Invoice {{ $ride['invoice_id'] }} &middot; Created {{ $ride['created_date'] }}</small></div>
            <div><label>Service</label><strong>{{ $ride['service_names'] ?: 'Not specified' }}</strong>@if($ride['extra_service_names'])<small>{{ $ride['extra_service_names'] }}</small>@endif</div>
            <div><label>Service date</label>@foreach($ride['all_rides'] as $segment)<strong>{{ optional($segment->from_date)->format('d M Y, H:i') ?: 'Not set' }}</strong><small>{{ $segment->from_place }} &rarr; {{ $segment->to_place }}</small>@endforeach</div>
            <div><label>Vendor / Rep</label><strong>{{ $ride['vendor_name'] }} &middot; {{ $ride['assigned_rep'] }}</strong></div>
            <div><label>Paid / Total</label><strong>&#8377;{{ number_format($ride['received_amount'], 2) }} / &#8377;{{ number_format($ride['total_amount'], 2) }}</strong><small>{{ $statusOptions[$ride['status']] ?? $ride['status_text'] }}</small></div>
            <div class="rs-actions">
                <button type="button" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-ride-btn" data-ride-id="{{ $ride['id'] }}" data-hs-overlay="#ride-details-modal" onclick="resetRefundSection()" title="View" aria-label="View ride {{ $ride['client_name'] }}"><i class="ri-eye-line" aria-hidden="true"></i></button>
            </div>
        </div>
        <details class="rs-timeline" open><summary>Timeline</summary><ol>
            @foreach($ride['timeline'] ?? [] as $event)
                <li class="{{ $event['date'] ? 'is-recorded' : '' }}"><span class="rs-step" aria-hidden="true">@if($event['date'])<i class="ri-check-line"></i>@else{{ $loop->iteration }}@endif</span><strong>{{ $event['label'] }}</strong><time>{{ $event['date'] ? \Carbon\Carbon::parse($event['date'])->format('d M Y, H:i') : '--' }}</time><small>{{ $event['note'] }}</small></li>
            @endforeach
        </ol></details>
    </article>
@empty<div class="rs-empty">No rides match these filters.</div>@endforelse
</section>
