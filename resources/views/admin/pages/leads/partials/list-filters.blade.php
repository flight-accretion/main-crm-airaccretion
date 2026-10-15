<form method="GET" action="{{ route('admin.clients.index') }}" id="filter-form" class="leads-filters">
    <input type="hidden" name="lead_queue" value="{{ request('lead_queue', 'all') }}">
    <input type="hidden" name="per_page" value="{{ request('per_page', 10) }}">
    <div class="leads-filter-grid">
        <label class="leads-search"><span class="sr-only">Search leads</span><i class="ri-search-line" aria-hidden="true"></i><input id="global-search" name="search" value="{{ request('search') }}" placeholder="Search by name, phone number or email..."></label>
        <label><span class="sr-only">Product</span><select name="product_ids" id="product-filter"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" @if(request('product_ids') == $product->id) selected @endif>{{ $product->product }}</option>@endforeach</select></label>
        <label><span class="sr-only">Service</span><select name="service_ids" id="service-filter"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" @if(request('service_ids') == $service->id) selected @endif>{{ str_contains(strtolower($service->service), 'call not connected') ? 'DNP' : $service->service }}</option>@endforeach</select></label>
        <label><span class="sr-only">Staff</span><select name="representative_user_id"><option value="">All staff</option>@if($canFilterUnassignedLeads ?? false)<option value="na" @if(in_array(strtolower((string) request('representative_user_id')), ['na', 'n/a', 'unassigned'], true)) selected @endif>Unassigned</option>@endif @foreach($staff as $user)<option value="{{ $user->id }}" @if(request('representative_user_id') == $user->id) selected @endif>{{ $user->name }}</option>@endforeach</select></label>
        <label><span class="sr-only">Status</span><select name="status" id="status-filter"><option value="">All statuses</option><option value="na" @if(in_array(strtolower((string) request('status')), ['na', 'n/a'], true)) selected @endif>N/A</option>@foreach($statusOptions as $value => $label)<option value="{{ $value }}" @if((string) request('status') !== '' && (string) request('status') === (string) $value) selected @endif>{{ $label }}</option>@endforeach</select></label>
    </div>
    <div class="leads-date-row">
        <div class="leads-date-tabs" aria-label="Date filter type">
            @foreach(['service' => 'Service date', 'created' => 'Created date', 'followup' => 'Follow-up date'] as $kind => $label)
                <button type="button" data-date-kind="{{ $kind }}" aria-pressed="{{ $kind === 'service' ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        @foreach(['service' => ['from_date', 'to_date'], 'created' => ['from_created_date', 'to_created_date'], 'followup' => ['from_followup_date', 'to_followup_date']] as $kind => $fields)
            <div class="leads-date-range" data-date-panel="{{ $kind }}" @if($kind !== 'service') hidden @endif>
                <label><span class="sr-only">From {{ $kind }} date</span><input type="date" name="{{ $fields[0] }}" value="{{ request($fields[0]) }}"></label>
                <label><span class="sr-only">To {{ $kind }} date</span><input type="date" name="{{ $fields[1] }}" value="{{ request($fields[1]) }}"></label>
                @if($kind === 'followup')
                    <label><span class="sr-only">Follow-up period</span><select name="followup_period"><option value="">Any period</option>@foreach(['today' => 'Today', 'overdue' => 'Overdue', 'upcoming' => 'Upcoming'] as $value => $label)<option value="{{ $value }}" @if(request('followup_period') === $value) selected @endif>{{ $label }}</option>@endforeach</select></label>
                @endif
            </div>
        @endforeach
        <div class="leads-filter-actions"><a href="{{ route('admin.clients.index') }}" class="leads-button"><i class="ri-refresh-line" aria-hidden="true"></i>Clear</a><button type="submit" class="leads-button leads-primary"><i class="ri-filter-3-line" aria-hidden="true"></i>Apply</button></div>
    </div>
    <div class="leads-applied-dates" aria-live="polite"></div>
    @if($isSuperAdmin ?? false)
        <label class="leads-allocation">Allocation Status<select id="allocation-status-filter" name="allocation_status"><option value="">All</option><option value="queued" @if(request('allocation_status') === 'queued') selected @endif>Queued</option><option value="not_queued" @if(request('allocation_status') === 'not_queued') selected @endif>Not Queued</option></select></label>
    @endif
    <details class="leads-extra-filters" @if(request()->filled('name') || request()->filled('email') || request()->filled('phone')) open @endif><summary>More filters</summary><div class="leads-extra-grid">@foreach(['name' => 'Name', 'email' => 'Email', 'phone' => 'Phone'] as $field => $label)<label>{{ $label }}<input name="{{ $field }}" value="{{ request($field) }}"></label>@endforeach</div></details>
</form>
