@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
    </div>
    <!-- Page Header -->
    
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
    
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="assign-executive-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                            viewBox="0 0 24 24" width="24px" fill="#000000">
                                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                                            <path d="M16 4c0-1.11-.89-2-2-2s-2 .89-2 2 .89 2 2 2 2-.89 2-2zm4 18v-6h2.5l-2.54-7.63A2.986 2.986 0 0 0 17.06 7H14c-.8 0-1.54.37-2 .97l-2.89 3.89A2.994 2.994 0 0 0 12 15h2.5v7h5.5z"></path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage
                                            Sales Executives</h5>
                                        @if(in_array(Auth::user()->userType->user_type, ['Super Admin', 'Admin', 'Senior Sales Manager', 'Sales Manager']))
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="assign-executive-form">
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
                                                Assign Executive
                                            </button>
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="assign-executive-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300 @if ($errors->any()) !block @endif"
                            aria-labelledby="assign-executive-accordion">
                            <div class="box-body">
                                <form class="ti-custom-validation" id="assign-executive-form-element" novalidate method="POST" action="{{ route('admin.sales-executive-management.store') }}">
                                    @csrf
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="manager_id" class="ti-form-label mb-0">Manager<span class="text-danger">*</span></label>
                                            @php
                                                $currentType = Auth::user()->userType->user_type ?? '';
                                            @endphp

                                            @if(in_array($currentType, [\App\Models\UserType::SENIOR_SALES_MANAGER, \App\Models\UserType::SALES_MANAGER]))
                                                {{-- For manager users: show the logged-in user selected and disable the dropdown. Include a hidden input so value is submitted. --}}
                                                <select class="ti-form-select rounded-sm form-control-sm " id="manager_id_disabled" disabled>
                                                    <option value="{{ Auth::id() }}">{{ Auth::user()->name }} ({{ Auth::user()->userType->user_type ?? 'N/A' }})</option>
                                                </select>
                                                <input type="hidden" name="manager_id" value="{{ Auth::id() }}">
                                            @else
                                                {{-- For admin roles: show all available managers to choose from --}}
                                                <select class="ti-form-select rounded-sm form-control-sm" name="manager_id" id="manager_id" required>
                                                    <option value="">Select Manager</option>
                                                    @foreach($managers as $manager)
                                                        <option value="{{ $manager->id }}" {{ old('manager_id') == $manager->id ? 'selected' : '' }}>
                                                            {{ $manager->name }} ({{ $manager->userType->user_type ?? 'N/A' }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            @endif

                                            @error('manager_id')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="sales_executive_id" class="ti-form-label mb-0">Sales Executive<span class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm" name="sales_executive_id" id="sales_executive_id" required>
                                                <option value="">Select Sales Executive</option>
                                                @php
                                                    $salesExecutives = \App\Models\User::whereHas('userType', function ($query) {
                                                        $query->where('user_type', \App\Models\UserType::SALES_EXECUTIVE);
                                                    })->where('status', 1)->get();
                                                @endphp
                                                @foreach($salesExecutives as $executive)
                                                    <option value="{{ $executive->id }}" {{ old('sales_executive_id') == $executive->id ? 'selected' : '' }}>
                                                        {{ $executive->name }} ({{ $executive->email }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('sales_executive_id')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 col-span-12 pt-4">
                                            <button type="submit" class="ti-btn ti-btn-primary ti-custom-validate-btn">
                                                <i class="ti ti-device-floppy"></i> Assign Executive
                                            </button>
                                        </div>
                                        <div class="xl:col-span-12 col-span-12">
                                            <label for="notes" class="ti-form-label mb-0">Assignment Notes</label>
                                            <textarea class="ti-form-input" name="notes" id="notes" placeholder="Add any notes about this assignment..." rows="2">{{ old('notes') }}</textarea>
                                            <div class="form-text text-muted">Optional notes about this assignment (maximum 1000 characters)</div>
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

    <!-- Assignments DataTable -->
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">Sales Executive Assignments</div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">Sr.No</th>
                                    <th data-priority="2">Manager</th>
                                    <th data-priority="3">Sales Executive</th>
                                    <th data-priority="4">Assigned Date</th>
                                    <th data-priority="5">Notes</th>
                                    <th data-priority="6">Status</th>
                                    <th data-priority="7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($assignments as $assignment)
                                    <tr class="border-b border-defaultborder">
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td>{{ $assignment->manager->name ?? 'N/A' }}
                                            <div class="text-xs text-muted">{{ $assignment->manager->userType->user_type ?? 'N/A' }}</div>
                                        </td>
                                        <td>{{ $assignment->salesExecutive->name ?? 'N/A' }}
                                            <div class="text-xs text-muted">{{ $assignment->salesExecutive->email ?? 'N/A' }}</div>
                                        </td>
                                        <td>{{ $assignment->assigned_date->format('d M Y') }}
                                            <div class="text-xs text-muted">{{ $assignment->assigned_date->format('h:i A') }}</div>
                                        </td>
                                        <td>
                                            @if($assignment->notes)
                                                <span class="text-truncate d-inline-block" style="max-width: 200px;" title="{{ $assignment->notes }}">
                                                    {{ Str::limit($assignment->notes, 50) }}
                                                </span>
                                            @else
                                                <span class="text-muted">No notes</span>
                                            @endif
                                        </td>
                                        <td class="text-center"><span class="badge bg-success">Active</span></td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                @if(in_array(Auth::user()->userType->user_type, ['Super Admin', 'Admin']) || Auth::user()->id === $assignment->manager_id)
                                                    <form id="delete-assignment-form-{{ $assignment->id }}" action="{{ route('admin.sales-executive-management.destroy', $assignment->id) }}" method="POST" class="d-inline delete-assignment-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-assignment-btn" data-form-id="delete-assignment-form-{{ $assignment->id }}" data-bs-placement="top" title="Remove Assignment">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-muted text-sm">No actions</span>
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

@endsection

@push('scripts')
<script>
$(document).ready(function() {

    // Form validation
    $('#assign-executive-form-element').on('submit', function(e) {
        // When a manager user is logged in we render a disabled select with id "manager_id_disabled"
        // and a hidden input named "manager_id". Prefer the visible select if present, otherwise read the hidden input.
        var managerId = null;
        if ($('#manager_id').length) {
            managerId = $('#manager_id').val();
        } else if ($('input[name="manager_id"]').length) {
            managerId = $('input[name="manager_id"]').val();
        }

        var executiveId = $('#sales_executive_id').val();

        if (!managerId || !executiveId) {
            e.preventDefault();
            alert('Please select both manager and sales executive.');
            return false;
        }

        if (String(managerId) === String(executiveId)) {
            e.preventDefault();
            alert('Manager and Sales Executive cannot be the same person.');
            return false;
        }
    });



    // Use shared confirmation modal for deletes (from admin.partials.modals.success-error-modals)
    // Use delegated event binding so dynamically added rows/buttons also work
    $(document).on('click', '.delete-assignment-btn', function(e) {
        e.preventDefault();
        var formId = $(this).data('form-id');
        var form = document.getElementById(formId);

        // Debug log (remove in production)
        if (window.console && console.log) console.log('Delete button clicked, formId=', formId, 'form=', form);

        // Prefer the reusable confirmation modal; if it's not available or HSOverlay missing, fallback to native confirm
        if (typeof showConfirmationModal === 'function') {
            try {
                showConfirmationModal('Remove Assignment', 'Are you sure you want to remove this assignment?', function() {
                    if (form) form.submit();
                });
                return;
            } catch (err) {
                if (window.console && console.error) console.error('showConfirmationModal failed:', err);
            }
        }

        // Final fallback
        if (confirm('Are you sure you want to remove this assignment?')) {
            if (form) form.submit();
        }
    });
});
</script>
@endpush

{{-- Include reusable success/error/confirmation modals --}}
@include('admin.partials.modals.success-error-modals')