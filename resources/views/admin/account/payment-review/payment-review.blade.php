@extends('admin.layouts.header')
@section('content')
@php $tableProductNames = app(\App\Services\LeadTableProducts::class)->forRecords($payments); @endphp
@php
    $reviewSummary = $reviewSummary ?? [];
    $inr = fn ($amount) => '₹' . number_format((float) $amount, 0);
    // Compact rupee figure for the cards, e.g. ₹14.2L / ₹9.6K.
    $inrShort = function ($amount) {
        $amount = (float) $amount;
        if ($amount >= 10000000) return '₹' . round($amount / 10000000, 1) . 'Cr';
        if ($amount >= 100000) return '₹' . round($amount / 100000, 1) . 'L';
        if ($amount >= 1000) return '₹' . round($amount / 1000, 1) . 'K';
        return '₹' . number_format($amount, 0);
    };
    $currentApproval = request('payment_status', '');
    $chipUrl = fn ($value) => route('admin.account.payment-review', array_filter(array_merge(request()->except(['page', 'payment_status']), ['payment_status' => $value]), fn ($v) => $v !== null && $v !== ''));
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/payment-review.css') }}?v=2">
<div class="crm-payment-review">
    <header class="pr-heading">
        <div>
            <h1>Payment Review</h1>
            <p>Approve or reject incoming payments</p>
        </div>
        <a href="{{ route('admin.account.payment-review.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
            class="pr-ghost"><i class="ri-download-line" aria-hidden="true"></i> Export (filtered)</a>
    </header>

    <section class="pr-stats" aria-label="Payment review summary">
        <div class="pr-stat is-brand">
            <strong>{{ number_format($reviewSummary['pending_count'] ?? 0) }} <small>· {{ $inrShort($reviewSummary['pending_amount'] ?? 0) }}</small></strong>
            <span>Pending approval</span>
            <small>{{ isset($reviewSummary['oldest_days']) && $reviewSummary['oldest_days'] !== null ? 'oldest waiting ' . $reviewSummary['oldest_days'] . ' ' . \Illuminate\Support\Str::plural('day', $reviewSummary['oldest_days']) : 'nothing waiting' }}</small>
        </div>
        <div class="pr-stat is-green">
            <strong>{{ number_format($reviewSummary['approved_today_count'] ?? 0) }} <small>· {{ $inrShort($reviewSummary['approved_today_amount'] ?? 0) }}</small></strong>
            <span>Approved today</span>
            <small>{{ isset($reviewSummary['avg_decision_hours']) && $reviewSummary['avg_decision_hours'] !== null ? 'avg decision time ' . $reviewSummary['avg_decision_hours'] . 'h' : 'no approvals yet today' }}</small>
        </div>
        <div class="pr-stat is-amber">
            <strong>{{ number_format($reviewSummary['rejected_week_count'] ?? 0) }}</strong>
            <span>Rejected this week</span>
            <small>Since {{ now()->startOfWeek()->format('d M') }}</small>
        </div>
        <div class="pr-stat is-red">
            <strong>{{ number_format($reviewSummary['stale_count'] ?? 0) }}</strong>
            <span>Waiting &gt; 72h</span>
            <small>@if(($reviewSummary['stale_count'] ?? 0) > 0){{ $inrShort($reviewSummary['stale_amount'] ?? 0) }}@if(!empty($reviewSummary['stale_oldest_client'])) — oldest: {{ $reviewSummary['stale_oldest_client'] }}@endif @else none @endif</small>
        </div>
    </section>

    {{-- Chips only set the existing Payment Status filter; other filters are kept. --}}
    <nav class="pr-chips" aria-label="Approval status">
        <a href="{{ $chipUrl('pending') }}" class="{{ $currentApproval === 'pending' ? 'is-on' : '' }}">Pending · {{ number_format($pendingLeadCount ?? 0) }}</a>
        <a href="{{ $chipUrl('approved') }}" class="{{ $currentApproval === 'approved' ? 'is-on' : '' }}">Approved</a>
        <a href="{{ $chipUrl('rejected') }}" class="{{ $currentApproval === 'rejected' ? 'is-on' : '' }}">Rejected</a>
        <a href="{{ $chipUrl('') }}" class="{{ $currentApproval === '' ? 'is-on' : '' }}">All</a>
    </nav>

    <form method="GET" action="{{ route('admin.account.payment-review') }}" id="filter-form" class="pr-filters">
        @if(request('search'))<input type="hidden" name="search" value="{{ request('search') }}">@endif
        <label class="pr-field">Created from<input type="date" name="from_date" value="{{ request('from_date') }}"></label>
        <label class="pr-field">Created to<input type="date" name="to_date" value="{{ request('to_date') }}"></label>
        <label class="pr-field">Service
            <select name="service_name">
                <option value="">All services</option>
                @foreach ($services as $service)
                    <option value="{{ $service->id }}" {{ request('service_name') == $service->id ? 'selected' : '' }}>{{ $service->service }}</option>
                @endforeach
            </select>
        </label>
        <label class="pr-field">Status
            <select name="status">
                <option value="">All status</option>
                <option value="3" {{ request('status') == '3' ? 'selected' : '' }}>Full Paid</option>
                <option value="4" {{ request('status') == '4' ? 'selected' : '' }}>Partial Paid</option>
            </select>
        </label>
        <label class="pr-field">Payment status
            <select name="payment_status">
                <option value="">All payment status</option>
                <option value="pending" {{ request('payment_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="approved" {{ request('payment_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                <option value="rejected" {{ request('payment_status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
            </select>
        </label>
        <label class="pr-field">Service date<input type="date" name="service_date" value="{{ request('service_date') }}"></label>
        <div class="pr-filter-actions">
            <button type="button" class="pr-ghost" onclick="clearFilters()">Reset</button>
            <button type="submit" class="pr-primary">Filter</button>
        </div>
    </form>

    <section class="box custom-box pr-queue">
        <div class="box-header">
            <div class="box-title">Payment transactions</div>
        </div>
        <div class="box-body">
            <div class="pr-table-wrap">
                <table class="table table-datatable server-paginated no-horizontal-scroll pr-table" width="100%" data-empty-msg="No payment records found">
                    <thead>
                        <tr>
                            <th class="pr-col-client">Client</th>
                            <th>Service date</th>
                            <th class="pr-col-service">Product</th>
                            <th>Received / Total</th>
                            <th>Balance</th>
                            <!-- <th>Status</th> -->
                            <th>Payment status</th>
                            <th class="pr-col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($payments as $index => $payment)
                            @php
                                $followupStatusLabels = [2 => 'Cancelled', 3 => 'Full Paid', 4 => 'Partial Paid', 5 => 'Ride Completed', 6 => 'Pending', 7 => 'Rescheduled', 8 => 'Approved', 9 => 'Rejected', 10 => 'Sales Follow-up', 11 => 'Close Follow-up'];
                                $statusLabel = $followupStatusLabels[$payment->status] ?? 'Unknown';
                                $statusTone = [3 => 'green', 4 => 'amber', 2 => 'red', 8 => 'blue', 9 => 'neutral'][$payment->status] ?? 'neutral';
                                $received = (float) ($payment->received_amount ?? 0);
                                $receivedApproved = (float) ($payment->received_amount_approved ?? 0);
                                $total = (float) ($payment->total_amount ?? 0);
                                if (isset($payment->audit_status) && $payment->audit_status == 1 && $receivedApproved >= $total && $total > 0) {
                                    [$approvalLabel, $approvalTone] = ['Approved', 'green'];
                                } elseif (isset($payment->audit_status) && $payment->audit_status == 2) {
                                    [$approvalLabel, $approvalTone] = ['Rejected', 'red'];
                                } else {
                                    [$approvalLabel, $approvalTone] = ['Pending', 'amber'];
                                }
                                $initials = collect(preg_split('/\s+/', trim($payment->first_name ?: '?')))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            @endphp
                            <tr>
                                <td data-label="Client">
                                    <div class="pr-client">
                                        <span class="pr-avatar" aria-hidden="true">{{ $initials }}</span>
                                        <div>
                                            <strong>{{ $payment->first_name ?: 'N/A' }}</strong>
                                            <small>{{ $payment->phone_number ?: 'No phone' }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Service date">
                                    <strong>{{ $payment->from_date ? \Carbon\Carbon::parse($payment->from_date)->format('d M Y') : 'N/A' }}</strong>
                                </td>
                                <td data-label="Product">
                                    <strong>{{ ($tableProductNames[$payment->lead_id] ?? '') ?: 'N/A' }}</strong>
                                    @if($payment->from_place || $payment->to_place)<small>{{ $payment->from_place ?: '—' }} &rarr; {{ $payment->to_place ?: '—' }}</small>@endif
                                </td>
                                <td data-label="Received / Total">
                                    <strong>₹{{ number_format($received, 2) }} / ₹{{ number_format($total, 2) }}</strong>
                                    <small>Approved ₹{{ number_format($receivedApproved, 2) }}</small>
                                </td>
                                <td data-label="Balance">
                                    @if((float) $payment->balance > 0)
                                        <span class="pr-tag {{ $received > 0 ? 'amber' : 'red' }}">₹{{ number_format($payment->balance, 2) }}</span>
                                    @else
                                        <strong>—</strong>
                                    @endif
                                </td>
                                <!-- <td data-label="Status"><span class="pr-tag {{ $statusTone }}">{{ $statusLabel }}</span></td> -->
                                <td data-label="Payment status"><span class="pr-tag {{ $approvalTone }}">{{ $approvalLabel }}</span></td>
                                <td data-label="Action">
                                    <a aria-label="Review payment for {{ $payment->first_name }}" class="pr-primary pr-row-btn view-payment-btn"
                                        data-id="{{ $payment->followup_id }}" data-hs-overlay="#view-payment-review">Review</a>
                                </td>
                            </tr>
                        @empty
                            <tr class="pr-empty-row"><td colspan="8">No payment records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if(isset($paymentsPaginator) && $paymentsPaginator->hasPages())
                <div class="mt-4">
                    {{ $paymentsPaginator->onEachSide(1)->appends(request()->except('page'))->links() }}
                </div>
            @endif
        </div>
    </section>
</div>
<!-- Review Payment dialog (redesigned). All element IDs used by the existing scripts are unchanged. -->
<div id="view-payment-review" class="view-payment-review hs-overlay hidden ti-modal pr-review-modal" tabindex="-1"
    role="dialog" aria-modal="true" aria-labelledby="pr-review-title">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out pr-review-box">
        <div class="ti-modal-content pr-review-content">
            <div class="pr-review-head">
                <div class="pr-review-heading">
                    <h5 id="pr-review-title">Review payment — <span id="client-name-header">Loading...</span></h5>
                    <div class="pr-review-sub">
                        <span class="pr-pill amber" id="pr-review-status" hidden></span>
                        <a href="#" id="pr-open-lead" target="_blank" rel="noopener" hidden>Open lead <span aria-hidden="true">&rarr;</span></a>
                        <span class="pr-review-muted" id="pr-received-at"></span>
                       <span
                            class="pr-review-title pr-pill amber"
                            id="pr-sales-person"
                        >
                            <i
                                class="ri-user-line"
                                aria-hidden="true"
                            ></i>

                            Sales Person:

                            <strong id="pr-sales-person-name">
                                Loading...
                            </strong>
                        </span>
                    </div>
                </div>
                <button type="button" class="pr-review-close" data-hs-overlay="#view-payment-review" aria-label="Close">
                    <i class="ri-close-line" aria-hidden="true"></i>
                </button>
            </div>

            <div class="pr-verdict">
                <div class="pr-verdict-cell"><span>Total amount</span><strong id="total-amount">₹0</strong></div>
                <div class="pr-verdict-cell"><span>Received amount</span><strong class="is-ok" id="paid-amount">₹0</strong></div>
                <div class="pr-verdict-cell"><span>Balance</span><strong class="is-due" id="balance-amount">₹0</strong></div>
                <div class="pr-verdict-note" id="pr-verdict-note" hidden></div>
            </div>

            <div class="ti-modal-body pr-review-body view-payment-review-body">
                <div class="pr-review-col pr-review-left">
                    <section class="pr-block">
                        <h6>Client information</h6>
                        <dl class="pr-kv">
                            <div><dt>Full name</dt><dd id="client-name">-</dd></div>
                            <div><dt>Phone</dt><dd id="client-phone">-</dd></div>
                            <div><dt>WhatsApp</dt><dd id="client-whatsapp">-</dd></div>
                            <div><dt>Email</dt><dd id="client-email">-</dd></div>
                            <div><dt>Country</dt><dd id="client-country">-</dd></div>
                            <div><dt>City</dt><dd id="client-city">-</dd></div>
                            <div class="pr-kv-wide"><dt>Address</dt><dd id="client-address">-</dd></div>
                        </dl>
                    </section>
                    <section class="pr-block">
                        <h6>Travel information</h6>
                        <div id="travel-information-container" class="pr-travel">
                            <!-- Travel information will be populated dynamically -->
                            <p class="pr-review-muted">Loading travel information...</p>
                        </div>
                    </section>
                    <section class="pr-block">
                        <h6>Service information</h6>
                        <dl class="pr-kv pr-kv-two">
                            <div><dt>Service</dt><dd id="service-name">-</dd></div>
                            <div><dt>Extra services</dt><dd id="extra-services">-</dd></div>
                        </dl>
                    </section>
                </div>

                <div class="pr-review-col pr-review-right">
                    <section class="pr-block">
                        <h6>Payment history</h6>
                        <p class="pr-review-hint">Each pending payment has its own decision: choose the method and date, add a narration, then Approve or Reject.</p>
                        <div id="payment-history-container">
                            <!-- Payment history will be populated dynamically -->
                        </div>
                    </section>
                </div>
                <!-- <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Narration</h5>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <textarea class="form-control" id="text-area" rows="3" placeholder="Add notes about this payment ..."></textarea>
                                </div>

                            </div>
                        </div>
                    </div> -->
                <!-- <div class="mt-5">
                    <form id="payment-action-form">
                        <input type="hidden" id="payment-followup-id" name="followup_id" value="">
                        <button type="submit" id="approve-payment-btn"
                            class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn"
                            style="display: inline-block;">Approve all Payments</button>
                        <button type="button" id="reject-payment-btn" class="ti-btn ti-btn-outline-primary ti-btn-wave"
                            style="display: inline-block;">Reject all Payments </button>
                    </form>
                </div> -->
            </div>

            <div class="pr-review-foot">
                <span class="pr-review-muted">Approved or rejected payments are locked; a payment already decided by someone else can't be decided twice.</span>
                <button type="button" class="pr-ghost" data-hs-overlay="#view-payment-review">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Viewer Modal -->
<div id="receipt-viewer-modal" class="hs-overlay hidden ti-modal">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full">
            <div class="ti-modal-header">
                <h6 class="modal-title" id="staticBackdropLabel">Payment Receipt
                </h6>
                <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                    data-hs-overlay="#receipt-viewer-modal">
                    <span class="sr-only">Close</span>
                    <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                        xmlns="http://www.w3.org/2000/svg">
                        <path
                            d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                            fill="currentColor"></path>
                    </svg>
                </button>
            </div>
            <div class="ti-modal-body px-4">
                <div id="receipt-content" class="text-center">
                    <p>Loading receipt...</p>
                </div>
            </div>
            <div class="ti-modal-footer">
                <a id="download-receipt-modal-btn" href="#" class="ti-btn ti-btn-primary-full align-middle" download>
                    <i class="ri-download-line me-1"></i>Download
                </a>
                <button type="button" class="hs-dropdown-toggle ti-btn  ti-btn-secondary-full align-middle"
                    data-hs-overlay="#receipt-viewer-modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Message Modal -->
<div id="success-message-modal" class="hs-overlay hidden ti-modal">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-green-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-blue-600 mb-2" id="success-message-title">Payment Approved!</h3>
                <p class="text-gray-600 mb-1" id="success-message-text">The payment has been verified and marked as
                    received.</p>
                <p class="text-sm text-gray-500" id="success-message-date">Date: 12 Jul 2025 | 11:45 AM</p>
            </div>

            <!-- Payment Approval Modal -->
            <div id="payment-approval-modal" class="hs-overlay hidden ti-modal">
                <div
                    class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
                    <div class="ti-modal-content w-full max-w-lg mx-auto">
                        <div class="ti-modal-header">
                            <h6 class="modal-title" id="payment-approval-title">Payment Approval Details</h6>
                            <button type="button"
                                class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                                data-hs-overlay="#payment-approval-modal">
                                <span class="sr-only">Close</span>
                                <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                        fill="currentColor"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="ti-modal-body px-4">
                            <form id="payment-approval-form">
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="xl:col-span-12 col-span-12">
                                        <label class="ti-form-label">Payment Method <span
                                                class="text-red-500">*</span></label>
                                        <select class="form-control" id="payment-method" name="payment_method" required>
                                            <option value="">Select Payment Method</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Online Payment">Online Payment</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Debit Card">Debit Card</option>
                                            <option value="UPI">UPI</option>
                                            <option value="Cheque">Cheque</option>
                                            <option value="Other">Other</option>
                                            <option value="Net Banking">Net Banking</option>
                                            <option value="Website">Website</option>

                                        </select>
                                    </div>
                                    <div class="xl:col-span-12 col-span-12">
                                        <label class="ti-form-label">Payment Received Date <span
                                                class="text-red-500">*</span></label>
                                        <input type="date" class="form-control" id="received-date-time"
                                            name="received_date" max="{{ date('Y-m-d') }}" required>
                                    </div>
                                    <div class="xl:col-span-12 col-span-12">
                                        <label class="ti-form-label">Narration/Notes</label>
                                        <textarea class="form-control" id="approval-narration" name="narration" rows="3"
                                            placeholder="Add any additional notes about this payment approval..."></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="ti-modal-footer">
                            <button type="button" class="hs-dropdown-toggle ti-btn ti-btn-secondary-full align-middle"
                                data-hs-overlay="#payment-approval-modal">
                                Cancel
                            </button>
                            <button type="button" id="confirm-approve-btn"
                                class="ti-btn ti-btn-primary-full align-middle">
                                Approve Payment
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="ti-modal-footer justify-center">
                <button type="button" class="ti-btn bg-primary text-white !font-medium"
                    data-hs-overlay="#success-message-modal" onclick="location.reload()">
                    OK
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Error Message Modal -->
<div id="error-message-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-red-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-red-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-red-600 mb-2" id="error-message-title">Validation Error</h3>
                <p class="text-gray-600 mb-1" id="error-message-text">Please fill in all required fields.</p>
            </div>
            <div class="ti-modal-footer justify-center">
                <button type="button" class="ti-btn bg-red-600 text-white" data-hs-overlay="#error-message-modal"
                    id="error-modal-ok-btn">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmation-modal" class="hs-overlay hidden ti-modal">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-md mx-auto">
            <div class="ti-modal-body p-6 text-center">
                <div class="mb-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-yellow-100 rounded-full flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-yellow-600" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-semibold text-gray-800 mb-2" id="confirmation-title">Confirm Action</h3>
                <p class="text-gray-600 mb-1" id="confirmation-text">Are you sure you want to proceed?</p>
            </div>
            <div class="ti-modal-footer justify-center space-x-3">
                <button type="button" class="ti-btn bg-gray-500 text-white"
                    data-hs-overlay="#confirmation-modal">Cancel</button>
                <button type="button" class="ti-btn bg-primary text-white" id="confirm-action-btn">Confirm</button>
            </div>
        </div>
    </div>
</div>

@stop

@push('scripts')
<script>
    // Function to show error modal
        function showErrorModal(title, message) {
            $('#error-message-title').text(title || 'Error');
            $('#error-message-text').text(message || 'An error occurred. Please try again.');

            // Handle the OK button click to reopen the review modal
            $('#error-modal-ok-btn').off('click').on('click', function() {
                window.HSOverlay.close(document.getElementById('error-message-modal'));
                setTimeout(() => {
                    window.HSOverlay.open(document.getElementById('view-payment-review'));
                }, 100);
            });

            // Use native event listener for hs.overlay.closed
            var errorModal = document.getElementById('error-message-modal');
            // Remove any previous listener
            if (errorModal._hsClosedListener) {
                errorModal.removeEventListener('hs.overlay.closed', errorModal._hsClosedListener);
            }
            errorModal._hsClosedListener = function() {
                setTimeout(function() {
                    window.HSOverlay.open(document.getElementById('view-payment-review'));
                }, 100);
            };
            errorModal.addEventListener('hs.overlay.closed', errorModal._hsClosedListener);

            // Fallback: Use MutationObserver to detect when modal is hidden
            if (errorModal._observer) {
                errorModal._observer.disconnect();
            }
            errorModal._observer = new MutationObserver(function(mutationsList) {
                for (var mutation of mutationsList) {
                    if (mutation.attributeName === 'class') {
                        // Modal is hidden when it has 'hidden' class
                        if (errorModal.classList.contains('hidden')) {
                            setTimeout(function() {
                                window.HSOverlay.open(document.getElementById('view-payment-review'));
                            }, 100);
                        }
                    }
                }
            });
            errorModal._observer.observe(errorModal, {
                attributes: true
            });

            window.HSOverlay.open(errorModal);
        }

        // Function to show confirmation modal
        function showConfirmationModal(title, message, onConfirm) {
            $('#confirmation-title').text(title || 'Confirm Action');
            $('#confirmation-text').text(message || 'Are you sure you want to proceed?');

            // Remove any existing click handlers
            $('#confirm-action-btn').off('click');

            // Add new click handler
            $('#confirm-action-btn').on('click', function() {
                window.HSOverlay.close(document.getElementById('confirmation-modal'));
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });

            window.HSOverlay.open(document.getElementById('confirmation-modal'));
        }

        $(document).ready(function() {
            // Whether the logged-in user is allowed to see the "Paid Directly to Vendor" option
            @php
                $isOpsOrAccounts = in_array(auth()->user()->userType->user_type ?? '', array_merge(\App\Models\UserType::OPERATIONS_ROLES, \App\Models\UserType::ACCOUNTS_ROLES));
                $isSuperAdmin = (auth()->user()->userType->user_type ?? '') === \App\Models\UserType::SUPER_ADMIN;
                $today = date('Y-m-d');
            @endphp
            const isOpsOrAccounts = @json($isOpsOrAccounts);
            const isSuperAdmin = @json($isSuperAdmin);
            const today = @json($today);
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Initially hide filter section and set icon to down
            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function() {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });
            // DataTable initialization is handled globally in header.blade.php
            // Log changes to payment method and received date
            $("select[name='payment_method']").on('change', function() {
                console.log('Payment Method changed to:', $(this).val());
            });
            $("#datetime").on('change', function() {
                console.log('Received Date changed to:', $(this).val());
            });
            // Initialize datetime picker with old value or current date
            var oldDate = $("#datetime").val();
            $("#datetime").flatpickr({
                enableTime: false,
                dateFormat: "Y-m-d",
                defaultDate: oldDate ? oldDate : new Date(),
                time_24hr: false,
                minuteIncrement: 1,
                allowInput: true,
                clickOpens: true,
            });
            // Handle view payment button click
            // $('.view-payment-btn').on('click', function() {
            //     const followupId = $(this).data('id');
            //     loadPaymentDetails(followupId);
            // });

            $(document).on('click', '.view-payment-btn', function() {
                const followupId = $(this).data('id');
                loadPaymentDetails(followupId);
                // 2. Manually open the overlay using the Preline API
                const modalElement = document.getElementById('view-payment-review');
                if (window.HSOverlay) {
                    window.HSOverlay.open(modalElement);
                }
            });


            // Load payment details function
            function loadPaymentDetails(followupId) {
                $.ajax({
                    url: `/admin/account/payment-review/${followupId}`,
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        // Show loading state
                        $('#client-name-header').text('Loading...');
                        $('#pr-sales-person-name')
                        .text(
                            'Loading...'
                        );
                        $('#pr-review-status, #pr-verdict-note, #pr-open-lead').prop('hidden', true);
                        $('#pr-received-at').text('');
                    },
                    success: function(response) {
                        const {
                        followup,
                        client,
                        representative,
                        rides,
                        services,
                        extraServices,
                        paymentHistory,
                        latestAudit
                    } = response;

                        // Set form ID
                        $('#payment-followup-id').val(followup.id);

                        // Update header
                        $('#client-name-header').text(client.name || 'Unknown Client');
                        /*
                        |--------------------------------------------------------------------------
                        | Assigned Sales Person
                        |--------------------------------------------------------------------------
                        */

                        $('#pr-sales-person-name')
                            .text(
                                representative?.name
                                || 'Unassigned'
                            );

                        // Update client information
                        $('#client-name').text(client.name || '-');
                        $('#client-email').text(client.email || '-');
                        $('#client-phone').text(client.contact_number || '-');
                        $('#client-whatsapp').text(client.alternate_number || '-');
                        $('#client-country').text(client.country ? client.country.name : '-');
                        $('#client-city').text(client.city ? client.city.name : '-');
                        $('#client-address').text(client.address || '-');

                        // Update travel information
                        updateTravelInformation(rides);

                        // Update service information
                        const serviceNames = services.map(service => service.service).join(', ');
                        $('#service-name').text(serviceNames || '-');

                        const extraServiceNames = extraServices.map(service => service.extra_service)
                            .join(', ');
                        $('#extra-services').text(extraServiceNames || '-');

                        // Update payment information
                        const totalAmount = parseFloat(followup.total_amount || 0);
                        const receivedAmount = parseFloat(followup.received_amount || 0);

                        if (latestAudit) {
                            // Set payment method
                            $('select[name="payment_method"]').val(latestAudit.payment_method);

                            // Set received date
                            const receivedDate = new Date(latestAudit.paid_date);
                            const formattedDate = receivedDate.toLocaleDateString('en-GB', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric'
                            });
                            // Convert to YYYY-MM-DD format for date input
                            const formattedDateForInput = receivedDate.toISOString().split('T')[0];
                            $('#datetime').val(formattedDateForInput);

                            // Set narration
                            $('#text-area').val(latestAudit.narration || '');

                        }
                        // Calculate total paid amount only from approved payments (audit_trail.payment_status = 1)
                        let totalPaidAmount = 0;
                        if (paymentHistory && paymentHistory.length > 0) {
                            totalPaidAmount = paymentHistory.reduce((sum, payment) => {
                                // Only count approved payments
                                if (payment.audit_trail && payment.audit_trail.payment_status ==
                                    1) {
                                    return sum + parseFloat(payment.amount || 0);
                                }
                                return sum;
                            }, 0);
                        }

                        // Calculate remaining balance: Total Amount - Total Paid Amount
                        const balance = totalAmount - totalPaidAmount;

                        $('#total-amount').text(
                            `₹${totalAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}`);
                        $('#paid-amount').text(
                            `₹${totalPaidAmount.toLocaleString('en-IN', {minimumFractionDigits: 2})}`
                        );
                        $('#balance-amount').text(
                            `₹${balance.toLocaleString('en-IN', {minimumFractionDigits: 2})}`);

                        // Update payment history dynamically
                        updatePaymentHistory(paymentHistory);

                        // Redesigned dialog header and summary strip (display only).
                        updateReviewSummary(followup, paymentHistory || [], totalAmount, totalPaidAmount, balance);

                        // Show/hide action buttons based on status
                        const currentStatus = parseInt(followup.status);
                        // Always show approve/reject buttons for payment review
                        // Status 3 = Full Payment Received, 4 = Partial Payment Received
                        // These payments need to be approved or rejected for audit purposes
                        $('#approve-payment-btn, #reject-payment-btn').show();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error loading payment details:', error);
                        alert('Error loading payment details. Please try again.');
                    }
                });
            }

            // Fills the dialog's status pill, lead link, received time and match note. Reads only; changes nothing.
            function updateReviewSummary(followup, history, totalAmount, approvedAmount, balance) {
                const money = value => '₹' + Number(value || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
                const pending = history.filter(item => ![1, 2].includes(parseInt(item.audit_trail?.payment_status)));
                const pendingAmount = pending.reduce((sum, item) => sum + parseFloat(item.amount || 0), 0);
                const oldestPending = pending.map(item => new Date(item.created_at)).filter(d => !isNaN(d)).sort((a, b) => a - b)[0];

                const $status = $('#pr-review-status').removeClass('amber green');
                if (pending.length) {
                    const days = oldestPending ? Math.max(0, Math.floor((Date.now() - oldestPending) / 86400000)) : null;
                    const age = days === null ? '' : (days === 0 ? ' · today' : ` · ${days} ${days === 1 ? 'day' : 'days'}`);
                    $status.addClass('amber').text(`Pending approval${pending.length > 1 ? ` (${pending.length})` : ''}${age}`).prop('hidden', false);
                } else {
                    $status.addClass('green').text('No pending payments').prop('hidden', false);
                }

                const leadId = followup.lead_id;
                $('#pr-open-lead').attr('href', leadId ? `{{ url('admin/leads') }}/${leadId}` : '#').prop('hidden', !leadId);

                const latest = history[0];
                const receivedAt = latest && latest.created_at ? new Date(latest.created_at) : null;
                $('#pr-received-at').text(receivedAt && !isNaN(receivedAt)
                    ? 'latest received ' + receivedAt.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
                    : '');

                $('#paid-amount').toggleClass('is-ok', approvedAmount > 0);
                $('#balance-amount').removeClass('is-ok is-due is-over').addClass(Math.abs(balance) < 0.005 ? 'is-ok' : (balance < 0 ? 'is-over' : 'is-due'));

                let note = '';
                let tone = 'green';
                if (Math.abs(balance) < 0.005 && totalAmount > 0) {
                    note = '✓ Approved payments match the booking total.';
                } else if (balance < -0.005) {
                    note = `Approved payments are ${money(Math.abs(balance))} more than the booking total — please check.`;
                    tone = 'red';
                } else {
                    note = `${money(balance)} of ${money(totalAmount)} is not yet approved.`;
                    tone = 'amber';
                }
                if (pending.length) {
                    note += ` ${pending.length} pending ${pending.length === 1 ? 'payment' : 'payments'} worth ${money(pendingAmount)} below.`;
                }
                $('#pr-verdict-note').removeClass('green amber red').addClass(tone).text(note).prop('hidden', false);
            }

            // Handle approve payment (Approve All)
            $('#approve-payment-btn').on('click', function(e) {
                e.preventDefault();
                // Prevent approving when there are no pending payments
                if ($(this).prop('disabled')) {
                    showErrorModal('No Payments', 'There are no pending payments to approve.');
                    return;
                }
                const followupId = $('#payment-followup-id').val();
                const paymentMethod = $('select[name="payment_method"]').val();
                const receivedDate = $('#datetime').val();
                const narration = $('#text-area').val();
                // Reset error messages
                $('.error-message').hide().text('');
                // Validate required fields
                if (!paymentMethod || !receivedDate) {
                    showErrorModal('Validation Error',
                        'Please fill in Payment Method and Received Date fields.');
                    return;
                }
                // Validate date format (YYYY-MM-DD)
                const datePattern = /^\d{4}-\d{2}-\d{2}$/;
                if (!datePattern.test(receivedDate)) {
                    showErrorModal('Date Format Error',
                        'Please select a valid date.');
                    return;
                }
                showConfirmationModal(
                    'Approve All Payments',
                    'Are you sure you want to approve all payments for this lead?',
                    function() {
                        submitPaymentAction(followupId, 'approve-all', {
                            payment_method: paymentMethod,
                            received_date: receivedDate,
                            narration: narration
                        });
                    }
                );
            });

            // Handle reject payment with modal confirmation (Reject All)
            $('#reject-payment-btn').on('click', function(e) {
                e.preventDefault();
                // Prevent rejecting when there are no pending payments
                if ($(this).prop('disabled')) {
                    showErrorModal('No Payments', 'There are no pending payments to reject.');
                    return;
                }
                const followupId = $('#payment-followup-id').val();
                const narration = $('#text-area').val();

                // Show custom confirmation modal
                showRejectConfirmation(function() {
                    const paymentMethod = $('select[name="payment_method"]').val();
                    submitPaymentAction(followupId, 'reject-all', {
                        payment_method: paymentMethod,
                        narration: narration
                    });
                });
            });

            // Show reject confirmation modal
            function showRejectConfirmation(onConfirm) {
                // Use the confirmation modal for rejection, not the success modal
                showConfirmationModal(
                    'Reject All Payments',
                    'Are you sure you want to reject all payments for this lead? This action cannot be undone.',
                    function() {
                        if (typeof onConfirm === 'function') onConfirm();
                    }
                );
            }

            // Submit payment action
            function submitPaymentAction(followupId, action, data) {
                $.ajax({
                    url: `/admin/account/payment-review/${followupId}/${action}`,
                    type: 'POST',
                    data: data,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    beforeSend: function() {
                        $('#approve-payment-btn, #reject-payment-btn').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            showSuccessMessage(action, response.message);
                        } else {
                            showErrorModal('Processing Error', response.message ||
                                'Something went wrong');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error submitting payment action:', error);
                        console.error(xhr.responseText);
                        showErrorModal('Network Error',
                            'Error submitting payment action. Please try again.');
                    },
                    complete: function() {
                        $('#approve-payment-btn, #reject-payment-btn').prop('disabled', false);
                    }
                });
            }
            // Show success message modal
            function showSuccessMessage(action, message) {
                const currentDate = new Date();
                const dateStr = currentDate.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                });
                const timeStr = currentDate.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });

                if (action === 'approve') {
                    $('#success-message-title').text('Payment Approved!');
                    $('#success-message-text').text('The payment has been verified and marked as received.');
                } else if (action === 'reject') {
                    $('#success-message-title').text('Payment Rejected!');
                    $('#success-message-text').text('The payment has been reviewed and marked as rejected.');
                } else {
                    // fallback for other actions
                    $('#success-message-title').text('Success!');
                    $('#success-message-text').text(message || 'Action completed successfully.');
                }

                $('#success-message-date').text(`Date: ${dateStr} | ${timeStr}`);

                // Close the payment review modal first
                window.HSOverlay.close(document.getElementById('view-payment-review'));

                // Always reset modal button handlers/content
                var okBtn = $(
                    '#success-message-modal .ti-btn.bg-primary, #success-message-modal .ti-btn.bg-primary.text-white'
                );
                okBtn.text('OK');
                okBtn.off('click').on('click', function() {
                    location.reload();
                });
                $('#success-message-date').show();

                // Show only the success modal
                window.HSOverlay.open(document.getElementById('success-message-modal'));
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
                        const accordionId = `payment-trip-accordion-${index}`;

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

            // Function to update payment history
            function updatePaymentHistory(paymentHistory) {
                const container = $('#payment-history-container');

                if (!paymentHistory || paymentHistory.length === 0) {
                    container.html(`
                <div class="text-center py-4">
                    <p class="text-gray-500">No payment history found</p>
                </div>
            `);
                    return;
                }

                let historyHtml = '';
                paymentHistory.forEach((payment, index) => {
                    // payment object is used below to render history
                    const amount = parseFloat(payment.amount);
                    const totalAmount = parseFloat(payment.total_amount || 0);
                    const percentage = totalAmount > 0 ? (amount / totalAmount * 100).toFixed(0) : 0;

                    let statusText = '';
                    let statusClass = '';

                    // Check audit trail status first, then fallback to follow-up status
                    const auditStatus = payment.audit_trail?.payment_status;

                    if (auditStatus == 1) {
                        statusText = 'Payment Approved';
                        statusClass = 'bg-green-600 text-white';
                    } else if (auditStatus == 2) {
                        statusText = 'Payment Rejected';
                        statusClass = 'bg-red-600 text-white';
                    } else {
                        // Fallback to follow-up status
                        switch (parseInt(payment.status)) {
                            case 2:
                                statusText = 'Payment Cancelled';
                                statusClass = 'bg-red-600 text-white';
                                break;
                            case 3:
                                statusText = 'Full Payment Received';
                                statusClass = 'bg-green-600 text-white';
                                break;
                            case 4:
                                statusText = 'Partial Payment Received';
                                statusClass = 'bg-warning text-white';
                                break;
                            case 8:
                                statusText = 'Payment Approved';
                                statusClass = 'bg-green-600 text-white';
                                break;
                            case 9:
                                statusText = 'Payment Rejected';
                                statusClass = 'bg-red-600 text-white';
                                break;
                            default:
                                statusText = 'Payment Pending';
                                statusClass = 'bg-blue-600 text-white';
                        }
                    }

                    // Check if payment has audit trail entry
                    const auditEntry = payment.audit_trail || {};
                    const isApproved = auditEntry.payment_status == 1;
                    const isRejected = auditEntry.payment_status == 2;

                    // Format created by information - check multiple possible field names
                    let createdByInfo = '';
                    if (payment.created_by_name) {
                        createdByInfo = payment.created_by_name;
                        if (payment.created_by_email) {
                            createdByInfo += ` | ${payment.created_by_email}`;
                        }
                    } else if (payment.user && payment.user.name) {
                        createdByInfo = payment.user.name;
                        if (payment.user.email) {
                            createdByInfo += ` | ${payment.user.email}`;
                        }
                    } else if (payment.created_by) {
                        createdByInfo = payment.created_by;
                    }

                    // Card layout (redesign). The inputs and buttons below keep the same classes and
                    // data-payment-id attributes the approve / reject / receipt handlers read.
                    const pillTone = isApproved ? 'green' : (isRejected ? 'red' : 'amber');
                    const pillText = (isApproved || isRejected) ? statusText : 'Awaiting approval';
                    const methodLabel = payment.payment_method ? payment.payment_method.charAt(0).toUpperCase() + payment.payment_method.slice(1) : '';
                    const createdAt = payment.created_at ? new Date(payment.created_at) : null;
                    const metaParts = [
                        methodLabel,
                        payment.paid_date ? new Date(payment.paid_date).toLocaleDateString('en-GB') : '',
                        createdAt && !isNaN(createdAt) ? 'received ' + createdAt.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '',
                        (!isApproved && !isRejected) ? statusText : '',
                        (payment.created_by_name || payment.user?.name || createdByInfo) ? 'recorded by ' + (payment.created_by_name || payment.user?.name || createdByInfo) : ''
                    ].filter(Boolean);
                    const receiptButtons = `
                                <div class="pr-receipt-row">
                                    <button type="button" class="pr-ghost pr-small view-receipt-btn"
                                            data-payment-id="${payment.id}"
                                            data-file="${payment.file || ''}"
                                            ${!payment.file ? 'disabled' : ''}>
                                        <i class="ri-eye-line" aria-hidden="true"></i>${payment.file ? 'View receipt' : 'No receipt'}
                                    </button>
                                    ${payment.file ? `<a href="${followupReceiptUrl(payment.file)}" class="pr-ghost pr-small download-receipt-btn" download><i class="ri-download-line" aria-hidden="true"></i>Download</a>` : ''}
                                </div>`;

                    historyHtml += `
                        <div class="payment-card pr-pentry ${isRejected ? 'is-rejected' : ''}">
                            <div class="pr-ptop">
                                <span class="pr-pamt">₹${amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}</span>
                                <span class="pr-pill ${pillTone}">${pillText}</span>
                            </div>
                            ${metaParts.length ? `<div class="pr-pmeta">${metaParts.join(' · ')}</div>` : ''}
                            ${payment.followup_note ? `<div class="pr-narr"><b>Note</b>${payment.followup_note}</div>` : ''}
                    `;

                    if (!isApproved && !isRejected) {
                        // Pending: receipt, then the decision. Method / date / narration open from the toggle.
                        historyHtml += `
                            ${receiptButtons}
                            <div class="pr-decide">
                                <button type="button" class="pr-decision-toggle" aria-expanded="false" aria-controls="pr-decision-${payment.id}">
                                    <span>Payment details</span><small>Payment Method *, Date *, Narration (Optional)</small>
                                    <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
                                </button>
                                <div class="pr-decision-fields" id="pr-decision-${payment.id}" hidden>
                                    <div class="pr-decision-grid">
                                        <label class="pr-field-label">Payment Method *
                                            <select class="ti-form-select rounded-sm form-control-sm w-full payment-method-select" data-payment-id="${payment.id}" required>
                                                <option value="">Select method</option>
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
                                                    @php
                                                        $currentUserType = auth()->user()->userType->user_type ?? null;
                                                        $vendorAllowedRoles = array_merge(\App\Models\UserType::OPERATIONS_ROLES, \App\Models\UserType::ACCOUNTS_ROLES);
                                                    @endphp
                                                    @if(in_array($currentUserType, $vendorAllowedRoles))
                                                        <option value="Paid Directly to Vendor">Paid Directly to Vendor</option>
                                                    @endif
                                            </select>
                                        </label>
                                        <label class="pr-field-label">Date *
                                            <input type="date" class="form-control form-control-sm received-date-input"
                                                data-payment-id="${payment.id}"
                                                min="${isSuperAdmin ? '' : today}"
                                                value="${today}"
                                                required>
                                        </label>
                                        <label class="pr-field-label pr-field-wide">Narration (Optional)
                                            <textarea class="form-control form-control-sm narration-textarea"
                                                    data-payment-id="${payment.id}"
                                                    rows="2"
                                                    placeholder="Add notes about this payment..."></textarea>
                                        </label>
                                    </div>
                                </div>
                                <div class="pr-decision-actions">
                                    <button type="button" class="pr-approve approve-individual-payment-btn" data-payment-id="${payment.id}">
                                        <i class="ri-check-line" aria-hidden="true"></i>Approve payment
                                    </button>
                                    <button type="button" class="pr-reject reject-individual-payment-btn" data-payment-id="${payment.id}">
                                        <i class="ri-close-line" aria-hidden="true"></i>Reject
                                    </button>
                                </div>
                            </div>
                        `;
                    } else {
                        // Approved / rejected: read-only details.
                        historyHtml += `
                            <div class="pr-pgrid">
                                <div><span>Payment method</span><strong>${auditEntry?.payment_method || 'Not specified'}</strong></div>
                                <div><span>Date</span><strong>${auditEntry?.paid_date ? new Date(auditEntry.paid_date).toLocaleDateString('en-GB') : 'Not specified'}</strong></div>
                            </div>
                            ${auditEntry?.narration ? `<div class="pr-narr"><b>Narration</b>${auditEntry.narration}</div>` : ''}
                            ${receiptButtons}
                        `;
                    }

                    // Close the payment-card div
                    historyHtml += `</div>`;

                });

                container.html(historyHtml);

                // After rendering payment history, enable/disable the global approve/reject buttons
                // based on whether there are any pending payments (payments that can be approved/rejected).
                (function() {
                    var pendingApproveBtns = $('#view-payment-review .approve-individual-payment-btn');
                    var pendingRejectBtns = $('#view-payment-review .reject-individual-payment-btn');
                    var pendingCount = Math.max(pendingApproveBtns.length, pendingRejectBtns.length);
                    if (pendingCount === 0) {
                        $('#approve-payment-btn, #reject-payment-btn').prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                    } else {
                        $('#approve-payment-btn, #reject-payment-btn').prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                    }
                })();

                // Also update the fallback payment history display elements
                if (paymentHistory && paymentHistory.length > 0) {
                    const latestPayment = paymentHistory[0];
                    $('#payment-history-amount').text(
                        `₹${parseFloat(latestPayment.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})}`
                    );
                    $('#payment-history-date').text(
                        `${latestPayment.payment_method ? latestPayment.payment_method.charAt(0).toUpperCase() + latestPayment.payment_method.slice(1) : 'N/A'} • ${latestPayment.paid_date ? new Date(latestPayment.paid_date).toLocaleDateString('en-GB') : 'N/A'}`);

                    const totalPaymentAmount = parseFloat(latestPayment.total_amount || 0);
                    const receivedPaymentAmount = parseFloat(latestPayment.amount || 0);
                    const paymentPercentage = totalPaymentAmount > 0 ? (receivedPaymentAmount / totalPaymentAmount *
                        100).toFixed(0) : 0;

                    let paymentType = '';
                    if (paymentPercentage >= 100) {
                        paymentType = 'Full payment - 100%';
                    } else if (paymentPercentage > 0) {
                        paymentType = `Partial payment - ${paymentPercentage}%`;
                    } else {
                        paymentType = 'No payment - 0%';
                    }
                    $('#payment-history-type').text(paymentType);

                    // Update status badge based on audit trail status first, then followup status
                    let statusText = '';
                    let statusClass = '';

                    // Check audit trail status first
                    const auditStatus = latestPayment.audit_trail?.payment_status;

                    if (auditStatus == 1) {
                        statusText = 'Payment Approved';
                        statusClass = 'bg-green-600 text-white';
                    } else if (auditStatus == 2) {
                        statusText = 'Payment Rejected';
                        statusClass = 'bg-red-600 text-white';
                    } else {
                        // Fallback to follow-up status
                        switch (parseInt(latestPayment.status)) {
                            case 2:
                                statusText = 'Payment Cancelled';
                                statusClass = 'bg-red-600 text-white';
                                break;
                            case 3:
                                statusText = 'Full Payment Received';
                                statusClass = 'bg-green-600 text-white';
                                break;
                            case 4:
                                statusText = 'Partial Payment Received';
                                statusClass = 'bg-yellow-600 text-white';
                                break;
                            case 8:
                                statusText = 'Payment Approved';
                                statusClass = 'bg-green-600 text-white';
                                break;
                            case 9:
                                statusText = 'Payment Rejected';
                                statusClass = 'bg-red-600 text-white';
                                break;
                            default:
                                statusText = 'Payment Pending';
                                statusClass = 'bg-blue-600 text-white';
                        }
                    }

                    $('#payment-history-status').removeClass().addClass(`badge !rounded-full ${statusClass}`).text(
                        statusText);

                    // Update view receipt button
                    const receiptBtn = $('#view-receipt-btn');
                    if (latestPayment.file) {
                        receiptBtn.prop('disabled', false).attr('data-file', latestPayment.file).attr(
                            'data-payment-id', latestPayment.id);
                    } else {
                        receiptBtn.prop('disabled', true).removeAttr('data-file').removeAttr('data-payment-id');
                    }

                    // Payment method and date are now displayed inline in the payment cards above
                    // No need to update separate badge elements as they're part of the main display
                }
            }

            // Handle receipt viewing
            $(document).on('click', '.view-receipt-btn, #view-receipt-btn', function() {
                const fileName = $(this).attr('data-file');
                const paymentId = $(this).attr('data-payment-id');

                console.log('Receipt file name:', fileName); // Debug log
                console.log('Payment ID:', paymentId); // Debug log

                if (!fileName) {
                    showErrorModal('Receipt Not Found', 'No receipt file found for this payment.');
                    return;
                }

                viewReceipt(fileName, paymentId);
            });

            const followupReceiptBaseUrl = "{{ route('admin.followups.file', ['filename' => '__file__'], false) }}".replace('/__file__', '');

            function followupReceiptUrl(fileName) {
                const normalizedFileName = String(fileName || '')
                    .replace(/\\/g, '/')
                    .split('/')
                    .pop();

                return normalizedFileName
                    ? `${followupReceiptBaseUrl}/${encodeURIComponent(normalizedFileName)}`
                    : '#';
            }

            // Function to view receipt
            function viewReceipt(fileName, paymentId) {
                const fileUrl = followupReceiptUrl(fileName);
                const fileExtension = fileName.split('.').pop().toLowerCase();

                let content = '';

                if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileExtension)) {
                    // Image file - only show the image without download functionality
                    content = `
                <div class="text-center">
                    <img src="${fileUrl}" alt="Payment Receipt" class="max-w-full h-auto rounded-lg shadow-lg" style="max-height: 70vh;" 
                         onerror="this.parentElement.innerHTML='<div class=\\'p-4 border rounded-lg\\'><i class=\\'ri-image-line text-6xl text-gray-400 mb-4\\'></i><p class=\\'text-gray-600\\'>Image not found or failed to load</p><p class=\\'text-sm text-gray-500\\'>File: ${fileName}</p><p class=\\'text-xs text-red-500\\'>URL: ${fileUrl}</p></div>';">
                </div>
            `;
                } else if (fileExtension === 'pdf') {
                    // PDF file - only show the PDF without download functionality
                    content = `
                <div class="text-center">
                    <iframe src="${fileUrl}" width="100%" height="500px" class="rounded-lg shadow-lg" 
                            onerror="this.parentElement.innerHTML='<div class=\\'p-4 border rounded-lg\\'><i class=\\'ri-file-pdf-line text-6xl text-gray-400 mb-4\\'></i><p class=\\'text-gray-600\\'>PDF not found or failed to load</p><p class=\\'text-sm text-gray-500\\'>File: ${fileName}</p><p class=\\'text-xs text-red-500\\'>URL: ${fileUrl}</p></div>';"></iframe>
                </div>
            `;
                } else {
                    // Other file types
                    content = `
                <div class="text-center">
                    <div class="p-4 border rounded-lg">
                        <i class="ri-file-line text-6xl text-gray-400 mb-4"></i>
                        <p class="text-gray-600">File type not supported for preview</p>
                        <p class="text-sm text-gray-500">File: ${fileName}</p>
                        <p class="text-xs text-red-500">URL: ${fileUrl}</p>
                    </div>
                </div>
            `;
                }

                $('#receipt-content').html(content);

                // Set the download button URL in the modal footer
                $('#download-receipt-modal-btn').attr('href', fileUrl);

                // Show modal
                window.HSOverlay.open(document.getElementById('receipt-viewer-modal'));

                // Ensure the parent payment-review modal is reopened when receipt modal is closed.
                // This avoids the parent modal staying closed after viewing/closing the receipt.
                (function() {
                    var receiptModalEl = document.getElementById('receipt-viewer-modal');
                    var parentModalEl = document.getElementById('view-payment-review');

                    // Record whether parent was visible before opening receipt
                    receiptModalEl._reopenParentIfClosed = !!(parentModalEl && !parentModalEl.classList.contains('hidden'));

                    // Remove previously attached listener if any
                    if (receiptModalEl._hsClosedListener) {
                        receiptModalEl.removeEventListener('hs.overlay.closed', receiptModalEl._hsClosedListener);
                    }

                    receiptModalEl._hsClosedListener = function() {
                        // Wait a tiny bit for overlay state to settle, then reopen parent only if it was open before
                        setTimeout(function() {
                            try {
                                if (receiptModalEl._reopenParentIfClosed && parentModalEl && parentModalEl.classList.contains('hidden')) {
                                    window.HSOverlay.open(parentModalEl);
                                }
                            } catch (e) {
                                console.error('Failed to reopen parent modal after receipt closed:', e);
                            }
                        }, 100);
                    };

                    receiptModalEl.addEventListener('hs.overlay.closed', receiptModalEl._hsClosedListener);

                    // Fallback: observe class attribute changes (hidden class added when closed)
                    if (receiptModalEl._observer) {
                        receiptModalEl._observer.disconnect();
                    }
                    receiptModalEl._observer = new MutationObserver(function(mutationsList) {
                        for (var mutation of mutationsList) {
                            if (mutation.attributeName === 'class') {
                                if (receiptModalEl.classList.contains('hidden')) {
                                    setTimeout(function() {
                                        if (receiptModalEl._reopenParentIfClosed && parentModalEl && parentModalEl.classList.contains('hidden')) {
                                            window.HSOverlay.open(parentModalEl);
                                        }
                                    }, 100);
                                }
                            }
                        }
                    });
                    receiptModalEl._observer.observe(receiptModalEl, { attributes: true });

                    // Also attach click handler to any close buttons inside receipt modal as a final fallback
                    // (buttons with data-hs-overlay="#receipt-viewer-modal")
                    var closeBtns = receiptModalEl.querySelectorAll('[data-hs-overlay="#receipt-viewer-modal"]');
                    if (receiptModalEl._closeBtnHandler) {
                        closeBtns.forEach(function(btn) {
                            btn.removeEventListener('click', receiptModalEl._closeBtnHandler);
                        });
                    }
                    receiptModalEl._closeBtnHandler = function() {
                        setTimeout(function() {
                            if (receiptModalEl._reopenParentIfClosed && parentModalEl && parentModalEl.classList.contains('hidden')) {
                                window.HSOverlay.open(parentModalEl);
                            }
                        }, 100);
                    };
                    closeBtns.forEach(function(btn) {
                        btn.addEventListener('click', receiptModalEl._closeBtnHandler);
                    });
                })();
            }
            // Payment details toggle (Payment Method / Date / Narration are collapsed by default).
            function setDecisionOpen(toggle, open) {
                const fields = document.getElementById(toggle.getAttribute('aria-controls'));
                if (!fields) return;
                fields.hidden = !open;
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            }
            $(document).on('click', '#view-payment-review .pr-decision-toggle', function() {
                setDecisionOpen(this, this.getAttribute('aria-expanded') !== 'true');
            });
            // Approve while the details are collapsed: open them first so the required fields are visible.
            // Nothing is sent here; once open, the existing approve flow below runs unchanged.
            document.addEventListener('click', function(event) {
                const approve = event.target.closest('#view-payment-review .approve-individual-payment-btn');
                if (!approve) return;
                const toggle = approve.closest('.pr-decide')?.querySelector('.pr-decision-toggle');
                if (!toggle || toggle.getAttribute('aria-expanded') === 'true') return;
                event.preventDefault();
                event.stopImmediatePropagation();
                setDecisionOpen(toggle, true);
                approve.closest('.pr-decide').querySelector('.payment-method-select')?.focus();
            }, true);

            // Handle individual payment approve
            $(document).on('click', '.approve-individual-payment-btn', function() {
                const paymentId = $(this).data('payment-id');
                const paymentMethod = $(`.payment-method-select[data-payment-id="${paymentId}"]`).val();
                const receivedDate = $(`.received-date-input[data-payment-id="${paymentId}"]`)
                    .val();
                const narration = $(`.narration-textarea[data-payment-id="${paymentId}"]`).val();

                // Validate required fields
                if (!paymentMethod || !receivedDate) {
                    showErrorModal('Validation Error',
                        'Please fill in Payment Method and Received Date fields.');
                    return;
                }

                // Validate date format (YYYY-MM-DD)
                const datePattern = /^\d{4}-\d{2}-\d{2}$/;
                if (!datePattern.test(receivedDate)) {
                    showErrorModal('Date Format Error',
                        'Please select a valid date.');
                    return;
                }

                // Show confirmation modal instead of confirm dialog
                showConfirmationModal(
                    'Confirm Payment Approval',
                    'Are you sure you want to approve this payment?',
                    function() {
                        // Execute the approval
                        $.ajax({
                            url: `/admin/account/payment-history/${paymentId}/approve`,
                            type: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                payment_method: paymentMethod,
                                received_date: receivedDate,
                                narration: narration
                            },
                            success: function(response) {
                                if (response.success) {
                                    showSuccessMessage('approve', response.message);
                                    location.reload();
                                } else {
                                    showErrorModal('Approval Error', response.message ||
                                        'Something went wrong');
                                }
                            },
                            error: function(xhr) {
                                console.error('Error approving payment:', xhr);
                                showErrorModal('Network Error',
                                    'Error approving payment. Please try again.');
                            }
                        });
                    } // Close the confirmation callback function
                ); // Close the showConfirmationModal call
            });

            // Handle individual payment reject
            $(document).on('click', '.reject-individual-payment-btn', function() {
                const paymentId = $(this).data('payment-id');
                const narration = $(`.narration-textarea[data-payment-id="${paymentId}"]`).val();

                showConfirmationModal(
                    'Confirm Payment Rejection',
                    'Are you sure you want to reject this payment?',
                    function() {
                        $.ajax({
                            url: `/admin/account/payment-history/${paymentId}/reject`,
                            type: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                            },
                            data: {
                                payment_method: $('select[name="payment_method"]').val(),
                                narration: narration || 'Payment rejected'
                            },
                            success: function(response) {
                                if (response.success) {
                                    showSuccessMessage('reject', response.message);
                                    location.reload();
                                } else {
                                    showErrorModal('Rejection Error', response.message ||
                                        'Something went wrong');
                                }
                            },
                            error: function(xhr) {
                                console.error('Error rejecting payment:', xhr);
                                showErrorModal('Network Error',
                                    'Error rejecting payment. Please try again.');
                            }
                        });
                    } // Close the confirmation callback function
                ); // Close the showConfirmationModal call
            });

            // Set current date as default when user clicks on date input
            // $(document).on('focus', '.received-date-input', function() {
            //     if (!this.value) {
            //         const today = new Date().toISOString().split('T')[0];
            //         this.value = today;
            //     }
            // });

            // Set current date as default and enforce min for non-Super Admin
