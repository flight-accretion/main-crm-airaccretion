@php $linked = $service->getProducts(); @endphp
<div class="sc-details">
    <h3>{{ $service->service }}</h3>
    <dl class="sc-detail-grid">
        <div><dt>Product</dt><dd>{{ $linked->pluck('product')->join(', ') ?: 'No product assigned' }}</dd></div>
        <div><dt>Status</dt><dd><span class="k-tag {{ $service->status ? 'green' : 'neutral' }}">{{ $service->status ? 'Active' : 'Inactive' }}</span></dd></div>
        <div><dt>Service amount</dt><dd>{{ config('settings.currency_symbol') }}{{ number_format($service->service_amount, 2) }}</dd></div>
        <div><dt>Fees</dt><dd>{{ number_format((float)$service->fees_percent, 2) }}% · {{ config('settings.currency_symbol') }}{{ number_format($service->fees_amount, 2) }}</dd></div>
        <div><dt>Original amount (fees minus)</dt><dd>{{ config('settings.currency_symbol') }}{{ number_format($service->amount_after_fees, 2) }}</dd></div>
        <div><dt>Created</dt><dd>{{ $service->created_at?->format('d M Y, H:i') ?? '--' }}</dd></div><div><dt>Updated</dt><dd>{{ $service->updated_at?->format('d M Y, H:i') ?? '--' }}</dd></div>
        <div class="sc-full"><dt>Description</dt><dd>{{ $service->description ?: 'Not specified' }}</dd></div>
        <div class="sc-full"><dt>Registration email note</dt><dd class="sc-preline">{{ $service->booking_email_note ?: 'Not specified' }}</dd></div>
    </dl>
    <h4>Terms &amp; conditions</h4><div class="sc-richtext">{!! $service->terms_and_conditions ?: '<p>Not specified</p>' !!}</div>
    <h4>Extra services ({{ $service->extraServices->count() }})</h4>
    @forelse($service->extraServices as $extra)<div class="sc-detail-extra"><div><strong>{{ $extra->extra_service }}</strong><p>{{ $extra->description }}</p></div><div>{{ config('settings.currency_symbol') }}{{ number_format($extra->extra_service_amount, 2) }}<small>{{ $extra->status ? 'Active' : 'Inactive' }}</small></div></div>@empty<p class="k-muted">No attached extra services.</p>@endforelse
    <footer class="sc-detail-actions"><a class="k-ghost" href="{{ route('admin.services.edit', $service->id) }}"><i class="ti ti-pencil" aria-hidden="true"></i> Edit service</a></footer>
</div>
