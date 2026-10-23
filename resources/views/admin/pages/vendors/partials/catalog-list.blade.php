@php $hasFilters = collect(request()->only(['search','vendor_name','email','product_id','service_id','status']))->contains(fn($value) => $value !== null && $value !== ''); @endphp
<header class="vc-heading"><div><h1>Vendors</h1><p>Partner network · {{ $vendorSummary['total'] }} vendors</p></div><button class="k-primary" type="button" data-vendor-add><i class="ti ti-plus" aria-hidden="true"></i> Add vendor</button></header>
<div class="vc-stats" aria-label="Vendor summary">
    <div><strong>{{ $vendorSummary['total'] }}</strong><span>Total vendors</span></div>
    <div><strong>{{ $vendorSummary['active'] }}</strong><span>Active</span></div>
    <div><strong>{{ $vendorSummary['inactive'] }}</strong><span>Inactive</span></div>
    <div><strong>{{ $vendorSummary['no_email'] }}</strong><span>No email on file</span></div>
</div>
<section class="vc-list" aria-labelledby="vc-list-title">
    <header class="vc-toolbar"><h2 id="vc-list-title">Vendor list</h2><button type="button" class="k-ghost" id="toggle-filters" aria-expanded="false" aria-controls="filter-section"><i class="ti ti-filter" aria-hidden="true"></i> Filters @if($hasFilters)<span class="k-tag blue">Active</span>@endif <i class="ti ti-chevron-down" id="filter-icon" aria-hidden="true"></i></button></header>
    <div id="filter-section" hidden>
        <form method="GET" action="{{ route('admin.vendors.index') }}" class="filter-form vc-filter" id="vendor-filter-form">
            <label>Search<input type="search" name="search" value="{{ request('search') }}" placeholder="Name, contact, email or location"></label>
            <label>Vendor name<input type="text" name="vendor_name" value="{{ request('vendor_name') }}"></label>
            <label>Email<input type="text" name="email" value="{{ request('email') }}"></label>
            <label>Product<select name="product_id" class="product-select"><option value="">All Products</option>@foreach($allProducts as $product)<option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></label>
            <label>Service<select name="service_id" class="service-select"><option value="">All Services</option>@foreach($allServices as $service)<option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>{{ $service->service }}</option>@endforeach</select></label>
            <label>Status<select name="status"><option value="">All Status</option><option value="1" {{ request('status') === '1' ? 'selected' : '' }}>Active</option><option value="0" {{ request('status') === '0' ? 'selected' : '' }}>Inactive</option></select></label>
            <input type="hidden" name="per_page" value="{{ $vendors->perPage() }}">
            <div class="vc-actions"><button type="button" class="k-ghost" onclick="clearFilters()" title="Reset filters" aria-label="Reset filters"><i class="ti ti-refresh" aria-hidden="true"></i></button><button type="submit" class="k-primary">Apply filters</button></div>
        </form>
    </div>
    <div class="vc-row-count"><label for="vendor-page-size">Rows</label><select id="vendor-page-size" data-vendor-page-size>@foreach(array_unique([10,20,50,100,$vendors->perPage()]) as $size)<option value="{{ $size }}" {{ $vendors->perPage() === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select><span>{{ $vendors->total() }} vendors</span></div>
    <table id="vendors-table" class="vc-table"><thead><tr><th>Vendor</th><th>Contact</th><th>Email</th><th>Location</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    @forelse($vendors as $vendor)
        <tr data-vendor-row="{{ $vendor->id }}">
            <td data-label="Vendor"><strong>{{ $vendor->name }}</strong></td>
            <td data-label="Contact"><span>{{ $vendor->contact_number }}</span></td>
            <td data-label="Email"><span>{{ trim((string)$vendor->email) !== '' ? $vendor->email : 'No email on file' }}</span></td>
            <td data-label="Location"><div>{{ collect([$vendor->city->name ?? null, $vendor->city->state->name ?? null])->filter()->implode(', ') ?: 'Not recorded' }}<small>{{ $vendor->city->state->country->name ?? '' }}</small></div></td>
            <td data-label="Status"><span class="k-tag {{ $vendor->status == 1 ? 'green' : 'neutral' }}">{{ $vendor->status == 1 ? 'Active' : 'Inactive' }}</span></td>
            <td data-label="Actions"><div class="vc-actions">
                <button type="button" class="k-rowbtn view-vendor-btn view-product" data-hs-overlay="#view-vendor" data-vendor-id="{{ $vendor->id }}" data-id="{{ $vendor->id }}" aria-haspopup="dialog">View</button>
                <button type="button" class="k-rowbtn edit-vendor-btn edit-product" data-hs-overlay="#edit-vendor" data-vendor-id="{{ $vendor->id }}" data-id="{{ $vendor->id }}" aria-haspopup="dialog">Edit</button>
                <button type="button" class="k-rowbtn open-vendor-modal" data-id="{{ $vendor->id }}" data-name="{{ $vendor->name }}" data-status="{{ $vendor->status }}">{{ $vendor->status == 1 ? 'Deactivate' : 'Activate' }}</button>
            </div></td>
        </tr>
    @empty<tr><td colspan="6">No vendors match these filters.</td></tr>@endforelse
    </tbody></table>
    <div class="vc-pagination">{{ $vendors->appends(request()->except('page'))->links() }}</div>
</section>