$(document).on('focus', '.received-date-input', function() {
    if (!this.value) {
        this.value = today;
    }
    // Re-enforce min so past dates stay greyed out
    if (!isSuperAdmin) {
        $(this).attr('min', today);
    }
});

            $(document).on('click', '.reject-history-payment-btn', function() {
                const paymentId = $(this).data('payment-id');
                if (confirm('Are you sure you want to reject this payment?')) {
                    $.ajax({
                        url: `/admin/account/payment-history/${paymentId}/reject`,
                        type: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        data: {
                            payment_method: $('select[name="payment_method"]').val(),
                            narration: 'Payment rejected'
                        },
                        success: function(response) {
                            if (response.success) {
                                showSuccessMessage('reject', response.message);
                                location.reload();
                            } else {
                                showErrorModal('Rejection Error', response.message ||
                                    'Something went wrong');
                            }
                        },
                        error: function(xhr) {
                            console.error('Error rejecting payment:', xhr);
                            console.error(xhr.responseText);
                            showErrorModal('Rejection Error',
                                'Error rejecting payment. Please try again.');
                        }
                    });
                }
            });
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.account.payment-review') }}";
        }

        // Ensure date inputs are properly initialized
        $(document).ready(function() {
            console.log('Initializing date inputs...');
            
            // Check if date inputs are found
            const dateInputs = $('input[type="date"]');
            console.log('Found ' + dateInputs.length + ' date inputs');
            
            // Set default values for date inputs if empty
            $('input[type="date"]').on('focus', function() {
                console.log('Date input focused:', this.id);
                if (!this.value) {
                    const today = new Date().toISOString().split('T')[0];
                    this.value = today;
                    console.log('Set default date:', today);
                }
            });

            // Test if date picker is working
            $('input[type="date"]').on('change', function() {
                console.log('Date changed:', this.id, this.value);
            });
        });
</script>
@endpush
