@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Edit Lead</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="{{ route('admin.clients.index') }}">
                    Lead
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Edit Lead
            </li>
        </ol>
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

    <!-- Start::row-1 -->
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <form class="ti-custom-validation" method="POST" action="{{ route('admin.leads.update', $latestLead) }}"
                novalidate>
                @csrf
                @method('PUT')
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Basic Information</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name<span class="text-danger">*</span></label>
                                <input type="text" name="name"
                                    class="firstName ti-form-input rounded-sm form-control-sm" placeholder="Full Name"
                                    value="{{ old('name', $client->name) }}" required>
                                @error('name')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                <input type="email" name="email"
                                    class="email-address ti-form-input rounded-sm form-control-sm"
                                    placeholder="your@site.com" value="{{ old('email', $client->email) }}" required>
                                @error('email')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number<span class="text-danger">*</span></label>
                                <input type="tel" id="contact_number" name="contact_number"
                                    class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                    value="{{ old('contact_number', $client->contact_number) }}" required>
                                <input type="hidden" name="contact_country_code" id="contact_country_code"
                                    value="{{ old('contact_country_code', $client->contact_country_code) }}">
                                @error('contact_number')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">WhatsApp Number</label>
                                <input type="tel" id="alternate_number" name="alternate_number"
                                    class="ti-form-input intl-phone-input iti rounded-sm form-control-sm"
                                    value="{{ old('alternate_number', $client->alternate_number) }}" required>
                                <input type="hidden" name="whatsapp_country_code" id="whatsapp_country_code"
                                    value="{{ old('whatsapp_country_code', $client->whatsapp_country_code) }}">
                                @error('alternate_number')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>

                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="ti-form-input rounded-sm form-control-sm"
                                    aria-label="dateofbirth" value="{{ old('date_of_birth', $client->date_of_birth) }}">
                                @error('date_of_birth')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label for="inputAddress"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                <textarea name="address" class="ti-form-input form-control form-control-sm" rows="1">{{ old('address', $client->address) }}</textarea>
                                @error('address') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div class="space-y-2">
                                <label for="inputCountry"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                <select name="country_id" id="countryCodeSelect"
                                    class="ti-form-select rounded-sm form-control-sm" required>
                                    <option value="">Select Country</option>
                                    @php

                                        $selectedCountryId = old('country_id') ?? $client->country_id;
                                    @endphp
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}"
                                            {{ (string) $selectedCountryId === (string) $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('country_id')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label for="inputCity" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                <select name="city" id="citySelect" class="ti-form-select rounded-sm form-control-sm"
                                    required>
                                    <option value="">Select City</option>
                                    @if ($client->country_id)
                                        @foreach ($cities as $city)
                                            <option value="{{ $city->id }}"
                                                {{ old('city', $client->city) == $city->id ? 'selected' : '' }}>
                                                {{ $city->name }}
                                            </option>
                                        @endforeach
                                    @endif
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
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Number of Passengers<span class="text-danger">*</span></label>
                                <div class="form-control-sm bg-white border border-gray-200 rounded-sm dark:bg-bodybg dark:border-white/10"
                                    data-hs-input-number>
                                    <div class="w-full flex justify-between items-center gap-x-3">
                                        <input
                                            class="w-full p-0 bg-transparent border-0 text-gray-800 focus:ring-0 dark:text-white"
                                            type="text" name="number_of_passengers"
                                            value="{{ old('number_of_passengers', $latestLead->number_of_passengers ?? 1) }}"
                                            min="1" required data-hs-input-number-input>
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
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Occasion</label>
                                <input type="text" name="occasion"
                                    class="occasion ti-form-input rounded-sm form-control-sm" placeholder="Enter occasion"
                                    value="{{ old('occasion', $latestLead->occasion ?? '') }}" required>
                                @error('occasion')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div id="repeatableFieldsContainer">
                            @php
                                // $trips is prepared in the controller. Fall back to a single empty row as a last resort.
                                $trips =
                                    $trips ??
                                    old('trips', [
                                        ['from_date' => '', 'to_date' => '', 'from_place' => '', 'to_place' => ''],
                                    ]);
                            @endphp
                            @foreach ($trips as $index => $trip)
                                <div class="repeatable-row grid grid-cols-12 gap-x-4 gap-y-2 items-center mt-5">
                                    <div class="sm:col-span-3 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date<span class="text-danger">*</span></label>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                        class="ri-calendar-line"></i> </div>
                                                <input type="text" class="datetime form-control form-control-sm"
                                                    name="trips[{{ $index }}][from_date]"
                                                    value="{{ $trip['from_date'] ?? '' }}"
                                                    placeholder="Choose date with time" required>
                                                @if (isset($trip['id']))
                                                    <input type="hidden" name="trips[{{ $index }}][id]"
                                                        value="{{ $trip['id'] }}">
                                                @else
                                                    <input type="hidden" name="trips[{{ $index }}][id]"
                                                        value="">
                                                @endif
                                            </div>
                                        </div>
                                        @error('trips.' . $index . '.from_date')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sm:col-span-2 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Place<span class="text-danger">*</span></label>
                                        <input type="text" name="trips[{{ $index }}][from_place]"
                                            class="fromPlace ti-form-input rounded-sm form-control-sm"
                                            placeholder="Enter departure location"
                                            value="{{ $trip['from_place'] ?? '' }}" required>
                                        @error('trips.' . $index . '.from_place')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sm:col-span-3 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date<span class="text-danger">*</span></label>
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                        class="ri-calendar-line"></i> </div>
                                                <input type="text" name="trips[{{ $index }}][to_date]"
                                                    class="datetime form-control form-control-sm"
                                                    placeholder="Choose date with time"
                                                    value="{{ $trip['to_date'] ?? '' }}" required>
                                            </div>
                                        </div>
                                        @error('trips.' . $index . '.to_date')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sm:col-span-2 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Place<span class="text-danger">*</span></label>
                                        <input type="text" name="trips[{{ $index }}][to_place]"
                                            class="fromPlace ti-form-input rounded-sm form-control-sm"
                                            placeholder="Enter arrival location" value="{{ $trip['to_place'] ?? '' }}"
                                            required>
                                        @error('trips.' . $index . '.to_place')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                    </div>
                                    <div class="sm:col-span-2 col-span-12" style="align-self: end;">
                                        @if ($index === 0)
                                            <button type="button"
                                                class="addBtn bg-green-500 text-white px-4 py-2 rounded">+</button>
                                        @else
                                            <button type="button"
                                                class="removeBtn bg-red-500 text-white px-4 py-2 rounded">−</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Service Selection</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid lg:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product<span class="text-danger">*</span></label>
                                <select class="js-example-basic-multiple w-full form-control-sm" name="product_ids[]"
                                    id="product_ids" data-placeholder="Select Products" multiple="multiple" required>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            {{ in_array($product->id, (array) old('product_ids', json_decode($latestLead->product_ids ?? '[]', true) ?? [])) ? 'selected' : '' }}>
                                            {{ $product->product }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('product_ids')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                                <select name="service_ids[]" class="js-example-basic-multiple w-full service_ids" multiple="multiple"
                                    data-placeholder="Select Services" required>
                                    @foreach ($services as $service)
                                        <option value="{{ $service->id }}"
                                            {{ in_array($service->id, (array) old('service_ids', json_decode($latestLead->service_ids ?? '[]', true) ?? [])) ? 'selected' : '' }}>
                                            {{ $service->service }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_ids')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="space-y-2">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Call Notes <span class="text-warning">(Add a Lead Followup From Here)</span> <a aria-label="anchor" href="{{ route('admin.leads.follow-up.create', $latestLead->id) }}"
                            
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full" target="_blank"
                            data-bs-toggle="tooltip" data-bs-placement="top" title="Add Lead Followup"><i
                                class="ri-add-line"></i></a></label>
                                
                                <textarea style="background-color: rgb(232, 232, 232)" name="requirement_description" class="ti-form-input form-control form-control-sm" rows="3" maxlength="1000" pattern="(?=.*[A-Za-z])[A-Za-z0-9\s]+" title="Call Notes must contain at least one letter and may only include letters, numbers and spaces" readonly>{{ old('requirement_description', $latestLead->description ?? '') }}</textarea>
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
                        <span class="text-warning">(Add a Lead Followup From Here)</span>
                        <a aria-label="anchor" href="{{ route('admin.leads.follow-up.create', $latestLead->id) }}"
                            
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full" target="_blank"
                            data-bs-toggle="tooltip" data-bs-placement="top" title="Add Lead Followup"><i
                                class="ri-add-line"></i></a>

                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Next Follow-up</label>
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-text text-[#8C9097] dark:text-white/50"> <i
                                                class="ri-calendar-line"></i> </div>
                                        <input type="text" name="next_followup_date"
                                            class="form-control form-control-sm" id="datetime"
                                            value="{{ old('next_followup_date', $followups->next_followup_date ?? '') }}"
                                            placeholder="Schedule follow-up">
                                    </div>
                                </div>
                                @error('next_followup_date')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Staff
                                    Representative<span class="text-danger">*</span></label>
                                <select name="representative_user_id" class="js-example-basic-single w-full form-control-sm"
                                    required >
                                    <option value="">Select Representative</option>
                                    @foreach ($staff as $user)
                                        <option value="{{ $user->id }}"
                                            {{ old('representative_user_id', $latestLead->representative_user_id ?? '') == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}</option>
                                    @endforeach
                                </select>
                                {{-- Hidden input removed: rely on the select field to submit representative_user_id --}}
                                @error('representative_user_id')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                @php
    $currentStatus = $followups->status ?? null;
    $statusLabels = [
        0 => 'Initiated',
        1 => 'Active',
        2 => 'Cancelled',
        3 => 'Full Payment Received',
        4 => 'Partial Payment Received',
        5 => 'Confirmed',
        6 => 'Pending',
        7 => 'Rescheduled',
        8 => 'Approved',
        9 => 'Rejected',
    ];
    $statusLabel = $statusLabels[$currentStatus] ?? 'N/A';
@endphp
<input type="text" 
    class="ti-form-input rounded-sm form-control-sm cursor-not-allowed" 
    value="{{ $statusLabel }}" 
    readonly 
    style="background-color: #f3f4f6; color: #6b7280;">
<small class="text-muted">Status cannot be changed here. Use the Follow-up page to update status.</small>
                                @error('status')
                                    <span class="text-red-500 text-xs">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>
                        <div class="mt-5">
                            <button type="submit"
                                class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Update</button>
                        </div>
                    </div>
                </div>

                    <script>
                        // Mirror add-lead behavior: make Travel Details optional when product/service indicates no travel
                        function setTravelFieldsRequiredEdit(isRequired) {
                            const passengers = document.querySelector('input[name="number_of_passengers"]');
                            if (passengers) passengers.required = !!isRequired;
                            const occasion = document.querySelector('input[name="occasion"]');
                            if (occasion) occasion.required = !!isRequired;
                            document.querySelectorAll('#repeatableFieldsContainer [name]').forEach(input => {
                                if (/trips\[\d+\]\[/.test(input.name)) {
                                    if (isRequired) input.setAttribute('required', 'required'); else input.removeAttribute('required');
                                }
                            });
                        }

                        function toggleTravelRequiredEdit() {
                            const productSelect = document.getElementById('product_ids');
                            const serviceSelect = document.querySelector('select.service_ids');
                            if (!productSelect && !serviceSelect) return;
                            const optionIndicatesNoTravel = (text) => {
                                if (!text) return false;
                                const t = text.toLowerCase();
                                return t.includes('call not connected') || t.includes('no requirement');
                            };
                            const prodSelected = Array.from(productSelect ? productSelect.selectedOptions : []);
                            const prodFlag = prodSelected.some(opt => optionIndicatesNoTravel(opt.text));
                            const servSelected = Array.from(serviceSelect ? serviceSelect.selectedOptions : []);
                            const servFlag = servSelected.some(opt => optionIndicatesNoTravel(opt.text));
                            const isNoTravel = prodFlag || servFlag;
                            setTravelFieldsRequiredEdit(!isNoTravel);
                        }

                        document.addEventListener('DOMContentLoaded', function () {
                            const prod = document.getElementById('product_ids');
                            const serv = document.querySelector('select.service_ids');
                            if (prod) prod.addEventListener('change', toggleTravelRequiredEdit);
                            if (serv) serv.addEventListener('change', toggleTravelRequiredEdit);
                            toggleTravelRequiredEdit();
                        });
                    </script>
            </form>
        </div>
    </div>

    <script>
        function initDatePickers(container) {
            container.querySelectorAll(".datetime").forEach(input => {
                // Prevent re-initialization on already initialized flatpickr instances
                if (!input._flatpickr) {
                    flatpickr(input, {
                        enableTime: true,
                        dateFormat: "Y-m-d H:i"
                    });
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('repeatableFieldsContainer');
            initDatePickers(container);

            container.addEventListener('click', function(e) {
                if (e.target.classList.contains('addBtn')) {
                    // Get all existing rows to calculate new index
                    const rows = container.querySelectorAll('.repeatable-row');
                    const index = rows.length;

                    const row = e.target.closest('.repeatable-row');
                    const clone = row.cloneNode(true);
                    clone.querySelectorAll('input').forEach(input => {
                        if (input.name.includes('trips') && !input.name.includes('id')) {
                            input.value = '';
                        }
                    });
                    // Add a hidden field with empty ID to indicate new segment
                    const idInput = clone.querySelector('input[name^="trips"][name$="[id]"]');
                    if (!idInput) {
                        const newIdInput = document.createElement('input');
                        newIdInput.type = 'hidden';
                        newIdInput.name = `trips[${index}][id]`;
                        newIdInput.value = '';
                        clone.querySelector('.form-group').appendChild(newIdInput);
                    } else {
                        idInput.value = '';
                    }
                    // Update all field names with new index
                    clone.querySelectorAll('[name]').forEach(input => {
                        const name = input.name;
                        input.name = name.replace(/\[(\d+)\]/, `[${index}]`);
                    });
                    const btn = clone.querySelector('.addBtn');
                    btn.textContent = '−';
                    btn.classList.remove('addBtn');
                    btn.classList.add('removeBtn');
                    btn.style.backgroundColor = 'red';

                    container.appendChild(clone);

                    initDatePickers(clone);
                } else if (e.target.classList.contains('removeBtn')) {
                    const row = e.target.closest('.repeatable-row');
                    row.remove();

                    // Re-index remaining fields after removal
                    reindexTripFields();
                }
            });
        });

        function reindexTripFields() {
            const container = document.getElementById('repeatableFieldsContainer');
            const rows = container.querySelectorAll('.repeatable-row');

            rows.forEach((row, index) => {
                row.querySelectorAll('[name]').forEach(input => {
                    const name = input.name;
                    input.name = name.replace(/\[(\d+)\]/, `[${index}]`);
                });
            });
        }
        document.addEventListener('DOMContentLoaded', function() {
            $('#countryCodeSelect').select2({
                placeholder: "Select Country",
                allowClear: true,
                width: '100%'
            });

            // Initialize Select2 for city dropdown
            $('#citySelect').select2({
                placeholder: "Select City",
                allowClear: true,
                width: '100%'
            });

            // Country change handler
            // Pass selected city from server to JS to avoid Blade quoting inside JS
            let selectedCity = "{{ old('city', $client->city) }}";

            $('#countryCodeSelect').on('change', function() {
                const countryId = $(this).val();
                console.log('Country selected:', countryId);

                // Clear and disable city dropdown
                const citySelect = $('#citySelect');
                citySelect.empty().append('<option value="">Select City</option>');

                if (!countryId) {
                    return;
                }

                // Load cities for selected country
                $.ajax({
                    url: '/get-cities/' + countryId,
                    type: 'GET',
                    dataType: 'json',
                    beforeSend: function() {
                        citySelect.prop('disabled', true);
                    },
                    success: function(response) {
                        citySelect.empty().append('<option value="">Select City</option>');

                        if (response?.length) {
                            response.forEach(city => {
                                citySelect.append(
                                    $('<option></option>')
                                    .val(city.id)
                                    .text(city.name)
                                    .prop('selected', city.id == selectedCity)
                                );
                            });
                            if (window.selectedCity) {
                                citySelect.val(window.selectedCity).trigger('change');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Error loading cities:', xhr.responseText);
                        citySelect.html('<option value="">Error loading cities</option>');
                    },
                    complete: function() {
                        citySelect.prop('disabled', false);
                    }
                });
            });

            // Trigger change if country is preselected
            @if ($client->country_id)
                $('#countryCodeSelect').trigger('change');
            @endif

            // Initialize datetime pickers
            flatpickr(".datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i"
            });
            flatpickr("#datetime", {
                enableTime: true,
                dateFormat: "Y-m-d H:i"
            });

            // Initialize service multi-select
            $('.js-example-basic-multiple').select2({
                placeholder: "Select Services",
                allowClear: true
            });
            const phoneInput = document.getElementById("contact_number");
            const whatsappInput = document.getElementById("alternate_number");
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

            phoneInput.addEventListener('blur', () => {
                if (phoneInput.value && !whatsappInput.value) {
                    whatsappInput.value = phoneInput.value;
                    itiWhatsapp.setNumber(phoneInput.value);
                }
            });

            const form = document.querySelector('form.ti-custom-validation');
            form.addEventListener('submit', function() {
                contactCodeInput.value = `+${itiPhone.getSelectedCountryData().dialCode}`;
                whatsappCodeInput.value = `+${itiWhatsapp.getSelectedCountryData().dialCode}`;
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function updatePassengerButtonsEdit() {
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
            const field = document.querySelector('input[name="number_of_passengers"]');
            if (field) {
                field.addEventListener('input', updatePassengerButtonsEdit);
                field.addEventListener('change', updatePassengerButtonsEdit);

                // Also listen for clicks on the increment/decrement controls which may change the value
                // programmatically without emitting input/change events.
                const decrementBtn = document.querySelector('[data-hs-input-number-decrement]');
                const incrementBtn = document.querySelector('[data-hs-input-number-increment]');
                if (decrementBtn) {
                    decrementBtn.addEventListener('click', function () {
                        setTimeout(updatePassengerButtonsEdit, 0);
                    });
                }
                if (incrementBtn) {
                    incrementBtn.addEventListener('click', function () {
                        setTimeout(updatePassengerButtonsEdit, 0);
                    });
                }

                updatePassengerButtonsEdit();
            }

            $('#product_ids').on('change', function() {
                const productIds = $(this).val() || [];
                $('.service_ids').val(null).trigger('change');

                if (productIds.length) {
                    $.ajax({
                        url: `/fetch-services/${productIds}`,
                        type: 'GET',
                        success: function(response) {
                            let $serviceSelect = $('.service_ids'); // correct selector
                            $serviceSelect.empty().append(
                                '<option value="">Select Service</option>');

                            response.forEach(service => {
                                $serviceSelect.append(
                                    `<option value="${service.id}">${service.service}</option>`
                                );
                            });

                            // Refresh select2 (if you are using it)
                            $serviceSelect.trigger('change');
                        },
                        error: function(xhr) {
                            console.error('Error fetching services:', xhr.responseText);
                            $('.service_ids').empty().append(
                                '<option value="">Error loading services</option>');
                        }
                    });
                }

            });
        });
    </script>
@stop
