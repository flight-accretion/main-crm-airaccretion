@extends('admin.layouts.header')

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/customer-call-queue.css') }}">
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseStatuses = $caseStatuses ?? [];
        $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRelatedLeads($cases);
        $vendorFollowupRole = optional(auth()->user()->userType)->user_type;
        $canCreateVendorFollowup = $vendorFollowupRole === \App\Models\UserType::SUPER_ADMIN
            || in_array($vendorFollowupRole, \App\Models\UserType::OPERATIONS_ROLES, true);
    @endphp

    <div class="ccq-page">
        <header class="ccq-heading">
            <div><p class="ccq-eyebrow">Operations Queue</p><h1>Customer Call Queue</h1><p>Contact customers, record call outcomes and close Operations call tasks.</p></div>
            <span class="ccq-counter">Pending calls <strong>{{ number_format($cases->total()) }}</strong></span>
        </header>

        @if(session('success'))<div class="ccq-notice" role="status">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="ccq-notice ccq-error" role="alert">{{ session('error') }}</div>@endif
        @if($errors->any())<div class="ccq-notice ccq-error" role="alert">{{ $errors->first() }}</div>@endif

        <section class="ccq-card">
            <header class="ccq-card-header">
                <div class="ccq-card-title"><h2>Search Filters</h2><p>Filter by service date, customer, product, salesperson or Operations user.</p></div>
                <button type="button" class="ccq-filter-toggle" id="ccq-filter-toggle" aria-expanded="false" aria-controls="ccq-filter-body" title="Show filters"><i class="ri-arrow-down-s-line" aria-hidden="true"></i></button>
            </header>
            <div id="ccq-filter-body" class="ccq-filter-body" hidden>
                <form method="GET" action="{{ route('admin.operations.queue', 'customer_call') }}" id="ccq-filter-form">
                    <div class="ccq-filter-grid">
                        @foreach ([['from_service_date','From Service Date','date'],['to_service_date','To Service Date','date'],['from_created_date','From Created Date','date'],['to_created_date','To Created Date','date'],['from_date','From Opened Date','date'],['to_date','To Opened Date','date'],['name','Customer Name','text'],['email','Email','text'],['phone','Phone','text']] as [$name,$label,$inputType])
                            <label>{{ $label }}<input type="{{ $inputType }}" name="{{ $name }}" value="{{ $filters[$name] ?? '' }}"></label>
                        @endforeach
                        <label>Salesperson<select name="representative_user_id"><option value="">All staff</option>@foreach($staff as $user)<option value="{{ $user->id }}" {{ ($filters['representative_user_id'] ?? '') === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
                        <label>Lead status<select name="lead_status"><option value="">All statuses</option><option value="na" {{ in_array(strtolower((string)($filters['lead_status'] ?? '')), ['na','n/a'], true) ? 'selected' : '' }}>N/A</option>@foreach($leadStatusOptions as $value => $label)<option value="{{ $value }}" {{ (string)($filters['lead_status'] ?? '') === (string)$value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
                        <label>Operations status<select name="status"><option value="">All statuses</option>@foreach($caseStatuses as $status)<option value="{{ $status }}" {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label>
                        <label>Service<select name="service_ids"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" {{ ($filters['service_ids'] ?? '') === (string)$service->id ? 'selected' : '' }}>{{ $service->service }}</option>@endforeach</select></label>
                        <label>Product<select name="product_ids"><option value="">All products</option>@foreach($products as $product)<option value="{{ $product->id }}" {{ ($filters['product_ids'] ?? '') === (string)$product->id ? 'selected' : '' }}>{{ $product->product }}</option>@endforeach</select></label>
                        <label>Operations user<select name="operations_user_id"><option value="">All users</option>@foreach($operationUsers as $user)<option value="{{ $user->id }}" {{ ($filters['operations_user_id'] ?? '') === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
                        <div class="ccq-filter-actions"><button class="ccq-button ccq-primary" type="submit"><i class="ri-filter-3-line" aria-hidden="true"></i> Apply filters</button><a class="ccq-button" href="{{ route('admin.operations.queue', 'customer_call') }}" title="Reset filters"><i class="ri-refresh-line" aria-hidden="true"></i> Reset</a></div>
                    </div>
                </form>
            </div>
        </section>

        <section class="ccq-card ccq-list">
            <header class="ccq-card-header">
                <div class="ccq-card-title"><h2>Customer Call List</h2><p>Pending call tasks waiting for Operations action.</p></div>
                <div class="ccq-list-controls">
                    <label>Show <select id="ccq-per-page" name="per_page" form="ccq-filter-form">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ (int)($filters['per_page'] ?? 25) === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select> entries</label>
                    <input id="ccq-search" name="search" form="ccq-filter-form" value="{{ $filters['search'] ?? '' }}" placeholder="Search customer, phone or product">
                </div>
            </header>
            <div class="ccq-table-wrap"><table class="ccq-table"><thead><tr><th>S.No</th><th>Client</th><th>Phone</th><th>Next Follow-up</th><th>Assigned</th><th>Service Date</th><th>Product</th><th>Status</th><th>Action</th></tr></thead><tbody>
                @forelse($cases as $key => $case)
                    @php
                        $lead = $case->lead; $client = optional($lead)->client; $segments = optional($lead)->rideSegments ?? collect();
                        $status = ucfirst(str_replace('_', ' ', $case->status));
                    @endphp
                    <tr>
                        <td data-label="S.No" class="ccq-serial">{{ $cases->firstItem() ? $cases->firstItem() + $key : $key + 1 }}</td>
                        <td data-label="Client" class="ccq-client"><strong>{{ optional($client)->name ?? 'N/A' }}</strong><small>Customer lead</small></td>
                        <td data-label="Phone">{{ optional($client)->contact_number ?? 'N/A' }}</td>
                        <td data-label="Next Follow-up">{{ optional($case->next_followup_at)->format('d M Y · H:i') ?? 'Not scheduled' }}</td>
                        <td data-label="Assigned">{{ optional(optional($lead)->representative)->name ?? 'N/A' }}</td>
                        <td data-label="Service Date" class="ccq-client">@if($segments->count())<strong>{{ optional($segments->first()->from_date)->format('d M Y') ?? 'N/A' }}</strong><small>to {{ optional($segments->last()->to_date)->format('d M Y') ?? 'N/A' }}</small>@else N/A @endif</td>
                        <td data-label="Product" class="ccq-product">{{ ($tableProductNames[$case->lead_id] ?? '') ?: 'N/A' }}</td>
                        <td data-label="Status"><span class="ccq-badge {{ $case->status === 'pending' ? 'ccq-pending' : '' }}">{{ $status }}</span></td>
                        <td data-label="Action"><div class="ccq-actions">@if($canCreateVendorFollowup && $case->lead_id)<a href="{{ route('admin.operations.vendor-followups.create', $case->lead_id) }}" class="ccq-button ccq-info">Vendor Follow-up</a>@endif<button type="button" class="ccq-button ccq-success complete-case-btn" data-complete-url="{{ route('admin.operations.case.complete', $case) }}" data-client-name="{{ optional($client)->name ?? 'this lead' }}">Complete</button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="ccq-empty">No pending records.</td></tr>
                @endforelse
            </tbody></table></div>
            <footer class="ccq-pagination"><span>Showing {{ $cases->firstItem() ?? 0 }}–{{ $cases->lastItem() ?? 0 }} of {{ number_format($cases->total()) }} results</span>{{ $cases->onEachSide(1)->links() }}</footer>
        </section>
    </div>

    @include('admin.pages.operations.partials.complete-modal')

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                var toggle = document.getElementById('ccq-filter-toggle'), body = document.getElementById('ccq-filter-body'), perPage = document.getElementById('ccq-per-page');
                toggle.addEventListener('click', function () { var open = body.hasAttribute('hidden'); body.toggleAttribute('hidden', !open); toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); toggle.querySelector('i').className = open ? 'ri-arrow-up-s-line' : 'ri-arrow-down-s-line'; });
                perPage.addEventListener('change', function () { document.getElementById('ccq-filter-form').submit(); });
            });
        </script>
    @endpush
@endsection
