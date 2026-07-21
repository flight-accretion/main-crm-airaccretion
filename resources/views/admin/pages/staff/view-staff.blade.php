@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Staff Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="{{ route('admin.users.index') }}">
                    Staff
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50"
                aria-current="page">
                {{ $user->name }}
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Basic Information</h5>
                    <div class="flex gap-2">
                        <a aria-label="anchor" href="{{ route('admin.users.edit', $user->id) }}"
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" data-bs-toggle="tooltip"
                            data-bs-placement="top" title="Edit">
                            <i class="ri-edit-line"></i>
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 sm:gap-6">
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->name }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->email }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->address ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">User Type</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->userType->user_type ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->contact_number ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created At</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Updated At</label>
                            <p class="text-gray-800 dark:text-white">{{ $user->updated_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                            <p class="text-gray-800 dark:text-white">
                                @if ($user->status == 1)
                                    <span class="badge bg-success/10 text-success">Active</span>
                                @else
                                    <span class="badge bg-danger/10 text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Last Login</label>
                            <p class="text-gray-800 dark:text-white">
                                {{ $user->last_login_at ? $user->last_login_at->format('d-m-Y H:i') : 'Never logged in' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Status Toggle Confirmation Modal -->
    <div id="toggle-status-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Status toggle functionality
            let userIdToToggle = null;
            let currentStatus = null;
            const toggleBtn = document.querySelector('.toggle-staff-status');

            if (toggleBtn) {
                toggleBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    userIdToToggle = this.dataset.id;
                    currentStatus = this.dataset.status;
                    const staffName = this.dataset.name;

                    const action = currentStatus == 1 ? 'deactivate' : 'activate';
                    document.getElementById('status-modal-message').textContent =
                        `Are you sure you want to ${action} ${staffName}?`;
                    document.getElementById('toggle-status-modal').classList.remove('hidden');
                });
            }

            document.getElementById('confirm-status-toggle')?.addEventListener('click', function() {
                if (!userIdToToggle) return;

                fetch(`/admin/users/toggle-status/${userIdToToggle}`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            _method: 'PATCH'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('toggle-status-modal').classList.add('hidden');
                            showToast('success', data.message);

                            // Update the UI
                            const button = document.querySelector(
                                `.toggle-staff-status[data-id="${userIdToToggle}"]`);
                            const statusBadge = document.querySelector(
                                '.box-body .grid .col-span-12:nth-child(7) p');

                            if (button && statusBadge) {
                                const newStatus = data.new_status;
                                const icon = button.querySelector('i');

                                // Update button
                                icon.className = newStatus ? 'ri-close-line' : 'ri-check-line';
                                button.dataset.status = newStatus;
                                button.title = newStatus ? 'Deactivate' : 'Activate';

                                // Update status badge
                                statusBadge.innerHTML = newStatus ?
                                    '<span class="badge bg-success/10 text-success">Active</span>' :
                                    '<span class="badge bg-danger/10 text-danger">Inactive</span>';
                            }
                        } else {
                            showToast('error', data.message || 'Something went wrong');
                        }
                    })
                    .catch(error => {
                        showToast('error', 'Failed to update status');
                        console.error('Error:', error);
                    });
            });

            // Cancel toggle
            document.getElementById('cancel-toggle')?.addEventListener('click', function() {
                document.getElementById('toggle-status-modal').classList.add('hidden');
            });

            document.getElementById('close-toggle-modal')?.addEventListener('click', function() {
                document.getElementById('toggle-status-modal').classList.add('hidden');
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
    </script>
@endsection
