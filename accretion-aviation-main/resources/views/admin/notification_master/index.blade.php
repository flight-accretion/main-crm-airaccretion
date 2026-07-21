@extends('admin.layouts.header')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/css/intlTelInput.css">
<style>
    /* Make iti wrapper fill its column */
    .iti {
        width: 100%;
        display: block;
    }

    /* Input fills full width */
    .iti input[type="tel"] {
        width: 100% !important;
    }

    /* Static fallback: push text past flag+dialcode (~95px) */
    .iti--separate-dial-code input[type="tel"] {
        padding-left: 95px !important;
    }

    /* Flag button bg */
    .iti--separate-dial-code .iti__selected-flag {
        background-color: transparent;
    }

    .intl-phone-input {
        padding-left: 80px !important;
    }
</style>
@endpush

@section('content')
<div class="block justify-between page-header md:flex"></div>

@if (session('success'))
<div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif
@if (session('error'))
<div class="alert alert-danger mb-4">{{ session('error') }}</div>
@endif

<!-- Add Mobile Accordion -->
<div class="grid grid-cols-12">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="hs-accordion-group">
                <div class="hs-accordion" id="add-target-accordion">
                    <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                    <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                        viewBox="0 0 24 24" width="24px" fill="#000000">
                                        <path d="M0 0h24v24H0V0z" fill="none"></path>
                                        <path
                                            d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z">
                                        </path>
                                    </svg>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Add New Mobile</h5>
                                    <div class="text-danger font-semibold">
                                        <button type="button"
                                            class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                            aria-controls="add-target-form">
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
                                            Add Mobile
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="add-target-form"
                        class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300 @if ($errors->any()) !block @endif"
                        aria-labelledby="add-target-accordion">
                        <div class="box-body">
                            <form class="ti-custom-validation" id="add-mobile-form-element" novalidate method="POST"
                                action="{{ route('admin.notification-master.store') }}">
                                @csrf
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <div class="xl:col-span-4 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                            Phone Number<span class="text-danger">*</span>
                                        </label>
                                        {{-- ✅ id="add_phone" matches JS --}}
                                        <input type="hidden" name="contact_country_code" id="add_contact_country_code"
                                            value="{{ old('contact_country_code', '+91') }}">
                                        {{-- Stores ISO code (e.g. "us", "in") to restore correct flag after validation
                                        failure --}}
                                        <input type="hidden" name="country_iso" id="add_country_iso"
                                            value="{{ strtolower(old('country_iso', 'in')) }}">
                                        <input id="add_phone" type="tel" name="contact_number"
                                            class="intl-phone-input ti-form-input rounded-sm form-control-sm"
                                            value="{{ old('contact_number') }}" required>
                                        {{-- ✅ id="add_contact_country_code" matches JS --}}

                                        @error('contact_number')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="xl:col-span-4 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email
                                            Address</label>
                                        <input type="email" name="email"
                                            class="ti-form-input rounded-sm form-control-sm" placeholder="your@site.com"
                                            value="{{ old('email') }}">
                                        @error('email')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    {{-- <div class="xl:col-span-2 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                            Status<span class="text-danger">*</span>
                                        </label>
                                        <select name="status" class="ti-form-select rounded-sm form-control-sm"
                                            required>
                                            <option value="active" selected>Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                    </div> --}}
                                    <div class="xl:col-span-3 col-span-12 pt-4">
                                        <button type="submit" class="ti-btn ti-btn-primary ti-custom-validate-btn">
                                            <i class="ti ti-device-floppy"></i> Add Mobile
                                        </button>
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

