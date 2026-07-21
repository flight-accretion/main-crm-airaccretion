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
                    <div class="hs-accordion" id="add-service-address-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px"
                                            viewBox="0 0 24 24" width="24px" fill="#000000">
                                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                                            <path
                                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z">
                                            </path>
                                        </svg>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Add Service Address</h5>
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="add-service-address-form">
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
                                                Add Address
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-service-address-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300 @if ($errors->add->any()) !block @endif"
                            aria-labelledby="add-service-address-accordion">
                            <div class="box-body">
                                <form class="ti-custom-validation" action="{{ route('admin.service-addresses.store') }}"
                                    method="POST" novalidate>
                                    @csrf
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="service_id" class="ti-form-label mb-0">Service<span
                                                    class="text-danger">*</span></label>

                                            <select class="ti-form-select rounded-sm form-control-sm" name="service_id"
                                                id="service_id" required>
                                                <option value="">Select Service</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}"
                                                        {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                                        {{ $service->service }}</option>
                                                @endforeach
                                            </select>
                                            @error('service_id', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="product_id" class="ti-form-label mb-0">Product<span
                                                    class="text-danger">*</span></label>
                                            <select class="ti-form-select rounded-sm form-control-sm" name="product_id"
                                                id="product_id" required>
                                                <option value="">Select Product</option>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}"
                                                        {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                                        {{ $product->product }}</option>
                                                @endforeach
                                            </select>
                                            @error('product_id', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="contact_person_name" class="ti-form-label mb-0">Contact Person
                                                Name<span class="text-danger">*</span></label>
                                            <input type="text" name="contact_person_name"
                                                class="ti-form-input rounded-sm form-control-sm" id="contact_person_name"
                                                value="{{ old('contact_person_name') }}"
                                                placeholder="Enter Contact Person Name" required>
                                            @error('contact_person_name', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label class="ti-form-label mb-0">Contact Number<span
                                                    class="text-danger">*</span></label>
                                            <input type="hidden" name="country_code" id="country_code"
                                                value="{{ old('country_code') }}">
                                            <input type="tel" name="contact_number" id="contact_number"
                                                class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                                value="{{ old('contact_number') }}" placeholder="Enter Contact Number"
                                                required>
                                            @error('country_code', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                            @error('contact_number', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label class="ti-form-label mb-0">Country<span
                                                    class="text-danger">*</span></label>
                                            <select name="country_id" id="countrySelect"
                                                class="ti-form-select rounded-sm form-control-sm" required>
                                                <option value="">Select Country</option>
                                                @foreach ($countries as $country)
                                                    <option value="{{ $country->id }}"
                                                        data-phonecode="+{{ $country->phonecode }}"
                                                        {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                        {{ $country->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('country_id', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label class="ti-form-label mb-0">State<span
                                                    class="text-danger">*</span></label>
                                            <select name="state_id" id="stateSelect"
                                                class="ti-form-select rounded-sm form-control-sm" required>
                                                <option value="">Select State</option>
                                                @php
                                                    $selectedCountryId = old('country_id');
                                                @endphp
                                                @if ($selectedCountryId)
                                                    @foreach ($states->where('country_id', $selectedCountryId) as $state)
                                                        <option value="{{ $state->id }}"
                                                            {{ old('state_id') == $state->id ? 'selected' : '' }}>
                                                            {{ $state->name }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('state_id', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label class="ti-form-label mb-0">City<span
                                                    class="text-danger">*</span></label>
                                            <select name="city_id" id="citySelect"
                                                class="ti-form-select rounded-sm form-control-sm" required>
                                                <option value="">Select City</option>
                                                @php
                                                    $selectedStateId = old('state_id');
                                                @endphp
                                                @if ($selectedStateId)
                                                    @foreach ($cities->where('state_id', $selectedStateId) as $city)
                                                        <option value="{{ $city->id }}"
                                                            {{ old('city_id') == $city->id ? 'selected' : '' }}>
                                                            {{ $city->name }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('city_id', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="map_link" class="ti-form-label mb-0">Map Link</label>
                                            <input type="url" name="map_link"
                                                class="ti-form-input rounded-sm form-control-sm" id="map_link"
                                                value="{{ old('map_link') }}" placeholder="Enter Map URL">
                                        </div>

                                        <div class="xl:col-span-4 col-span-12">
                                            <label for="address" class="ti-form-label mb-0">Address <span
                                                    class="text-danger">*</span></label>
                                            <textarea name="address" class="ti-form-input rounded-sm form-control-sm" id="address" rows="1"
                                                placeholder="Enter Address" required>{{ old('address') }}</textarea>
                                            @error('address', 'add')
                                                <div class="text-danger mt-1 text-sm">{{ $message }}</div>
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


    <!-- Addresses Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">
                        All Service Addresses
                    </div>
                    {{--  <a href="{{ route('admin.service-addresses.create') }}" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave">
                            <i class="ri-add-line"></i> Add Address
                        </a>  --}}
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable server-paginated" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th data-priority="1">S.No</th>
                                    <th data-priority="2">Contact Person</th>
                                    <th data-priority="3">Address</th>
                                    <th data-priority="4">Contact Number</th>
                                    <th data-priority="5">Product</th>
                                    <th data-priority="6">Service</th>
                                    <th data-priority="5">City</th>
                                    <th data-priority="6">Status</th>
                                    <th data-priority="7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($addresses as $key => $address)
                                    <tr class="border-b border-defaultborder">

                                        <td class="text-center">{{ ($addresses->firstItem() ?? 1) + $key }}</td>
                                        <td>{{ $address->contact_person_name }}</td>
                                        <td>{{ Str::limit($address->address, 30) }}</td>
                                        <td class="text-center">{{ $address->contact_number }}</td>
                                        <td>
                                            @if ($address->product)
                                                {{ $address->product->product }}
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                        <td>
                                            @if ($address->service)
                                                {{ Str::limit($address->service->service, 50) }}
                                            @else
                                                N/A
                                            @endif
                                        </td>

                                        <td>{{ $address->city->name ?? 'N/A' }}</td>
                                        <td class="address-status-badge text-center">
                                            @if ($address->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-address-btn"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View"
                                                    data-address-id="{{ $address->id }}">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <a aria-label="anchor"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-address-btn"
                                                    data-id="{{ $address->id }}" data-bs-toggle="tooltip"
                                                    data-bs-placement="top" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>

                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full toggle-address-status"
                                                    data-id="{{ $address->id }}" data-status="{{ $address->status }}"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $address->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $address->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($addresses->hasPages())
                    <div class="mt-4">
                        {{ $addresses->appends(request()->except('page'))->links() }}
                    </div>
                    @endif
                </div>
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

    <div id="edit-service-address"
        class="edit-service-address hs-overlay ti-offcanvas ti-offcanvas-right @if ($errors->edit->any()) open @else hidden @endif"
        tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-map-pin-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Service Address</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                                data-hs-overlay="#edit-service-address">
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

        <div class="ti-offcanvas-body edit-service-address-body">
            <form class="ti-custom-validation" action="{{ optional($address ?? null)->id ? route('admin.service-addresses.update', optional($address ?? null)->id) : '#' }}"
                id="edit-service-address-form" method="POST" novalidate>
                @csrf
                @method('PUT')
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <!-- Service -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                                        <select name="service_id" id="edit_service_id"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select Service</option>
                                            @foreach ($services as $service)
                                                <option value="{{ $service->id }}"
                                                    {{ old('service_id', optional($address ?? null)->service_id) == $service->id ? 'selected' : '' }}>
                                                    {{ $service->service }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('service_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <!-- Product -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                                        <select name="product_id" id="edit_product_id"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select Product</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}"
                                                    {{ old('product_id', optional($address ?? null)->product_id) == $product->id ? 'selected' : '' }}>
                                                    {{ $product->product }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('product_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Contact Person -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Person
                                            Name</label>
                                        <input type="text" name="contact_person_name" id="edit_contact_person_name"
                                            class="ti-form-input w-full rounded-sm form-control-sm"
                                            value="{{ old('contact_person_name') }}" required>
                                        @error('contact_person_name', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Contact Number -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact
                                            Number</label>
                                        <input type="hidden" name="contact_country_code" id="edit_contact_country_code"
                                            value="{{ old('contact_country_code', optional($address ?? null)->contact_country_code) }}">
                                        <input type="tel" name="contact_number" id="edit_contact_number"
                                            class="ti-form-input w-full rounded-sm form-control-sm intl-phone-input"
                                            value="{{ old('contact_number', '') }}" required
                                            placeholder="Enter Contact Number">
                                        @error('contact_number', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                        @error('contact_country_code', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Country -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                        <select name="country_id" id="edit_countrySelect"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select Country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    data-phonecode="+{{ $country->phonecode }}"
                                                    {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('country_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- State -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">State</label>
                                        <select name="state_id" id="edit_stateSelect"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select State</option>
                                            @if (old('country_id') || isset($address))
                                                @foreach ($states->where('country_id', old('country_id', optional($address ?? null)->country_id)) as $state)
                                                    <option value="{{ $state->id }}"
                                                        {{ old('state_id', optional($address ?? null)->state_id) == $state->id ? 'selected' : '' }}>
                                                        {{ $state->name }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('state_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- City -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                        <select name="city_id" id="edit_citySelect"
                                            class="ti-form-select rounded-sm form-control-sm" required>
                                            <option value="">Select City</option>
                                            @if (old('state_id') || isset($address))
                                                @foreach ($cities->where('state_id', old('state_id', optional($address ?? null)->state_id)) as $city)
                                                    <option value="{{ $city->id }}"
                                                        {{ old('city_id', optional($address ?? null)->city_id) == $city->id ? 'selected' : '' }}>
                                                        {{ $city->name }}
                                                    </option>
                                                @endforeach
                                            @endif
                                        </select>
                                        @error('city_id', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Map Link -->
                                    <div class="xl:col-span-6 col-span-12">
                                        <label for="map_link" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map
                                            Link</label>
                                        <input type="url" name="map_link" id="edit_map_link"
                                            class="ti-form-input w-full rounded-sm form-control-sm"
                                            value="{{ old('map_link') }}" placeholder="Enter Map URL">
                                        @error('map_link', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <!-- Address -->
                                    <div class="xl:col-span-12 col-span-12">
                                        <label for="address"
                                            class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                        <textarea name="address" id="edit_address" rows="3" class="ti-form-input w-full rounded-sm form-control-sm"
                                            required placeholder="Enter Address">{{ old('address') }}</textarea>
                                        @error('address', 'edit')
                                            <span class="text-red-500 text-sm">{{ $message }}</span>
                                        @enderror
                                    </div>

                                    <div class="xl:col-span-12 col-span-12 mt-4">
                                        <button type="submit"
                                            class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Update
                                            Address</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="edit-service-address" class="edit-service-address hs-overlay hidden ti-offcanvas ti-offcanvas-right"
        tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-map-pin-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Service Address</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                                data-hs-overlay="#edit-service-address">
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
    </div>

    <!-- View Service Address Modal -->
    <div id="view-service-address" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <svg class="svg-white" xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 0 24 24"
                            width="24px" fill="#000000">
                            <path d="M0 0h24v24H0V0z" fill="none"></path>
                            <path
                                d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z">
                            </path>
                        </svg>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">View Service Address</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn p-0 text-gray-500 hover:text-gray-700 dark:text-[#8c9097] dark:hover:text-white/80"
                                data-hs-overlay="#view-service-address">
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
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 sm:gap-6" id="view-address-content">
                                <!-- Content will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let addressIdToToggle = null;
            let currentStatus = null;
            let $buttonElement = null;

            // Toggle address status functionality
            $(document).on('click', '.toggle-address-status', function(e) {
                e.preventDefault();
                addressIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                $buttonElement = $(this);
                const contactPerson = $(this).closest('tr').find('td:nth-child(3)').text()
                    .trim();

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(
                    `Are you sure you want to ${action} address for "${contactPerson}"?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!addressIdToToggle) return;

                const url = "{{ route('admin.service-addresses.toggle-status', ':id') }}"
                    .replace(':id', addressIdToToggle);

                $.ajax({
                    url: url,
                    type: 'POST',
                    data: {
                        _token: "{{ csrf_token() }}",
                        _method: 'PATCH'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            // Update all relevant elements
                            const newStatus = response.status;
                            // 1. Update the button's data-status attribute
                            $buttonElement.data('status', newStatus);
                            $buttonElement.attr('data-status', newStatus);


                            // 2. Update the icon classes
                            const $icon = $buttonElement.find('i');
                            if (newStatus) {
                                $icon.removeClass('ri-check-line').addClass(
                                    'ri-lock-line');
                            } else {
                                $icon.removeClass('ri-lock-line').addClass(
                                    'ri-check-line');
                            }

                            // 3. Update the button title
                            $buttonElement.attr('title', newStatus ? 'Deactivate' :
                                'Activate');

                            // 4. Update the status badge
                            const statusBadge = $buttonElement.closest('tr').find(
                                '.address-status-badge');
                            statusBadge.html(newStatus ?
                                '<span class="badge bg-success/10 text-success">Active</span>' :
                                '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                            );

                        } else {
                            showToast('error', response.message ||
                                "Operation failed");
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message ||
                            "Server error occurred");
                        $('#toggle-status-modal').addClass('hidden');
                    }
                });
            });

            // Cancel toggle
            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                addressIdToToggle = null;
                currentStatus = null;
                $buttonElement = null;
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

    <!-- ======================================================= -->
    <!-- == Script for ADD FORM and general page actions      == -->
    <!-- ======================================================= -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {

            // --- General Page Actions (Status Toggle) ---
            let addressIdToToggle = null;
            let $buttonElement = null;
            let addIti;
            // Initialize the Add Form logic when the page loads
            initializeAddForm();

            function initializeAddForm() {
                // Initialize Select2 for Add Form dropdowns
                $('#add-service-address-form select').select2({
                    width: '100%',
                    dropdownParent: $('#add-service-address-form')
                });

                // Initialize International Phone Input for Add form
                const addPhoneInput = document.querySelector("#contact_number");
                if (addPhoneInput) {
                    addIti = window.intlTelInput(addPhoneInput, {
                        initialCountry: "in",
                        separateDialCode: true,
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                    });
                    // On form submit, update the hidden country code field
                    addPhoneInput.form.addEventListener("submit", function() {
                        const countryData = addIti.getSelectedCountryData();
                        document.querySelector("#country_code").value = '+' + countryData.dialCode;
                    });
                }
            }

            // Fetch states when country changes
            $('#countrySelect').on('change', function() {
                const countryId = $(this).val();
                const stateDropdown = $('#stateSelect');

                stateDropdown.empty().append('<option value="">Select State</option>');

                if (countryId) {
                    $.get(`/admin/service-addresses/states/${countryId}`, function(response) {
                        if (response.states) {
                            console.log(response.states);
                            response.states.forEach(state => {
                                stateDropdown.append(new Option(state.name, state.id));
                            });
                        }
                    }).fail(error => {
                        console.error("State fetch failed:", error.responseJSON?.error);
                    });
                }
            });

            // Fetch cities
            $('#stateSelect').on('change', function() {
                const stateId = $(this).val();
                const cityDropdown = $('#citySelect');

                cityDropdown.empty().append('<option value="">Select City</option>');

                if (stateId) {
                    $.get(`/admin/service-addresses/cities/${stateId}`, function(cities) {
                        cities.forEach(city => {
                            cityDropdown.append(new Option(city.name, city.id));
                        });
                    }).fail(error => {
                        console.error("City fetch failed:", error.responseJSON?.error);
                    });
                }
            });



            // Utility function to show toast notifications
            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className =
                    `fixed top-5 right-5 z-[9999] px-4 py-2 rounded-md text-white ${type === 'success' ? 'bg-green-600' : 'bg-red-600'}`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
    </script>

    <!-- ======================================================= -->
    <!-- == Script for EDIT FORM  == -->
    <!-- ======================================================= -->
    @if ($errors->edit->any())
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                if (window.HSOverlay) {
                    window.HSOverlay.open('#edit-service-address');
                } else {
                    // Fallback: show modal by removing 'hidden' class
                    document.getElementById('edit-service-address').classList.remove('hidden');
                    $('.edit-service-address-body select').select2({
                        width: '100%',
                        dropdownParent: $('#edit-service-address')
                    });
                }
            });
        </script>
    @endif
    <script>
        let editIti;
        let currentEditAddressId = null;
        let initialServiceId = null;
        let initialStateId = null;
        let initialCityId = null;


        $(document).ready(function() {
            // Initialize edit form phone input
            const editPhoneInput = document.querySelector("#edit_contact_number");
            if (editPhoneInput) {
                editIti = window.intlTelInput(editPhoneInput, {
                    initialCountry: "in",
                    separateDialCode: true,
                    utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                });
                // Set initial value if present
                const oldCountryCode = "{{ old('contact_country_code', optional($address ?? null)->contact_country_code ?? '') }}";
                const oldContactNumber = "{{ old('contact_number', optional($address ?? null)->contact_number ?? '') }}";
                if (oldCountryCode && oldContactNumber) {
                    editIti.setNumber('+' + oldCountryCode + oldContactNumber);
                }
            }
            // Initialize select2 after populating options
            $('.edit-service-address-body select').select2({
                width: '100%',
                dropdownParent: $('#edit-service-address')
            });
            // Handle edit button click
            $(document).on('click', '.edit-address-btn', function(e) {
                e.preventDefault();
                const addressId = $(this).data('id');
                currentEditAddressId = addressId;
                $('#edit-service-address-form').attr('action', `/admin/service-addresses/${addressId}`);
                // Clear form first
                resetEditForm();

                // Fetch and populate data
                fetchAddressDetails(addressId);
                // Initialize select2 for edit form dropdowns
                $('#edit_product_id, #edit_service_id, #edit_countrySelect, #edit_stateSelect, #edit_citySelect')
                    .select2({
                        width: '100%',
                        dropdownParent: $('#edit-service-address')
                    });
            });

            // Function to reset edit form
            function resetEditForm() {
                $('.edit-service-address-body form')[0].reset();
                $('.text-red-500').remove();

            }

            // Function to fetch address details
            function fetchAddressDetails(addressId) {
                $.ajax({
                    url: `/admin/service-addresses/${addressId}/edit-details`,
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {

                    },
                    success: function(response) {
                        if (response.success) {
                            initialServiceId = response.data.serviceAddress.service_id;
                            initialStateId = response.data.serviceAddress.city.state_id;
                            initialCityId = response.data.serviceAddress.city_id;
                            populateEditForm(response.data);
                            // Open overlay/modal after population
                            if (window.HSOverlay) {
                                window.HSOverlay.open('#edit-service-address');
                            } else {
                                $('#edit-service-address').removeClass('hidden').addClass('open');
                                $('body').addClass('ti-offcanvas-open');
                            }
                        } else {
                            showToast('error', response.message || 'Failed to load address details');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching address details:', xhr);
                        showToast('error', 'Failed to load address details');
                    },
                    complete: function() {
                        $('.edit-service-address-body').find('button[type="submit"]').prop('disabled',
                            false).text('Update Address');
                    }
                });
            }

            // Function to populate edit form
            function populateEditForm(data) {
                console.log('Edit data received:', data);
                const serviceAddress = data.serviceAddress;

                let rawContactNumber = serviceAddress.contact_number || '';
                let countryCode = '+91';
                let nationalNumber = rawContactNumber;

                if (rawContactNumber.includes('-')) {
                    const parts = rawContactNumber.split('-');
                    if (parts[0].startsWith('+')) {
                        countryCode = parts[0];
                    } else {
                        countryCode = '+' + parts[0];
                    }
                    nationalNumber = parts[1];
                }

                // Populate products dropdown (only active products)
                const productSelect = $('#edit_product_id');
                productSelect.empty().append('<option value="">Select Product</option>');
                data.products.forEach(function(product) {
                    productSelect.append(`<option value="${product.id}">${product.product}</option>`);
                });

                // Populate services dropdown (all active services)
                const serviceSelect = $('#edit_service_id');
                serviceSelect.empty().append('<option value="">Select Service</option>');
                if (data.services && data.services.length) {
                    data.services.forEach(function(service) {
                        serviceSelect.append(`<option value="${service.id}">${service.service}</option>`);
                    });
                }

                // Populate countries dropdown
                const countrySelect = $('#edit_countrySelect');
                countrySelect.empty().append('<option value="">Select Country</option>');
                data.countries.forEach(function(country) {
                    countrySelect.append(
                        `<option value="${country.id}" data-phonecode="+${country.phonecode}">${country.name}</option>`
                    );
                });

                // Set values
                productSelect.val(serviceAddress.product_id).trigger('change');
                serviceSelect.val(serviceAddress.service_id).trigger('change');
                $('#edit_contact_person_name').val(serviceAddress.contact_person_name);
                $('#edit_contact_country_code').val(countryCode.replace('+', ''));
                $('#edit_contact_number').val(contact_number);
                $('#edit_address').val(serviceAddress.address);
                $('#edit_map_link').val(serviceAddress.map_link || '');

                if (typeof editIti !== 'undefined' && editIti) {
                    try {
                        editIti.setNumber(countryCode + nationalNumber);
                    } catch (e) {
                        console.error("Error setting phone number:", e);
                    }
                }

                // Set country and then load states/cities
                if (serviceAddress.city && serviceAddress.city.state) {
                    countrySelect.val(serviceAddress.city.state.country_id).trigger('change');

                    setTimeout(() => {
                        const stateSelect = $('#edit_stateSelect');
                        stateSelect.empty().append('<option value="">Select State</option>');

                        if (data.states && data.states.length) {
                            data.states.forEach(function(state) {
                                stateSelect.append(
                                    `<option value="${state.id}">${state.name}</option>`);
                            });
                            stateSelect.val(serviceAddress.city.state_id).trigger('change');

                            setTimeout(() => {
                                const citySelect = $('#edit_citySelect');
                                citySelect.empty().append('<option value="">Select City</option>');

                                if (data.cities && data.cities.length) {
                                    data.cities.forEach(function(city) {
                                        citySelect.append(
                                            `<option value="${city.id}">${city.name}</option>`
                                        );
                                    });
                                    citySelect.val(serviceAddress.city_id);
                                }
                            }, 200);
                        }
                    }, 200);
                }

                // Re-initialize select2
                $('.edit-service-address-body select').select2({
                    width: '100%',
                    dropdownParent: $('#edit-service-address')
                });
            }

            // Handle product change in edit form
            // Handle product change in edit form (no AJAX, just keep all active services visible)
            $('#edit_product_id').on('change', function() {
                // No need to filter services by product, just keep all active services in the dropdown
                // Optionally, you can reset the selected service if you want:
                // $('#edit_service_id').val('').trigger('change');
            });

            $('#edit_countrySelect').on('change', function() {
                const countryId = $(this).val();
                const stateSelect = $('#edit_stateSelect');
                const citySelect = $('#edit_citySelect');

                stateSelect.empty().append('<option value="">Select State</option>');
                citySelect.empty().append('<option value="">Select City</option>');

                if (countryId) {
                    // Get the selected country's phone code
                    const phoneCode = $(this).find('option:selected').data('phonecode');
                    $('#edit_contact_country_code').val(phoneCode);

                    // Fetch states for the selected country
                    $.ajax({
                        url: `/admin/service-addresses/states/${countryId}`,
                        type: 'GET',
                        success: function(response) {
                            if (response.states && response.states.length) {
                                response.states.forEach(state => {
                                    stateSelect.append(new Option(state.name, state
                                        .id));
                                });
                            } else {
                                stateSelect.append(
                                    '<option value="">No states available</option>');
                            }
                        },
                        error: function(xhr) {
                            console.error("State fetch failed:", xhr.responseJSON?.error);
                            stateSelect.append(
                                '<option value="">Error loading states</option>');
                        }
                    });
                }
            });

            // Handle state change in edit form
            $('#edit_stateSelect').on('change', function() {
                const stateId = $(this).val();
                const citySelect = $('#edit_citySelect');

                citySelect.empty().append('<option value="">Select City</option>');

                if (stateId) {
                    $.ajax({
                        url: `/admin/service-addresses/cities/${stateId}`,
                        type: 'GET',
                        success: function(response) {
                            if (response && response.length) {
                                response.forEach(city => {
                                    citySelect.append(new Option(city.name, city.id));
                                });
                            } else {
                                citySelect.append(
                                    '<option value="">No cities available</option>');
                            }
                        },
                        error: function(xhr) {
                            console.error("City fetch failed:", xhr.responseJSON?.error);
                            citySelect.append('<option value="">Error loading cities</option>');
                        }
                    });
                }
            });

            // Handle form submission
            $('.edit-service-address-body form').on('submit', function(e) {
                if (editIti) {
                    const countryData = editIti.getSelectedCountryData();
                    $('#edit_contact_country_code').val('+' + countryData.dialCode);
                    // Set only the national number (without country code)
                    const nationalNumber = editIti.getNumber(intlTelInputUtils.numberFormat.NATIONAL)
                        .replace(/\D/g, '');
                    $('#edit_contact_number').val(nationalNumber);
                }
            });
        });

        // View Address Modal functionality
        $(document).on('click', '.view-address-btn', function() {
            const addressId = $(this).data('address-id');

            // Show loading state
            $('#view-address-content').html(`
                    <div class="col-span-12 text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                        <p class="mt-2 text-gray-600">Loading address details...</p>
                    </div>
                `);

            // Open modal using HSOverlay or fallback
            if (window.HSOverlay) {
                window.HSOverlay.open('#view-service-address');
            } else {
                // Fallback: show modal by removing 'hidden' class and adding 'open'
                $('#view-service-address').removeClass('hidden').addClass('open');
                $('body').addClass('ti-offcanvas-open');
            }

            // Fetch address details
            $.ajax({
                url: `/admin/service-addresses/${addressId}/view-modal`,
                type: 'GET',
                success: function(response) {
                    if (response.success) {
                        displayAddressDetails(response.serviceAddress, response.country_code, response
                            .contact_number);
                    } else {
                        $('#view-address-content').html(`
                                <div class="col-span-12 text-center py-8">
                                    <i class="ri-error-warning-line text-4xl text-red-500 mb-4"></i>
                                    <p class="text-red-600">${response.message || 'Failed to load address details'}</p>
                                </div>
                            `);
                    }
                },
                error: function(xhr) {
                    $('#view-address-content').html(`
                            <div class="col-span-12 text-center py-8">
                                <i class="ri-error-warning-line text-4xl text-red-500 mb-4"></i>
                                <p class="text-red-600">Error loading address details</p>
                            </div>
                        `);
                }
            });
        });

        function displayAddressDetails(address, countryCode, contactNumber) {
            const content = `
                    <!-- Contact Person -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Person Name</label>
                        <p class="text-gray-800 dark:text-white">${address.contact_person_name || 'N/A'}</p>
                    </div>
                    
                    <!-- Contact Number -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                        <p class="text-gray-800 dark:text-white">${address.contact_number || 'N/A'}</p>
                    </div>
                    
                    <!-- Service -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                        <p class="text-gray-800 dark:text-white">${address.service ? address.service.service : 'N/A'}</p>
                    </div>

                    <!-- Product -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                        <p class="text-gray-800 dark:text-white">${address.product ? address.product.product : 'N/A'}</p>
                    </div>
                    
                    <!-- Country -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                        <p class="text-gray-800 dark:text-white">${address.city && address.city.state && address.city.state.country ? address.city.state.country.name : 'N/A'}</p>
                    </div>
                    
                    <!-- State -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">State</label>
                        <p class="text-gray-800 dark:text-white">${address.city && address.city.state ? address.city.state.name : 'N/A'}</p>
                    </div>
                    
                    <!-- City -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                        <p class="text-gray-800 dark:text-white">${address.city ? address.city.name : 'N/A'}</p>
                    </div>

                    <!-- Map Link -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map Link</label>
                        <p class="text-gray-800 dark:text-white">
                            ${address.map_link ? `<a href="${address.map_link}" target="_blank" class="text-blue-600 hover:text-blue-800">View on Map <i class="ri-external-link-line"></i></a>` : 'N/A'}
                        </p>
                    </div>
                    <!-- Address -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                        <p class="text-gray-800 dark:text-white">${address.address || 'N/A'}</p>
                    </div>
                    
                    <!-- Status -->
                    <div class="xl:col-span-6 col-span-12">
                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                        <p class="text-gray-800 dark:text-white">
                            <span class="badge ${address.status == 1 ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger'}">
                                ${address.status == 1 ? 'Active' : 'Inactive'}
                            </span>
                        </p>
                    </div> 
                    
                `;
            $('#view-address-content').html(content);
        }

        // Close view modal handler
        $(document).on('click', '[data-hs-overlay="#view-service-address"]', function() {
            $('#view-service-address').addClass('hidden').removeClass('open');
            $('body').removeClass('ti-offcanvas-open');
        });
    </script>



@endpush
