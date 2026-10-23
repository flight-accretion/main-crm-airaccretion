@php
    $editing = isset($service);
    $linkedProducts = $editing ? \App\Http\Controllers\ProductController::serviceProductIds($service->getRawOriginal('product_ids')) : [];
    $selectedProduct = old('product_id', $linkedProducts[0] ?? '');
    $selectedExtras = array_map('strval', old('extra_service_ids', $editing ? $service->extraServices->pluck('id')->all() : []));
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/services/services.css') }}">
<div class="crm-kimi sc-page">
    <header class="sc-heading"><div><h1>{{ $editing ? 'Edit Service' : 'Add Service' }}</h1></div><a class="k-ghost" href="{{ route('admin.services.index') }}">Cancel</a></header>
    @foreach(['success', 'error'] as $flash) @if(session($flash))<p class="sc-notice" role="status">{{ session($flash) }}</p>@endif @endforeach
    @if($errors->any())<div class="sc-notice sc-danger" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <form action="{{ $editing ? route('admin.services.update', $service->id) : route('admin.services.store') }}" method="POST" id="serviceForm" data-service-form data-currency="{{ config('settings.currency_symbol') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="sc-form-layout"><div>
            <section class="sc-form-section" aria-labelledby="service-pricing-title"><h2 id="service-pricing-title">Service &amp; pricing</h2>
                <div class="sc-form-grid">
                    <label class="k-fg sc-full" for="service-name">Service name *<input id="service-name" name="service" value="{{ old('service', $service->service ?? '') }}" maxlength="100" required></label>
                    <label class="k-fg" for="product_id">Product *<select id="product_id" name="product_id" class="js-example-basic-single" required><option value="">Select product</option>@foreach($products as $product)<option value="{{ $product->id }}" {{ (string)$selectedProduct === (string)$product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></label>
                    <label class="k-fg" for="service-description">Description<textarea id="service-description" name="description" maxlength="255" rows="2">{{ old('description', $service->description ?? '') }}</textarea></label>
                    <label class="k-fg" for="service_amount_input">Service amount (client pays) *<input type="number" id="service_amount_input" name="service_amount" value="{{ old('service_amount', $service->service_amount ?? '') }}" min="0" max="9999999" step="1" required></label>
                    <label class="k-fg" for="fees_percent_input">Fees %<input type="number" id="fees_percent_input" name="fees_percent" value="{{ old('fees_percent', $service->fees_percent ?? 0) }}" min="0" max="100" step="0.01"></label>
                    <div class="sc-full"><label for="amount_after_fees_preview">Original amount (fees minus)</label><output id="amount_after_fees_preview" class="sc-net" for="service_amount_input fees_percent_input">0.00</output></div>
                </div>
            </section>
            <section class="sc-form-section" aria-labelledby="service-extra-title"><h2 id="service-extra-title">Extra services (add-ons)</h2>
                <div class="sc-picker" data-service-picker><label class="k-fg" for="service-extra-search">Search extra services<input type="search" id="service-extra-search" data-service-extra-search placeholder="Search name or description"></label>
                    <div class="sc-extra-options">@forelse($extraServices as $extra)
                        <label class="sc-extra-option" data-service-extra-option data-search="{{ Str::lower($extra->extra_service.' '.$extra->description) }}"><span><input type="checkbox" name="extra_service_ids[]" value="{{ $extra->id }}" data-amount="{{ $extra->extra_service_amount }}" {{ in_array((string)$extra->id, $selectedExtras, true) ? 'checked' : '' }}> {{ $extra->extra_service }}</span><span>{{ config('settings.currency_symbol') }}{{ number_format($extra->extra_service_amount, 2) }}</span></label>
                    @empty<p class="k-muted">No active customer extra services available.</p>@endforelse</div>
                    <p class="k-muted" data-service-extra-empty hidden>No matching extra services.</p>
                    <p class="sc-extra-summary">Add-ons total: <strong data-service-extras-total></strong><span data-service-selected-count></span></p>
                </div>
            </section>
            <section class="sc-form-section" aria-labelledby="service-email-title"><h2 id="service-email-title">Registration email note</h2><label class="k-fg" for="service-email-note"><span class="sr-only">Registration email note</span><textarea id="service-email-note" name="booking_email_note" maxlength="5000" rows="4">{{ old('booking_email_note', $service->booking_email_note ?? '') }}</textarea></label></section>
            <section class="sc-form-section" aria-labelledby="service-terms-title"><h2 id="service-terms-title">Terms &amp; conditions</h2>
                <textarea id="termsEditor" name="terms_and_conditions" class="sc-terms-fallback" aria-label="Terms and conditions" rows="8">{{ old('terms_and_conditions', $service->terms_and_conditions ?? '') }}</textarea><div id="editor"></div>
            </section>
        </div>
        <aside class="sc-preview"><h2>Pricing preview</h2><div class="sc-preview-card"><h3 data-service-preview-name>Service name</h3><dl>
            <div><dt>Service amount</dt><dd data-service-preview-amount></dd></div><div><dt>Fees <span data-service-preview-fees-percent></span></dt><dd data-service-preview-fees></dd></div>
            <div><dt>Original amount</dt><dd class="sc-green" data-service-preview-net></dd></div><div><dt>Add-ons</dt><dd data-service-preview-extras></dd></div><div class="sc-total"><dt>Total with selected add-ons</dt><dd data-service-preview-total></dd></div>
        </dl></div><button class="k-primary" type="submit"><i class="ti ti-device-floppy" aria-hidden="true"></i> {{ $editing ? 'Update service' : 'Save service' }}</button></aside>
        </div>
    </form>
</div>
@push('scripts')<script src="{{ asset('assets/admin/js/crm/services.js') }}" defer></script>@endpush
