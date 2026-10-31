@php
    $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRecords($vouchers);
    $activeStatus = (string) request('status', '1');
    $activeStatus = ['completed' => '2', 'active' => '1', '' => '1'][$activeStatus] ?? $activeStatus;
    $queueUrl = fn ($status) => route('admin.account.invoices', array_merge(request()->except(['page', 'status', 'open_invoice']), ['status' => $status]));
    $hasFilters = collect(request()->except(['page', 'status', 'open_invoice', 'per_page']))->filter(fn ($value) => filled($value))->isNotEmpty();
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/invoices.css') }}?v=1">
<main class="crm-invoices">
    @if(isset($errors) && $errors->any())<div class="ai-filter-errors" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <header class="ai-heading"><div><h1>Invoices</h1><p>Booking invoices, billing details and finalization</p></div>
        <a class="ai-button primary" href="{{ $queueUrl('awaiting') }}#invoice-queue"><i class="ri-file-add-line" aria-hidden="true"></i> Generate invoice</a>
    </header>
    <section class="ai-stats" aria-label="Invoice summary">
        <div><strong>{{ $invoiceStats['total'] }}</strong><span>Eligible bookings</span><small>Within the selected filters</small></div>
        <div><strong>{{ $invoiceStats['awaiting'] }}</strong><span>Awaiting generation</span><small>No invoice created yet</small></div>
        <div><strong>{{ $invoiceStats['finalized'] }}</strong><span>Finalized invoices</span><small>Locked against further changes</small></div>
        @php($pageProfit = $invoicesData->sum('profit_loss'))
        <div><strong class="{{ $pageProfit < 0 ? 'ai-loss' : 'ai-profit' }}">{{ $pageProfit < 0 ? '-' : '' }}&#8377;{{ number_format(abs($pageProfit), 2) }}</strong><span>Profit / loss</span><small>Invoices on the current page</small></div>
    </section>
    <nav class="ai-tabs" aria-label="Invoice queues">
        @foreach (['1' => ['Needs action', $invoiceStats['awaiting'] + $invoiceStats['generated']], 'all' => ['All', $invoiceStats['total']], 'awaiting' => ['Awaiting generation', $invoiceStats['awaiting']], 'generated' => ['Generated', $invoiceStats['generated']], '2' => ['Finalized', $invoiceStats['finalized']]] as $key => [$label, $count])
            <a href="{{ $queueUrl($key) }}" @if($activeStatus === (string) $key || ($key === '2' && $activeStatus === 'completed')) aria-current="page" @endif>{{ $label }} <span>{{ $count }}</span></a>
        @endforeach
    </nav>
    <section class="ai-queue" id="invoice-queue">
        <header class="ai-queue-head"><h2>Invoice queue</h2><button type="button" id="toggle-filters" class="ai-button" aria-expanded="{{ $hasFilters ? 'true' : 'false' }}" aria-controls="filter-section"><i class="ri-filter-3-line" aria-hidden="true"></i> Filters <i class="ti {{ $hasFilters ? 'ti-chevron-up' : 'ti-chevron-down' }}" id="filter-icon" aria-hidden="true"></i></button></header>
        <div id="filter-section" @if(!$hasFilters) style="display:none" @endif>
            <form method="GET" action="{{ route('admin.account.invoices') }}" id="filter-form" class="ai-filters">
                @foreach (['service_from' => 'Service date from', 'service_to' => 'Service date to', 'invoice_created_from' => 'Invoice created from', 'invoice_created_to' => 'Invoice created to', 'finalized_from' => 'Finalize date from', 'finalized_to' => 'Finalize date to'] as $name => $label)
                    <label class="ai-field" for="{{ $name }}"><span>{{ $label }}</span><input type="date" id="{{ $name }}" name="{{ $name }}" value="{{ request($name) }}"></label>
                @endforeach
                <label class="ai-field" for="service_name"><span>Service</span><select name="service_name" id="service_name"><option value="">All services</option>@foreach($services as $service)<option value="{{ $service->id }}" {{ request('service_name') == $service->id ? 'selected' : '' }}>{{ $service->service }}</option>@endforeach</select></label>
                <label class="ai-field" for="client_id"><span>Client</span><select name="client_id" id="client_id"><option value="">All clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>@endforeach</select></label>
                <label class="ai-field" for="status"><span>Invoice status</span><select name="status" id="status">@foreach(['1' => 'Needs action', 'all' => 'All invoices', 'awaiting' => 'Awaiting generation', 'generated' => 'Generated', '2' => 'Finalized'] as $value => $label)<option value="{{ $value }}" {{ $activeStatus === (string) $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
                <label class="ai-field" for="invoice-search"><span>Search</span><input id="invoice-search" name="search" type="search" value="{{ request('search') }}" placeholder="Name, phone or invoice number"></label>
                <input type="hidden" name="per_page" value="{{ $vouchers->perPage() }}">
                <div class="ai-filter-actions"><button type="button" class="ai-button" id="reset-filters" title="Reset filters"><i class="ri-refresh-line" aria-hidden="true"></i> Reset</button><button class="ai-button primary" type="submit">Apply filters</button></div>
            </form>
        </div>
        <div class="ai-table-tools"><span>{{ $vouchers->total() }} {{ $vouchers->total() === 1 ? 'booking' : 'bookings' }}</span><form method="GET" action="{{ route('admin.account.invoices') }}">@foreach(request()->except(['page','per_page']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach<label for="invoice-rows">Rows <select id="invoice-rows" name="per_page" onchange="this.form.submit()">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ $vouchers->perPage() === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label></form></div>
        <div class="ai-table-wrap"><table class="ai-table" aria-label="Invoices"><colgroup><col style="width:4%"><col style="width:22%"><col style="width:16%"><col style="width:10%"><col style="width:11%"><col style="width:11%"><col style="width:13%"><col style="width:13%"></colgroup>
            <thead><tr><th scope="col">#</th><th scope="col">Client / billing</th><th scope="col">Product / sales</th><th scope="col">Service date</th><th scope="col">Invoice created</th><th scope="col">Invoice finalize date</th><th scope="col">Amount / P&amp;L</th><th scope="col">Actions</th></tr></thead>
            <tbody>@forelse($invoicesData as $index => $invoice)
                @php($finalized = $invoice['invoice_status'] === 2)
                @php($voucher = $vouchers->firstWhere('id', $invoice['id']))
                @php($canRegenerateInvoice = auth()->check() && (in_array(optional(auth()->user()->userType)->user_type, array_merge(\App\Models\UserType::ADMIN_ROLES, \App\Models\UserType::ACCOUNTS_ROLES, \App\Models\UserType::OWNER_ROLES), true)))
                <tr class="ai-invoice-row" data-invoice-id="{{ $invoice['id'] }}" tabindex="0" aria-label="View invoice details for {{ $invoice['client']['name'] }}">
                    <td data-label="#">{{ ($vouchers->firstItem() ?: 1) + $index }}</td>
                    <td data-label="Client / billing"><div><strong>{{ $invoice['client']['name'] }}</strong><a class="ai-phone" href="tel:{{ $invoice['client']['phone'] }}">{{ $invoice['client']['phone'] }}</a><small>{{ $invoice['invoice_company_name'] !== 'N/A' ? $invoice['invoice_company_name'] : 'Individual customer' }}</small>@if($invoice['invoice_gst_number'] !== 'N/A')<small>GSTIN {{ $invoice['invoice_gst_number'] }}</small>@endif<small class="ai-number">{{ $invoice['existing_invoice_id'] ?: 'Not generated' }}</small></div></td>
                    <td data-label="Product / sales"><div><strong>{{ ($tableProductNames[$voucher?->lead_id] ?? '') ?: 'Not specified' }}</strong><small>Sales: {{ $invoice['sales_person_name'] }}</small></div></td>
                    <td data-label="Service date">{{ $invoice['ride']['from_date'] ? \Carbon\Carbon::parse($invoice['ride']['from_date'])->format('d M Y') : 'Not set' }}</td>
                    <td data-label="Invoice created">{{ $invoice['invoice_created_at']?->format('d M Y') ?: '--' }}</td>
                    <td data-label="Invoice finalize date">{{ $invoice['finalized_date']?->format('d M Y') ?: ($finalized ? 'Not recorded' : '--') }}</td>
                    <td data-label="Amount / P&L"><div><strong>&#8377;{{ number_format($invoice['payment']['total_amount'], 2) }}</strong><small class="{{ $invoice['profit_loss'] < 0 ? 'ai-loss' : 'ai-profit' }}">P/L {{ $invoice['profit_loss'] < 0 ? '-' : '+' }}&#8377;{{ number_format(abs($invoice['profit_loss']), 2) }}</small></div></td>
                    <td data-label="Actions"><div class="ai-actions">
                        @if(!$invoice['existing_invoice'])<button type="button" class="ai-button primary generate-invoice-btn" data-invoice-id="{{ $invoice['id'] }}" data-client-name="{{ $invoice['client']['name'] }}" data-company-name="{{ $invoice['client']['company_name'] }}" data-gst-number="{{ $invoice['client']['gst_number'] }}">Generate</button>
                        @elseif(!$finalized)<button type="button" class="ai-button primary finalize-invoice-btn" data-invoice-id="{{ $invoice['id'] }}" data-client-name="{{ $invoice['client']['name'] }}">Finalize</button>@endif
                        @if($invoice['existing_invoice'] && $canRegenerateInvoice)<button type="button" class="ai-button regenerate-invoice-btn" data-invoice-id="{{ $invoice['id'] }}">Regenerate</button>@endif
                        @if(!$finalized)<button type="button" class="ai-button ai-gst-btn" data-invoice-id="{{ $invoice['id'] }}" title="Optional GST and billing details">GST details</button>@endif
                        @if($invoice['existing_invoice'])<a class="ai-button ai-icon" href="{{ route('admin.account.invoices.pdf', $invoice['id']) }}" target="_blank" rel="noopener" aria-label="Preview invoice PDF" title="Preview PDF"><i class="ri-file-pdf-2-line" aria-hidden="true"></i></a><a class="ai-button ai-icon" href="{{ route('admin.account.invoices.download', $invoice['id']) }}" aria-label="Download invoice" title="Download invoice"><i class="ri-download-2-line" aria-hidden="true"></i></a>@endif
                    </div></td>
                </tr>
            @empty<tr class="ai-empty"><td colspan="8">No invoices match these filters.</td></tr>@endforelse</tbody>
        </table></div>
        @if($vouchers->hasPages())<div class="ai-pagination">{{ $vouchers->appends(request()->except('page'))->links() }}</div>@endif
    </section>
</main>
<div id="invoice-finalize-modal" class="hs-overlay hidden ti-modal ai-finalize-modal" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="invoice-finalize-title">
    <div class="ti-modal-box ai-finalize-box"><div class="ti-modal-content"><form id="invoice-finalize-form">
        <header><h2 id="invoice-finalize-title">Finalize invoice</h2><button type="button" class="ai-button ai-icon" data-hs-overlay="#invoice-finalize-modal" aria-label="Close"><i class="ri-close-line" aria-hidden="true"></i></button></header>
        <div class="ai-finalize-body"><p id="invoice-finalize-client"></p><input type="hidden" id="invoice-finalize-id"><label class="ai-field" for="invoice-finalize-date"><span>Invoice finalize date</span><input type="date" id="invoice-finalize-date" required value="{{ now()->toDateString() }}"></label><p class="ai-finalize-note">Finalizing locks the invoice against further changes.</p><p id="invoice-finalize-error" class="ai-loss" role="alert" hidden></p></div>
        <footer><button type="button" class="ai-button" data-hs-overlay="#invoice-finalize-modal">Cancel</button><button type="submit" class="ai-button primary" id="invoice-finalize-save">Save and finalize</button></footer>
    </form></div></div>
</div>
