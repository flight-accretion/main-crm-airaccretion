@php $tableProductNames = app(\App\Services\LeadTableProducts::class)->forLeads($leads); @endphp
<table class="leads-table" aria-label="Leads">
    <thead><tr>
        @if($isSuperAdmin)<th class="leads-selection"><input type="checkbox" id="select-all-transfer-leads" aria-label="Select all leads on this page"></th>@endif
        <th scope="col" class="leads-col-name">Name</th><th scope="col" class="leads-col-phone">Phone</th><th scope="col" class="leads-col-product">Product</th><th scope="col" class="leads-col-service-date">Service date</th><th scope="col" class="leads-col-followup">Follow-up</th><th scope="col" class="leads-col-assigned">Assigned</th><th scope="col" class="leads-col-ai">AI score</th><th scope="col" class="leads-col-status">Status</th><th scope="col" class="leads-col-action">Action</th>
    </tr></thead>
    <tbody>
    @forelse($leads as $key => $enquiry)
        @php
            $latest = $latestFollowups[$enquiry->id] ?? null;
            $status = $latest ? $latest->status : null;
            $canCreateManualRepeatLead = \App\Models\LeadFollowup::allowsManualRepeatLeadCreation($status);
            $isOverdue = $latest && $latest->next_followup_date && $latest->next_followup_date->lt(now()->startOfDay()) && in_array((int) $status, \App\Models\LeadFollowup::TODAY_FOLLOWUP_MISSED_OPEN_STATUSES, true);
            $score = $enquiry->latestAiScore;
            $temperature = $score && $score->status === 'completed' ? $score->displayTemperature($leadAiScoringSetting) : null;
            $phone = \App\Support\LeadPhonePresentation::forNumber(optional($enquiry->client)->contact_number);
        @endphp
        <tr class="lead-followup-hover-row {{ $isOverdue ? 'leads-overdue-row' : '' }}">
            @if($isSuperAdmin)<td class="leads-selection"><input type="checkbox" class="lead-transfer-checkbox" value="{{ $enquiry->id }}" data-client-name="{{ optional($enquiry->client)->name ?? 'N/A' }}" data-current-representative="{{ optional($enquiry->representative)->name ?? 'N/A' }}" aria-label="Select {{ optional($enquiry->client)->name ?? 'lead' }}"></td>@endif
            <td data-label="Name" class="leads-client" data-sort-value="{{ optional($enquiry->client)->name ?? '' }}"><strong>{{ optional($enquiry->client)->name ?? 'N/A' }}</strong><details class="leads-record-dates"><summary>Details</summary><span>Created: {{ optional($enquiry->created_at)->format('d-m-Y H:i') }}</span><span>Updated: {{ optional($enquiry->updated_at)->format('d-m-Y H:i') }}</span></details></td>
            <td data-label="Phone" class="leads-phone" data-sort-value="{{ $phone['copy'] ?? $phone['display'] }}">
                @if($phone['dial'])
                    <a class="leads-phone-link" href="{{ $phone['dial'] }}" data-lead-phone="{{ $phone['copy'] }}" title="{{ $phone['display'] }}">{{ $phone['display'] }}</a>
                @else{{ $phone['display'] }}@endif
            </td>
            <td data-label="Product"><strong>{{ ($tableProductNames[$enquiry->id] ?? '') ?: 'N/A' }}</strong></td>
            <td data-label="Service date">@if($enquiry->rideSegments->count()) @php $firstSegment = $enquiry->rideSegments->first(); $lastSegment = $enquiry->rideSegments->last(); @endphp {{ \Carbon\Carbon::parse($firstSegment->from_date)->format('d M Y') }}@if($firstSegment->from_date != $lastSegment->to_date)<br>{{ \Carbon\Carbon::parse($lastSegment->to_date)->format('d M Y') }}@endif @else N/A @endif</td>
            <td data-label="Follow-up" data-sort-value="{{ $latest && $latest->next_followup_date ? $latest->next_followup_date->format('Y-m-d H:i:s') : '9999-12-31' }}"><span class="{{ $isOverdue ? 'leads-overdue' : '' }}">{{ $latest && $latest->next_followup_date ? $latest->next_followup_date->format('d M Y, H:i') : 'No follow-up date' }}</span>@include('admin.pages.leads.partials.list-history')</td>
            <td data-label="Assigned">@if($enquiry->representative){{ $enquiry->representative->name }}@else<span class="leads-pill leads-amber">Unassigned</span>@endif</td>
            <td data-label="AI score" data-sort-value="{{ $temperature ? ($score->score ?? -1) : -1 }}">@if($temperature)<span class="leads-pill {{ $temperature === 'hot' ? 'leads-hot' : 'leads-neutral' }}">{{ $score->score ?? 'N/A' }}@if($temperature === 'hot') <i class="ri-fire-line" aria-hidden="true"></i> Hot @endif</span>@else<span class="leads-muted">N/A</span>@endif</td>
            <td data-label="Status" class="leads-status"><span class="leads-pill {{ in_array((int) $status, [2, 9], true) ? 'leads-red' : ($status !== null && in_array((int) $status, [1, 3, 5, 8], true) ? 'leads-green' : 'leads-neutral') }}">{{ $status !== null ? (['Initiated', 'Active', 'Cancelled', 'Full Payment Received', 'Partial Payment Received', 'Confirmed', 'Pending', 'Rescheduled', 'Approved', 'Rejected'][$status] ?? 'N/A') : 'N/A' }}</span></td>
            <td data-label="Action" class="leads-actions">@include('admin.pages.leads.partials.list-actions')</td>
        </tr>
    @empty
        <tr><td colspan="{{ $isSuperAdmin ? 10 : 9 }}" class="leads-empty">No matching leads</td></tr>
    @endforelse
    </tbody>
</table>
