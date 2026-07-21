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

    {{-- Include reusable success/error modals so we can show validation errors in a modal and reopen edit modal --}}
    @include('admin.partials.modals.success-error-modals')

    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div id="add-staff-accordion" class="hs-accordion " aria-labelledby="add-staff-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                            viewBox="0 0 24 24" width="24px" fill="#000000">
                                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                                            <path
                                                d="M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z">
                                            </path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage Staff</h5>

                                        <button type="button"
                                            class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                            aria-controls="add-staff-form">
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
                                            </svg>Add Staff
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-staff-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300  @if ($errors->add->any()) !block @else hidden @endif"
                            aria-labelledby="add-staff-accordion">
                            <div class="box-body">
                                <form class="ti-custom-validation" action="{{ route('admin.users.store') }}" method="POST"
                                    novalidate id="staff-form">
                                    @csrf
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <!-- Name Field -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Name <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" name="name"
                                                class="ti-form-input rounded-sm form-control-sm" value="{{ old('name') }}"
                                                placeholder="Enter Name" required minlength="2" maxlength="255"
                                                pattern="[a-zA-Z\s\'-\.]+">
                                            @error('name', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Email Field -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Email <span
                                                    class="text-red-500">*</span></label>
                                            <input type="email" name="email"
                                                class="ti-form-input rounded-sm form-control-sm" value="{{ old('email') }}"
                                                placeholder="Enter Email" required maxlength="320">
                                            @error('email', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Password Field -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Password <span
                                                    class="text-red-500">*</span></label>
                                            <div class="relative">
                                                <input type="password" name="password" id="add_password"
                                                    class="ti-form-input rounded-sm form-control-sm"
                                                    placeholder="Enter Password" required minlength="8" maxlength="128">
                                                <button type="button"
                                                    class="absolute top-0 end-0 p-2 rounded-e-md dark:focus:outline-none dark:focus:ring-0 dark:shadow-none dark:focus:ring-transparent"
                                                    onclick="togglePassword('add_password', 'toggleAddPasswordIcon')">
                                                    <i class="ri-eye-line" id="toggleAddPasswordIcon"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Must contain uppercase, lowercase, number, and special
                                                character</small>
                                            @error('password', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Phone Number -->
                                        <div class="xl:col-span-4 col-span-12">
                                            <label class="ti-form-label mb-0">Contact Number <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="contact_number" value="{{ old('contact_number') }}"
                                                placeholder="Enter Contact Number" required minlength="10" maxlength="15"
                                                pattern="[\+]?[0-9\-\(\)\s]+">
                                            @error('contact_number', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Address Field -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Address <span
                                                    class="text-red-500">*</span></label>
                                            <textarea name="address" class="ti-form-input w-full rounded-sm form-control-sm" rows="1"
                                                placeholder="Enter Address" required minlength="10" maxlength="500">{{ old('address') }}</textarea>
                                            @error('address', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- User Type Hierarchy Dropdowns -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">User Type (Level 1) <span
                                                    class="text-red-500">*</span></label>
                                            <select id="add_user_type_level1"
                                                class="add-user-type-select ti-form-select w-full rounded-sm form-control-sm"
                                                data-level="1" data-next-level="2" required>
                                                <option value="">Select user type</option>
                                                @foreach ($userTypes as $userType)
                                                    <option value="{{ $userType->id }}"
                                                        {{ old('user_type_id') == $userType->id ? 'selected' : '' }}>
                                                        {{ $userType->user_type }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('user_type_id', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- User Type Level 2 -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2 add-user-type-container"
                                            id="add_user_type_level2_container" style="display: none;">
                                            <label class="ti-form-label mb-0">User Type (Level 2)</label>
                                            <select id="add_user_type_level2"
                                                class="add-user-type-select ti-form-input w-full" data-level="2"
                                                data-next-level="3">
                                                <option value="">Select user type</option>
                                            </select>
                                        </div>

                                        <!-- User Type Level 3 -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2 add-user-type-container"
                                            id="add_user_type_level3_container" style="display: none;">
                                            <label class="ti-form-label mb-0">User Type (Level 3)</label>
                                            <select id="add_user_type_level3"
                                                class="add-user-type-select ti-form-input w-full" data-level="3">
                                                <option value="">Select user type</option>
                                            </select>
                                        </div>

                                        <!-- Hidden field for final user type ID -->
                                        <input type="hidden" id="add_user_type_id" name="user_type_id" required>

                                        <!-- Joining Date -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Joining Date</label>
                                            <input type="date" name="joining_date"
                                                class="ti-form-input w-full rounded-sm form-control-sm"
                                                value="{{ old('joining_date') }}" max="{{ date('Y-m-d') }}">
                                            @error('joining_date', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Status Field -->
                                        <div class="xl:col-span-4 col-span-12 space-y-2">
                                            <label class="ti-form-label mb-0">Status</label>
                                            <div class="flex items-center">
                                                <input type="hidden" name="status" value="0">
                                                <input type="checkbox" name="status" class="ti-switch" value="1"
                                                    {{ old('status', 1) ? 'checked' : '' }}>
                                                <label class="ms-2">Active</label>
                                            </div>
                                            @error('status', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-12 col-span-12 mt-4">
                                            <button type="submit"
                                                class="ti-btn bg-theme ti-btn-primary-full">Submit</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Staff Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">
                        All Staff Members
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">S.No</th>
                                    <th data-priority="2">Name</th>
                                    <th data-priority="3">Email</th>
                                    <th data-priority="4">User Type</th>
                                    <th data-priority="5">Phone</th>
                                    <th data-priority="6">Address</th>
                                    <th data-priority="7">Status</th>
                                    <th data-priority="8">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($users as $key => $user)
                                    <tr class="border-b border-defaultborder">
                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ $user->name }}</td>
                                        <td>{{ $user->email }}</td>
                                        <td>{{ $user->userType->user_type ?? 'N/A' }}</td>
                                        <td class="text-center">{{ $user->contact_number ?? 'N/A' }}</td>
                                        <td>{{ Str::limit($user->address ?? 'N/A', 50) }}</td>
                                        <td class="text-center">
                                            @if ($user->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-staff-btn"
                                                    data-id="{{ $user->id }}"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-staff-btn"
                                                    data-id="{{ $user->id }}"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <!-- <a aria-label="anchor" href="javascript:void(0);"
                                                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-staff"
                                                                            data-id="{{ $user->id }}"
                                                                            data-name="{{ $user->name }}"
                                                                            data-bs-toggle="tooltip" data-bs-placement="top" title="Delete">
                                                                            <i class="ri-delete-bin-line"></i>
                                                                        </a> -->
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class=" toggle-staff-status ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full"
                                                    data-id="{{ $user->id }}" data-status="{{ $user->status }}"
                                                    data-name="{{ $user->name }}" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $user->status ? 'Deactivate' : 'Activate' }}">
                                                    <i class="{{ $user->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </a>
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
    <div id="delete-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-delete-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Deletion</h5>
                <p class="mb-4 text-gray-600" id="delete-modal-message"></p>
                <form id="delete-form" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <div>
                        <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                            id="cancel-delete">Cancel</button>
                        <button type="submit" class="ti-btn bg-danger text-white px-4 py-1">Delete</button>
                    </div>
                </form>
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

    <!-- View Staff Modal -->
    <div id="view-staff" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-eye-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">View Staff Details</h5>
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#view-staff">
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
        <div class="ti-offcanvas-body">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 sm:gap-6">
                                <!-- Name -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_name">-</p>
                                </div>

                                <!-- Email -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_email">-</p>
                                </div>

                                <!-- Contact Number -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_contact">-
                                    </p>
                                </div>

                                <!-- Address -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_address">-</p>
                                </div>

                                <!-- User Type -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">User Type</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_user_type">-
                                    </p>
                                </div>

                                <!-- Joining Date -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Joining Date</label>
                                    <p class="text-gray-800 dark:text-white" id="view_staff_joining_date">
                                        -</p>
                                </div>

                                <!-- Status -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-6 col-span-6">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                    <div class="flex items-center">
                                        <span class="badge bg-success/10 text-success" id="view_staff_status">-</span>
                                    </div>
                                </div>
        
                                <!-- Password (View) -->
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Password</label>
                                    <div class="flex items-center gap-2">
                                        <p class="text-gray-800 dark:text-white" id="view_staff_password">••••••••</p>
                                        @if(in_array(optional(auth()->user()->userType)->user_type, [\App\Models\UserType::SUPER_ADMIN, \App\Models\UserType::ADMIN]))
                                            <button type="button" class="ti-btn ti-btn-ghost p-0" id="view_toggle_password_btn" onclick="toggleViewPassword()" title="Show/Hide Password">
                                                <!-- <i class="ri-eye-line" id="view_toggle_password_icon"></i> -->
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div id="edit-staff" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-edit-box-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Staff</h5>
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#edit-staff">
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
    <form class="ti-custom-validation edit-staff-form" method="POST" action="{{ old('edit_user_id') ? url('admin/users/' . old('edit_user_id')) : '' }}" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_user_id" id="edit_user_id" value="{{ old('edit_user_id') }}">
            <div class="ti-offcanvas-body">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <!-- Name -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_name" class="ti-form-label mb-0">Name <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                            id="edit_name" name="name" value="{{ old('name', '') }}" required minlength="2"
                                                            maxlength="255" pattern="[a-zA-Z\s\'\-\.]+">
                                        @if(($errors->edit->has('name') ?? false) || $errors->has('name'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('name') ?? $errors->first('name') }}</span>
                                        @endif
                                    </div>

                                    <!-- Email (Read-only) -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_email" class="ti-form-label mb-0">Email <span
                                                class="text-red-500">*</span></label>
                                        <input type="email" class="ti-form-input w-full rounded-sm form-control-sm"
                                            value="{{ old('email', '') }}" id="edit_email" name="email" readonly required>
                                        <small class="text-muted">Email cannot be changed</small>
                                        @if(($errors->edit->has('email') ?? false) || $errors->has('email'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('email') ?? $errors->first('email') }}</span>
                                        @endif
                                    </div>

                                    <!-- Contact Number -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_contact_number" class="ti-form-label mb-0">Contact Number <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                            id="edit_contact_number" value="{{ old('contact_number', '') }}" name="contact_number" required
                                            minlength="10" maxlength="15" pattern="[\+]?[0-9\-\(\)\s]+">
                                        @if(($errors->edit->has('contact_number') ?? false) || $errors->has('contact_number'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('contact_number') ?? $errors->first('contact_number') }}</span>
                                        @endif
                                    </div>

                                    <!-- Address -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_address" class="ti-form-label mb-0">Address <span
                                                class="text-red-500">*</span></label>
                                        <textarea class="ti-form-input w-full rounded-sm form-control-sm" id="edit_address" name="address" rows="1"
                                            required minlength="10" maxlength="500">{{ old('address', '') }}</textarea>
                                        @if(($errors->edit->has('address') ?? false) || $errors->has('address'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('address') ?? $errors->first('address') }}</span>
                                        @endif
                                    </div>

                                    <!-- User Type Hierarchy Dropdowns for Edit -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_user_type_level1" class="ti-form-label mb-0">User Type (Level 1)
                                            <span class="text-red-500">*</span></label>
                                        <select class="ti-form-select rounded-sm form-control-sm edit-user-type-select"
                                            id="edit_user_type_level1" data-level="1" data-next-level="2" required>
                                            <option value="">Select user type</option>
                                            @foreach ($userTypes as $userType)
                                                <option value="{{ $userType->id }}">
                                                    {{ $userType->user_type }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(($errors->edit->has('user_type_id') ?? false) || $errors->has('user_type_id'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('user_type_id') ?? $errors->first('user_type_id') }}</span>
                                        @endif
                                    </div>

                                    <!-- User Type Level 2 for Edit -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 edit-user-type-container"
                                        id="edit_user_type_level2_container" style="display: none;">
                                        <label for="edit_user_type_level2" class="ti-form-label mb-0">User Type (Level
                                            2)</label>
                                        <select class="ti-form-select rounded-sm form-control-sm edit-user-type-select"
                                            id="edit_user_type_level2" data-level="2" data-next-level="3">
                                            <option value="">Select user type</option>
                                        </select>
                                    </div>

                                    <!-- User Type Level 3 for Edit -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 edit-user-type-container"
                                        id="edit_user_type_level3_container" style="display: none;">
                                        <label for="edit_user_type_level3" class="ti-form-label mb-0">User Type (Level
                                            3)</label>
                                        <select class="ti-form-select rounded-sm form-control-sm edit-user-type-select"
                                            id="edit_user_type_level3" data-level="3">
                                            <option value="">Select user type</option>
                                        </select>
                                    </div>

                                    <!-- Hidden User Type ID for Edit -->
                                        <input type="hidden" id="edit_user_type_id" name="user_type_id" value="{{ old('user_type_id', '') }}">

                                    <!-- Joining Date -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_joining_date" class="ti-form-label mb-0">Joining Date</label>
                                        <input type="date" class="ti-form-input w-full rounded-sm form-control-sm"
                                            id="edit_joining_date" name="joining_date" value="{{ old('joining_date', '') }}"
                                            max="{{ date('Y-m-d') }}">
                                        @if(($errors->edit->has('joining_date') ?? false) || $errors->has('joining_date'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('joining_date') ?? $errors->first('joining_date') }}</span>
                                        @endif
                                    </div>

                                    <!-- Status -->
                                    <input type="hidden" name="status" value="0">
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_status" class="ti-form-label mb-0">Status</label>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="edit_status" name="status" class="ti-switch"
                                                value="1" {{ old('status') ? 'checked' : '' }}>
                                            <label for="edit_status" class="ms-2">Active</label>
                                        </div>
                                        @if(($errors->edit->has('status') ?? false) || $errors->has('status'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('status') ?? $errors->first('status') }}</span>
                                        @endif
                                    </div>

                                    <!-- Password (Edit) -->
                                    <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="edit_password" class="ti-form-label mb-0">Password <small class="text-muted">(Leave blank to keep existing)</small></label>
                                        <div class="relative">
                                            <input type="password" class="ti-form-input w-full rounded-sm form-control-sm" id="edit_password" name="password" placeholder="Enter new password to change" minlength="8" maxlength="128">
                                            <button type="button" class="absolute top-0 end-0 p-2 rounded-e-md" onclick="togglePasswordField('edit_password','edit_toggle_password_icon')">
                                                <i class="ri-eye-line" id="edit_toggle_password_icon"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted">Must contain uppercase, lowercase, number, and special character</small>
                                        @if(($errors->edit->has('password') ?? false) || $errors->has('password'))
                                            <span class="text-danger text-xs">{{ $errors->edit->first('password') ?? $errors->first('password') }}</span>
                                        @endif
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="xl:col-span-12 col-span-12 mt-4">
                                        <button type="submit"
                                            class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Update
                                            Staff</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            // Toggle staff status button click
            $(document).on('click', '.toggle-staff-status', function(e) {
                e.preventDefault();
                staffIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                const staffName = $(this).data('name');
                const action = currentStatus ? 'deactivate' : 'activate';

                $('#status-modal-message').text(`Are you sure you want to ${action} ${staffName}?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            // Confirm status toggle
            $('#confirm-status-toggle').click(function() {
                if (!staffIdToToggle) return;

                $.ajax({
                    url: <?php echo json_encode(route('admin.users.toggle-status', '')); ?> + "/" + staffIdToToggle,
                    type: 'PATCH',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            const button = $(
                                `.toggle-staff-status[data-id="${staffIdToToggle}"]`);
                            const newStatus = response.status;
                            button.find('i')
                                .removeClass(newStatus ? 'ri-check-line' : 'ri-lock-line')
                                .addClass(newStatus ? 'ri-lock-line' : 'ri-check-line');

                            button.attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            const statusCell = button.closest('tr').find('td:nth-child(7)');
                            statusCell.html(
                                newStatus ?
                                '<span class="badge bg-success/10 text-success">Active</span>' :
                                '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                            );
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
                staffIdToToggle = null;
                currentStatus = null;
            });

            // Auto-open add form accordion if validation errors exist
            @if ($errors->add->any())
                setTimeout(() => {
                    $('#add-staff-accordion .hs-accordion-toggle').click();
                    $('#add-staff-form').removeClass('hidden').show();
                }, 300);
            @endif

          

            // Tooltip init (optional)
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
                    new bootstrap.Tooltip(el);
                });
            }

            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className =
                    `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${type === 'success' ? 'bg-green-500' : 'bg-red-500'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
    </script>


    <!-- Add staff script -->
    <script>
        $(document).ready(function() {
            // ADD FORM HIERARCHY LOGIC

            // Update hidden input with the deepest selected user type for ADD form
            function updateAddFinalUserTypeId() {
                let finalId = '';
                $('.add-user-type-select').each(function() {
                    const val = $(this).val();
                    if (val) finalId = val;
                });
                $('#add_user_type_id').val(finalId);
                console.log('Add Final User Type ID:', finalId);
            }

            // Reset subsequent dropdown levels for ADD form
            function resetAddSubsequentLevels(startLevel) {
                for (let i = startLevel; i <= 3; i++) {
                    const $select = $(`#add_user_type_level${i}`);
                    const $container = $(`#add_user_type_level${i}_container`);

                    $select.html('<option value="">Select user type</option>');
                    $container.hide();
                }
                updateAddFinalUserTypeId();
            }

            // Fetch user types dynamically for ADD form
            function fetchAddUserTypes(parentId, level) {
                if (!parentId) {
                    resetAddSubsequentLevels(level);
                    return;
                }

                $.ajax({
                    url: <?php echo json_encode(route('admin.users.user-types.by-parent')); ?>,
                    method: 'GET',
                    data: {
                        parent_id: parentId
                    },
                    beforeSend: function() {
                        const $select = $(`#add_user_type_level${level}`);
                        $select.html('<option value="">Loading...</option>');
                    },
                    success: function(response) {
                        const $select = $(`#add_user_type_level${level}`);
                        const $container = $(`#add_user_type_level${level}_container`);

                        $select.html('<option value="">Select user type</option>');

                        if (response && response.length > 0) {
                            response.forEach(function(userType) {
                                $select.append(
                                    `<option value="${userType.id}">${userType.user_type}</option>`
                                );
                            });
                            $container.show();
                        } else {
                            $container.hide();
                        }

                        resetAddSubsequentLevels(level + 1);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error fetching add user types:', error);
                        const $select = $(`#add_user_type_level${level}`);
                        $select.html('<option value="">Error loading options</option>');
                        alert('Failed to load user types. Please try again.');
                    }
                });
            }

            // Handle ADD form user type selection
            $('.add-user-type-select').on('change', function() {
                const $this = $(this);
                const level = parseInt($this.data('level'));
                const nextLevel = level + 1;
                const selectedId = $this.val();


                resetAddSubsequentLevels(nextLevel);

                if (selectedId && nextLevel <= 3) {
                    fetchAddUserTypes(selectedId, nextLevel);
                } else {
                    updateAddFinalUserTypeId();
                }
            });

            // Form validation before submit for ADD form
            $('#staff-form').on('submit', function(e) {
                const finalUserTypeId = $('#add_user_type_id').val();
                if (!finalUserTypeId) {
                    e.preventDefault();
                    alert('Please select a complete user type hierarchy.');
                    return false;
                }
            });

            // Handle status checkbox for ADD form
            $('input[name="status"]').change(function() {
                $(this).val(this.checked ? '1' : '0');
            });

        });
    </script>

    <!-- View staff script -->
    <script>
        $(document).ready(function() {
            // Handle view button click - using event delegation for pagination
            // Listen only to the actual view action buttons (don't bind to the overlay toggle attribute —
            // that is also present on the modal close button and caused requests with no user id)
            $(document).on('click', '.view-staff-btn', function(e) {
                // console.log('[DEBUG] .view-staff-btn clicked', this);
                e.preventDefault();

                const userId = $(this).data('id');
                if (!userId) {
                    // console.warn('[DEBUG] No userId found on .view-staff-btn', this);
                    return;
                }

                $.ajax({
                    url: <?php echo json_encode(route('admin.users.getUserforEdit')); ?>,
                    method: 'GET',
                    data: {
                        user_id: userId
                    },
                    success: function(user) {

                        // Populate view modal fields
                        $('#view_staff_name').text(user.name || 'N/A');
                        $('#view_staff_email').text(user.email || 'N/A');
                        $('#view_staff_contact').text(user.contact_number || 'N/A');
                        $('#view_staff_address').text(user.address || 'N/A');
                        $('#view_staff_joining_date').text(user.joining_date || 'N/A');
                        $('#view_staff_status').text(user.status == 1 ? 'Active' : 'Inactive');
                        // Password cannot be retrieved as plaintext (stored hashed). Show masked dots.
                        $('#view_staff_password').text('••••••••');

                        // Load and display user type hierarchy
                        if (user.user_type_id) {
                            loadViewUserTypeHierarchy(user.user_type_id);
                        } else {
                            $('#view_staff_user_type').text('N/A');
                        }

                        // Open the view overlay programmatically (prevent relying on data-hs-overlay)
                        try {
                            const viewModalEl = document.getElementById('view-staff');
                            if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                                window.HSOverlay.open(viewModalEl);
                            } else if (typeof HSOverlay !== 'undefined') {
                                const inst = HSOverlay.getInstance(viewModalEl) || new HSOverlay(viewModalEl);
                                inst.open();
                            } else {
                                viewModalEl.classList.remove('hidden');
                            }
                        } catch (e) {
                            document.getElementById('view-staff').classList.remove('hidden');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Failed to load user data. Please try again.');
                    }
                });
            });

            // Function to load and display user type hierarchy for view
            function loadViewUserTypeHierarchy(userTypeId) {
                $.ajax({
                    url: <?php echo json_encode(route('admin.users.user-types.hierarchy-path')); ?>,
                    method: 'GET',
                    data: {
                        user_type_id: userTypeId
                    },
                    success: function(hierarchy) {

                        if (hierarchy && hierarchy.length > 0) {
                            // Build hierarchy display string
                            let hierarchyText = hierarchy.map(level => level.user_type).join(' → ');
                            $('#view_staff_user_type').text(hierarchyText);
                        } else {
                            $('#view_staff_user_type').text('N/A');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading view hierarchy:', xhr.responseText);
                        $('#view_staff_user_type').text('Error loading user type');
                    }
                });
            }

        });
    </script>

    <!-- Edit staff script -->
    <script>
        $(document).ready(function() {
            // EDIT FORM HIERARCHY LOGIC

            // Update hidden input with the deepest selected user type for EDIT form
            function updateEditFinalUserTypeId() {
                let finalId = '';
                $('.edit-user-type-select').each(function() {
                    const val = $(this).val();
                    if (val) finalId = val;
                });
                $('#edit_user_type_id').val(finalId);
            }

            // Reset subsequent dropdown levels for EDIT form
            function resetEditSubsequentLevels(startLevel) {
                for (let i = startLevel; i <= 3; i++) {
                    const $select = $(`#edit_user_type_level${i}`);
                    const $container = $(`#edit_user_type_level${i}_container`);

                    if (i > 1) { // Don't reset level 1 as it should always have top-level options
                        $select.html('<option value="">Select user type</option>');
                        $container.hide();
                    } else {
                        // For level 1, just clear the selection but keep the options
                        $select.val('');
                    }
                }
                updateEditFinalUserTypeId();
            }

            // Fetch user types dynamically for EDIT form
            function fetchEditUserTypes(parentId, level) {
                if (!parentId) {
                    resetEditSubsequentLevels(level);
                    return Promise.resolve();
                }

                return new Promise((resolve, reject) => {
                    $.ajax({
                        url: <?php echo json_encode(route('admin.users.user-types.by-parent')); ?>,
                        method: 'GET',
                        data: {
                            parent_id: parentId
                        },
                        beforeSend: function() {
                            const $select = $(`#edit_user_type_level${level}`);
                            $select.html('<option value="">Loading...</option>');
                        },
                        success: function(response) {
                            const $select = $(`#edit_user_type_level${level}`);
                            const $container = $(`#edit_user_type_level${level}_container`);

                            $select.html('<option value="">Select user type</option>');

                            if (response && response.length > 0) {
                                response.forEach(function(userType) {
                                    $select.append(
                                        `<option value="${userType.id}">${userType.user_type}</option>`
                                    );
                                });
                                $container.show();
                            } else {
                                $container.hide();
                            }

                            resetEditSubsequentLevels(level + 1);
                            resolve(response);
                        },
                        error: function(xhr, status, error) {
                            console.error('Error fetching edit user types:', error);
                            const $select = $(`#edit_user_type_level${level}`);
                            $select.html('<option value="">Error loading options</option>');
                            reject(error);
                        }
                    });
                });
            }

            // Populate user type hierarchy for EDIT form
            function populateEditUserTypeHierarchy(userTypeId) {
                if (!userTypeId) return;


                $.ajax({
                    url: <?php echo json_encode(route('admin.users.user-types.hierarchy-path')); ?>,
                    method: 'GET',
                    data: {
                        user_type_id: userTypeId
                    },
                    success: function(hierarchy) {
                        // console.log('User type hierarchy response:', hierarchy);

                        if (hierarchy && hierarchy.length > 0) {
                            // First, reset all dropdowns
                            resetEditSubsequentLevels(2);

                            // Process each level in the hierarchy sequentially
                            processHierarchyLevel(hierarchy, 0);
                        } else {
                            console.log('No hierarchy data received');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading user type hierarchy:', xhr.responseText);
                    }
                });
            }

            // Recursively process hierarchy levels
            function processHierarchyLevel(hierarchy, index) {
                if (index >= hierarchy.length) {
                    updateEditFinalUserTypeId();
                    return;
                }

                const level = hierarchy[index];
                const levelNum = level.level;
                const $select = $(`#edit_user_type_level${levelNum}`);
                const $container = $(`#edit_user_type_level${levelNum}_container`);

                if (levelNum === 1) {
                    // First level - just select the value
                    $select.val(level.id);

                    // If there's a next level, load its options
                    if (index + 1 < hierarchy.length) {
                        fetchEditUserTypes(level.id, levelNum + 1).then(() => {
                            processHierarchyLevel(hierarchy, index + 1);
                        });
                    } else {
                        updateEditFinalUserTypeId();
                    }
                } else {
                    // For subsequent levels, the options should already be loaded
                    $select.val(level.id);
                    $container.show();

                    // Continue with next level
                    if (index + 1 < hierarchy.length) {
                        fetchEditUserTypes(level.id, levelNum + 1).then(() => {
                            processHierarchyLevel(hierarchy, index + 1);
                        });
                    } else {
                        updateEditFinalUserTypeId();
                    }
                }
            }

            // Handle EDIT form user type selection
            $('.edit-user-type-select').on('change', function() {
                const $this = $(this);
                const level = parseInt($this.data('level'));
                const nextLevel = level + 1;
                const selectedId = $this.val();


                resetEditSubsequentLevels(nextLevel);

                if (selectedId && nextLevel <= 3) {
                    fetchEditUserTypes(selectedId, nextLevel);
                } else {
                    updateEditFinalUserTypeId();
                }
            });

            // Handle edit button click - using event delegation for pagination
            // Only handle clicks on the edit button itself to avoid catching modal close toggles
            $(document).on('click', '.edit-staff-btn', function(e) {
                // console.log('[DEBUG] .edit-staff-btn clicked', this);
                e.preventDefault();
                $('.text-danger, .text-red-500').remove();
                $('.ti-form-input').removeClass('border-red-500');

                const userId = $(this).data('id');
                if (!userId) {
                    // console.warn('[DEBUG] No userId found on .edit-staff-btn', this);
                    return;
                }

                $.ajax({
                    url: <?php echo json_encode(route('admin.users.getUserforEdit')); ?>,
                    method: 'GET',
                    data: {
                        user_id: userId
                    },
                    success: function(user) {
                        // console.log('User data:', user);

                        // Populate basic form fields
                        $('#edit_name').val(user.name);
                        $('#edit_email').val(user.email);
                        $('#edit_contact_number').val(user.contact_number);
                        $('#edit_address').val(user.address);
                        $('#edit_joining_date').val(user.joining_date);
                        $('#edit_status').prop('checked', user.status == 1);

                        // Set hidden edit user id so validation redirects can repopulate
                        $('#edit_user_id').val(user.id);

                        // Reset password input for edit modal (leave blank to keep existing password)
                        $('#edit_password').val('');
                        $('#edit_password').attr('type', 'password');
                        $('#edit_toggle_password_icon').removeClass('ri-eye-off-line').addClass('ri-eye-line');

                        // Update form action
                        var actionUrl = "{{ url('admin/users') }}/" + user.id;
                        $('.edit-staff-form').attr('action', actionUrl);

                        // Check if level 1 dropdown has options
                        const level1Options = $('#edit_user_type_level1 option').length;
                        // console.log('Level 1 dropdown has', level1Options, 'options');

                        // Reset levels 2 and 3 only (keep level 1 options)
                        resetEditSubsequentLevels(2);

                        // Populate user type hierarchy
                        if (user.user_type_id) {
                            // console.log('Loading hierarchy for user_type_id:', user
                            //     .user_type_id);
                            populateEditUserTypeHierarchy(user.user_type_id);
                        }

                        // Open the edit overlay programmatically (prevent relying on data-hs-overlay)
                        try {
                            const editModalEl = document.getElementById('edit-staff');
                            if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                                window.HSOverlay.open(editModalEl);
                            } else if (typeof HSOverlay !== 'undefined') {
                                const inst = HSOverlay.getInstance(editModalEl) || new HSOverlay(editModalEl);
                                inst.open();
                            } else {
                                editModalEl.classList.remove('hidden');
                            }
                        } catch (e) {
                            document.getElementById('edit-staff').classList.remove('hidden');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText);
                        alert('Failed to load user data. Please try again.');
                    }
                });
            });

            // Form validation before submit for EDIT form
            $('.edit-staff-form').on('submit', function(e) {
                const finalUserTypeId = $('#edit_user_type_id').val();
                if (!finalUserTypeId) {
                    e.preventDefault();
                    alert('Please select a complete user type hierarchy.');
                    return false;
                }
            });

            // Function to load and display user type hierarchy for edit
            function loadEditUserTypeHierarchy(userTypeId) {
                if (!userTypeId) return;

                $.ajax({
                    url: '{{ route('admin.users.user-types.hierarchy-path') }}',
                    method: 'GET',
                    data: {
                        user_type_id: userTypeId
                    },
                    success: function(hierarchy) {
                        console.log('Edit hierarchy:', hierarchy);

                        if (hierarchy && hierarchy.length > 0) {
                            // Reset levels 2 and 3 only (keep level 1 options)
                            resetEditSubsequentLevels(2);

                            // Process each level in the hierarchy sequentially
                            processHierarchyLevel(hierarchy, 0);
                        } else {
                            console.log('No hierarchy data received');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading edit hierarchy:', xhr.responseText);
                    }
                });
            }

            console.log('Edit user type hierarchy system initialized');
        });
    </script>
    <!-- Password Toggle Script (keep only for show/hide, no validation) -->
    <script>
        function togglePassword(fieldId, iconId) {
            const passwordField = document.getElementById(fieldId);
            const toggleIcon = document.getElementById(iconId);
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.classList.remove('ri-eye-line');
                toggleIcon.classList.add('ri-eye-off-line');
            } else {
                passwordField.type = 'password';
                toggleIcon.classList.remove('ri-eye-off-line');
                toggleIcon.classList.add('ri-eye-line');
            }
        }

        // Alias for consistency with new onclick used in edit modal
        function togglePasswordField(fieldId, iconId) {
            togglePassword(fieldId, iconId);
        }

        // For the view modal: passwords are stored hashed and cannot be revealed.
        // Show a small informational toast or alert to the admin when they click the eye.
        function toggleViewPassword() {
            const msg = 'Passwords are stored securely and cannot be displayed. Use Edit to set a new password if required.';
            // Simple toast
            const toast = document.createElement('div');
            toast.className = 'fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white bg-blue-600';
            toast.textContent = msg;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 4000);
        }
          // Auto-open edit modal and show error modal if validation errors exist (either in named 'edit' bag or default)
            (function() {
                const hasEditErrors = @json($errors->edit->any());
                const hasDefaultErrors = @json($errors->any());
                const anyErrors = hasEditErrors || hasDefaultErrors;

                if (anyErrors) {
                    // Build an aggregated error message to show in the error modal
                    let message = '';
                    // Prefer named edit bag messages if present
                    const editErrors = @json($errors->edit->all());
                    const defaultErrors = @json($errors->all());

                    const sourceErrors = (editErrors && editErrors.length) ? editErrors : defaultErrors;
                    if (sourceErrors && sourceErrors.length) {
                        message = sourceErrors.join('\n');
                    } else {
                        message = 'There were validation errors. Please review the form.';
                    }

                    // Show the error modal and reopen the edit modal after it's closed
                    setTimeout(() => {
                        if (typeof showErrorModal === 'function') {
                            showErrorModal('Validation Error', message, function() {
                                // After closing error modal, open edit overlay so admin can correct inputs
                                const editModalEl = document.getElementById('edit-staff');
                                // If server provided an old edit_user_id, set the form action so PUT will submit correctly
                                try {
                                    const oldEditId = @json(old('edit_user_id'));
                                    if (oldEditId) {
                                        document.getElementById('edit_user_id').value = oldEditId;
                                        const actionUrl = "{{ url('admin/users') }}/" + oldEditId;
                                        document.querySelectorAll('.edit-staff-form').forEach(f => f.setAttribute('action', actionUrl));
                                    }
                                } catch (e) { console.error(e); }
                                try {
                                    if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                                        window.HSOverlay.open(editModalEl);
                                    } else if (typeof HSOverlay !== 'undefined') {
                                        const inst = HSOverlay.getInstance(editModalEl) || new HSOverlay(editModalEl);
                                        inst.open();
                                    } else {
                                        editModalEl.classList.remove('hidden');
                                        }
                                } catch (e) {
                                    editModalEl.classList.remove('hidden');
                                }

                                // Attempt to populate the user type hierarchy from old input if available
                                try {
                                    const oldUserTypeId = @json(old('user_type_id'));
                                    if (oldUserTypeId) {
                                        // Wait for hierarchy functions to be ready (they are defined later in the script)
                                        let attempts = 0;
                                        const tryPopulate = () => {
                                            if (typeof populateEditUserTypeHierarchy === 'function') {
                                                populateEditUserTypeHierarchy(oldUserTypeId);
                                            } else if (attempts < 10) {
                                                attempts++;
                                                setTimeout(tryPopulate, 200);
                                            }
                                        };
                                        tryPopulate();
                                    }
                                } catch (e) { console.error(e); }
                            });
                        } else {
                            alert(message);
                            // fallback: open edit modal
                            const editModalEl = document.getElementById('edit-staff');
                            editModalEl.classList.remove('hidden');
                        }
                    }, 200);
                }
            })();
    </script>
@endpush
