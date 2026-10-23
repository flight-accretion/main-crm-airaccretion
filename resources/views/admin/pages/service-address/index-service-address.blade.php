@extends('admin.layouts.header')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/service-addresses.css') }}?v=2">
    <div class="crm-kimi sa-page" data-address-page data-base="{{ route('admin.service-addresses.index') }}"
        data-csrf="{{ csrf_token() }}"
        data-restore="{{ $errors->edit->any() ? 'edit' : ($errors->add->any() ? 'add' : '') }}">
        <header class="sa-heading">
            <div>
                <h1>Service Addresses</h1>
                <p>Pickup and reporting points linked to products and services</p>
            </div><button class="k-primary" type="button" data-address-add><i class="ti ti-plus" aria-hidden="true"></i> Add
                address</button>
        </header>
        @foreach(['success', 'error'] as $flash) @if(session($flash))
            <p role="status" class="{{ $flash === 'error' ? 'sa-error' : 'sa-sub' }}">{{ session($flash) }}</p>@endif
        @endforeach
        <div class="sa-stats">
            <div><strong>{{ $addressSummary['total'] }}</strong><span>Service addresses</span><small>Across
                    {{ $addressSummary['cities'] }} cities</small></div>
            <div><strong>{{ min(3, $cityCounts->count()) }}</strong><span>Most used
                    cities</span><small>@foreach($cityCounts->take(3) as $city){{ $city['name'] }}
                    {{ $city['count'] }}{{ !$loop->last ? ' · ' : '' }}@endforeach</small></div>
            <div><strong>{{ $addressSummary['shared_numbers'] }}</strong><span>Shared phone numbers</span></div>
            <div><strong>{{ $addressSummary['missing_contact'] }}</strong><span>Missing contact person</span></div>
        </div>
        <nav class="sa-chips" aria-label="Filter by city"><a class="sa-chip {{ !request('city_id') ? 'is-active' : '' }}"
                href="{{ route('admin.service-addresses.index', request()->except('city_id', 'page')) }}">All ·
                {{ $addressSummary['total'] }}</a>
            @foreach($cityCounts as $cityId => $city)<a
                class="sa-chip {{ request('city_id') === (string) $cityId ? 'is-active' : '' }}"
                href="{{ route('admin.service-addresses.index', array_merge(request()->except('page'), ['city_id' => $cityId])) }}">{{ $city['name'] }}
            · {{ $city['count'] }}</a>@endforeach
        </nav>
        <section class="sa-list" aria-labelledby="sa-title">
            <div class="sa-heading">
                <h2 id="sa-title">All service addresses</h2><span class="k-tag amber">Contact flags first</span>
            </div>
            <form class="sa-tools" method="GET" action="{{ route('admin.service-addresses.index') }}">
                @if(request('city_id'))<input type="hidden" name="city_id" value="{{ request('city_id') }}">@endif
                <label>Search<input type="search" name="search" value="{{ request('search') }}"
                        placeholder="Address, contact, product or service"></label>
                <label>Status<select name="status">
                        <option value="">All statuses</option>
                        <option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option>
                        <option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option>
                    </select></label>
                <label>Rows<select name="per_page">@foreach([10, 20, 50, 100] as $size)
                    <option value="{{ $size }}" {{ (int) request('per_page', 10) === $size ? 'selected' : '' }}>{{ $size }}
                </option>@endforeach
                    </select></label>
                <button class="k-ghost" type="submit"><i class="ti ti-filter" aria-hidden="true"></i> Filter</button><a
                    class="k-ghost" aria-label="Reset filters" title="Reset filters"
                    href="{{ route('admin.service-addresses.index') }}"><i class="ti ti-refresh" aria-hidden="true"></i></a>
            </form>
            <table class="sa-table">
                <thead>
                    <tr>
                        <th>Address</th>
                        <th>Contact person</th>
                        <th>Contact number</th>
                        <th>Product / service</th>
                        <th>Flags / status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($addresses as $item)
                        @php $flags = $addressFlags[$item->id]; @endphp
                        <tr data-address-row="{{ $item->id }}"
                            class="{{ $flags['shared'] || $flags['missing'] ? 'sa-flagged' : '' }}">
                            <td data-label="Address">
                                <div><strong>{{ $item->address }}</strong>
                                    <p class="sa-sub">
                                        {{ optional($item->city)->name }}{{ optional(optional($item->city)->state)->name ? ', ' . optional($item->city->state)->name : '' }}
                                    </p>
                                    <!-- @if($item->map_link && in_array(strtolower(parse_url($item->map_link, PHP_URL_SCHEME) ?? ''), ['http', 'https']))
                                    <a
                                        href="{{ $item->map_link }}" target="_blank" rel="noopener noreferrer">View map <i
                                    class="ti ti-external-link" aria-hidden="true"></i></a>
                                    @endif -->
                                </div>
                            </td>
                            <td data-label="Contact person">
                                <div>{{ $item->contact_person_name ?: 'Missing contact person' }}</div>
                            </td>
                            <td data-label="Contact number">
                                <div>{{ $item->contact_number ?: 'Not recorded' }}</div>
                            </td>
                            <td data-label="Product / service">
                                <div><strong>{{ optional($item->product)->product ?? 'Unavailable product' }}</strong>
                                    <p class="sa-sub">{{ optional($item->service)->service ?? 'Unavailable service' }}</p>
                                </div>
                            </td>
                            <td data-label="Flags / status">
                                <div>@if($flags['shared'])<span class="k-tag amber">Shared phone</span>@endif
                                    @if($flags['missing'])<span class="k-tag red">No contact person</span>@endif <span
                                        class="k-tag {{ $item->status ? 'green' : 'neutral' }}">{{ $item->status ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </td>
                            <td data-label="Actions">
                                <div class="sa-actions"><button type="button" class="k-ghost k-sm"
                                        data-address-edit="{{ $item->id }}">Edit</button><button type="button"
                                        class="k-ghost k-sm" data-address-view="{{ $item->id }}">View Address</button><button
                                        type="button" class="k-ghost k-sm" data-address-status="{{ $item->id }}"
                                        data-active="{{ $item->status ? '1' : '0' }}">{{ $item->status ? 'Deactivate' : 'Activate' }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty<tr>
                        <td colspan="6">No service addresses found.</td>
                    </tr>@endforelse
                </tbody>
            </table>
            <div class="sa-pagination">{{ $addresses->links() }}</div>
        </section>
        @foreach(['add' => 'Add Address', 'edit' => 'Edit Address', 'view' => 'View Address', 'status' => 'Change address status'] as $mode => $title)
            <dialog class="sa-dialog" data-address-dialog="{{ $mode }}" aria-labelledby="sa-{{ $mode }}-title">
                <header>
                    <h2 id="sa-{{ $mode }}-title">{{ $title }}</h2><button type="button" class="sa-close" data-address-close
                        aria-label="Close" title="Close">×</button>
                </header>
                @if(in_array($mode, ['add', 'edit'])) @include('admin.pages.service-address.partials.form', ['mode' => $mode])
                @elseif($mode === 'view')
                    <div data-address-details role="status"></div>
                @else<p data-status-message></p>
                    <p data-status-error class="sa-error" role="alert" hidden></p>
                    <footer class="sa-actions"><button type="button" class="k-ghost" data-address-close>Cancel</button><button
                type="button" class="k-primary" data-status-confirm>Confirm</button></footer>@endif
            </dialog>
        @endforeach
    </div>
@endsection
@push('scripts')
    <script src="{{ asset('assets/admin/js/crm/service-addresses.js') }}" defer></script>
@endpush
