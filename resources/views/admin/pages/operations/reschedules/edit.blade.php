@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/reschedules.css') }}">
@php
    $noDate = old('no_date', $ride->is_tba ? '1' : '0');
    $fields = ['from_date' => 'From date', 'to_date' => 'To date', 'from_place' => 'From place', 'to_place' => 'To place', 'total_time' => 'Total time'];
    $reasons = ['client_personal' => 'Client - personal', 'client_business' => 'Client - business', 'weather' => 'Weather', 'maintenance' => 'Aircraft maintenance', 'vendor_unavailable' => 'Vendor unavailable', 'other' => 'Other'];
@endphp
<div class="crm-reschedules">
    <header class="rc-heading"><div><h1>Edit Reschedule</h1><p>{{ $client?->name ?: 'Customer' }} &middot; Opened {{ $case->opened_at?->format('d M Y') }}</p></div><a class="rc-button" href="{{ route('admin.operations.reschedules.index') }}"><i class="ri-arrow-left-line" aria-hidden="true"></i> Back</a></header>
    @if($errors->any())<div class="rc-notice rc-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <section class="rc-section"><div class="rc-section-heading"><h2>Customer</h2><span class="rc-badge">CRM record</span></div><dl class="rc-facts">
        <div><dt>Name</dt><dd>{{ $client?->name ?: 'Not recorded' }}</dd></div>
        <div><dt>Phone</dt><dd>{{ $client?->contact_number ?: 'Not recorded' }}</dd></div>
        <div><dt>Lead</dt><dd>@if($lead)<a href="{{ route('admin.leads.view', $lead->id) }}" target="_blank" rel="noopener">View lead <i class="ri-external-link-line" aria-hidden="true"></i></a>@else Not recorded @endif</dd></div>
        <div><dt>Current service date</dt><dd>{{ $ride->from_date?->format('d M Y · H:i') ?: 'No date / TBA' }}</dd></div>
    </dl></section>
    <form method="POST" action="{{ route('admin.operations.reschedules.update', $ride) }}" id="reschedule-editor" data-conflicts-url="{{ route('admin.operations.reschedules.conflicts', $ride) }}">
        @csrf
        <section class="rc-section"><h2>Reschedule type</h2><fieldset class="rc-modes"><legend class="sr-only">Reschedule type</legend>
            <label><input type="radio" name="no_date" value="0" {{ !$noDate ? 'checked' : '' }}><span><strong>Fixed new date</strong><small>Customer confirmed a new slot</small></span></label>
            <label><input type="radio" name="no_date" value="1" {{ $noDate ? 'checked' : '' }}><span><strong>Open - within 90 days</strong><small>Customer will confirm the date later</small></span></label>
        </fieldset></section>
        <section class="rc-section"><h2>Trip details</h2><div class="rc-comparison">
            <div><h3>Current booking</h3><dl class="rc-current">@foreach($fields as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ in_array($key, ['from_date', 'to_date']) ? ($ride->$key?->format('d M Y · H:i') ?: 'Not recorded') : ($ride->$key ?? 'Not recorded') }}</dd></div>@endforeach</dl></div>
            <div><h3>New details</h3><div class="rc-new-details">@foreach($fields as $key => $label)
                @php $dateField = in_array($key, ['from_date', 'to_date']); $value = $dateField ? $ride->$key?->format('Y-m-d\TH:i') : $ride->$key; @endphp
                <label>{{ $label }}<input name="{{ $key }}" type="{{ $dateField ? 'datetime-local' : ($key === 'total_time' ? 'number' : 'text') }}" value="{{ old($key, $value) }}" data-current="{{ $value }}" @if($dateField) data-date-field @endif @if($key === 'total_time') min="0" max="9999" step="0.01" @endif @if(in_array($key, ['from_place', 'to_place'])) maxlength="255" @endif></label>
            @endforeach</div></div>
        </div></section>
        <section class="rc-section rc-window" id="reschedule-window" {{ $noDate ? '' : 'hidden' }}><h2>90-day open window</h2><dl class="rc-facts">
            <div><dt>Original service date</dt><dd>{{ $originalTrip && data_get($originalTrip, 'from_date') ? \Carbon\Carbon::parse($originalTrip['from_date'])->format('d M Y') : ($ride->from_date?->format('d M Y') ?: 'Not recorded') }}</dd></div>
            <div><dt>Valid until</dt><dd>{{ $window['expires_on']?->format('d M Y') ?: 'Original date required' }}</dd></div>
            <div><dt>Days remaining</dt><dd>{{ $window['days_left'] === null ? 'Not available' : ($window['days_left'] < 0 ? 'Expired' : $window['days_left'].' days') }}</dd></div>
            <div><dt>Case status after save</dt><dd>Open - awaiting date</dd></div>
        </dl>@if($window['days_left'] !== null && $window['days_left'] <= 10)<p class="rc-notice rc-warning">{{ $window['days_left'] < 0 ? 'The 90-day window has expired. Manual review is required.' : 'This window expires within 10 days.' }}</p>@endif</section>
        @if($resourceChecksAvailable)
        <section class="rc-section"><h2>Aircraft / vehicle availability</h2>
            @if($resourceChecksAvailable)
            <label>Aircraft / vehicle ID<input type="text" name="booking_resource_key" maxlength="80" value="{{ old('booking_resource_key', $ride->booking_resource_key) }}" placeholder="VT-UML"></label>
            <div class="rc-actions" style="margin-top:14px"><button type="button" class="rc-button" id="check-booking-conflict"><i class="ri-calendar-check-line" aria-hidden="true"></i> Check availability</button></div>
            <p id="booking-conflict-result" class="rc-notice" role="status" aria-live="polite">{{ $ride->booking_resource_key ? 'Availability not checked for this slot.' : 'Aircraft / vehicle not assigned. Availability unverified.' }}</p>
            @else<p class="rc-notice rc-warning">Booking resource setup is pending. Availability unverified.</p>@endif
        </section>
        @endif
        <section class="rc-section"><h2>Reason and internal note</h2><div class="rc-note-fields">
            <label>Reason<select name="reason"><option value="">Not recorded</option>@foreach($reasons as $key => $label)<option value="{{ $key }}" {{ old('reason', data_get($case->metadata, 'reschedule.reason')) === $key ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
            <label>Internal note<textarea name="note" rows="3" maxlength="5000">{{ old('note', $case->note) }}</textarea></label>
        </div></section>
        <footer class="rc-editor-footer"><a class="rc-button" href="{{ route('admin.operations.reschedules.index') }}">Back</a><button type="submit" class="rc-button rc-primary"><i class="ri-save-line" aria-hidden="true"></i> Save reschedule</button></footer>
    </form>
    @if($case->activities->contains(fn($activity) => is_array(data_get($activity->metadata, 'before'))))
    <section class="rc-section"><h2>Change history</h2><div class="rc-history">
        @foreach($case->activities->sortByDesc('created_at') as $activity)
            @if(is_array(data_get($activity->metadata, 'before')))
            <details><summary>{{ $activity->created_at->format('d M Y · H:i') }} &middot; {{ $activity->user?->name ?: 'Not recorded' }}</summary>
                <dl>@foreach($fields as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ data_get($activity->metadata, 'before.'.$key) ?? 'Not recorded' }} &rarr; {{ data_get($activity->metadata, 'after.'.$key) ?? 'No date / not recorded' }}</dd></div>@endforeach</dl>
                @if($activity->note)<p>{{ $activity->note }}</p>@endif
            </details>
            @endif
        @endforeach
    </div></section>
    @endif
</div>
<script src="{{ asset('assets/admin/js/operations-reschedules.js') }}" defer></script>
@endsection
