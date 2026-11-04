@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vendor-followups.css') }}">
<div class="vf-page">
    <header class="vf-heading vf-heading-card"><div><p class="vf-eyebrow">Vendor Follow-ups</p><h1>Add Vendor Follow-up</h1><p>Create a vendor task for {{ $lead->client?->name ?: 'this customer' }}.</p></div><div class="vf-actions"><a class="vf-button" href="{{ route('admin.operations.vendor-followups.index') }}"><i class="ri-arrow-left-line" aria-hidden="true"></i> Back to queue</a><button type="submit" form="vendor-followup-form" class="vf-button vf-primary">Create follow-up</button></div></header>
    @if($errors->any())<div class="vf-notice vf-red" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="vf-create-layout"><main>
        <section class="vf-section vf-card"><div class="vf-card-header"><div><h2>Lead Context</h2><p>Case this follow-up belongs to.</p></div></div><div class="vf-card-body"><div class="vf-lead-context"><strong>{{ $lead->client?->name ?: 'Not recorded' }}</strong><a href="{{ route('admin.leads.view', $lead->id) }}" target="_blank" rel="noopener">View lead <i class="ri-external-link-line" aria-hidden="true"></i></a><p>{{ $lead->client?->contact_number ?: 'Phone not recorded' }}</p><p>{{ implode(' / ', $interestNames) ?: 'Product / service not recorded' }}</p>@foreach($lead->rideSegments as $segment)<p>{{ $segment->from_place }}@if($segment->to_place) &rarr; {{ $segment->to_place }}@endif &middot; {{ $segment->from_date?->format('d M Y · H:i') ?: 'No date / TBA' }}</p>@endforeach</div></div></section>
        <section class="vf-section vf-card"><div class="vf-card-header"><div><h2>Follow-up Details</h2><p>Vendor, purpose, due date and assignment.</p></div></div><div class="vf-card-body">
            <form id="vendor-followup-form" method="POST" action="{{ route('admin.operations.vendor-followups.store', $lead) }}" data-today="{{ now()->toDateString() }}">
                @csrf
                <div class="vf-form-grid">
                    <div><label>Vendor <span aria-hidden="true">*</span><select name="vendor_id" required><option value="">Select vendor</option>@foreach($vendors as $vendor)<option value="{{ $vendor->id }}" data-phone="{{ $vendor->contact_number }}" {{ old('vendor_id') === $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>@endforeach</select></label><div class="vf-vendor-contact" id="vf-vendor-contact" hidden><strong data-vendor-name></strong><small data-vendor-phone></small></div></div>
                    <label>Assigned to<select name="assigned_to"><option value="">Me - {{ auth()->user()->name }}</option>@foreach($operationUsers as $user)<option value="{{ $user->id }}" {{ old('assigned_to') === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
                    <label class="vf-full">Purpose<input type="text" name="purpose" value="{{ old('purpose', 'Confirm Ride Date & Time') }}" maxlength="255"></label>
                    <label>Tentative ride date &amp; time<input type="datetime-local" name="tentative_ride_at" value="{{ old('tentative_ride_at', $tentativeRideAt?->format('Y-m-d\TH:i')) }}"></label>
                    <div><label>Follow-up date <span aria-hidden="true">*</span><input type="date" name="followup_date" required min="{{ now()->toDateString() }}" value="{{ old('followup_date', $suggestedFollowupDate->toDateString()) }}"></label><div class="vf-quick-dates" role="group" aria-label="Follow-up date">@foreach([0 => 'Today', 1 => 'Tomorrow', 3 => '+3 days', 7 => '+7 days'] as $offset => $label)<button type="button" data-date-offset="{{ $offset }}" aria-pressed="false">{{ $label }}</button>@endforeach</div></div>
                    <label class="vf-full">Remarks<textarea name="remarks" maxlength="5000" rows="5">{{ old('remarks') }}</textarea></label>
                </div>
                <footer class="vf-form-footer"><a class="vf-button" href="{{ route('admin.operations.vendor-followups.index') }}">Close</a><button class="vf-button vf-primary" type="submit">Create follow-up</button></footer>
            </form>
        </div></section>
    </main><aside class="vf-preview vf-card" aria-label="Follow-up preview"><div class="vf-card-header"><div><h2>Follow-up Preview</h2><p>How this item will appear in the queue.</p></div><span class="vf-badge vf-amber">Pending</span></div><div class="vf-card-body"><dl>
        <div><dt>Client</dt><dd>{{ $lead->client?->name ?: 'Not recorded' }}</dd></div>
        <div><dt>Vendor</dt><dd data-vf-preview="vendor_id">Not selected</dd></div>
        <div><dt>Purpose</dt><dd data-vf-preview="purpose"></dd></div>
        <div><dt>Remarks</dt><dd data-vf-preview="remarks"></dd></div>
        <div><dt>Tentative ride</dt><dd data-vf-preview="tentative_ride_at"></dd></div>
        <div><dt>Due</dt><dd data-vf-preview="followup_date"></dd></div>
        <div><dt>Assigned</dt><dd data-vf-preview="assigned_to"></dd></div>
        <div><dt>Status</dt><dd><span class="vf-badge vf-amber">Pending</span></dd></div>
    </dl></div></aside></div>
</div>
<script src="{{ asset('assets/admin/js/vendor-followups.js') }}" defer></script>
@endsection
