{{-- Vendor Refund right-side drawer. Same structure as the Customer Refund drawer; populated via AJAX. --}}
<div id="vendor-refund-preview-modal" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
    <div class="ti-offcanvas-header">
        <div class="flex items-center">
            <div class="me-4 gap-0">
                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                    <i class="las la-file-invoice-dollar"></i>
                </span>
            </div>
            <div class="flex-grow">
                <div class="flex items-center justify-between">
                    <h5 class="font-semibold mb-0 leading-none text-[1rem]">Vendor Refund Preview – <span
                            class="text-primary" id="vr-modal-vendor-name">Loading...</span></h5>
                    <div class="flex gap-2">
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700"
                            data-hs-overlay="#vendor-refund-preview-modal">
                            <i class="ri-close-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ti-offcanvas-body">
        <!-- Status / Progress -->
        <div class="box">
            <div class="box-body bg-gray-50">
                <div class="grid grid-cols-12 gap-6">
                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Status</label>
                        <span id="vr-status-badge" class="badge !rounded-full bg-warning/10 text-warning">Pending</span>
                    </div>
                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Progress</label>
                        <div class="flex items-center gap-x-3 whitespace-nowrap w-full mb-4">
                            <div class="ti-main-progress w-full progress bg-gray-200 dark:bg-bodybg">
                                <div class="ti-main-progress-bar bg-primary text-xs text-white text-center"
                                    id="vr-progress-bar" style="width: 0%" role="progressbar" aria-valuenow="0"
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="text-end">
                                <span class="text-sm text-gray-800 dark:text-white" id="vr-progress-text">0%
                                    Complete</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Vendor Refund Information -->
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Vendor Refund Information</h5>
            </div>
            <div class="box-body bg-gray-50">
                <form id="vendor-refund-form" enctype="multipart/form-data">
                    <input type="hidden" id="vr-vendor-payment-id">

                    <div class="grid grid-cols-12 gap-6">
                        @foreach ([
                            'vr-original-amount' => 'Original Vendor Amount',
                            'vr-cancellation-amount' => 'Cancellation Amount',
                            'vr-gross-paid' => 'Total Paid To Vendor',
                            'vr-refund-received' => 'Refund Received',
                            'vr-net-paid' => 'Net Paid To Vendor',
                            'vr-refund-due' => 'Refund Due',
                        ] as $id => $label)
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">{{ $label }}</label>
                                <div class="input-group">
                                    <span class="input-group-text">₹</span>
                                    <input type="text" class="form-control" id="{{ $id }}" readonly>
                                </div>
                            </div>
                        @endforeach

                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Type</label>
                            <select class="form-control" id="vr-refund-type" name="refund_type">
                                <option value="">Select refund type...</option>
                                @foreach (['Bank Transfer', 'UPI Payment', 'Cash', 'Cheque', 'Debit Card', 'Credit Card', 'Net Banking', 'Wallet', 'Payment Gateway', 'Other'] as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Date</label>
                            <input type="date" class="form-control" id="vr-refund-date" name="refund_date">
                        </div>

                        <div class="col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Upload refund proof</label>
                            <div class="flex items-center gap-3">
                                <input type="file" class="form-control" id="vr-refund-proof" name="refund_proof"
                                    accept=".pdf,.jpg,.jpeg,.png">
                                <button type="button" id="vr-preview-proof-btn"
                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-outline-secondary"
                                    style="display: none;" title="Preview proof">
                                    <i class="ri-eye-line"></i>
                                </button>
                            </div>
                            <div class="text-sm text-gray-500 mt-1" id="vr-proof-filename">No file uploaded</div>
                            <div class="text-sm text-gray-400 mt-1">Optional. Max file size: 2 MB. Allowed types: .pdf,
                                .jpg, .jpeg, .png</div>
                        </div>

                        <div class="col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Refund Reason</label>
                            <textarea class="form-control" id="vr-refund-reason" name="refund_reason" rows="3"
                                placeholder="Vendor refund reason"></textarea>
                        </div>

                        <div class="col-span-12">
                            <button type="submit" class="ti-btn bg-theme ti-btn-primary-full" id="vr-save-btn">
                                Save changes
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Vendor Information -->
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Vendor Information</h5>
            </div>
            <div class="box-body bg-gray-50">
                <div class="grid grid-cols-12 gap-4">
                    @foreach ([
                        'vr-vendor-email' => 'Email Address',
                        'vr-vendor-phone' => 'Phone Number',
                        'vr-vendor-city' => 'City',
                        'vr-vendor-address' => 'Address',
                    ] as $id => $label)
                        <div class="xl:col-span-6 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">{{ $label }}</label>
                            <p class="text-gray-800 dark:text-white" id="{{ $id }}">-</p>
                        </div>
                    @endforeach
                    <div class="col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Bank Details</label>
                        <p class="text-gray-800 dark:text-white whitespace-pre-line" id="vr-vendor-bank">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Travel Information -->
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Travel Information</h5>
            </div>
            <div class="box-body bg-gray-50" id="vr-travel-info-container">
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
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Customer</label>
                        <p class="text-gray-800 dark:text-white" id="vr-customer-name">-</p>
                    </div>
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0 text-sm">Service Date</label>
                        <p class="text-gray-800 dark:text-white" id="vr-service-date">-</p>
                    </div>
                    <div class="col-span-12" id="vr-service-container"></div>
                </div>
            </div>
        </div>

        <!-- Vendor Payment History -->
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Vendor Payment History</h5>
            </div>
            <div class="box-body bg-gray-50" id="vr-payment-history-container">
                <div class="text-center py-4">
                    <p class="text-gray-500">Loading payment history...</p>
                </div>
            </div>
        </div>

        <!-- Vendor Refund History -->
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Vendor Refund History</h5>
            </div>
            <div class="box-body bg-gray-50" id="vr-refund-history-container">
                <div class="text-center py-4">
                    <p class="text-gray-500">Loading refund history...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Mark Refund As Done Modal (same as Customer Refund) -->
<div id="vendor-refund-mark-done-modal" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="flex items-center justify-center min-h-screen w-full fixed inset-0 z-50"
        style="background: rgba(0,0,0,0.2);">
        <div class="ti-modal-box ti-modal-content bg-white rounded shadow-lg" style="max-width: 400px; width: 100%;">
            <div class="ti-modal-header">
                <h6 class="modal-title text-[1rem] font-semibold">Confirm Mark as Done</h6>
                <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                    data-hs-overlay="#vendor-refund-mark-done-modal">
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
                    <p class="text-gray-600 mb-4">
                        Are you sure you want to mark this refund as done?
                    </p>
                </div>
            </div>
            <div class="ti-modal-footer">
                <button type="button" class="ti-btn ti-btn-outline-secondary"
                    data-hs-overlay="#vendor-refund-mark-done-modal">
                    Cancel
                </button>
                <button type="button" class="ti-btn ti-btn-primary" id="vr-confirm-mark-done">
                    Yes, Mark as Done
                </button>
            </div>
        </div>
    </div>
</div>
