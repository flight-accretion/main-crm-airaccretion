@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">

</div>
<!-- Page Header Close -->

<div class="grid grid-cols-12">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="hs-accordion-group">
                <div class="hs-accordion" id="refund-accordion">
                    <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                    <i class="las la-undo-alt"></i>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Refund Notes</h5>
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
                <form method="GET" action="{{ route('admin.refunds.index') }}" id="filter-form">
                    <div class="grid grid-cols-12 gap-4">
                        <!-- Client Name Filter -->
                        {{-- <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Name</label>
                            <input type="text" name="name" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by name..." value="{{ request('name') }}">
                        </div>

                        <!-- Email Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Email</label>
                            <input type="email" name="email" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by email..." value="{{ request('email') }}">
                        </div>

                        <!-- Phone Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Phone</label>
                            <input type="text" name="phone" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by phone..." value="{{ request('phone') }}">
                        </div>

                        <!-- Staff Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Staff/Rep</label>
                            <input type="text" name="staff" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by staff..." value="{{ request('staff') }}">
                        </div>

                        <!-- Service Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Service</label>
                            <select name="service_id" class="ti-form-input rounded-sm form-control-sm">
                                <option value="">All Services</option>
                                @isset($services)
                                @foreach($services as $service)
                                <option value="{{ $service->id }}" {{ request('service_id')==$service->id ? 'selected' :
                                    '' }}>
                                    {{ $service->service }}
                                </option>
                                @endforeach
                                @endisset
                            </select>
                        </div>

                        <!-- Product Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Product</label>
                            <input type="text" name="product" class="ti-form-input rounded-sm form-control-sm"
                                placeholder="Search by product..." value="{{ request('product') }}">
                        </div> --}}

                        <!-- Service Date Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm" name="service_date"
                                    value="{{ request('service_date') }}">
                            </div>
                        </div>

                        <!-- Refund Date Filter -->
                        {{-- <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm" name="refund_date"
                                    value="{{ request('refund_date') }}">
                            </div>
                        </div> --}}

                        <!-- Refund Status Filter -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Status</label>
                            <div class="input-group">
                                <select name="refund_status" class="form-control form-control-sm">
                                    <option value="pending" {{ (isset($selectedStatus) && $selectedStatus==='pending' )
                                        ? 'selected' : '' }}>Pending</option>
                                    <option value="complete" {{ (isset($selectedStatus) && $selectedStatus==='complete'
                                        ) ? 'selected' : '' }}>Completed</option>
                                </select>
                            </div>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="xl:col-span-2 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
                            <div class="flex gap-2">
                                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                    Apply Filters
                                </button>
                                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                    onclick="clearFilters()">
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

