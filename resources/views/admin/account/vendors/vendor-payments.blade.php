@extends('admin.layouts.header')
@section('content')
@php $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRecords($vendorPaymentsData ?? []); @endphp
@php
    $paymentsSummary = $paymentsSummary ?? [];
    $statusChipCounts = $statusChipCounts ?? [];
    // Compact rupee figure for the cards, e.g. ₹41.2L / ₹86K.
    $inrShort = function ($amount) {
        $amount = (float) $amount;
        if ($amount >= 10000000) return '₹' . round($amount / 10000000, 1) . 'Cr';
        if ($amount >= 100000) return '₹' . round($amount / 100000, 1) . 'L';
        if ($amount >= 1000) return '₹' . round($amount / 1000, 1) . 'K';
        return '₹' . number_format($amount, 0);
    };
    $currentStatus = request('status', '');
    // Chips only set the existing Payment Status filter; other filters are kept.
    $chipUrl = fn ($value) => route('admin.account.vendor-payments', array_filter(array_merge(request()->except(['page', 'status']), ['status' => $value]), fn ($v) => $v !== null && $v !== ''), false);
    $payNext = $paymentsSummary['pay_next'] ?? null;
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vendor-payments.css') }}">
<div class="crm-vendor-payments">
    <header class="vp-heading">
        <div>
            <h1>Vendor Payments</h1>
            <p>What we owe, what is overdue, what is settled</p>
        </div>
        <!-- <a href="{{ route('admin.account.vendor-payments.export', [], false) }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
            class="vp-ghost"><i class="ri-download-line" aria-hidden="true"></i> Export (filtered)</a> -->
    </header>

    <section class="vp-stats" aria-label="Vendor payment summary">
        <div class="vp-stat is-brand">
            <strong>{{ $inrShort($paymentsSummary['payable_amount'] ?? 0) }}</strong>
            <span>Payable to vendors</span>
            <small>{{ number_format($paymentsSummary['payable_vendors'] ?? 0) }} vendor {{ \Illuminate\Support\Str::plural('entry', $paymentsSummary['payable_vendors'] ?? 0) }} with balance</small>
        </div>
        <div class="vp-stat is-red">
            <strong>{{ number_format($paymentsSummary['overdue_count'] ?? 0) }} <small>· {{ $inrShort($paymentsSummary['overdue_amount'] ?? 0) }}</small></strong>
            <span>Overdue — past service date</span>
            <small>Balance still unpaid</small>
        </div>
        <div class="vp-stat is-green">
            <strong>{{ number_format($paymentsSummary['paid_month_count'] ?? 0) }} <small>· {{ $inrShort($paymentsSummary['paid_month_amount'] ?? 0) }}</small></strong>
            <span>Paid this month</span>
            <small>Payments dated {{ now()->format('M Y') }}</small>
        </div>
        <div class="vp-stat is-amber">
            <strong>{{ $inrShort($paymentsSummary['refund_due_amount'] ?? 0) }}</strong>
            <span>Refund due from vendors</span>
            <small>{{ number_format($paymentsSummary['refund_due_count'] ?? 0) }} vendor {{ \Illuminate\Support\Str::plural('entry', $paymentsSummary['refund_due_count'] ?? 0) }}</small>
        </div>
    </section>

    @if($payNext)
        <div class="vp-paynext" role="status">
            <i class="ri-error-warning-line" aria-hidden="true"></i>
            <span><strong>Pay next:</strong> {{ $payNext['vendor'] }} ₹{{ number_format($payNext['amount'], 2) }}@if($payNext['client']) — {{ $payNext['client'] }}@endif
                @if($payNext['service_date']) · service date {{ $payNext['service_date']->format('d M Y') }}, {{ (int) $payNext['service_date']->copy()->startOfDay()->diffInDays(now()->startOfDay()) }} days past @endif</span>
        </div>
    @endif

    <nav class="vp-chips" aria-label="Payment status">
        <a href="{{ $chipUrl('unpaid') }}" class="{{ $currentStatus === 'unpaid' ? 'is-on' : '' }}">Unpaid · {{ number_format($statusChipCounts['unpaid'] ?? 0) }}</a>
        <a href="{{ $chipUrl('partial') }}" class="{{ $currentStatus === 'partial' ? 'is-on' : '' }}">Partial · {{ number_format($statusChipCounts['partial'] ?? 0) }}</a>
        <a href="{{ $chipUrl('paid') }}" class="{{ $currentStatus === 'paid' ? 'is-on' : '' }}">Paid · {{ number_format($statusChipCounts['paid'] ?? 0) }}</a>
        <a href="{{ $chipUrl('') }}" class="{{ $currentStatus === '' ? 'is-on' : '' }}">All · {{ number_format($statusChipCounts['all'] ?? 0) }}</a>
    </nav>

    {{-- Same filters, names and IDs as before (Select2 still applies via js-example-basic-single). --}}
    <form method="GET" action="{{ route('admin.account.vendor-payments', [], false) }}" id="filter-form" class="vp-filters">
        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
        <div class="vp-field">
            <label for="client_id">Client Name</label>
            <select class="js-example-basic-single w-full form-control-sm" name="client_id" id="client_id">
                <option value="">All Clients</option>
                @foreach ($clients as $client)
                    <option value="{{ $client->id }}" {{ request('client_id') == $client->id ? 'selected' : '' }}>{{ $client->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="vp-field">
            <label for="vendor_id">Vendor Name</label>
            <select class="js-example-basic-single w-full form-control-sm" name="vendor_id" id="vendor_id">
                <option value="">All Vendors</option>
                @foreach ($vendors as $vendor)
                    <option value="{{ $vendor->id }}" {{ request('vendor_id') == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="vp-field">
            <label for="service_id">Service</label>
            <select class="js-example-basic-single w-full form-control-sm" name="service_id" id="service_id">
                <option value="">All Services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" {{ request('service_id') == $service->id ? 'selected' : '' }}>{{ $service->service }}</option>
                @endforeach
            </select>
        </div>
        <div class="vp-field">
            <label for="status">Payment Status</label>
            <select class="ti-form-select form-control-sm rounded-sm" name="status" id="status">
                <option value="">All Status</option>
                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Full Paid</option>
                <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial Paid</option>
                <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
            </select>
        </div>
        <div class="vp-filter-actions">
            <button type="button" class="vp-ghost" title="Clear Filters" onclick="clearFilters()">Reset</button>
            <button type="submit" class="vp-primary">Apply Filters</button>
        </div>
    </form>

    <section class="box custom-box vp-list">
        <div class="box-header">
            <div class="box-title">Vendor Payment List</div>
        </div>
        <div class="box-body">
            <div class="vp-table-wrap">
                {{-- One group (tbody) per client: client, service date and actions span that client's vendor lines. --}}
                <table class="table table-datatable server-paginated no-horizontal-scroll vp-table" width="100%" data-empty-msg="No vendor payments found">
                    <thead>
                        <tr>
                            <th class="vp-col-sno">S.No</th>
                            <th class="vp-col-client">Client</th>
                            <th>Service date</th>
                            <th class="vp-col-vendor">Vendor / Product</th>
                            <th>Vendor Service Cost</th>
                            <th>Balance Amount</th>
                            <th>Paid Amount</th>
                            <th>Date Paid</th>
                            <th>Status</th>
                            <th class="vp-col-action">Actions</th>
                        </tr>
                    </thead>
                    @forelse($vendorPaymentsData ?? [] as $index => $payment)
                        @php
                            $vendorRows = $payment['vendors'];
                            $span = max(1, count($vendorRows));
                            $hasBalance = collect($vendorRows)->contains(fn ($v) => (float) $v['balance_amount'] > 0);
                            $isOverdue = $hasBalance && $payment['service_date'] && $payment['service_date']->lt(now()->startOfDay());
                        @endphp
                        <tbody class="vp-group {{ $isOverdue ? 'is-overdue' : '' }}">
                            @foreach ($vendorRows as $vendorIndex => $vendor)
                                @php
                                    $tone = str_contains($vendor['status']['class'], 'success') ? 'green' : (str_contains($vendor['status']['class'], 'warning') ? 'amber' : 'red');
                                @endphp
                                <tr class="{{ $vendorIndex > 0 ? 'vp-more' : '' }}">
                                    @if ($vendorIndex === 0)
                                        <td rowspan="{{ $span }}" data-label="S.No" class="vp-sno">{{ (isset($vendorPaymentsPaginator) && $vendorPaymentsPaginator->firstItem() ? $vendorPaymentsPaginator->firstItem() : 1) + $index }}</td>
                                        <td rowspan="{{ $span }}" data-label="Client">
                                            <strong>{{ $payment['client_name'] }}</strong>
                                            @if($span > 1)<small>{{ $span }} vendors</small>@endif
                                        </td>
                                        <td rowspan="{{ $span }}" data-label="Service date">
                                            <strong>{{ $payment['service_date'] ? $payment['service_date']->format('d M Y') : 'N/A' }}</strong>
                                            @if($isOverdue)<small class="vp-overdue-note">{{ (int) $payment['service_date']->copy()->startOfDay()->diffInDays(now()->startOfDay()) }} days past</small>@endif
                                        </td>
                                    @endif
                                    <td data-label="Vendor / Product">
                                        <strong>{{ $vendor['vendor_name'] }}</strong>
                                        <small>{{ ($tableProductNames[$payment['lead_id']] ?? '') ?: 'Not specified' }}</small>
                                    </td>
                                    <td data-label="Vendor Service Cost">₹{{ number_format($vendor['adjusted_vendor_payable'] ?? $vendor['vendor_service_cost'], 2) }}</td>
                                    <td data-label="Balance Amount" class="vp-balance {{ (float) $vendor['balance_amount'] > 0 ? 'is-due' : '' }}">₹{{ number_format($vendor['balance_amount'], 2) }}</td>
                                    <td data-label="Paid Amount">₹{{ number_format($vendor['net_paid_amount'] ?? $vendor['paid_amount'], 2) }}</td>
                                    <td data-label="Date Paid">{{ $vendor['paid_date'] }}</td>
                                    <td data-label="Status"><span class="vp-tag {{ $tone }}">{{ $vendor['status']['status'] }}</span></td>
                                    @if ($vendorIndex === 0)
                                        <td rowspan="{{ $span }}" data-label="Actions" class="vp-actions">
                                            <div>
                                                <a aria-label="anchor"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-vendor-payment-btn"
                                                    data-payment-id="{{ $payment['view_id'] ?? $vendor['id'] }}"
                                                    data-hs-overlay="#view-vendor-payment" title="View">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                @if(!empty($payment['voucher_id']))
                                                    <!-- Open PDF preview to match invoice module rendering -->
                                                    <a aria-label="anchor"
                                                        href="{{ route('admin.account.invoices.pdf', $payment['voucher_id']) }}"
                                                        target="_blank" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-dark-full"
                                                        title="Preview Invoice (PDF)">
                                                        <i class="ri-file-text-line"></i>
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    @empty
                        <tbody><tr class="vp-empty-row"><td colspan="10">No vendor payments found.</td></tr></tbody>
                    @endforelse
                </table>
            </div>
            @if(isset($vendorPaymentsPaginator) && $vendorPaymentsPaginator->hasPages())
                <div class="mt-4">
                    {{ $vendorPaymentsPaginator->onEachSide(1)->appends(request()->except('page'))->links() }}
                </div>
            @endif
        </div>
    </section>
</div>

<!-- Vendor Payment Details dialog (redesigned like Payment Review). Element IDs used by the scripts are unchanged. -->
<div id="view-vendor-payment" class="view-vendor-payment hs-overlay hidden ti-modal vp-modal" tabindex="-1"
    role="dialog" aria-modal="true" aria-labelledby="vp-modal-title">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out vp-modal-box">
        <div class="ti-modal-content vp-modal-content">
            <div class="vp-modal-head">
                <div class="vp-modal-heading">
                    <h5 id="vp-modal-title">Vendor payments — <span id="modal-client-name">Loading...</span></h5>
                    <div class="vp-modal-sub">
                        <span class="vp-pill amber" id="vp-modal-status" hidden></span>
                        <a href="#" id="vp-open-lead" target="_blank" rel="noopener" hidden>Open lead <span aria-hidden="true">&rarr;</span></a>
                        <a href="#" id="vp-open-invoice" target="_blank" rel="noopener" hidden>Invoice PDF <span aria-hidden="true">&rarr;</span></a>
                    </div>
                </div>
                <button type="button" class="vp-modal-close" data-hs-overlay="#view-vendor-payment" aria-label="Close">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </div>

            <div class="vp-verdict">
                <div class="vp-verdict-cell"><span>Vendor payable</span><strong id="vp-sum-payable">—</strong></div>
                <div class="vp-verdict-cell"><span>Net paid to vendors</span><strong class="is-ok" id="vp-sum-paid">—</strong></div>
                <div class="vp-verdict-cell"><span>Balance</span><strong id="vp-sum-balance">—</strong></div>
                <div class="vp-verdict-note" id="vp-sum-note" hidden></div>
            </div>

            <div class="ti-modal-body vp-modal-body view-vendor-payment-body">
                <div class="vp-modal-col vp-modal-left">
                    <section class="vp-block">
                        <h6>Client information</h6>
                        <div class="grid grid-cols-12 gap-6 vp-info-grid" id="client-info">
                            <!-- Dynamic client info will be loaded here -->
                        </div>
                    </section>
                    <section class="vp-block">
                        <h6>Travel information</h6>
                        <div id="travel-information-container" class="vp-travel">
                            <!-- Travel information will be populated dynamically -->
                            <p class="vp-muted">Loading travel information...</p>
                        </div>
                    </section>
                    <section class="vp-block">
                        <h6>Service information</h6>
                        <div class="grid grid-cols-12 gap-6 vp-info-grid" id="service-info">
                            <!-- Dynamic service info will be loaded here -->
                        </div>
                    </section>
                    <section class="vp-block">
                        <h6>Client payment history</h6>
                        <div id="client-payment-history" class="vp-history">
                            <!-- Client payment audit trail will be rendered here -->
                        </div>
                    </section>
                </div>

                <div class="vp-modal-col vp-modal-right">
                    <section class="vp-block">
                        <h6>Vendors</h6>
                        <p class="vp-modal-hint">Record a payment with "Add Vendor Payment" on the vendor below; each vendor keeps its own payment history.</p>
                        <!-- Dynamic Vendor Information Sections -->
                        <div id="vendor-sections">
                            <!-- Dynamic vendor sections will be loaded here -->
                        </div>
                    </section>
                </div>
                <!-- Narration -->
                <!-- <div class="box">
                            <div class="box-header flex justify-between items-center">
                                <h5 class="box-title">Narration</h5>
                            </div>
                            <div class="box-body bg-gray-50">
                                <div class="grid grid-cols-12 gap-6">
                                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <textarea class="form-control" id="global-narration" rows="3" placeholder="Add notes about this payment ..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                <!-- <div class="mt-5">
                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Generate Invoice</button>
                </div> -->
            </div>

            <div class="vp-modal-foot">
                <span class="vp-muted">Payments recorded here need a receipt and are logged in each vendor's history.</span>
                <button type="button" class="vp-ghost" data-hs-overlay="#view-vendor-payment">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Helper to open Add Vendor Payment modal programmatically -->
<a id="open-add-vendor-modal" data-hs-overlay="#add-vendor-payment-modal" style="display:none"></a>

<!-- Helper to open Receipt Viewer modal programmatically -->
<a id="open-receipt-viewer" data-hs-overlay="#receipt-viewer-modal" style="display:none"></a>

<!-- Add Vendor Payment Modal -->
<div id="add-vendor-payment-modal" class="hs-overlay hidden ti-modal">
    <div
        class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto">
        <div class="ti-modal-content">
            <div class="ti-modal-header">
                <h3 class="ti-modal-title">
                    Add Vendor Payment
                </h3>
                <button type="button" class="hs-dropdown-toggle ti-modal-close-btn"
                    data-hs-overlay="#add-vendor-payment-modal">
                    <span class="sr-only">Close</span>
                    <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                            fill="currentColor"></path>
                    </svg>
                </button>
            </div>
            <div class="ti-modal-body">
                <form id="vendor-payment-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="lead-vendor-payment-id" name="lead_vendor_payment_id">

                    <div class="grid grid-cols-12 gap-4">
                        <div class="col-span-12">
                            <label class="ti-form-label">Vendor Name</label>
                            <p class="text-gray-800 dark:text-white" id="modal-vendor-name">-</p>
                        </div>

                        <div class="col-span-4">
                            <label class="ti-form-label">Total Amount</label>
                            <input type="text" class="ti-form-input" id="modal-total-amount" readonly>
                        </div>

                        <div class="col-span-4">
                            <label class="ti-form-label">Balance Amount</label>
                            <input type="text" class="ti-form-input" id="modal-balance-amount" readonly>
                        </div>

                        <div class="col-span-4">
                            <label class="ti-form-label">Paid Amount *</label>
                            <input type="number" class="ti-form-input" id="modal-paid-amount" name="paid_amount"
                                step="0.01" required>
                            <div id="modal-paid-error" class="field-error text-danger text-sm mt-1"
                                style="display:none;"></div>
                        </div>

                        <div class="col-span-6">
                            <label class="ti-form-label">Payment Method *</label>
                            <select class="ti-form-select" name="payment_method" required>
                                <option value="">Select Payment Method</option>
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Online Payment">Online Payment</option>
                                <option value="UPI">UPI</option>
                                <option value="Credit Card">Credit Card</option>
                                <option value="Debit Card">Debit Card</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Other">Other</option>
                                <option value="Net Banking">Net Banking</option>
                                <option value="Website">Website</option>
                                <option value="Paid Directly to Vendor">Paid Directly to Vendor</option>
                            </select>
                        </div>

                        <div class="col-span-6">
                            <label class="ti-form-label">Paid Date *</label>
                            <input type="date" class="ti-form-input" name="paid_date" required>
                        </div>

                        <div class="col-span-12">
                            <label class="ti-form-label">Receipt Upload <span class="text-danger">*</span></label>
                            <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required>
                            <small class="text-muted">Accepted formats: JPG, PNG, PDF (Max: 2MB)</small>
                        </div>

                        <div class="col-span-12">
                            <label class="ti-form-label">Narration</label>
                            <textarea class="ti-form-input" name="narration" rows="3"
                                placeholder="Add payment notes..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="ti-modal-footer">
                <button type="button" class="ti-btn ti-btn-outline-secondary"
                    data-hs-overlay="#add-vendor-payment-modal">
                    Cancel
                </button>
                <button type="button" class="ti-btn bg-theme ti-btn-primary-full" id="save-vendor-payment">
                    Save Payment
                </button>
            </div>
        </div>
    </div>
</div>

<div id="global-loader" style="
    display:none;
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.6);
    z-index:999999;
    align-items:center;
    justify-content:center;
">
    <div style="
        background:white;
        padding:30px 40px;
        border-radius:12px;
        text-align:center;
        width:300px;
    ">
        <div class="loader-spinner mb-3"></div>
        <h5 style="font-weight:600;">Processing Payment...</h5>
        <p style="font-size:13px; color:#666;">
            Sending email & WhatsApp notification to vendor...
        </p>
    </div>
</div>

@stop

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const VENDOR_RECEIPT_MAX_SIZE_BYTES = 2 * 1024 * 1024;
    const VENDOR_RECEIPT_MAX_SIZE_LABEL = '2MB';

    function validateVendorReceiptFile(receiptInput, showFieldError) {
        const receiptFile = receiptInput && receiptInput.files && receiptInput.files[0] ? receiptInput.files[0] : null;

        if (!receiptFile) {
            return true;
        }

        if (receiptFile.size > VENDOR_RECEIPT_MAX_SIZE_BYTES) {
            showFieldError(receiptInput, `Receipt file must be ${VENDOR_RECEIPT_MAX_SIZE_LABEL} or smaller.`);
            receiptInput.value = '';
            return false;
        }

        return true;
    }

    function parseVendorPaymentResponse(response) {
        if (response.status === 413) {
            throw new Error(`Receipt file is too large for upload. Please compress it below ${VENDOR_RECEIPT_MAX_SIZE_LABEL} and try again.`);
        }

        if (response.status === 422) {
            return response.json().then(json => {
                throw {
                    validation: json.errors || {}
                };
            });
        }

        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return response.text().then(() => {
                throw new Error('Server returned an unexpected response. Please try again with a smaller receipt file.');
            });
        }

        return response.json().then(json => {
            if (!response.ok) {
                throw new Error(json.error || json.message || `Request failed with status ${response.status}`);
            }

            return json;
        });
    }

    document.addEventListener('DOMContentLoaded', function() {

            // Initialize DataTable with custom drawCallback for vendor payments
            const $vendorPaymentTable = $('.table-datatable').not('.server-paginated');
            const emptyMsg = $vendorPaymentTable.first().data('empty-msg') || 'No vendor payments found';
            if ($vendorPaymentTable.length && !$.fn.DataTable.isDataTable($vendorPaymentTable.first())) {
                $vendorPaymentTable.DataTable({
                    responsive: false,
                    scrollX: true,
                    columnDefs: [{
                        orderable: false,
                        targets: 0
                    }],
                    language: {
                        emptyTable: emptyMsg,
                        zeroRecords: emptyMsg
                    },
                    order: [[0, 'asc']],
                    drawCallback: function(settings) {
                        var api = this.api();
                        api.rows({ page: 'current' }).every(function(rowIdx) {
                            var cell = this.cell(rowIdx, 0).node();
                            $(cell).html(rowIdx + 1);
                        });
                        
                        // Reinitialize HSOverlay for newly drawn elements
                        if (typeof HSOverlay !== 'undefined' && HSOverlay.autoInit) {
                            HSOverlay.autoInit();
                        }
                        
                        // Reattach receipt handlers if needed
                        if (typeof attachReceiptViewHandlers === 'function') {
                            attachReceiptViewHandlers();
                        }
                    }
                });
            }

            // Use event delegation to handle view vendor payment button clicks
            // This ensures buttons work even after pagination/table redraw
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-vendor-payment-btn')) {
                    const button = e.target.closest('.view-vendor-payment-btn');
                    const paymentId = button.getAttribute('data-payment-id');
                    loadVendorPaymentDetails(paymentId);
                }
            });

            // If modal exists, handle balance calculation when paid amount changes
            const modalPaidAmount = document.getElementById('modal-paid-amount');
            if (modalPaidAmount) {
                modalPaidAmount.addEventListener('input', function() {
                    calculateBalance();
                });

                // Handle save vendor payment (modal fallback)
                document.getElementById('save-vendor-payment').addEventListener('click', function() {
                    saveVendorPayment();
                });
            }
            // attach handlers for any receipt preview icons present on initial load
            if (typeof attachReceiptViewHandlers === 'function') attachReceiptViewHandlers();
        });

        // Fills the dialog's pill, links and summary strip. Same formulas as the vendor cards; reads only.
        function updateVendorSummary(data) {
            const vendors = Array.isArray(data.all_vendor_payments) ? data.all_vendor_payments : [];
            const histories = data.per_vendor_histories || {};
            let payable = 0, netPaid = 0, balance = 0, refundDue = 0, owing = 0;
            vendors.forEach(vendorPayment => {
                const history = histories[vendorPayment.id] || {};
                const refunds = history.refund_history || vendorPayment.vendor_refunds || [];
                const latestRefund = Array.isArray(refunds) && refunds.length
                    ? refunds.slice().sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))[0] : null;
                const original = parseFloat(vendorPayment.total_vendor_service_amount || 0) || 0;
                const finalPayable = latestRefund && latestRefund.cancellation_amount !== undefined && latestRefund.cancellation_amount !== null
                    ? (parseFloat(latestRefund.cancellation_amount || 0) || 0) : original;
                const gross = parseFloat(history.paid_total || vendorPayment.paid_amount || 0) || 0;
                const refunded = parseFloat(history.refunded_total || 0) || 0;
                const net = Math.max(0, gross - refunded);
                const vendorBalance = Math.max(0, finalPayable - net);
                payable += finalPayable;
                netPaid += net;
                balance += vendorBalance;
                refundDue += Math.max(0, gross - finalPayable - refunded);
                if (vendorBalance > 0.005) owing++;
            });

            $('#vp-sum-payable').text('₹' + formatNumber(payable));
            $('#vp-sum-paid').text('₹' + formatNumber(netPaid));
            $('#vp-sum-balance').text('₹' + formatNumber(balance)).toggleClass('is-due', balance > 0.005).toggleClass('is-ok', balance <= 0.005);

            const $status = $('#vp-modal-status').removeClass('amber green');
            const vendorWord = vendors.length === 1 ? 'vendor' : 'vendors';
            if (owing) {
                $status.addClass('amber').text(`${vendors.length} ${vendorWord} · ${owing} to pay`);
            } else {
                $status.addClass('green').text(`${vendors.length} ${vendorWord} · fully paid`);
            }
            $status.prop('hidden', vendors.length === 0);

            let note = '';
            let tone = 'green';
            if (balance > 0.005) {
                note = `₹${formatNumber(balance)} still to pay across ${owing} ${owing === 1 ? 'vendor' : 'vendors'}.`;
                tone = 'amber';
            } else if (vendors.length) {
                note = '✓ All vendors are fully paid.';
            }
            if (refundDue > 0.005) {
                note += ` ₹${formatNumber(refundDue)} refund due from vendors.`;
                tone = tone === 'green' ? 'red' : tone;
            }
            $('#vp-sum-note').removeClass('green amber red').addClass(tone).text(note.trim()).prop('hidden', !note);

            const vendorPayment = data.vendor_payment || {};
            const leadUrl = @json(url('admin/leads'));
            $('#vp-open-lead').attr('href', vendorPayment.lead_id ? `${leadUrl}/${vendorPayment.lead_id}` : '#').prop('hidden', !vendorPayment.lead_id);
            const invoiceUrl = @json(route('admin.account.invoices.pdf', '__VOUCHER__'));
            $('#vp-open-invoice').attr('href', vendorPayment.voucher_id ? invoiceUrl.replace('__VOUCHER__', vendorPayment.voucher_id) : '#').prop('hidden', !vendorPayment.voucher_id);
        }

        function loadVendorPaymentDetails(paymentId) {
            window._currentVendorPaymentId = paymentId;
            // Clear the redesigned header bits while the next client loads.
            $('#vp-modal-status, #vp-sum-note, #vp-open-lead, #vp-open-invoice').prop('hidden', true);
            $('#vp-sum-payable, #vp-sum-paid, #vp-sum-balance').text('—');
            const clientNameEl = document.getElementById('modal-client-name');
            if (clientNameEl) clientNameEl.textContent = 'Loading...';
            const detailsUrl = @json(route('admin.account.vendor-payments.show', ['id' => '__PAYMENT_ID__'], false));
            fetch(detailsUrl.replace('__PAYMENT_ID__', encodeURIComponent(paymentId)))
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }

                    // Update modal title
                    document.getElementById('modal-client-name').textContent = data.client.name;

                    // Load client information
                    loadClientInfo(data.client);

                    // Load travel information
                    updateTravelInformation(data.travel_info);

                    // Load service information
                    loadServiceInfo(data.service_info);

                    // store global histories so JS can access them later
                    window._perVendorHistories = data.per_vendor_histories || {};
                    window._vendorPaymentGlobalHistory = data.payment_history || [];

                    // Load vendor sections (pass per-vendor histories so we can render paid totals and histories)
                    loadVendorSections(data.all_vendor_payments, window._perVendorHistories || {});

                    // Load client payment audit history (if present)
                    renderClientPaymentHistory(data.client_payment_history || []);

                    // Redesigned dialog header and summary strip (display only).
                    updateVendorSummary(data);
                })
                .catch(error => {
                    console.error('Error loading vendor payment details:', error);
                    alert('Error loading payment details');
                });
        }

        // When opening the modal via the hidden anchor, clear previous modal inputs (except totals set programmatically)
        document.getElementById('open-add-vendor-modal')?.addEventListener('click', function() {
            const form = document.getElementById('vendor-payment-form');
            if (!form) return;
            // clear previous errors
            form.querySelectorAll('.field-error').forEach(e => e.remove());
            // clear inputs except total/balance which are set when launching
            form.querySelectorAll('input[name], textarea[name], select[name]').forEach(el => {
                if (el.name === 'paid_amount' || el.name === 'payment_method' || el.name === 'paid_date' ||
                    el.name === 'narration' || el.name === 'receipt' || el.name === 'lead_vendor_payment_id'
                    ) {
                    el.value = '';
                }
            });
        });

        function renderClientPaymentHistory(history) {
            const container = document.getElementById('client-payment-history');
            if (!container) return;

            if (!history || history.length === 0) {
                container.innerHTML = '<p class="text-gray-500">No client payment audit records found</p>';
                return;
            }

            let html = '';
            history.forEach(item => {
                // Determine status badge based on payment_status from audit trail
                const statusCode = (item.payment_status !== null && item.payment_status !== undefined) ? item.payment_status : 'pending';
                const statusBadge = getPaymentStatusBadge(statusCode);

                const amount = (item.amount !== undefined && item.amount !== null) ? item.amount : (item.paid_amount !== undefined ? item.paid_amount : 0);
                const paidDate = item.paid_date || item.created_at || item.date || null;
                const method = item.payment_method || item.method || '';
                const notes = item.narration || item.notes || '';
                const receipt = item.receipt || null;

                const avatarSvg = getStatusAvatarSvg(statusCode);
                html += `
            <div class="flex items-center mb-4">
                <div class="me-4 gap-0">` + avatarSvg + `</div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="font-semibold mb-2 leading-none text-[1.25rem]">₹${formatNumber(amount)}</h5>
                            <p>${paidDate ? new Date(paidDate).toLocaleDateString() : '-'} • ${method}</p>
                            <p class="text-[#8c9097]">${notes || 'No notes'}</p>
                        </div>
                        <div class="text-end">
                            ${statusBadge}
                            ${item.created_by_name ? '<div class="text-xs text-gray-500 mt-1">' + item.created_by_name + '</div>' : ''}
                            ${receipt ? '<p><a href="' + getReceiptUrl(receipt) + '" target="_blank" class="receipt-link" data-receipt="' + receipt + '" data-method="' + (method || '') + '" data-date="' + (paidDate || '') + '" data-amount="' + amount + '" data-notes="' + (notes || '') + '"><i class="ri-eye-line text-primary cursor-pointer"></i></a> <a href="' + getReceiptUrl(receipt) + '" download class="receipt-download ms-2 text-muted" title="Download Receipt"><i class="ri-download-2-line"></i></a> Receipt</p>' : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
            });

            container.innerHTML = html;
            // attach click handlers for receipt preview icons
            attachReceiptViewHandlers();
        }

        function loadClientInfo(client) {
            const clientInfoHtml = `
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
            <p class="text-gray-800 dark:text-white">${client.name || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
            <p class="text-gray-800 dark:text-white">${client.email || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
            <p class="text-gray-800 dark:text-white">${client.contact_number || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp Number</label>
            <p class="text-gray-800 dark:text-white">${client.alternate_number || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
            <p class="text-gray-800 dark:text-white">${client.country?.name || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
            <p class="text-gray-800 dark:text-white">${client.city?.name || 'N/A'}</p>
        </div>
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
            <p class="text-gray-800 dark:text-white">${client.address || 'N/A'}</p>
        </div>
    `;
            document.getElementById('client-info').innerHTML = clientInfoHtml;
        }

        // Function to update travel information with multiple rides
        function updateTravelInformation(rides) {
            const container = $('#travel-information-container');

            if (!rides || rides.length === 0) {
                container.html(`
                    <div class="text-center py-4">
                        <p class="text-gray-500">No travel information found</p>
                    </div>
                `);
                return;
            }

            let travelHtml = '';

            if (rides.length === 1) {
                // Single trip - display in original format
                const ride = rides[0];
                travelHtml = `
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                            <p class="text-gray-800 dark:text-white">${ride.from_date ? new Date(ride.from_date).toLocaleDateString('en-GB') : '-'}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place</label>
                            <p class="text-gray-800 dark:text-white">${ride.from_place || '-'}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                            <p class="text-gray-800 dark:text-white">${ride.to_date ? new Date(ride.to_date).toLocaleDateString('en-GB') : '-'}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place</label>
                            <p class="text-gray-800 dark:text-white">${ride.to_place || '-'}</p>
                        </div>
                    </div>
                `;
            } else {
                // Multiple trips - display as collapsible trip segments
                travelHtml = `
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm font-semibold text-primary">Multiple Trip Segments (${rides.length} trips)</span>
                        <span class="badge bg-primary/10 text-primary rounded-full px-3 py-1 text-xs">Multi-Trip Journey</span>
                    </div>
                    <div class="hs-accordion-group" data-hs-accordion-always-open>
                `;

                rides.forEach((ride, index) => {
                    const fromDate = ride.from_date ? new Date(ride.from_date).toLocaleDateString(
                        'en-GB') : '-';
                    const toDate = ride.to_date ? new Date(ride.to_date).toLocaleDateString('en-GB') :
                        '-';
                    const fromTime = ride.from_date ? new Date(ride.from_date).toLocaleTimeString(
                        'en-GB', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) : '';
                    const toTime = ride.to_date ? new Date(ride.to_date).toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    const isFirst = index === 0;
                    const accordionId = `vendor-trip-accordion-${index}`;

                    travelHtml += `
                    <div class="hs-accordion ${isFirst ? 'active' : ''} border mb-2" id="${accordionId}-heading">
                        <button class="hs-accordion-toggle hs-accordion-active:text-primary bg-white border-b py-3 px-4 inline-flex items-center justify-between gap-x-3 w-full font-semibold text-start text-gray-800 hover:text-gray-500 disabled:opacity-50 disabled:pointer-events-none dark:hs-accordion-active:text-blue-500 dark:text-gray-200 dark:hover:text-gray-400 dark:focus:outline-none dark:focus:text-gray-400" aria-controls="${accordionId}-collapse">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-theme text-white rounded-full flex items-center justify-center text-sm font-semibold">
                                    ${index + 1}
                                </div>
                                <span>Trip Segment ${index + 1}: ${ride.from_place || '-'} → ${ride.to_place || '-'}</span>
                            </div>
                            <svg class="hs-accordion-active:hidden block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            <svg class="hs-accordion-active:block hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                        </button>
                        <div id="${accordionId}-collapse" class="hs-accordion-content bg-white pt-3 ${isFirst ? '' : 'hidden'} w-full overflow-hidden transition-[height] duration-300" aria-labelledby="${accordionId}-heading">
                            <div class="p-4 pt-0">
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Departure</label>
                                        <div class="bg-white p-2 rounded border">
                                            <p class="text-gray-800 dark:text-white font-medium text-sm">${ride.from_place || '-'}</p>
                                            <p class="text-gray-600 text-xs">${fromDate}${fromTime ? ' • ' + fromTime : ''}</p>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-2 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12 flex items-center justify-center">
                                        <div class="flex flex-col items-center">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                            </svg>
                                            <span class="text-xs text-gray-500">Journey</span>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Arrival</label>
                                        <div class="bg-white p-2 rounded border">
                                            <p class="text-gray-800 dark:text-white font-medium text-sm">${ride.to_place || '-'}</p>
                                            <p class="text-gray-600 text-xs">${toDate}${toTime ? ' • ' + toTime : ''}</p>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Duration</label>
                                        <div class="bg-white p-2 rounded border">
                                            <p class="text-gray-700 text-sm">${calculateDuration(ride.from_date, ride.to_date)}</p>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });

                // Close accordion group
                travelHtml += `</div>`;

                // Add journey summary
                const firstRide = rides[0];
                const lastRide = rides[rides.length - 1];
                const totalDuration = calculateTotalDuration(firstRide.from_date, lastRide.to_date);

                travelHtml += `
                    <div class="box">
                        <div class="box-header">
                            <h6 class="text-sm font-semibold text-blue-800 mb-2">Journey Summary</h6>
                        </div>
                        <div class="box-body">
                            <div class="grid grid-cols-12 gap-4 text-sm">
                                <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Start:</label>
                                    <p class="text-gray-800 dark:text-white">${firstRide.from_place || '-'}</p>
                                    <p class="text-gray-800 dark:text-white">${firstRide.from_date ? new Date(firstRide.from_date).toLocaleDateString('en-GB') : '-'}</p>
                                </div>
                                <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">End:</label>
                                    <p class="text-gray-800 dark:text-white">${lastRide.to_place || '-'}</p>
                                    <p class="text-gray-800 dark:text-white">${lastRide.to_date ? new Date(lastRide.to_date).toLocaleDateString('en-GB') : '-'}</p>
                                </div>
                                <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Duration:</label>
                                    <p class="text-gray-800 dark:text-white">${totalDuration}</p>
                                </div>
                                <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Segments:</label>
                                    <p class="text-gray-800 dark:text-white">${rides.length} trip(s)</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }

            container.html(travelHtml);
            
            // Reinitialize HSAccordion for dynamically added content
            if (typeof HSAccordion !== 'undefined') {
                HSAccordion.autoInit();
            }
        }

        // Helper function to calculate duration between two dates
        function calculateDuration(fromDate, toDate) {
            if (!fromDate || !toDate) return 'Not specified';

            const from = new Date(fromDate);
            const to = new Date(toDate);
            const diffTime = Math.abs(to - from);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays === 1) {
                return '1 day';
            } else if (diffDays < 1) {
                const diffHours = Math.round(diffTime / (1000 * 60 * 60));
                return diffHours === 1 ? '1 hour' : `${diffHours} hours`;
            } else {
                return `${diffDays} days`;
            }
        }

        // Helper function to calculate total duration
        function calculateTotalDuration(startDate, endDate) {
            if (!startDate || !endDate) return 'Not specified';

            const start = new Date(startDate);
            const end = new Date(endDate);
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays === 1) {
                return '1 day';
            } else if (diffDays < 1) {
                const diffHours = Math.round(diffTime / (1000 * 60 * 60));
                return diffHours === 1 ? '1 hour' : `${diffHours} hours`;
            } else {
                return `${diffDays} days`;
            }
        }

        function loadServiceInfo(serviceInfo) {
            // Add totals and profit/loss display
            const totalService = parseFloat(serviceInfo.total_service_amount || 0);
            const totalVendor = parseFloat(serviceInfo.total_vendor_amount || 0);
            const profitLoss = parseFloat(serviceInfo.profit_loss || 0);
            const profitLabel = serviceInfo.profit_loss_label || (profitLoss >= 0 ? 'Profit' : 'Loss');

            const serviceInfoHtml = `
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
            <p class="text-gray-800 dark:text-white">${serviceInfo.service_display || 'N/A'}</p>
        </div>
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
            <p class="text-gray-800 dark:text-white">${serviceInfo.extra_service_display || 'None'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12 mt-3">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Service Cost</label>
            <p class="text-gray-800 dark:text-white">₹${formatNumber(totalService)}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12 mt-3">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Vendor Cost</label>
            <p class="text-gray-800 dark:text-white">₹${formatNumber(totalVendor)}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-4 md:col-span-12 sm:col-span-12 col-span-12 mt-3">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">${profitLabel}</label>
            <p class="text-${profitLoss >= 0 ? 'success' : 'danger'} font-semibold">₹${formatNumber(Math.abs(profitLoss))} ${profitLoss >= 0 ? '' : ''}</p>
        </div>
    `;
            document.getElementById('service-info').innerHTML = serviceInfoHtml;
        }

        function loadVendorSections(vendorPayments, perVendorHistories = {}) {
            let vendorSectionsHtml = '';

            vendorPayments.forEach((vendorPayment, index) => {
                const vendorHistory = (perVendorHistories && perVendorHistories[vendorPayment.id]) ?
                    perVendorHistories[vendorPayment.id] : {};
                const refundHistory = vendorHistory.refund_history || vendorPayment.vendor_refunds || [];
                const latestRefund = Array.isArray(refundHistory) && refundHistory.length ?
                    refundHistory.slice().sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0))[0] :
                    null;
                const originalVendorAmount = parseFloat(vendorPayment.total_vendor_service_amount || 0) || 0;
                const finalPayable = latestRefund && latestRefund.cancellation_amount !== undefined && latestRefund.cancellation_amount !== null ?
                    (parseFloat(latestRefund.cancellation_amount || 0) || 0) :
                    originalVendorAmount;
                const paidFromHist = parseFloat(vendorHistory.paid_total || vendorPayment.paid_amount || 0) || 0;
                const refundedAmount = parseFloat(vendorHistory.refunded_total || 0) || 0;
                const netPaidAmount = Math.max(0, paidFromHist - refundedAmount);
                const balance = Math.max(0, finalPayable - netPaidAmount);
                const refundDue = Math.max(0, paidFromHist - finalPayable - refundedAmount);
                
                // Handle null vendor safely
                const vendorName = vendorPayment.vendor?.name || 'N/A';
                
                vendorSectionsHtml += `
            <div class="box vendor-section" data-vendor-id="${vendorPayment.id}">
                    <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Vendor Information - #${index + 1}</h5>
                    <div>
                        <button type="button" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 add-payment-btn" 
                                data-vendor-payment-id="${vendorPayment.id}"
                                data-vendor-name="${vendorName}"
                                data-total-amount="${finalPayable}"
                                data-balance-amount="${balance}">
                            Add Vendor Payment
                        </button>
                    </div>
                </div>
                <div class="box-body bg-gray-50">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor Name</label>
                            <p class="text-gray-800 dark:text-white">${vendorName}</p>
                        </div>
                       
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Original Amount</label>
                            <p class="text-gray-800 dark:text-white">₹${formatNumber(originalVendorAmount)}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Final Payable</label>
                            <p class="text-gray-800 dark:text-white vendor-total-amount" data-vendor-id="${vendorPayment.id}">₹${formatNumber(finalPayable)}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Gross Paid</label>
                            <p class="text-gray-800 dark:text-white">₹${formatNumber(paidFromHist)}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor Refund</label>
                            <p class="text-gray-800 dark:text-white text-success">₹${formatNumber(refundedAmount)}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Net Paid</label>
                            <p class="text-gray-800 dark:text-white text-success">₹<span class="vendor-paid-amount" data-vendor-id="${vendorPayment.id}">${formatNumber(netPaidAmount)}</span></p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Balance</label>
                            <p class="text-danger-800 dark:text-white text-danger">₹${formatNumber(balance)}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Due</label>
                            <p class="text-danger-800 dark:text-white text-danger">₹${formatNumber(refundDue)}</p>
                        </div>

                        <!-- Inline Add Payment Form (hidden by default) -->
                       <div class="col-span-12 inline-add-payment" id="inline-add-payment-${vendorPayment.id}" style="display:none;">
                            <form class="inline-payment-form" data-vendor-id="${vendorPayment.id}" enctype="multipart/form-data">
                                <div class="grid grid-cols-12 gap-4">

                                    <!-- Total Amount -->
                                    <div class="col-span-4">
                                        <label class="ti-form-label">Total Amount</label>
                                        <input type="number" name="total_amount" class="ti-form-input inline-total-amount" 
                                            step="0.01" placeholder="Enter total amount" required />
                                    </div>

                                    <!-- Paid Amount -->
                                    <div class="col-span-4">
                                        <label class="ti-form-label">Paid Amount</label>
                                        <input type="number" name="paid_amount" class="ti-form-input inline-paid-amount" 
                                            step="0.01" placeholder="Enter paid amount" required />
                                    </div>

                                    <!-- Payment Method -->
                                    <div class="col-span-4">
                                        <label class="ti-form-label">Payment Method</label>
                                        <select name="payment_method" class="ti-form-select inline-payment-method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Online Payment">Online Payment</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Other">Other</option>
                                            <option value="Net Banking">Net Banking</option>
                                            <option value="Website">Website</option>
                                            <option value="Paid Directly to Vendor">Paid Directly to Vendor</option>
                                        </select>
                                    </div>

                                    <!-- Paid Date -->
                                    <div class="col-span-4">
                                        <label class="ti-form-label">Paid Date</label>
                                        <input type="date" name="paid_date" class="ti-form-input inline-paid-date" required />
                                    </div>

                                    <!-- Narration -->
                                    <div class="col-span-6">
                                        <label class="ti-form-label">Narration</label>
                                        <input type="text" name="narration" class="ti-form-input inline-narration" 
                                            placeholder="Enter narration (optional)" />
                                    </div>

                                    <!-- Receipt Upload -->
                                    <div class="col-span-12">
                                        <label class="ti-form-label required">Upload Receipt </label>
                                        <input type="file" name="receipt" class="inline-receipt" accept=".jpg,.jpeg,.png,.pdf" />
                                        <!-- existing receipt preview (shown when history contains a receipt) -->
                                        <div class="mt-2 existing-receipt" id="existing-receipt-${vendorPayment.id}" style="display:none;">
                                            <!-- populated below when perVendorHistories indicates a recent receipt -->
                                        </div>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="col-span-12 flex gap-2">
                                        <button type="button" class="ti-btn bg-theme ti-btn-primary-full save-inline-payment" 
                                                data-vendor-id="${vendorPayment.id}">Save</button>
                                        <button type="button" class="ti-btn ti-btn-outline-secondary cancel-inline-payment" 
                                                data-vendor-id="${vendorPayment.id}">Cancel</button>
                                    </div>

                                </div>
                            </form>
                        </div>


                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 vendor-payment-history" id="vendor-payment-history-${vendorPayment.id}">
                            ${renderVendorHistoryHtml(vendorPayment.id, perVendorHistories)}
                        </div>
                    </div>
                </div>
            </div>
        `;
            });

            document.getElementById('vendor-sections').innerHTML = vendorSectionsHtml;
            // attach receipt click handlers for any injected history items
            attachReceiptViewHandlers();

            // Add event listeners for the new buttons
            document.querySelectorAll('.add-payment-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const vendorPaymentId = this.getAttribute('data-vendor-payment-id');
                    const vendorName = this.getAttribute('data-vendor-name');
                    const totalAmount = this.getAttribute('data-total-amount');
                    const balanceAmount = this.getAttribute('data-balance-amount');

                    // Show inline add payment form for this vendor
                    document.querySelectorAll('.inline-add-payment').forEach(el => el.style.display =
                        'none');
                    const inlineForm = document.getElementById(`inline-add-payment-${vendorPaymentId}`);
                    if (inlineForm) {
                        // reset previous values/errors except Total Amount
                        const totalInput = inlineForm.querySelector('.inline-total-amount');
                        const paidInput = inlineForm.querySelector('.inline-paid-amount');
                        const methodSelect = inlineForm.querySelector('.inline-payment-method');
                        const paidDateInput = inlineForm.querySelector('.inline-paid-date');
                        const narrationInput = inlineForm.querySelector('.inline-narration');
                        // clear any previous inline error messages
                        inlineForm.querySelectorAll('.field-error').forEach(e => e.remove());

                        // show the form
                        inlineForm.style.display = 'block';

                        // set total amount (editable) and set max for paid amount
                        if (totalInput) {
                            totalInput.value = totalAmount;
                        }

                        if (paidInput) {
                            paidInput.value = ''; // clear previously entered paid amount
                            paidInput.setAttribute('max', balanceAmount);
                            paidInput.placeholder = 'Max: ' + balanceAmount;
                        }

                        if (methodSelect) methodSelect.value = '';
                        if (paidDateInput) paidDateInput.value = '';
                        if (narrationInput) narrationInput.value = '';
                        // If there is an existing receipt for this vendorPayment, show preview/download in the inline form
                        try {
                            const existingContainer = inlineForm.querySelector(`#existing-receipt-${vendorPaymentId}`);
                            const receiptFileInput = inlineForm.querySelector('.inline-receipt');
                            if (existingContainer) {
                                existingContainer.style.display = 'none';
                                existingContainer.innerHTML = '';
                                // clear any previous object URL stored
                                if (existingContainer.dataset.objectUrl) {
                                    try { URL.revokeObjectURL(existingContainer.dataset.objectUrl); } catch (e) {}
                                    delete existingContainer.dataset.objectUrl;
                                }
                            }

                            // Attach a change handler to the inline file input so the preview shows
                            // only when the user selects a file.
                            if (receiptFileInput) {
                                // remove any previously attached handler flag
                                if (!receiptFileInput.__inlinePreviewBound) {
                                    receiptFileInput.addEventListener('change', function() {
                                        const file = this.files && this.files[0] ? this.files[0] : null;
                                        const container = inlineForm.querySelector(`#existing-receipt-${vendorPaymentId}`);
                                        if (!container) return;

                                        // revoke previous object URL if present
                                        if (container.dataset.objectUrl) {
                                            try { URL.revokeObjectURL(container.dataset.objectUrl); } catch (e) {}
                                            delete container.dataset.objectUrl;
                                        }

                                        if (!file) {
                                            container.style.display = 'none';
                                            container.innerHTML = '';
                                            return;
                                        }

                                        const url = URL.createObjectURL(file);
                                        container.dataset.objectUrl = url;
                                        let inner = '';
                                        const ext = (file.name || '').split('.').pop().toLowerCase();
                                        if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                                            inner = `
                                                <div class="d-flex align-items-center">
                                                    <div class="ms-3">
                                                        <p class="mb-1"><a href="${url}" target="_blank" class="text-primary"><i class="ri-eye-line"></i> View</a></p>
                                                        <p class="mb-0"><a href="${url}" download class="text-muted"><i class="ri-download-2-line"></i> Download</a></p>
                                                    </div>
                                                </div>
                                            `;
                                        } else if (ext === 'pdf') {
                                            inner = `
                                                <div>
                                                    <p class="mb-1"><a href="${url}" target="_blank" class="text-primary"><i class="ri-file-pdf-line"></i> Open PDF</a></p>
                                                    <p class="mb-0"><a href="${url}" download class="text-muted"><i class="ri-download-2-line"></i> Download</a></p>
                                                </div>
                                            `;
                                        } else {
                                            inner = `
                                                <div>
                                                    <p class="mb-1"><a href="${url}" target="_blank" class="text-primary">Open file</a></p>
                                                    <p class="mb-0"><a href="${url}" download class="text-muted">Download</a></p>
                                                </div>
                                            `;
                                        }

                                        container.style.display = 'block';
                                        container.innerHTML = inner;
                                    });
                                    receiptFileInput.__inlinePreviewBound = true;
                                }
                            }
                        } catch (e) {
                            // ignore non-critical errors
                        }
                    }
                });
            });

            // Attach cancel handlers
            document.querySelectorAll('.cancel-inline-payment').forEach(btn => {
                btn.addEventListener('click', function() {
                    const vid = this.getAttribute('data-vendor-id');
                    const inline = document.getElementById(`inline-add-payment-${vid}`);
                    if (inline) inline.style.display = 'none';
                });
            });

            // Attach save handlers for inline payments
            document.querySelectorAll('.save-inline-payment').forEach(btn => {
                btn.addEventListener('click', function() {
                    const vid = this.getAttribute('data-vendor-id');
                    const form = document.querySelector(`.inline-payment-form[data-vendor-id='${vid}']`);
                    if (!form) return;

                    // helper to show field error
                    function showFieldError(el, msg) {
                        if (!el) return;
                        // remove old error for this field
                        const existing = el.parentNode.querySelector('.field-error');
                        if (existing) existing.remove();
                        const div = document.createElement('div');
                        div.className = 'field-error text-danger text-sm mt-1';
                        div.textContent = msg;
                        el.parentNode.appendChild(div);
                        try {
                            el.style.borderColor = '#e3342f';
                            el.setAttribute('aria-invalid', 'true');
                        } catch (e) {}
                    }

                    // clear previous errors
                    form.querySelectorAll('.field-error').forEach(e => e.remove());

                    const formData = new FormData();
                    const paidAmount = form.querySelector('.inline-paid-amount').value;
                    const paymentMethod = form.querySelector('.inline-payment-method').value;
                    const paidDate = form.querySelector('.inline-paid-date').value;
                    const narration = form.querySelector('.inline-narration').value;
                    const totalAmountVal = form.querySelector('.inline-total-amount') ? form.querySelector(
                        '.inline-total-amount').value : '';
                    const receiptInput = form.querySelector('.inline-receipt');
                    const receiptFile = receiptInput && receiptInput.files && receiptInput.files[0] ?
                        receiptInput.files[0] : null;

                    // client-side required validation (mirror backend rules)
                    let hasError = false;
                    if (!paidAmount) {
                        showFieldError(form.querySelector('.inline-paid-amount'),
                        'Paid Amount is required');
                        hasError = true;
                    }
                    
                    if (!paymentMethod) {
                        showFieldError(form.querySelector('.inline-payment-method'),
                            'Payment Method is required');
                        hasError = true;
                    }
                    // After existing hasError checks, add:
                    if (!receiptFile) {
                        showFieldError(receiptInput, 'Receipt is required');
                        hasError = true;
                    } else if (!validateVendorReceiptFile(receiptInput, showFieldError)) {
                        hasError = true;
                    }

                    if (!paidDate) {
                        showFieldError(form.querySelector('.inline-paid-date'), 'Paid Date is required');
                        hasError = true;
                    } else {
                        // disallow future paid dates on client-side
                        const pd = new Date(paidDate);
                        const today = new Date();
                        today.setHours(0,0,0,0);
                        pd.setHours(0,0,0,0);
                        if (pd > today) {
                            showFieldError(form.querySelector('.inline-paid-date'), 'Paid Date cannot be a future date');
                            hasError = true;
                        }
                    }
                    // validate that paid amount does not exceed allowed max/balance
                    const paidNumInline = parseFloat(paidAmount) || 0;
                    const paidInputEl = form.querySelector('.inline-paid-amount');
                    const maxAttr = paidInputEl ? paidInputEl.getAttribute('max') : null;
                    const maxAllowed = maxAttr ? (parseFloat(maxAttr) || 0) : (totalAmountVal !== '' ? (
                        parseFloat(totalAmountVal) || 0) : null);
                    if (maxAllowed !== null && paidNumInline > maxAllowed) {
                        showFieldError(form.querySelector('.inline-paid-amount'),
                            'Paid amount cannot exceed available balance');
                        hasError = true;
                    }
                    if (hasError) return;

                    formData.append('lead_vendor_payment_id', vid);
                    if (totalAmountVal !== '') formData.append('total_vendor_service_amount',
                        totalAmountVal);
                    formData.append('paid_amount', paidAmount);
                    formData.append('payment_method', paymentMethod);
                    formData.append('paid_date', paidDate);
                    formData.append('narration', narration);
                    // CSRF token
                    formData.append('_token', document.querySelector('meta[name="csrf-token"]')
                        .getAttribute('content'));
                    if (receiptFile) {
                        formData.append('receipt', receiptFile);
                    }

                    // Disable button
                    btn.disabled = true;
                    const originalText = btn.textContent;
                    btn.textContent = 'Processing...';

                    showLoader(); // 🔥 ADD THIS

                    const _origSuccessModal = window.showSuccessModal;
                    window.showSuccessModal = function() {}; // temporarily disable

                    fetch(@json(route('admin.account.vendor-payments.store', [], false)), {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(parseVendorPaymentResponse)
                        .then(data => {

                            if (data.success) {

                                hideLoader();

                                if (window._currentVendorPaymentId) {
                                    sessionStorage.setItem('reopenVendorPaymentId', window._currentVendorPaymentId);
                                }
                                // Show custom success popup matching theme
                                const successOverlay = document.createElement('div');
                                successOverlay.id = 'vendor-success-overlay';
                                successOverlay.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
                                successOverlay.innerHTML = 
                                    `<div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                                        <div style="margin-bottom:20px;">
                                            <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#dcfce7;">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10" fill="#22c55e"/>
                                                    <path d="M7.5 12.5L10.5 15.5L16.5 9.5" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </span>
                                        </div>
                                        <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">Payment Successful!</h5>
                                        <p style="color:#6b7280;margin-bottom:6px;font-size:0.9rem;">Vendor payment recorded successfully.</p>
                                        <button id="vendor-success-ok-btn" style="background:#2B53A9;color:#fff;border:none;border-radius:8px;padding:10px 40px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>
                                    </div>`;
                                
                                document.body.appendChild(successOverlay);
                                document.getElementById('vendor-success-ok-btn').onclick = function() {
                                    successOverlay.remove();
                                    location.reload();
                                };
                                
                                // Restore global success modal
window.showSuccessModal = _origSuccessModal;

                                return;
                                // Update vendor paid amount and history UI for this vendor
                                const paidSpan = document.querySelector(
                                    `.vendor-paid-amount[data-vendor-id='${vid}']`);
                                if (paidSpan) {
                                    // increment displayed amount by new paid amount
                                    const current = parseFloat(paidSpan.textContent.replace(/,/g,
                                        '')) || 0;
                                    const added = parseFloat(paidAmount) || 0;
                                    paidSpan.textContent = formatNumber(current + added);
                                }

                                // If total amount was updated, reflect it in the UI and update balance
                                if (totalAmountVal !== '') {
                                    const totalEl = document.querySelector(
                                        `.vendor-total-amount[data-vendor-id='${vid}']`);
                                    if (totalEl) totalEl.textContent = '₹' + formatNumber(
                                        totalAmountVal);

                                    // update balance displayed in vendor section
                                    const balanceEl = document.querySelector(
                                            `.vendor-section[data-vendor-id='${vid}'] .text-danger-800`
                                            ) || document.querySelector(
                                            `.vendor-section[data-vendor-id='${vid}'] p.text-danger`);
                                    if (balanceEl) {
                                        // recalc balance = total - new paid total (we have current and added)
                                        const currentPaid = parseFloat((document.querySelector(
                                                `.vendor-paid-amount[data-vendor-id='${vid}']`)
                                            ?.textContent || '0').replace(/,/g, '')) || 0;
                                        const newBalance = parseFloat(totalAmountVal) - currentPaid;
                                        balanceEl.textContent = '₹' + formatNumber(newBalance);
                                    }
                                }

                                // Prepend new history item to vendor history container
                                const historyContainer = document.getElementById(
                                    `vendor-payment-history-${vid}`);
                                if (historyContainer) {
                                    const html = `\
                            <div class="flex items-center mb-4">\
                                <div class="me-4 gap-0">\
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">\
                                        <svg xmlns=\"http://www.w3.org/2000/svg\" width=\"24\" height=\"24\" fill=\"none\" viewBox=\"0 0 24 24\">\
                                            <circle cx=\"12\" cy=\"12\" r=\"10\" stroke=\"#fff\" stroke-width=\"2\" fill=\"#2B53A9\" />\
                                            <path d=\"M8.5 12.5L11 15L16 9.5\" stroke=\"#fff\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" />\
                                        </svg>\
                                    </span>\
                                </div>\
                                <div class=\"flex-grow\">\
                                    <div class=\"flex items-center justify-between\">\
                                        <div>\
                                            <h5 class=\"font-semibold mb-2 leading-none text-[1.25rem]\">₹${formatNumber(paidAmount)}</h5>\
                                            <p>${new Date(paidDate).toLocaleDateString()} • ${paymentMethod}</p>\
                                            <p class=\"text-[#8c9097]\">${narration || 'No notes'}</p>\
                                        </div>\
                                        <div class=\"text-end\">\
                                            <span class=\"badge !rounded-full bg-success text-white mb-2\">Done</span>\
                                                ${data.payment && (data.payment.receipt || typeof data.receipt_url !== 'undefined' && data.receipt_url) ? '<p><a href="' + (data.receipt_url ? data.receipt_url : getReceiptUrl(data.payment.receipt)) + '" target="_blank" class="receipt-link" data-receipt="' + (data.payment.receipt || '') + '" data-method="' + paymentMethod + '" data-date="' + paidDate + '" data-amount="' + paidAmount + '" data-notes="' + (narration || '') + '"><i class="ri-eye-line text-primary cursor-pointer"></i></a> <a href="' + (data.receipt_url ? data.receipt_url : getReceiptUrl(data.payment.receipt)) + '" download class="receipt-download ms-2 text-muted" title="Download Receipt"><i class="ri-download-2-line"></i></a> Receipt</p>' : ''}\
                                            </div>\
                                    </div>\
                                </div>\
                            </div>\
                        `;
                                    historyContainer.insertAdjacentHTML('afterbegin', html);
                                        // attach handlers for the newly inserted receipt icon (if any)
                                        attachReceiptViewHandlers();
                                }

                                // Update global JS caches: perVendorHistories and global history
                                const newPaymentObj = {
                                    id: data.payment.id || null,
                                    paid_amount: parseFloat(paidAmount),
                                    paid_date: paidDate,
                                    payment_method: paymentMethod,
                                    narration: narration,
                                    receipt: data.payment.receipt || null,
                                    created_at: new Date().toISOString()
                                };

                                if (!window._perVendorHistories) window._perVendorHistories = {};
                                if (!window._perVendorHistories[vid]) window._perVendorHistories[
                                vid] = {
                                    history: [],
                                    paid_total: 0
                                };
                                // unshift to history and update paid_total
                                window._perVendorHistories[vid].history.unshift(newPaymentObj);
                                window._perVendorHistories[vid].paid_total = (parseFloat(window
                                    ._perVendorHistories[vid].paid_total || 0) + parseFloat(
                                    paidAmount)).toFixed(2);

                                // update global history list
                                if (!window._vendorPaymentGlobalHistory) window
                                    ._vendorPaymentGlobalHistory = [];
                                window._vendorPaymentGlobalHistory.unshift(newPaymentObj);

                                // hide inline form
                                // hide inline form and reset all fields including receipt
const inline = document.getElementById(`inline-add-payment-${vid}`);
if (inline) {
    inline.style.display = 'none';
    // clear all form inputs
    const inlineForm = inline.querySelector('.inline-payment-form');
    if (inlineForm) {
        inlineForm.querySelectorAll('input, select, textarea').forEach(el => {
            if (el.type === 'file') {
                el.value = '';  // clear file input
            } else if (el.type !== 'hidden') {
                el.value = '';
            }
        });
        // hide receipt preview container
        const existingReceipt = inlineForm.querySelector('[id^="existing-receipt-"]');
        if (existingReceipt) {
            if (existingReceipt.dataset.objectUrl) {
                try { URL.revokeObjectURL(existingReceipt.dataset.objectUrl); } catch(e) {}
                delete existingReceipt.dataset.objectUrl;
            }
            existingReceipt.style.display = 'none';
            existingReceipt.innerHTML = '';
        }
        // clear any lingering field errors
        inlineForm.querySelectorAll('.field-error').forEach(e => e.remove());
    }
}
                            } else {
                                throw new Error(data.error || 'Error saving payment');
                            }
                        })
                        .catch(err => {
                            hideLoader(); // 🔥 important
                            console.error('Inline save error', err);
                            if (err && err.validation) {
                                // show backend validation errors next to fields
                                Object.keys(err.validation).forEach(field => {
                                    const input = form.querySelector(`[name="${field}"]`);
                                    if (input) showFieldError(input, err.validation[field].join(
                                        ', '));
                                });
                                return;
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed!',
                                text: err.message || 'Something went wrong'
                            });
                            alert('Error saving payment: ' + (err.message || 'Unknown'));
                        })
                        .finally(() => {
                            btn.disabled = false;
                            btn.textContent = originalText;
                        });
                });
            });
        }

        // payment history UI removed (handled within per-vendor sections)

        function calculateBalance() {
            const totalAmountText = document.getElementById('modal-total-amount').value;
            const paidAmount = parseFloat(document.getElementById('modal-paid-amount').value) || 0;

            // Extract number from "₹X,XXX" format
            const totalAmount = parseFloat(totalAmountText.replace(/[₹,]/g, '')) || 0;
            const balance = totalAmount - paidAmount;

            document.getElementById('modal-balance-amount').value = `₹${formatNumber(balance)}`;

            // Validate that paid amount doesn't exceed total and show inline message
            const paidErrDiv = document.getElementById('modal-paid-error');
            const paidInputEl = document.getElementById('modal-paid-amount');
            if (paidAmount > totalAmount) {
                if (paidInputEl) paidInputEl.style.borderColor = '#e3342f';
                if (paidInputEl) paidInputEl.setAttribute('aria-invalid', 'true');
                if (paidInputEl) paidInputEl.setCustomValidity('Paid amount cannot exceed total amount');
                if (paidErrDiv) {
                    paidErrDiv.style.display = 'block';
                    paidErrDiv.setAttribute('role', 'alert');
                    paidErrDiv.textContent = 'Paid amount cannot exceed available balance';
                }
            } else {
                if (paidInputEl) paidInputEl.style.borderColor = '';
                if (paidInputEl) paidInputEl.removeAttribute('aria-invalid');
                if (paidInputEl) paidInputEl.setCustomValidity('');
                if (paidErrDiv) {
                    paidErrDiv.style.display = 'none';
                    paidErrDiv.textContent = '';
                }
            }
        }

        function saveVendorPayment() {
            const form = document.getElementById('vendor-payment-form');
            const saveButton = document.getElementById('save-vendor-payment');
            const originalText = saveButton.textContent;
            function showModalFieldError(el, msg) {
                if (!el) return;
                const existing = el.parentNode.querySelector('.field-error:not(#modal-paid-error)');
                if (existing) existing.remove();
                const div = document.createElement('div');
                div.className = 'field-error text-danger text-sm mt-1';
                div.textContent = msg;
                el.parentNode.appendChild(div);
                el.style.borderColor = '#e3342f';
                el.setAttribute('aria-invalid', 'true');
            }
            // clear any old inline errors but preserve the modal's paid-error container
            form.querySelectorAll('.field-error').forEach(e => {
                if (e.id && e.id === 'modal-paid-error') return; // keep modal paid error element
                e.remove();
            });
            // client-side validation: ensure paid amount does not exceed total
            const paidInput = form.querySelector('#modal-paid-amount');
            const totalText = document.getElementById('modal-total-amount') ? document.getElementById('modal-total-amount')
                .value : '';
            const totalNum = parseFloat((totalText || '').toString().replace(/[₹,]/g, '')) || 0;
            const paidNum = parseFloat(paidInput ? paidInput.value : 0) || 0;
            const modalPaidErr = document.getElementById('modal-paid-error');

            // clear previous visual error state
            if (paidInput) {
                paidInput.style.borderColor = '';
                paidInput.removeAttribute('aria-invalid');
            }
            if (modalPaidErr) {
                modalPaidErr.style.display = 'none';
                modalPaidErr.textContent = '';
            }

            if (paidNum > totalNum) {
                if (paidInput) paidInput.style.borderColor = '#e3342f';
                if (paidInput) paidInput.setAttribute('aria-invalid', 'true');
                if (modalPaidErr) {
                    modalPaidErr.style.display = 'block';
                    modalPaidErr.textContent = 'Paid amount cannot exceed available balance';
                    modalPaidErr.setAttribute('role', 'alert');
                    try {
                        alert('Paid amount cannot exceed available balance');
                    } catch (e) {}
                }
                if (paidInput) paidInput.focus();
                return;
            }

            // Validate paid_date is not in future (client-side)
            const modalPaidDateInput = form.querySelector('input[name="paid_date"]');
            const modalPaidDateVal = modalPaidDateInput ? modalPaidDateInput.value : null;
            if (modalPaidDateVal) {
                const pd = new Date(modalPaidDateVal);
                const today = new Date();
                today.setHours(0,0,0,0);
                pd.setHours(0,0,0,0);
                if (pd > today) {
                    if (modalPaidErr) {
                        modalPaidErr.style.display = 'block';
                        modalPaidErr.textContent = 'Paid Date cannot be a future date';
                        modalPaidErr.setAttribute('role', 'alert');
                    }
                    if (modalPaidDateInput) modalPaidDateInput.focus();
                    // reset button state
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                    return;
                }
            }

            const modalReceiptInput = form.querySelector('input[name="receipt"]');
            if (!validateVendorReceiptFile(modalReceiptInput, showModalFieldError)) {
                if (modalReceiptInput) modalReceiptInput.focus();
                return;
            }

            const formData = new FormData(form);

            // Show loading state
            saveButton.textContent = 'Saving...';
            saveButton.disabled = true;

            fetch(@json(route('admin.account.vendor-payments.store', [], false)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: formData
                })
                .then(parseVendorPaymentResponse)
                .then(data => {
                    if (data.success) {
                        // hideLoader();

                        // setTimeout(() => {
                        //     Swal.fire({
                        //         icon: 'success',
                        //         title: 'Payment Completed!',
                        //         text: 'Email & WhatsApp notification sent successfully to vendor.',
                        //     }).then(() => {
                        //         location.reload();
                        //     });
                        // }, 200);
                        alert('Payment saved successfully');
                        // Close modal
                        document.querySelector('[data-hs-overlay="#add-vendor-payment-modal"]').click();
                        // Reload the page or update the UI
                        location.reload();
                    } else {
                        throw new Error(data.error || 'Unknown error occurred');
                    }
                })
                .catch(error => {
                    hideLoader();
                    console.error('Error saving payment:', error);
                    if (error && error.validation) {
                        // show field errors beside modal fields
                        Object.keys(error.validation).forEach(field => {
                            const input = form.querySelector(`[name="${field}"]`);
                            if (input) {
                                const div = document.createElement('div');
                                div.className = 'field-error text-danger text-sm mt-1';
                                div.textContent = error.validation[field].join(', ');
                                input.parentNode.appendChild(div);
                            }
                        });
                        return;
                    }
                    alert('Error saving payment: ' + (error.message || 'Unknown'));
                })
                .finally(() => {
                    // Reset button state
                    saveButton.textContent = originalText;
                    saveButton.disabled = false;
                });
        }

        function formatNumber(number) {
            return parseFloat(number).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getPaymentStatusBadge(statusCode) {
            // Map payment status codes to appropriate badges
            switch (parseInt(statusCode)) {
                case 1:
                    return '<span class="badge !rounded-full bg-green-600 text-white">Approved</span>';
                case 2:
                    return '<span class="badge !rounded-full bg-red-600 text-white">Rejected</span>';
                case 3:
                    return '<span class="badge !rounded-full bg-green-600 text-white">Full Payment</span>';
                case 4:
                    return '<span class="badge !rounded-full bg-warning text-white">Partial Payment</span>';
                default:
                    return '<span class="badge !rounded-full bg-blue-600 text-white">Pending</span>';
            }
        }

        // Return avatar SVG (inner svg markup) based on status code.
        // statusCode: numeric codes where 2 => Rejected, 1 => Approved, default => Checked/Done
        function getStatusAvatarSvg(statusCode) {
            const code = (statusCode !== null && statusCode !== undefined && statusCode !== 'pending') ? parseInt(statusCode) : null;
            switch (code) {
                case 2:
                    // Rejected - full span+svg matches payment-review markup
                    return `
                            <span class="avatar avatar-md p-2 !rounded-full bg-red-600 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2" fill="#dc2626" />
                                    <path d="M8 8L16 16M16 8L8 16" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>`;
                default:
                    // Default/approved/done - full span+svg matches payment-review markup
                    return `
                            <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2" fill="#2B53A9" />
                                    <path d="M8.5 12.5L11 15L16 9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>`;
            }
        }

        function viewReceipt(receiptPath, meta) {
            if (receiptPath) {
                showReceiptInModal(receiptPath, meta || null);
            }
        }

        function getReceiptUrl(receipt, fallbackUrl) {
            if (!receipt) return fallbackUrl || null;
            // If server already returned full URL, use it
            if (receipt.indexOf('http://') === 0 || receipt.indexOf('https://') === 0) return receipt;
            // if receipt already begins with /storage or /, normalize
            if (receipt.indexOf('/storage/') === 0) return receipt;
            if (receipt.indexOf('/') === 0) return '/storage' + receipt;
            return '/storage/' + receipt;
        }

        function attachReceiptViewHandlers() {
            // Attach handlers to anchor links with class .receipt-link.
            // Default click opens the file in a new tab. If user holds Ctrl/Cmd/Shift while clicking,
            // we prevent navigation and open the modal preview instead.
            document.querySelectorAll('.receipt-link').forEach(el => {
                if (el.__receiptLinkBound) return;
                el.addEventListener('click', function(event) {
                    const path = this.getAttribute('data-receipt');
                    const meta = {
                        payment_method: this.getAttribute('data-method') || '',
                        paid_date: this.getAttribute('data-date') || '',
                        paid_amount: this.getAttribute('data-amount') || '',
                        narration: this.getAttribute('data-notes') || ''
                    };
                    // If modifier key pressed, open modal preview instead of navigating
                    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                        event.preventDefault();
                        showReceiptInModal(path, meta);
                    }
                    // otherwise allow default (open in new tab via target=_blank)
                });
                el.__receiptLinkBound = true;
            });
        }

        function showReceiptInModal(receiptPath, meta) {
            const preview = document.getElementById('receipt-preview-area');
            const downloadBtn = document.getElementById('receipt-download-btn');
            const openBtn = document.getElementById('receipt-open-new-btn');
            const metaMethod = document.getElementById('receipt-meta-method');
            const metaDate = document.getElementById('receipt-meta-date');
            const metaAmount = document.getElementById('receipt-meta-amount');
            const metaNotes = document.getElementById('receipt-meta-notes');

            if (!preview) return;

            // clear preview
            preview.innerHTML = '';

            const url = `/storage/${receiptPath}`;
            const ext = (receiptPath || '').split('.').pop().toLowerCase();
            if (['jpg','jpeg','png','gif','webp'].includes(ext)) {
                const img = document.createElement('img');
                img.src = url;
                img.alt = 'Receipt';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                preview.appendChild(img);
            } else if (ext === 'pdf') {
                const iframe = document.createElement('iframe');
                iframe.src = url;
                iframe.style.width = '100%';
                iframe.style.height = '100%';
                iframe.frameBorder = 0;
                preview.appendChild(iframe);
            } else {
                const a = document.createElement('a');
                a.href = url;
                a.textContent = 'Open file';
                a.target = '_blank';
                preview.appendChild(a);
            }

            // populate metadata
            if (metaMethod) metaMethod.textContent = meta && meta.payment_method ? meta.payment_method : '-';
            if (metaDate) metaDate.textContent = meta && meta.paid_date ? (new Date(meta.paid_date)).toLocaleDateString() : '-';
            if (metaAmount) metaAmount.textContent = meta && meta.paid_amount ? ('₹' + formatNumber(meta.paid_amount)) : '-';
            if (metaNotes) metaNotes.textContent = meta && meta.narration ? meta.narration : '-';

            if (downloadBtn) {
                downloadBtn.href = url;
            }
            if (openBtn) {
                openBtn.href = url;
            }

            // open modal via hidden anchor to use existing overlay handling
            const anchor = document.getElementById('open-receipt-viewer');
            if (anchor) anchor.click();
        }

        function renderVendorHistoryHtml(vendorPaymentId, perVendorHistories) {
            if (!perVendorHistories || !perVendorHistories[vendorPaymentId] || !perVendorHistories[vendorPaymentId]
                .history || perVendorHistories[vendorPaymentId].history.length === 0) {
                return '<p class="text-gray-500">No payment history found for this vendor</p>';
            }

            let html = '';
            perVendorHistories[vendorPaymentId].history.forEach(payment => {
                const avatarSvg = getStatusAvatarSvg(payment.payment_status);
                html += `
            <div class="flex items-center mb-4">
                <div class="me-4 gap-0">` + avatarSvg + `</div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <div>
                            <h5 class="font-semibold mb-2 leading-none text-[1.25rem]">₹${formatNumber(payment.paid_amount)}</h5>
                            <p>${new Date(payment.paid_date).toLocaleDateString()} • ${payment.payment_method}</p>
                            <p class="text-[#8c9097]">${payment.narration || 'No notes'}</p>
                        </div>
                                <div class="text-end">
                                    <span class="badge !rounded-full bg-success text-white mb-2">Done</span>
                                    ${payment.receipt ? '<p><a href="' + getReceiptUrl(payment.receipt) + '" target="_blank" class="receipt-link" data-receipt="' + payment.receipt + '" data-method="' + (payment.payment_method || '') + '" data-date="' + (payment.paid_date || '') + '" data-amount="' + (payment.paid_amount || '') + '" data-notes="' + (payment.narration || '') + '"><i class="ri-eye-line text-primary cursor-pointer"></i></a> <a href="' + getReceiptUrl(payment.receipt) + '" download class="receipt-download ms-2 text-muted" title="Download Receipt"><i class="ri-download-2-line"></i></a> Receipt</p>' : ''}
                                </div>
                    </div>
                </div>
            </div>
        `;
            });
            return html;
        }

        // Export handled by direct anchor link. No JS handler required.

        // Handle filter changes and UI toggles
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.getElementById('filter-form');
            const clientSelect = document.getElementById('client_id');
            const vendorSelect = document.getElementById('vendor_id');
            const serviceSelect = document.getElementById('service_id');
            const statusSelect = document.getElementById('status');

            // Auto-submit when client/vendor/service changes, but DO NOT auto-submit on status change.
            [clientSelect, vendorSelect, serviceSelect].forEach(select => {
                if (select) select.addEventListener('change', function() { filterForm.submit(); });
            });

            const toggleFilters = document.getElementById('toggle-filters');
            const filterSection = document.getElementById('filter-section');
            const filterIcon = document.getElementById('filter-icon');
            if (toggleFilters && filterSection && filterIcon) {
                toggleFilters.addEventListener('click', function() {
                    if (filterSection.style.display === 'none') {
                        filterSection.style.display = 'block';
                        filterIcon.classList.remove('ti-chevron-down');
                        filterIcon.classList.add('ti-chevron-up');
                    } else {
                        filterSection.style.display = 'none';
                        filterIcon.classList.remove('ti-chevron-up');
                        filterIcon.classList.add('ti-chevron-down');
                    }
                });
            }
            const reopenId = sessionStorage.getItem('reopenVendorPaymentId');
if (reopenId) {
    sessionStorage.removeItem('reopenVendorPaymentId');
    setTimeout(function() {
        loadVendorPaymentDetails(reopenId);
        const modal = document.getElementById('view-vendor-payment');
        if (modal) {
            if (window.HSOverlay) {
                window.HSOverlay.open(modal);
            } else {
                modal.classList.remove('hidden');
                modal.classList.add('open');
            }
        }
    }, 500);
}
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = @json(route('admin.account.vendor-payments', [], false));
        }
        function showLoader() {
            document.getElementById('global-loader').style.display = 'flex';
        }

        function hideLoader() {
            const loader = document.getElementById('global-loader');
            loader.style.display = 'none';
            loader.style.zIndex = '-1'; // 🔥 ensure it's gone
        }
</script>
<style>
.loader-spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #eee;
    border-top: 4px solid #2B53A9;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: auto;
}
@keyframes spin {
    100% { transform: rotate(360deg); }
}
</style>
@endpush