<!-- Search Filters -->
<div class="grid grid-cols-12">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header">
                <div class="box-title">Search Filters</div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>
            <div class="box-body" id="filter-section">
                <form class="ti-custom-validation" method="GET" action="{{ route('admin.notification-master.index') }}"
                    id="filter-form" novalidate>
                    <div class="grid grid-cols-12 sm:gap-6 items-end">
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 col-span-12">
                            <label class="ti-form-label mb-1">Phone</label>
                            <input id="search_phone" type="tel" name="contact_number"
                                class="intl-phone-input ti-form-input rounded-sm form-control-sm"
                                value="{{ request('contact_number') }}" placeholder="Enter phone">
                            <input type="hidden" name="contact_country_code" id="search_country_code_hidden"
                                value="{{ request('contact_country_code') }}">
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 col-span-12">
                            <label class="ti-form-label mb-1">Email</label>
                            <input type="text" name="email" class="ti-form-input rounded-sm form-control-sm"
                                value="{{ request('email') }}" placeholder="Enter email">
                        </div>
                        <div class="xl:col-span-2 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                            <select name="status" class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All</option>
                                <option value="active" {{ request('status')==='active' ? 'selected' : '' }}>Active
                                </option>
                                <option value="inactive" {{ request('status')==='inactive' ? 'selected' : '' }}>Inactive
                                </option>
                            </select>
                        </div>
                        <div class="xl:col-span-2 col-span-12">
    <div class="flex gap-2 items-center flex-nowrap">
        <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-3 whitespace-nowrap">
            <i class="ti ti-filter me-1"></i>Apply Filters
        </button>
        <button type="button" onclick="clearFilters()"
            class="ti-btn ti-btn-outline-secondary !py-1 !px-2 flex-shrink-0" title="Reset Filters">
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