<!-- Refund List -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header justify-between flex">
                <div class="box-title">
                    Refund List
                </div>
            </div>
            <div class="box-body">
                <div class="box-body" id="refunds-table-container"
                    data-download-url-template="{{ route('admin.refunds.download', ':refundId') }}"
                    data-invoice-download-url-template="{{ route('admin.refunds.invoice.download', ':refundId') }}"
                    data-invoice-preview-url-template="{{ route('admin.refunds.invoice.preview', ':refundId') }}">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%"
                            data-empty-msg="No cancelled rides found for refund processing.">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th data-priority="1">S. No.</th>
                                    <th data-priority="2">Name</th>
                                    <th data-priority="3">Phone</th>
                                    <th data-priority="4">Invoice ID</th>
                                    <th data-priority="5">Service Date</th>
                                    <th data-priority="6">Original Amount</th>
                                    <th data-priority="7">Refund Amount</th>
                                    <th data-priority="8">Remarks</th>
                                    <th data-priority="9">Refund Date</th>
                                    <th data-priority="9" style="width: 200px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($refundsData as $index => $refund)
                                <tr>

                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td>{{ $refund['client_name'] }}</td>
                                    <td class="text-center">{{ $refund['contact_number'] }}</td>
                                    <td class="text-center">
                                        {{ $refund['invoice_id'] ?? '-' }}</td>
                                    <td class="text-center">
                                        {{ \Carbon\Carbon::parse($refund['service_date'])->format('Y-m-d') }}</td>
                                    <td class="text-center">₹{{ number_format($refund['original_amount'], 2) }}</td>
                                    <td class="text-center">₹{{ number_format($refund['refund_amount'], 2) }}</td>
                                    <td class="text-center">{{ $refund['remarks'] ?
                                        \Illuminate\Support\Str::limit($refund['remarks'], 80) : '-' }}</td>
                                    <td class="text-center">{{ $refund['refund_date'] ?? '-' }}</td>
                                    <td>
                                        <div class="flex gap-2">
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-refund-btn"
                                                data-ride-id="{{ $refund['followup_id'] }}" title="View Details">
                                                <i class="ri-eye-line"></i>
                                            </button>
                                            @if ($refund['has_refund'])
                                            <!-- <button type="button"
                                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full download-refund-btn"
                                                        data-refund-id="{{ $refund['refund_id'] }}" title="Download Proof">
                                                        <i class="ri-download-line"></i>
                                                    </button> -->
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full download-refund-invoice-btn"
                                                data-refund-id="{{ $refund['refund_id'] }}"
                                                title="Download Refund Invoice">
                                                <i class="ri-download-line"></i>
                                            </button>
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-secondary-full preview-refund-invoice-btn"
                                                data-refund-id="{{ $refund['refund_id'] }}"
                                                title="Preview Refund Invoice">
                                                <i class="ri-file-text-line"></i>
                                            </button>
                                            @if ($refund['refund_status'] != 'completed')
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full mark-done-btn"
                                                data-refund-id="{{ $refund['refund_id'] }}" title="Mark as Done">
                                                <i class="ri-check-line"></i>
                                            </button>
                                            @else
                                            <span class="badge bg-success/10 text-success">Done</span>
                                            @endif
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @empty

                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Preview Modal -->
    <div id="refund-preview-modal" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="las la-file-invoice-dollar"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1rem]">Refund Preview – <span
                                class="text-primary" id="modal-client-name">Loading...</span></h5>
                        <div class="flex gap-2">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700"
                                data-hs-overlay="#refund-preview-modal">
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
        <div class="ti-offcanvas-body">
            <!-- Ride Status -->
            <div class="box">
                <div class="box-body bg-gray-50">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride Status</label>
                            <span class="badge !rounded-full bg-danger/10 text-danger">Cancelled</span>

                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Progress</label>
                            <div class="flex items-center gap-x-3 whitespace-nowrap w-full mb-4">
                                <div class="ti-main-progress w-full progress bg-gray-200 dark:bg-bodybg">
                                    <div class="ti-main-progress-bar bg-primary text-xs text-white text-center"
                                        id="refund-progress-bar" style="width: 0%" role="progressbar" aria-valuenow="0"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div class="text-end">
                                    <span class="text-sm text-gray-800 dark:text-white" id="refund-progress-text">0%
                                        Complete</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Refund Information Form -->
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Refund Information</h5>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-secondary" id="cancel-btn"
                        style="display: none;">
                        Cancel
                    </button>
                </div>
                <div class="box-body bg-gray-50">
                    <form id="refund-form">
                        <input type="hidden" id="followup-id" name="followup_id">
                        @php
                        $currentRole = optional(auth()->user()->userType)->user_type;
                        $isAdmin = in_array($currentRole, \App\Models\UserType::ADMIN_ROLES ?? []);
                        $isAccounts = in_array($currentRole, \App\Models\UserType::ACCOUNTS_ROLES ?? []);
                        $isOperations = in_array($currentRole, \App\Models\UserType::OPERATIONS_ROLES ?? []);
                        @endphp
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Original Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="original-amount"
                                        name="original_amount" step="0.01" {{ ($isOperations || $isAdmin) ? ''
                                        : 'readonly' }}>
                                </div>
                                @if(!$isOperations && !$isAdmin)
                                <small class="text-muted">Original amount can be entered by Operations only.</small>
                                @endif
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="number" class="form-control" id="refund-amount" name="refund_amount"
                                        step="0.01" {{ ($isOperations || $isAdmin || $isAccounts) ? '' : 'readonly' }}>
                                </div>
                                @if(!$isOperations && !$isAdmin && !$isAccounts)
                                <small class="text-muted">Refund amount can be entered by Operations only.</small>
                                @endif
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Type</label>
                                <select class="form-control" id="refund-type" name="refund_type" {{ ($isAccounts ||
                                    $isAdmin) ? '' : 'disabled' }}>
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
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Date</label>
                                <input type="date" class="form-control" id="refund-date" name="refund_date" {{
                                    ($isAccounts || $isAdmin) ? '' : 'readonly' }}>
                                @if(!$isAccounts && !$isAdmin)
                                <small class="text-muted">Refund date can be entered by Accounts only.</small>
                                @endif
                            </div>
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Upload refund proof<span
                                        class="text-danger">*</span></label>
                                <div class="flex items-center gap-3">
                                    <input type="file" class="form-control" id="refund-proof" name="refund_proof"
                                        accept=".pdf,.jpg,.jpeg,.png" {{ ($isAccounts || $isAdmin) ? '' : 'disabled' }}>
                                    <div class="flex items-center gap-3">
                                        <button type="button" id="preview-proof-btn"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-outline-secondary"
                                            style="display: none;" title="Preview proof">
                                            <i class="ri-eye-line"></i>
                                        </button>
                                        @if($isAccounts || $isAdmin)
                                        <button type="button" id="remove-proof-btn"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-outline-danger"
                                            style="display: none;" title="Remove proof">
                                            <i class="ri-delete-bin-6-line"></i>
                                        </button>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-sm text-gray-500 mt-1 flex items-center gap-3">
                                    <div id="proof-filename">Refund_receipt_001.pdf</div>
                                    <small id="proof-hint" class="text-muted"></small>
                                </div>
                                <div class="text-sm text-gray-400 mt-1">Max file size: 2 MB. Allowed types: .pdf, .jpg,
                                    .jpeg, .png</div>
                                @if(!$isAccounts && !$isAdmin)
                                <small class="text-muted">Only Accounts can upload refund proof.</small>
                                @endif
                            </div>
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Reason</label>
                                <textarea class="form-control" id="refund-reason" name="refund_reason" rows="3" {{
                                    ($isOperations || $isAdmin || $isAccounts) ? '' : 'readonly' }}
                                    placeholder="Client requested partial refund due to service delay."></textarea>
                                @if(!$isOperations && !$isAdmin && !$isAccounts)
                                <small class="text-muted">Refund reason can be entered by Operations only.</small>
                                @endif
                            </div>
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Remarks</label>
                                <p id="refund-remarks" class="text-sm text-gray-700">-</p>
                            </div>
                            <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                <div class="flex gap-3">
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full">
                                        Save changes
                                    </button>
                                    <!-- <button type="button" id="preview-refund-invoice-modal-btn" 
                                            class="ti-btn ti-btn-secondary-full" style="display: none;"
                                            title="Preview Refund Invoice">
                                        <i class="ri-eye-line me-2"></i>Preview Invoice
                                    </button>
                                    <button type="button" id="download-refund-invoice-modal-btn" 
                                            class="ti-btn ti-btn-warning-full" style="display: none;"
                                            title="Download Refund Invoice">
                                        <i class="ri-file-text-line me-2"></i>Refund Invoice
                                    </button>
                                    <button type="button" id="download-btn" 
                                            class="ti-btn ti-btn-success-full" style="display: none;"
                                            title="Download Refund Proof">
                                        <i class="ri-download-line me-2"></i>Download Proof
                                    </button> -->
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Client Information -->
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Client Information</h5>
                </div>
                <div class="box-body bg-gray-50">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Email
                                Address</label>
                            <p class="text-gray-800 dark:text-white" id="client-email">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Phone Number</label>
                            <p class="text-gray-800 dark:text-white" id="client-phone">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Whatsapp
                                Number</label>
                            <p class="text-gray-800 dark:text-white" id="client-whatsapp">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Country</label>
                            <p class="text-gray-800 dark:text-white" id="client-country">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">City</label>
                            <p class="text-gray-800 dark:text-white" id="client-city">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Address</label>
                            <p class="text-gray-800 dark:text-white" id="client-address">-</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Travel Information -->
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Travel Information</h5>
                </div>
                <div class="box-body bg-gray-50" id="travel-info-container">
                    <!-- Dynamic content will be loaded here -->
                    <div class="text-center py-4">
                        <p class="text-gray-500">Loading travel information...</p>
                    </div>
                </div>
            </div>

            <!-- Service Information -->
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Service Information</h5>
                </div>
                <div class="box-body bg-gray-50">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Service</label>
                            <p class="text-gray-800 dark:text-white" id="service-name">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Cost Price</label>
                            <p class="text-gray-800 dark:text-white" id="service-cost">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Extra
                                Services</label>
                            <p class="text-gray-800 dark:text-white" id="extra-services">-</p>
                        </div>
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Profit/Loss</label>
                            <p class="text-success" id="profit-loss">-</p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Payment History -->
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Payment History</h5>
                </div>
                <div class="box-body bg-gray-50" id="payment-history-container">
                    <div class="text-center py-4">
                        <p class="text-gray-500">Loading payment history...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endsection
    <!-- Mark Refund As Done Modal -->
    <div id="refund-mark-done-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
        <div class="flex items-center justify-center min-h-screen w-full fixed inset-0 z-50"
            style="background: rgba(0,0,0,0.2);">
            <div class="ti-modal-box ti-modal-content bg-white rounded shadow-lg"
                style="max-width: 400px; width: 100%;">
                <div class="ti-modal-header">
                    <h6 class="modal-title text-[1rem] font-semibold">Confirm Mark as Done</h6>
                    <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                        data-hs-overlay="#refund-mark-done-modal">
                        <span class="sr-only">Close</span>
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="ti-modal-body px-4 py-4">
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="ri-question-line text-4xl text-info"></i>
                        </div>
                        <h5 class="text-lg font-semibold mb-2">Mark Refund as Done</h5>
                        <p class="text-gray-600 mb-4" id="refund-mark-done-message">
                            Are you sure you want to mark this refund as done?
                        </p>
                    </div>
                </div>
                <div class="ti-modal-footer">
                    <button type="button" class="ti-btn ti-btn-outline-secondary"
                        data-hs-overlay="#refund-mark-done-modal">
                        Cancel
                    </button>
                    <button type="button" class="ti-btn ti-btn-primary" id="confirm-mark-done">
                        Yes, Mark as Done
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Proof Preview Modal -->
    <div id="refund-proof-preview-modal" class="hs-overlay hidden ti-modal" style="z-index: 10000;">
        <div class="flex items-center justify-center min-h-screen w-full fixed inset-0 z-50"
            style="background: rgba(0,0,0,0.35);">
            <div class="ti-modal-box ti-modal-content bg-white rounded shadow-lg" style="max-width: 900px; width: 95%;">
                <div class="ti-modal-header flex items-center justify-between">
                    <h6 class="modal-title text-[1rem] font-semibold">Refund Proof Preview</h6>
                    <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                        data-hs-overlay="#refund-proof-preview-modal">
                        <span class="sr-only">Close</span>
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="ti-modal-body px-4 py-4">
                    <div id="proof-preview-content" style="min-height: 300px;">
                        <!-- dynamic preview -->
                    </div>
                </div>
                <div class="ti-modal-footer text-end">
                    <a id="proof-download-link" class="ti-btn ti-btn-outline-secondary" style="display:none;"
                        target="_blank">Open in new tab</a>
                    <button type="button" class="ti-btn ti-btn-primary"
                        data-hs-overlay="#refund-proof-preview-modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Refund Proof Remove Modal -->
    <div id="refund-proof-remove-modal" class="hs-overlay hidden ti-modal" style="z-index: 10001;">
        <div class="flex items-center justify-center min-h-screen w-full fixed inset-0 z-50"
            style="background: rgba(0,0,0,0.2);">
            <div class="ti-modal-box ti-modal-content bg-white rounded shadow-lg"
                style="max-width: 600px; width: 100%;">
                <div class="ti-modal-header">
                    <h6 class="modal-title text-[1rem] font-semibold">Remove Refund Proof</h6>
                    <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                        data-hs-overlay="#refund-proof-remove-modal">
                        <span class="sr-only">Close</span>
                        <i class="ri-close-line"></i>
                    </button>
                </div>
                <div class="ti-modal-body px-4 py-4">
                    <div class="text-center mb-4">
                        <i class="ri-question-line text-4xl text-danger"></i>
                    </div>
                    <p class="text-gray-600 mb-4">You are about to remove the uploaded refund proof. Please provide a
                        reason or remarks for audit purposes before proceeding.</p>
                    <div class="mb-3">
                        <textarea id="refund-proof-remove-remarks" class="form-control" rows="4"
                            placeholder="Enter remarks..."></textarea>
                        <input type="hidden" id="refund-proof-remove-id" />
                    </div>
                </div>
                <div class="ti-modal-footer">
                    <button type="button" class="ti-btn ti-btn-outline-secondary"
                        data-hs-overlay="#refund-proof-remove-modal">Cancel</button>
                    <button type="button" class="ti-btn ti-btn-danger" id="confirm-remove-proof">Remove Proof</button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function initSimpleTable() {
            $('.table-datatable').wrap('<div class="table-responsive"></div>');

            // Add search functionality
            $('#search-input').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('.table-datatable tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
        }
        let currentRefundData = null;
        // Maximum upload size guard (bytes). Keep this in sync with validation rules and server limits
        const MAX_PROOF_SIZE = 2 * 1024 * 1024; // 2 MB
        const isAccounts = @json($isAccounts);
        const isAdmin = @json($isAdmin);

        $(document).ready(function() {
            // Toggle filters
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

            // View refund details
            $(document).on('click', '.view-refund-btn', function() {
                const followupId = $(this).data('ride-id');
                loadRefundDetails(followupId);

                // Open modal using HSOverlay
                const modal = document.getElementById('refund-preview-modal');
                if (window.HSOverlay) {
                    window.HSOverlay.open(modal);
                } else {
                    // Fallback for when HSOverlay is not available
                    modal.classList.remove('hidden');
                    modal.classList.add('open');
                }
            });

            // Download refund note
            $(document).on('click', '.download-refund-btn', function() {
                const refundId = $(this).data('refund-id');
                downloadRefundNote(refundId);
            });

            // Download refund invoice
            $(document).on('click', '.download-refund-invoice-btn', function() {
                const refundId = $(this).data('refund-id');
                downloadRefundInvoice(refundId);
            });

            // Preview refund invoice
            $(document).on('click', '.preview-refund-invoice-btn', function() {
                const refundId = $(this).data('refund-id');
                previewRefundInvoice(refundId);
            });

            // Form submission
            $('#refund-form').on('submit', function(e) {
                e.preventDefault();
                saveRefundData();
            });

            // Keep refund-amount max in sync when original amount changes (operations may edit original)
            $('#original-amount').on('input change', function() {
                try {
                    const originalAmt = parseFloat($(this).val()) || 0;
                    const refundInput = $('#refund-amount');
                    refundInput.attr('max', originalAmt.toFixed(2));
                    const currentVal = parseFloat(refundInput.val()) || 0;
                    if (currentVal > originalAmt) {
                        refundInput.val(originalAmt.toFixed(2));
                    }
                } catch (e) {
                    console.warn('Error syncing refund amount max', e);
                }
            });

            // Ensure preview button hidden by default until a file is present
            $('#preview-proof-btn').hide();

            // Download from modal
            $('#download-btn').on('click', function() {
                if (currentRefundData && currentRefundData.refund) {
                    downloadRefundNote(currentRefundData.refund.id);
                }
            });

            // Download refund invoice from modal
            $('#download-refund-invoice-modal-btn').on('click', function() {
                if (currentRefundData && currentRefundData.refund) {
                    downloadRefundInvoice(currentRefundData.refund.id);
                }
            });

            // Preview refund invoice from modal
            $('#preview-refund-invoice-modal-btn').on('click', function() {
                if (currentRefundData && currentRefundData.refund) {
                    previewRefundInvoice(currentRefundData.refund.id);
                }
            });
        });

        // If the page was opened with a followup_id query param, auto-load and open its refund details
        (function () {
            try {
                var autoFollowupId = {!! json_encode(request('followup_id') ?: null) !!};
                if (autoFollowupId) {
                    // Delay slightly to allow the rest of the page to initialize
                    setTimeout(function () {
                        loadRefundDetails(autoFollowupId);
                    }, 250);
                }
            } catch (e) {
                // ignore
            }
        })();

        function loadRefundDetails(followupId) {
            // Show loading state
            showLoadingState();

            $.ajax({
                url: "{{ route('admin.refunds.show', ':followupId') }}".replace(':followupId', followupId),
                method: 'GET',
                success: function(response) {
                    if (response && (response.ride || response.client || response.followup)) {
                        currentRefundData = response;
                        populateRefundDetails(response);
                        // Ensure modal is opened after details are loaded so preview shows reliably
                        openRefundPreviewModal();
                    } else {
                        showError('Invalid response format from server');
                    }
                },
                error: function(xhr) {
                    showError('Error loading refund details: ' + (xhr.responseJSON?.message ||
                        'Unknown error'));
                }
            });
        }

        function populateRefundDetails(data) {
            // Update modal header
            console.log('Populating refund details:', data);
            $('#modal-client-name').text(data.client?.name || 'Unknown Client');

            // Update client information
            $('#client-email').text(data.client?.email || '-');
            $('#client-phone').text(data.client?.contact_number || '-');
            $('#client-whatsapp').text(data.client?.alternate_number || '-');
            $('#client-country').text(data.client?.country || '-');
            $('#client-city').text(data.client?.city || '-');
            $('#client-address').text(data.client?.address || '-');

            // Update travel information - handle multiple trips
            updateTravelInformation(data.all_rides || (data.ride ? [data.ride] : []));

            // Update service information
            $('#service-name').text(data.service_names || '-');
            $('#service-cost').text('₹' + (data.original_amount ? data.original_amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2
            }) : '0.00'));
            $('#extra-services').text(data.extra_service_names || '-');
            $('#profit-loss').text(''); // This would need to be calculated based on your business logic

            // Update refund form
            $('#followup-id').val(data.followup_id);
            $('#original-amount').val(data.original_amount || 0);

            if (data.refund) {
                // Existing refund - populate form
                $('#refund-amount').val(data.refund.refund_amount || 0);
                $('#refund-type').val(data.refund.refund_type || '');
                $('#refund-date').val(data.refund.refund_date || '');
                $('#refund-reason').val(data.refund.refund_reason || '');
                $('#refund-remarks').text(data.refund.remarks || '-');
                const existingProofPath = data.refund.refund_proof || null;
                $('#proof-filename').text(existingProofPath ? existingProofPath.split('/').pop() : 'No file uploaded');

                // If there is an existing uploaded proof, wire the Preview button to open it in a new tab
                if (existingProofPath) {
                    try {
                        const storageUrl = `{{ asset('storage') }}/${existingProofPath}`;
                        $('#preview-proof-btn').data('preview-url', storageUrl);
                        $('#preview-proof-btn').data('preview-name', existingProofPath.split('/').pop());
                        $('#preview-proof-btn').show();
                        $('#proof-hint').text('Uploaded file — click Preview to open in a new tab.');
                    } catch (e) {
                        console.warn('Could not wire preview button for existing proof', e);
                    }
                } else {
                    $('#preview-proof-btn').hide();
                }

                // Update progress
                const progress = data.refund.status >= 1 ? 100 : 50;
                $('#refund-progress-bar').css('width', progress + '%');
                $('#refund-progress-text').text(progress + '% Complete');

                // Show download button
                $('#download-btn').show();
                $('#download-refund-invoice-modal-btn').show();
                $('#preview-refund-invoice-modal-btn').show();
            } else {
                // New refund - set default values
                $('#refund-amount').val(data.received_amount || 0);
                $('#refund-type').val('');
                $('#refund-date').val(new Date().toISOString().split('T')[0]);
                $('#refund-reason').val('');
                $('#proof-filename').text('No file selected');

                // Update progress
                $('#refund-progress-bar').css('width', '0%');
                $('#refund-progress-text').text('0% Complete');

                // Hide download button
                $('#download-btn').hide();
                $('#download-refund-invoice-modal-btn').hide();
                $('#preview-refund-invoice-modal-btn').hide();
            }

            // Ensure refund amount cannot exceed original amount on the client side
            try {
                const originalAmt = parseFloat($('#original-amount').val()) || 0;
                const refundInput = $('#refund-amount');
                refundInput.attr('max', originalAmt.toFixed(2));

                // If current refund value exceeds original, clamp it
                const currentRefundVal = parseFloat(refundInput.val()) || 0;
                if (currentRefundVal > originalAmt) {
                    refundInput.val(originalAmt.toFixed(2));
                }
            } catch (e) {
                console.warn('Could not set refund amount max attribute', e);
            }

            // Load payment history
            loadPaymentHistory(data.payment_history || []);
            // Update preview button visibility now that currentRefundData is set
            updatePreviewButtonVisibility();
        }

        function openRefundPreviewModal() {
            const modal = document.getElementById('refund-preview-modal');
            if (!modal) return;
            try {
                if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                    window.HSOverlay.open(modal);
                } else {
                    modal.classList.remove('hidden');
                    modal.classList.add('open');
                }
            } catch (e) {
                // fallback: try to show by toggling classes
                try { modal.classList.remove('hidden'); modal.classList.add('open'); } catch (err) { /* ignore */ }
            }
        }

        function updateTravelInformation(rides) {
            const container = $('#travel-info-container');
            container.empty();

            if (!rides || rides.length === 0) {
                container.html(
                    '<div class="text-center py-4"><p class="text-gray-500">No travel information available.</p></div>');
                return;
            }

            let travelHtml = '';

            if (rides.length === 1) {
                // Single trip - simple display
                const ride = rides[0];
                travelHtml = `
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label mb-0 text-sm">From Date</label>
                        <p class="text-gray-800">${formatDate(ride.from_date) || '-'}</p>
                    </div>
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label mb-0 text-sm">From Place</label>
                        <p class="text-gray-800">${ride.from_place || '-'}</p>
                    </div>
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label mb-0 text-sm">To Date</label>
                        <p class="text-gray-800">${formatDate(ride.to_date) || '-'}</p>
                    </div>
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label mb-0 text-sm">To Place</label>
                        <p class="text-gray-800">${ride.to_place || '-'}</p>
                    </div>
                </div>
            `;
            } else {
                // Multiple trips - render as HS accordion with first open
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
                    const aid = `refund-notes-trip-${index}`;

                    travelHtml += `
                            <div class="hs-accordion ${isFirst ? 'active' : ''} border mb-2" id="${aid}-heading">
                                <button class="hs-accordion-toggle hs-accordion-active:text-primary bg-white border-b py-3 px-4 inline-flex items-center justify-between gap-x-3 w-full font-semibold text-start text-gray-800 hover:text-gray-500 disabled:opacity-50 disabled:pointer-events-none" aria-controls="${aid}-collapse">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 bg-theme text-white rounded-full flex items-center justify-center text-sm font-semibold">${index + 1}</div>
                                        <span>Trip Segment ${index + 1}: ${ride.from_place || '-'} → ${ride.to_place || '-'}</span>
                                            </div>
                                    <svg class="hs-accordion-active:hidden block size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                            <svg class="hs-accordion-active:block hidden size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                        </button>
                        <div id="${aid}-collapse" class="hs-accordion-content bg-white pt-3 ${isFirst ? '' : 'hidden'} w-full overflow-hidden transition-[height] duration-300" aria-labelledby="${aid}-heading">
                            <div class="p-4 pt-0">
                                    <div class="grid grid-cols-12 gap-4">
                                <div class="xl:col-span-3 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Departure</label>
                                    <div class="bg-white p-2 rounded border">
                                        <p class="text-gray-800 font-medium text-sm">${ride.from_place || '-'}</p>
                                        <p class="text-gray-600 text-xs">${fromDateTime || '-'}</p>
                                    </div>
                                </div>
                                <div class="xl:col-span-2 col-span-12 flex items-center justify-center">
                                    <div class="flex flex-col items-center">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-primary mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                        </svg>
                                        <span class="text-xs text-gray-500">Journey</span>
                                    </div>
                                </div>
                                <div class="xl:col-span-3 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Arrival</label>
                                    <div class="bg-white p-2 rounded border">
                                        <p class="text-gray-800 font-medium text-sm">${ride.to_place || '-'}</p>
                                        <p class="text-gray-600 text-xs">${toDateTime || '-'}</p>
                                    </div>
                                </div>
                                <div class="xl:col-span-4 col-span-12">
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

                // Add journey summary if multiple trips
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

            container.html(travelHtml);
            // Reinitialize HSAccordion for dynamically added content
            if (typeof HSAccordion !== 'undefined') {
                HSAccordion.autoInit();
            }
        }

        function loadPaymentHistory(paymentHistory) {
            const container = $('#payment-history-container');

            if (!paymentHistory || paymentHistory.length === 0) {
                container.html(
                    '<div class="text-center py-4"><p class="text-gray-500">No payment history found.</p></div>');
                return;
            }

            let historyHtml = '';
            paymentHistory.forEach(function(payment, index) {
                const amount = parseFloat(payment.amount || 0);
                const paymentDate = payment.created_at ? formatDate(payment.created_at) : 'N/A';
                const paymentMethod = payment.payment_method || 'Unknown';
                const statusBadge = getPaymentStatusBadge(payment.status);
                const createdBy = payment.created_by_name || 'System';
                const followupNote = payment.followup_note || '';
                historyHtml += `
                <div class="flex items-center ${index > 0 ? 'mt-4 pt-4 border-t border-gray-200' : ''}">
                    <div class="me-4 gap-0">
                        <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10" stroke="#fff" stroke-width="2" fill="#2B53A9" />
                                <path d="M8.5 12.5L11 15L16 9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between">
                            <div>
                                <h5 class="font-semibold mb-1 leading-none text-[1.25rem]">₹${amount.toLocaleString('en-IN', {minimumFractionDigits: 2})}</h5>
                                <p class="text-sm text-gray-600 mb-1">Method: <span class="font-semibold">${paymentMethod}</span></p>
                                <p class="text-sm text-gray-600 mb-1">Note: <span class="font-semibold">${followupNote}</span></p>
                            </div>
                            <div class="text-end">
                                ${statusBadge}
                                <p class="text-sm text-gray-600">Date: ${paymentDate}</p>
                                <p class="text-xs text-[#8c9097]">By: ${createdBy}</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            });

            container.html(historyHtml);
        }

        function saveRefundData() {
            // Client-side guard: ensure refund_amount <= original_amount
            try {
                const originalAmt = parseFloat($('#original-amount').val()) || 0;
                const refundAmt = parseFloat($('#refund-amount').val()) || 0;
                if (refundAmt > originalAmt) {
                    const msg = 'Refund amount cannot be greater than the original amount.';
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Validation Error', msg);
                    } else {
                        alert(msg);
                    }
                    return;
                }
            } catch (e) {
                console.warn('Error during client-side refund validation', e);
            }

            // Validate file size before sending to avoid server rejecting with 413
            const selectedFile = $('#refund-proof')[0] && $('#refund-proof')[0].files && $('#refund-proof')[0].files[0];
            if (selectedFile && selectedFile.size > MAX_PROOF_SIZE) {
                showErrorModal('Validation Error', 'Selected proof is larger than 2 MB. Please choose a smaller file.');
                return;
            }

            const formData = new FormData($('#refund-form')[0]);
            formData.append('_token', '{{ csrf_token() }}');

            $.ajax({
                url: "{{ route('admin.refunds.store') }}",
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#refund-form button[type="submit"]').prop('disabled', true).text('Saving...');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);

                        // Update progress
                        $('#refund-progress-bar').css('width', '100%');
                        $('#refund-progress-text').text('100% Complete');

                        // Show download button
                        $('#download-btn').show();
                        $('#download-refund-invoice-modal-btn').show();
                        $('#preview-refund-invoice-modal-btn').show();

                        // Reload page after delay
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showError(response.message || 'Error saving refund data');
                    }
                },
                error: function(xhr) {
                    // If server rejects due to content too large, show specific message
                    if (xhr.status === 413) {
                        showErrorModal('Upload Failed', 'The uploaded file is too large for the server to accept. Please reduce the file size to under 2 MB, or contact your administrator to increase server upload limits.');
                        return;
                    }
                    // If validation error (422), show friendly message and reopen the refund preview modal after user dismisses
                    if (xhr.status === 422) {
                        const msg = xhr.responseJSON?.message || 'Validation failed. Please check the form and try again.';
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Validation Error', msg, reopenRefundModal);
                        } else {
                            alert('Validation Error: ' + msg);
                            reopenRefundModal();
                        }
                    } else {
                        showError('Error saving refund data: ' + (xhr.responseJSON?.message || 'Unknown error'));
                    }
                },
                complete: function() {
                    $('#refund-form button[type="submit"]').prop('disabled', false).text('Save changes');
                }
            });
        }

        $(document).on('click', '.mark-done-btn', function() {
            pendingRefundDoneId = $(this).data('refund-id');
            showMarkDoneConfirmation();
        });

        function showMarkDoneConfirmation() {
            // Show the confirmation modal using HSOverlay
            const modal = document.getElementById('refund-mark-done-modal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.classList.add('open');
                // Add overlay backdrop
                const backdrop = document.createElement('div');
                backdrop.className =
                    'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
                backdrop.setAttribute('id', 'refund-mark-done-backdrop');
                backdrop.onclick = function() {
                    hideMarkDoneModal();
                };
                document.body.appendChild(backdrop);
                document.body.style.overflow = 'hidden';
            }
        }

        function hideMarkDoneModal() {
            const modal = document.getElementById('refund-mark-done-modal');
            const backdrop = document.getElementById('refund-mark-done-backdrop');
            if (modal) {
                modal.classList.add('hidden');
                modal.classList.remove('open');
            }
            if (backdrop) {
                backdrop.remove();
            }
            document.body.style.overflow = '';
            pendingRefundDoneId = null;
        }

        $(document).on('click', '[data-hs-overlay="#refund-mark-done-modal"]', function() {
            hideMarkDoneModal();
        });

        $(document).on('click', '#confirm-mark-done', function() {
            if (!pendingRefundDoneId) return;
            markRefundAsDone(pendingRefundDoneId);
            hideMarkDoneModal();
        });

        function markRefundAsDone(refundId) {
            $.ajax({
                url: "{{ route('admin.refunds.mark-done', ':refundId') }}".replace(':refundId', refundId),
                method: 'POST',
                data: {
                    _token: '{{ csrf_token() }}'
                },
                beforeSend: function() {
                    $(`.mark-done-btn[data-refund-id="${refundId}"]`).prop('disabled', true)
                        .html('<i class="ri-loader-2-line animate-spin"></i>');
                },
                success: function(response) {
                    if (response.success) {
                        showSuccess(response.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showError(response.message || 'Failed to mark refund as done');
                    }
                },
                error: function(xhr) {
                    showError('Error marking refund as done: ' + (xhr.responseJSON?.message ||
                        'Unknown error'));
                },
                complete: function() {
                    $(`.mark-done-btn[data-refund-id="${refundId}"]`).prop('disabled', false)
                        .html('<i class="ri-check-line"></i>');
                }
            });
        }

        function downloadRefundNote(refundId) {
            const container = document.getElementById('refunds-table-container');
            const template = container?.dataset?.downloadUrlTemplate || "{{ route('admin.refunds.download', ':refundId') }}";
            window.location.href = template.replace(':refundId', refundId);
        }

        function downloadRefundInvoice(refundId) {
            const container = document.getElementById('refunds-table-container');
            const template = container?.dataset?.invoiceDownloadUrlTemplate || "{{ route('admin.refunds.invoice.download', ':refundId') }}";
            window.location.href = template.replace(':refundId', refundId);
        }

        function previewRefundInvoice(refundId) {
            const container = document.getElementById('refunds-table-container');
            const template = container?.dataset?.invoicePreviewUrlTemplate || "{{ route('admin.refunds.invoice.preview', ':refundId') }}";
            window.open(template.replace(':refundId', refundId), '_blank');
        }

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.refunds.index') }}";
        }

        function showLoadingState() {
            $('#modal-client-name').text('Loading...');
            $('#client-email, #client-phone, #client-whatsapp, #client-country, #client-city, #client-address').text(
                'Loading...');
            $('#travel-info-container').html(
                '<div class="text-center py-4"><p class="text-gray-500">Loading travel information...</p></div>');
            $('#service-name, #service-cost, #extra-services, #profit-loss').text('Loading...');
            $('#payment-history-container').html(
                '<div class="text-center py-4"><p class="text-gray-500">Loading payment history...</p></div>');
        }

        // Helper functions for date/time formatting
        function formatDate(dateString) {
            if (!dateString) return null;
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
                    return dateString;
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

        function calculateTotalDuration(fromDate, toDate) {
            return calculateDuration(fromDate, toDate);
        }

        function getPaymentStatusBadge(status) {
            const badges = {
                0: '<span class="badge !rounded-full bg-info/10 text-info mb-2">Initiated</span>',
                1: '<span class="badge !rounded-full bg-success/10 text-success mb-2">Active</span>',
                2: '<span class="badge !rounded-full bg-danger/10 text-danger mb-2">Cancelled</span>',
                3: '<span class="badge !rounded-full bg-primary/10 text-primary mb-2">Full Payment</span>',
                4: '<span class="badge !rounded-full bg-warning/10 text-warning mb-2">Partial Payment</span>',
                5: '<span class="badge !rounded-full bg-success/10 text-success mb-2">Complete</span>',
                6: '<span class="badge !rounded-full bg-secondary/10 text-secondary mb-2">Pending</span>',
                7: '<span class="badge !rounded-full bg-purple/10 text-purple mb-2">Reschedule</span>'
            };
            return badges[status] || '<span class="badge !rounded-full bg-secondary/10 text-secondary mb-2">Payment</span>';
        }

        function showSuccess(message) {
            console.log('Success:', message);
            if (typeof showSuccessMessage === 'function') {
                try {
                    showSuccessMessage('success', message);
                } catch (e) {
                    console.log(message);
                }
            } else {
                // Fallback
                alert(' Success: ' + message);
            }
        }

        function reopenRefundModal() {
            const modal = document.getElementById('refund-preview-modal');
            if (!modal) return;
            // Use HSOverlay if available
            if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                window.HSOverlay.open(modal);
                return;
            }
            // Fallback: ensure classes are set to show the offcanvas/modal
            modal.classList.remove('hidden');
            modal.classList.add('open');
            // Add backdrop if missing
            if (!document.getElementById('refund-preview-backdrop')) {
                const backdrop = document.createElement('div');
                backdrop.id = 'refund-preview-backdrop';
                backdrop.className = 'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
                backdrop.onclick = function() {
                    // close modal when backdrop clicked
                    modal.classList.add('hidden');
                    modal.classList.remove('open');
                    backdrop.remove();
                };
                document.body.appendChild(backdrop);
                document.body.style.overflow = 'hidden';
            }
        }

        function showError(message) {
            console.error('Error:', message);
            if (typeof showErrorModal === 'function') {
                try {
                    showErrorModal('Error', message);
                } catch (e) {
                    console.error(message);
                }
            } else {
                // Fallback
                alert(' Error: ' + message);
            }
        }

        // Refund proof preview helpers
        function isImageFile(nameOrType) {
            return /\.(jpg|jpeg|png)$/i.test(nameOrType) || /image\//i.test(nameOrType);
        }

        function isPdfFile(nameOrType) {
            return /\.pdf$/i.test(nameOrType) || /application\/pdf/i.test(nameOrType);
        }

        // Show preview modal with content (fileUrl is an object URL or remote URL)
        function openProofPreview(fileUrl, fileName) {
            const container = $('#proof-preview-content');
            container.empty();
            $('#proof-download-link').hide().attr('href', '#').text('Open in new tab');

            if (!fileUrl) {
                container.html('<p class="text-gray-500">No preview available.</p>');
            } else if (isPdfFile(fileName) || isPdfFile(fileUrl)) {
                // Embed PDF via iframe
                container.html(`<iframe src="${fileUrl}" style="width:100%;height:70vh;border:none;"></iframe>`);
                $('#proof-download-link').show().attr('href', fileUrl).text('Open PDF in new tab');
            } else if (isImageFile(fileName) || isImageFile(fileUrl)) {
                container.html(`<div class="text-center"><img src="${fileUrl}" style="max-width:100%;height:auto;" alt="${fileName}"></div>`);
                $('#proof-download-link').show().attr('href', fileUrl).text('Open image in new tab');
            } else {
                // Fallback: provide download/open link
                container.html(`<p class="text-gray-500">Preview not supported for this file type. You can download/open the file using the link below.</p>`);
                $('#proof-download-link').show().attr('href', fileUrl).text('Open file in new tab');
            }

            // Open modal
            const modal = document.getElementById('refund-proof-preview-modal');
            if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                window.HSOverlay.open(modal);
            } else {
                modal.classList.remove('hidden');
                modal.classList.add('open');
            }
        }

        // Control visibility of the Preview button: only show when a file (selected or uploaded) exists
        function updatePreviewButtonVisibility() {
            try {
                const selectedFile = $('#refund-proof')[0] && $('#refund-proof')[0].files && $('#refund-proof')[0].files[0];
                const hasSelected = !!selectedFile;
                const hasUploaded = !!(currentRefundData && currentRefundData.refund && currentRefundData.refund.refund_proof);

                if (hasSelected || hasUploaded) {
                    $('#preview-proof-btn').show();
                } else {
                    $('#preview-proof-btn').hide();
                }

                // Show/Hide remove button for accounts/admin only
                if (isAccounts || isAdmin) {
                    if (hasSelected || hasUploaded) {
                        $('#remove-proof-btn').show();
                    } else {
                        $('#remove-proof-btn').hide();
                    }
                } else {
                    $('#remove-proof-btn').hide();
                }
            } catch (e) {
                // In case DOM elements aren't present yet, hide the button safely
                $('#preview-proof-btn').hide();
            }
        }

        // When user selects a new file
        $(document).on('change', '#refund-proof', function(e) {
            const file = this.files && this.files[0];
            if (!file) {
                $('#proof-filename').text('No file selected');
                // update visibility centrally
                updatePreviewButtonVisibility();
                return;
            }
            // Client-side guard for maximum file size (prevent 413 from server)
            if (file.size && file.size > MAX_PROOF_SIZE) {
                const kb = Math.round(file.size / 1024);
                showErrorModal('File too large', `Selected file (${kb} KB) exceeds the maximum allowed size of 2048 KB (2 MB). Please select a smaller file.`);
                $(this).val('');
                $('#proof-filename').text('No file selected');
                $('#proof-hint').text('File cleared — too large');
                $('#preview-proof-btn').hide();
                $('#remove-proof-btn').hide();
                return;
            }
            $('#proof-filename').text(file.name);
            // update visibility centrally
            updatePreviewButtonVisibility();

            // set hint
            $('#proof-hint').text('Selected file — click Preview to view.');

            // Temporarily store object URL on button data for preview
            try {
                const objectUrl = URL.createObjectURL(file);
                $('#preview-proof-btn').data('preview-url', objectUrl);
                $('#preview-proof-btn').data('preview-name', file.name);
            } catch (e) {
                // fallback to FileReader if createObjectURL not available
                const reader = new FileReader();
                reader.onload = function(evt) {
                    $('#preview-proof-btn').data('preview-url', evt.target.result);
                    $('#preview-proof-btn').data('preview-name', file.name);
                };
                reader.readAsDataURL(file);
            }
        });

        // Click preview button for newly selected file
        // Preview button should open the file in a new tab (for both selected files and existing uploads)
        $(document).on('click', '#preview-proof-btn', function() {
            const url = $(this).data('preview-url');
            if (!url) {
                showError('No file available for preview');
                return;
            }
            // Open directly in a new tab/window
            try {
                window.open(url, '_blank');
            } catch (e) {
                // Fallback: navigate the current window
                window.location.href = url;
            }
        });

        // Click proof filename to preview existing uploaded file (when loading refund details)
        // Clicking the filename also opens the uploaded file in a new tab (if present)
        $(document).on('click', '#proof-filename', function() {
            const filename = $(this).text().trim();
            if (!filename || filename === 'No file uploaded' || filename === 'No file selected') return;

            if (currentRefundData && currentRefundData.refund && currentRefundData.refund.refund_proof) {
                const path = currentRefundData.refund.refund_proof;
                const storageUrl = `{{ asset('storage') }}/${path}`;
                try {
                    window.open(storageUrl, '_blank');
                } catch (e) {
                    window.location.href = storageUrl;
                }
            } else {
                showError('No uploaded proof available to preview');
            }
        });

        // Click remove proof button (either clear selection before saving or request removal after saved)
        $(document).on('click', '#remove-proof-btn', function() {
            // If there's a currently saved proof, request deletion via modal + remarks
            const hasSaved = !!(currentRefundData && currentRefundData.refund && currentRefundData.refund.refund_proof);
            const selectedFile = $('#refund-proof')[0] && $('#refund-proof')[0].files && $('#refund-proof')[0].files[0];
            const hasSelected = !!selectedFile;

            if (hasSaved && !(hasSelected)) {
                // Open dialog prompting for remarks
                $('#refund-proof-remove-remarks').val('');
                $('#refund-proof-remove-id').val(currentRefundData.refund.id);
                const modal = document.getElementById('refund-proof-remove-modal');
                if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                    window.HSOverlay.open(modal);
                } else {
                    modal.classList.remove('hidden');
                    modal.classList.add('open');
                }
                return;
            }

            // Otherwise, clear the file input (pre-save)
            try {
                $('#refund-proof').val('');
                $('#proof-filename').text('No file selected');
                $('#proof-hint').text('File removed');
                $('#preview-proof-btn').hide();
                $('#remove-proof-btn').hide();
            } catch (e) {
                console.warn('Error clearing selected file', e);
            }
        });

        // Confirm remove proof with remarks
        $(document).on('click', '#confirm-remove-proof', function() {
            const refundId = $('#refund-proof-remove-id').val();
            const remarks = $('#refund-proof-remove-remarks').val().trim();

            if (!refundId) {
                showError('Refund not identified');
                return;
            }
            if (!remarks) {
                showError('Please enter remarks for deleting proof');
                return;
            }

            const url = "{{ route('admin.refunds.delete-proof', ':refundId') }}".replace(':refundId', refundId);
            const token = '{{ csrf_token() }}';

            $.ajax({
                url: url,
                method: 'DELETE',
                data: {
                    _token: token,
                    remarks: remarks
                },
                success: function(resp) {
                    if (resp.success) {
                        // Update UI: remove proof preview and hide remove & preview buttons
                        if (currentRefundData && currentRefundData.refund) {
                            currentRefundData.refund.refund_proof = null;
                            currentRefundData.refund.remarks = remarks;
                        }
                        $('#proof-filename').text('No file uploaded');
                        $('#proof-hint').text('File removed');
                        $('#preview-proof-btn').hide();
                        $('#remove-proof-btn').hide();
                        $('#refund-remarks').text(remarks);

                        // Close modal
                        const modal = document.getElementById('refund-proof-remove-modal');
                        if (window.HSOverlay && typeof window.HSOverlay.close === 'function') {
                            window.HSOverlay.close(modal);
                        } else {
                            modal.classList.add('hidden');
                            modal.classList.remove('open');
                        }
                        showSuccessModal('Success', resp.message || 'Proof removed successfully');
                    } else {
                        showError(resp.message || 'Error removing proof');
                    }
                },
                error: function(xhr) {
                    showError(xhr.responseJSON?.message || 'Error deleting proof');
                }
            });
        });

        // If the page was opened with a query param to auto-open a refund preview
        try {
            const params = new URLSearchParams(window.location.search);
            const openRefundFollowupId = params.get('open_refund_id') || params.get('open_refund_followup_id');
            if (openRefundFollowupId) {
                // Load refund details for that followup and open the preview modal
                loadRefundDetails(openRefundFollowupId);
                const modalEl = document.getElementById('refund-preview-modal');
                if (modalEl) {
                    if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                        window.HSOverlay.open(modalEl);
                    } else {
                        modalEl.classList.remove('hidden');
                        modalEl.classList.add('open');
                    }
                }

                // Remove the query param so reloading doesn't reopen it automatically
                try {
                    const newUrl = window.location.pathname + window.location.hash;
                    history.replaceState(null, '', newUrl);
                } catch (e) {
                    // ignore
                }
            }
        } catch (e) {
            // ignore URL parsing errors
        }
    </script>
    @endpush
    @include('admin.partials.modals.success-error-modals')