@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor text-[1.125rem] font-semibold">
                {{ isset($objFollowUp) ? 'Edit User Role' : 'Add User Role' }}
            </h3>
        </div>
    </div>
    @if (session('success'))
        <div class="alert alert-success mb-4 grid-cols-12">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger mb-4 grid-cols-12">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header">
                    <div class="box-title">
                        Add User Role
                    </div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>
                <div class="box-body" id="filter-section">
                    <form class="ti-custom-validation" id="filter-form" method="POST"
                        action="{{ isset($objFollowUp) ? route('admin.user-types.update', $objFollowUp->id) : route('admin.user-types.store') }}"
                        novalidate>
                        @csrf
                        @if (isset($objFollowUp))
                            @method('PUT')
                        @endif
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">User Role</label>
                                <input type="text" name="user_type" class="ti-form-input rounded-sm form-control-sm"
                                    value="{{ old('user_type', $objFollowUp->user_type ?? '') }}">
                                @error('user_type')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Parent</label>
                                <select name="parent_id" class="ti-form-select rounded-sm form-control-sm">
                                    <option value="">-- Select Parent --</option>
                                    @foreach ($arrobjParentUserTypes as $objParent)
                                        <option value="{{ $objParent->id }}"
                                            {{ old('parent_id', $objFollowUp->parent_id ?? '') == $objParent->id ? 'selected' : '' }}>
                                            {{ $objParent->user_type }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                <textarea name="description" class="ti-form-input w-full rounded-sm form-control-sm" rows="1">{{ old('description', $objFollowUp->description ?? '') }}</textarea>
                                @error('description')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <input type="hidden" name="status" value="0">
                                <input type="checkbox" name="status" value="1" class="ti-switch"
                                    {{ old('status', $objFollowUp->status ?? 1) == 1 ? 'checked' : '' }}>
                                @error('status')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <div class="flex gap-2">
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                        {{ isset($objFollowUp) ? 'Update' : 'Submit' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box custom-box">
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable server-paginated" id="userRolesTable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th>Sr.No</th>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Assigned Under</th>
                                    <th>Created Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($arrobjUserTypes as $intKey => $objUserType)
                                    <tr>

                                        <td class="text-center">{{ ($arrobjUserTypes->firstItem() ?? 1) + $intKey }}</td>
                                        <td>{{ $objUserType->user_type }}</td>
                                        <td>{{ Str::limit($objUserType->description ?? '-', 50) }}</td>
                                        <td>{{ $objUserType->parent->user_type ?? '-' }}</td>
                                        <td class="text-center">{{ $objUserType->created_at?->format('d-m-Y') }}</td>
                                        <td class="text-center">
                                            @if ($objUserType->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack gap-3">
                                                <a href="{{ route('admin.user-types.edit', $objUserType->id) }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="ri-pencil-line"></i>
                                                </a>
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm {{ $objUserType->status ? 'ti-btn-danger-full' : 'ti-btn-success-full' }} open-confirm-modal"
                                                    data-id="{{ $objUserType->id }}"
                                                    data-name="{{ $objUserType->user_type }}"
                                                    data-status="{{ $objUserType->status }}" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $objUserType->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $objUserType->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($arrobjUserTypes->hasPages())
                    <div class="mt-4">
                        {{ $arrobjUserTypes->appends(request()->except('page'))->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Delete Modal -->
    <div id="custom-delete-alert" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-alert">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Status Change</h5>
                <p class="mb-4 text-gray-600 modal-message"></p>
                <form method="POST" id="delete-form">
                    @csrf
                    <div>
                        <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                            id="decline-delete">Cancel</button>
                        <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Confirm</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@stop
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get elements
            const modal = document.getElementById('custom-delete-alert');
            const form = document.getElementById('delete-form');
            const modalMessage = document.querySelector('.modal-message');

            // Event delegation for toggle buttons
            document.addEventListener('click', function(e) {
                // Handle toggle button click
                const toggleBtn = e.target.closest('.open-confirm-modal');
                if (toggleBtn) {

                    // Get data attributes
                    const id = toggleBtn.dataset.id;
                    const name = toggleBtn.dataset.name;
                    const status = toggleBtn.dataset.status;

                    // Set form action
                    form.action = `/admin/user-types/${id}/toggle-status`;

                    // Update modal message
                    const action = status === '1' ? 'deactivate' : 'activate';
                    modalMessage.textContent = `Are you sure you want to ${action} "${name}"?`;

                    // Show modal
                    modal.classList.remove('hidden');
                }

                // Handle modal close buttons
                if (e.target.closest('#close-alert, #decline-delete')) {
                    modal.classList.add('hidden');
                    console.log('Modal closed');
                }
            });

        });


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
