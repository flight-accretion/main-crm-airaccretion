@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                View Services</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="">
                    Services
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                View Services
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <!-- Search Filters -->
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
                    <form class="ti-custom-validation view-service-filters" method="GET"
                        action="{{ route('admin.services.index') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="service-name" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service
                                    Name</label>
                                <input type="text" name="service" class="ti-form-input rounded-sm form-control-sm"
                                    id="service-name" value="{{ request('service') }}" placeholder="Search by service name">
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="status"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <select name="status" class="ti-form-select rounded-sm form-control-sm" id="status">
                                    <option value="">All Statuses</option>
                                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
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

    <!-- Services Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header">
                    <div class="box-title">
                        All Services
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th>S.No</th>
                                    <th>Service Name</th>
                                    <th>Product</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    {{-- <th>Extra Services</th> --}}
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($services as $key => $service)
                                    <tr class="border-b border-defaultborder">

                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ $service->service }}</td>
                                        <td>
                                            @php
                                                $products = $service->getProducts();
                                            @endphp
                                            @if ($products->isNotEmpty())
                                                {{ $products->pluck('product')->join(', ') }}
                                            @else
                                                <span class="text-muted">No product assigned</span>
                                            @endif
                                        </td>
                                        <td>{{ Str::limit($service->description, 50) }}</td>
                                        <td class="text-center">
                                            {{ config('settings.currency_symbol') }}{{ number_format($service->service_amount, 2) }}
                                        </td>
                                        {{-- <td> {{ $service->extraServices->pluck('extra_service')->join(', ') }}</td> --}}
                                        <td class="text-center">
                                            @if ($service->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.services.view', $service->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.services.edit', $service->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm {{ $service->status ? 'ti-btn-danger-full' : 'ti-btn-success-full' }} toggle-service-status"
                                                    data-id="{{ $service->id }}" data-status="{{ $service->status }}"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $service->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $service->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </a>
                                                @if ($isSuperAdmin)
                                                    <form method="POST"
                                                        action="{{ route('admin.services.destroy', $service->id) }}"
                                                        style="display:inline;" class="delete-product-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button"
                                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-product-btn"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="Delete" data-product-name="{{ $service->service }}">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-product-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
            <button type="button" class="float-right text-gray-500 hover:text-black" id="close-delete-modal">
                <i class="bi bi-x"></i>
            </button>
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Delete</h5>
            <p class="mb-4 text-gray-600" id="delete-modal-message"></p>
            <div class="flex justify-center gap-4">
                <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                    id="cancel-delete">Cancel</button>
                <button type="button" class="ti-btn bg-danger text-white px-4 py-1"
                    id="confirm-delete-product">Delete</button>
            </div>
        </div>
    </div>
    <!-- Status Toggle Confirmation Modal -->
    <div id="toggle-status-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-toggle-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Status Change</h5>
                <p class="mb-4 text-gray-600" id="status-modal-message"></p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="cancel-toggle">Cancel</button>
                    <button type="button" class="ti-btn bg-primary text-white px-4 py-1"
                        id="confirm-status-toggle">Confirm</button>
                </div>
            </div>
        </div>
    </div>

@stop

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof jQuery == 'undefined') {
                console.error('jQuery is not loaded');
                return;
            }

            let serviceIdToToggle = null;
            let currentStatus = null;
            let serviceIdToDelete = null;
            let serviceNameToDelete = '';

            // Toggle service status functionality
            $(document).on('click', '.toggle-service-status', function(e) {
                e.preventDefault();
                serviceIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                const serviceName = $(this).closest('tr').find('td:nth-child(3)').text();

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(
                    `Are you sure you want to ${action} ${serviceName} service?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!serviceIdToToggle) return;

                $.ajax({
                    url: "{{ url('admin/services/toggle-status') }}/" + serviceIdToToggle,
                    type: 'POST',
                    data: {
                        _method: 'PATCH',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            // Update the UI without reloading
                            const button = $(
                                `.toggle-service-status[data-id="${serviceIdToToggle}"]`);
                            const newStatus = response.new_status;

                            // Update button icon, color, and tooltip
                            if (newStatus) {
                                button.removeClass('ti-btn-success-full').addClass(
                                    'ti-btn-danger-full');
                                button.find('i').removeClass('ri-check-line').addClass(
                                    'ri-lock-line');
                                button.attr('title', 'Deactivate').data('status', 1);
                            } else {
                                button.removeClass('ti-btn-danger-full').addClass(
                                    'ti-btn-success-full');
                                button.find('i').removeClass('ri-lock-line').addClass(
                                    'ri-check-line');
                                button.attr('title', 'Activate').data('status', 0);
                            }

                            // Update status badge
                            const statusCell = button.closest('tr').find('td:nth-child(6)');
                            if (newStatus) {
                                statusCell.html(
                                    '<span class="badge bg-success/10 text-success">Active</span>'
                                );
                            } else {
                                statusCell.html(
                                    '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                                );
                            }
                        } else {
                            showToast('error', response.message);
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message ||
                            "Something went wrong.");
                    }
                });
            });

            // Cancel toggle
            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                serviceIdToToggle = null;
                currentStatus = null;
            });

            // Delete service functionality
            $(document).on('click', '.delete-product-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                serviceIdToDelete = form.attr('action').split('/').pop();
                serviceNameToDelete = $(this).data('product-name');
                $('#delete-modal-message').text(
                    `Are you sure you want to delete "${serviceNameToDelete}" service?`);
                $('#delete-product-modal').removeClass('hidden');
            });

            $('#confirm-delete-product').click(function() {
                if (!serviceIdToDelete) return;
                $.ajax({
                    url: "{{ url('admin/services') }}/" + serviceIdToDelete,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#delete-product-modal').addClass('hidden');
                            showToast('success', response.message ||
                                'Service deleted successfully.');
                            // Remove the row from the table
                            $(`.delete-product-btn[data-product-name="${serviceNameToDelete}"]`)
                                .closest('tr').remove();
                        } else {
                            showToast('error', response.message || 'Failed to delete service.');
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message ||
                        "Something went wrong.");
                        console.error(xhr);

                    }
                });
            });

            // Cancel delete
            $('#cancel-delete, #close-delete-modal').click(function() {
                $('#delete-product-modal').addClass('hidden');
                serviceIdToDelete = null;
                serviceNameToDelete = '';
            });

            // Initialize tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = '{{ route('admin.services.index') }}';
        }

        // Toggle filters
        $(document).ready(function() {
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
        });
    </script>
@endpush
