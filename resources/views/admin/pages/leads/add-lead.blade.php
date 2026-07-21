@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
                <div class="block justify-between page-header md:flex">
                    <div>
                        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold"> Add Lead</h3>
                    </div>
                    <ol class="flex items-center whitespace-nowrap min-w-0">
                        <li class="text-[0.813rem] ps-[0.5rem]">
                          <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="javascript:void(0);">
                            Lead
                            <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                          </a>
                        </li>
                        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 " aria-current="page">
                            Add Lead
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
                <!-- Start::row-1 -->
                 <div class="box">
                        <div class="box-header">
                            <h5 class="box-title">Client Selection</h5>
                        </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xxl:col-span-4 xl:col-span-4  col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Select Existing Client or Add New</label>
                                <select id="clientSelect" class="ti-form-select rounded-sm form-control-sm w-1/3" name="client_id">
                                    <option value="new">+ Add New Client</option>
                                    @foreach($existingClients as $client)
                                        @php
                                            // Extract country code and phone number from contact_number
                                            $contactParts = explode('-', $client->contact_number ?? '');
                                            $contactCountryCode = $contactParts[0] ?? '';
                                            $contactPhoneNumber = $contactParts[1] ?? $client->contact_number ?? '';

                                            // Extract country code and phone number from alternate_number
                                            $altParts = explode('-', $client->alternate_number ?? '');
                                            $altCountryCode = $altParts[0] ?? '';
                                            $altPhoneNumber = $altParts[1] ?? $client->alternate_number ?? '';
                                        @endphp
                                        <option value="{{ $client->id }}"
                                            data-email="{{ $client->email }}"
                                            data-phone="{{ $contactPhoneNumber }}"
                                            data-phone-country-code="{{ $contactCountryCode }}"
                                            data-alt-phone="{{ $altPhoneNumber }}"
                                            data-alt-phone-country-code="{{ $altCountryCode }}"
                                            data-dob="{{ $client->date_of_birth }}"
                                            data-country="{{ $client->country_id }}"
                                            data-city="{{ $client->city_id }}"
                                            data-address="{{ $client->address }}"
                                            data-search-text="{{ $client->name }} {{ $client->email }} {{ $client->contact_number }} {{ $client->alternate_number }}">
                                            {{ $client->name }} | {{ $client->contact_number }} | {{ $client->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                       <form class="ti-custom-validation" method="POST" action="{{ route('admin.clients.store') }}" novalidate>
                            @csrf
                            <div class="box">
                                <div class="box-header">
                                    <h5 class="box-title">Basic Information</h5>
                                </div>
                                <div class="box-body">
                                    <div class="grid lg:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name<span class="text-danger">*</span></label>
                                            <input type="hidden" name="client_id" id="client_id_field" value="">
                                            <input type="text" name="name" class="firstName ti-form-input  rounded-sm form-control-sm" placeholder="Full Name" value="{{ old('name') }}" >
                                            @error('name')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                            <input type="email" name="email" class="email-address ti-form-input  rounded-sm form-control-sm" placeholder="your@site.com" value="{{ old('email') }}">
                                            @error('email')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                                            <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number<span class="text-danger">*</span></label>
                                            <input id="phone" type="tel" name="contact_number"
                                            class="ti-form-input rounded-sm form-control-sm intl-phone-input"
                                                value="{{ old('contact_number') }}" required>
                                            <input type="hidden" name="contact_country_code" id="contact_country_code" value="{{ old('contact_country_code') }}">
                                            @error('contact_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp Number</label>
                                            <input id="whatsapp" type="tel" name="alternate_number"
                                                class="ti-form-input rounded-sm form-control-sm intl-phone-input"
                                                value="{{ old('alternate_number') }}" required>
                                            <input type="hidden" name="whatsapp_country_code" id="whatsapp_country_code" value="{{ old('whatsapp_country_code') }}">
                                            @error('alternate_number') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                                            <textarea name="description" class="ti-form-input rounded-sm form-control-sm" rows="1">{{ old('description') }}</textarea>
                                            @error('description')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                       <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of Birth</label>
                                            <input type="date" name="date_of_birth" class="ti-form-input  rounded-sm form-control-sm" aria-label="dateofbirth" value="{{ old('date_of_birth') }}">
                                            @error('date_of_birth')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label for="inputAddress" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                            <textarea name="address" class="ti-form-input w-full rounded-sm form-control-sm" rows="1">{{ old('address') }}</textarea>
                                            @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label for="inputCity" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                            <select name="country_id" id="countryCodeSelect" class="ti-form-select rounded-sm form-control-sm w-1/3">
                                                <option value="">Select Country</option>
                                                    @foreach($countries as $country)
                                                        <option value="{{ $country->id }}"
                                                            data-iso="{{ $country->iso2 ?? $country->iso_code ?? $country->iso ?? '' }}"
                                                            data-phonecode="{{ $country->isd_code ?? '' }}"
                                                            {{ old('country_id') == $country->id ? 'selected' : '' }}>
                                                            {{ $country->name }}
                                                        </option>
                                                    @endforeach
                                            </select>
                                        </div>
                                        <div class="space-y-2">
                                            <label for="inputCity" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                            <select name="city" id="citySelect" class="ti-form-select rounded-sm form-control-sm w-1/3" >
                                                <option value="">Select City</option>
                                                @foreach($cities as $city)
                                                    <option value="{{ $city->id }}" {{ old('city') == $city->id ? 'selected' : '' }}>
                                                        {{ $city->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('city')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-header">
                                    <h5 class="box-title">Travel Details</h5>
                                </div>
                                <div class="box-body">
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Number of Passengers</label>
                                            <div class="form-control-sm bg-white border border-gray-200 rounded-sm dark:bg-bodybg dark:border-white/10"
                                                data-hs-input-number>
                                                <div class="w-full flex justify-between items-center gap-x-3">
                                                    <input
                                                        class="w-full p-0 bg-transparent border-0 text-gray-800 focus:ring-0 dark:text-white"
                                                        type="text" name="number_of_passengers" value="{{ old('number_of_passengers', 1) }}" min="1" required data-hs-input-number-input>
                                                    <div class="flex justify-end items-center gap-x-1.5">
                                                        <button type="button"
                                                            class="size-6 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-full border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-bodybg dark:border-white/10 dark:text-white dark:hover:bg-bgdark/80 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-white/10"
                                                            data-hs-input-number-decrement>
                                                            <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg"
                                                                width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round">
                                                                <path d="M5 12h14" />
                                                            </svg>
                                                        </button>
                                                        <button type="button"
                                                            class="size-6 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-full border border-gray-200 bg-white text-gray-800 shadow-sm hover:bg-gray-50 disabled:opacity-50 disabled:pointer-events-none dark:bg-bodybg dark:border-white/10 dark:text-white dark:hover:bg-bgdark/80 dark:focus:outline-none dark:focus:ring-1 dark:focus:ring-white/10"
                                                            data-hs-input-number-increment>
                                                            <svg class="flex-shrink-0 size-3.5" xmlns="http://www.w3.org/2000/svg"
                                                                width="24" height="24" viewBox="0 0 24 24" fill="none"
                                                                stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                                                stroke-linejoin="round">
                                                                <path d="M5 12h14" />
                                                                <path d="M12 5v14" />
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            @error('number_of_passengers')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Occasion</label>
                                            <input type="text" name="occasion" class="occasion ti-form-input  rounded-sm form-control-sm" placeholder="Enter occasion" value="{{ old('occasion') }}">
                                                @error('occasion')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                        </div>
                                    </div>
                                    <div id="repeatableFieldsContainer">
                                        @php
                                            $trips = old('trips', [['from_date' => '', 'to_date' => '', 'from_place' => '', 'to_place' => '']]);
                                        @endphp
                                     @foreach($trips as $index => $trip)
                                        <div class="repeatable-row grid grid-cols-12  gap-x-4 gap-y-2 items-center mt-5">
                                            <div class="sm:col-span-3 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date<span class="text-danger">*</span></label>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i class="ri-calendar-line"></i> </div>
                                                        <input type="text"class="datetime form-control form-control-sm" name="trips[{{ $index }}][from_date]" value="{{ $trip['from_date'] }}"placeholder="Choose date with time" required>
                                                    </div>
                                                </div>
                                                 @error('trips.'.$index.'.from_date')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="sm:col-span-3 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place<span class="text-danger">*</span></label>
                                                <input type="text" name="trips[{{ $index }}][from_place]" class="fromPlace ti-form-input  rounded-sm form-control-sm" placeholder="Enter departure location" value="{{ $trip['from_place'] }}" required>
                                                @error('trips.'.$index.'.from_place')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="sm:col-span-3 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date<span class="text-danger">*</span></label>
                                                <div class="form-group">
                                                    <div class="input-group">
                                                        <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i class="ri-calendar-line"></i> </div>
                                                        <input type="text" name="trips[{{ $index }}][to_date]" class="datetime form-control form-control-sm" placeholder="Choose date with time" value="{{ $trip['to_date'] }}">
                                                    </div>
                                                </div>
                                                @error('trips.'.$index.'.to_date')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="sm:col-span-2 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place<span class="text-danger">*</span></label>
                                                <input type="text" name="trips[{{ $index }}][to_place]" class="fromPlace ti-form-input  rounded-sm form-control-sm" placeholder="Enter arrival location" value="{{ $trip['to_place'] }}" required>
                                                @error('trips.'.$index.'.to_place')
                                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <div class="sm:col-span-1 col-span-12" style="align-self: end;">
                                            <button type="button" class="addBtn bg-green-500 text-white px-4 py-2 rounded">+</button>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-header">
                                    <h5 class="box-title">Product Selection</h5>
                                </div>
                                <div class="box-body">
                                    <div class="grid lg:grid-cols-2 gap-6">
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product<span class="text-danger">*</span></label>
                                            <select class="js-example-basic-multiple w-full form-control-sm" name="product_ids[]"
                                                id="product_ids" data-placeholder="Select Product(s)" multiple="multiple" required>
                                                @foreach ($products as $product)
                                                    <option value="{{ $product->id }}"
                                                        {{ (is_array(old('product_ids')) && in_array($product->id, old('product_ids'))) ? 'selected' : '' }}>
                                                        {{ $product->product }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('product_ids')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                       <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">
                                                Services
                                            </label>
                                            <select id="serviceSelect" name="service_ids[]"
                                                class="js-example-basic-multiple w-full service_ids"
                                                multiple="multiple" data-placeholder="Select Services" required>
                                            </select>
                                            @error('service_ids')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Call Notes</label>
                                            <textarea name="requirement_description" class="ti-form-input form-control form-control-sm" rows="3" maxlength="1000" pattern="(?=.*[A-Za-z])[A-Za-z0-9\s]+" title="Call Notes must contain at least one letter and may only include letters, numbers and spaces">{{ old('requirement_description') }}</textarea>
                                            @error('requirement_description')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box">
                                <div class="box-header">
                                    <h5 class="box-title">Internal & Follow-up</h5>
                                </div>
                                <div class="box-body">
                                    <div class="grid grid-cols-12 sm:gap-6">
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Next Follow-up</label>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-text text-[#8C9097] dark:text-white/50"> <i class="ri-calendar-line"></i> </div>
                                                    <input type="text" name="next_follow_up" class="form-control form-control-sm" id="datetime" value="{{ old('next_follow_up') }}" placeholder="Schedule follow-up">
                                                </div>
                                            </div>
                                            @error('next_follow_up')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Staff Representative<span class="text-danger">*</span></label>
                                                <select id="staffDropdown" name="representative_user_id" class="ti-form-select rounded-sm form-control-sm" required>
                                                <option value="">Select Representative</option>
                                                @php
                                                    // Priority: old input -> authenticated user (if present in staff list) -> single staff fallback
                                                    $defaultSelected = old('representative_user_id');

                                                    if (!$defaultSelected && auth()->check()) {
                                                        // Only use the authenticated user if they exist in the provided staff collection
                                                        if ($staff->contains('id', auth()->id())) {
                                                            $defaultSelected = auth()->id();
                                                        }
                                                    }

                                                    if (!$defaultSelected && $staff->count() === 1) {
                                                        $defaultSelected = $staff->first()->id;
                                                    }
                                                @endphp
                                                @foreach($staff as $user)
                                                    <option value="{{ $user->id }}" {{ $defaultSelected == $user->id ? 'selected' : '' }}>
                                                        {{ $user->name }}
                                                    </option>
                                                @endforeach

                                                {{-- If the authenticated user is not in the $staff collection, add them as an option so they can be selected by default --}}
                                                @if(auth()->check() && !$staff->contains('id', auth()->id()))
                                                    <option value="{{ auth()->id() }}" {{ $defaultSelected == auth()->id() ? 'selected' : '' }}>
                                                        {{ auth()->user()->name }}
                                                    </option>
                                                @endif
                                            </select>
                                            @error('representative_user_id')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                            <select name="status" class="ti-form-select rounded-sm form-control-sm" required>
                                                <option value="0" selected>Initiated</option>
                                                <option value="2" {{ old('status') == '2' ? 'selected' : '' }}>Cancelled</option>
                                            </select>
                                            @error('status')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="mt-5">
                                        <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
@stop
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('repeatableFieldsContainer');
    
    // Handle status change to show/hide next follow-up requirement
    const statusSelect = document.querySelector('select[name="status"]');
    const nextFollowupGroup = document.querySelector('input[name="next_follow_up"]').closest('.xl\\:col-span-4');
    const nextFollowupLabel = nextFollowupGroup.querySelector('label');
    
    function updateFollowupRequirement() {
        const selectedStatus = statusSelect.value;
        if (selectedStatus === '2') { // Cancelled
            // Make follow-up optional for cancelled leads
            nextFollowupLabel.innerHTML = 'Next Follow-up <span class="text-gray-500">(Optional for cancelled leads)</span>';
            nextFollowupGroup.style.opacity = '0.7';
        } else {
            // Make follow-up required for initiated leads
            nextFollowupLabel.innerHTML = 'Next Follow-up';
            nextFollowupGroup.style.opacity = '1';
        }
    }
    
    // Initialize on page load
    updateFollowupRequirement();
    
    // Update when status changes
    statusSelect.addEventListener('change', updateFollowupRequirement);

    // Helper function to get country ISO code from dial code using intl-tel-input data
    function getCountryISOFromDialCode(dialCode) {
        // Remove + if present
        const cleanDialCode = dialCode.replace('+', '');

        // Try to use intl-tel-input's built-in country data first
        if (window.intlTelInputGlobals && window.intlTelInputGlobals.getCountryData) {
            try {
                const countries = window.intlTelInputGlobals.getCountryData();
                const matchingCountry = countries.find(country => country.dialCode === cleanDialCode);
                if (matchingCountry) {
                    console.log('Found country using intlTelInputGlobals:', matchingCountry.iso2, 'for dial code:', cleanDialCode);
                    return matchingCountry.iso2;
                }
            } catch (e) {
                console.log('Error using intlTelInputGlobals:', e);
            }
        }

        // Fallback: Create a temporary instance to detect the country
        try {
            // Create a temporary number with the dial code to let intl-tel-input detect the country
            const tempNumber = '+' + cleanDialCode + '1234567890';
            const tempElement = document.createElement('input');
            tempElement.type = 'tel';
            tempElement.style.display = 'none';
            document.body.appendChild(tempElement);

            const tempIti = window.intlTelInput(tempElement, {
                initialCountry: "auto",
                separateDialCode: true,
                utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
            });

            tempIti.setNumber(tempNumber);
            const detectedCountry = tempIti.getSelectedCountryData();

            // Clean up
            tempIti.destroy();
            document.body.removeChild(tempElement);

            if (detectedCountry && detectedCountry.dialCode === cleanDialCode) {
                console.log('Found country using temp instance:', detectedCountry.iso2, 'for dial code:', cleanDialCode);
                return detectedCountry.iso2;
            }
        } catch (e) {
            console.log('Error using temp instance method:', e);
        }

        console.log('Could not find country for dial code:', cleanDialCode);
        return null;
    }

    function initDatePickers(scope) {
        scope.querySelectorAll(".datetime").forEach(input => {
            if (!input._flatpickr) {
                flatpickr(input, { enableTime: true, dateFormat: "Y-m-d H:i" });
            }
        });
    }

    function reindexTripFields() {
        const rows = container.querySelectorAll('.repeatable-row');
        rows.forEach((row, index) => {
            row.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/\[(\d+)\]/, `[${index}]`);
            });
        });
    }

    function handleRepeatableClick(e) {
        if (e.target.classList.contains('addBtn')) {
            const rows = container.querySelectorAll('.repeatable-row');
            const index = rows.length;

            const row = e.target.closest('.repeatable-row');
            const clone = row.cloneNode(true);

            clone.querySelectorAll('input').forEach(input => input.value = '');

            clone.querySelectorAll('[name]').forEach(input => {
                input.name = input.name.replace(/\[(\d+)\]/, `[${index}]`);
            });

            const btn = clone.querySelector('.addBtn');
            btn.textContent = '−';
            btn.classList.replace('addBtn', 'removeBtn');
            btn.style.backgroundColor = 'red';

            container.appendChild(clone);
            initDatePickers(clone);
        } else if (e.target.classList.contains('removeBtn')) {
            e.target.closest('.repeatable-row').remove();
            reindexTripFields();
        }
    }

    function initSelect2() {
        $('#countryCodeSelect, #citySelect').select2({
            placeholder: function () {
                return $(this).attr('id') === 'countryCodeSelect' ? 'Select Country' : 'Select City';
            },
            allowClear: true,
            width: '100%'
        });
    }

    function initClientSelect2() {
        $('#clientSelect').select2({
            placeholder: 'Search by name, email, or phone number...',
            allowClear: true,
            width: '100%',
            matcher: function(params, data) {
                // If there are no search terms, return all data
                if ($.trim(params.term) === '') {
                    return data;
                }

                // Skip if there is no 'text' property
                if (typeof data.text === 'undefined') {
                    return null;
                }

                // Get the search text from data attributes or use the text
                var searchText = '';
                if (data.element && data.element.dataset.searchText) {
                    searchText = data.element.dataset.searchText.toLowerCase();
                } else {
                    searchText = data.text.toLowerCase();
                }

                // Check if the search term matches
                var term = params.term.toLowerCase();
                if (searchText.indexOf(term) > -1) {
                    return data;
                }

                // Return null if the term doesn't match
                return null;
            },
            templateResult: function(data) {
                if (data.loading) {
                    return data.text;
                }

                // Handle "Add New Client" option
                if (data.id === 'new') {
                    return data.text;
                }

                // Custom template for dropdown results
                if (data.element && data.element.dataset) {
                    var clientName = data.text.split(' | ')[0] || data.text;
                    var phone = data.element.dataset.phone || '';
                    var phoneCountryCode = data.element.dataset.phoneCountryCode || '';
                    var email = data.element.dataset.email || '';

                    // Construct full phone number for display
                    var fullPhone = phone && phoneCountryCode ? phoneCountryCode + '-' + phone : phone;

                    var $container = $(
                        '<div class="client-option">' +
                        '<div class="client-name">' + clientName + '</div>' +
                        '<div class="client-details" style="font-size: 0.8em; color: #666;">' +
                        (fullPhone ? 'Phone: ' + fullPhone : '') +
                        (email ? (fullPhone ? ' | ' : '') + 'Email: ' + email : '') +
                        '</div>' +
                        '</div>'
                    );
                    return $container;
                }
                return data.text;
            },
            templateSelection: function(data) {
                return data.text;
            }
        }).on('change', function(e) {
        handleClientSelectionChange(e);
    });
    }

    function initProductServiceSelects() {
        // Initialize product and service Select2 instances so .val(...).trigger('change') updates the UI
        try {
            if (typeof $ !== 'undefined' && $.fn.select2) {
                // product (single/multi depending on markup)
                if (!$('#product_ids').hasClass('select2-hidden-accessible')) {
                    $('#product_ids').select2({ width: '100%' });
                }

                // service (multiple)
                if (!$('#serviceSelect').hasClass('select2-hidden-accessible')) {
                    $('#serviceSelect').select2({ width: '100%' });
                }
            }
        } catch (e) {
            console.error('Error initializing product/service select2:', e);
        }
    }
    function loadCities(countryId, selectedCityId = null) {
        const citySelect = $('#citySelect');
        citySelect.empty().append('<option value="">Select City</option>');

        if (!countryId) return;

        $.ajax({
            url: '/get-cities/' + countryId,
            type: 'GET',
            dataType: 'json',
            beforeSend: () => citySelect.prop('disabled', true),
            success: (response) => {
                if (Array.isArray(response)) {
                    response.forEach(city => {
                        citySelect.append(
                            $('<option></option>')
                                .val(city.id)
                                .text(city.name)
                        );
                    });

                    if (selectedCityId) {
                        citySelect.val(selectedCityId).trigger('change');
                    } else if (window.pendingCityId) {
                        citySelect.val(window.pendingCityId).trigger('change');
                        window.pendingCityId = null;
                    }
                }
            },
            error: (xhr) => {
                console.error('Error loading cities:', xhr.responseText);
                citySelect.html('<option value="">Error loading cities</option>');
            },
            complete: () => citySelect.prop('disabled', false)
        });
    }

    function handleClientSelectionChange(e) {
        const selected = e.target.options[e.target.selectedIndex];
        const value = e.target.value;
        const clientFields = ['name', 'email', 'contact_number', 'alternate_number', 'date_of_birth', 'country_id', 'city', 'address'];
        const clientIdField = document.getElementById('client_id_field') || (() => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'client_id';
            input.id = 'client_id_field';
            e.target.parentNode.appendChild(input);
            return input;
        })();

        if (value === 'new') {
            clientFields.forEach(field => {
                const el = document.querySelector(`[name="${field}"]`);
                if (el) {
                    el.disabled = false;
                    el.required = true;
                    if (!['country_id', 'city'].includes(field)) el.value = '';
                }
            });

            $('#countryCodeSelect').val('').trigger('change');
            clientIdField.value = '';
        } else if (value) {
            // Extract client name from the new format: "Name | Phone | Email"
            const clientDisplayText = selected.text.split(' | ')[0] || '';
            document.querySelector('input[name="name"]').value = clientDisplayText;
            document.querySelector('input[name="email"]').value = selected.dataset.email || '';

            // Handle phone numbers with proper country code separation
            const phoneNumber = selected.dataset.phone || '';
            const phoneCountryCode = selected.dataset.phoneCountryCode || '';
            const altPhoneNumber = selected.dataset.altPhone || '';
            const altPhoneCountryCode = selected.dataset.altPhoneCountryCode || '';

            console.log('Client Selection Debug:');
            console.log('Phone Number:', phoneNumber);
            console.log('Phone Country Code:', phoneCountryCode);
            console.log('Alt Phone Number:', altPhoneNumber);
            console.log('Alt Phone Country Code:', altPhoneCountryCode);

            // Set the phone numbers (without country codes)
            document.querySelector('input[name="contact_number"]').value = phoneNumber;
            document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;

            // Set the hidden country code fields
            if (phoneCountryCode) {
                document.getElementById('contact_country_code').value = phoneCountryCode;
            }
            if (altPhoneCountryCode) {
                document.getElementById('whatsapp_country_code').value = altPhoneCountryCode;
            }

            // Update intl-tel-input widgets with the full numbers (country code + phone number)
            if (phoneNumber && phoneCountryCode) {
                try {
                    console.log('Setting phone number:', phoneNumber, 'with country code:', phoneCountryCode);

                    // Use our helper function to get the ISO code
                    const phoneIso = getCountryISOFromDialCode(phoneCountryCode);
                    if (phoneIso) {
                        console.log('Setting phone country using helper to:', phoneIso);
                        itiPhone.setCountry(phoneIso);
                        setTimeout(() => {
                            document.querySelector('input[name="contact_number"]').value = phoneNumber;

                            // Update hidden field with the correct country code
                            const countryData = itiPhone.getSelectedCountryData();
                            if (countryData?.dialCode) {
                                document.getElementById('contact_country_code').value = `+${countryData.dialCode}`;
                                console.log('Updated phone hidden field to:', `+${countryData.dialCode}`);
                            }
                        }, 50);
                    } else {
                        console.log('No ISO found from helper, trying setNumber method');
                        // Try to set the full number and let intl-tel-input auto-detect
                        itiPhone.setNumber(phoneCountryCode + phoneNumber);

                        setTimeout(() => {
                            const countryData = itiPhone.getSelectedCountryData();
                            if (countryData?.dialCode) {
                                document.getElementById('contact_country_code').value = `+${countryData.dialCode}`;
                                console.log('Auto-detected phone country code:', `+${countryData.dialCode}`);
                            } else {
                                // Final fallback: use the original country code
                                document.getElementById('contact_country_code').value = phoneCountryCode;
                                console.log('Fallback: set phone country code to:', phoneCountryCode);
                            }
                        }, 100);
                    }
                } catch (e) {
                    console.log('Error setting phone number:', e);
                    // Fallback: just set the number part and country code
                    document.querySelector('input[name="contact_number"]').value = phoneNumber;
                    document.getElementById('contact_country_code').value = phoneCountryCode;
                }
            }

            if (altPhoneNumber && altPhoneCountryCode) {
                try {
                    console.log('Setting WhatsApp number:', altPhoneNumber, 'with country code:', altPhoneCountryCode);

                    // Method 1: Try to set country first, then number
                    const whatsappIso = getCountryISOFromDialCode(altPhoneCountryCode);
                    if (whatsappIso) {
                        console.log('Setting WhatsApp country first using helper to:', whatsappIso);
                        itiWhatsapp.setCountry(whatsappIso);
                        setTimeout(() => {
                            document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;
                        }, 50);
                    } else {
                        // Method 2: Try to set the full number
                        itiWhatsapp.setNumber(altPhoneCountryCode + altPhoneNumber);

                        // Method 3: If the flag didn't update, find the country by dial code and set it explicitly
                        setTimeout(() => {
                            const currentCountry = itiWhatsapp.getSelectedCountryData();
                            const expectedDialCode = altPhoneCountryCode.replace('+', '');

                            console.log('WhatsApp - Current country dial code:', currentCountry.dialCode, 'Expected:', expectedDialCode);

                            if (currentCountry.dialCode !== expectedDialCode) {
                                // Try using intlTelInputGlobals if available
                                if (window.intlTelInputGlobals && window.intlTelInputGlobals.getCountryData) {
                                    const countries = window.intlTelInputGlobals.getCountryData();
                                    const matchingCountry = countries.find(country => country.dialCode === expectedDialCode);

                                    if (matchingCountry) {
                                        console.log('Setting WhatsApp country to:', matchingCountry.iso2);
                                        itiWhatsapp.setCountry(matchingCountry.iso2);
                                        setTimeout(() => {
                                            document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;
                                        }, 50);
                                    }
                                } else {
                                    // Fallback 1: Use country dropdown data to find ISO code
                                    const countryOption = $('#countryCodeSelect option').filter(function() {
                                        const phonecode = $(this).data('phonecode');
                                        return phonecode === altPhoneCountryCode || phonecode === expectedDialCode || phonecode === '+' + expectedDialCode;
                                    }).first();

                                    if (countryOption.length > 0) {
                                        const iso = countryOption.data('iso');
                                        if (iso) {
                                            console.log('Setting WhatsApp country using dropdown data to:', iso);
                                            itiWhatsapp.setCountry(iso.toLowerCase());
                                            setTimeout(() => {
                                                document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;
                                            }, 50);
                                        }
                                    } else {
                                        // Fallback 2: Use our helper function
                                        const iso = getCountryISOFromDialCode(expectedDialCode);
                                        if (iso) {
                                            console.log('Setting WhatsApp country using helper function to:', iso);
                                            itiWhatsapp.setCountry(iso);
                                            setTimeout(() => {
                                                document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;
                                            }, 50);
                                        }
                                    }
                                }
                            }
                        }, 100);
                    }
                } catch (e) {
                    console.log('Error setting WhatsApp number:', e);
                    // Fallback: just set the number part
                    document.querySelector('input[name="alternate_number"]').value = altPhoneNumber;
                }
            }

            document.querySelector('input[name="date_of_birth"]').value = selected.dataset.dob || '';
            document.querySelector('textarea[name="address"]').value = selected.dataset.address || '';

            $('#countryCodeSelect').val(selected.dataset.country || '').trigger('change');
            window.pendingCityId = selected.dataset.city || '';

            // Allow editing of the selected existing client so user can update values before submitting
            clientFields.forEach(field => {
                const el = document.querySelector(`[name="${field}"]`);
                if (el) {
                    // Make field editable
                    el.disabled = false;
                    // Keep validation consistent with the "Add New" behavior for core fields
                    if (!['country_id', 'city'].includes(field)) {
                        el.required = true;
                    } else {
                        // country and city are managed via selects and may be optional depending on form flow
                        el.required = false;
                    }
                }
            });

            clientIdField.value = value;
        } else {
            clientFields.forEach(field => {
                const el = document.querySelector(`[name="${field}"]`);
                if (el) {
                    el.disabled = false;
                    el.required = true;
                    if (!['country_id', 'city'].includes(field)) el.value = '';
                }
            });

            $('#countryCodeSelect').val('').trigger('change');
            clientIdField.value = '';
        }
    }

    // Init everything
    initDatePickers(container);
    initSelect2();

    // Initialize intl-tel-input for phone numbers FIRST
    const phoneInput = document.getElementById("phone");
    const whatsappInput = document.getElementById("whatsapp");
    const contactCodeInput = document.getElementById("contact_country_code");
    const whatsappCodeInput = document.getElementById("whatsapp_country_code");

    console.log('Phone Input:', phoneInput);
    console.log('WhatsApp Input:', whatsappInput);

    if (!phoneInput || !whatsappInput) {
        console.error('Phone inputs not found!');
        return;
    }

    // Declare as global variables so they can be accessed from other functions
    window.itiPhone = window.intlTelInput(phoneInput, {
        initialCountry: "in",
        separateDialCode: true,
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
    });

    window.itiWhatsapp = window.intlTelInput(whatsappInput, {
        initialCountry: "in",
        separateDialCode: true,
        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
    });

    console.log('ITI Phone initialized:', window.itiPhone);
    console.log('ITI WhatsApp initialized:', window.itiWhatsapp);

    // Now initialize client select AFTER intl-tel-input is ready
    initClientSelect2();
    // Initialize product/service select2 so restoring old selections works correctly
    initProductServiceSelects();

    container.addEventListener('click', handleRepeatableClick);
    document.getElementById('clientSelect').addEventListener('change', handleClientSelectionChange);

    // Toggle Travel Details required when selected product is "Call not Connected"
    function setTravelFieldsRequired(isRequired) {
        // Number of passengers
        const passengers = document.querySelector('input[name="number_of_passengers"]');
        if (passengers) passengers.required = !!isRequired;

        // Occasion (if you want occasion to be mandatory as part of travel details)
        const occasion = document.querySelector('input[name="occasion"]');
        if (occasion) occasion.required = !!isRequired;

        // All repeatable trip inputs (from_date, from_place, to_date, to_place)
        document.querySelectorAll('#repeatableFieldsContainer [name]').forEach(input => {
            // Only target trip related inputs
            if (/trips\[\d+\]\[/.test(input.name)) {
                if (isRequired) {
                    input.setAttribute('required', 'required');
                } else {
                    input.removeAttribute('required');
                }
            }
        });
    }

    function toggleTravelRequiredByProduct() {
        const productSelect = document.getElementById('product_ids');
        const serviceSelect = document.querySelector('select.service_ids');
        if (!productSelect && !serviceSelect) return;

        // Helper to detect either 'call not connected' or 'no requirement' in option text
        const optionIndicatesNoTravel = (text) => {
            if (!text) return false;
            const t = text.toLowerCase();
            return t.includes('call not connected') || t.includes('no requirement');
        };

        // Check selected products
        const prodSelected = Array.from(productSelect ? productSelect.selectedOptions : []);
        const prodFlag = prodSelected.some(opt => optionIndicatesNoTravel(opt.text));

        // Check selected services as well
        const servSelected = Array.from(serviceSelect ? serviceSelect.selectedOptions : []);
        const servFlag = servSelected.some(opt => optionIndicatesNoTravel(opt.text));

        const isNoTravel = prodFlag || servFlag;
        // If product/service indicates no travel -> make travel details NOT required
        setTravelFieldsRequired(!isNoTravel);
    }

    // Wire up to products change and run on load to set initial state
    const productSelectEl = document.getElementById('product_ids');
    if (productSelectEl) {
        productSelectEl.addEventListener('change', toggleTravelRequiredByProduct);
    }
    const serviceSelectEl = document.querySelector('select.service_ids');
    if (serviceSelectEl) {
        serviceSelectEl.addEventListener('change', toggleTravelRequiredByProduct);
    }
    // initial evaluation
    toggleTravelRequiredByProduct();

    // Function to update country dropdown based on ISO code
    function updateCountryDropdown(isoCode) {
        if (!isoCode) return;

        const countrySelect = $('#countryCodeSelect');
        const option = countrySelect.find(`option[data-iso="${isoCode.toUpperCase()}"]`);

        if (option.length > 0) {
            countrySelect.val(option.val()).trigger('change');
        }
    }

    // Update country dropdown when intl-tel-input country is changed
    phoneInput.addEventListener('countrychange', function() {
        const countryData = itiPhone.getSelectedCountryData();
        updateCountryDropdown(countryData.iso2);
        // Update hidden field
        if (countryData?.dialCode) {
            contactCodeInput.value = `+${countryData.dialCode}`;
        }
    });

    whatsappInput.addEventListener('countrychange', function() {
        const countryData = itiWhatsapp.getSelectedCountryData();
        updateCountryDropdown(countryData.iso2);
        // Update hidden field
        if (countryData?.dialCode) {
            whatsappCodeInput.value = `+${countryData.dialCode}`;
        }
    });

    // Copy contact number to WhatsApp if empty
    phoneInput.addEventListener('blur', function() {
        if (phoneInput.value && !whatsappInput.value) {
            whatsappInput.value = phoneInput.value;
            itiWhatsapp.setNumber(phoneInput.value);
        }
    });

    $('#countryCodeSelect').on('change', function () {
        loadCities(this.value);

        // Get the selected country's ISO code and dial code
        const selectedOption = $(this).find(':selected');
        const selectedIso = selectedOption.data('iso');
        const phoneCode = selectedOption.data('phonecode');

        console.log('Country dropdown changed:');
        console.log('- Selected ISO:', selectedIso);
        console.log('- Phone Code:', phoneCode);
        console.log('- Selected Option:', selectedOption[0]);

        // Update intl-tel-input country if ISO is available
        if (selectedIso) {
            const isoCode = selectedIso.toLowerCase();
            console.log('Attempting to set ITI country to:', isoCode);

            try {
                // Get current country before setting
                const currentPhoneCountry = itiPhone.getSelectedCountryData();
                const currentWhatsappCountry = itiWhatsapp.getSelectedCountryData();
                console.log('Current Phone Country:', currentPhoneCountry?.iso2);
                console.log('Current WhatsApp Country:', currentWhatsappCountry?.iso2);

                // Set the country for both inputs
                itiPhone.setCountry(isoCode);
                itiWhatsapp.setCountry(isoCode);

                console.log('setCountry() called successfully');

                // Verify the change after a short delay
                setTimeout(() => {
                    const newPhoneData = itiPhone.getSelectedCountryData();
                    const newWhatsappData = itiWhatsapp.getSelectedCountryData();

                    console.log('Verification after setCountry:');
                    console.log('- New Phone Country:', newPhoneData);
                    console.log('- New WhatsApp Country:', newWhatsappData);

                    // Update hidden fields with the actual data from ITI
                    if (newPhoneData?.dialCode) {
                        contactCodeInput.value = `+${newPhoneData.dialCode}`;
                        console.log('Updated phone hidden field to:', `+${newPhoneData.dialCode}`);
                    }
                    if (newWhatsappData?.dialCode) {
                        whatsappCodeInput.value = `+${newWhatsappData.dialCode}`;
                        console.log('Updated whatsapp hidden field to:', `+${newWhatsappData.dialCode}`);
                    }

                    // Check if the visual change happened and force refresh if needed
                    const phoneContainer = document.querySelector('#phone').parentElement;
                    const whatsappContainer = document.querySelector('#whatsapp').parentElement;

                    // Try to trigger a refresh of the flag display
                    if (phoneContainer && phoneContainer.classList.contains('iti')) {
                        console.log('Phone container found, checking flag...');
                        const phoneFlag = phoneContainer.querySelector('.iti__flag');
                        const phoneSelectedCountry = phoneContainer.querySelector('.iti__selected-country');
                        console.log('Phone flag class:', phoneFlag?.className);
                        console.log('Phone selected country:', phoneSelectedCountry);
                    }

                    if (whatsappContainer && whatsappContainer.classList.contains('iti')) {
                        console.log('WhatsApp container found, checking flag...');
                        const whatsappFlag = whatsappContainer.querySelector('.iti__flag');
                        const whatsappSelectedCountry = whatsappContainer.querySelector('.iti__selected-country');
                        console.log('WhatsApp flag class:', whatsappFlag?.className);
                        console.log('WhatsApp selected country:', whatsappSelectedCountry);
                    }

                    // Try to manually trigger a refresh by dispatching events
                    try {
                        phoneInput.dispatchEvent(new Event('open:countrydropdown'));
                        phoneInput.dispatchEvent(new Event('close:countrydropdown'));
                        whatsappInput.dispatchEvent(new Event('open:countrydropdown'));
                        whatsappInput.dispatchEvent(new Event('close:countrydropdown'));
                        console.log('Dispatched refresh events');
                    } catch (e) {
                        console.log('Could not dispatch refresh events:', e);
                    }

                }, 100);

            } catch (error) {
                console.error('Error setting ITI country:', error);
                console.log('Failed ISO code:', isoCode);

                // Fallback: Just set the hidden fields with dropdown data
                if (phoneCode) {
                    contactCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                    whatsappCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                    console.log('Fallback: Set hidden codes to:', phoneCode);
                }
            }
        } else {
            // If no ISO but we have phone code, try to get ISO from intl-tel-input
            if (phoneCode) {
                console.log('No ISO in dropdown, trying to get ISO from phone code:', phoneCode);

                // Try to get ISO code from phone code using our helper
                const isoFromPhone = getCountryISOFromDialCode(phoneCode);
                if (isoFromPhone) {
                    console.log('Found ISO from phone code:', isoFromPhone);
                    try {
                        itiPhone.setCountry(isoFromPhone);
                        itiWhatsapp.setCountry(isoFromPhone);

                        setTimeout(() => {
                            const newPhoneData = itiPhone.getSelectedCountryData();
                            const newWhatsappData = itiWhatsapp.getSelectedCountryData();

                            if (newPhoneData?.dialCode) {
                                contactCodeInput.value = `+${newPhoneData.dialCode}`;
                                console.log('Updated phone hidden field from phone code to:', `+${newPhoneData.dialCode}`);
                            }
                            if (newWhatsappData?.dialCode) {
                                whatsappCodeInput.value = `+${newWhatsappData.dialCode}`;
                                console.log('Updated whatsapp hidden field from phone code to:', `+${newWhatsappData.dialCode}`);
                            }
                        }, 100);
                    } catch (e) {
                        console.log('Error setting country from phone code:', e);
                        // Final fallback: just set the phone code
                        contactCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                        whatsappCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                        console.log('Final fallback - Set hidden codes to:', phoneCode);
                    }
                } else {
                    // Just set the phone code as is
                    contactCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                    whatsappCodeInput.value = phoneCode.startsWith('+') ? phoneCode : `+${phoneCode}`;
                    console.log('Could not find ISO, set hidden codes to:', phoneCode);
                }
            }
        }
    });

    // Form submission handler
    const form = document.querySelector('form.ti-custom-validation');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Get country codes and set hidden inputs
            const phoneCountryData = itiPhone.getSelectedCountryData();
            const whatsappCountryData = itiWhatsapp.getSelectedCountryData();

            // Set hidden country code inputs
            if (phoneCountryData?.dialCode) {
                contactCodeInput.value = `+${phoneCountryData.dialCode}`;
            }
            if (whatsappCountryData?.dialCode) {
                whatsappCodeInput.value = `+${whatsappCountryData.dialCode}`;
            }

            // Ensure that client_id hidden field is populated from the clientSelect (which is outside the form)
            try {
                const clientSelectEl = document.getElementById('clientSelect');
                const clientHidden = document.getElementById('client_id_field');
                if (clientSelectEl && clientHidden) {
                    const selVal = clientSelectEl.value;
                    // if 'new', clear hidden so backend treats as new client
                    if (selVal && selVal !== 'new') {
                        clientHidden.value = selVal;
                    } else {
                        clientHidden.value = '';
                    }
                }
            } catch (err) {
                console.warn('Could not sync clientSelect to hidden client_id before submit:', err);
            }

        });
    }

    // Disable decrement button when passengers == 1
    function updatePassengerButtons() {
        const passengerInput = document.querySelector('input[name="number_of_passengers"]');
        if (!passengerInput) return;
        const decrementBtn = document.querySelector('[data-hs-input-number-decrement]');
        const value = parseInt(passengerInput.value) || 1;
        if (decrementBtn) {
            if (value <= 1) {
                decrementBtn.setAttribute('disabled', 'disabled');
            } else {
                decrementBtn.removeAttribute('disabled');
            }
        }
    }

    // Attach listeners to keep the state updated
    const passengerInputField = document.querySelector('input[name="number_of_passengers"]');
    if (passengerInputField) {
        passengerInputField.addEventListener('input', updatePassengerButtons);
        passengerInputField.addEventListener('change', updatePassengerButtons);

    const decrementBtn = document.querySelector('[data-hs-input-number-decrement]');
    const incrementBtn = document.querySelector('[data-hs-input-number-increment]');

    if (decrementBtn) {
            decrementBtn.addEventListener('click', function () {
                // run after any programmatic change completes
                setTimeout(updatePassengerButtons, 0);
            });
        }
    if (incrementBtn) {
            incrementBtn.addEventListener('click', function () {
                setTimeout(updatePassengerButtons, 0);
            });
        }
        // initialize on load
        updatePassengerButtons();
    }

    // Initialize country codes on page load
    setTimeout(function() {
        const phoneCountryData = itiPhone.getSelectedCountryData();
        const whatsappCountryData = itiWhatsapp.getSelectedCountryData();

        console.log('Phone Country Data:', phoneCountryData);
        console.log('WhatsApp Country Data:', whatsappCountryData);

        // Debug: Log available countries in intl-tel-input (using instance method)
        try {
            // Get country data from the ITI instance instead of globals
            const phoneCountryData = itiPhone.getSelectedCountryData();
            console.log('ITI Phone instance working:', phoneCountryData);

            // Test if we can set countries programmatically
            console.log('Testing setCountry method...');

        } catch (e) {
            console.log('Could not access ITI instance:', e);
        }

        // Debug: Log countries from dropdown
        let dropdownCount = 0;
        $('#countryCodeSelect option').each(function() {
            const iso = $(this).data('iso');
            const phone = $(this).data('phonecode');
            const countryName = $(this).text();
            if (iso && phone && dropdownCount < 5) {
                console.log(`Dropdown country: ${countryName}, ISO: "${iso}", Phone: "${phone}"`);
                dropdownCount++;
            }
        });

        if (phoneCountryData?.dialCode) {
            contactCodeInput.value = `+${phoneCountryData.dialCode}`;
            console.log('Set phone country code:', `+${phoneCountryData.dialCode}`);
        }
        if (whatsappCountryData?.dialCode) {
            whatsappCodeInput.value = `+${whatsappCountryData.dialCode}`;
            console.log('Set whatsapp country code:', `+${whatsappCountryData.dialCode}`);
        }
    }, 100);

    // If country_id is already selected from old input, trigger it
    const __oldCountryId = "{{ old('country_id') }}";
    if (__oldCountryId) {
        $('#countryCodeSelect').val(__oldCountryId).trigger('change');
    }

    // When the product selection changes, fetch services for those products.
    // Preserve any currently selected service IDs unless the caller provides a specific list.
    $('#product_ids').on('change', function() {
        const productIds = $(this).val() || [];
        // Determine currently selected services so we can preserve them if still applicable
        const currentSelectedServices = $('.service_ids').val() || [];

        if (productIds.length) {
            fetchServicesForProducts(productIds, currentSelectedServices.map(String));
        } else {
            // No product selected -> clear services
            $('.service_ids').empty().append('<option value="">Select Service</option>').trigger('change');
        }
    });

    // Helper to fetch services and optionally mark some as selected (used on change and on page load to restore old input)
    function fetchServicesForProducts(productIds, selectedServiceIds = []) {
        if (!productIds || !productIds.length) return;
        const productParam = Array.isArray(productIds) ? productIds.join(',') : productIds;
        $.ajax({
            url: `/fetch-services/${productParam}`,
            type: 'GET',
            success: function(response) {
                    let $serviceSelect = $('.service_ids');

                    // If Select2 is active, destroy it first to avoid stale state
                    try {
                        if ($serviceSelect.length && typeof $ !== 'undefined' && $.fn.select2 && $serviceSelect.hasClass('select2-hidden-accessible')) {
                            $serviceSelect.select2('destroy');
                        }
                    } catch (err) {
                        console.warn('Could not destroy existing select2 on service select:', err);
                    }

                    $serviceSelect.empty();

                    // If this is NOT a multiple select, you may want a placeholder option; for multiple selects
                    // adding an empty value option can interfere with Select2, so skip it for multiple selects.
                    const isMultiple = $serviceSelect.prop('multiple');
                    if (!isMultiple) {
                        $serviceSelect.append('<option value="">Select Service</option>');
                    }

                    // Build option elements and track which returned ids match requested selectedServiceIds
                    const returnedIds = [];
                    response.forEach(service => {
                        $serviceSelect.append(`<option value="${service.id}">${service.service}</option>`);
                        returnedIds.push(String(service.id));
                    });

                    // Re-initialize Select2 on the service select so UI reflects new options
                    try {
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $serviceSelect.select2({ width: '100%' });
                        }
                    } catch (err) {
                        console.warn('Could not initialize select2 for service select:', err);
                    }

                    // Determine final selected ids to set: intersection of requested selectedServiceIds and returnedIds
                    const selectedToSet = (Array.isArray(selectedServiceIds) ? selectedServiceIds.map(String) : [])
                        .filter(id => returnedIds.includes(id));

                    // Use .val([...]).trigger('change.select2') so Select2 updates correctly
                    if (selectedToSet.length) {
                        // Try immediately, then retry a few times in case Select2 isn't fully ready/rendered
                        function setSelectedServices(attempt) {
                            try {
                                $serviceSelect.val(selectedToSet).trigger('change.select2');
                                // verify selection applied
                                const applied = ($serviceSelect.val() || []).map(String);
                                if (selectedToSet.every(id => applied.includes(id))) {
                                    return true;
                                }
                            } catch (e) {
                                // ignore and retry
                            }
                            if (attempt < 4) {
                                setTimeout(() => setSelectedServices(attempt + 1), 100);
                            } else {
                                // final attempt using native change if select2 event didn't work
                                try { $serviceSelect.val(selectedToSet).trigger('change'); } catch (e) {}
                            }
                        }
                        setSelectedServices(0);
                    } else {
                        // ensure select2 refresh
                        $serviceSelect.trigger('change.select2');
                    }
                },
            error: function(xhr) {
                console.error('Error fetching services:', xhr.responseText);
                $('.service_ids').empty().append('<option value="">Error loading services</option>');
            }
        });
    }

    // On page load, if there are old product_ids and/or old service_ids (from validation), fetch services and restore selections
    (function restoreOldServices() {
        try {
            const initialProductIds = {!! json_encode(old('product_ids', [])) !!};
            const initialServiceIds = {!! json_encode(old('service_ids', [])) !!};

            if (Array.isArray(initialProductIds) && initialProductIds.length) {
                // Set the product select value WITHOUT triggering its change handler to avoid a second fetch
                try {
                    // set raw value
                    $('#product_ids').val(initialProductIds);

                    // If Select2 is active, re-initialize so the UI reflects the value without firing 'change'
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        if ($('#product_ids').hasClass('select2-hidden-accessible')) {
                            $('#product_ids').select2('destroy');
                        }
                        $('#product_ids').select2({ width: '100%' });
                    }
                } catch (e) {
                    console.warn('Could not set product select value silently:', e);
                }

                // Now fetch services and mark previously selected services
                fetchServicesForProducts(initialProductIds, initialServiceIds.map(String));
            }
        } catch (e) {
            console.error('Error restoring old services:', e);
        }
    })();

    // Auto Fill client data on phone number input
    function tryMatchClientByPhone(inputValue) {
        const clientSelect = document.getElementById("clientSelect");
        if (!clientSelect) return;
        
        const phone = inputValue.trim();
        if (!phone) return;
        
        let matchedOption = null;
        
        for (let option of clientSelect.options) {
            if (option.value === "new") continue; // skip "Add New"
        
            const clientPhone = option.dataset.phone || "";
            const clientAltPhone = option.dataset.altPhone || "";
        
            if (clientPhone === phone || clientAltPhone === phone) {
                matchedOption = option;
                break;
            }
        }
    
        if (matchedOption) {
            // Select it in the dropdown
            clientSelect.value = matchedOption.value;
            $('#clientSelect').trigger('change'); // <- this will automatically call handleClientSelectionChange
        }
    }

    // Hook into intl-tel-input phone field
    if (phoneInput) {
        phoneInput.addEventListener("blur", () => {
            // get number without country code or with, depending on how you stored data
            const phoneVal = phoneInput.value;
            tryMatchClientByPhone(phoneVal);
        });
    }

    // Hook into WhatsApp input
    if (whatsappInput) {
        whatsappInput.addEventListener("blur", () => {
            const whatsappVal = whatsappInput.value;
            tryMatchClientByPhone(whatsappVal);
        });
    }
});
</script>

@endpush
