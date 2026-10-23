<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/services/services.css') }}?v=2">
@php $productNames = $catalogProducts->pluck('product', 'id'); @endphp
<div class="crm-kimi sc-page" data-service-catalog data-csrf="{{ csrf_token() }}">
    <header class="sc-heading">
        <div>
            <h1>Services</h1>
            <p>{{ $serviceSummary['total'] }} services in the product catalog</p>
        </div>
        <a class="k-primary" href="{{ route('admin.services.create') }}"><i class="ti ti-plus" aria-hidden="true"></i>
            Add service</a>
    </header>
    @foreach(['success', 'error'] as $flash) @if(session($flash))
    <p class="sc-notice" role="status">{{ session($flash) }}</p>@endif @endforeach
    <div class="sc-stats" aria-label="Service summary">
        <div><strong>{{ $serviceSummary['total'] }}</strong><span>Total services</span><small>Under
                {{ $serviceSummary['products'] }} products</small></div>
        <div><strong>{{ $serviceSummary['active'] }}</strong><span>Active</span></div>
        <div><strong>{{ $serviceSummary['missing_price'] }}</strong><span>Price missing</span></div>
        <div><strong>{{ $serviceSummary['no_extras'] }}</strong><span>No extra services</span></div>
    </div>
    <section class="sc-list" aria-labelledby="sc-list-title">
        <div class="sc-list-heading">
            <h2 id="sc-list-title">Service list</h2><span class="k-tag amber">Price missing first</span>
        </div>
        <details class="sc-filters">
            <summary class="k-ghost"><i class="ti ti-filter" aria-hidden="true"></i> Filters
                @if(request()->filled('product_id') || request()->filled('service') || request()->filled('status'))<span
                class="k-tag blue">Active</span>@endif</summary>
            <form class="sc-tools" action="{{ route('admin.services.index') }}" method="GET">
                <label class="k-fg">Service name<input type="search" name="service" value="{{ request('service') }}"
                        placeholder="Search service name"></label>
                <label class="k-fg">Product<select name="product_id">
                        <option value="">All products</option>@foreach($catalogProducts as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') === (string) $product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach
                    </select></label>
                <label class="k-fg">Status<select name="status">
                        <option value="">All statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select></label>
                <div class="sc-filter-actions"><button class="k-ghost" type="submit"><i class="ti ti-filter" aria-hidden="true"></i> Filter</button>
                <a class="sc-icon" title="Reset filters" aria-label="Reset filters"
                    href="{{ route('admin.services.index') }}"><i class="ti ti-refresh" aria-hidden="true"></i></a></div>
            </form>
        </details>
        <div class="sc-tools"><label class="k-fg">Rows<select data-service-page-size>
                    <option selected>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select></label></div>
        <table class="sc-table" id="services-table">
            <thead>
                <tr>
                    <th>Service</th>
                    <th>Product</th>
                    <th>Pricing (amount − fees → original)</th>
                    <th>Extra services</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($services as $service)
                    <tr data-service-row="{{ $service->id }}"
                        class="{{ (float) $service->service_amount <= 0 ? 'sc-price-missing' : '' }}">
                        <td data-label="Service"><strong>{{ $service->service }}</strong></td>
                        <td data-label="Product">
                            <div class="sc-products">@forelse($serviceProductIds[$service->id] ?? [] as $productId)<span
                            class="k-tag blue">{{ $productNames[$productId] ?? 'Unavailable product' }}</span>@empty<span
                                    class="k-muted">No product assigned</span>@endforelse</div>
                        </td>
                        <td data-label="Pricing">
                            <div class="sc-pricing">
                                {{ config('settings.currency_symbol') }}{{ number_format($service->service_amount, 2) }} −
                                {{ number_format((float) $service->fees_percent, 2) }}% =
                                <strong>{{ config('settings.currency_symbol') }}{{ number_format($service->amount_after_fees, 2) }}</strong>
                                @if((float) $service->service_amount <= 0)<span class="k-tag red">Price missing</span>@endif
                            </div>
                        </td>
                        <td data-label="Extra services">
                            <details class="sc-extras">
                                <summary>
                                    {{ $service->extraServices->count() ? $service->extraServices->count() . ' attached' : 'None' }}
                                </summary>
                                @foreach($service->extraServices as $extra)
                                    <div>{{ $extra->extra_service }} ·
                                        {{ config('settings.currency_symbol') }}{{ number_format($extra->extra_service_amount, 2) }}
                                </div>@endforeach
                            </details>
                        </td>
                        <td data-label="Status"><span class="k-tag {{ $service->status ? 'green' : 'neutral' }}"
                                data-service-status>{{ $service->status ? 'Active' : 'Inactive' }}</span></td>
                        <td data-label="Actions">
                            <div class="sc-actions">
                                <a class="k-rowbtn"
                                    href="{{ route('admin.services.edit', $service->id) }}{{ (float) $service->service_amount <= 0 ? '?focus=price' : '' }}"
                                    @if((float) $service->service_amount <= 0)
                                    aria-label="Set price for {{ $service->service }}" @endif><i class="ti ti-pencil"
                                        aria-hidden="true"></i>
                                    {{ (float) $service->service_amount <= 0 ? 'Set price' : 'Edit' }}</a>
                                <a class="k-rowbtn" data-service-view
                                    href="{{ route('admin.services.view', $service->id) }}" aria-haspopup="dialog">View
                                    Service</a>
                                <button type="button" class="k-rowbtn" data-service-toggle
                                    data-url="{{ route('services.toggle-status', $service->id) }}"
                                    data-status="{{ $service->status ? 1 : 0 }}"
                                    data-name="{{ $service->service }}">{{ $service->status ? 'Deactivate' : 'Activate' }}</button>
                                @if($isSuperAdmin)<button type="button" class="sc-icon sc-danger" data-service-delete
                                    data-url="{{ route('admin.services.destroy', $service->id) }}"
                                    data-name="{{ $service->service }}" title="Delete service"
                                    aria-label="Delete {{ $service->service }}"><i class="ti ti-trash"
                                aria-hidden="true"></i></button>@endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="sc-empty" data-service-empty @if($services->isNotEmpty()) hidden @endif>No services match these
            filters.</p>
        <footer class="sc-pagination"><span data-service-range></span>
            <div><button class="sc-icon" type="button" data-service-prev title="Previous page"
                    aria-label="Previous page"><i class="ti ti-chevron-left" aria-hidden="true"></i></button><span
                    data-service-page></span><button class="sc-icon" type="button" data-service-next title="Next page"
                    aria-label="Next page"><i class="ti ti-chevron-right" aria-hidden="true"></i></button></div>
        </footer>
    </section>
    <dialog class="sc-dialog" id="service-view-dialog" aria-labelledby="service-view-title">
        <header>
            <h2 id="service-view-title">Service details</h2><button class="sc-icon" type="button" data-service-close
                title="Close" aria-label="Close service details"><i class="ti ti-x" aria-hidden="true"></i></button>
        </header>
        <div data-service-view-content aria-live="polite"></div>
    </dialog>
    <dialog class="sc-dialog sc-confirm" id="service-confirm-dialog" aria-labelledby="service-confirm-title">
        <header>
            <h2 id="service-confirm-title">Confirm action</h2><button class="sc-icon" type="button" data-service-close
                title="Close" aria-label="Close confirmation"><i class="ti ti-x" aria-hidden="true"></i></button>
        </header>
        <p data-service-confirm-message></p>
        <p role="alert" data-service-confirm-error></p>
        <footer><button class="k-ghost" type="button" data-service-close>Cancel</button><button class="k-primary"
                type="button" data-service-confirm>Confirm</button></footer>
    </dialog>
    <p role="status" class="sc-toast" data-service-feedback hidden></p>
</div>
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/services.js') }}" defer></script>@endpush
