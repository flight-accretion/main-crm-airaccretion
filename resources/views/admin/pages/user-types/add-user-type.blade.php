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
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-user-type-btn"
                                                    data-id="{{ $objUserType->id }}"
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

    <div id="edit-user-type" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-shield-user-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit User Role</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                                data-hs-overlay="#edit-user-type">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" width="8" height="8" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.26 1.01C0.35 0.91 0.48 0.86 0.61 0.86C0.74 0.86 0.87 0.91 0.97 1.01L3.61 3.65L6.26 1.01C6.36 0.91 6.55 0.85 6.71 0.89C6.87 0.92 7.02 1.05 7.08 1.16C7.11 1.23 7.12 1.29 7.12 1.36C7.12 1.42 7.1 1.49 7.08 1.55C7.05 1.61 7.01 1.67 6.96 1.71L4.32 4.36L6.96 7.01C7.06 7.1 7.11 7.23 7.11 7.36C7.1 7.49 7.05 7.61 6.96 7.71C6.87 7.8 6.74 7.85 6.61 7.85C6.48 7.85 6.35 7.8 6.26 7.71L3.61 5.07L0.97 7.71C0.87 7.8 0.74 7.85 0.61 7.85C0.48 7.85 0.36 7.8 0.26 7.71C0.17 7.61 0.12 7.49 0.12 7.36C0.12 7.23 0.17 7.1 0.26 7.01L2.9 4.36L0.26 1.71C0.17 1.61 0.12 1.49 0.12 1.36C0.12 1.23 0.17 1.1 0.26 1.01Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ti-offcanvas-body">
            <form class="ti-custom-validation" id="edit-user-type-form" method="POST" action="#" novalidate>
                @csrf
                @method('PUT')
                <div id="edit-user-type-errors" class="alert alert-danger mb-4 hidden"></div>
                <div class="grid grid-cols-12 sm:gap-6">
                    <div class="xl:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">User Role</label>
                        <input type="text" name="user_type" id="edit_user_type"
                            class="ti-form-input rounded-sm form-control-sm" required>
                    </div>

                    <div class="xl:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Parent</label>
                        <select name="parent_id" id="edit_parent_id" class="ti-form-select rounded-sm form-control-sm">
                            <option value="">-- Select Parent --</option>
                            @foreach ($arrobjParentUserTypes as $objParent)
                                <option value="{{ $objParent->id }}">{{ $objParent->user_type }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                        <textarea name="description" id="edit_description" class="ti-form-input w-full rounded-sm form-control-sm"
                            rows="3"></textarea>
                    </div>

                    <div class="xl:col-span-12 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                        <input type="hidden" name="status" value="0">
                        <input type="checkbox" name="status" value="1" id="edit_status" class="ti-switch">
                    </div>

                    <div class="xl:col-span-12 col-span-12 mt-4">
                        <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                            Update
                        </button>
                    </div>
                </div>
            </form>
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
            const editForm = document.getElementById('edit-user-type-form');
            const editErrorBox = document.getElementById('edit-user-type-errors');

            function removeUserTypeBackdrop() {
                const backdrop = document.getElementById('edit-user-type-backdrop');
                if (backdrop) {
                    backdrop.remove();
                }
            }

            function restoreUserTypePageScroll() {
                document.documentElement.classList.remove('overflow-hidden');
                document.body.classList.remove('ti-offcanvas-open', 'overflow-hidden');

                document.documentElement.style.overflow = '';
                document.documentElement.style.paddingRight = '';
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }

            function cleanupUserTypeOverlay() {
                const cleanup = function() {
                    removeUserTypeBackdrop();

                    const hasOpenOverlay = $('.hs-overlay').filter(function() {
                        return !this.classList.contains('hidden') || this.classList.contains('open');
                    }).length > 0;

                    if (!hasOpenOverlay) {
                        restoreUserTypePageScroll();
                    }
                };

                window.setTimeout(cleanup, 100);
                window.setTimeout(cleanup, 350);
            }

            function openUserTypeOverlay() {
                removeUserTypeBackdrop();

                if (window.HSOverlay) {
                    try {
                        window.HSOverlay.open('#edit-user-type');
                    } catch (error) {
                        window.HSOverlay.open(document.querySelector('#edit-user-type'));
                    }
                } else {
                    document.getElementById('edit-user-type').classList.remove('hidden');
                    document.getElementById('edit-user-type').classList.add('open');
                    document.body.classList.add('ti-offcanvas-open');
                }
            }

            function closeUserTypeOverlay() {
                if (window.HSOverlay) {
                    try {
                        window.HSOverlay.close('#edit-user-type');
                    } catch (error) {
                        window.HSOverlay.close(document.querySelector('#edit-user-type'));
                    }
                }

                document.getElementById('edit-user-type').classList.add('hidden');
                document.getElementById('edit-user-type').classList.remove('open');
                cleanupUserTypeOverlay();
            }

            function showEditError(message, errors = null) {
                let html = message || 'Unable to update user role.';

                if (errors) {
                    html = Object.values(errors).flat().join('<br>');
                }

                editErrorBox.innerHTML = html;
                editErrorBox.classList.remove('hidden');
            }

            function clearEditError() {
                editErrorBox.innerHTML = '';
                editErrorBox.classList.add('hidden');
            }

            // Event delegation for toggle buttons
            document.addEventListener('click', function(e) {
                const editBtn = e.target.closest('.edit-user-type-btn');
                if (editBtn) {
                    e.preventDefault();
                    clearEditError();

                    fetch(editBtn.href, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    })
                        .then(response => response.json().then(data => ({ ok: response.ok, data })))
                        .then(({ ok, data }) => {
                            if (!ok || !data.success) {
                                throw data;
                            }

                            const userType = data.userType;
                            editForm.action = `/admin/user-types/update/${encodeURIComponent(userType.id)}`;
                            document.getElementById('edit_user_type').value = userType.user_type || '';
                            document.getElementById('edit_parent_id').value = userType.parent_id || '';
                            document.getElementById('edit_description').value = userType.description || '';
                            document.getElementById('edit_status').checked = String(userType.status) === '1';
                            openUserTypeOverlay();
                        })
                        .catch(error => {
                            showEditError(error.message || 'Unable to load user role details.');
                            openUserTypeOverlay();
                        });
                }

                if (e.target.closest('[data-hs-overlay="#edit-user-type"], #edit-user-type-backdrop')) {
                    e.preventDefault();
                    closeUserTypeOverlay();
                }

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

            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                clearEditError();

                fetch(editForm.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: new FormData(editForm)
                })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (!ok || !data.success) {
                            throw data;
                        }

                        closeUserTypeOverlay();
                        window.location.reload();
                    })
                    .catch(error => {
                        showEditError(error.message, error.errors);
                    });
            });

        });


        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');
            const isEditingUserType = @json(isset($objFollowUp));

            if (isEditingUserType) {
                filterSection.show();
                icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
            } else {
                filterSection.hide();
                icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
            }

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
