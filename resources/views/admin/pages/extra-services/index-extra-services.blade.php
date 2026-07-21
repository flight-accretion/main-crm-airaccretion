@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">

    </div>
    <!-- Page Header Close -->

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

    <!-- Button to add new extra service -->
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="add-extra-service-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <i class="bx bx-cog"></i>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage Extra Services
                                        </h5>
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="add-extra-service-form">
                                                <svg class="hs-accordion-active:hidden block size-4 ml-2"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M5 12h14" />
                                                    <path d="M12 5v14" />
                                                </svg>
                                                <svg class="hs-accordion-active:block hidden size-4 ml-2"
                                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round">
                                                    <path d="M5 12h14" />
                                                </svg>
                                                Add Extra Service
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-extra-service-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300"
                            aria-labelledby="add-extra-service-accordion">
                            <form action="{{ route('admin.extra-services.store') }}" method="POST"
                                enctype="multipart/form-data" id="add-extra-service-form-element">
                                @csrf
                                <div class="box-body">
                                    <div class="grid lg:grid-cols-4 gap-6">
                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Extra Service Name</label>
                                            <input type="text" name="extra_service_name"
                                                class="ti-form-input extra-service-name rounded-sm form-control-sm hidden"
                                                placeholder="Service Name" value="{{ old('extra_service_name') }}">
                                            @error('extra_service_name')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Extra Service Amount *</label>
                                            <input type="number" name="extra_service_amount"
                                                class="ti-form-input extra-service-amount rounded-sm form-control-sm"
                                                placeholder="Service Amount" value="{{ old('extra_service_amount') }}"
                                                min="0" required>
                                            @error('extra_service_amount')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Description</label>
                                            <textarea class="ti-form-input extra-service-description w-full rounded-sm form-control-sm"
                                                name="extra_service_description" rows="1">{{ old('extra_service_description') }}</textarea>
                                            @error('extra_service_description')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit"
                                        class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
                    <form class="ti-custom-validation view-extra-service-filters" method="GET"
                        action="{{ route('admin.extra-services.index') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="extra-service-name"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Service
                                    Name</label>
                                <input type="text" name="extra-service" class="ti-form-input rounded-sm form-control-sm"
                                    id="extra-service-name" value="{{ request('extra-service') }}"
                                    placeholder="Search by extra service name">
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="status"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <select name="status" class="ti-form-select rounded-sm form-control-sm" id="status">
                                    <option value="">All Statuses</option>
                                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active
                                    </option>
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
                        All Extra Services
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th>S.No</th>
                                    <th>Extra Service Name</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($extraServices as $key => $extraService)
                                    <tr class="border-b border-defaultborder">

                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ $extraService->extra_service }}</td>
                                        <td>{{ Str::limit($extraService->description, 50) }}</td>
                                        <td class="text-center">
                                            {{ config('settings.currency_symbol') }}{{ number_format($extraService->extra_service_amount, 2) }}
                                        </td>
                                        <td class="text-center">
                                            @if ($extraService->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-extra-service-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View"
                                                    data-extra-service-id="{{ $extraService->id }}">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-extra-service-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"
                                                    data-extra-service-id="{{ $extraService->id }}">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm {{ $extraService->status ? 'ti-btn-danger-full' : 'ti-btn-success-full' }} toggle-extra-service-status"
                                                    data-id="{{ $extraService->id }}"
                                                    data-status="{{ $extraService->status }}" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $extraService->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $extraService->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </a>
                                                @if ($isSuperAdmin)
                                                    <form method="POST"
                                                        action="{{ route('admin.extra-services.destroy', $extraService->id) }}"
                                                        style="display:inline;" class="delete-extra-service-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button"
                                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-extra-service-btn"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="Delete"
                                                            data-extra-service-name="{{ $extraService->extra_service }}">
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
    <div id="delete-extra-service-modal"
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
                    id="confirm-delete-extra-service">Delete</button>
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

    <!-- Edit Extra Service Modal -->
    <div id="edit-extra-service" class="edit-extra-service hs-overlay hidden ti-offcanvas ti-offcanvas-right"
        tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="bx bx-cog"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Extra Service</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#edit-extra-service">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ti-offcanvas-body edit-extra-service-body">
            <form id="edit-extra-service-form" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid lg:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Service
                                            Name</label>
                                        <input type="text" name="extra_service_name" id="edit_extra_service"
                                            class="ti-form-input rounded-sm form-control-sm" required>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                        <input type="text" name="extra_service_description" id="edit_description"
                                            class="ti-form-input rounded-sm form-control-sm" required>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Service
                                            Amount</label>
                                        <input type="number" name="extra_service_amount" id="edit_extra_service_amount"
                                            class="ti-form-input rounded-sm form-control-sm" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <button type="submit"
                        class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Extra Service Modal -->
    <div id="view-extra-service" class="view-extra-service hs-overlay hidden ti-offcanvas ti-offcanvas-right"
        tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="bx bx-cog"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">View Extra Service Details</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#view-extra-service">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ti-offcanvas-body view-extra-service-body">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid lg:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services
                                        Name</label>
                                    <p class="text-gray-800 dark:text-white" id="view_extra_service">
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                    <p class="text-gray-800 dark:text-white" id="view_description">
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Service
                                        Amount</label>
                                    <p class="text-gray-800 dark:text-white" id="view_extra_service_amount"></p>
                                </div>

                                <div class="space-y-2">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                    <span class="" id="view_status"></span>
                                </div>

                                <div class="space-y-2">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created Date</label>
                                    <p class="text-gray-800 dark:text-white" id="view_created_at">
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof jQuery == 'undefined') {
                return;
            }

            let serviceIdToToggle = null;
            let currentStatus = null;
            // Toggle service status functionality
            $(document).on('click', '.toggle-extra-service-status', function(e) {
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
                    url: "{{ url('admin/extra-services/toggle-status') }}/" + serviceIdToToggle,
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
                                `.toggle-extra-service-status[data-id="${serviceIdToToggle}"]`
                            );
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
                            const statusCell = button.closest('tr').find('td:nth-child(5)');
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

            let serviceIdToDelete = null;
            let serviceNameToDelete = '';
            // Delete service functionality
            $(document).on('click', '.delete-extra-service-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');
                serviceIdToDelete = form.attr('action').split('/').pop();
                serviceNameToDelete = $(this).data('extra-service-name');
                $('#delete-modal-message').text(
                    `Are you sure you want to delete "${serviceNameToDelete}" service?`);
                $('#delete-extra-service-modal').removeClass('hidden');
            });

            $('#confirm-delete-extra-service').click(function() {
                if (!serviceIdToDelete) return;
                $.ajax({
                    url: "{{ url('admin/extra-services') }}/" + serviceIdToDelete,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#delete-extra-service-modal').addClass('hidden');
                            showToast('success', response.message ||
                                'Service deleted successfully.');
                            // Remove the row from the table
                            $(`.delete-extra-service-btn[data-extra-service-name="${serviceNameToDelete}"]`)
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
                $('#delete-extra-service-modal').addClass('hidden');
                serviceIdToDelete = null;
                serviceNameToDelete = '';
            });

            // ===== VIEW EXTRA SERVICE FUNCTIONALITY =====

            // View Extra service button click handler
            $(document).on('click', '.view-extra-service-btn', function() {
                const extraServiceId = $(this).data('extra-service-id');
                loadExtraServiceViewData(extraServiceId);
            });

            function loadExtraServiceViewData(extraServiceId) {
                $.get(`/admin/extra-services/${extraServiceId}/view-modal`, function(response) {
                    if (response.success) {
                        populateViewModal(response);

                        // Show the modal
                        $('#view-extra-service').removeClass('hidden').addClass('open');
                        $('body').addClass('ti-offcanvas-open');
                    } else {
                        console.error('View extra-service response failed:', response);
                        alert('Error loading extra-service data');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error loading extra-service view data:', textStatus, errorThrown);
                    alert('Error loading extra-service data: ' + textStatus);
                });
            }

            function populateViewModal(response) {
                const extraService = response.extraService;

                // Populate basic fields
                $('#view_extra_service').text(extraService.extra_service);
                $('#view_description').text(extraService.description || 'N/A');
                $('#view_extra_service_amount').text(extraService.extra_service_amount);

                // Status
                const statusHtml = extraService.status == 1 ?
                    '<span class="badge bg-success/10 text-success">Active</span>' :
                    '<span class="badge bg-danger/10 text-danger">Inactive</span>';
                $('#view_status').html(statusHtml);

                // Created date
                const createdDate = new Date(extraService.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                $('#view_created_at').text(createdDate);
            }

            // Close view modal handler
            $(document).on('click', '[data-hs-overlay="#view-extra-service"]', function() {
                $('#view-extra-service').addClass('hidden').removeClass('open');
                $('body').removeClass('ti-offcanvas-open');
            });

            // ===== EDIT EXTRA SERVICE FUNCTIONALITY =====
            let editIti;
            let currentExtraServiceData = null;

            // Edit extra service button click handler
            $(document).on('click', '.edit-extra-service-btn', function() {
                const extraServiceId = $(this).data('extra-service-id');
                loadExtraServiceData(extraServiceId);
            });

            function loadExtraServiceData(extraServiceId) {
                $.get(`/admin/extra-services/${extraServiceId}/view-modal`, function(response) {
                    if (response.success) {
                        currentExtraServiceData = response.extraService;
                        populateEditForm(response);

                        // Show the modal
                        $('#edit-extra-service').removeClass('hidden').addClass('open');
                        $('body').addClass('ti-offcanvas-open');

                    } else {
                        console.error('Edit extra service response failed:', response);
                        alert('Error loading extra service data');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error loading extra service data:', textStatus, errorThrown);
                    alert('Error loading extra service data: ' + textStatus);
                });
            }

            function populateEditForm(response) {
                const extraService = response.extraService;

                // Set form action
                $('#edit-extra-service-form').attr('action', `/admin/extra-services/${extraService.id}/`);

                // Populate basic fields
                $('#edit_extra_service').val(extraService.extra_service);
                $('#edit_description').val(extraService.description);
                $('#edit_extra_service_amount').val(extraService.extra_service_amount);
            }

            // Close edit modal handler
            $(document).on('click', '[data-hs-overlay="#edit-extra-service"]', function() {
                $('#edit-extra-service').addClass('hidden').removeClass('open');
                $('body').removeClass('ti-offcanvas-open');
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
            window.location.href = '{{ route('admin.extra-services.index') }}';
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