<!-- Mobile Numbers Table -->
<div class="grid grid-cols-12 gap-6 mt-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header justify-between flex">
                <div class="box-title">Notification Mobile Numbers</div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table id="masters-table" class="table display responsive nowrap table-datatable server-paginated" width="100%">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">
                                <th class="text-center" data-priority="1">Sr.No</th>
                                <th data-priority="2">Phone</th>
                                <th data-priority="3">Email</th>
                                <th data-priority="4">Status</th>
                                <th data-priority="5">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($masters as $index => $m)
                            <tr class="border-b border-defaultborder">
                                <td class="text-center">{{ ($masters->firstItem() ?? 1) + $index }}</td>
                                <td>{{ $m->contact_country_code ? $m->contact_country_code . '-' : '' }}{{
                                    $m->mobile_number }}</td>
                                <td>{{ $m->email_id ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $m->status == 1 ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $m->status == 1 ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>
                                    <div class="hstack flex gap-3 text-[.9375rem]">
                                        <a aria-label="anchor" href="javascript:void(0);"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-mobile-btn"
                                            data-id="{{ $m->id }}" title="Edit">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        @if(optional(Auth::user())->isSuperAdmin())
                                        <a aria-label="anchor" href="javascript:void(0);"
                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-mobile-btn"
                                            data-id="{{ $m->id }}" title="Delete">
                                            <i class="ri-delete-bin-line"></i>
                                        </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if($masters->hasPages())
                <div class="mt-4">
                    {{ $masters->appends(request()->except('page'))->links() }}
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Edit Mobile Off-canvas -->
<div id="edit-mobile" class="hs-overlay ti-offcanvas ti-offcanvas-right hidden" tabindex="-1">
    <div class="ti-offcanvas-header">
        <div class="flex items-center">
            <div class="me-4 gap-0">
                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                    <i class="ri-phone-line"></i>
                </span>
            </div>
            <div class="flex-grow">
                <div class="flex items-center justify-between">
                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Mobile</h5>
                    <button type="button"
                        class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                        data-hs-overlay="#edit-mobile">
                        <span class="sr-only">Close</span>
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

    <div class="ti-offcanvas-body">
        <form class="ti-custom-validation" action="" id="edit-mobile-form" method="POST" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" id="edit_mobile_id" name="id">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 sm:gap-6">
                                {{-- <div class="xl:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                        Phone Number<span class="text-danger">*</span>
                                    </label>
                                    
                                    <input id="edit_phone" type="tel" name="mobile_number"
                                        class="intl-phone-input ti-form-input rounded-sm form-control-sm"
                                        value="{{ request('contact_number') }}" placeholder="Enter phone">
                                   <input type="hidden" name="country_iso" id="edit_country_iso"
                                        value="{{ request('contact_country_code') }}">
                                    @error('mobile_number')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div> --}}

                                <div class="xl:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                        Phone Number<span class="text-danger">*</span>
                                    </label>

                                    <input id="edit_phone" type="tel" name="mobile_number"
                                        class="intl-phone-input ti-form-input rounded-sm form-control-sm"
                                        placeholder="Enter phone number" required>

                                    <!-- Dial Code -->
                                    <input type="hidden" name="contact_country_code" id="edit_contact_country_code">

                                    <!-- ISO Code (VERY IMPORTANT) -->
                                    <input type="hidden" name="country_iso" id="edit_country_iso">

                                    @error('mobile_number')
                                        <span class="text-red-500 text-xs">{{ $message }}</span>
                                    @enderror
                                </div>
                                <div class="xl:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                    <input type="email" name="email_id" id="edit_email_id"
                                        class="ti-form-input w-full rounded-sm form-control-sm"
                                        placeholder="your@site.com">
                                </div>
                                <div class="xl:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                        Status<span class="text-danger">*</span>
                                    </label>
                                    <select name="status" id="edit_status"
                                        class="ti-form-select rounded-sm form-control-sm" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="xl:col-span-12 col-span-12 mt-4">
                                    <button type="submit"
                                        class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">
                                        Update Mobile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection
@include('admin.partials.modals.success-error-modals')
@push('scripts')
<script>
    function clearFilters() {
        window.location.href = "{{ route('admin.notification-master.index') }}";
    }

    document.addEventListener('DOMContentLoaded', function() {
        const toggleButton = document.getElementById('toggle-filters');
        const filterSection = document.getElementById('filter-section');
        const filterIcon   = document.getElementById('filter-icon');
        if (toggleButton && filterSection && filterIcon) {
            toggleButton.addEventListener('click', function() {
                const hidden = filterSection.style.display === 'none';
                filterSection.style.display = hidden ? 'block' : 'none';
                filterIcon.classList.toggle('ti-chevron-up', hidden);
                filterIcon.classList.toggle('ti-chevron-down', !hidden);
            });
        }
    });

    $(document).ready(function() {

        // ============ EDIT ============
        $(document).on('click', '.edit-mobile-btn', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            $('#edit-mobile-form').attr('action', `{{ url('/admin/notification-master') }}/${id}`);
            $('#edit-mobile-form')[0].reset();
            $('.text-red-500').remove();

            $.ajax({
                url: `{{ url('/admin/notification-master') }}/${id}/edit`,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.master) {
                        const m = response.master;
                        $('#edit_mobile_id').val(m.id);
                        $('#edit_email_id').val(m.email_id || '');
                        $('#edit_status').val(m.status == 1 ? 'active' : 'inactive');
                        $('#edit_contact_country_code').val(m.contact_country_code || '+91');

                        if (window.itiEditPhone) {
                            // setNumber with full E.164 so it auto-selects the right flag
                            const fullNumber = (m.contact_country_code || '+91') + m.mobile_number;
                            window.itiEditPhone.setNumber(fullNumber);
                            const d = window.itiEditPhone.getSelectedCountryData();
                            if (d && d.iso2) {
                                $('#edit_country_iso').val(d.iso2.toUpperCase());
                            }
                            setTimeout(function() {
                                fixPadding(document.getElementById('edit_phone'));
                            }, 150);
                        } else {
                            $('#edit_phone').val(m.mobile_number);
                        }

                        if (window.HSOverlay) {
                            window.HSOverlay.open('#edit-mobile');
                        } else {
                            $('#edit-mobile').removeClass('hidden').addClass('open');
                            $('body').addClass('ti-offcanvas-open');
                        }
                    }
                },
                error: function() { showAlert('error', 'Error loading mobile details.'); }
            });
        });

        $('#edit-mobile-form').on('submit', function(e) {
            e.preventDefault();
            if (window.itiEditPhone) {
                const d = window.itiEditPhone.getSelectedCountryData();
                if (d && d.dialCode) $('#edit_contact_country_code').val('+' + d.dialCode);
            }
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                success: function(response) {
                    // clear previous errors
                    $('#edit-mobile-form .text-red-500').remove();

                    if (response.master) {
                        const m = response.master;

                        $('#edit_mobile_id').val(m.id);
                        $('#edit_email_id').val(m.email_id || '');
                        $('#edit_status').val(m.status == 1 ? 'active' : 'inactive');
                        $('#edit_contact_country_code').val(m.contact_country_code || '+91');

                        if (window.itiEditPhone) {
                            const fullNumber = (m.contact_country_code || '+91') + m.mobile_number;
                            window.itiEditPhone.setNumber(fullNumber);
                        }

                        // Close overlay and refresh the table so timestamps / data match server
                        try {
                           if (window.HSOverlay) {
                                window.HSOverlay.close('#edit-mobile');
                            } else {
                                $('#edit-mobile').addClass('hidden').removeClass('open');
                                $('body').removeClass('ti-offcanvas-open');
                            }
                        } catch (e) { $('#edit-mobile').addClass('hidden').removeClass('open'); $('body').removeClass('ti-offcanvas-open'); }

                        // If success, show a confirmation and reload to reflect changes
                        if (response.success) {
                            showSuccessModal('Updated', response.message || 'Updated successfully.', function() { location.reload(); });
                        } else {
                            showAlert('success', response.message || 'Updated.');
                        }
                    }
                },
                error: function(xhr) {
                    // remove old errors
                    $('#edit-mobile-form .text-red-500').remove();

                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        const errors = xhr.responseJSON.errors;
                        // iterate errors and attach below the corresponding input
                        $.each(errors, function(key, messages) {
                            // Try exact name match first
                            let field = $('#edit-mobile-form').find('[name="' + key + '"]');
                            if (!field.length) {
                                // fallback: dot notation -> bracket/underscore variations
                                const alt = key.replace('.', '_');
                                field = $('#edit-mobile-form').find('[name="' + alt + '"]');
                            }
                            if (field.length) {
                                field.after('<span class="text-red-500 text-xs">' + messages[0] + '</span>');
                            } else {
                                // if no matching field, show top-level alert
                                showAlert('error', messages[0]);
                            }
                        });
                    } else {
                        showErrorModal('Update Failed', (xhr.responseJSON && xhr.responseJSON.message) || 'Error updating.');
                    }
                }
            });
        });

        // ============ DELETE ============
        $(document).on('click', '.delete-mobile-btn', function(e) {
            e.preventDefault();
            const id = $(this).data('id');
            showConfirmationModal('Delete Mobile', 'Are you sure you want to delete this mobile number?', function() {
                $.ajax({
                    url: `{{ url('/admin/notification-master') }}/${id}`,
                    type: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(r) {
                        if (r.success) showSuccessModal('Deleted', r.message || 'Deleted!', function() { location.reload(); });
                        else showErrorModal('Delete Failed', r.message || 'Failed to delete.');
                    },
                    error: function(xhr) {
                        showErrorModal('Delete Failed', (xhr.responseJSON && xhr.responseJSON.message) || 'Error deleting.');
                    }
                });
            });
        });

        function showAlert(type, message) {
            const cls = type === 'success' ? 'alert-success' : 'alert-danger';
            $('.page-header').after(`<div class="alert ${cls} mb-4">${message}</div>`);
            setTimeout(function() { $('.alert').fadeOut(); }, 5000);
        }

        $(document).on('click', '[data-hs-overlay="#edit-mobile"]', function() {
           if (window.HSOverlay) {
                                window.HSOverlay.close('#edit-mobile');
                            } else {
                                $('#edit-mobile').addClass('hidden').removeClass('open');
                                $('body').removeClass('ti-offcanvas-open');
                            }
        });

        
    });

    // ============ INTL-TEL-INPUT INITIALIZATION ============
    document.addEventListener('DOMContentLoaded', function() {

        // ── Shared helper: measure actual rendered flag-button width and apply as padding ──
        function fixPadding(inputEl) {
            if (!inputEl) return;
            const wrapper = inputEl.closest('.iti');
            if (!wrapper) return;
            const flagBtn = wrapper.querySelector('.iti__selected-flag');
            if (flagBtn) {
                inputEl.style.paddingLeft = (flagBtn.offsetWidth + 6) + 'px';
            }
        }

        // ── ADD FORM ──────────────────────────────────────────────────────────────────
        const addPhoneEl = document.getElementById('add_phone');               // ✅ matches HTML
        const addCodeEl  = document.getElementById('add_contact_country_code'); // ✅ matches HTML

        if (addPhoneEl && window.intlTelInput) {
            // Read country from old() hidden field so flag is restored after validation failure
            const addCountryIsoEl = document.getElementById('add_country_iso');
            const addInitialCountry = (addCountryIsoEl && addCountryIsoEl.value) ? addCountryIsoEl.value : 'in';

            window.itiAddPhone = window.intlTelInput(addPhoneEl, {
                initialCountry: addInitialCountry,
                separateDialCode: true,
                dropdownContainer: document.body,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js'
            });

            // Fix padding once rendered, then on every country change
            setTimeout(function() { fixPadding(addPhoneEl); }, 150);

            addPhoneEl.addEventListener('countrychange', function() {
                const d = window.itiAddPhone.getSelectedCountryData();
                if (d && d.dialCode) addCodeEl.value = '+' + d.dialCode;
                if (d && d.iso2 && addCountryIsoEl) addCountryIsoEl.value = d.iso2.toLowerCase();
                setTimeout(function() { fixPadding(addPhoneEl); }, 50);
            });

            // Seed hidden fields on load
            setTimeout(function() {
                const d = window.itiAddPhone.getSelectedCountryData();
                if (d && d.dialCode) addCodeEl.value = '+' + d.dialCode;
                if (d && d.iso2 && addCountryIsoEl) addCountryIsoEl.value = d.iso2.toLowerCase();
            }, 200);

            // On submit: capture both country code and ISO
            document.getElementById('add-mobile-form-element').addEventListener('submit', function() {
                const d = window.itiAddPhone.getSelectedCountryData();
                if (d && d.dialCode) addCodeEl.value = '+' + d.dialCode;
                if (d && d.iso2 && addCountryIsoEl) addCountryIsoEl.value = d.iso2.toLowerCase();
            });
        }

        // ── EDIT FORM ─────────────────────────────────────────────────────────────────
        const editPhoneEl = document.getElementById('edit_phone');              // ✅ matches HTML
        const editCodeEl  = document.getElementById('edit_contact_country_code'); // ✅ matches HTML

        if (editPhoneEl && window.intlTelInput) {
            window.itiEditPhone = window.intlTelInput(editPhoneEl, {
                initialCountry: '',
                separateDialCode: true,
                dropdownContainer: document.body,
                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js'
            });

            setTimeout(function() { fixPadding(editPhoneEl); }, 150);

            editPhoneEl.addEventListener('countrychange', function() {
                const d = window.itiEditPhone.getSelectedCountryData();
                if (d && d.dialCode) editCodeEl.value = '+' + d.dialCode;
                setTimeout(function() { fixPadding(editPhoneEl); }, 50);
            });
        }

        // ── SEARCH PHONE INPUT (filters) ─────────────────────────────────────────────
        const searchPhoneEl = document.getElementById('search_phone');
        const searchCodeEl  = document.getElementById('search_country_code_hidden');

        if (searchPhoneEl && window.intlTelInput) {
            window.itiSearchPhone = window.intlTelInput(searchPhoneEl, {
                initialCountry: '',
                separateDialCode: true,

                utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js'
            });

            // Set padding and seed code after render
            setTimeout(function() {
                const d = window.itiSearchPhone.getSelectedCountryData();
                if (d && d.dialCode && searchCodeEl) searchCodeEl.value = '+' + d.dialCode;
                fixPadding(searchPhoneEl);
            }, 200);

            searchPhoneEl.addEventListener('countrychange', function() {
                const d = window.itiSearchPhone.getSelectedCountryData();
                if (d && d.dialCode && searchCodeEl) searchCodeEl.value = '+' + d.dialCode;
                setTimeout(function() { fixPadding(searchPhoneEl); }, 50);
            });

            const filterForm = document.getElementById('filter-form');
            if (filterForm) {
                filterForm.addEventListener('submit', function() {
                    const d = window.itiSearchPhone.getSelectedCountryData();
                    if (searchCodeEl) searchCodeEl.value = (d && d.dialCode) ? '+' + d.dialCode : '';
                });
            }
        }
    });
</script>
@endpush
