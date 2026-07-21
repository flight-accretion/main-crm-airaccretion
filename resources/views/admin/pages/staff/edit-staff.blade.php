@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Edit Staff</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
              <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.users.index') }}">
                Staff
                <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
              </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                Edit Staff
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
                    <form class="ti-custom-validation" action="{{ route('admin.users.update', $user->id) }}" method="POST" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="name" class="ti-form-label mb-0">Name</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                    value="{{ old('name', $user->name) }}" placeholder="Enter Name" required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="email" class="ti-form-label mb-0">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                    value="{{ old('email', $user->email) }}" placeholder="Enter Email" required readonly>
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="password" class="ti-form-label mb-0">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                    placeholder="Leave blank to keep unchanged">
                                @error('password')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="contact_number" class="ti-form-label mb-0">Contact Number</label>
                                <input type="text" class="form-control" id="contact_number" name="contact_number" 
                                    value="{{ old('contact_number', $user->contact_number) }}" placeholder="Enter contact number">
                                @error('contact_number')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="address" class="ti-form-label mb-0">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="1">{{ old('address', $user->address) }}</textarea>
                            </div>
                            
                            <!-- Dynamic User Type Dropdowns -->
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="user_type_level1" class="ti-form-label mb-0">User Type</label>
                                <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3" 
                                        id="user_type_level1" 
                                        data-level="1"
                                        data-next-level="2">
                                    <option value="">Select user type</option>
                                    @foreach($userTypes as $userType)
                                        <option value="{{ $userType->id }}" 
                                            {{ $userType->id == ($selectedTypes[1] ?? null) ? 'selected' : '' }}>
                                            {{ $userType->user_type }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_type_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 user-type-container" 
                                id="user_type_level2_container" 
                                style="display: {{ isset($selectedTypes[2]) ? 'block' : 'none' }};">
                                <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3" 
                                        id="user_type_level2" 
                                        data-level="2"
                                        data-next-level="3">
                                    <option value="">Select user type</option>
                                    @if(isset($level2Types))
                                        @foreach($level2Types as $type)
                                            <option value="{{ $type->id }}" 
                                                {{ $type->id == ($selectedTypes[2] ?? null) ? 'selected' : '' }}>
                                                {{ $type->user_type }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 user-type-container" 
                                id="user_type_level3_container" 
                                style="display: {{ isset($selectedTypes[3]) ? 'block' : 'none' }};">
                                <select class="ti-form-select user-type-select rounded-sm !py-2 !px-3" 
                                        id="user_type_level3" 
                                        data-level="3">
                                    <option value="">Select user type</option>
                                    @if(isset($level3Types))
                                        @foreach($level3Types as $type)
                                            <option value="{{ $type->id }}" 
                                                {{ $type->id == ($selectedTypes[3] ?? null) ? 'selected' : '' }}>
                                                {{ $type->user_type }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            
                            <input type="hidden" id="user_type_id" name="user_type_id" value="{{ old('user_type_id', $user->user_type_id) }}" required>
                            
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="joining_date" class="ti-form-label mb-0">Joining Date</label>
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i class="ri-calendar-line"></i> </div>
                                        <input type="datetime-local" class="form-control" id="joining_date" name="joining_date"
                                            value="{{ old('joining_date', $user->joining_date ? \Carbon\Carbon::parse($user->joining_date)->format('Y-m-d\TH:i') : '') }}">
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="status" value="0">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="status" class="ti-form-label mb-0">Status</label>
                                <div class="flex items-center">
                                    <input type="checkbox" id="status" name="status" class="ti-switch" value="1" 
                                        {{ old('status', $user->status) ? 'checked' : '' }}>
                                    <label for="status" class="ms-2">Active</label>
                                </div>
                                @error('status')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                            
                            <div class="xl:col-span-12 col-span-12 mt-4">
                                <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Update Staff</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function populateUserTypeHierarchy(hierarchy, index = 0) {
            if (index >= hierarchy.length) return;

            const currentType = hierarchy[index];
            const level = index + 1;
            const nextLevel = level + 1;

            // Set value for current level
            const $select = $(`#user_type_level${level}`);
            const $container = $(`#user_type_level${level}_container`);
            $select.val(currentType.id);
            $container.show();

            if (index + 1 < hierarchy.length) {
                // Fetch options for next level
                $.ajax({
                    url: '{{ route("admin.users.user-types.by-parent") }}',
                    method: 'GET',
                    data: { parent_id: currentType.id },
                    success: function (options) {
                        const $nextSelect = $(`#user_type_level${nextLevel}`);
                        const $nextContainer = $(`#user_type_level${nextLevel}_container`);
                        $nextSelect.html('<option value="">Select user type</option>');

                        options.forEach(opt => {
                            $nextSelect.append(`<option value="${opt.id}">${opt.user_type}</option>`);
                        });

                        $nextContainer.show();
                        populateUserTypeHierarchy(hierarchy, index + 1); // recursive step
                    }
                });
            }
        }
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
    $(document).ready(function () {
    let initialUserTypeId = $('#user_type_id').val();

    if (initialUserTypeId) {
        $.ajax({
            url: '{{ route("admin.users.user-types.hierarchy") }}',
            method: 'GET',
            data: { id: initialUserTypeId },
            success: function (hierarchy) {
                populateUserTypeHierarchy(hierarchy);
            },
            error: function () {
                console.error('Could not load user type hierarchy.');
            }
        });
    }      
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
});        $('.user-type-select').trigger('change');
    });
    </script>
@endsection