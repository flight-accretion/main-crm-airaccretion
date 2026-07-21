@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold"> Add Service Address</h3>
    </div>
    <ol class="flex items-center whitespace-nowrap min-w-0">
        <li class="text-[0.813rem] ps-[0.5rem]">
            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.service-addresses.index') }}">
                Service Address
                <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
            </a>
        </li>
        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
            Add Service Address
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

<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <form class="ti-custom-validation" action="{{ route('admin.service-addresses.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="grid grid-cols-12 sm:gap-6">
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="product_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                            <select class="ti-form-select rounded-sm form-control-sm" name="product_id" id="product_id">
                                <option value="">Select Product</option>
                                @foreach($products as $product)
                                <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                    {{ $product->product }}
                                </option>
                                @endforeach
                            </select>
                            @error('product_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="service_id" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                            <select class="ti-form-select rounded-sm form-control-sm" name="service_id" id="service_id">
                                <option value="">Select Service</option>
                                @foreach($services as $service)
                                <option value="{{ $service->id }}" {{ old('service_id') == $service->id ? 'selected' : '' }}>
                                    {{ $service->service }}
                                </option>
                                @endforeach
                            </select>
                            @error('service_id')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="contact_person_name" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Person Name *</label>
                            <input type="text" name="contact_person_name" class="ti-form-input rounded-sm form-control-sm" id="contact_person_name" value="{{ old('contact_person_name') }}" required>
                            @error('contact_person_name')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Contact Number</label>
                            <input type="hidden" name="country_code" id="country_code"
                                value="{{ old('country_code') }}">
                            <input type="tel" name="contact_number" id="contact_number"
                                class="form-control w-full intl-phone-input"
                                value="{{ old('contact_number') }}" required>
                            @error('country_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            @error('contact_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">Country</label>
                            <select name="country_id" id="countrySelect"
                                class="ti-form-select rounded-sm form-control-sm" required>
                                <option value="">Select Country</option>
                                @foreach($countries as $country)
                                <option value="{{ $country->id }}"
                                    {{ old('country_id') == $country->id ? 'selected' : '' }}
                                    data-phonecode="{{ $country->isd_code }}">
                                    {{ $country->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('country_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">State</label>
                            <select name="state_id" id="stateSelect"
                                class="ti-form-select rounded-sm form-control-sm" required>
                                <option value="">Select State</option>
                                @if(old('state_id') && old('country_id'))
                                @foreach($states as $state)
                                <option value="{{ $state->id }}"
                                    {{ old('state_id') == $state->id ? 'selected' : '' }}>
                                    {{ $state->name }}
                                </option>
                                @endforeach
                                @endif
                            </select>
                            @error('state_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label mb-0">City</label>
                            <select name="city_id" id="citySelect"
                                class="ti-form-select rounded-sm form-control-sm" required>
                                <option value="">Select City</option>
                                @if(old('city_id') && old('state_id'))
                                @foreach($cities as $city)
                                <option value="{{ $city->id }}"
                                    {{ old('city_id') == $city->id ? 'selected' : '' }}>
                                    {{ $city->name }}
                                </option>
                                @endforeach
                                @endif
                            </select>
                            @error('city_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="map_link" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map Link</label>
                            <input type="url" name="map_link" class="ti-form-input rounded-sm form-control-sm" id="map_link" value="{{ old('map_link') }}">
                            @error('map_link')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="address" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address *</label>
                            <textarea name="address" class="ti-form-input rounded-sm form-control-sm" id="address" rows="3" required>{{ old('address') }}</textarea>
                            @error('address')
                            <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $('#product_id').on('change', function() {
        const productId = $(this).val();

        if (productId) {
            // Clear and disable the service dropdown
            const serviceDropdown = $('#service_id');
            serviceDropdown.html('<option value="">Loading...</option>').prop('disabled', true);

            $.ajax({
                url: `/admin/service-addresses/get-services-by-product/${productId}`,
                type: 'GET',
                success: function(response) {
                    serviceDropdown.empty().append('<option value="">Select Service</option>');

                    if (Array.isArray(response) && response.length) {
                        response.forEach(service => {
                            serviceDropdown.append(`<option value="${service.id}">${service.service}</option>`);
                        });
                    } else {
                        serviceDropdown.append('<option value="">No services available</option>');
                    }

                    serviceDropdown.prop('disabled', false);
                },
                error: function(xhr) {
                    console.error('Error fetching services:', xhr.responseText);
                    serviceDropdown.html('<option value="">Error loading services</option>');
                    serviceDropdown.prop('disabled', false);
                }
            });
        } else {
            $('#service_id').html('<option value="">Select Service</option>').prop('disabled', false);
        }
    });
    $(document).ready(function() {
        let iti; // Store the intl-tel-input instance

        const phoneInput = document.querySelector("#contact_number");
        if (phoneInput) {
            iti = window.intlTelInput(phoneInput, {
                initialCountry: "in",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            // On form submit, update contact number with formatted value
            phoneInput.form.addEventListener("submit", function(e) {
                const countryData = iti.getSelectedCountryData();
                document.querySelector("#country_code").value = '+' + countryData.dialCode;
            });
        }

        // Initialize select2
        $('#countrySelect, #stateSelect, #citySelect').select2({
            width: '100%'
        });

        $('#countrySelect').on('change', function() {
            const phoneCode = $(this).find('option:selected').data('phonecode');
            if (phoneCode) {
                $('#country_code').val(phoneCode);
            }

            let countryId = $(this).val();
            let $stateDropdown = $('#stateSelect').empty().append('<option value="">Select State</option>');
            $('#citySelect').empty().append('<option value="">Select City</option>');

            if (countryId) {
                $.get(`/admin/vendors/states/${countryId}`, function(res) {
                    if (res.states?.length) {
                        res.states.forEach(state => $stateDropdown.append(`<option value="${state.id}">${state.name}</option>`));
                    } else {
                        $stateDropdown.append('<option value="">No states available</option>');
                    }
                });
            }
        });

        $('#stateSelect').on('change', function() {
            let stateId = $(this).val();
            let $cityDropdown = $('#citySelect').empty().append('<option value="">Select City</option>');

            if (stateId) {
                $.get(`/admin/vendors/cities/${stateId}`, function(res) {
                    if (res?.length) {
                        res.forEach(city => $cityDropdown.append(`<option value="${city.id}">${city.name}</option>`));
                    } else {
                        $cityDropdown.append('<option value="">No cities available</option>');
                    }
                });
            }
        });

        @if(old('country_id'))
        $('#countrySelect').val('{{ old("country_id") }}').trigger('change');
        @endif
    });
</script>
@endsection