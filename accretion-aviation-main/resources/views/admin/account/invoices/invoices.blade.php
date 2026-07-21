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
                    <div class="hs-accordion" id="invoice-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <i class="ri-money-rupee-circle-line"></i>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Invoices</h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-header">
            <div class="box-title">Search Filters</div>
            <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                <i class="ti ti-chevron-up" id="filter-icon"></i>
            </button>
        </div>
        <div class="box-body" id="filter-section">
            <form method="GET" action="{{ route('admin.account.invoices') }}" id="filter-form">
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label">Service Date</label>
                        <div class="input-group">
                            <input type="date" class="ti-form-input form-control-sm rounded-sm" name="service_date"
                                id="service_date" value="{{ request('service_date') }}">
                        </div>
                    </div>

                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label">Service Name</label>
                        <select class="js-example-basic-single w-full form-control-sm" name="service_name"
                            id="service_name">
                            <option value="">Select service...</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}"
                                    {{ request('service_name') == $service->id ? 'selected' : '' }}>{{ $service->service }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label">Invoice Status</label>
                        <select class="ti-form-select form-control-sm rounded-sm" name="status" id="status">
                            <option value="">Select status...</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="2" {{ request('status') == '2' ? 'selected' : '' }}>Completed</option>
                            <!-- <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Full Paid</option>
                                                                                                                            <option value="partial" {{ request('status') == 'partial' ? 'selected' : '' }}>Partial Paid</option>
                                                                                                                            <option value="unpaid" {{ request('status') == 'unpaid' ? 'selected' : '' }}>Unpaid</option> -->
                        </select>
                    </div>

                    <div class  ="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label">Client Name</label>
                        <select class="js-example-basic-single w-full form-control-sm" name="client_id" id="client_id">
                            <option value="">All Clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}"
                                    {{ request('client_id') == $client->id ? 'selected' : '' }}>
                                    {{ $client->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                        <div class="flex gap-2">
                            <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">Apply
                                Filters</button>
                            <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2" id="reset-filters"><i
                                    class="ri-refresh-line"></i></button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">
                        Invoice List
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable server-paginated" width="100%"
                            data-empty-msg="No invoice record found">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">S.No</th>
                                    <th data-priority="2">Name</th>
                                    <th data-priority="3">Phone</th>
                                    <th data-priority="4">Service Date</th>
                                    <th data-priority="5">Service</th>
                                    <th data-priority="6">Amount</th>
                                    <th data-priority="7">Profit/Loss</th>
                                    <th data-priority="8">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoicesData as $index => $invoice)
                                    <tr>
                                        <td class="text-center">{{ (isset($vouchers) && $vouchers->firstItem() ? $vouchers->firstItem() : 1) + $index }}</td>
                                        <td>{{ $invoice['client']['name'] ?? 'N/A' }}</td>
                                        <td class="text-center">{{ $invoice['client']['phone'] ?? 'N/A' }}</td>
                                        <td class="text-center">
                                            {{ $invoice['ride']['from_date'] ? \Carbon\Carbon::parse($invoice['ride']['from_date'])->format('d-m-Y') : '-' }}
                                        </td>
                                        <td>
                                            {{ Str::limit($invoice['service']['service_names'] ?? '-', 50) }}
                                        </td>
                                        <td class="text-center">
                                            ₹{{ number_format($invoice['payment']['total_amount'] ?? 0, 2) }}</td>
                                        <td class="text-center">
                                            <span
                                                class="badge !rounded-full {{ $invoice['profit_loss'] >= 0 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">₹{{ number_format(abs($invoice['profit_loss'] ?? 0), 2) }}</span>
                                        </td>
                                        <td>
                                            <!-- <a aria-label="anchor"
                                                href="{{ route('admin.account.invoices.pdf', $invoice['id']) }}"
                                                target="_blank" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-dark-full"
                                                title="Download Invoice"><i class="ri-download-2-line"></i></a> -->
                                            <a aria-label="anchor"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-invoice-btn"
                                                data-invoice-id="{{ $invoice['id'] }}" title="Invoice Preview"><i
                                                    class="ri-eye-line"></i></a>
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full invoice-actions-btn"
                                                data-invoice-id="{{ $invoice['id'] }}"
                                                data-client-name="{{ $invoice['client']['name'] ?? 'N/A' }}"
                                                data-service-name="{{ $invoice['service']['service_names'] ?: $invoice['service']['service_names'] ?? '-' }}"
                                                title="Invoice Options">
                                                <i class="ri-file-text-line"></i>
                                            </button>
                                            <!-- Quick generate invoice button: will save client name as company_name if provided -->
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-secondary generate-invoice-btn {{ !empty($invoice['existing_invoice']) ? 'opacity-50 cursor-not-allowed' : '' }}"
                                                data-invoice-id="{{ $invoice['id'] }}"
                                                data-client-name="{{ $invoice['client']['name'] ?? '' }}"
                                                data-existing-invoice="{{ $invoice['existing_invoice'] ? '1' : '0' }}"
                                                title="{{ !empty($invoice['existing_invoice']) ? 'Invoice already generated' : 'Generate Invoice' }}"
                                                {{ !empty($invoice['existing_invoice']) ? 'disabled' : '' }}>
                                                <i class="ri-file-3-line"></i>
                                            </button>
                                            <button type="button"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success finalize-invoice-btn"
                                                data-invoice-id="{{ $invoice['id'] }}" title="Finalize Invoice">
                                                <i class="ri-checkbox-circle-line"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if(isset($vouchers) && $vouchers->hasPages())
                    <div class="mt-4">
                        {{ $vouchers->appends(request()->except('page'))->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <a id="open-view-invoice" data-hs-overlay="#view-invoice" style="display:none"></a>
    @include('admin.partials.modals.success-error-modals')

    <!-- Invoice Actions Modal (success-style card similar to screenshot) -->
    <div id="invoice-actions-modal" class="hs-overlay hidden ti-modal">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
            <div class="ti-modal-content w-full max-w-xl mx-auto">
                <div class="ti-modal-body p-6 text-center">
                    <div class="mb-4">
                        <span
                            class="avatar avatar-xl p-4 !rounded-full bg-success/10 text-success inline-flex items-center justify-center">
                            <i class="ri-check-line text-success text-3xl"></i>
                        </span>
                    </div>
                    <h3 class="text-2xl font-semibold text-purple-600 mb-2">Invoice generated successfully!</h3>
                    <p class="text-sm text-muted mb-2">Client: <span id="modal-invoice-client-name-header">-</span> |
                        Service: <span id="modal-invoice-service-name">-</span></p>
                    <p class="text-sm text-muted mb-4">Date: <span id="modal-invoice-date">-</span> | <span
                            id="modal-invoice-time">-</span></p>

                    <!-- Optional inline preview area (kept for compatibility with existing JS) -->
                    <div id="invoice-preview-content" class="text-center mb-4"
                        style="display:none; max-height:45vh; overflow-y:auto;"></div>

                    <div class="flex gap-3 justify-center">
                        <button type="button" class="ti-btn bg-primary text-white" id="view-preview-btn">
                            <i class="ri-eye-line me-2"></i>Preview
                        </button>
                        <button type="button" class="ti-btn ti-btn-outline-primary" id="download-invoice-btn">
                            <i class="ri-download-2-line me-2"></i>Download
                        </button>
                    </div>
                </div>
                <div class="ti-modal-footer justify-center">
                    <button type="button" class="ti-btn ti-btn-outline-secondary"
                        data-hs-overlay="#invoice-actions-modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <div id="view-invoice" class="view-invoice hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-money-rupee-circle-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1rem]">Invoice Preview – <span
                                class="text-primary" id="modal-client-name">-</span></h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#view-invoice">
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
        <div class="ti-offcanvas-body view-invoice-body">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride Status</label>
                                    <span class="badge !rounded-full bg-secondary text-white mb-2"
                                        id="modal-ride-status">-</span>
                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Payment
                                        Progress</label>
                                    <div class="flex items-center gap-x-3 whitespace-nowrap w-full mb-4">
                                        <div class="ti-main-progress w-full progress bg-gray-200 dark:bg-bodybg">
                                            <div class="ti-main-progress-bar bg-primary text-xs text-white text-center"
                                                id="modal-payment-progress" style="width: 0%" role="progressbar"
                                                aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-sm text-gray-800 dark:text-white"
                                                id="modal-payment-progress-label">0% Complete</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Client Information</h5>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6" id="client-info">
                                <!-- client info injected by JS -->
                            </div>
                        </div>
                    </div>
                    <div class="box" id="gst-box">
                        <div class="box-header justify-between">
                            <h5 class="box-title">Customer GST Info</h5>
                            <div>
                                <button type="button" id="edit-gst-btn"
                                    class="ti-btn ti-btn-outline-primary ti-btn-wave !py-1 !px-2 ">Edit Details</button>
                            </div>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6" id="gst-info">
                                <!-- GST info injected by JS -->
                            </div>
                            <!-- <div class="grid grid-cols-12 gap-6">
                                                                                                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Company Name</label>
                                                                                                                            <input type="text" name="company_name"
                                                                                                                                class="ti-form-input rounded-sm form-control-sm" value=""
                                                                                                                                placeholder="Accretion Aviation" required>
                                                                                                                        </div>
                                                                                                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">GST Number</label>
                                                                                                                            <input type="number" name="gst_number"
                                                                                                                                class="ti-form-input rounded-sm form-control-sm" value=""
                                                                                                                                placeholder="ABCDE1234F" required>
                                                                                                                        </div>
                                                                                                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Billing Address</label>
                                                                                                                            <textarea name="billing_address" class="ti-form-input w-full rounded-sm form-control-sm" placeholder="Ratnagiri"
                                                                                                                                rows="1"></textarea>
                                                                                                                        </div>
                                                                                                                        <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                            <button type="submit"
                                                                                                                                class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Save
                                                                                                                                Changes</button>
                                                                                                                        </div>
                                                                                                                    </div> -->
                        </div>
                    </div>
                    <div class="box">
                        <div class="box-header justify-between">
                            <h5 class="box-title">Travel Information</h5>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6" id="travel-info">
                                <!-- travel info injected by JS -->
                            </div>
                            <!-- Edit Trvel dates code -->
                            <!-- <div class="grid grid-cols-12 gap-6">
                                                                                                                                                                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <p class="text-primary">Previous Dates :</p>
                                                                                                                                                                        <div>
                                                                                                                                                                            <p>
                                                                                                                                                                                <span>From : 11-07-2025 12:00</span>
                                                                                                                                                                                |
                                                                                                                                                                                <span>To : 12-07-2025 12:00</span>
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
                                                                                                                                                                                <input type="text" class="form-control form-control-sm" id="datetime"
                                                                                                                                                                                    placeholder="Choose date with time">
                                                                                                                                                                            </div>
                                                                                                                                                                        </div>
                                                                                                                                                                    </div>
                                                                                                                                                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place</label>
                                                                                                                                                                        <p class="text-gray-800 dark:text-white">Mumbai</p>
                                                                                                                                                                    </div>
                                                                                                                                                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                                                                                                                                                                        <div class="form-group">
                                                                                                                                                                            <div class="input-group">
                                                                                                                                                                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                                                                                                                                                                    <i class="ri-calendar-line"></i>
                                                                                                                                                                                </div>
                                                                                                                                                                                <input type="text" class="form-control form-control-sm" id="datetime"
                                                                                                                                                                                    placeholder="Choose date with time">
                                                                                                                                                                            </div>
                                                                                                                                                                        </div>
                                                                                                                                                                    </div>
                                                                                                                                                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place</label>
                                                                                                                                                                        <p class="text-gray-800 dark:text-white">Goa</p>
                                                                                                                                                                    </div>
                                                                                                                                                                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <div class="form-check form-check-md flex items-center">
                                                                                                                                                                            <input class="form-check-input" type="checkbox" value=""
                                                                                                                                                                                id="checkebox-md">
                                                                                                                                                                            <label class="form-check-label" for="checkebox-md">
                                                                                                                                                                                No Date
                                                                                                                                                                            </label>
                                                                                                                                                                        </div>
                                                                                                                                                                    </div>
                                                                                                                                                                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                                                                                                                                        <button type="submit" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Save Changes</button>
                                                                                                                                                                    </div>
                                                                                                                                                                </div> -->
                        </div>
                    </div>
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Service Information</h5>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 gap-6" id="service-info">
                                <!-- service info injected by JS -->
                            </div>
                        </div>
                    </div>
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Vendor Information</h5>
                        </div>
                        <div class="box-body bg-gray-50">
                            <div id="vendor-sections">
                                <!-- vendor sections injected by JS -->
                            </div>
                        </div>
                        <div class="box-footer" id="vendor-footer" style="display:none;">
                            <div class="flex items-center">
                                <button type="button" id="view-receipt-btn"
                                    class="ti-btn ti-btn-outline-primary ti-btn-wave"><i
                                        class="ri-eye-line ms-2 inline-block align-middle"></i> View Receipt</button>
                                <div class="ms-3" id="vendor-footer-meta"></div>
                            </div>
                        </div>
                    </div>
                    <!-- Payment Information removed -->
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Payment History</h5>
                        </div>
                        <div class="box-body bg-gray-50" id="client-payment-history">
                            <!-- injected -->
                        </div>
                    </div>

                </div>
            </div>
            <div class="mt-5">
                <!-- <button type="submit" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Generate
                                                                                                        Invoice</button> -->
                <!-- <button type="submit" class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Refund Note</button> -->
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Attach click listeners for view and generate buttons
            document.querySelectorAll('.view-invoice-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-invoice-id');
                    if (!id) return;
                    loadInvoiceDetails(id);
                    // open offcanvas
                    document.querySelector('[data-hs-overlay="#view-invoice"]')?.click?.();
                });
            });

            // Finalize invoice (green tick) handler — no browser confirm; using shared success modal
            document.querySelectorAll('.finalize-invoice-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-invoice-id');
                    if (!id) return;

                    fetch(`{{ url('admin/account/invoices') }}/${id}/finalize`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        },
                        credentials: 'same-origin'
                    }).then(r => r.json()).then(res => {
                        if (res && res.success) {
                            // Use the shared success modal partial
                            const clientName = btn.closest('tr')?.querySelector(
                                'td:nth-child(2)')?.textContent?.trim() || '';
                            const serviceName = btn.closest('tr')?.querySelector(
                                'td:nth-child(5)')?.textContent?.trim() || '';
                            showSuccessMessage('Invoice marked as finalized!',
                                `Client: ${clientName} | Service: ${serviceName}`);

                            // remove the row from the table so it disappears from listing
                            const row = btn.closest('tr');
                            if (row) row.remove();
                        } else {
                            showErrorModal('Finalize failed', res.message ||
                                'Failed to finalize invoice');
                        }
                    }).catch(e => {
                        console.error(e);
                        showErrorModal('Finalize failed', 'Failed to finalize invoice');
                    });
                });
            });

            document.querySelectorAll('.generate-invoice-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-invoice-id');
                    if (!id) return;
                    // Pass client name so controller can save it as company_name
                    const clientName = this.getAttribute('data-client-name') || '';
                    generateInvoice(id, clientName);
                });
            });

            // Invoice actions button handler (opens modal with preview/download options)
            document.querySelectorAll('.invoice-actions-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-invoice-id');
                    const clientName = this.getAttribute('data-client-name') || '-';
                    const serviceName = this.getAttribute('data-service-name') || '-';

                    if (!id) return;

                    // Set modal header and details
                    document.getElementById('modal-invoice-client-name-header').textContent =
                        clientName;
                    const serviceEl = document.getElementById('modal-invoice-service-name');
                    if (serviceEl) serviceEl.textContent = serviceName;

                    // Set current date/time as a fallback (will be overwritten if preview contains specific info)
                    const now = new Date();
                    const options = {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    };
                    const timeOptions = {
                        hour: '2-digit',
                        minute: '2-digit'
                    };
                    const formattedDate = now.toLocaleDateString(undefined, options);
                    const formattedTime = now.toLocaleTimeString(undefined, timeOptions);
                    const dateEl = document.getElementById('modal-invoice-date');
                    const timeEl = document.getElementById('modal-invoice-time');
                    if (dateEl) dateEl.textContent = formattedDate;
                    if (timeEl) timeEl.textContent = formattedTime;

                    // Do not auto-load invoice preview here. Keep inline preview hidden until user clicks 'View preview'.
                    const previewEl = document.getElementById('invoice-preview-content');
                    if (previewEl) {
                        previewEl.innerHTML = '';
                        previewEl.style.display = 'none';
                    }

                    // Set up preview button click handler (open PDF in new tab)
                    const previewBtn = document.getElementById('view-preview-btn');
                    previewBtn.onclick = function() {
                        window.open(`{{ url('admin/account/invoices') }}/${id}/pdf`, '_blank');
                    };

                    // Set up download button click handler
                    const downloadBtn = document.getElementById('download-invoice-btn');
                    downloadBtn.onclick = function() {
                        window.location.href =
                            `{{ url('admin/account/invoices') }}/${id}/download`;
                    };

                    // Open the modal
                    window.HSOverlay.open(document.getElementById('invoice-actions-modal'));
                });
            });

            // Clear/hide inline preview content when modal closes to avoid style/script bleed
            const invoiceActionsModal = document.getElementById('invoice-actions-modal');
            if (invoiceActionsModal) {
                // Listen for overlay close trigger (HSOverlay provides hooks via custom event or you can watch clicks)
                invoiceActionsModal.addEventListener('click', function(e) {
                    // if clicked outside the content or on close buttons, clear preview
                    const previewEl = document.getElementById('invoice-preview-content');
                    if (previewEl) {
                        previewEl.innerHTML = '';
                        previewEl.style.display = 'none';
                    }
                });
            }

            // Filter form handlers
            const filterForm = document.getElementById('filter-form');
            // NOTE: Do NOT auto-submit when filter inputs change. User must click 'Apply Filters' button.
            // This prevents unexpected reloads and keeps DataTable initialization stable.

            // Toggle filter section
            const toggleFilters = document.getElementById('toggle-filters');
            const filterSection = document.getElementById('filter-section');
            const filterIcon = document.getElementById('filter-icon');
            if (toggleFilters && filterSection && filterIcon) {
                toggleFilters.addEventListener('click', function() {
                    if (filterSection.style.display === 'none' || getComputedStyle(filterSection)
                        .display === 'none') {
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

            // Reset filters button
            const resetBtn = document.getElementById('reset-filters');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    window.location = "{{ route('admin.account.invoices') }}";
                });
            }
        });

        // Auto-open invoice if requested via query param (redirected from controller)
        (() => {
            try {
                const params = new URLSearchParams(window.location.search);
                const openId = params.get('open_invoice');
                if (openId) {
                    // load and open invoice details
                    loadInvoiceDetails(openId);
                    // open offcanvas if the overlay helper exists
                    setTimeout(() => {
                        document.querySelector('[data-hs-overlay="#view-invoice"]')?.click?.();
                    }, 200);
                }
            } catch (e) {
                console.error('Failed to auto-open invoice', e);
            }
        })();

        function loadInvoiceDetails(id) {
            fetch(`{{ url('admin/account/invoices') }}/${id}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                .then(r => {
                    if (!r.ok) throw new Error('Network response was not ok: ' + r.status);
                    return r.json();
                })
                .then(resp => {
                    if (!resp || !resp.success) throw new Error((resp && resp.message) ? resp.message :
                        'Error fetching invoice');
                    const data = resp.data || {};
                    const voucher = data.voucher || {};
                    const invoiceData = data.invoiceData || {};
                    const paymentHistory = data.paymentHistory || [];

                    // Client
                    document.getElementById('modal-client-name').textContent = data.client?.name || '-';
                    renderClientInfo(data.client || {});

                    // Payment progress
                    const total = invoiceData.payment?.total_amount || 0;
                    const received = invoiceData.payment?.received_amount || 0;
                    const pct = total > 0 ? Math.round((received / total) * 100) : 0;
                    document.getElementById('modal-payment-progress').style.width = pct + '%';
                    document.getElementById('modal-payment-progress-label').textContent = pct + '% Complete';

                    // GST
                    renderGstInfo(invoiceData.invoice ?? null, data.existingInvoice ?? null, data.client || {},
                        voucher || {});

                    // Ride status - prefer latest payment followup status, then voucher relation fallbacks
                    // Map followup status codes to three display labels: Active, Complete, Pending
                    // Followup codes (from your note):
                    // 0=initiated, 1=active, 2=canceled, 3=Full payment received, 4=Partial payment received,
                    // 5=confirm/complete, 6=pending, 7=reschedule, 8=approve, 9=reject
                    try {
                        let raw = invoiceData.payment?.latest_payment?.status ||
                            (voucher.lead && (voucher.lead.leadFollowups || voucher.lead.lead_followups) && (voucher
                                .lead.leadFollowups || voucher.lead.lead_followups)[0]?.status) ||
                            null;

                        const mapToLabel = (s) => {
                            if (s === null || s === undefined || s === '') return 'Pending';
                            const n = Number(s);
                            if (!Number.isNaN(n)) {
                                // Active: 1 (active), 8 (approve)
                                if (n === 1 || n === 8) return 'Active';
                                // Complete: 3 (full payment received), 5 (confirm/complete)
                                if (n === 3 || n === 5) return 'Complete';
                                // All other codes show Pending
                                return 'Pending';
                            }

                            // If textual, try to infer
                            const key = String(s).toLowerCase();
                            if (key.includes('active') || key.includes('approve')) return 'Active';
                            if (key.includes('complete') || key.includes('paid') || key.includes('full'))
                                return 'Complete';
                            return 'Pending';
                        };

                        const label = mapToLabel(raw);
                        document.getElementById('modal-ride-status').textContent = label;
                    } catch (e) {
                        // ignore
                    }

                    // Travel - Use multiple trips functionality
                    renderTravelInfo(invoiceData.all_rides || [], invoiceData.ride || {});

                    // Service (pass full invoiceData so profit/loss is available)
                    renderServiceInfo(invoiceData || {});

                    // Vendors
                    renderVendorSections(invoiceData.vendor?.vendors || [], buildPerVendorHistories(paymentHistory));

                    // Payment history
                    renderClientPaymentHistory(paymentHistory || []);
                })
                .catch(err => {
                    console.error(err);
                    alert('Failed to load invoice details');
                });
        }

        function renderClientInfo(client) {
            const html = `
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
            <p class="text-gray-800 dark:text-white">${client.mobile_number || client.contact_number || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
            <p class="text-gray-800 dark:text-white">${client.whatsapp_number || client.alternate_number || client.mobile_number || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
            <p class="text-gray-800 dark:text-white">${client.country?.country || client.country?.name || 'N/A'}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
            <p class="text-gray-800 dark:text-white">${client.city?.city || client.city?.name || 'N/A'}</p>
        </div>
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
            <p class="text-gray-800 dark:text-white">${client.address || 'N/A'}</p>
        </div>
    `;
            document.getElementById('client-info').innerHTML = html;
        }

        function renderGstInfo(invoice, existingInvoice, client, voucher) {
            // Prefill company name: prefer existing saved invoice -> fall back to client name -> invoice payload
            const company = existingInvoice?.company_name || client?.name || invoice?.company_name || '';
            const gst = existingInvoice?.gst_number || invoice?.gst_number || '';
            const billing = existingInvoice?.billing_address || invoice?.billing_address || client.address || '';

            const html = `
        <div class="xl:col-span-12 col-span-12">
            <div id="gst-display">
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-4 col-span-12">
                        <label class="ti-form-label">Company Name</label>
                        <p class="text-gray-800" id="gst-company">${company || 'Not set'}</p>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <label class="ti-form-label">GST Number</label>
                        <p class="text-gray-800" id="gst-number">${gst || 'Not set'}</p>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <label class="ti-form-label">Billing Address</label>
                        <p class="text-gray-800" id="gst-billing">${billing || 'Not set'}</p>
                    </div>
                </div>
            </div>
            <div id="gst-edit" style="display:none">
                <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">Company Name</label>
                            <input type="text" id="gst-company-input" class="ti-form-input" value="${company}">
                            <div class="text-danger text-sm mt-1" id="gst-company-error" style="display:none"></div>
                        </div>
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">GST Number</label>
                            <input type="text" id="gst-number-input" class="ti-form-input" value="${gst}">
                            <div class="text-danger text-sm mt-1" id="gst-number-error" style="display:none"></div>
                        </div>
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">Billing Address</label>
                            <textarea id="gst-billing-input" class="ti-form-input">${billing}</textarea>
                            <div class="text-danger text-sm mt-1" id="gst-billing-error" style="display:none"></div>
                        </div>
                    <div class="xl:col-span-12 col-span-12 mt-2">
                        <button class="ti-btn bg-theme ti-btn-primary-full" id="gst-save-btn">Save</button>
                        <button class="ti-btn ti-btn-outline-secondary" id="gst-cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
    `;
            document.getElementById('gst-info').innerHTML = html;

            // Edit button handler: toggle edit form
            document.getElementById('edit-gst-btn')?.addEventListener('click', function() {
                document.getElementById('gst-display').style.display = 'none';
                document.getElementById('gst-edit').style.display = 'block';
            });

            document.getElementById('gst-cancel-btn')?.addEventListener('click', function() {
                document.getElementById('gst-display').style.display = 'block';
                document.getElementById('gst-edit').style.display = 'none';
            });

            document.getElementById('gst-save-btn')?.addEventListener('click', function() {
                const companyName = document.getElementById('gst-company-input').value || '';
                const gstNumber = document.getElementById('gst-number-input').value || '';
                const billingAddress = document.getElementById('gst-billing-input').value || '';
                const voucherId = invoice?.voucher_id || existingInvoice?.voucher_id || (invoice?.voucher?.id ??
                    null) || (voucher?.id ?? null);
                if (!voucherId) {
                    alert('Unable to determine voucher id');
                    return;
                }

                // clear previous errors
                ['gst-company-error', 'gst-number-error', 'gst-billing-error'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) {
                        el.style.display = 'none';
                        el.textContent = '';
                    }
                });

                fetch(`{{ url('admin/account/invoices') }}/${voucherId}/gst-info`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        company_name: companyName,
                        gst_number: gstNumber,
                        billing_address: billingAddress
                    }),
                    credentials: 'same-origin'
                }).then(r => r.json()).then(res => {
                    if (res.success) {
                        // success
                        // re-render display view
                        document.getElementById('gst-company').textContent = companyName || 'Not set';
                        document.getElementById('gst-number').textContent = gstNumber || 'Not set';
                        document.getElementById('gst-billing').textContent = billingAddress || 'Not set';
                        document.getElementById('gst-display').style.display = 'block';
                        document.getElementById('gst-edit').style.display = 'none';
                    } else if (res.errors) {
                        // show validation errors inline
                        if (res.errors.company_name) {
                            const el = document.getElementById('gst-company-error');
                            el.style.display = 'block';
                            el.textContent = res.errors.company_name.join(', ');
                        }
                        if (res.errors.gst_number) {
                            const el = document.getElementById('gst-number-error');
                            el.style.display = 'block';
                            el.textContent = res.errors.gst_number.join(', ');
                        }
                        if (res.errors.billing_address) {
                            const el = document.getElementById('gst-billing-error');
                            el.style.display = 'block';
                            el.textContent = res.errors.billing_address.join(', ');
                        }
                    } else {
                        alert(res.message || 'Failed to update');
                    }
                }).catch(e => {
                    console.error(e);
                    alert('Failed to update GST');
                });
            });
        }

        function renderTravelInfo(allRides, fallbackRide) {
            const container = document.getElementById('travel-info');

            // Use allRides if available, otherwise fallback to single ride format
            const rides = allRides && allRides.length > 0 ? allRides : (fallbackRide ? [fallbackRide] : []);

            if (!rides || rides.length === 0) {
                container.innerHTML = `
            <div class="text-center py-4">
                <p class="text-gray-500">No travel information found</p>
            </div>
        `;
                return;
            }

            let travelHtml = '';

            if (rides.length === 1) {
                // Single trip - display in original format
                const ride = rides[0];
                travelHtml = `
            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                <p class="text-gray-800 dark:text-white">${ride.from_date ? new Date(ride.from_date).toLocaleDateString('en-GB') : 'N/A'}</p>
            </div>
            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place</label>
                <p class="text-gray-800 dark:text-white">${ride.from_place || 'N/A'}</p>
            </div>
            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                <p class="text-gray-800 dark:text-white">${ride.to_date ? new Date(ride.to_date).toLocaleDateString('en-GB') : 'N/A'}</p>
            </div>
            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place</label>
                <p class="text-gray-800 dark:text-white">${ride.to_place || 'N/A'}</p>
            </div>
        `;
            } else {
                // Multiple trips - display as collapsible trip segments
                travelHtml = `
            <div class="xl:col-span-12 col-span-12">
                
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm font-semibold text-primary">Multiple Trip Segments (${rides.length} trips)</span>
                    <span class="badge bg-primary/10 text-primary rounded-full px-3 py-1 text-xs">Multi-Trip Journey</span>
                </div>
                <div class="hs-accordion-group" data-hs-accordion-always-open>
        `;

                rides.forEach((ride, index) => {
                    const fromDate = ride.from_date ? new Date(ride.from_date).toLocaleDateString('en-GB') : '-';
                    const toDate = ride.to_date ? new Date(ride.to_date).toLocaleDateString('en-GB') : '-';
                    const fromTime = ride.from_date ? new Date(ride.from_date).toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    const toTime = ride.to_date ? new Date(ride.to_date).toLocaleTimeString('en-GB', {
                        hour: '2-digit',
                        minute: '2-digit'
                    }) : '';
                    const isFirst = index === 0;
                    const accordionId = `invoice-trip-accordion-${index}`;

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

                // Add journey summary for multiple trips
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
            </div>
        `;
            }

            container.innerHTML = travelHtml;
            
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

        function renderServiceInfo(invoiceData) {
            // invoiceData contains service and profit_loss (set by controller)
            const service = invoiceData.service || {};
            // Use cost price as Total Service Cost, matching preview
            const total = service?.detailed_items?.reduce(
            (sum, item) => sum + (item.cost_price ?? 0),
            0
            );
            const serviceNames = service.service_names || service.service_display || '';
            const extra = (service.extra_services && service.extra_services.length) ? service.extra_services.join(', ') : (
                service.extra_service_names || service.extra_service_display || 'None');
            // Profit/Loss: controller provides invoiceData.profit_loss (received - vendor_cost)
            const profitRaw = (typeof invoiceData.profit_loss !== 'undefined') ? parseFloat(invoiceData.profit_loss) : NaN;
            const profit = isNaN(profitRaw) ? 0 : profitRaw;
            const profitClass = profit > 0 ? 'text-success' : (profit < 0 ? 'text-danger' :
                'text-gray-800 dark:text-white');
            const profitLabel = profit > 0 ? 'Profit' : (profit < 0 ? 'Loss' : 'Profit/Loss');

            const html = `
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
            <p class="text-gray-800 dark:text-white">${serviceNames || 'N/A'}</p>
        </div>
        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
            <p class="text-gray-800 dark:text-white">${extra}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12 mt-3">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Service Cost</label>
            <p class="text-gray-800 dark:text-white">₹${formatNumber(total)}</p>
        </div>
        <div class="xl:col-span-4 lg:col-span-4 md:col-span-6 sm:col-span-12 col-span-12 mt-3">
            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">${profitLabel}</label>
            <p class="${profitClass}">₹${formatNumber(Math.abs(profit))}${profit !== 0 ? ' ' + (profit > 0 ? '(Profit)' : '(Loss)') : ''}</p>
        </div>
    `;
            document.getElementById('service-info').innerHTML = html;
        }

        // Payment information section removed per request

        function buildPerVendorHistories(history) {
            // Convert paymentHistory items into a map keyed by vendor_payment_id if possible
            const map = {};
            if (!history) return map;
            history.forEach(item => {
                if (!item.lead_followup) return;
                const vendorId = item.vendor_payment_id || item.lead_followup.vendor_payment_id || null;
                if (!vendorId) return;
                if (!map[vendorId]) map[vendorId] = {
                    paid_total: 0,
                    items: []
                };
                map[vendorId].paid_total = (map[vendorId].paid_total || 0) + (parseFloat(item.paid_amount || 0) ||
                    0);
                map[vendorId].items.push(item);
            });
            return map;
        }

        function renderVendorSections(vendors, perVendorHistories = {}) {
            // vendors is expected to be an array of {name, total_amount, paid_amount, balance, payment_status}
            let html = '';
            vendors.forEach((v, idx) => {
                const paid = perVendorHistories[v.id]?.paid_total ?? v.paid_amount ?? 0;
                const balance = (v.total_amount || 0) - (paid || 0);
                html += `
            <div class="box vendor-section" data-vendor-id="${v.id}">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Vendor Information - #${idx+1}</h5>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-12 col-span-6">
                            <label class="ti-form-label">Vendor Name</label>
                            <p class="text-gray-800 dark:text-white">${v.name || 'N/A'}</p>
                        </div>
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">Total Amount</label>
                            <p class="text-gray-800">₹${formatNumber(v.total_amount || 0)}</p>
                        </div>
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">Paid Amount</label>
                            <p class="text-success">₹${formatNumber(paid || 0)}</p>
                        </div>
                        <div class="xl:col-span-4 col-span-12">
                            <label class="ti-form-label">Balance</label>
                            <p class="text-danger">₹${formatNumber(balance)}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
            });
            document.getElementById('vendor-sections').innerHTML = html;
        }

        function renderClientPaymentHistory(history) {
            const container = document.getElementById('client-payment-history');
            if (!container) return;
            if (!history || history.length === 0) {
                container.innerHTML = '<p class="text-gray-500">No payment history found</p>';
                return;
            }
            let html = '';
            history.forEach(item => {
                // Determine a human-friendly status from the audit trail or fallback to lead_followup.status
                const statusCode = (typeof item.payment_status !== 'undefined' && item.payment_status !== null) ?
                    item.payment_status : (item.lead_followup && item.lead_followup.payment_status ? item
                        .lead_followup.payment_status : null);
                let statusText = 'Audit';
                if (statusCode !== null) {
                    const code = parseInt(statusCode);
                    if (code === 1) statusText = 'Approved';
                    else if (code === 2) statusText = 'Rejected';
                    else if (code === 3) statusText = 'Pending';
                    else statusText = String(statusCode);
                } else if (item.lead_followup && item.lead_followup.status) {
                    statusText = item.lead_followup.status;
                }

                // Choose badge classes: Approved (green), Rejected (red), default secondary
                let badgeClass = 'bg-secondary text-white';
                if (String(statusText).toLowerCase() === 'approved') badgeClass = 'bg-success text-white';
                else if (String(statusText).toLowerCase().includes('reject') || String(statusText).toLowerCase() ===
                    'rejected') badgeClass = 'bg-danger text-white';

                html +=
                    `<div class="flex items-center mb-4"><div class="me-4 gap-0"><span class="avatar avatar-sm p-2 !rounded-full bg-theme m-0 text-white"><i class="ri-time-line text-[1.2rem]"></i></span></div><div class="flex-grow"><div class="flex items-center justify-between"><div><h5 class="font-semibold mb-1">₹${(parseFloat(item.paid_amount||0)).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2})}</h5><p class="text-sm text-gray-600">${item.paid_date ? new Date(item.paid_date).toLocaleString() : (item.created_at ? new Date(item.created_at).toLocaleString() : 'N/A')}</p><p class="text-xs text-gray-500">${item.payment_method || ''} ${item.narration ? '• ' + item.narration : ''}</p></div><div class="text-end"><span class="badge !rounded-full ${badgeClass}">${statusText}</span>${item.created_by_name ? '<div class="text-xs text-gray-500 mt-1">'+item.created_by_name+'</div>' : ''}</div></div></div></div>`;
            });
            container.innerHTML = html;
        }

        function generateInvoice(voucherId, clientName = '') {
            showConfirmationModal('Generate Invoice', 'Generate invoice for this voucher?', function() {
                fetch(`{{ url('admin/account/invoices') }}/${voucherId}/generate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        company_name: clientName || '',
                        gst_number: '',
                        billing_address: ''
                    }),
                    credentials: 'same-origin'
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        showSuccessMessage('Invoice generated', res.message ||
                            'Invoice generated successfully');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1200);
                    } else {
                        showErrorModal('Invoice generation failed', res.message ||
                            'Failed to generate invoice');
                    }
                }).catch(e => {
                    console.error(e);
                    showErrorModal('Invoice generation failed', 'Failed to generate invoice');
                });
            });
        }

        function formatNumber(n) {
            return (parseFloat(n) || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
    </script>
@endpush
