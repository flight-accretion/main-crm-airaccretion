@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">

</div>
<!-- Page Header Close -->

<div class="grid grid-cols-12">
    <div class="xl:col-span-12  col-span-12">
        <div class="box">
            <div class="hs-accordion-group">
                <div class="hs-accordion" id="payment-review-accordion">
                    <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" fill="white"
                                        viewBox="0 0 24 24">
                                        <path
                                            d="M2 6c0-1.1.9-2 2-2h16c1.1 0 2 .9 2 2v3H2V6zm0 5h20v7c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2v-7zm5 3c-.83 0-1.5.67-1.5 1.5S6.17 17 7 17s1.5-.67 1.5-1.5S7.83 14 7 14z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="md:flex block items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Payment Review</h5>
                                    <div class="hs-dropdown ti-dropdown mt-3 md:mt-0">
                                        <a href="{{ route('admin.account.payment-review.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                                            class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2"
                                            aria-expanded="false">
                                            <i class="ri-share-box-fill"></i>
                                            Export
                                            <i class="ri-arrow-down-s-line align-middle ms-1 inline-block"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Search Filters -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header">
                <div class="box-title">
                    Search Filters
                </div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>
            <div class="box-body" id="filter-section">
                <form method="GET" action="{{ route('admin.account.payment-review') }}" id="filter-form">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">From Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm rounded-sm" name="from_date"
                                    value="{{ request('from_date') }}">
                            </div>
                        </div>

                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">To Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm rounded-sm" name="to_date"
                                    value="{{ request('to_date') }}">
                            </div>
                        </div>
                        <!-- Service Date Filter -->
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">Service Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm rounded-sm" name="service_date"
                                    value="{{ request('service_date') }}">
                            </div>
                        </div>

                        <!-- Service Name Filter -->
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">Service</label>
                            <select class="js-example-basic-single w-full form-control-sm" name="service_name">
                                <option value="">All Services</option>
                                @foreach ($services as $service)
                                <option value="{{ $service->id }}" {{ request('service_name')==$service->id ? 'selected'
                                    : '' }}>
                                    {{ $service->service }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter (Followup Status) -->
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">Status</label>
                            <select class="js-example-basic-single w-full form-control-sm" name="status">
                                <option value="">All Status</option>
                                <option value="3" {{ request('status')=='3' ? 'selected' : '' }}>Full Paid
                                </option>
                                <option value="4" {{ request('status')=='4' ? 'selected' : '' }}>Partial Paid
                                </option>
                            </select>
                        </div>

                        <!-- Payment Status Filter (Approval Status) -->
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">Payment Status</label>
                            <select class="js-example-basic-single w-full form-control-sm" name="payment_status">
                                <option value="">All Payment Status</option>
                                <option value="pending" {{ request('payment_status')=='pending' ? 'selected' : '' }}>
                                    Pending
                                </option>
                                <option value="approved" {{ request('payment_status')=='approved' ? 'selected' : '' }}>
                                    Approved
                                </option>
                                <option value="rejected" {{ request('payment_status')=='rejected' ? 'selected' : '' }}>
                                    Rejected
                                </option>
                            </select>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label">&nbsp;</label>
                            <div class="flex gap-2">
                                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                    Apply Filters
                                </button>
                                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                    title="Clear Filters" onclick="clearFilters()">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header justify-between flex">
                <div class="box-title">
                    Payment Transactions
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable server-paginated" width="100%"
                        data-empty-msg="No payment records found">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">

                                <th data-priority="1">S.No</th>
                                <th data-priority="2">Name</th>
                                <th data-priority="3">Phone</th>
                                <th data-priority="4">Service Date</th>
                                <th data-priority="5">Service</th>
                                <th data-priority="6">Received/Total</th>
                                <th data-priority="7">Status</th>
                                <th data-priority="8">Payment Status</th>
                                <th data-priority="1">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $index => $payment)
                            <tr>

                                <td class="text-center">{{ (isset($paymentsPaginator) && $paymentsPaginator->firstItem() ? $paymentsPaginator->firstItem() : 1) + $index }}</td>
                                <td>{{ $payment->first_name }} </td>
                                <td class="text-center">{{ $payment->phone_number }}</td>
                                <td class="text-center">
                                    {{ $payment->from_date ? \Carbon\Carbon::parse($payment->from_date)->format('d-m-Y')
                                    : 'N/A' }}
                                </td>
                                <td>
                                    @php

                                    $serviceIds = is_string($payment->service_ids)
                                    ? json_decode($payment->service_ids, true)
                                    : $payment->service_ids;

                                    if ($serviceIds) {
                                    $services = \App\Models\Service::whereIn('id', $serviceIds)
                                    ->pluck('service')
                                    ->toArray();

                                    echo Str::limit(implode(', ', $services), 50); // yaha limit laga diya
                                    } else {
                                    echo 'N/A';
                                    }
                                    @endphp
                                </td>

                                <td class="text-center">
                                    ₹{{ number_format($payment->received_amount, 2) }}/₹{{
                                    number_format($payment->total_amount, 2) }}
                                </td>
                                <td>
                                    @php
                                    $followupStatusLabels = [
                                    2 => 'Cancelled',
                                    3 => 'Full Paid',
                                    4 => 'Partial Paid',
                                    5 => 'Confirmed/Complete',
                                    6 => 'Pending',
                                    7 => 'Rescheduled',
                                    8 => 'Approved',
                                    9 => 'Rejected',
                                    ];
                                    $statusLabel = $followupStatusLabels[$payment->status] ?? 'Unknown';
                                    $statusClass = '';
                                    if ($payment->status == 3) {
                                    $statusClass = 'badge !rounded-full bg-success/10 text-success';
                                    } elseif ($payment->status == 4) {
                                    $statusClass = 'badge !rounded-full bg-warning/10 text-warning';
                                    } elseif ($payment->status == 2) {
                                    $statusClass = 'badge !rounded-full bg-danger/10 text-danger';
                                    } elseif ($payment->status == 8) {
                                    $statusClass = 'badge !rounded-full bg-info/10 text-info';
                                    } elseif ($payment->status == 9) {
                                    $statusClass = 'badge !rounded-full bg-secondary/10 text-secondary';
                                    }
                                    @endphp
                                    <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="text-center">
                                    @php
                                    // Numeric comparisons
                                    $received = (float) ($payment->received_amount ?? 0);
                                    $receivedApproved = (float) ($payment->received_amount_approved ?? 0);
                                    $total = (float) ($payment->total_amount ?? 0);
                                    @endphp
                                    @if (isset($payment->audit_status))
                                    @if ($payment->audit_status == 1 && $receivedApproved >= $total && $total > 0)
                                    <span class="badge !rounded-full bg-success/10 text-success">Approved</span>
                                    @elseif($payment->audit_status == 2)
                                    <span class="badge !rounded-full bg-danger/10 text-danger">Rejected</span>
                                    @else
                                    <span class="badge !rounded-full bg-warning/10 text-warning">Pending</span>
                                    @endif
                                    @else
                                    <span class="badge !rounded-full bg-warning/10 text-warning">Pending</span>
                                    @endif
                                </td>
                                <td>
                                    <a aria-label="anchor"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-payment-btn"
                                        data-id="{{ $payment->followup_id }}" data-hs-overlay="#view-payment-review">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                </td>
                            </tr>
                            @empty

                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(isset($paymentsPaginator) && $paymentsPaginator->hasPages())
                <div class="mt-4">
                    {{ $paymentsPaginator->appends(request()->except('page'))->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
<div id="view-payment-review" class="view-payment-review hs-overlay hidden ti-offcanvas ti-offcanvas-right"
    tabindex="-1">
    <div class="ti-offcanvas-header">
        <div class="flex items-center">
            <div class="me-4 gap-0">
                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" fill="white" viewBox="0 0 24 24">
                            <path
                                d="M2 6c0-1.1.9-2 2-2h16c1.1 0 2 .9 2 2v3H2V6zm0 5h20v7c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2v-7zm5 3c-.83 0-1.5.67-1.5 1.5S6.17 17 7 17s1.5-.67 1.5-1.5S7.83 14 7 14z" />
                        </svg>
                    </span>
                </span>
            </div>
            <div class="flex-grow">
                <div class="flex items-center justify-between">
                    <h5 class="font-semibold mb-0 leading-none text-[1rem]">Review Payment – <span
                            id="client-name-header">Loading...</span>
                    </h5>
                    <div class="text-danger font-semibold">
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#view-payment-review">
                            <span class="sr-only">Close modal</span>
                            <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <path
                                    d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                    fill="currentColor"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="ti-offcanvas-body view-payment-review-body">
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12">
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Client Information</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                                <p class="text-gray-800 dark:text-white" id="client-name">-</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                <p class="text-gray-800 dark:text-white" id="client-email">-</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                <p class="text-gray-800 dark:text-white" id="client-phone">-</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
                                <p class="text-gray-800 dark:text-white" id="client-whatsapp">-</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                <p class="text-gray-800 dark:text-white" id="client-country">-</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                <p class="text-gray-800 dark:text-white" id="client-city">-</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                <p class="text-gray-800 dark:text-white" id="client-address">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Travel Information</h5>
                    </div>
                    <div class="box-body bg-gray-50" id="travel-information-container">
                        <!-- Travel information will be populated dynamically -->
                        <div class="text-center py-4">
                            <p class="text-gray-500">Loading travel information...</p>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Service Information</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                                <p class="text-gray-800 dark:text-white" id="service-name">-</p>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
                                <p class="text-gray-800 dark:text-white" id="extra-services">-</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Payment Information</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Amount</label>
                                <p class="text-gray-800 dark:text-white" id="total-amount">₹0</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Received Amount</label>
                                <p class="text-gray-800 dark:text-white text-success" id="paid-amount">₹0</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Balance</label>
                                <p class="text-danger-800 dark:text-white text-danger" id="balance-amount">₹0</p>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="box-footer">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label for="payment-method" class="ti-form-label mb-0">Payment Method</label>
                                    <select class="ti-form-select rounded-sm form-control-sm w-full" name="payment_method"
                                        required>
                                        <option value="" disabled {{ old('payment_method') ? '' : 'selected' }}>
                                            Select payment method</option>
                                        <option value="Cash" {{ old('payment_method') == 'Cash' ? 'selected' : '' }}>
                                            Cash</option>
                                        <option value="UPI Payment"
                                            {{ old('payment_method') == 'UPI Payment' ? 'selected' : '' }}>UPI Payment
                                        </option>
                                        <option value="Bank Transfer"
                                            {{ old('payment_method') == 'Bank Transfer' ? 'selected' : '' }}>Bank Transfer
                                        </option>
                                        <option value="Payment Gateway"
                                            {{ old('payment_method') == 'Payment Gateway' ? 'selected' : '' }}>Payment
                                            Gateway</option>
                                        <option value="Cheque" {{ old('payment_method') == 'Cheque' ? 'selected' : '' }}>
                                            Cheque</option>
                                        <option value="Cash" {{ old('payment_method') == 'Cash' ? 'selected' : '' }}>
                                            Cash</option>
                                        <option value="Credit Card"
                                            {{ old('payment_method') == 'Credit Card' ? 'selected' : '' }}>Credit Card
                                        </option>
                                        <option value="Debit Card"
                                            {{ old('payment_method') == 'Debit Card' ? 'selected' : '' }}>Debit Card
                                        </option>
                                        <option value="Net Banking"
                                            {{ old('payment_method') == 'Net Banking' ? 'selected' : '' }}>Net Banking
                                        </option>
                                        <option value="Website"
                                            {{ old('payment_method') == 'Website' ? 'selected' : '' }}>Website</option>
                                        <option value="Other" {{ old('payment_method') == 'Other' ? 'selected' : '' }}>
                                            Other</option>
                                    </select>
                                    <span id="payment_method_error" class="text-danger text-xs d-block mb-2"
                                        style="display: none;"></span>
                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label for="datetime" class="ti-form-label mb-0">Received Date</label>
                                    <div class="form-group">
                                        <input type="date" class="form-control form-control-sm datetime"
                                            
                                            value="{{ old('received_date') }}"
                                            name="received_date" 
                                            max="{{ date('Y-m-d') }}"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div> -->
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Payment History</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div id="payment-history-container">

                            <!-- Payment history will be populated dynamically -->
                        </div>
                        <!-- <div class="text-center py-4">
                                <p class="text-gray-500">Loading payment history...</p>
                            </div> -->
                    </div>
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
            </div>
        </div>
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
                    },
                    success: function(response) {
                        const {
                            followup,
                            client,
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

                    let statusIcon = '';
                    if (isApproved) {
                        statusIcon = `
                            <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2"
                                        fill="#2B53A9" />
                                    <path d="M8.5 12.5L11 15L16 9.5" stroke="#fff" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>`;
                    } else if (isRejected) {
                        statusIcon = `
                            <span class="avatar avatar-md p-2 !rounded-full bg-red-600 m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2"
                                        fill="#dc2626" />
                                    <path d="M8 8L16 16M16 8L8 16" stroke="#fff" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>`;
                    } else {
                        statusIcon = `
                            <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                    fill="none" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2"
                                        fill="#2B53A9" />
                                    <path d="M8.5 12.5L11 15L16 9.5" stroke="#fff" stroke-width="2"
                                        stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>`;
                    }

                    historyHtml += `
                        <div class="box payment-card ${index > 0 ? 'mt-4' : ''} border border-gray-200 rounded-lg">
                            <div class="box-header" style="display: block;">
                                <div class="flex items-center">
                                    <div class="me-4 gap-0">
                                        ${statusIcon}
                                    </div>
                                    <div class="flex-grow">
                                        <div class="payment-header flex items-center justify-between">
                                            <div class="flex-grow mr-4">
                                                <h5 class="font-semibold mb-1 leading-none text-[1.25rem]">
                                                    ₹${amount.toLocaleString('en-IN', { minimumFractionDigits: 2 })}
                                                </h5>
                                                <p class="text-sm text-gray-600 mb-1">
                                                    ${payment.payment_method ? `<span class="badge bg-info/10 text-info">${payment.payment_method.charAt(0).toUpperCase() + payment.payment_method.slice(1)}</span>` : ''}
                                                    ${payment.paid_date ? `<span class="ms-2 text-muted">${new Date(payment.paid_date).toLocaleDateString('en-GB')}</span>` : ''}
                                                </p>
                                                ${payment.followup_note ? `<p class="text-sm text-gray-600 mt-1"><i class="ri-sticky-note-line me-1"></i>Note: ${payment.followup_note}</p>` : ''}
                                            </div>
                                            <div class="payment-status text-right min-w-[200px]">
                                                <div class="mb-2">
                                                    <span class="badge !rounded-full ${statusClass}">${statusText}</span>
                                                </div>
                                                ${createdByInfo ? `<p class="text-xs text-gray-500 mb-2">${createdByInfo}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    `;

                    // Add footer section for each payment
                    if (!isApproved && !isRejected) {
                        // Editable state - show form for pending payments
                        historyHtml += `                            
                            <div class="box-body">
                                <div class="grid grid-cols-12 gap-2">
                                    <!-- Payment Method -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label mb-0">Payment Method *</label>
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
                                    </div>
                                    <!-- Received Date -->
                                    <!-- <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label mb-0">Date *</label>
                                        <div class="form-group">
                                            <input type="date" class="form-control form-control-sm received-date-input" 
                                                data-payment-id="${payment.id}" 
                                                required>
                                        </div>
                                    </div> -->

                                    <!-- Received Date -->
<div class="xl:col-span-6 col-span-12">
    <label class="ti-form-label mb-0">Date *</label>
    <div class="form-group">
        <input type="date" class="form-control form-control-sm received-date-input" 
            data-payment-id="${payment.id}"
            min="${isSuperAdmin ? '' : today}"
            value="${today}"
            required>
    </div>
</div>
                                    <!-- Narration -->
                                    <div class="xl:col-span-12 col-span-12">
                                        <label class="ti-form-label mb-0">Narration (Optional)</label>
                                        <textarea class="form-control form-control-sm narration-textarea" 
                                                data-payment-id="${payment.id}" 
                                                rows="2"
                                                placeholder="Add notes about this payment..."></textarea>
                                    </div>
                                    
                                </div>
                            </div>
                            <div class="box-footer">
                                <div class="grid grid-cols-12 gap-2">
                                    <!-- View Receipt Button -->
                                    <div class="xl:col-span-4 col-span-12">
                                        <button type="button" class="ti-btn ti-btn-outline-primary view-receipt-btn w-full" 
                                                data-payment-id="${payment.id}" 
                                                data-file="${payment.file || ''}"
                                                ${!payment.file ? 'disabled' : ''}>
                                            <i class="ri-eye-line me-1"></i>${payment.file ? 'View Receipt' : 'No Receipt'}
                                        </button>
                                    </div>
                                    <!-- Approve Button -->
                                    <div class="xl:col-span-4 col-span-12">
                                        <button type="button" class="ti-btn ti-btn-outline-success approve-individual-payment-btn w-full" data-payment-id="${payment.id}">
                                            <i class="ri-check-line me-1"></i>Approve
                                        </button>
                                    </div>
                                    <!-- Reject Button -->
                                    <div class="xl:col-span-4 col-span-12">
                                        <button type="button" class="ti-btn ti-btn-outline-danger reject-individual-payment-btn w-full" data-payment-id="${payment.id}">
                                            <i class="ri-close-line me-1"></i>Reject
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Approved/Rejected state - show read-only information
                        historyHtml += `
                            <!-- Box Footer -->
                            <div class="box-body">
                                <div class="grid grid-cols-12 gap-3">
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Payment Method</label>
                                        <p class="text-gray-800 dark:text-white">${auditEntry?.payment_method || 'Not specified'}</p>
                                    </div>
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date</label>
                                        <p class="text-gray-800 dark:text-white">${auditEntry?.paid_date ? new Date(auditEntry.paid_date).toLocaleDateString('en-GB') : 'Not specified'}</p>
                                    </div>
                                    ${auditEntry?.narration ? `
                                                                                <div class="xl:col-span-6 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                                                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Narration</label>
                                                                                    <p class="text-gray-800 dark:text-white">${auditEntry.narration}</p>
                                                                                </div>` : ''}
                                    <div class="col-span-12">
                                        <div class="flex gap-2">
                                            <button type="button" class="ti-btn ti-btn-outline-primary view-receipt-btn" 
                                                    data-payment-id="${payment.id}" 
                                                    data-file="${payment.file || ''}"
                                                    ${!payment.file ? 'disabled' : ''}>
                                                <i class="ri-eye-line me-1"></i>View Receipt
                                            </button>
                                            <a href="${payment.file ? (payment.file.startsWith('followups/') ? '/storage/' + payment.file : '/storage/followups/' + payment.file) : '#'}" 
                                               class="ti-btn ti-btn-outline-info download-receipt-btn ${!payment.file ? 'disabled pointer-events-none opacity-50' : ''}" 
                                               download
                                               ${!payment.file ? 'disabled' : ''}>
                                                <i class="ri-download-line"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
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

            // Function to view receipt
            function viewReceipt(fileName, paymentId) {
                // Handle file names that already include the followups/ path
                let fileUrl;
                if (fileName.startsWith('followups/')) {
                    // File name already includes the folder path
                    fileUrl = `/storage/${fileName}`;
                } else {
                    // File name is just the filename
                    fileUrl = `/storage/followups/${fileName}`;
                }
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
