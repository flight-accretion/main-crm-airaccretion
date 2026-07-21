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
                <div class="hs-accordion" id="ride-status-accordion">
                    <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                    <i class="las la-plane"></i>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Ride Status</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="grid grid-cols-12 gap-6">
    <div class="col-span-12">
        <div class="box">
            <div class="box-header">
                <div class="box-title">
                    Search Filters
                </div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>
            <div class="box-body" id="filter-section">
                <form class="ti-custom-validation view-client-filters" method="GET"
                    action="{{ route('admin.rides.ride-status') }}" id="filter-form" novalidate>
                    <div class="grid grid-cols-12 gap-6 items-end">
                        {{-- Row 1: existing date + status + apply --}}
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">From Service Date</label>
                            <input type="date" name="from_date" class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $currentFilters['from_date'] ?? '' }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">To Service Date</label>
                            <input type="date" name="to_date" class="ti-form-input rounded-sm form-control-sm"
                                value="{{ $currentFilters['to_date'] ?? '' }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Status</label>
                            <select name="status" class="js-example-basic-single w-full form-control-sm">
                                <option value="">Select Status</option>
                                @foreach ($statusOptions ?? [] as $val => $label)
                                <option value="{{ $val }}" {{ (string)($currentFilters['status'] ?? '' )===(string)$val
                                    ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        {{-- <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Client Name</label>
                            <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by name..." value="{{ $currentFilters['name'] ?? '' }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Phone Number</label>
                            <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by phone..." value="{{ $currentFilters['phone'] ?? '' }}">
                        </div>
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Product</label>
                            <select name="product_id" class="js-example-basic-single w-full form-control-sm">
                                <option value="">All Products</option>
                                @foreach ($products ?? [] as $product)
                                <option value="{{ $product->id }}" {{ ($currentFilters['product_id'] ?? '' )==$product->
                                    id ? 'selected' : '' }}>
                                    {{ $product->product }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Service</label>
                            <select name="service_id" class="js-example-basic-single w-full form-control-sm">
                                <option value="">All Services</option>
                                @foreach ($services ?? [] as $service)
                                <option value="{{ $service->id }}" {{ ($currentFilters['service_id'] ?? '' )==$service->
                                    id ? 'selected' : '' }}>
                                    {{ $service->service }}
                                </option>
                                @endforeach
                            </select>
                        </div> --}}
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <div class="flex gap-2">
                                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                    Apply Filters
                                </button>
                                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                    onclick="clearRideFilters()" title="Clear all filters">
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
                    All Rides
                </div>
                {{-- <div class="export-buttons flex gap-2 mb-3">
                    <button type="button" class="ti-btn ti-btn-success-full ti-btn-sm export-excel-btn"
                        title="Export to Excel">
                        <i class="ri-file-excel-line"></i>
                    </button>
                    <button type="button" class="ti-btn ti-btn-info-full ti-btn-sm export-csv-btn"
                        title="Export to CSV">
                        <i class="ri-file-text-line"></i>
                    </button>
                </div> --}}
            </div>
            <div class="box-body">

                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable server-paginated" width="100%"
                        data-empty-msg="No rides found with the specified status criteria.">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">

                                <th data-priority="1">S.No</th>

                                <th data-priority="6">Name</th>
                                <th data-priority="7">Phone</th>
                                <th data-priority="8">Service Date</th>
                                <th data-priority="9">Service</th>
                                {{-- <th data-priority="2">Invoice ID</th> --}}
                                {{-- <th data-priority="3">Vendor Name</th> --}}
                                {{-- <th data-priority="4">Assigned Rep</th> --}}
                                {{-- <th data-priority="5">Created Date</th> --}}
                                <th data-priority="10">Paid/Total</th>
                                <th data-priority="11">Status</th>
                                <th data-priority="12">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($ridesData as $index => $ride)
                            <tr>

                                <td class="text-center">{{ (isset($rideStatusPaginator) && $rideStatusPaginator->firstItem() ? $rideStatusPaginator->firstItem() : 1) + $index }}</td>

                                <td>{{ $ride['client_name'] }}</td>
                                <td class="text-center">{{ $ride['contact_number'] }}</td>
                                <td class="text-center"
                                    data-order="{{ isset($ride['service_date_sortable']) ? $ride['service_date_sortable'] : '0000-00-00' }}">
                                    {{ $ride['service_date'] }}</td>
                                <td>
                                    {{ $ride['service_names'] ?: 'N/A' }}
                                </td>
                                {{-- <td>{{ $ride['invoice_id'] }}</td> --}}
                                {{-- <td>{{ $ride['vendor_name'] }}</td> --}}
                                {{-- <td>{{ $ride['assigned_rep'] }}</td> --}}
                                {{-- <td class="text-center"
                                    data-order="{{ isset($ride['created_date_sortable']) ? $ride['created_date_sortable'] : '0000-00-00' }}">
                                    {{ $ride['created_date'] }}</td> --}}
                                <td class="text-center">
                                    ₹{{ number_format($ride['received_amount'], 2) }}/₹{{
                                    number_format($ride['total_amount'], 2) }}
                                </td>
                                <td class="text-center">
                                    @php
                                    $status = $ride['status'];
                                    $statusLabels = [
                                    1 => ['text' => 'Active', 'class' => 'bg-success/10 text-success'],
                                    2 => ['text' => 'Cancelled', 'class' => 'bg-danger/10 text-danger'],
                                    3 => [
                                    'text' => 'Full Payment Received',
                                    'class' => 'bg-primary/10 text-primary',
                                    ],
                                    4 => [
                                    'text' => 'Partial Payment Received',
                                    'class' => 'bg-warning/10 text-warning',
                                    ],
                                    5 => [
                                    'text' => 'Completed',
                                    'class' => 'bg-success/10 text-success',
                                    ],
                                    7 => [
                                    'text' => 'Reschedule',
                                    'class' => 'bg-purple/10 text-purple',
                                    ],
                                    8 => [
                                    'text' => 'Approved',
                                    'class' => 'bg-info/10 text-info',
                                    ],
                                    9 => [
                                    'text' => 'Rejected',
                                    'class' => 'bg-yellow/10 text-yellow',
                                    ],
                                    ];
                                    $statusInfo = $statusLabels[$status] ?? [
                                    'text' => 'Pending',
                                    'class' => 'bg-gray/10 text-gray',
                                    ];
                                    @endphp
                                    <span class="badge !rounded-full {{ $statusInfo['class'] }}">{{ $statusInfo['text']
                                        }}</span>
                                </td>
                                <td>
                                    <button type="button"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-ride-btn"
                                        data-ride-id="{{ $ride['id'] }}" data-hs-overlay="#ride-details-modal"
                                        onclick="resetRefundSection()">
                                        <i class="ri-eye-line"></i>
                                    </button>
                                </td>
                            </tr>
                            @empty

                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if(isset($rideStatusPaginator) && $rideStatusPaginator->hasPages())
                <div class="mt-4">
                    {{ $rideStatusPaginator->appends(request()->except('page'))->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Ride Details Modal -->
<div id="ride-details-modal" data-refunds-url="{{ route('admin.refunds.index') }}"
    class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1" data-hs-overlay-options='{
        "onClose": function () {
            document.getElementById("refund-information-section").style.display = "none";
        }
     }'>
    <div class="ti-offcanvas-header">
        <div class="flex items-center">
            <div class="me-4 gap-0">
                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                    <i class="las la-plane"></i>
                </span>
            </div>
            <div class="flex-grow">
                <div class="flex items-center justify-between">
                    <h5 class="font-semibold mb-0 leading-none text-[1rem]">Ride Details – <span class="text-primary"
                            id="modal-client-name">Loading...</span>
                    </h5>
                    <div class="text-danger font-semibold">
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#ride-details-modal">
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
    <div class="ti-offcanvas-body view-ride-status-body">
        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12">
                <!-- Current Status & Payment Progress -->
                <div class="box">
                    <div class="box-body bg-gray-50">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-2">Current Status</label>
                                <div id="current-status-badge">
                                    <span class="badge !rounded-full bg-secondary/10 text-secondary">Loading...</span>
                                </div>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-2">Ride Status</label>
                                <select class="ti-form-select form-control-sm rounded-sm" id="ride-status-dropdown">
                                    <option value="">Select Status</option>
                                    <option value="2">Ride Cancelled</option>
                                    <option value="5">Ride Completed</option>
                                    <option value="7">Ride Reschedule</option>
                                </select>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-2">Payment Progress</label>
                                <div class="flex items-center gap-x-3 whitespace-nowrap w-full mb-4">
                                    <div class="ti-main-progress w-full progress bg-gray-200 dark:bg-bodybg">
                                        <div class="ti-main-progress-bar bg-primary text-xs text-white text-center"
                                            id="payment-progress-bar" style="width: 0%" role="progressbar"
                                            aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="text-end">
                                        <span class="text-sm text-gray-800 dark:text-white"
                                            id="payment-progress-text">0%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-5" id="action-buttons" style="display: none;">
                    <button type="button" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn me-2"
                        id="generate-invoice-btn" style="display: none;">Generate Invoice</button>
                    <button type="button" class="ti-btn ti-btn-danger-full ti-custom-validate-btn" id="refund-note-btn"
                        style="display: none;">Refund Note</button>
                </div>

                <!-- Refund Information Section (Hidden by default, shown when refund note button is clicked) -->
                <div class="box" id="refund-information-section" style="display: none;">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Refund Information</h5>
                        <button type="button" class="ti-btn ti-btn-outline-secondary" id="cancel-refund-btn">
                            Cancel
                        </button>
                    </div>
                    <div class="box-body bg-gray-50">
                        <form id="ride-refund-form" enctype="multipart/form-data">
                            <input type="hidden" id="ride-followup-id" name="followup_id">
                            @php
                            $currentRole = optional(auth()->user()->userType)->user_type;
                            $isAdmin = in_array($currentRole, \App\Models\UserType::ADMIN_ROLES ?? []);
                            $isAccounts = in_array($currentRole, \App\Models\UserType::ACCOUNTS_ROLES ?? []);
                            $isOperations = in_array($currentRole, \App\Models\UserType::OPERATIONS_ROLES ?? []);
                            @endphp
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Original Amount<span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number"
                                            class="form-control {{ $isAccounts ? 'bg-gray-100 text-gray-600' : '' }}"
                                            id="ride-original-amount" name="original_amount" step="0.01" {{ $isAccounts
                                            ? 'readonly' : '' }}>
                                    </div>
                                    <small class="text-muted">Auto-calculated from payments</small>
                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Amount<span
                                            class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" id="ride-refund-amount"
                                            name="refund_amount" step="0.01">
                                    </div>
                                    <small class="text-muted">Cannot be greater than original amount</small>
                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Type<span
                                            class="text-danger">*</span></label>
                                    <select class="form-control" id="ride-refund-type" name="refund_type" {{
                                        ($isAccounts || $isAdmin) ? '' : 'disabled' }}>
                                        <option value="">Select refund type...</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="UPI Payment">UPI Payment</option>
                                        <option value="Cash">Cash</option>
                                        <option value="Cheque">Cheque</option>
                                        <option value="Debit Card">Debit Card</option>
                                        <option value="Credit Card">Credit Card</option>
                                        <option value="Net Banking">Net Banking</option>
                                        <option value="Wallet">Wallet</option>
                                        <option value="Payment Gateway">Payment Gateway</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    @if(!$isAccounts && !$isAdmin)
                                    <small class="text-muted">Only Accounts can select refund type.</small>
                                    @endif
                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Date<span
                                            class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="ride-refund-date" name="refund_date" {{
                                        ($isAccounts || $isAdmin) ? '' : 'readonly' }}>
                                    @if(!$isAccounts && !$isAdmin)
                                    <small class="text-muted">Only Accounts can enter refund date.</small>
                                    @endif
                                </div>
                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Upload Refund
                                        Proof<span class="text-danger">*</span></label>
                                    <div class="flex items-center gap-3">
                                        <input type="file" class="form-control" id="ride-refund-proof"
                                            name="refund_proof" accept=".pdf,.jpg,.jpeg,.png" {{ ($isAccounts ||
                                            $isAdmin) ? '' : 'disabled' }}>
                                        <button type="button" id="ride-preview-proof-btn"
                                            class="ti-btn ti-btn-outline-secondary" style="width: 20%;">
                                            Preview
                                        </button>
                                    </div>
                                    <small class="text-muted">Upload PDF or image (JPG, JPEG, PNG). Max 2MB.</small>
                                    <div class="text-sm text-gray-500 mt-1 flex items-center gap-3">
                                        <div id="ride-proof-filename">No file selected</div>
                                        <small id="ride-proof-hint" class="text-muted"></small>
                                    </div>
                                    @if(!$isAccounts && !$isAdmin)
                                    <small class="text-muted d-block">Only Accounts can upload refund proof.</small>
                                    @endif
                                </div>
                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund
                                        Reason</label>
                                    <textarea class="form-control" id="ride-refund-reason" name="refund_reason" rows="3"
                                        placeholder="Enter reason for refund..."></textarea>
                                </div>
                                <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                    <div class="flex gap-3">
                                        <button type="submit" class="ti-btn bg-theme ti-btn-primary-full">
                                            Save Changes
                                        </button>
                                        <button type="button" id="ride-redirect-to-refunds-btn"
                                            class="ti-btn ti-btn-outline-secondary">
                                            Ride Refund
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Client Information -->
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
                            <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                <p class="text-gray-800 dark:text-white" id="client-address">-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Travel Information -->
                <div class="box">
                    <div class="box-header justify-between">
                        <h5 class="box-title">Travel Information</h5>
                        <div id="edit-dates-container" style="display: none;">
                            <button type="button" class="ti-btn ti-btn-outline-primary ti-btn-wave !py-1 !px-2"
                                id="edit-dates-btn">Edit Dates</button>
                        </div>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div id="travel-info-display">
                            <div id="travel-information-container">
                                <!-- Travel information will be populated dynamically -->
                                <div class="text-center py-4">
                                    <p class="text-gray-500">Loading travel information...</p>
                                </div>
                            </div>
                        </div>

                        <!-- Edit Travel Form (Hidden by default) -->
                        <div id="travel-edit-form" style="display: none;">
                            <div id="travel-edit-content">
                                <!-- Content will be populated dynamically based on single or multiple trips -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Information -->
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

                <!-- Payment Information -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Payment Information</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="grid grid-cols-12 gap-6 items-center">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Amount</label>
                                <p class="text-gray-800 dark:text-white" id="total-amount">₹0</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Paid Amount</label>
                                <p class="text-gray-800 dark:text-white text-success" id="paid-amount">₹0</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Balance</label>
                                <p class="text-danger-800 dark:text-white text-danger" id="balance-amount">₹0</p>
                            </div>
                            <!-- <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <button type="button" id="view-receipt-btn"
                                        class="ti-btn ti-btn-outline-primary ti-btn-wave" disabled>
                                        <i class="ri-eye-line me-2 inline-block align-middle"></i> View Receipt
                                    </button>
                                </div>
                                 <div class="xl:col-span-8 lg:col-span-8 md:col-span-12 sm:col-span-12 col-span-12">
                                    <div id="payment-receipt-info">
                                        <span class="badge !rounded-full bg-primary/10 text-primary"
                                            id="payment-method-badge">UPI Payment</span>
                                        <span id="payment-received-info">-</span>
                                    </div>
                                 </div> -->
                        </div>

                    </div>
                </div>

                <!-- Payment History -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Payment History</h5>
                    </div>
                    <div class="box-body bg-gray-50" id="payment-history-container">
                        <div class="text-center py-4">
                            <p class="text-gray-500">No payment history found</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Status Change Confirmation Modal -->
<div id="status-change-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out">
        <div class="ti-modal-content">
            <div class="ti-modal-header">
                <h6 class="modal-title text-[1rem] font-semibold">Confirm Status Change</h6>
                <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                    data-hs-overlay="#status-change-modal">
                    <span class="sr-only">Close</span>
                    <i class="ri-close-line"></i>
                </button>
            </div>
            <div class="ti-modal-body px-4 py-4">
                <div class="text-center">
                    <div class="mb-4">
                        <i class="ri-question-line text-4xl text-warning"></i>
                    </div>
                    <h5 class="text-lg font-semibold mb-2">Change Ride Status</h5>
                    <p class="text-gray-600 mb-4" id="status-change-message">
                        Are you sure you want to change the status to "Completed"?
                    </p>
                </div>
            </div>
            <div class="ti-modal-footer">
                <button type="button" class="ti-btn ti-btn-outline-secondary" data-hs-overlay="#status-change-modal">
                    Cancel
                </button>
                <button type="button" class="ti-btn ti-btn-primary" id="confirm-status-change">
                    Yes, Change Status
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Refund Success Details Modal -->
<div id="refund-success-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-xl mx-auto" style="position:relative;">
            <div class="ti-modal-body p-6 text-center">
                <button type="button"
                    onclick="closeRefundSuccessModal(); if(currentRideId) loadRideDetails(currentRideId);"
                    class="absolute top-3 right-3 ti-btn p-1 text-gray-500 hover:text-gray-700"
                    style="position:absolute; top:12px; right:12px; z-index:10;">
                    <span class="sr-only">Close</span>
                    <i class="ri-close-line" style="font-size:1.2rem;"></i>
                </button>
                <div class="mb-3">
                    <span
                        class="avatar avatar-xl p-4 !rounded-full bg-primary/10 text-primary inline-flex items-center justify-center">
                        <i class="ri-refund-2-line text-primary text-3xl"></i>
                    </span>
                </div>
                <h3 class="text-xl font-semibold text-primary mb-1">Review Refund Details</h3>
                <p class="text-sm text-muted mb-4">
                    Client: <span id="refund-modal-client-name" class="font-semibold text-gray-800">-</span>
                </p>

                <!-- Refund Detail Cards -->
                <div class="grid grid-cols-2 gap-3 text-left mb-4">
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Original Amount</p>
                        <p class="font-semibold text-gray-800" id="refund-modal-original-amount">-</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Refund Amount</p>
                        <p class="font-semibold text-success" id="refund-modal-refund-amount">-</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Refund Type</p>
                        <p class="font-semibold text-gray-800" id="refund-modal-refund-type">-</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200">
                        <p class="text-xs text-gray-500 mb-1">Refund Date</p>
                        <p class="font-semibold text-gray-800" id="refund-modal-refund-date">-</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 col-span-2">
                        <p class="text-xs text-gray-500 mb-1">Refund Reason</p>
                        <p class="font-semibold text-gray-800 text-sm" id="refund-modal-refund-reason">-</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 border border-gray-200 col-span-2"
                        id="refund-modal-proof-row">
                        <p class="text-xs text-gray-500 mb-1">Refund Proof</p>
                        <div class="flex items-center justify-between">
                            <p class="font-semibold text-gray-800 text-sm" id="refund-modal-proof-name">-</p>
                            <button type="button"
                                class="ti-btn ti-btn-sm ti-btn-outline-primary py-0 px-2 flex items-center justify-center"
                                id="refund-modal-preview-btn" style="display:none; width: 32px; height: 32px;">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status indicator while saving -->
                <div id="refund-modal-saving-indicator" style="display:none;" class="mb-3">
                    <span class="text-sm text-primary">
                        <i class="ri-loader-2-line animate-spin me-1"></i>Saving refund data...
                    </span>
                </div>
                <div id="refund-modal-saved-indicator" style="display:none;" class="mb-3">
                    <span class="text-sm text-primary">
                        <i class="ri-checkbox-circle-line me-1"></i>Refund saved successfully!
                    </span>
                </div>

                <!-- Action Buttons -->
                <div class="flex gap-3 justify-center mt-2">
                    <!-- Edit: close modal, go back to form -->
                    <button type="button" class="ti-btn ti-btn-outline-primary" id="refund-modal-edit-btn">
                        <i class="ri-edit-line me-2"></i>Edit
                    </button>
                    <!-- OK: confirm and finalize save -->
                    <button type="button" class="ti-btn bg-primary text-white" id="refund-modal-ok-btn">
                        <i class="ri-checkbox-circle-line me-2"></i>OK, Confirm
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Global Loader Overlay -->
<!-- Global Loader Overlay -->
<div id="status-processing-loader"
    style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:999999999; align-items:center; justify-content:center; pointer-events:all;">

    <div
        style="background:white; padding:30px 40px; border-radius:12px; text-align:center; width:300px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div
            style="width:48px;height:48px;border:4px solid #eee;border-top:4px solid #2B53A9;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 16px;">
        </div>
        <h5 id="status-loader-title" style="font-weight:700;margin-bottom:6px;color:#111827;">Processing Status...</h5>
        <p id="status-loader-subtitle" style="font-size:13px; color:#666; margin:0;">Please wait, do not close this
            window.</p>
    </div>

</div>

@endsection

@push('scripts')
<script>
    // Toggle filter section and clear filters helper
        function clearRideFilters() {
            window.location.href = '{{ route("admin.rides.ride-status") }}';
        }

        $(document).on('click', '#toggle-filters', function() {
            $('#filter-section').toggleClass('hidden');
            $('#filter-icon').toggleClass('ti-chevron-up ti-chevron-down');
        });

        let currentRideId = null;
        let pendingStatusChange = null;
        let lastUpdatedRideId = null;

        // Role flags from server side
        const isAdmin = {!! json_encode($isAdmin ?? false) !!};
        const isAccounts = {!! json_encode($isAccounts ?? false) !!};
        const isOperations = {!! json_encode($isOperations ?? false) !!};

        $(document).ready(function() {
            // Check if we need to reopen a modal after page reload
            const reopenRideId = sessionStorage.getItem('reopenRideModal');
            if (reopenRideId) {
                sessionStorage.removeItem('reopenRideModal');
                // Delay to ensure page is fully loaded
                setTimeout(() => {
                    currentRideId = reopenRideId;
                    loadRideDetails(reopenRideId);
                }, 500);
            }

            // Helper: collect current filter values from the form inputs
            function getFilterParams() {
                var params = new URLSearchParams();
                // Gather all form inputs that have a value
                $('#filter-form').find('input, select').each(function() {
                    var name = $(this).attr('name');
                    var val = $(this).val();
                    if (name && val && val !== '') {
                        params.set(name, val);
                    }
                });
                return params;
            }

            // Export handlers - export using current filter form values
            $('.export-excel-btn').on('click', function() {
                var params = getFilterParams();
                params.set('format', 'xlsx');
                window.location.href = '{{ route("admin.rides.ride-status.export") }}?' + params.toString();
            });

            $('.export-csv-btn').on('click', function() {
                var params = getFilterParams();
                params.set('format', 'csv');
                window.location.href = '{{ route("admin.rides.ride-status.export") }}?' + params.toString();
            });

            // View ride details
            $(document).on('click', '.view-ride-btn', function(e) {
                // Don't prevent default - let the data-hs-overlay handle modal opening
                currentRideId = $(this).data('ride-id');

                // Test: Log the generated URL
                const testUrl = `{{ route('admin.rides.ride-status.details', ':rideId') }}`.replace(
                    ':rideId', currentRideId);
                // ...removed debug log...

                loadRideDetails(currentRideId);
            });

            // Status change handler
            $('#ride-status-dropdown').on('change', function() {
                const newStatus = parseInt($(this).val());
                // ...removed debug log...
                if (newStatus) {
                    showStatusChangeConfirmation(newStatus);
                }
            });

            // Confirmation modal handlers
            // $('#confirm-status-change').on('click', function() {
            //     if (pendingStatusChange) {
            //         updateRideStatus(pendingStatusChange);
            //         hideStatusChangeModal();
            //     }
            // });

         $('#confirm-status-change').on('click', function() {
    if (pendingStatusChange) {
        const statusToProcess = parseInt(pendingStatusChange);
        hideStatusChangeModal();
        showStatusLoader(statusToProcess);
        updateRideStatus(statusToProcess);
    }
});

            // Close modal handlers
            $(document).on('click', '[data-hs-overlay="#status-change-modal"]', function() {
                hideStatusChangeModal();
            });

            // Close modal when clicking outside
            $(document).on('click', '#status-modal-backdrop', function() {
                hideStatusChangeModal();
            });

            // Edit dates functionality - using event delegation for dynamically changing IDs
            $(document).on('click', '#edit-dates-btn', function() {
                showEditDatesForm();
            });

            $(document).on('click', '#cancel-edit-btn', function() {
                hideEditDatesForm();
            });

            $(document).on('click', '#save-dates-btn', function() {
                saveDateChanges();
            });

            // Action buttons
            $('#generate-invoice-btn').on('click', function() {
                generateInvoice();
            });

            $('#refund-note-btn').on('click', function() {
                showRefundInformationSection();
            });

            // Cancel refund button handler
            $('#cancel-refund-btn').on('click', function() {
                hideRefundInformationSection();
            });

            // Ride refund form submission
            $('#ride-refund-form').on('submit', function(e) {
                e.preventDefault();
                saveRideRefundData();
            });

            // Redirect to refund notes when Ride Refund button is clicked
            $(document).on('click', '#ride-redirect-to-refunds-btn', function(e) {
                e.preventDefault();
                const followupParam = encodeURIComponent($('#ride-followup-id').val() || '');
                const baseUrl = "{{ route('admin.refunds.index') }}";
                if (followupParam) {
                    window.location.href = baseUrl + '?open_refund_id=' + followupParam;
                } else {
                    window.location.href = baseUrl;
                }
            });

            // Preview proof button click - open preview URL (works for existing saved proofs and newly selected files)
            $(document).on('click', '#ride-preview-proof-btn', function(e) {
                e.preventDefault();
                const previewUrl = $(this).data('preview-url');
                if (previewUrl) {
                    // Open in new tab
                    window.open(previewUrl, '_blank');
                } else {
                    showError('No preview available');
                }
            });

            // When a new file is selected, create an object URL for preview and update filename/hint
            $(document).on('change', '#ride-refund-proof', function() {
                const fileInput = this;
                const file = fileInput.files && fileInput.files[0];
                if (file) {
                    try {
                        // Revoke previous object URL if present
                        if (window.currentRefundObjectUrl) {
                            try { URL.revokeObjectURL(window.currentRefundObjectUrl); } catch (e) { /* ignore */ }
                            window.currentRefundObjectUrl = null;
                        }
                        const objectUrl = URL.createObjectURL(file);
                        window.currentRefundObjectUrl = objectUrl;
                        $('#ride-preview-proof-btn').data('preview-url', objectUrl).show();
                        $('#ride-proof-filename').text(file.name);
                        $('#ride-proof-hint').text('Selected file — click Preview to open in a new tab.');
                    } catch (e) {
                        // fallback: no preview
                        $('#ride-preview-proof-btn').data('preview-url', '').hide();
                        $('#ride-proof-filename').text(file.name || 'No file selected');
                        $('#ride-proof-hint').text('');
                    }
                } else {
                    $('#ride-preview-proof-btn').data('preview-url', '').hide();
                    $('#ride-proof-filename').text('No file selected');
                    $('#ride-proof-hint').text('');
                }
            });

            // No date checkbox handler
            $('#no-date-checkbox').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#new-from-date, #new-to-date').prop('disabled', true).val('');
                } else {
                    $('#new-from-date, #new-to-date').prop('disabled', false);
                }
            });
        });

        function loadRideDetails(rideId) {
            // Show loading state
            showLoadingState();

            // ...removed debug log...

            $.ajax({
                url: `{{ route('admin.rides.ride-status.details', ':rideId') }}`.replace(':rideId', rideId),
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                success: function(response) {
                    // Check if response has the expected format with success flag
                    if (response && response.success && response.data) {
                        populateRideDetails(response.data);

                        // Explicitly trigger the modal to open
                        setTimeout(() => {
                            const modal = document.getElementById('ride-details-modal');
                            if (modal) {
                                if (window.HSOverlay) {
                                    window.HSOverlay.open(modal);
                                } else {
                                    modal.classList.remove('hidden');
                                    modal.classList.add('open');
                                }
                            }
                        }, 100);
                    }
                    // Handle direct response format (current API format)
                    else if (response && (response.ride || response.client || response.followup)) {
                        // ...removed debug log...
                        // Transform the response to match expected format
                        const transformedData = {
                            client_name: response.client ? `${response.client.name || ''}`.trim() :
                                'Unknown Client',
                            client_email: response.client ? response.client.email : '',
                            contact_number: response.client ? response.client.contact_number : '',
                            whatsapp_number: response.client ? response.client.alternate_number : '',
                            country_name: response.client ? response.client.country : '',
                            city_name: response.client ? response.client.city : '',
                            address: response.client ? response.client.address : '',
                            from_date: response.ride ? response.ride.from_date : '',
                            from_place: response.ride ? response.ride.from_place : '',
                            to_date: response.ride ? response.ride.to_date : '',
                            to_place: response.ride ? response.ride.to_place : '',
                            service_names: response.service_names || '',
                            extra_services: response.extra_service_names || '',
                            total_amount: response.total_amount || 0,
                            received_amount: response.received_amount || 0,
                            status: response.status || (response.followup ? response.followup.status : 0),
                            payment_history: response.payment_history || [],
                            all_rides: response.all_rides || [], // Include multiple rides
                            followup_id: response.followup_id || null, // Include followup_id for refund operations
                            // carry over refund if API returns it so front-end can prefill old values
                            refund: response.refund || null
                        };
                        populateRideDetails(transformedData);

                        // Ensure modal opens after data is populated
                        // ...removed debug log...

                        // Explicitly trigger the modal to open
                        setTimeout(() => {
                            const modal = document.getElementById('ride-details-modal');
                            if (modal) {
                                // Use HSOverlay to show the modal
                                if (window.HSOverlay) {
                                    window.HSOverlay.open(modal);
                                } else {
                                    // Fallback: manually add classes
                                    modal.classList.remove('hidden');
                                    modal.classList.add('open');
                                }
                            }
                        }, 100);
                    } else {
                        const errorMsg = response && response.message ? response.message :
                            'No valid response data received';
                        showError('Failed to load ride details: ' + errorMsg);
                        // ...removed debug log...
                    }
                },
                error: function(xhr, status, error) {
                    // ...removed debug log...

                    let errorMessage = 'Error loading ride details. ';
                    if (xhr.status === 404) {
                        errorMessage +=
                            'Route not found (404). The backend controller method might not be implemented yet.';
                        // Show sample data for testing
                        // ...removed debug log...
                        showSampleData(rideId);
                        return;
                    } else if (xhr.status === 500) {
                        errorMessage += 'Server error (500). Please check the backend implementation.';
                    } else if (xhr.status === 0) {
                        errorMessage += 'Network error. Please check your connection.';
                    } else {
                        errorMessage += `HTTP ${xhr.status}: ${xhr.statusText}`;
                    }

                    showError(errorMessage);
                }
            });
        }

        function populateRideDetails(data) {
            // ...removed debug log...

            // Clear any previous edit form state
            $('#travel-edit-form').hide();
            $('#travel-info-display').show();
            $('#travel-edit-content').empty();

            // Update modal header
            $('#modal-client-name').text(data.client_name || 'Unknown Client');

            // Update current status badge
            $('#current-status-badge').html(getRideStatusBadge(data.status));

            // Only set dropdown value if the status is one of the allowed options (2, 5, 7)
            // Otherwise, don't select anything in the dropdown
            // if ([2, 5, 7].includes(parseInt(data.status))) {
            //     $('#ride-status-dropdown').val(data.status);
            // } else {
            //     $('#ride-status-dropdown').val(''); // Clear selection
            // }

            if ([2, 5, 7].includes(parseInt(data.status))) {
    $('#ride-status-dropdown').val(data.status);
} else {
    $('#ride-status-dropdown').val('');
}

// Freeze rules:
// status=5 (Completed): always frozen
// status=2 (Cancelled) + refund.status>=1: frozen only after popup OK + email/WA sent
// Save Changes sets refund.status=0 — dropdown stays EDITABLE
const hasRefundNotified = !!(data.refund && data.refund.id && parseInt(data.refund.status) >= 1);
const statusInt2 = parseInt(data.status);
const shouldFreezeStatus = (statusInt2 === 5) || (statusInt2 === 2 && hasRefundNotified);

$('#ride-status-freeze-msg').remove();
if (shouldFreezeStatus) {
    $('#ride-status-dropdown').prop('disabled', true);
    const freezeMsg = (statusInt2 === 5)
        ? 'Status is locked — ride completed and invoice generated.'
        : 'Status is locked — refund notifications already sent.';
    $('#ride-status-dropdown').after(
        '<small id="ride-status-freeze-msg" class="text-danger mt-1 d-block">' +
        '<i class="ri-lock-line me-1"></i>' + freezeMsg +
        '</small>'
    );
} else {
    $('#ride-status-dropdown').prop('disabled', false);
}

            // Calculate and update payment progress
            const totalAmount = parseFloat(data.total_amount || 0);

            // Calculate total paid amount using only approved payments from audit trail (match PaymentReview)
            let totalPaidAmount = 0;
            if (data.payment_history && data.payment_history.length > 0) {
                totalPaidAmount = data.payment_history.reduce((sum, payment) => {
                    try {
                        const audit = payment.audit_trail || null;
                        if (audit && parseInt(audit.payment_status) === 1) {
                            return sum + parseFloat(audit.paid_amount || 0);
                        }
                        return sum;
                    } catch (e) {
                        return sum;
                    }
                }, 0);
            }

            const paymentPercentage = totalAmount > 0 ? (totalPaidAmount / totalAmount * 100) : 0;

            $('#payment-progress-bar').css('width', paymentPercentage + '%').attr('aria-valuenow', paymentPercentage);
            $('#payment-progress-text').text(Math.round(paymentPercentage) + '%');

            // Update client information
            $('#client-name').text(data.client_name || '-');
            $('#client-email').text(data.client_email || '-');
            $('#client-phone').text(data.contact_number || '-');
            $('#client-whatsapp').text(data.whatsapp_number || '-');
            $('#client-country').text(data.country_name || '-');
            $('#client-city').text(data.city_name || '-');
            $('#client-address').text(data.address || '-');

            // Store rides data for editing (clear previous data first)
            window.currentRidesData = null; // Clear completely first
            window.currentRidesData = data.all_rides || [];

            // ...removed debug log...

            // Update travel information
            updateTravelInformation(data.all_rides || []);

            // Store original dates for editing (use first ride for compatibility)
            const firstRide = (data.all_rides && data.all_rides.length > 0) ? data.all_rides[0] : {
                from_date: data.from_date,
                to_date: data.to_date
            };
            $('#prev-from-date').text(formatDate(firstRide.from_date) || '-');
            $('#prev-to-date').text(formatDate(firstRide.to_date) || '-');

            // Set datetime input values for editing (use first ride)
            if (firstRide.from_date) {
                $('#new-from-date').val(formatDateTimeLocal(firstRide.from_date));
            }
            if (firstRide.to_date) {
                $('#new-to-date').val(formatDateTimeLocal(firstRide.to_date));
            }

            // Update service information
            $('#service-name').text(data.service_names || '-');
            $('#extra-services').text(data.extra_services || '-');

            // Update payment information (following payment review logic)
            $('#total-amount').text('₹' + (totalAmount ? totalAmount.toLocaleString('en-IN', {
                minimumFractionDigits: 2
            }) : '0.00'));
            $('#paid-amount').text('₹' + (totalPaidAmount ? totalPaidAmount.toLocaleString('en-IN', {
                minimumFractionDigits: 2
            }) : '0.00'));
            const balance = totalAmount - totalPaidAmount;
            $('#balance-amount').text('₹' + (balance ? balance.toLocaleString('en-IN', {
                minimumFractionDigits: 2
            }) : '0.00'));

            // Update view receipt button based on latest payment
            updateViewReceiptButton(data.payment_history || []);

            // Show/hide edit dates button for rescheduled rides only
            if (data.status === 7) {
                $('#edit-dates-container').show();
            } else {
                $('#edit-dates-container').hide();
            }

            // Update action buttons based on status
            updateActionButtons(data.status);

            // Load payment history
            loadPaymentHistory(data.payment_history || []);

            // Store current followup id (latest followup for this lead) for refund operations
            try {
                window.currentFollowupId = data.followup_id || null;
            } catch (e) {
                window.currentFollowupId = null;
            }

            // Store existing refund data (if any) so refund form can be pre-filled
            try {
                window.currentRefundData = data.refund || null;
            } catch (e) {
                window.currentRefundData = null;
            }
        }

        function updateActionButtons(status) {
            // Hide all buttons initially
            $('#action-buttons, #generate-invoice-btn, #refund-note-btn').hide();

            // Status-based button display logic:
            // Status 0 (Initiated) - No buttons
            // Status 1 (Active) - No buttons
            // Status 2 (Cancelled) - Refund Note button
            // Status 3 (Full Payment) - No buttons
            // Status 4 (Partial Payment) - No buttons
            // Status 5 (Complete) - Generate Invoice button
            // Status 6 (Pending) - No buttons
            // Status 7 (Reschedule) - No buttons at bottom (Edit button is shown in Travel Information section)

            const statusInt = parseInt(status);

            if (statusInt === 2) { // Cancelled - show refund note
                $('#action-buttons, #refund-note-btn').show();
            }
            // Status 5 (Complete) no longer shows Generate Invoice button
            // because invoice is auto-generated when status changes to completed
            // For all other statuses (0,1,3,4,5,6,7), no buttons are shown at the bottom
        }

        function loadPaymentHistory(paymentHistory) {
            const container = $('#payment-history-container');

            if (!paymentHistory || paymentHistory.length === 0) {
                container.html('<div class="text-center py-4"><p class="text-gray-500">No payment history found</p></div>');
                return;
            }

            let historyHtml = '';
            paymentHistory.forEach(function(payment, index) {
                const amount = parseFloat(payment.amount || 0);
                // Prefer payment status from the audit trail (latest audit) as the
                // source of truth. Fall back to payment.status if audit not present.
                const statusCode = (payment.audit_trail && payment.audit_trail.payment_status != null) ? payment.audit_trail.payment_status : (payment.status != null ? payment.status : 'pending');
                const statusBadge = getPaymentStatusBadge(statusCode);
                const paymentDate = payment.created_at ? new Date(payment.created_at).toLocaleDateString('en-GB') :
                    'N/A';
                const paymentTime = payment.created_at ? new Date(payment.created_at).toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                }) : '';

                const createdByInfo = payment.created_by_name || payment.user_name || 'System';
                const paymentMethod = payment.payment_method || '';

                // Determine description with clear priority:
                // 1) audit.narration (authoritative for approve/reject)
                // 2) payment.description
                // 3) payment.followup_note
                // 4) derive from audit.payment_status (Approved/Rejected/Full/Partial)
                const audit = payment.audit_trail || null;
                let description = 'Payment received';

                if (audit && audit.narration && String(audit.narration).trim() !== '') {
                    description = String(audit.narration);
                } else if (payment.description && String(payment.description).trim() !== '') {
                    description = String(payment.description);
                } else if (payment.followup_note && String(payment.followup_note).trim() !== '') {
                    description = String(payment.followup_note);
                } else if (audit && audit.payment_status != null) {
                    switch (parseInt(audit.payment_status)) {
                        case 1:
                            description = 'Payment Approved' + (audit.payment_method ? ' • ' + audit.payment_method : '');
                            break;
                        case 2:
                            description = 'Payment Rejected';
                            break;
                        case 3:
                            description = 'Full Payment Received';
                            break;
                        case 4:
                            description = 'Partial Payment Received';
                            break;
                        default:
                            description = 'Payment received';
                    }
                }

                // Generate receipt button for each payment that has a file
                let receiptButton = '';
                if (payment.file) {
                    receiptButton = `
                        <button type="button" class="ti-btn ti-btn-outline-primary view-payment-receipt-btn mt-2" 
                            data-file="${payment.file}" data-payment-id="${payment.id}">
                            <i class="ri-file-text-line me-1"></i> View Receipt
                        </button>
                    `;
                }

                historyHtml += `
                <div class="flex ${index > 0 ? 'mt-4 pt-4 border-t border-gray-200' : ''}">
                    <div class="me-4 gap-0">
                        <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2" fill="#2B53A9" />
                                <path d="M8.5 12.5L11 15L16 9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <div class="flex-grow">
                        <div class="md:flex block items-center justify-between mb-2">
                            <div>
                                <h5 class="font-semibold mb-1 leading-none text-[1.25rem]">₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</h5>
                                <p class="text-sm text-gray-600 mb-1">${paymentDate} • ${paymentMethod}</p>
                            </div>
                            <div>
                                ${statusBadge}
                                <p class="text-sm text-gray-600">${createdByInfo}</p>
                                <p class="text-[#8c9097] text-xs">${paymentDate}${paymentTime ? ' | ' + paymentTime : ''}</p>
                            </div>
                        </div>
                        <p class="text-[#8c9097] text-sm">${description}</p>
                        ${receiptButton}
                    </div>
                </div>
            `;
            });

            container.html(historyHtml);
        }

        function getPaymentStatusBadge(status) {
            const badges = {
                // Audit/status mapping (audit is source of truth for payments)
                0: '<span class="badge !rounded-full bg-info/10 text-info mb-2">Initiated</span>',
                1: '<span class="badge !rounded-full bg-success/10 text-success mb-2">Payment Approved</span>',
                2: '<span class="badge !rounded-full bg-danger/10 text-danger mb-2">Payment Rejected</span>',
                3: '<span class="badge !rounded-full bg-primary/10 text-primary mb-2">Full Payment Received</span>',
                4: '<span class="badge !rounded-full bg-warning/10 text-warning mb-2">Partial Payment Received</span>',
                5: '<span class="badge !rounded-full bg-success/10 text-success mb-2">Complete</span>',
                6: '<span class="badge !rounded-full bg-secondary/10 text-secondary mb-2">Pending</span>',
                7: '<span class="badge !rounded-full bg-purple/10 text-purple mb-2">Reschedule</span>',
                8: '<span class="badge !rounded-full bg-info/10 text-info mb-2">Approved</span>',
                9: '<span class="badge !rounded-full bg-yellow/10 text-yellow mb-2">Rejected</span>',
                'completed': '<span class="badge !rounded-full bg-success/10 text-success mb-2">Completed</span>',
                'pending': '<span class="badge !rounded-full bg-warning/10 text-warning mb-2">Pending</span>',
                'failed': '<span class="badge !rounded-full bg-danger/10 text-danger mb-2">Failed</span>'
            };
            return badges[status] || '<span class="badge !rounded-full bg-secondary/10 text-secondary mb-2">Payment</span>';
        }

        function getRideStatusBadge(status) {
            const badges = {
                0: '<span class="badge !rounded-full bg-info/10 text-info">Initiated</span>',
                1: '<span class="badge !rounded-full bg-success/10 text-success">Active</span>',
                2: '<span class="badge !rounded-full bg-danger/10 text-danger">Cancelled</span>',
                3: '<span class="badge !rounded-full bg-primary/10 text-primary">Full Payment Received</span>',
                4: '<span class="badge !rounded-full bg-warning/10 text-warning">Partial Payment Received</span>',
                5: '<span class="badge !rounded-full bg-success/10 text-success">Completed</span>',
                6: '<span class="badge !rounded-full bg-secondary/10 text-secondary">Pending</span>',
                7: '<span class="badge !rounded-full bg-purple/10 text-purple">Reschedule</span>'
            };
            return badges[status] || '<span class="badge !rounded-full bg-secondary/10 text-secondary">Pending</span>';
        }

        function showStatusChangeConfirmation(newStatus) {
            // ...removed debug log...

            const statusLabels = {
                2: 'Cancelled',
                5: 'Completed',
                7: 'Reschedule'
            };

            const statusText = statusLabels[newStatus] || 'Unknown Status';
            pendingStatusChange = newStatus;

            // ...removed debug log...

            // Update confirmation message dynamically
            $('#status-change-message').text(`Are you sure you want to change the status to "${statusText}"?`);

            // ...removed debug log...

            // Show the confirmation modal using HSOverlay properly
            const statusModal = document.getElementById('status-change-modal');
            // ...removed debug log...

            if (statusModal) {
                // Remove hidden class and add open state
                statusModal.classList.remove('hidden');
                statusModal.classList.add('open');

                // ...removed debug log...

                // Add overlay backdrop
                const backdrop = document.createElement('div');
                backdrop.className =
                    'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
                backdrop.setAttribute('id', 'status-modal-backdrop');
                backdrop.onclick = function() {
                    hideStatusChangeModal();
                };
                document.body.appendChild(backdrop);

                // Prevent body scroll
                document.body.style.overflow = 'hidden';

                // ...removed debug log...
            } else {
                // ...removed debug log...
            }
        }

        function hideStatusChangeModal() {
            // ...removed debug log...

            const statusModal = document.getElementById('status-change-modal');
            const backdrop = document.getElementById('status-modal-backdrop');

            if (statusModal) {
                statusModal.classList.add('hidden');
                statusModal.classList.remove('open');
            }

            if (backdrop) {
                backdrop.remove();
            }

            // Restore body scroll
            document.body.style.overflow = '';

            // Reset pending status change
            pendingStatusChange = null;

            // Reset dropdown to original value
            $('#ride-status-dropdown').val('');
            // modal cleanup completed
        }

        function updateRideStatus(newStatus) {
            if (!currentRideId) {
                showError('No ride selected');
                return;
            }

            // Block if status is already final with a refund
            if ($('#ride-status-dropdown').prop('disabled')) {
                showError('Status is locked and cannot be changed.');
                return;
            }

            // Only allow statuses 2, 5, or 7
            const allowedStatuses = [2, 5, 7];
            if (!allowedStatuses.includes(newStatus)) {
                showError('Only Cancelled (2), Completed (5), and Reschedule (7) statuses can be updated');
                $('#ride-status-dropdown').val('');
                return;
            }

            // Get current values from the page
            const totalAmount = parseFloat($('#total-amount').text().replace(/[^0-9.]/g, '')) || 0;

            let serviceIds = [];
            let extraServiceIds = [];

            try {
                if (window.currentRidesData) {
                    window.currentRidesData.forEach(ride => {
                        // Process service_ids
                        if (ride.service_ids) {
                            let ids = ride.service_ids;
                            if (typeof ids === 'string') {
                                try {
                                    if (ids.startsWith('"') && ids.endsWith('"')) {
                                        ids = ids.slice(1, -1);
                                    }
                                    ids = JSON.parse(ids);
                                } catch (e) {
                                    ids = [];
                                }
                            }
                            if (Array.isArray(ids)) {
                                serviceIds = [...new Set([...serviceIds, ...ids])];
                            }
                        }

                        // Process extra_service_ids
                        if (ride.extra_service_ids) {
                            let ids = ride.extra_service_ids;
                            if (typeof ids === 'string') {
                                try {
                                    // Handle the same format as above
                                    if (ids.startsWith('"') && ids.endsWith('"')) {
                                        ids = ids.slice(1, -1);
                                    }
                                    ids = JSON.parse(ids);
                                } catch (e) {
                                    ids = [];
                                }
                            }
                            if (Array.isArray(ids)) {
                                extraServiceIds = [...new Set([...extraServiceIds, ...ids])];
                            }
                        }
                    });
                }
            } catch (e) {
                // error collecting services
            }

            // Always send arrays, never undefined/null
            if (!Array.isArray(serviceIds)) {
                serviceIds = [];
            }
            if (!Array.isArray(extraServiceIds)) {
                extraServiceIds = [];
            }


            // Prepare request data
            const requestData = {
                status: newStatus,
                total_amount: totalAmount,
                service_ids: serviceIds,
                extra_service_ids: extraServiceIds,
                _token: '{{ csrf_token() }}'
            };

            // sending status update

            $.ajax({
                url: `{{ route('admin.rides.ride-status.update-status', ':rideId') }}`.replace(':rideId',
                    currentRideId),
                method: 'POST',
                data: requestData,
                beforeSend: function() {
                    $('#ride-status-dropdown').prop('disabled', true);
                },
                success: function(response) {
    hideStatusLoader();
    if (response?.success) {
        const invoiceInfo = response?.data?.invoice;
        const statusChanged = parseInt(requestData.status);

        if (statusChanged === 5 && invoiceInfo && invoiceInfo.redirect_url) {
            // Completed — show popup then redirect to invoice
            showSuccessModal('Success', 'Ride completed — invoice generated and finalized successfully.', function() {
                window.location.href = invoiceInfo.redirect_url;
            });
        }else if (statusChanged === 2) {
    // Cancelled — show loader briefly then popup
    document.getElementById('status-loader-title').textContent = 'Ride Cancelled!';
    document.getElementById('status-loader-subtitle').textContent = 'Status updated successfully.';

    setTimeout(function() {
        hideStatusLoader();
        const successOverlay = document.createElement('div');
            successOverlay.style.cssText = 'position:fixed;inset:0;z-index:999999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
            successOverlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="margin-bottom:20px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#fee2e2;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" fill="#ef4444"/>
                                <path d="M8 8L16 16M16 8L8 16" stroke="#fff" stroke-width="2.5" stroke-linecap="round"/>
                            </svg>
                        </span>
                    </div>
                    <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">Ride Cancelled</h5>
                    <p style="color:#6b7280;margin-bottom:20px;font-size:0.9rem;">The ride has been successfully cancelled.</p>
                    <button id="cancel-status-ok-btn" style="background:#2B53A9;color:#fff;border:none;border-radius:8px;padding:10px 40px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>
                </div>
            `;
            document.body.appendChild(successOverlay);
            document.body.style.pointerEvents = '';
            document.getElementById('cancel-status-ok-btn').onclick = function() {
                successOverlay.remove();
                sessionStorage.setItem('reopenRideModal', currentRideId);
                location.reload();
            };
    },1500);
    
        } else if (statusChanged === 7) {
    // Cancelled — show loader briefly then popup
    document.getElementById('status-loader-title').textContent = 'Ride Rescheduled';
    document.getElementById('status-loader-subtitle').textContent = 'Status updated successfully.';

    setTimeout(function() {
        hideStatusLoader();
        const successOverlay = document.createElement('div');
            successOverlay.style.cssText = 'position:fixed;inset:0;z-index:999999999;background:rgba(0,0,0,0.5);display:flex;align-items:center;justify-content:center;';
            successOverlay.innerHTML = `
                <div style="background:#fff;border-radius:12px;padding:40px 48px;text-align:center;max-width:400px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                    <div style="margin-bottom:20px;">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:72px;height:72px;border-radius:50%;background:#e0e7ff;">
    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" fill="none" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="10" fill="#2B53A9"/>
        <path d="M12 7v5l3 2" stroke="#fff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</span>
                    </div>
                    <h5 style="font-size:1.25rem;font-weight:700;margin-bottom:8px;color:#111827;">Ride Rescheduled</h5>
                    <p style="color:#6b7280;margin-bottom:20px;font-size:0.9rem;">The ride has been successfully rescheduled.</p>
                    <button id="cancel-status-ok-btn" style="background:#2B53A9;color:#fff;border:none;border-radius:8px;padding:10px 40px;font-size:1rem;font-weight:600;cursor:pointer;">OK</button>
                </div>
            `;
            document.body.appendChild(successOverlay);
            document.body.style.pointerEvents = '';
            document.getElementById('cancel-status-ok-btn').onclick = function() {
                successOverlay.remove();
                sessionStorage.setItem('reopenRideModal', currentRideId);
                location.reload();
            };
    },1500);
    }else {
            // Reschedule or others — reload and reopen
            sessionStorage.setItem('reopenRideModal', currentRideId);
            setTimeout(() => location.reload(), 800);
        }
    } else {
        const errorMsg = response?.message || 'Status update failed';
        showError(errorMsg);
        $('#ride-status-dropdown').val('');
    }
},
                error: function(xhr) {
                    hideStatusLoader();   // hide loader
                    const errorMsg = xhr.responseJSON?.message ||
                        'Error updating status. Please try again.';
                    showError(errorMsg);
                    $('#ride-status-dropdown').val('');
                },
                complete: function() {
                     hideStatusLoader();
                    $('#ride-status-dropdown').prop('disabled', false);
                }
            });
        }

        function showEditDatesForm() {
            $('#travel-info-display').hide();
            $('#travel-edit-form').show();
            $('#edit-dates-btn').text('Cancel').attr('id', 'cancel-edit-btn');

            // Populate edit form based on single or multiple trips
            populateEditForm();
        }

        function populateEditForm() {
            const container = $('#travel-edit-content');
            const rides = window.currentRidesData || [];

            if (rides.length === 0) {
                container.html('<p class="text-gray-500">No trip data available for editing</p>');
                return;
            }

            let editHtml = '';

            if (rides.length === 1) {
                // Single trip - original format
                const ride = rides[0];
                editHtml = `
                <div class="grid grid-cols-12 gap-6">
                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <p class="text-primary">Previous Dates :</p>
                        <div>
                            <p>
                                <span>From : <span>${formatDate(ride.from_date) || '-'}</span></span>
                                |
                                <span>To : <span>${formatDate(ride.to_date) || '-'}</span></span>
                            </p>
                        </div>
                    </div>
                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <p class="text-primary">New Dates :</p>
                    </div>
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="datetime-local" class="form-control form-control-sm" id="new-from-date" value="${formatDateTimeLocal(ride.from_date) || ''}">
                            </div>
                        </div>
                    </div>
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                        <div class="form-group">
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="datetime-local" class="form-control form-control-sm" id="new-to-date" value="${formatDateTimeLocal(ride.to_date) || ''}">
                            </div>
                        </div>
                    </div>
                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <div class="form-check form-check-md flex items-center">
                            <input class="form-check-input" type="checkbox" value="" id="no-date-checkbox">
                            <label class="form-check-label" for="no-date-checkbox">
                                No Date
                            </label>
                        </div>
                    </div>
                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <button type="button" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn me-2" id="save-dates-btn">Save Changes</button>
                        <button type="button" class="ti-btn ti-btn-outline-secondary ti-btn-wave" id="cancel-edit-btn">Cancel</button>
                    </div>
                </div>
            `;
            } else {
                // Multiple trips - show each trip with places as non-editable and dates as editable
                editHtml = `
                <div class="mb-4">
                    <p class="text-primary font-semibold mb-2">Edit Multiple Trip Dates</p>
                    <p class="text-sm text-gray-600 mb-4">You can edit the dates for each trip segment. Places cannot be modified.</p>
                </div>
            `;

                rides.forEach((ride, index) => {
                    editHtml += `
                    <div class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
                        <div class="flex items-center mb-3">
                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-semibold mr-3">
                                ${index + 1}
                            </div>
                            <h6 class="text-lg font-semibold text-gray-800">Trip Segment ${index + 1}</h6>
                        </div>
                        
                        <div class="grid grid-cols-12 gap-4">
                            <!-- Previous Dates -->
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12 mb-3">
                                <p class="text-primary text-sm font-medium">Previous Dates:</p>
                                <p class="text-sm text-gray-600">
                                    <span>From: ${formatDate(ride.from_date) || '-'}</span> | 
                                    <span>To: ${formatDate(ride.to_date) || '-'}</span>
                                </p>
                            </div>
                            
                            <!-- From Place (Non-editable) -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-1 text-xs">From Place</label>
                                <div class="bg-white p-2 rounded border border-gray-300">
                                    <p class="text-gray-800 font-medium text-sm">${ride.from_place || '-'}</p>
                                </div>
                            </div>
                            
                            <!-- New From Date -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-1 text-xs">From Date</label>
                                <div class="form-group">
                                    <input type="datetime-local" class="form-control form-control-sm trip-from-date" 
                                           data-trip-index="${index}" value="${formatDateTimeLocal(ride.from_date) || ''}">
                                </div>
                            </div>
                            
                            <!-- To Place (Non-editable) -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-1 text-xs">To Place</label>
                                <div class="bg-white p-2 rounded border border-gray-300">
                                    <p class="text-gray-800 font-medium text-sm">${ride.to_place || '-'}</p>
                                </div>
                            </div>
                            
                            <!-- New To Date -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-1 text-xs">To Date</label>
                                <div class="form-group">
                                    <input type="datetime-local" class="form-control form-control-sm trip-to-date" 
                                           data-trip-index="${index}" value="${formatDateTimeLocal(ride.to_date) || ''}">
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                });

                // Add action buttons for multiple trips
                editHtml += `
                <div class="grid grid-cols-12 gap-6 mt-4">
                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <div class="form-check form-check-md flex items-center mb-3">
                            <input class="form-check-input" type="checkbox" value="" id="no-date-checkbox">
                            <label class="form-check-label" for="no-date-checkbox">
                                No Date (Clear all dates)
                            </label>
                        </div>
                        <button type="button" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn me-2" id="save-dates-btn">Save Changes</button>
                        <button type="button" class="ti-btn ti-btn-outline-secondary ti-btn-wave" id="cancel-edit-btn">Cancel</button>
                    </div>
                </div>
            `;
            }

            container.html(editHtml);

            // Re-bind the no-date checkbox handler for the new elements
            $('#no-date-checkbox').on('change', function() {
                if ($(this).is(':checked')) {
                    $('.trip-from-date, .trip-to-date, #new-from-date, #new-to-date').prop('disabled', true).val(
                        '');
                } else {
                    $('.trip-from-date, .trip-to-date, #new-from-date, #new-to-date').prop('disabled', false);
                }
            });
        }

        function hideEditDatesForm() {
            $('#travel-edit-form').hide();
            $('#travel-info-display').show();
            $('#cancel-edit-btn').text('Edit Dates').attr('id', 'edit-dates-btn');
            // Clear form content
            $('#travel-edit-content').html('');
        }

        function saveDateChanges() {

            if (!currentRideId) {
                showError('No ride selected');
                return;
            }

            const rides = window.currentRidesData || [];
            const noDate = $('#no-date-checkbox').is(':checked');

            // current state logged in development only

            if (rides.length === 1) {
                // Single trip - original logic
                const fromDate = $('#new-from-date').val();
                const toDate = $('#new-to-date').val();

                // single trip dates

                if (!noDate && (!fromDate || !toDate)) {
                    showError('Please select both from and to dates or check "No Date"');
                    return;
                }

                const requestData = {
                    from_date: noDate ? null : fromDate,
                    to_date: noDate ? null : toDate,
                    no_date: noDate,
                    _token: '{{ csrf_token() }}'
                };

                // single trip request data

                $.ajax({
                    url: `{{ route('admin.rides.ride-status.update-dates', ':rideId') }}`.replace(':rideId',
                        currentRideId),
                    method: 'POST',
                    data: requestData,
                    beforeSend: function() {
                        // sending single trip update request
                    },
                    success: function(response) {
                        // single trip update response
                        if (response.success) {
                            showSuccess('Travel dates updated successfully');
                            hideEditDatesForm();
                            loadRideDetails(currentRideId);
                        } else {
                            showError('Failed to update dates: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        // single trip update error
                        let errorMessage = 'Error updating dates. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showError(errorMessage);
                    }
                });
            } else {
                // Multiple trips - collect all trip dates
                const tripDates = [];
                let hasErrors = false;

                // processing multiple trips

                rides.forEach((ride, index) => {
                    const fromDate = $(`.trip-from-date[data-trip-index="${index}"]`).val();
                    const toDate = $(`.trip-to-date[data-trip-index="${index}"]`).val();

                    // trip details collected

                    if (!noDate && (!fromDate || !toDate)) {
                        showError(`Please fill both dates for Trip Segment ${index + 1} or check "No Date"`);
                        hasErrors = true;
                        return false;
                    }

                    tripDates.push({
                        trip_id: ride.id,
                        from_date: noDate ? null : fromDate,
                        to_date: noDate ? null : toDate
                    });
                });

                if (hasErrors) return;

                const requestData = {
                    trip_dates: tripDates,
                    no_date: noDate,
                    multiple_trips: true,
                    _token: '{{ csrf_token() }}'
                };

                // multiple trips request data prepared

                $.ajax({
                    url: `{{ route('admin.rides.ride-status.update-dates', ':rideId') }}`.replace(':rideId',
                        currentRideId),
                    method: 'POST',
                    data: requestData,
                    beforeSend: function() {
                        // sending multiple trips update request
                    },
                    success: function(response) {
                        // multiple trips update response
                        if (response.success) {
                            showSuccess('Travel dates updated successfully for all trip segments');
                            hideEditDatesForm();
                            loadRideDetails(currentRideId);
                        } else {
                            showError('Failed to update dates: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        // multiple trips update error
                        let errorMessage = 'Error updating dates. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showError(errorMessage);
                    }
                });
            }
        }

        function generateInvoice() {
            if (!currentRideId) return;

            $.ajax({
                url: `{{ route('admin.rides.ride-status.generate-invoice', ':rideId') }}`.replace(':rideId',
                    currentRideId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Use modal for success message and redirect to invoice page
                        showSuccessModal('Success', 'Invoice generation initiated successfully.', function() {
                            if (response.redirect_url) {
                                window.location.href = response.redirect_url;
                            }
                        });
                    } else {
                        showErrorModal('Error', 'Failed to generate invoice: ' + response.message);
                    }
                },
                error: function(xhr) {
                    showErrorModal('Error', 'Error generating invoice. Please try again.');
                }
            });
        }

        function generateRefundNote() {
            if (!currentRideId) return;

            // Show loading state
            $('#refund-note-btn').prop('disabled', true).html(
                '<i class="ri-loader-2-line animate-spin me-2"></i>Processing...');

            $.ajax({
                url: `{{ route('admin.rides.ride-status.generate-refund', ':rideId') }}`.replace(':rideId',
                    currentRideId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to refund notes page with open_refund_id param
                        var followupId = response.followup_id || currentRideId;
                        var modalEl = document.getElementById('ride-details-modal');
                        var refundsUrl = modalEl ? modalEl.getAttribute('data-refunds-url') : null;
                        var redirectUrl = response.redirect_url || refundsUrl || '/admin/refunds';
                        window.location.href = redirectUrl + '?open_refund_id=' + encodeURIComponent(
                            followupId);
                    } else {
                        showError('Failed to process refund note: ' + response.message);
                    }
                },
                error: function(xhr) {
                    showError('Error processing refund note. Please try again.');
                },
                complete: function() {
                    $('#refund-note-btn').prop('disabled', false).html('Refund Note');
                }
            });
        }

        function showRefundInformationSection() {
            if (!currentRideId) return;

            // console.log('showRefundInformationSection called with:', {
            //     currentRideId: currentRideId,
            //     currentFollowupId: window.currentFollowupId
            // });

            // Hide refund note button and show refund section
            $('#refund-note-btn').hide();
            $('#refund-information-section').slideDown();

            // Get current ride data to populate the form
            // Use the stored data from populateRideDetails
            const totalPaidAmount = parseFloat($('#paid-amount').text().replace('₹', '').replace(/,/g, '')) || 0;

            // Use the current followup id if available (this is the LeadFollowup id), otherwise fall back to currentRideId
            // let followupId = window.currentFollowupId || currentRideId;

            // // If an existing refund is found and it's tied to a different followup, prefer that followup id
            // if (window.currentRefundData && window.currentRefundData.lead_followup_id) {
            //     followupId = window.currentRefundData.lead_followup_id;
            //     // keep the JS state consistent
            //     window.currentFollowupId = followupId;
            // }

            // // Set form values
            // $('#ride-followup-id').val(followupId);

            // ALWAYS use the latest followup (window.currentFollowupId = latest cancelled followup)
            // NEVER override with the refund's old lead_followup_id — that may be a non-cancelled followup
            // which causes "Ride must be cancelled to create refund" error
            const followupId = window.currentFollowupId || currentRideId;

            // Set form values
            $('#ride-followup-id').val(followupId);

            // If there is an existing refund saved for this followup, pre-fill the form with OLD VALUES
            if (window.currentRefundData) {
                const r = window.currentRefundData;
                
                // Use the OLD original_amount from the saved refund data
                const oldOriginalAmount = parseFloat(r.original_amount) || totalPaidAmount;
                $('#ride-original-amount').val(oldOriginalAmount.toFixed(2));
                
                // Use the OLD refund_amount from the saved refund data
                //$('#ride-refund-amount').val((parseFloat(r.refund_amount) || oldOriginalAmount).toFixed(2));
                // Use the OLD refund_amount from the saved refund data (0 is valid, don't use || fallback)
                const savedRefundAmt = (r.refund_amount !== null && r.refund_amount !== undefined && r.refund_amount !== '')
                    ? parseFloat(r.refund_amount)
                    : oldOriginalAmount;
                $('#ride-refund-amount').val(savedRefundAmt.toFixed(2));
                
                // Use the OLD refund_type from the saved refund data
                $('#ride-refund-type').val(r.refund_type || '');
                
                // Use the OLD refund_date from the saved refund data (format to yyyy-MM-dd for date input)
                try {
                    const formattedRefundDate = formatDateForInput(r.refund_date) || new Date().toISOString().split('T')[0];
                    $('#ride-refund-date').val(formattedRefundDate);
                } catch (e) {
                    $('#ride-refund-date').val(new Date().toISOString().split('T')[0]);
                }
                
                // Use the OLD refund_reason from the saved refund data
                $('#ride-refund-reason').val(r.refund_reason || '');

                // Show the OLD refund proof if exists
                if (r.refund_proof) {
                    const storageUrl = `{{ asset('storage') }}/${r.refund_proof}`;
                    $('#ride-preview-proof-btn').data('preview-url', storageUrl);
                    $('#ride-proof-filename').text(r.refund_proof.split('/').pop());
                    $('#ride-proof-hint').text('Uploaded file — click Preview to open in a new tab.');
                    $('#ride-preview-proof-btn').show();
                } else {
                    $('#ride-preview-proof-btn').hide();
                    $('#ride-proof-filename').text('No file selected');
                    $('#ride-proof-hint').text('');
                }

                // Set max attribute for refund amount using the OLD original amount
                const maxAmt = parseFloat(oldOriginalAmount.toFixed(2));
                $('#ride-refund-amount').attr('max', maxAmt.toFixed(2));
                const currentVal = parseFloat($('#ride-refund-amount').val()) || 0;
                if (currentVal > maxAmt) {
                    $('#ride-refund-amount').val(maxAmt.toFixed(2));
                }
            } else {
                // No existing refund - set defaults with current values
                $('#ride-original-amount').val(totalPaidAmount.toFixed(2));
                $('#ride-refund-amount').val(totalPaidAmount.toFixed(2));
                $('#ride-refund-type').val('');
                $('#ride-refund-date').val(new Date().toISOString().split('T')[0]);
                $('#ride-refund-reason').val('');
                $('#ride-preview-proof-btn').hide();
                $('#ride-proof-filename').text('No file selected');
                $('#ride-proof-hint').text('');
                $('#ride-refund-amount').attr('max', totalPaidAmount.toFixed(2));
            }
        }

        function hideRefundInformationSection() {
            $('#refund-information-section').slideUp();
            $('#refund-note-btn').show();
            
            // Clear form
            $('#ride-refund-form')[0].reset();
            // Revoke any temporary object URL used for preview
            if (window.currentRefundObjectUrl) {
                try { URL.revokeObjectURL(window.currentRefundObjectUrl); } catch (e) { }
                window.currentRefundObjectUrl = null;
            }
            // Clear preview data attribute
            $('#ride-preview-proof-btn').data('preview-url', '').hide();
        }

        // function saveRideRefundData() {
        //     // Check if we have a valid ride ID
        //     if (!currentRideId) {
        //         showError('Error: Ride ID not found. Please close and reopen the modal.');
        //         console.error('currentRideId is not set:', currentRideId);
        //         return;
        //     }

        //     // Check if we have a valid followup ID
        //     const followupId = $('#ride-followup-id').val();
        //     if (!followupId) {
        //         showError('Error: Followup ID not found. Please close and reopen the modal.');
        //         console.error('followup_id is not set:', followupId);
        //         return;
        //     }

        //     // Client-side validation: ensure refund_amount <= original_amount
        //     try {
        //         const originalAmt = parseFloat($('#ride-original-amount').val()) || 0;
        //         const refundAmt = parseFloat($('#ride-refund-amount').val()) || 0;
                
        //         if (refundAmt > originalAmt) {
        //             showError('Refund amount cannot be greater than the original amount.');
        //             return;
        //         }
                
        //         // Validate required fields for Accounts only
        //         if (isAccounts) {
        //             const refundType = $('#ride-refund-type').val();
        //             const refundDate = $('#ride-refund-date').val();
        //             const refundProof = $('#ride-refund-proof')[0].files[0];

        //             if (!refundType || !refundDate || !refundProof) {
        //                 showError('Please fill in all required fields: Refund Type, Refund Date, and Refund Proof.');
        //                 return;
        //             }
        //         }
        //     } catch (e) {
        //         console.warn('Error during client-side refund validation', e);
        //     }

        //     const formData = new FormData($('#ride-refund-form')[0]);
        //     formData.append('_token', '{{ csrf_token() }}');

        //     // Submitting refund (debug logging removed)

        //     $.ajax({
        //         url: "{{ route('admin.rides.ride-status.save-refund', ':rideId') }}".replace(':rideId', currentRideId),
        //         method: 'POST',
        //         data: formData,
        //         processData: false,
        //         contentType: false,
        //         beforeSend: function() {
        //             $('#ride-refund-form button[type="submit"]').prop('disabled', true).html(
        //                 '<i class="ri-loader-2-line animate-spin me-2"></i>Saving...'
        //             );
        //         },
        //         // success: function(response) {
        //         //     if (response.success) {
        //         //         // Show success modal but DO NOT redirect; stay on ride modal.
        //         //         showSuccessModal('Success', response.message || 'Refund information saved successfully', function() {
        //         //             // On close: hide the refund information section and reload ride details for updated state
        //         //             try {
        //         //                 $('#refund-information-section').hide();
        //         //             } catch (e) {
        //         //                 // ignore
        //         //             }
        //         //             if (currentRideId) {
        //         //                 // reload ride details to pick up saved refund values
        //         //                 loadRideDetails(currentRideId);
        //         //             }
        //         //         });
        //         //     } else {
        //         //         showError(response.message || 'Error saving refund data');
        //         //     }
        //         // },

        //         success: function(response) {
        //             if (response.success) {
        //                 // Collect form values to display in the popup
        //                 const originalAmt = parseFloat($('#ride-original-amount').val()) || 0;
        //                 const refundAmt   = parseFloat($('#ride-refund-amount').val())   || 0;
        //                 const refundType  = $('#ride-refund-type').val()  || '-';
        //                 const refundDate  = $('#ride-refund-date').val()  || '-';
        //                 const refundReason = $('#ride-refund-reason').val() || '-';
        //                 const clientName  = $('#modal-client-name').text() || '-';

        //                 // Proof file info
        //                 const fileInput = $('#ride-refund-proof')[0];
        //                 const proofFile = fileInput && fileInput.files && fileInput.files[0];
        //                 const proofName = proofFile ? proofFile.name : ($('#ride-proof-filename').text() !== 'No file selected' ? $('#ride-proof-filename').text() : null);
        //                 const previewUrl = $('#ride-preview-proof-btn').data('preview-url') || null;

        //                 // Format date nicely
        //                 let formattedDate = refundDate;
        //                 try {
        //                     if (refundDate && refundDate !== '-') {
        //                         const d = new Date(refundDate);
        //                         if (!isNaN(d)) formattedDate = d.toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' });
        //                     }
        //                 } catch(e) {}

        //                 // Populate modal fields
        //                 $('#refund-modal-client-name').text(clientName);
        //                 $('#refund-modal-original-amount').text('₹' + originalAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        //                 $('#refund-modal-refund-amount').text('₹' + refundAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 }));
        //                 $('#refund-modal-refund-type').text(refundType || '-');
        //                 $('#refund-modal-refund-date').text(formattedDate);
        //                 $('#refund-modal-refund-reason').text(refundReason || 'No reason provided');

        //                 if (proofName) {
        //                     $('#refund-modal-proof-name').text(proofName);
        //                     $('#refund-modal-proof-row').show();
        //                 } else {
        //                     $('#refund-modal-proof-name').text('No file uploaded');
        //                     $('#refund-modal-proof-row').show();
        //                 }

        //                 // Preview button
        //                 if (previewUrl) {
        //                     $('#refund-modal-preview-btn').show().off('click').on('click', function() {
        //                         window.open(previewUrl, '_blank');
        //                     });
        //                 } else {
        //                     $('#refund-modal-preview-btn').hide();
        //                 }

        //                 // Show refund success modal (same pattern as status-change-modal)
        //                 const refundModal = document.getElementById('refund-success-modal');
        //                 if (refundModal) {
        //                     refundModal.classList.remove('hidden');
        //                     refundModal.classList.add('open');
        //                     const backdrop = document.createElement('div');
        //                     backdrop.className = 'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
        //                     backdrop.id = 'refund-modal-backdrop';
        //                     backdrop.onclick = function() {
        //                         refundModal.classList.add('hidden');
        //                         refundModal.classList.remove('open');
        //                         backdrop.remove();
        //                         document.body.style.overflow = '';
        //                         try { $('#refund-information-section').hide(); } catch(e) {}
        //                         if (currentRideId) loadRideDetails(currentRideId);
        //                     };
        //                     document.body.appendChild(backdrop);
        //                     document.body.style.overflow = 'hidden';
        //                 }

        //                 // When modal's Close button is clicked, also reload ride details
        //                 // (the inline onclick handles visual close; wire up post-close reload too)
        //                 $('#refund-success-modal').one('click', '.ti-btn-outline-secondary', function() {
        //                     setTimeout(function() {
        //                         try { $('#refund-information-section').hide(); } catch(e) {}
        //                         if (currentRideId) loadRideDetails(currentRideId);
        //                     }, 150);
        //                 });

        //             } else {
        //                 showError(response.message || 'Error saving refund data');
        //             }
        //         },
        //         error: function(xhr) {
        //             let errorMessage = 'Error saving refund data';
                    
        //             if (xhr.status === 422) {
        //                 const errors = xhr.responseJSON?.errors;
        //                 if (errors) {
        //                     errorMessage = Object.values(errors).flat().join(', ');
        //                 } else {
        //                     errorMessage = xhr.responseJSON?.message || 'Validation failed. Please check the form.';
        //                 }
        //             } else if (xhr.responseJSON?.message) {
        //                 errorMessage = xhr.responseJSON.message;
        //             }
                    
        //             showError(errorMessage);
        //         },
        //         complete: function() {
        //             $('#ride-refund-form button[type="submit"]').prop('disabled', false).html('Save Changes');
        //         }
        //     });
        // }
        function saveRideRefundData() {
            if (!currentRideId) {
                showError('Error: Ride ID not found. Please close and reopen the modal.');
                return;
            }

            const followupId = $('#ride-followup-id').val();
            if (!followupId) {
                showError('Error: Followup ID not found. Please close and reopen the modal.');
                return;
            }

            // Client-side validation
            // try {
                // const originalAmt = parseFloat($('#ride-original-amount').val()) || 0;
                // const refundAmt   = parseFloat($('#ride-refund-amount').val())   || 0;

                // if (refundAmt > originalAmt) {
                //     showError('Refund amount cannot be greater than the original amount.');
                //     return;
                // }

                // if (isAccounts) {
                //     const refundType  = $('#ride-refund-type').val();
                //     const refundDate  = $('#ride-refund-date').val();
                //     const refundProof = $('#ride-refund-proof')[0].files[0];
                //     if (!refundType || !refundDate || !refundProof) {
                //         showError('Please fill in all required fields: Refund Type, Refund Date, and Refund Proof.');
                //         return;
                //     }
                // }
            // } catch (e) {
            //     console.warn('Validation error', e);
            // }

            // ── Client-side validation (runs BEFORE popup opens) ──────────────────────
            try {
                const originalAmt = parseFloat($('#ride-original-amount').val()) || 0;
                const refundAmt   = parseFloat($('#ride-refund-amount').val())   || 0;
                const refundType  = $('#ride-refund-type').val();
                const refundDate  = $('#ride-refund-date').val();
                const newFileSelected  = $('#ride-refund-proof')[0].files && $('#ride-refund-proof')[0].files[0];
                const existingProofShown = $('#ride-proof-filename').text().trim() !== ''
                                            && $('#ride-proof-filename').text().trim() !== 'No file selected';
                const hasProof = !!(newFileSelected || existingProofShown);

                // 1. Refund amount check — for everyone
                const refundAmtRaw = $('#ride-refund-amount').val();
                if (refundAmtRaw === '' || refundAmtRaw === null || isNaN(refundAmt) || refundAmt < 0) {
                    showError('Please enter a valid Refund Amount.');
                    return;
                }

                if (refundAmt > originalAmt) {
                    showError('Refund amount cannot be greater than the original amount.');
                    return;
                }

                // 2. Refund Type, Date, Proof — required for Admin + Accounts
                //    (Operations can save without these — no email/WA sent for them anyway)
                if (isAdmin || isAccounts) {
                    const missing = [];
                    if (!refundType)  missing.push('Refund Type');
                    if (!refundDate)  missing.push('Refund Date');
                    if (!hasProof)    missing.push('Refund Proof');

                    if (missing.length > 0) {
                        showError('Please fill in all required fields: ' + missing.join(', ') + '.');
                        return;
                    }
                }

            } catch (e) {
                console.warn('Validation error', e);
            }

            // ── Collect form values for the preview popup ──────────────────────
            const originalAmt  = parseFloat($('#ride-original-amount').val()) || 0;
            const refundAmt    = parseFloat($('#ride-refund-amount').val())   || 0;
            const refundType   = $('#ride-refund-type').val()  || '-';
            const refundDate   = $('#ride-refund-date').val()  || '-';
            const refundReason = $('#ride-refund-reason').val() || '';
            const clientName   = $('#modal-client-name').text() || '-';

            const fileInput = $('#ride-refund-proof')[0];
            const proofFile = fileInput && fileInput.files && fileInput.files[0];
            const proofName = proofFile
                ? proofFile.name
                : ($('#ride-proof-filename').text() !== 'No file selected'
                    ? $('#ride-proof-filename').text()
                    : null);
            const previewUrl = $('#ride-preview-proof-btn').data('preview-url') || null;

            // Format date nicely for display
            let formattedDate = refundDate;
            try {
                if (refundDate && refundDate !== '-') {
                    const d = new Date(refundDate);
                    if (!isNaN(d)) {
                        formattedDate = d.toLocaleDateString('en-GB', {
                            day: '2-digit', month: 'short', year: 'numeric'
                        });
                    }
                }
            } catch (e) {}

            // ── Populate review modal ──────────────────────────────────────────
            $('#refund-modal-client-name').text(clientName);
            $('#refund-modal-original-amount').text(
                '₹' + originalAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 })
            );
            $('#refund-modal-refund-amount').text(
                '₹' + refundAmt.toLocaleString('en-IN', { minimumFractionDigits: 2 })
            );
            $('#refund-modal-refund-type').text(refundType || '-');
            $('#refund-modal-refund-date').text(formattedDate);
            $('#refund-modal-refund-reason').text(refundReason || 'No reason provided');
            $('#refund-modal-proof-name').text(proofName || 'No file uploaded');

            // Preview proof button
            if (previewUrl) {
                $('#refund-modal-preview-btn').show().off('click').on('click', function () {
                    window.open(previewUrl, '_blank');
                });
            } else {
                $('#refund-modal-preview-btn').hide();
            }

            // Reset status indicators
            $('#refund-modal-saving-indicator, #refund-modal-saved-indicator').hide();
            $('#refund-modal-ok-btn').prop('disabled', false).html(
                '<i class="ri-checkbox-circle-line me-2"></i>OK, Confirm'
            );

            // ── Open review modal ──────────────────────────────────────────────
            // openRefundSuccessModal();

            // ── Open review modal ONLY for Admin/Accounts — Operations saves directly ──
// if (isAdmin || isAccounts) {
//     openRefundSuccessModal();
// } else {
//     // Operations: skip popup, save directly
//     const $saveBtn = $('#ride-refund-form button[type="submit"]');
//     $saveBtn.prop('disabled', true).html('<i class="ri-loader-2-line animate-spin me-2"></i>Saving...');

//     const fd = new FormData($('#ride-refund-form')[0]);
//     fd.append('_token', '{{ csrf_token() }}');

//     $.ajax({
//         url: "{{ route('admin.rides.ride-status.save-refund', ':rideId') }}"
//             .replace(':rideId', currentRideId),
//         method: 'POST',
//         data: fd,
//         processData: false,
//         contentType: false,
//         success: function (response) {
//             if (response.success) {
//                 showSuccess('Refund information saved successfully.');
//                 try { $('#refund-information-section').hide(); } catch(e) {}
//                 if (currentRideId) loadRideDetails(currentRideId);
//             } else {
//                 showError(response.message || 'Error saving refund data');
//             }
//         },
//         error: function (xhr) {
//             let errorMessage = 'Error saving refund data';
//             if (xhr.status === 422) {
//                 const errors = xhr.responseJSON?.errors;
//                 errorMessage = errors
//                     ? Object.values(errors).flat().join(', ')
//                     : (xhr.responseJSON?.message || 'Validation failed.');
//             } else if (xhr.responseJSON?.message) {
//                 errorMessage = xhr.responseJSON.message;
//             }
//             showError(errorMessage);
//         },
//         complete: function () {
//             $saveBtn.prop('disabled', false).html('Save Changes');
//         }
//     });
// }

// ── Save FIRST, then open popup (Admin/Accounts) or reload (Operations) ──
const $saveBtn = $('#ride-refund-form button[type="submit"]');
$saveBtn.prop('disabled', true).html('<i class="ri-loader-2-line animate-spin me-2"></i>Saving...');

const fd = new FormData($('#ride-refund-form')[0]);
fd.append('_token', '{{ csrf_token() }}');

$.ajax({
    url: "{{ route('admin.rides.ride-status.save-refund', ':rideId') }}".replace(':rideId', currentRideId),
    method: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    success: function(response) {
        $saveBtn.prop('disabled', false).html('Save Changes');
        if (response.success) {
            if (isAdmin || isAccounts) {
                // Bind Edit button inside success only
                $('#refund-modal-edit-btn').off('click').on('click', function() {
                    closeRefundSuccessModal();
                    $('#refund-information-section').slideDown();
                });
                // Reset popup indicators
                $('#refund-modal-saving-indicator, #refund-modal-saved-indicator').hide();
                $('#refund-modal-ok-btn').prop('disabled', false).html(
                    '<i class="ri-checkbox-circle-line me-2"></i>OK, Confirm'
                );
                // Bind OK button inside success — sends email/WA then redirects
                $('#refund-modal-ok-btn').off('click').on('click', function() {
                    const $okBtn = $(this);
                    lockRefundModal();
                    // Unbind Edit so clicking it does nothing while sending
                    //$('#refund-modal-edit-btn').off('click');
                    // Disable Edit button visually and functionally while sending
                    // Disable Edit button visually and functionally while sending
                    $('#refund-modal-edit-btn')
                        .off('click')
                        .prop('disabled', true)
                        .attr('disabled', 'disabled')
                        .css({
                            'opacity': '0.4',
                            'cursor': 'not-allowed',
                            'pointer-events': 'none',
                            'filter': 'grayscale(1)',
                            'background-color': '#9ca3af',
                            'border-color': '#9ca3af',
                            'color': '#fff'
                        });
                    
                    $okBtn.prop('disabled', true).html(
                        '<i class="ri-loader-2-line animate-spin me-2"></i>Sending emails...'
                    );
                    
                    $('#refund-modal-saving-indicator').show();
                    $('#refund-modal-saved-indicator').hide();
                    $.ajax({
                        url: "{{ route('admin.rides.ride-status.send-refund-email', ':rideId') }}"
                            .replace(':rideId', currentRideId),
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                        success: function(emailResp) {
                            $('#refund-modal-saving-indicator').hide();
                            $('#refund-modal-saved-indicator')
                                .html('<i class="ri-checkbox-circle-line me-1"></i>Refund saved & emails sent!')
                                .show();
                            $okBtn.prop('disabled', true).html('<i class="ri-checkbox-circle-line me-2"></i>Done!');
                        },
                        error: function(xhr) {
                            $('#refund-modal-saving-indicator').hide();
                            const emailErr = xhr.responseJSON?.message || 'Email sending failed.';
                            $('#refund-modal-saved-indicator')
                                .html('<i class="ri-checkbox-circle-line me-1 text-warning"></i>Refund saved. <span class="text-warning">Email error: ' + emailErr + '</span>')
                                .show();
                            $okBtn.prop('disabled', true).html('<i class="ri-checkbox-circle-line me-2"></i>Saved');
                        },
                        complete: function() {
                            // Redirect Admin/Accounts to Refund Notes (pending tab)
                            setTimeout(function() {
                                closeRefundSuccessModal();
                                try { $('#refund-information-section').hide(); } catch(e) {}
                                const followupParam = encodeURIComponent($('#ride-followup-id').val() || '');
                                const baseUrl = "{{ route('admin.refunds.index') }}";
                                window.location.href = baseUrl + (followupParam ? '?open_refund_id=' + followupParam : '');
                            }, 1600);
                        }
                    });
                });
                // Open popup AFTER all bindings are ready
                openRefundSuccessModal();
            } else {
                // Operations: save succeeded — no popup, just reload
                showSuccess('Refund information saved successfully.');
                try { $('#refund-information-section').hide(); } catch(e) {}
                if (currentRideId) loadRideDetails(currentRideId);
            }
        } else {
            showError(response.message || 'Error saving refund data');
        }
    },
    error: function(xhr) {
        $saveBtn.prop('disabled', false).html('Save Changes');
        let errorMessage = 'Error saving refund data';
        if (xhr.status === 422) {
            const errors = xhr.responseJSON?.errors;
            errorMessage = errors
                ? Object.values(errors).flat().join(', ')
                : (xhr.responseJSON?.message || 'Validation failed.');
        } else if (xhr.responseJSON?.message) {
            errorMessage = xhr.responseJSON.message;
        }
        showError(errorMessage);
    }
});

        } // end saveRideRefundData

// ── Helpers to open/close the refund review modal ─────────────────────────
function openRefundSuccessModal() {
    const modal = document.getElementById('refund-success-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    // Backdrop
    if (!document.getElementById('refund-modal-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className =
            'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
        backdrop.id = 'refund-modal-backdrop';
        backdrop.onclick = function () { closeRefundSuccessModal(); };
        document.body.appendChild(backdrop);
    }
}

function closeRefundSuccessModal() {
    const modal = document.getElementById('refund-success-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('open');
    }
    const backdrop = document.getElementById('refund-modal-backdrop');
    if (backdrop) backdrop.remove();
    document.body.style.overflow = '';
}
        function showLoadingState() {
            $('#modal-client-name').text('Loading...');
            // Show loading indicators for all fields
            $('#client-name, #client-email, #client-phone, #client-whatsapp, #client-country, #client-city, #client-address')
                .text('Loading...');
            $('#travel-from-date, #travel-from-place, #travel-to-date, #travel-to-place').text('Loading...');
            $('#service-name, #extra-services').text('Loading...');
            $('#total-amount, #paid-amount, #balance-amount').text('Loading...');
            $('#payment-progress-bar').css('width', '0%');
            $('#payment-progress-text').text('0%');
            $('#payment-history-container').html(
                '<div class="text-center py-4"><p class="text-gray-500">Loading payment history...</p></div>');
        }

        function formatDate(dateString) {
            if (!dateString) return null;
            try {
                // Handle the format from your API: "11-07-2025 12:00"
                let date;
                if (dateString.includes('-') && dateString.includes(' ')) {
                    // Parse DD-MM-YYYY HH:MM format
                    const [datePart, timePart] = dateString.split(' ');
                    const [day, month, year] = datePart.split('-');
                    const [hour, minute] = timePart.split(':');
                    date = new Date(year, month - 1, day, hour, minute);
                } else {
                    date = new Date(dateString);
                }

                if (isNaN(date.getTime())) {
                    return dateString; // Return original if parsing fails
                }

                return date.toLocaleDateString('en-GB', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }) + ' ' + date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return dateString;
            }
        }

        function formatDateTimeLocal(dateString) {
            if (!dateString) return '';
            try {
                let date;
                if (dateString.includes('-') && dateString.includes(' ')) {
                    // Parse DD-MM-YYYY HH:MM format
                    const [datePart, timePart] = dateString.split(' ');
                    const [day, month, year] = datePart.split('-');
                    const [hour, minute] = timePart.split(':');
                    date = new Date(year, month - 1, day, hour, minute);
                } else {
                    date = new Date(dateString);
                }

                if (isNaN(date.getTime())) {
                    return '';
                }

                return date.toISOString().slice(0, 16);
            } catch (e) {
                return '';
            }
        }

        // Format various date string shapes into yyyy-MM-dd for HTML date inputs
        function formatDateForInput(dateString) {
            if (!dateString) return '';
            try {
                let s = String(dateString).trim();

                // If server returns "DD-MM-YYYY HH:MM" format
                if (s.includes('-') && s.includes(' ')) {
                    const [datePart] = s.split(' ');
                    const parts = datePart.split('-');
                    if (parts.length === 3) {
                        const [day, month, year] = parts;
                        return `${year}-${String(month).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
                    }
                }

                // Strip microseconds like .000000 before Z which some browsers don't parse
                s = s.replace(/\.\d+Z$/, 'Z');

                // Try native Date parse
                const d = new Date(s);
                if (!isNaN(d.getTime())) {
                    const yyyy = d.getFullYear();
                    const mm = String(d.getMonth() + 1).padStart(2, '0');
                    const dd = String(d.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                }

                return '';
            } catch (e) {
                console.warn('formatDateForInput failed for', dateString, e);
                return '';
            }
            }

        function showSuccess(message) {
            // Use the app's success modal if available, otherwise fallback to alert
            try {
                if (typeof showSuccessModal === 'function') {
                    // Pass a no-op onClose callback to prevent automatic reload
                    showSuccessModal('Success', message || 'Action completed successfully.', function() {
                        // no-op on close
                    });
                    return;
                }
            } catch (e) {
                // ignore and fallback
            }

            try {
                alert('✅ Success: ' + message);
            } catch (e) {
                // nothing else to do
            }
        }

        function showError(message) {
            // Prefer the app's error modal, fallback to friendly wrapper or alert
            try {
                if (typeof showErrorModal === 'function') {
                    showErrorModal('Error', message || 'An error occurred.');
                    return;
                }
                if (typeof showFriendlyError === 'function') {
                    showFriendlyError('Error', message || 'An error occurred.');
                    return;
                }
            } catch (e) {
                // ignore and fallback
            }

            try {
                alert('❌ Error: ' + message);
            } catch (e) {
                // nothing else to do
            }
        }

        // Function to update travel information with multiple rides
        function updateTravelInformation(rides) {
            const container = $('#travel-information-container');

            // updateTravelInformation called

            // Always clear the container first to prevent old content from showing
            container.empty();

            if (!rides || rides.length === 0) {
                console.log('No rides data, showing empty message'); // Debug log
                container.html(`
                <div class="text-center py-4">
                    <p class="text-gray-500">No travel information found</p>
                </div>
            `);
                return;
            }

            let travelHtml = '';

            // console.log('Number of rides:', rides.length); // Debug log

            if (rides.length === 1) {
                // Single trip - display in original format
                const ride = rides[0];
                travelHtml = `
                <div class="grid grid-cols-12 gap-6">
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                        <p class="text-gray-800 dark:text-white">${formatDate(ride.from_date) || '-'}</p>
                    </div>
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place</label>
                        <p class="text-gray-800 dark:text-white">${ride.from_place || '-'}</p>
                    </div>
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                        <p class="text-gray-800 dark:text-white">${formatDate(ride.to_date) || '-'}</p>
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
                    const fromDateTime = formatDate(ride.from_date);
                    const toDateTime = formatDate(ride.to_date);
                    const fromTime = ride.from_date ? getTimeFromDate(ride.from_date) : '';
                    const toTime = ride.to_date ? getTimeFromDate(ride.to_date) : '';
                    const isFirst = index === 0;
                    const accordionId = `trip-segment-accordion-${index}`;

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
                                        <p class="text-gray-600 text-xs">${fromDateTime || '-'}</p>
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
                                        <p class="text-gray-600 text-xs">${toDateTime || '-'}</p>
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
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Origin:</label>
                                <p class="text-gray-800 dark:text-white">${firstRide.from_place || '-'}</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Final Destination:</label>
                                <p class="text-gray-800 dark:text-white">${lastRide.to_place || '-'}</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Journey Time:</label>
                                <p class="text-gray-800 dark:text-white">${totalDuration}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }

            // console.log('Setting travel HTML, length:', travelHtml.length); // Debug log
            container.html(travelHtml);
            
            // Reinitialize HSAccordion for dynamically added content
            if (typeof HSAccordion !== 'undefined') {
                HSAccordion.autoInit();
            }
            // console.log('Travel information updated successfully'); // Debug log
        }

        // Helper function to get time from date string
        function getTimeFromDate(dateString) {
            if (!dateString) return '';
            try {
                let date;
                if (dateString.includes('-') && dateString.includes(' ')) {
                    const [datePart, timePart] = dateString.split(' ');
                    const [day, month, year] = datePart.split('-');
                    const [hour, minute] = timePart.split(':');
                    date = new Date(year, month - 1, day, hour, minute);
                } else {
                    date = new Date(dateString);
                }

                if (isNaN(date.getTime())) {
                    return '';
                }

                return date.toLocaleTimeString('en-GB', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            } catch (e) {
                return '';
            }
        }

        // Helper function to calculate duration between two dates
        function calculateDuration(fromDate, toDate) {
            if (!fromDate || !toDate) return '-';

            try {
                let startDate, endDate;

                // Parse both dates using the same logic
                if (fromDate.includes('-') && fromDate.includes(' ')) {
                    const [datePart, timePart] = fromDate.split(' ');
                    const [day, month, year] = datePart.split('-');
                    const [hour, minute] = timePart.split(':');
                    startDate = new Date(year, month - 1, day, hour, minute);
                } else {
                    startDate = new Date(fromDate);
                }

                if (toDate.includes('-') && toDate.includes(' ')) {
                    const [datePart, timePart] = toDate.split(' ');
                    const [day, month, year] = datePart.split('-');
                    const [hour, minute] = timePart.split(':');
                    endDate = new Date(year, month - 1, day, hour, minute);
                } else {
                    endDate = new Date(toDate);
                }

                if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                    return '-';
                }

                const diffMs = endDate - startDate;
                const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
                const diffMinutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));

                if (diffHours > 24) {
                    const days = Math.floor(diffHours / 24);
                    const hours = diffHours % 24;
                    return `${days}d ${hours}h ${diffMinutes}m`;
                } else {
                    return `${diffHours}h ${diffMinutes}m`;
                }
            } catch (e) {
                return '-';
            }
        }

        // Helper function to calculate total duration for journey summary
        function calculateTotalDuration(fromDate, toDate) {
            return calculateDuration(fromDate, toDate);
        }

        // Update view receipt button based on latest payment
        function updateViewReceiptButton(paymentHistory) {
            const receiptBtn = $('#view-receipt-btn');
            const paymentMethodBadge = $('#payment-method-badge');
            const paymentReceivedInfo = $('#payment-received-info');

            if (paymentHistory && paymentHistory.length > 0) {
                // Get the latest payment
                const latestPayment = paymentHistory[0];

                // Update receipt button
                if (latestPayment.file) {
                    receiptBtn.prop('disabled', false)
                        .attr('data-file', latestPayment.file)
                        .attr('data-payment-id', latestPayment.id);
                } else {
                    receiptBtn.prop('disabled', true)
                        .removeAttr('data-file')
                        .removeAttr('data-payment-id');
                }

                // Update payment method badge
                const paymentMethod = latestPayment.payment_method;
                paymentMethodBadge.text(paymentMethod);

                // Update payment received info
                if (latestPayment.created_at) {
                    const paymentDate = new Date(latestPayment.created_at);
                    const formattedDate = paymentDate.toLocaleDateString('en-GB', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    });
                    const formattedTime = paymentDate.toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit',
                        hour12: true
                    });
                    paymentReceivedInfo.text(`Received: ${formattedDate}, ${formattedTime}`);
                } else {
                    paymentReceivedInfo.text('Received: N/A');
                }
            } else {
                // No payment history
                receiptBtn.prop('disabled', true)
                    .removeAttr('data-file')
                    .removeAttr('data-payment-id');
                paymentMethodBadge.text('No Payment');
                paymentReceivedInfo.text('No payment received');
            }
        }

        // Handle receipt viewing
        $(document).on('click', '#view-receipt-btn', function() {
            const fileName = $(this).attr('data-file');
            const paymentId = $(this).attr('data-payment-id');

            // receipt file and payment id

            if (!fileName) {
                showError('No receipt file found for this payment');
                return;
            }

            viewReceipt(fileName, paymentId);
        });

        // Handle receipt viewing for individual payment history entries
        $(document).on('click', '.view-payment-receipt-btn', function() {
            const fileName = $(this).attr('data-file');
            const paymentId = $(this).attr('data-payment-id');

            if (!fileName) {
                showError('No receipt file found for this payment');
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
                // File name doesn't include the folder path, add it
                fileUrl = `/storage/followups/${fileName}`;
            }

            // opening file url

            // Open the file in a new tab/window
            window.open(fileUrl, '_blank');
        }
        function resetRefundSection() {
        const refundSection = document.getElementById('refund-information-section');
        if (refundSection) {
            refundSection.style.display = 'none';
        }
    }
    function lockRefundModal() {
    // Disable X button
    const xBtn = document.querySelector('#refund-success-modal .absolute.top-3');
    if (xBtn) {
        xBtn.__origOnclick = xBtn.onclick;
        xBtn.onclick = function(e) { e.stopPropagation(); return false; };
        xBtn.style.opacity = '0.3';
        xBtn.style.pointerEvents = 'none';
        xBtn.style.cursor = 'not-allowed';
    }
    // Disable backdrop click
    const backdrop = document.getElementById('refund-modal-backdrop');
    if (backdrop) {
        backdrop.onclick = null;
        backdrop.style.cursor = 'not-allowed';
    }
    // Disable Edit button
    $('#refund-modal-edit-btn').off('click').prop('disabled', true).css({
        'opacity': '0.4', 'cursor': 'not-allowed', 'pointer-events': 'none',
        'filter': 'grayscale(1)', 'background-color': '#9ca3af',
        'border-color': '#9ca3af', 'color': '#fff'
    });
}

function unlockRefundModal() {
    // Re-enable X button
    const xBtn = document.querySelector('#refund-success-modal .absolute.top-3');
    if (xBtn) {
        xBtn.onclick = xBtn.__origOnclick || null;
        xBtn.style.opacity = '';
        xBtn.style.pointerEvents = '';
        xBtn.style.cursor = '';
    }
    // Re-enable backdrop
    const backdrop = document.getElementById('refund-modal-backdrop');
    if (backdrop) {
        backdrop.onclick = function() { closeRefundSuccessModal(); };
        backdrop.style.cursor = '';
    }
}


   function showStatusLoader(status) {
    let title = 'Processing Status...';
    let subtitle = 'Please wait, do not close this window.';

    if (parseInt(status) === 2) {
        title = 'Cancelling Ride...';
        subtitle = 'Please wait while the ride is being cancelled.';
    } else if (parseInt(status) === 5) {
        title = 'Completing Ride...';
        subtitle = 'Please wait while the invoice is being generated.';
    } else if (parseInt(status) === 7) {
        title = 'Rescheduling Ride...';
        subtitle = 'Please wait while the ride is being rescheduled.';
    }

    document.getElementById('status-loader-title').textContent = title;
    document.getElementById('status-loader-subtitle').textContent = subtitle;

    const loader = document.getElementById('status-processing-loader');
    loader.style.display = 'flex';
    loader.style.zIndex = '999999999';
    loader.style.pointerEvents = 'all';
    document.body.style.pointerEvents = 'none';
}

function hideStatusLoader() {
    const loader = document.getElementById('status-processing-loader');
    loader.style.display = 'none';
    loader.style.zIndex = '-1';
    document.body.style.pointerEvents = '';
}

</script>
<style>
    #refund-modal-edit-btn[disabled],
    #refund-modal-edit-btn:disabled {
        opacity: 0.4 !important;
        cursor: not-allowed !important;
        pointer-events: none !important;
        filter: grayscale(1) !important;
        background-color: #9ca3af !important;
        border-color: #9ca3af !important;
        color: #fff !important;
    }
</style>

@endpush

{{-- Include success/error modals --}}
@include('admin.partials.modals.success-error-modals')
