@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->

    <div class="block justify-between page-header md:flex"></div>
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
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12  col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="add-client-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                            viewBox="0 0 24 24" width="24px" fill="#000000">
                                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                                            <path
                                                d="M12 5.9c1.16 0 2.1.94 2.1 2.1s-.94 2.1-2.1 2.1S9.9 9.16 9.9 8s.94-2.1 2.1-2.1m0 9c2.97 0 6.1 1.46 6.1 2.1v1.1H5.9V17c0-.64 3.13-2.1 6.1-2.1M12 4C9.79 4 8 5.79 8 8s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0 9c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4z">
                                            </path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage Clients</h5>

                                        <button type="button"
                                            class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                            aria-controls="add-client-form">
                                            <!-- Plus and minus icon toggles -->
                                            <svg class="hs-accordion-active:hidden block size-4 ml-2"
                                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M5 12h14" />
                                                <path d="M12 5v14" />
                                            </svg>
                                            <svg class="hs-accordion-active:block  hidden size-4 ml-2"
                                                xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round">
                                                <path d="M5 12h14" />
                                            </svg>Add Client
                                        </button>

                                    </div>
                                </div>
                            </div>
                        </div>
                        <div id="add-client-form"
                            class="hs-accordion-content  hidden w-full overflow-hidden transition-[height] duration-300"
                            aria-labelledby="add-client-accordion">
                            <div class="box-body">
                                <form class="ti-custom-validation" method="POST" action="{{ route('admin.client.store') }}"
                                    novalidate>
                                    @csrf
                                    <div class="grid lg:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full
                                                Name<span class="text-danger">*</span></label>
                                            <input type="text" name="name"
                                                class="ti-form-input rounded-sm form-control-sm"
                                                value="{{ old('name', '') }}" required>
                                            @error('name', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email
                                                Address</label>
                                            <input type="email" name="email"
                                                class="ti-form-input rounded-sm form-control-sm"
                                                value="{{ old('email', '') }}" required>
                                            @error('email', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <!-- Phone Number -->
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone
                                                Number<span class="text-danger">*</span></label>
                                            <input id="phone" type="tel" name="contact_number"
                                                class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                                value="{{ old('contact_number', '') }}" required>
                                            <input type="hidden" name="contact_country_code" id="contact_country_code"
                                                value="{{ old('contact_country_code', '') }}">
                                            @error('contact_number', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>

                                        <!-- WhatsApp Number -->
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp
                                                Number</label>
                                            <input id="whatsapp" type="tel" name="alternate_number"
                                                class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                                value="{{ old('alternate_number', '') }}" required>
                                            <input type="hidden" name="whatsapp_country_code" id="whatsapp_country_code"
                                                value="{{ old('whatsapp_country_code', '') }}">
                                            @error('alternate_number', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of
                                                Birth</label>
                                            <input type="date" name="date_of_birth"
                                                class="ti-form-input rounded-sm form-control-sm"
                                                value="{{ old('date_of_birth', '') }}">
                                            @error('date_of_birth', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                            <textarea name="address" class="ti-form-input w-full rounded-sm form-control-sm" rows="1" maxlength="500" pattern="[A-Za-z0-9\s]{1,500}" title="Address may contain letters, numbers and spaces only (minimum one letter)">{{ old('address', '') }}</textarea>
                                            @error('address', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country<span class="text-danger">*</span></label>
                                            <select name="country_id" id="add-client-country-code-select"
                                                class="ti-form-select rounded-sm form-control-sm w-full" required>
                                                <option value="">Select Country</option>
                                                @foreach ($countries as $country)
                                                    <option value="{{ $country->id }}"
                                                        {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                        {{ $country->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('country_id', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City<span class="text-danger">*</span></label>
                                            <select name="city" id="add-client-city-select"
                                                class="ti-form-select rounded-sm form-control-sm w-full" required>
                                                <option value="">Select City</option>
                                                @if (old('country_id'))
                                                    @php $cities = \App\Models\City::where('country_id', old('country_id'))->get(); @endphp
                                                    @foreach ($cities as $city)
                                                        <option value="{{ $city->id }}"
                                                            {{ old('city', '') == $city->id ? 'selected' : '' }}>
                                                            {{ $city->name }}
                                                        </option>
                                                    @endforeach
                                                @endif

                                            </select>
                                            @error('city', 'add')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror

                                        </div>
                                    </div>

                                    <div class="mt-5">
                                        <button type="submit"
                                            class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-body">
                    <div class="table-responsive">
                        <table id="" class="table display responsive nowrap table-datatable server-paginated" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">S.No</th>
                                    <th data-priority="2">Name</th>
                                    <th data-priority="3">Email</th>
                                    <th data-priority="5">Phone</th>
                                    <th data-priority="4">City</th>
                                    <th data-priority="1">Status</th>
                                    <th data-priority="1">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clients as $key => $client)
                                    <tr class="border-b border-defaultborder">
                                        <td class="text-center">{{ ($clients->firstItem() ?? 1) + $key }}</td>
                                        <td>{{ $client->name }}</td>
                                        <td>{{ $client->email }}</td>
                                        <td class="text-center">{{ $client->contact_number }}</td>
                                        <td>
                                            {{ $client->city->name ?? 'N/A' }}
                                        </td>
                                        <td class="text-center">
                                            @if ($client->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif

                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor"
                                                    href="{{ route('admin.client.view', $client->id) }}"
                                                    target="_blank"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-client-btn"><i
                                                        class="ri-eye-line"></i></a>
                                                <a aria-label="anchor"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-client-btn"
                                                    data-client-id="{{ $client->id }}"
                                                    data-hs-overlay="#edit-client"><i class="ri-edit-line"></i></a>
                                                <!-- <a aria-label="anchor" href="javascript:void(0);" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-client" data-id="{{ $client->id }}"><i class="ri-delete-bin-line"></i></a> -->
                                                @if(auth()->check() && in_array(optional(auth()->user()->userType)->user_type, \App\Models\UserType::ADMIN_ROLES))
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full toggle-client-status"
                                                    data-id="{{ $client->id }}"data-status="{{ $client->status }}"
                                                    data-name="{{ $client->name }}"data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $client->status ? 'Deactivate' : 'Activate' }}"><i
                                                        class="{{ $client->status ? 'ri-lock-line' : 'ri-check-line' }}"></i></a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($clients->hasPages())
                    <div class="mt-4">
                        {{ $clients->appends(request()->except('page'))->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- Delete Confirmation Alert Modal -->
    <div id="custom-delete-alert"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-alert">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
                <!-- client name will be injected via JS -->
                <p class="mb-4 text-gray-600">You want to deactivate this <span id="delete-modal-client-name">client</span>?</p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                        id="decline-delete">Decline</button>
                    <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-delete">Yes,
                        Deactivate</button>
                </div>
            </div>
        </div>
    </div>
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

    <div id="edit-client" class="edit-client hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-edit-box-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Client</h5>

                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#edit-client">
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
        <form class="ti-custom-validation" id="edit-client-form" method="POST"
            action="#" novalidate>
            @csrf
            @method('PATCH')
            <div class="ti-offcanvas-body edit-client-body">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid lg:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label for="edit_name"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                                        <input type="text" id="edit_name" name="name"
                                            class="ti-form-input rounded-sm form-control-sm"
                                            value="{{ old('name', '') }}" required>
                                        @error('name', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <label for="edit_email"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email
                                            Address</label>
                                        <input type="email" id="edit_email" name="email"
                                            class="ti-form-input rounded-sm form-control-sm"
                                            value="{{ old('email', '') }}" required>
                                        @error('email', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <!-- Phone Number -->
                                    <div class="space-y-2">
                                        <label for="edit_contact_number"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone
                                            Number</label>
                                        <input id="edit_contact_number" type="tel" name="contact_number"
                                            class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                            value="{{ old('contact_number', '') }}"
                                            required>

                                        <input type="hidden" name="contact_country_code" id="edit_contact_country_code"
                                            value="{{ old('contact_country_code', '') }}">

                                        @error('contact_number', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <!-- WhatsApp Number -->
                                    <div class="space-y-2">
                                        <label for="edit_alternate_number"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp
                                            Number</label>
                                        <input id="edit_alternate_number" type="tel" name="alternate_number"
                                            class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                            value="{{ old('alternate_number', '') }}"
                                            required>

                                        <input type="hidden" name="whatsapp_country_code"
                                            id="edit_whatsapp_country_code"
                                            value="{{ old('whatsapp_country_code', '') }}">

                                        @error('alternate_number', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <label for="edit_date_of_birth"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of
                                            Birth</label>
                                        <input type="date" id="edit_date_of_birth" name="date_of_birth"
                                            value="{{ old('date_of_birth', '') }}"
                                            class="ti-form-input rounded-sm form-control-sm">
                                        @error('date_of_birth', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <label for="edit_address"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                        <textarea id="edit_address" name="address" class="ti-form-input w-full rounded-sm form-control-sm" rows="1" maxlength="500" pattern="[A-Za-z0-9\s]{1,500}" title="Address may contain letters, numbers and spaces only (minimum one letter)">{{ old('address', '') }}</textarea>
                                        @error('address', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <label for="edit_country_id"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                        <select name="country_id" id="edit_country_id"
                                            class="ti-form-select rounded-sm form-control-sm w-full" required>
                                            <option value="">Select Country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('country_id', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="space-y-2">
                                        <label for="edit_city"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                        <select name="city" id="edit_city"
                                            class="ti-form-select rounded-sm form-control-sm w-full" required>
                                            <option value="">Select a City</option>
                                        </select>
                                        @error('city', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <button type="submit"
                        class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                </div>
            </div>
        </form>
    </div>
@stop

@push('scripts')
    <script>
        $(document).ready(function() {
            let selectedClientId = null;

            // When delete button clicked
            $(document).on('click', '.delete-client', function(e) {
                e.preventDefault();
                selectedClientId = $(this).data('id');
                const name = $(this).data('name') || 'client';
                $('#delete-modal-client-name').text(name);
                $('#custom-delete-alert').removeClass('hidden');
            });

            // When confirm delete
            $('#confirm-delete').click(function() {
                if (!selectedClientId) return;

                $.ajax({
                    url: "{{ url('admin/clients') }}/" + selectedClientId,
                    type: 'POST',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#custom-delete-alert').addClass('hidden');
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert("Something went wrong.");
                    }
                });
            });

            // Cancel delete
            $('#decline-delete, #close-alert').click(function() {
                $('#custom-delete-alert').addClass('hidden');
                selectedClientId = null;
            });
        });
        $(document).ready(function() {
            let clientIdToToggle = null;
            let currentStatus = null;

            // Initialize tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Status toggle functionality
            $(document).on('click', '.toggle-client-status', function(e) {
                e.preventDefault();
                clientIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                const clientName = $(this).data('name');

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(`Are you sure you want to ${action} ${clientName}?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!clientIdToToggle) return;

                $.ajax({
                    url: "{{ route('admin.clients.toggle-status', '') }}/" + clientIdToToggle,
                    type: 'PATCH',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            // Update the UI elements
                            const button = $(
                                `.toggle-client-status[data-id="${clientIdToToggle}"]`);
                            const newStatus = response.new_status;

                            // Update button icon and tooltip
                            button.find('i')
                                .removeClass(newStatus ? 'ri-lock-line' : 'ri-check-line')
                                .addClass(newStatus ? 'ri-check-line' : 'ri-lock-line');

                            button.attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            // Update status badge (10th column)
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
                        console.error(xhr.responseText);
                        showToast('error', xhr.responseJSON?.message ||
                            "Something went wrong.");
                    }
                });
            });

            // Cancel toggle
            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                clientIdToToggle = null;
                currentStatus = null;
            });

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


    <!-- Add Client code -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Initialize phone inputs
            const phoneInput = document.getElementById("phone");
            const whatsappInput = document.getElementById("whatsapp");
            const contactCodeInput = document.getElementById("contact_country_code");
            const whatsappCodeInput = document.getElementById("whatsapp_country_code");


            const itiPhone = window.intlTelInput(phoneInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            const itiWhatsapp = window.intlTelInput(whatsappInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            // Copy contact number to WhatsApp if empty
            phoneInput.addEventListener('blur', function() {
                if (phoneInput.value && !whatsappInput.value) {
                    whatsappInput.value = phoneInput.value;
                    itiWhatsapp.setNumber(phoneInput.value);
                }
            });

            // Form submission handler
            const form = document.querySelector('form.ti-custom-validation');
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Get country codes
                const phoneCountryData = itiPhone.getSelectedCountryData();
                const whatsappCountryData = itiWhatsapp.getSelectedCountryData();

                // Set hidden inputs
                if (phoneCountryData?.dialCode) {
                    contactCodeInput.value = `+${phoneCountryData.dialCode}`;
                }
                if (whatsappCountryData?.dialCode) {
                    whatsappCodeInput.value = `+${whatsappCountryData.dialCode}`;
                }
                form.submit();

            });

            // Initialize Select2
            $('#add-client-country-code-select').select2({
                width: '100%'
            });
            $('#add-client-city-select').select2({
                width: '100%'
            });

            // Load cities on country change
            $('#add-client-country-code-select').on('change', function() {
                const countryId = $(this).val();
                const citySelect = $('#add-client-city-select').empty().append(
                    '<option value="">Select City</option>');

                if (!countryId) return;

                $.ajax({
                    url: '/get-cities/' + countryId,
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        citySelect.prop('disabled', true);
                    },
                    success: function(response) {
                        if (response?.length) {
                            response.forEach(city => {
                                citySelect.append($('<option></option>').val(city.id)
                                    .text(city.name));
                            });
                        }
                    },
                    complete: function() {
                        citySelect.prop('disabled', false);
                    }
                });
            });

            @if (old('country_id'))
                $('#add-client-country-code-select').val('{{ old('country_id') }}').trigger('change');
            @endif
        });

        document.addEventListener('DOMContentLoaded', function() {
            @if ($errors->add->any())
                const addClientForm = document.getElementById('add-client-form');
                const addClientButton = document.querySelector('[aria-controls="add-client-form"]');

                if (addClientForm && addClientButton) {
                    addClientForm.classList.remove('hidden');
                    addClientButton.classList.add('hs-accordion-active');
                }
            @endif
        });
    </script>


    <!-- Edit client code -->
    <script>
        $(document).ready(function() {
            // Updated selectors with edit_ prefix
            const phoneInputEdit = document.getElementById('edit_contact_number');
            const whatsappInputEdit = document.getElementById('edit_alternate_number');

            const itiPhoneEdit = window.intlTelInput(phoneInputEdit, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            const itiWhatsappEdit = window.intlTelInput(whatsappInputEdit, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            // Initialize Select2 plugins with updated IDs
            $('#edit_country_id').select2({
                dropdownParent: $('#edit-client'),
                width: '100%'
            });
            $('#edit_city').select2({
                dropdownParent: $('#edit-client'),
                width: '100%'
            });

            // Load cities when country changes in the Edit Client modal
            $('#edit_country_id').on('change', function () {
                const countryId = $(this).val();
                const citySelect = $('#edit_city');
                citySelect.empty().append('<option value="">Select a City</option>');

                if (!countryId) {
                    citySelect.val(null).trigger('change');
                    return;
                }

                $.ajax({
                    url: '/get-cities/' + countryId,
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function () { citySelect.prop('disabled', true); },
                    success: function (response) {
                        if (Array.isArray(response) && response.length) {
                            response.forEach(function (city) {
                                citySelect.append($('<option></option>').val(city.id).text(city.name));
                            });
                        }
                    },
                    error: function () {
                        citySelect.html('<option>Error loading cities</option>');
                    },
                    complete: function () {
                        citySelect.prop('disabled', false).trigger('change');
                    }
                });
            });

            $('#edit-client-form').on('submit', function() {
                const phoneCountryData = itiPhoneEdit.getSelectedCountryData();
                const whatsappCountryData = itiWhatsappEdit.getSelectedCountryData();

                if (phoneCountryData.dialCode) {
                    $('#edit_contact_country_code').val(`+${phoneCountryData.dialCode}`);
                }
                if (whatsappCountryData.dialCode) {
                    $('#edit_whatsapp_country_code').val(`+${whatsappCountryData.dialCode}`);
                }
            });

            $(document).on('click', '.edit-client-btn[data-client-id]', function(e) {
                e.preventDefault();
                $('.text-danger, .text-red-500').remove();
                $('.ti-form-input').removeClass('border-red-500');
                const clientId = $(this).data('client-id');
                if (!clientId) return;
                const updateUrl = `/admin/client/${clientId}`;
                $('#edit-client-form').attr('action', updateUrl);

                // Clear previous form data
                $('#edit-client-form')[0].reset();
                $('#edit_country_id').val(null).trigger('change');
                $('#edit_city').empty().append('<option value="">Select City</option>').val(null).trigger(
                    'change');

                // Fetch client details via AJAX
                $.ajax({
                    url: `/admin/client/${clientId}/data`,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        const client = response.client;

                        // Updated field selectors with edit_ prefix
                        $('#edit_name').val(client.name);
                        $('#edit_email').val(client.email);
                        $('#edit_date_of_birth').val(client.date_of_birth);
                        $('#edit_address').val(client.address);

                        if (client.contact_number) {
                            itiPhoneEdit.setNumber(client.contact_number.replace('-', ''));
                        }
                        if (client.alternate_number) {
                            itiWhatsappEdit.setNumber(client.alternate_number.replace('-', ''));
                        }

                        $('#edit_country_id').val(client.country_id).trigger('change');

                        $.ajax({
                            url: '/get-cities/' + client.country_id,
                            type: 'GET',
                            dataType: 'json',
                            success: function(cities) {
                                const citySelect = $('#edit_city');
                                citySelect.empty().append(
                                    '<option value="">Select City</option>');
                                cities.forEach(function(city) {
                                    citySelect.append($('<option></option>')
                                        .val(city.id).text(city.name));
                                });
                                citySelect.val(client.city_id).trigger('change');
                            }
                        });
                    },
                    error: function(error) {
                        console.error("Failed to load client data:", error);
                        alert("Could not load client details. Please try again.");
                    }
                });
                
                // Ensure offcanvas becomes visible (fallback)
                ensureOffcanvasVisible('#edit-client');
                setTimeout(function() { ensureOffcanvasVisible('#edit-client'); }, 150);
                setTimeout(function() { ensureOffcanvasVisible('#edit-client'); }, 500);
            });

            // Helper: ensure offcanvas shows up if HSOverlay fails (fallback)
            function ensureOffcanvasVisible(selector) {
                try {
                    var $el = $(selector);
                    if (!$el.length) return;

                    // If already open, nothing to do
                    if ($el.hasClass('open') && !$el.hasClass('hidden')) return;

                    // Add necessary classes
                    $el.removeClass('hidden').addClass('open');
                    $('body').addClass('ti-offcanvas-open');

                    // Ensure it is on screen (right side)
                    $el.css({
                        'transform': 'translateX(0)',
                        'right': '0',
                        'visibility': 'visible'
                    });

                    // If no backdrop exists for this overlay, create one so clicking outside closes it
                    try {
                        var id = $el.attr('id') || selector.replace(/[#\.]/g, '');
                        var backdropId = id + '-backdrop';
                        if (!document.getElementById(backdropId)) {
                            var $back = $('<div/>', {
                                id: backdropId,
                                class: 'hs-overlay-backdrop transition duration fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80',
                            }).css('z-index', (parseInt($el.css('z-index')) || 1100) - 1);
                            // clicking backdrop should close and cleanup
                            $back.on('click', function() {
                                try {
                                    $el.addClass('hidden').removeClass('open');
                                    $el.css({'transform':'', 'right':'', 'visibility':''});
                                    cleanupOverlays();
                                    // set just closed flag briefly to avoid reopen race
                                    window._hsJustClosed = true;
                                    setTimeout(function() { window._hsJustClosed = false; }, 300);
                                } catch (e) {}
                            });
                            $(document.body).append($back);
                        }
                    } catch (e) {}

                    // No spinner required; just ensure classes and visibility are set so offcanvas is visible
                } catch (err) {
                }
            }

            // Cleanup helper to remove backdrops/body styles left behind by offcanvas/overlay
            function cleanupOverlays() {
                try {
                    // Remove known body class
                    $('body').removeClass('ti-offcanvas-open');

                    // Restore potential body/html styles set by overlays
                    $('body, html').css({
                        'overflow': '',
                        'padding-right': '',
                        'height': '',
                        'pointer-events': ''
                    });

                    // Remove common backdrop/overlay classes
                    $('.hs-overlay-backdrop, .ti-offcanvas-backdrop, .offcanvas-backdrop, .modal-backdrop, .overlay-backdrop').remove();

                    // Remove any visible backdrop-like elements that contain backdrop or overlay in their class name
                    $('*[class*="backdrop"], *[class*="overlay"]').each(function() {
                        var $el = $(this);
                        // don't remove modal containers (hs-overlay elements)
                        if ($el.hasClass('hs-overlay') || $el.closest('.hs-overlay').length) return;
                        // remove elements that are positioned fixed and covering the viewport
                        var pos = $el.css('position');
                        var z = parseInt($el.css('z-index')) || 0;
                        if (pos === 'fixed' && z >= 1000) {
                            $el.remove();
                        }
                    });

                    // Also hide any visible hs-overlay elements (offcanvas/modals)
                    $('.hs-overlay').each(function() {
                        var $el = $(this);
                        if (!$el.hasClass('edit-client') && !$el.hasClass('add-client')) {
                            $el.addClass('hidden').removeClass('open');
                            $el.css({'transform':'', 'right':'', 'visibility':''});
                        }
                    });
                } catch (err) {
                }
            }
        });
    </script>

    @if (old('country_id') && $errors->getBag('edit')->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const oldCountryId = "{{ old('country_id') }}";
                const oldCityId = "{{ old('city') }}";

                $.ajax({
                    url: '/get-cities/' + oldCountryId,
                    type: 'GET',
                    dataType: 'json',
                    success: function(cities) {
                        const citySelect = $('#edit_city');
                        citySelect.empty().append('<option value="">Select City</option>');
                        cities.forEach(function(city) {
                            citySelect.append(
                                $('<option></option>').val(city.id).text(city.name)
                            );
                        });
                        citySelect.val(oldCityId).trigger('change');
                    }
                });
            });
        </script>
    @endif

    @if ($errors->edit->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                console.log(typeof HSOverlay);
                const overlay = document.getElementById('edit-client');
                if (overlay) {
                    overlay.classList.add('open');
                    overlay.classList.remove('hidden');
                    overlay.style.display = 'block';
                } else {
                    console.warn('#edit-client not found');
                }
            });
        </script>
    @endif
@endpush
