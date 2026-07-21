@extends('admin.layouts.header') 
@section('content')
    <!-- Page Header -->
                <div class="block justify-between page-header md:flex">
                    <div>
                        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold"> Add Staff</h3>
                    </div>
                    <ol class="flex items-center whitespace-nowrap min-w-0">
                        <li class="text-[0.813rem] ps-[0.5rem]">
                          <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="javascript:void(0);">
                            Staff
                            <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                          </a>
                        </li>
                        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                            Add Staff
                        </li>
                    </ol>
                </div>
    <!-- Page Header Close -->
    @if(session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif
            <div class="grid grid-cols-12 gap-6 text-defaultsize">
                <div class="xl:col-span-12 col-span-12">
                    <div class="box">
                        <div class="box-body">
                            <form class="ti-custom-validation" action="{{ route('admin.users.store') }}" method="POST" novalidate>
                                @csrf
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="name" class="ti-form-label mb-0">Name</label>
                                        <input type="text" class="ti-form-input w-full rounded-sm form-control-sm" id="name" name="name" placeholder="Enter Name" required>
                                        @error('name')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="email" class="ti-form-label mb-0">Email</label>
                                        <input type="email" class="ti-form-input w-full rounded-sm form-control-sm" id="email" name="email" placeholder="Enter Email" required>
                                        @error('email')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="password" class="ti-form-label mb-0">Password</label>
                                        <input type="password" class="ti-form-input w-full rounded-sm form-control-sm" id="password" name="password" placeholder="Enter password" required>
                                        @error('password')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="contact_number" class="ti-form-label mb-0">Contact Number</label>
                                        <input type="text" class="ti-form-input w-full rounded-sm form-control-sm" id="contact_number" name="contact_number" placeholder="Enter contact number">
                                        @error('contact_number')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="address" class="ti-form-label mb-0">Address</label>
                                        <textarea class="ti-form-input w-full rounded-sm form-control-sm" id="address" name="address" rows="1"></textarea>
                                    </div>
                                    
                                    <!-- Dynamic User Type Dropdowns -->
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="user_type_level1" class="ti-form-label mb-0">User Type</label>
                                        <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3 ti-form-input w-full rounded-sm form-control-sm" 
                                                id="user_type_level1" 
                                                data-level="1"
                                                data-next-level="2">
                                            <option value="">Select user type</option>
                                            @foreach($userTypes as $userType)
                                                <option value="{{ $userType->id }}">{{ $userType->user_type }}</option>
                                            @endforeach
                                        </select>
                                        @error('user_type_id')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 user-type-container" id="user_type_level2_container" style="display: none;">
                                        <!-- <label for="user_type_level2" class="ti-form-label mb-0">User Type (Level 2)</label> -->
                                        <label for="file-input" class="sr-only">Select user type</label>
                                        <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3 ti-form-input w-full rounded-sm form-control-sm" 
                                                id="user_type_level2" 
                                                data-level="2"
                                                data-next-level="3">
                                            <option value="">Select user type</option>
                                        </select>
                                    </div>
                                    
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 user-type-container" id="user_type_level3_container" style="display: none;">
                                        <!-- <label for="user_type_level3" class="ti-form-label mb-0">User Type (Level 3)</label> -->
                                        <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3 ti-form-input w-full rounded-sm form-control-sm" 
                                                id="user_type_level3" 
                                                data-level="3">
                                            <option value="">Select user type</option>
                                        </select>
                                    </div>
                                    
                                    <input type="hidden" id="user_type_id" name="user_type_id" required>
                                    
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="joining_date" class="ti-form-label mb-0">Joining Date</label>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i class="ri-calendar-line"></i> </div>
                                                <input type="datetime-local" class="form-control ti-form-input w-full rounded-sm form-control-sm" id="joining_date" name="joining_date">
                                            </div>
                                        </div>
                                    </div>
                                <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label for="status" class="ti-form-label mb-0">Status</label>
                                        <div class="flex items-center">
                                            <input type="hidden" name="status" value="1">
                                            <input type="checkbox" id="status" name="status" class="ti-switch" value="1" 
                                                @if(old('status', 1)) checked @endif>
                                            <label for="status" class="ms-2">Active</label>
                                        </div>
                                        @error('status')
                                            <span class="text-danger">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    {{--  <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <button type="submit" class="ti-btn ti-btn-primary-full">Submit</button>
                                    </div>  --}}
                                </div>
                            </form>
                        </div>
                        <div class="box-footer">
                            <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                        </div>
                    </div>
                </div>
            </div>
<script>
$(document).ready(function () {
    // Update hidden input with the deepest selected user type
    function updateFinalUserTypeId() {
        let finalId = '';
        $('.user-type-select').each(function () {
            const val = $(this).val();
            if (val) finalId = val;
        });
        $('#user_type_id').val(finalId);
    }

    // Fetch user types dynamically
    function fetchUserTypes(parentId, level) {
        $.ajax({
            url: '{{ route("admin.users.user-types.by-parent") }}',
            method: 'GET',
            data: { parent_id: parentId },
            success: function (response) {
                const $nextSelect = $(`#user_type_level${level}`);
                const $nextContainer = $(`#user_type_level${level}_container`);
                $nextSelect.html('<option value="">Select user type</option>');

                if (response.length > 0) {
                    response.forEach(function (userType) {
                        $nextSelect.append(`<option value="${userType.id}">${userType.user_type}</option>`);
                    });
                    $nextContainer.show();
                } else {
                    $nextContainer.hide();
                }

                updateFinalUserTypeId(); 
            },
            error: function (xhr) {
                console.error('Error:', xhr.responseText);
                alert('Failed to load user types.');
            }
        });
    }
    $('#status').change(function() {
        $(this).val(this.checked ? '1' : '0');
        console.log('Status changed:', this.checked ? 'Active' : 'Inactive');
    });

    // When user selects a user type
    $('.user-type-select').on('change', function () {
        const level = parseInt($(this).data('level'));
        const nextLevel = $(this).data('next-level');
        const selectedId = $(this).val();

        // Reset all lower levels
        for (let i = level + 1; i <= 3; i++) {
            $(`#user_type_level${i}`).html('<option value="">Select user type</option>');
            $(`#user_type_level${i}_container`).hide();
        }

        if (nextLevel && selectedId) {
            fetchUserTypes(selectedId, nextLevel);
        }

        updateFinalUserTypeId();
    });
});
</script>
@endsection