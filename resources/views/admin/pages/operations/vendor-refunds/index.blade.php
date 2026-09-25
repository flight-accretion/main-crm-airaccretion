@extends('admin.layouts.header')

@section('content')

    <div class="block justify-between page-header md:flex">

        <div>
            <h3
                class="!text-defaulttextcolor
                       dark:!text-defaulttextcolor/70
                       dark:text-white
                       text-[1.125rem]
                       font-semibold"
            >
                Vendor Refund
            </h3>

            <p class="text-[0.75rem] text-gray-500 mt-1">
                Pending vendor refunds created from Ride Status.
            </p>
        </div>

    </div>


    @if (session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif


    @if (session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif


    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    {{-- Search Filters --}}
    <div class="box mb-6">

        <div class="box-header justify-between">
            <div class="box-title">
                Search Filters
            </div>
        </div>

        <div class="box-body">

            <form
                method="GET"
                action="{{ route('admin.operations.vendor-refunds.index') }}"
            >

                <div class="grid grid-cols-12 gap-4">

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">From Service Date</label>
                        <input
                            type="date"
                            name="from_service_date"
                            value="{{ request('from_service_date') }}"
                            class="form-control"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">To Service Date</label>
                        <input
                            type="date"
                            name="to_service_date"
                            value="{{ request('to_service_date') }}"
                            class="form-control"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">Customer</label>
                        <input
                            type="text"
                            name="customer"
                            value="{{ request('customer') }}"
                            class="form-control"
                            placeholder="Customer name"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">Vendor Phone</label>
                        <input
                            type="text"
                            name="phone"
                            value="{{ request('phone') }}"
                            class="form-control"
                            placeholder="Vendor phone number"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">Vendor</label>
                        <input
                            type="text"
                            name="vendor"
                            value="{{ request('vendor') }}"
                            class="form-control"
                            placeholder="Vendor name"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="form-label">Service</label>
                        <input
                            type="text"
                            name="service"
                            value="{{ request('service') }}"
                            class="form-control"
                            placeholder="Service"
                        >
                    </div>

                </div>

                <div class="flex gap-2 mt-4">
                    <button type="submit" class="ti-btn ti-btn-primary">
                        Apply
                    </button>

                    <a
                        href="{{ route('admin.operations.vendor-refunds.index') }}"
                        class="ti-btn ti-btn-light"
                    >
                        Reset
                    </a>
                </div>

            </form>

        </div>

    </div>


    @php
        $money = function ($value) {
            return '₹' . number_format((float) $value, 2);
        };
    @endphp

    {{-- Vendor Refund List --}}
    <div class="box">

        <div class="box-header">
            <div class="box-title">
                Vendor Refund List
            </div>
        </div>

        <div class="box-body overflow-x-auto">

            <table
                id="vendorRefundTable"
                class="table whitespace-nowrap table-bordered min-w-full"
            >

                <thead>
                    <tr>
                        <th>S.No</th>
                        <th>Customer Name</th>
                        <th>Customer Phone</th>
                        <th>Vendor Name</th>
                        <th>Vendor Phone</th>
                        <th>Service Name</th>
                        <th>Service Date</th>
                        <th>Original Vendor Amount</th>
                        <th>Cancellation Amount</th>
                        <!-- <th>Refund Received</th> -->
                        <th>Refund Due</th>
                        <!-- <th>Refund Date</th> -->
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($vendorRefunds as $index => $row)
        <script>
            console.log("Row [{{ $index }}] Data:", @js($row));
        </script>
                        <tr data-vendor-payment-id="{{ $row->id }}">

                            <td>{{ $vendorRefunds->firstItem() + $index }}</td>

                            <td>{{ $row->customer_name }}</td>

                            <td>{{ $row->customer_phone }}</td>

                            <td>{{ $row->vendor_name }}</td>

                            <td>{{ $row->vendor_phone }}</td>

                            <td>{{ \Illuminate\Support\Str::limit($row->service_name, 60) }}</td>

                            <td>
                                {{ $row->service_date ? $row->service_date->format('d M Y') : '-' }}
                            </td>

                            <td>{{ $money($row->original_amount) }}</td>

                            <td>
                                {{ $row->cancellation_amount !== null ? $money($row->cancellation_amount) : '-' }}
                            </td>

                            <!-- <td>{{ $money($row->refund_received) }}</td> -->

                            <td>{{ $money($row->refund_due) }}</td>

                            <!-- <td>
                                {{ $row->refund_date ? \Carbon\Carbon::parse($row->refund_date)->format('d M Y') : '-' }}
                            </td> -->

                            {{-- Actions --}}
                            <td>

                                <div class="flex gap-2">

                                    {{-- View --}}
                                    <button
                                        type="button"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-vendor-refund-btn"
                                        data-vendor-payment-id="{{ $row->id }}"
                                        title="View Details"
                                    >
                                        <i class="ri-eye-line"></i>
                                    </button>

                                    {{-- Download --}}
                                    <a
                                        href="{{ route('admin.operations.vendor-refunds.download', $row->id) }}"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full"
                                        title="Download Vendor Refund"
                                    >
                                        <i class="ri-download-line"></i>
                                    </a>

                                    {{-- Preview --}}
                                    <a
                                        href="{{ route('admin.operations.vendor-refunds.preview', $row->id) }}"
                                        target="_blank"
                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-secondary-full"
                                        title="Preview Vendor Refund"
                                    >
                                        <i class="ri-file-text-line"></i>
                                    </a>

                                    {{-- Mark Done: only when the vendor owes nothing more --}}
                                    @if ((float) $row->refund_due <= 0)
                                        <button
                                            type="button"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full mark-vendor-refund-done-btn"
                                            data-vendor-payment-id="{{ $row->id }}"
                                            title="Mark as Done"
                                        >
                                            <i class="ri-check-line"></i>
                                        </button>
                                    @else
                                        <button
                                            type="button"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-light opacity-50"
                                            title="Refund balance is still pending"
                                            disabled
                                        >
                                            <i class="ri-check-line"></i>
                                        </button>
                                    @endif

                                </div>

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="13" class="text-center py-6 text-gray-500">
                                No pending vendor refunds found.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

            @if (method_exists($vendorRefunds, 'links'))
                <div class="mt-4">
                    {{ $vendorRefunds->withQueryString()->links() }}
                </div>
            @endif

        </div>

    </div>

    {{-- Right-side drawer + Mark Done modal --}}
    @include('admin.pages.operations.vendor-refunds.partials.details')

@endsection


@push('scripts')
<script>
    const VR_URLS = {
        show: "{{ route('admin.operations.vendor-refunds.show', ':id') }}",
        update: "{{ route('admin.operations.vendor-refunds.update', ':id') }}",
        markDone: "{{ route('admin.operations.vendor-refunds.mark-done', ':id') }}",
        proof: "{{ route('admin.operations.vendor-refunds.proof.download', ':id') }}",
        storage: "{{ asset('storage') }}"
    };

    const VR_MAX_PROOF_SIZE = 2 * 1024 * 1024; // 2 MB, matches server validation

    let vrCurrentData = null;
    let vrPendingDoneId = null;

    function vrEsc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function vrMoney(value) {
        return '₹' + (parseFloat(value) || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function vrShowSuccess(message) {
        if (typeof showSuccessMessage === 'function') {
            showSuccessMessage('success', message);
        } else {
            alert('Success: ' + message);
        }
    }

    function vrShowError(message) {
        if (typeof showErrorModal === 'function') {
            showErrorModal('Error', message);
        } else {
            alert('Error: ' + message);
        }
    }

    function vrOpenOverlay(id) {
        const modal = document.getElementById(id);

        if (!modal) {
            return;
        }

        if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
            window.HSOverlay.open(modal);
        } else {
            modal.classList.remove('hidden');
            modal.classList.add('open');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Drawer
    |--------------------------------------------------------------------------
    */

    function openVendorRefundDrawer(vendorPaymentId) {
        vrShowLoading();

        $.ajax({
            url: VR_URLS.show.replace(':id', vendorPaymentId),
            method: 'GET',
            dataType: 'json',
            success: function (response) {
                if (response && response.success && response.data) {
                    vrCurrentData = response.data;
                    vrPopulate(response.data);
                    vrOpenOverlay('vendor-refund-preview-modal');
                } else {
                    vrShowError((response && response.message) || 'Invalid response format from server');
                }
            },
            error: function (xhr) {
                vrShowError('Error loading vendor refund details: ' + (xhr.responseJSON?.message || 'Unknown error'));
            }
        });
    }

    function vrShowLoading() {
        $('#vr-modal-vendor-name').text('Loading...');
        $('#vr-vendor-email, #vr-vendor-phone, #vr-vendor-city, #vr-vendor-address, #vr-vendor-bank').text('Loading...');
        $('#vr-travel-info-container').html('<div class="text-center py-4"><p class="text-gray-500">Loading travel information...</p></div>');
        $('#vr-payment-history-container').html('<div class="text-center py-4"><p class="text-gray-500">Loading payment history...</p></div>');
        $('#vr-refund-history-container').html('<div class="text-center py-4"><p class="text-gray-500">Loading refund history...</p></div>');
    }

    function vrPopulate(data) {
        const vendor = data.vendor || {};
        const refund = data.refund || null;

        $('#vr-modal-vendor-name').text(vendor.name || 'Unknown Vendor');

        // Status + progress
        $('#vr-status-badge')
            .text(data.is_completed ? 'Completed' : 'Pending')
            .toggleClass('bg-success/10 text-success', !!data.is_completed)
            .toggleClass('bg-warning/10 text-warning', !data.is_completed);

        const progress = parseInt(data.refund_progress, 10) || 0;
        $('#vr-progress-bar').css('width', progress + '%').attr('aria-valuenow', progress);
        $('#vr-progress-text').text(progress + '% Complete');

        // Refund information
        $('#vr-vendor-payment-id').val(data.id);
        $('#vr-original-amount').val(vrPlain(data.original_vendor_amount));
        $('#vr-cancellation-amount').val(data.cancellation_amount === null ? '-' : vrPlain(data.cancellation_amount));
        $('#vr-gross-paid').val(vrPlain(data.gross_paid));
        $('#vr-refund-received').val(vrPlain(data.refund_received));
        $('#vr-net-paid').val(vrPlain(data.net_paid));
        $('#vr-refund-due').val(vrPlain(data.refund_due));

        const refundType = refund ? (refund.refund_type || '') : '';
        const typeSelect = $('#vr-refund-type');
        typeSelect.find('option[data-extra]').remove();

        // Ride Status may store a type that is not in the standard list.
        if (refundType && typeSelect.find('option').filter(function () { return this.value === refundType; }).length === 0) {
            typeSelect.append($('<option>').attr('data-extra', '1').val(refundType).text(refundType));
        }

        typeSelect.val(refundType);
        $('#vr-refund-date').val(refund ? (refund.refund_date || '') : '');
        $('#vr-refund-reason').val(refund ? (refund.refund_reason || '') : '');
        $('#vr-refund-proof').val('');

        const proof = refund ? refund.refund_proof : null;
        $('#vr-proof-filename').text(proof ? proof.split('/').pop() : 'No file uploaded');
        vrUpdateProofPreview();

        // Vendor information
        $('#vr-vendor-email').text(vendor.email || '-');
        $('#vr-vendor-phone').text(vendor.contact_number || '-');
        $('#vr-vendor-city').text(vendor.city || '-');
        $('#vr-vendor-address').text(vendor.address || '-');
        $('#vr-vendor-bank').text(vendor.bank_details || '-');

        // Travel / service
        vrRenderTravel(data.rides || []);
        vrRenderServices(data);

        // History
        vrRenderPayments(data.payment_history || []);
        vrRenderRefunds(data.refund_history || []);
    }

    function vrPlain(value) {
        return (parseFloat(value) || 0).toLocaleString('en-IN', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function vrField(label, value, cls) {
        return `
            <div class="${cls || 'xl:col-span-6 col-span-12'}">
                <label class="ti-form-label mb-0 text-sm">${vrEsc(label)}</label>
                <p class="text-gray-800 dark:text-white">${vrEsc(value || '-')}</p>
            </div>`;
    }

    function vrRenderTravel(rides) {
        const container = $('#vr-travel-info-container');

        if (!rides.length) {
            container.html('<div class="text-center py-4"><p class="text-gray-500">No travel information available.</p></div>');
            return;
        }

        let html = '';

        rides.forEach(function (ride, index) {
            const fields =
                vrField('From Date', ride.from_date) +
                vrField('From Place', ride.from_place) +
                vrField('To Date', ride.to_date) +
                vrField('To Place', ride.to_place);

            if (rides.length === 1) {
                html += `<div class="grid grid-cols-12 gap-4">${fields}</div>`;
                return;
            }

            html += `
                <div class="border bg-white mb-2 p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-8 h-8 bg-theme text-white rounded-full flex items-center justify-center text-sm font-semibold">${index + 1}</div>
                        <span class="font-semibold">Trip Segment ${index + 1}: ${vrEsc(ride.from_place)} → ${vrEsc(ride.to_place)}</span>
                    </div>
                    <div class="grid grid-cols-12 gap-4">${fields}</div>
                </div>`;
        });

        container.html(html);
    }

    function vrRenderServices(data) {
        $('#vr-customer-name').text((data.customer && data.customer.name) || '-');
        $('#vr-service-date').text(data.service_date || '-');

        const block = function (title, items) {
            if (!items.length) {
                return '';
            }

            const rows = items.map(function (item) {
                return `
                    <tr>
                        <td class="text-start">${vrEsc(item.name)}</td>
                        <td class="text-end">${vrMoney(item.vendor_amount)}</td>
                    </tr>`;
            }).join('');

            return `
                <label class="ti-form-label mb-1 mt-2 text-sm">${title}</label>
                <table class="table table-bordered min-w-full">
                    <thead><tr><th class="text-start">Service</th><th class="text-end">Vendor Amount</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>`;
        };

        const services = data.services || [];
        const extras = data.extra_services || [];
        let html = block('Services', services) + block('Extra Services', extras);

        if (!html) {
            html = '<p class="text-gray-500">No service information available.</p>';
        }

        html += `<p class="text-sm mt-2">Total Vendor Amount: <span class="font-semibold">${vrMoney(data.original_vendor_amount)}</span></p>`;

        $('#vr-service-container').html(html);
    }

    function vrHistoryItem(index, left, right) {
        return `
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
                        <div>${left}</div>
                        <div class="text-end">${right}</div>
                    </div>
                </div>
            </div>`;
    }

    function vrRenderPayments(items) {
        const container = $('#vr-payment-history-container');

        if (!items.length) {
            container.html('<div class="text-center py-4"><p class="text-gray-500">No payment history found.</p></div>');
            return;
        }

        container.html(items.map(function (item, index) {
            const receipt = item.receipt
                ? `<p class="text-sm"><a class="text-primary" target="_blank" href="${VR_URLS.storage}/${encodeURI(item.receipt)}">View receipt</a></p>`
                : '';

            return vrHistoryItem(
                index,
                `<h5 class="font-semibold mb-1 leading-none text-[1.25rem]">${vrMoney(item.amount)}</h5>
                 <p class="text-sm text-gray-600 mb-1">Method: <span class="font-semibold">${vrEsc(item.payment_method)}</span></p>
                 <p class="text-sm text-gray-600 mb-1">Note: <span class="font-semibold">${vrEsc(item.narration)}</span></p>`,
                `<p class="text-sm text-gray-600">Date: ${vrEsc(item.paid_date)}</p>${receipt}`
            );
        }).join(''));
    }

    function vrRenderRefunds(items) {
        const container = $('#vr-refund-history-container');

        if (!items.length) {
            container.html('<div class="text-center py-4"><p class="text-gray-500">No refund history found.</p></div>');
            return;
        }

        container.html(items.map(function (item, index) {
            const badge = item.completed
                ? '<span class="badge !rounded-full bg-success/10 text-success mb-2">Completed</span>'
                : '<span class="badge !rounded-full bg-warning/10 text-warning mb-2">Pending</span>';

            const proof = item.has_proof
                ? `<p class="text-sm"><a class="text-primary" href="${VR_URLS.proof.replace(':id', encodeURIComponent(item.id))}">Download proof</a></p>`
                : '';

            return vrHistoryItem(
                index,
                `<h5 class="font-semibold mb-1 leading-none text-[1.25rem]">${vrMoney(item.amount)}</h5>
                 <p class="text-sm text-gray-600 mb-1">Type: <span class="font-semibold">${vrEsc(item.refund_type)}</span></p>
                 <p class="text-sm text-gray-600 mb-1">Reason: <span class="font-semibold">${vrEsc(item.refund_reason)}</span></p>`,
                `${badge}<p class="text-sm text-gray-600">Date: ${vrEsc(item.refund_date)}</p>${proof}`
            );
        }).join(''));
    }

    /*
    |--------------------------------------------------------------------------
    | Proof preview
    |--------------------------------------------------------------------------
    */

    function vrUpdateProofPreview() {
        const file = $('#vr-refund-proof')[0].files[0];
        const saved = vrCurrentData && vrCurrentData.refund ? vrCurrentData.refund.refund_proof : null;
        const btn = $('#vr-preview-proof-btn');

        if (file) {
            btn.data('preview-url', URL.createObjectURL(file)).show();
        } else if (saved) {
            btn.data('preview-url', VR_URLS.storage + '/' + encodeURI(saved)).show();
        } else {
            btn.removeData('preview-url').hide();
        }
    }

    $(document).on('change', '#vr-refund-proof', function () {
        const file = this.files && this.files[0];

        if (file && file.size > VR_MAX_PROOF_SIZE) {
            vrShowError('Selected proof is larger than 2 MB. Please choose a smaller file.');
            $(this).val('');
        }

        const chosen = this.files && this.files[0];
        const saved = vrCurrentData && vrCurrentData.refund ? vrCurrentData.refund.refund_proof : null;

        $('#vr-proof-filename').text(
            chosen ? chosen.name : (saved ? saved.split('/').pop() : 'No file uploaded')
        );

        vrUpdateProofPreview();
    });

    $(document).on('click', '#vr-preview-proof-btn', function () {
        const url = $(this).data('preview-url');

        if (url) {
            window.open(url, '_blank');
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Row actions
    |--------------------------------------------------------------------------
    */

    $(document).on('click', '.view-vendor-refund-btn', function () {
        openVendorRefundDrawer($(this).data('vendor-payment-id'));
    });

    /*
    |--------------------------------------------------------------------------
    | Save changes
    |--------------------------------------------------------------------------
    */

    $(document).on('submit', '#vendor-refund-form', function (event) {
        event.preventDefault();

        const vendorPaymentId = $('#vr-vendor-payment-id').val();

        if (!vendorPaymentId) {
            return;
        }

        const formData = new FormData(this);
        formData.append('_token', '{{ csrf_token() }}');

        $.ajax({
            url: VR_URLS.update.replace(':id', vendorPaymentId),
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function () {
                $('#vr-save-btn').prop('disabled', true).text('Saving...');
            },
            success: function (response) {
                if (response.success) {
                    vrShowSuccess(response.message);
                    setTimeout(() => location.reload(), 1500);
                } else {
                    vrShowError(response.message || 'Error saving vendor refund');
                }
            },
            error: function (xhr) {
                if (xhr.status === 413) {
                    vrShowError('The uploaded file is too large for the server to accept. Please reduce it to under 2 MB.');
                    return;
                }

                const errors = xhr.responseJSON?.errors;
                const firstError = errors ? Object.values(errors)[0][0] : null;

                vrShowError(firstError || xhr.responseJSON?.message || 'Error saving vendor refund');
            },
            complete: function () {
                $('#vr-save-btn').prop('disabled', false).text('Save changes');
            }
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Mark Done (same modal flow as Customer Refund)
    |--------------------------------------------------------------------------
    */

    function vrShowMarkDoneModal() {
        const modal = document.getElementById('vendor-refund-mark-done-modal');

        if (!modal) {
            return;
        }

        modal.classList.remove('hidden');
        modal.classList.add('open');

        const backdrop = document.createElement('div');
        backdrop.className = 'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80';
        backdrop.setAttribute('id', 'vendor-refund-mark-done-backdrop');
        backdrop.onclick = vrHideMarkDoneModal;
        document.body.appendChild(backdrop);
        document.body.style.overflow = 'hidden';
    }

    function vrHideMarkDoneModal() {
        const modal = document.getElementById('vendor-refund-mark-done-modal');
        const backdrop = document.getElementById('vendor-refund-mark-done-backdrop');

        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('open');
        }

        if (backdrop) {
            backdrop.remove();
        }

        document.body.style.overflow = '';
    }

    $(document).on('click', '.mark-vendor-refund-done-btn', function () {
        vrPendingDoneId = $(this).data('vendor-payment-id');
        vrShowMarkDoneModal();
    });

    $(document).on('click', '[data-hs-overlay="#vendor-refund-mark-done-modal"]', function () {
        vrHideMarkDoneModal();
        vrPendingDoneId = null;
    });

    $(document).on('click', '#vr-confirm-mark-done', function () {
        if (!vrPendingDoneId) {
            return;
        }

        const vendorPaymentId = vrPendingDoneId;

        vrHideMarkDoneModal();
        vrPendingDoneId = null;
        vrMarkDone(vendorPaymentId);
    });

    function vrMarkDone(vendorPaymentId) {
        const button = $(`.mark-vendor-refund-done-btn[data-vendor-payment-id="${vendorPaymentId}"]`);

        $.ajax({
            url: VR_URLS.markDone.replace(':id', vendorPaymentId),
            method: 'POST',
            dataType: 'json',
            data: {
                _token: '{{ csrf_token() }}'
            },
            beforeSend: function () {
                button.prop('disabled', true).html('<i class="ri-loader-2-line animate-spin"></i>');
            },
            success: function (response) {
                if (response.success) {
                    vrShowSuccess(response.message);
                    button.closest('tr').fadeOut(300);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    vrShowError(response.message || 'Failed to mark vendor refund as done');
                    button.prop('disabled', false).html('<i class="ri-check-line"></i>');
                }
            },
            error: function (xhr) {
                vrShowError(xhr.responseJSON?.message || 'Error marking vendor refund as done');
                button.prop('disabled', false).html('<i class="ri-check-line"></i>');
            }
        });
    }
</script>
@endpush

@include('admin.partials.modals.success-error-modals')
