@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Generate Voucher - {{ $lead->client->name }}
            </h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="{{ route('admin.clients.index') }}">
                    Leads
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50"
                aria-current="page">
                Generate Voucher
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
    <!-- @if ($errors->any())
    <div class="alert alert-danger mb-4">
                                                <ul class="mb-0">
                                                    @foreach ($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
                                                </ul>
                                            </div>
    @endif -->
    <!-- @include('admin.pages.vouchers._registration_link') -->
    <form id="voucher-form" action="{{ route('admin.vouchers.store') }}" method="POST" enctype="multipart/form-data"
        novalidate>
        @csrf
        <input type="hidden" name="lead_id" value="{{ $lead->id }}">

        <div class="grid grid-cols-12 gap-6">
            <div class="col-span-12">
                <!-- Client Information -->
                <div class="md:flex block items-center my-[1.5rem] page-header-breadcrumb">
                    <!-- Primary Actions -->
                    <div class="btn-list md:mt-0 mt-2">
                        <button type="submit" name="action" value="generate"
                            class="ti-btn bg-theme ti-btn-primary-full px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1">
                            <i class="ri-article-line text-sm"></i>
                            <span>Generate</span>
                        </button>
                        <!-- <button type="submit" name="action" value="generate_and_send"
                                                                    class="ti-btn ti-btn-success-full px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1">
                                                                    <i class="ri-mail-send-line text-sm"></i>
                                                                    <span>Generate & Send</span>
                                                                </button> -->


                        <!-- Voucher Actions -->
                        @if (isset($voucher))
                            <!-- Download button (forces download) -->
                            <a href="{{ route('admin.vouchers.pdf', $voucher->id) }}" target="_blank"
                                class="ti-btn ti-btn-outline-primary px-3 py-2 text-xs font-semibold rounded-md border hover:bg-primary hover:text-white transition-all duration-200 flex items-center gap-1">
                                <i class="ri-download-line text-sm"></i>
                                <span>Download</span>
                            </a>

                            <!-- Preview button (opens inline in new tab) -->
                            <a href="{{ route('admin.vouchers.pdf', $voucher->id) }}?preview=1" target="_blank"
                                class="ti-btn ti-btn-outline-secondary px-3 py-2 text-xs font-semibold rounded-md border hover:bg-primary hover:text-white transition-all duration-200 flex items-center gap-1">
                                <i class="ri-eye-line text-sm"></i>
                                <span>Preview</span>
                            </a>

                            <button type="button"
                                class="ti-btn ti-btn-info px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1"
                                id="send-email-btn" data-voucher-id="{{ $voucher->id }}">
                                <i class="ri-mail-line text-sm"></i>
                                <span>Email</span>
                            </button>

                            <button type="button"
                                class="ti-btn bg-green-500 hover:bg-green-600 text-white px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1"
                                id="send-whatsapp-btn" data-voucher-id="{{ $voucher->id }}">
                                <i class="ri-whatsapp-line text-sm"></i>
                                <span>WhatsApp</span>
                            </button>

                            <button type="button"
                                class="ti-btn bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1"
                                id="send-registration-link-btn" data-voucher-id="{{ $voucher->id }}">
                                <i class="ri-link text-sm"></i>
                                <span>Registration Link</span>
                            </button>

                            <!-- <button type="button"
                                                                        class="ti-btn bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 text-xs font-semibold rounded-md shadow-sm hover:shadow-md transition-all duration-200 flex items-center gap-1"
                                                                        id="resend-registration-btn" data-voucher-id="{{ $voucher->id }}">
                                                                        <i class="ri-refresh-line text-sm"></i>
                                                                        <span>Resend</span>
                                                                    </button> -->
                        @endif
                    </div>
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Client Information</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                                <input type="text" name="client_name"
                                    value="{{ old('client_name', $lead->client->name) }}"
                                    class="ti-form-input rounded-sm form-control-sm @error('client_name') border-red-500 @enderror"
                                    required>
                                @error('client_name')
                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                <input type="email" name="client_email"
                                    value="{{ old('client_email', $lead->client->email) }}"
                                    class="ti-form-input rounded-sm form-control-sm @error('client_email') border-red-500 @enderror"
                                    required>
                                @error('client_email')
                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                <input type="text" name="client_phone"
                                    value="{{ old('client_phone', $lead->client->contact_number) }}"
                                    class="ti-form-input rounded-sm form-control-sm @error('client_phone') border-red-500 @enderror"
                                    required>
                                @error('client_phone')
                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
                                <input type="text" name="client_whatsapp"
                                    value="{{ old('client_whatsapp', $lead->client->whatsapp_number ?? $lead->client->alternate_number) }}"
                                    class="ti-form-input rounded-sm form-control-sm @error('client_whatsapp') border-red-500 @enderror">
                                @error('client_whatsapp')
                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                <p class="text-gray-800 dark:text-white" id="client-country">
                                    {{ $lead->client->country->name ?? 'India' }}</p>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                <p class="text-gray-800 dark:text-white" id="client-city">
                                    {{ $lead->client->city->name ?? '' }}</p>
                            </div>
                            <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                <p class="text-gray-800 dark:text-white" id="client-address">
                                    {{ $lead->client->address ?? '' }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Travel Information -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Travel Information</h5>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div id="travel-segments">
                            @foreach ($lead->rideSegments as $index => $ride)
                                <div class="box travel-segment " data-segment="{{ $index }}">
                                    <div class="box-header">
                                        <h5 class="box-title">
                                            @if ($lead->rideSegments->count() > 1)
                                                Trip {{ $index + 1 }}
                                            @else
                                                Travel Details
                                            @endif
                                        </h5>
                                    </div>
                                    <div class="box-body">
                                        <div class="grid grid-cols-12 gap-3 items-center">
                                            @php
                                                // Normalize ride dates to Carbon instances when available
                                                $fromDate = $ride->from_date
                                                    ? \Carbon\Carbon::parse($ride->from_date)
                                                    : null;
                                                $toDate = $ride->to_date ? \Carbon\Carbon::parse($ride->to_date) : null;
                                                $isSameDay =
                                                    $fromDate && $toDate
                                                        ? $fromDate->format('Y-m-d') === $toDate->format('Y-m-d')
                                                        : false;
                                            @endphp

                                            <!-- Service Date or From/To Dates -->
                                            @if ($isSameDay)
                                                <!-- Single Service Date -->
                                                <div
                                                    class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service
                                                        Date</label>
                                                    <input type="date" name="rides[{{ $index }}][service_date]"
                                                        value="{{ old('rides.' . $index . '.service_date', $fromDate ? $fromDate->format('Y-m-d') : '') }}"
                                                        class="ti-form-input rounded-sm form-control-sm @error('rides.' . $index . '.service_date') border-red-500 @enderror"
                                                        required>
                                                    @error('rides.' . $index . '.service_date')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            @else
                                                <!-- From and To Dates -->
                                                <div
                                                    class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From
                                                        Date</label>
                                                    <input type="date" name="rides[{{ $index }}][from_date]"
                                                        value="{{ old('rides.' . $index . '.from_date', $fromDate ? $fromDate->format('Y-m-d') : '') }}"
                                                        class="ti-form-input rounded-sm form-control-sm @error('rides.' . $index . '.from_date') border-red-500 @enderror"
                                                        required>
                                                    @error('rides.' . $index . '.from_date')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                                <div
                                                    class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To
                                                        Date</label>
                                                    <input type="date" name="rides[{{ $index }}][to_date]"
                                                        value="{{ old('rides.' . $index . '.to_date', $toDate ? $toDate->format('Y-m-d') : '') }}"
                                                        class="ti-form-input rounded-sm form-control-sm @error('rides.' . $index . '.to_date') border-red-500 @enderror"
                                                        required>
                                                    @error('rides.' . $index . '.to_date')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </div>
                                            @endif

                                            <!-- TBA Checkbox -->
                                            <div
                                                class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <div class="form-check mt-6">
                                                    <input class="form-check-input" type="checkbox"
                                                        name="rides[{{ $index }}][is_tba]" value="1"
                                                        {{-- {{ $ride->is_tba ? 'checked' : '' }} --}}
                                                        {{ old('rides.' . $index . '.is_tba', $ride->is_tba) ? 'checked' : '' }}
                                                        id="tba_{{ $index }}">
                                                    <label class="form-check-label" for="tba_{{ $index }}">
                                                        To Be Announced
                                                    </label>
                                                </div>
                                            </div>

                                            <!-- Ride Time From -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride Time
                                                    From</label>
                                                <input type="text" name="rides[{{ $index }}][time_from]"
                                                    {{-- value="{{ old('rides.' . $index . '.time_from', $fromDate ? $fromDate->format('H:i') : '') }}" --}}
                                                    value="{{ old('rides.' . $index . '.time_from') ?? ($fromDate ? $fromDate->format('H:i') : '') }}"
                                                    class="ti-form-input rounded-sm form-control-sm ride-time-from @error('rides.' . $index . '.time_from') border-red-500 @enderror"
                                                    data-segment="{{ $index }}" required>
                                                @error('rides.' . $index . '.time_from')
                                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <!-- Ride Time To -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride Time
                                                    To</label>
                                                <input type="text" name="rides[{{ $index }}][time_to]"
                                                    value="{{ old('rides.' . $index . '.time_to', $toDate ? $toDate->format('H:i') : '') }}"
                                                    class="ti-form-input rounded-sm form-control-sm ride-time-to @error('rides.' . $index . '.time_to') border-red-500 @enderror"
                                                    data-segment="{{ $index }}" required>
                                                @error('rides.' . $index . '.time_to')
                                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <!-- Total Time (Auto-calculated) -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Total Time
                                                    (Hours)
                                                </label>
                                                <input type="text" name="rides[{{ $index }}][total_time]"
                                                    value="{{ $ride->total_time ?? '' }}"
                                                    class="ti-form-input rounded-sm form-control-sm total-time"
                                                    data-segment="{{ $index }}" readonly>
                                            </div>

                                            <!-- Departure City -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Departure
                                                    City</label>
                                                <input type="text" name="rides[{{ $index }}][from_place]"
                                                    value="{{ old('rides.' . $index . '.from_place', $ride->from_place) }}"
                                                    class="ti-form-input rounded-sm form-control-sm @error('rides.' . $index . '.from_place') border-red-500 @enderror"
                                                    required>
                                                @error('rides.' . $index . '.from_place')
                                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <!-- Arrival City -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Arrival
                                                    City</label>
                                                <input type="text" name="rides[{{ $index }}][to_place]"
                                                    value="{{ old('rides.' . $index . '.to_place', $ride->to_place) }}"
                                                    class="ti-form-input rounded-sm form-control-sm @error('rides.' . $index . '.to_place') border-red-500 @enderror"
                                                    required>
                                                @error('rides.' . $index . '.to_place')
                                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                @enderror
                                            </div>
                                            <!-- Service Address -->

                                            <div
                                                class="xl:col-span-5 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride
                                                    Address</label>
                                                <select
                                                    class="js-example-basic-single w-full form-control-sm service-address-select"
                                                    name="rides[{{ $index }}][service_address_id]"
                                                    data-segment="{{ $index }}">
                                                    <!-- <option value="">Select Ride Address</option> -->
                                                    @foreach ($serviceAddresses as $address)
                                                        <option value="{{ $address->id }}"
                                                            data-service-id="{{ $address->service_id }}"
                                                            data-contact-person="{{ $address->contact_person_name ?? '' }}"
                                                            data-contact-number="{{ $address->contact_number ?? '' }}"
                                                            data-map-link="{{ $address->map_link ?? '' }}"
                                                            {{ old('rides.' . $index . '.service_address_id', $ride->service_address_id) == $address->id ? 'selected' : '' }}>
                                                                {{ $address->address }},
                                                                {{ $address->city->name ?? '' }},
                                                                {{ $address->city->state->name ?? '' }},
                                                                {{ $address->city->country->name ?? '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('rides.{{ $index }}.service_address_id')
                                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                                @enderror
                                            </div>

                                            <!-- Contact Person -->
                                            <div
                                                class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact
                                                    Person</label>
                                                <input type="text" name="rides[{{ $index }}][contact_person]"
                                                    value="{{ old('rides.' . $index . '.contact_person', $ride->serviceAddress->contact_person_name ?? '') }}"
                                                    class="ti-form-input rounded-sm form-control-sm contact-person-input"
                                                    data-segment="{{ $index }}" placeholder="Contact Person Name">
                                            </div>

                                            <!-- Contact Number -->
                                            <div
                                                class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact
                                                    Number</label>
                                                <input type="text" name="rides[{{ $index }}][contact_number]"
                                                    value="{{ old('rides.' . $index . '.contact_number', $ride->serviceAddress->contact_number ?? '') }}"
                                                    class="ti-form-input rounded-sm form-control-sm contact-number-input"
                                                    data-segment="{{ $index }}" placeholder="Contact Number">
                                            </div>

                                            <!-- Map Link (editable) -->
                                            <div
                                                class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map
                                                    Link</label>
                                                <input type="url" name="rides[{{ $index }}][map_link]"
                                                    value="{{ old('rides.' . $index . '.map_link', $ride->serviceAddress->map_link ?? '') }}"
                                                    class="ti-form-input rounded-sm form-control-sm map-link-input"
                                                    data-segment="{{ $index }}" placeholder="Map URL (editable)">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($showHandlerSections)
                    <!-- Additional Person Information (Handler) -->
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Additional Person Information</h5>
                        </div>
                        <div class="box-body">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Person Name</label>
                                    <input type="text" name="additional_person_name"
                                        value="{{ old('additional_person_name', isset($voucher) && $voucher->passengers->where('is_additional_person', true)->first() ? $voucher->passengers->where('is_additional_person', true)->first()->name : '') }}"
                                        class="ti-form-input rounded-sm form-control-sm @error('additional_person_name') border-red-500 @enderror">
                                    @error('additional_person_name')
                                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                    <input type="text" name="additional_person_phone"
                                        value="{{ old('additional_person_phone', isset($voucher) && $voucher->passengers->where('is_additional_person', true)->first() ? $voucher->passengers->where('is_additional_person', true)->first()->contact_number : '') }}"
                                        class="ti-form-input rounded-sm form-control-sm @error('additional_person_phone') border-red-500 @enderror">
                                    @error('additional_person_phone')
                                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Handler Information -->
                    <div class="box">
                        <div class="box-header flex justify-between items-center">
                            <h5 class="box-title">Handler Information</h5>
                        </div>
                        <div class="box-body">
                            <div class="grid grid-cols-12 gap-6">
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Person Name</label>
                                    <input type="text" name="handler_person_name"
                                        value="{{ old('handler_person_name', isset($voucher) && $voucher->passengers->where('is_handler', true)->first() ? $voucher->passengers->where('is_handler', true)->first()->name : '') }}"
                                        class="ti-form-input rounded-sm form-control-sm @error('handler_person_name') border-red-500 @enderror">
                                    @error('handler_person_name')
                                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                    @enderror

                                </div>
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                                    <input type="text" name="handler_person_phone"
                                        value="{{ old('handler_person_phone', isset($voucher) && $voucher->passengers->where('is_handler', true)->first() ? $voucher->passengers->where('is_handler', true)->first()->contact_number : '') }}"
                                        class="ti-form-input rounded-sm form-control-sm @error('handler_person_phone') border-red-500 @enderror">
                                    @error('handler_person_phone')
                                        <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Service & Vendor Information -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Service & Vendor Information</h5>
                            <a href="{{ route('admin.leads.follow-up.create', $lead->id) }}" target="_blank" class="ti-btn ti-btn-secondary !text-[0.85rem] ml-2">
                                <i class="ri-external-link-line"></i> Open Add Follow Up
                            </a>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="table-responsive">
                            <table class="table display responsive nowrap table-datatable" width="100%" data-empty-msg="No services selected in followup. Click "Add Service & Vendor Detail" to add services.">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">
                                        <th>Sr.No</th>
                                        <th>Service Name</th>
                                        <th>Service Amount</th>
                                        <th>Vendor Name</th>
                                        <th>Vendor Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="service-vendor-container">
                                    @php
                                        $oldServices = old('services');
                                    @endphp

                                    @if (!empty($oldServices) && is_array($oldServices))
                                        @foreach ($oldServices as $index => $oldService)
                                            @php
                                                $selectedServiceModel = collect($allServices)->firstWhere(
                                                    'id',
                                                    $oldService['service_id'] ?? null,
                                                );
                                                $serviceVendors = $selectedServiceModel
                                                    ? $selectedServiceModel->vendors
                                                    : collect();
                                                $serviceAmount =
                                                    $oldService['amount'] ??
                                                    ($selectedServiceModel->service_amount ?? '');
                                                $vendorAmount = $oldService['vendor_amount'] ?? $serviceAmount;
                                            @endphp
                                            <tr class="service-vendor-item border-b border-defaultborder"
                                                data-index="{{ $index }}">
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <select name="services[{{ $index }}][service_id]"
                                                        class="js-example-basic-single w-full form-control-sm service-select @error('services.' . $index . '.service_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Service</option>
                                                        @foreach ($allServices as $availableService)
                                                            <option value="{{ $availableService->id }}"
                                                                {{ (isset($oldService['service_id']) && $oldService['service_id'] == $availableService->id) || old('services.' . $index . '.service_id') == $availableService->id ? 'selected' : '' }}
                                                                data-vendors='@json($availableService->vendors->pluck('name', 'id'))'
                                                                data-amount="{{ $availableService->service_amount }}"
                                                                data-extra-services='@json($availableService->extraServices->pluck('id')->values())'>
                                                                {{ $availableService->service_name ?? $availableService->service }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('services.' . $index . '.service_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">{{ $message }}
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number" name="services[{{ $index }}][amount]"
                                                        value="{{ $serviceAmount }}"
                                                        class="ti-form-input rounded-sm form-control-sm service-amount @error('services.' . $index . '.amount') border-red-500 @enderror"
                                                        step="0.01" required>
                                                    @error('services.' . $index . '.amount')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <select name="services[{{ $index }}][vendor_id]"
                                                        data-selected-vendor="{{ old('services.' . $index . '.vendor_id') ?? (isset($service->vendor_id) ? $service->vendor_id : '') }}"
                                                        class="js-example-basic-single w-full form-control-sm vendor-select @error('services.' . $index . '.vendor_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Vendor</option>
                                                        @foreach ($serviceVendors as $vendor)
                                                            <option value="{{ $vendor->id }}"
                                                                {{ isset($oldService['vendor_id']) && $oldService['vendor_id'] == $vendor->id ? 'selected' : '' }}>
                                                                {{ $vendor->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('services.' . $index . '.vendor_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            {{ $message }}</div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="services[{{ $index }}][vendor_amount]"
                                                        value="{{ $vendorAmount }}"
                                                        class="ti-form-input rounded-sm form-control-sm vendor-amount"
                                                        step="0.01">
                                                </td>
                                                <td>
                                                    <button type="button"
                                                        class="ti-btn ti-btn-danger  ti-btn-sm remove-service"
                                                        title="Remove Service">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        @forelse($selectedServices as $index => $service)
                                            <tr class="service-vendor-item border-b border-defaultborder"
                                                data-index="{{ $index }}">
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <select name="services[{{ $index }}][service_id]"
                                                        class="js-example-basic-single w-full form-control-sm service-select @error('services.' . $index . '.service_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Service</option>
                                                        @foreach ($allServices as $availableService)
                                                            <option value="{{ $availableService->id }}"
                                                                {{ old('services.' . $index . '.service_id') == $availableService->id || $service->id == $availableService->id ? 'selected' : '' }}
                                                                data-vendors='@json($availableService->vendors->pluck('name', 'id'))'
                                                                data-amount="{{ $availableService->service_amount }}"
                                                                data-extra-services='@json($availableService->extraServices->pluck('id')->values())'>
                                                                {{ $availableService->service_name ?? $availableService->service }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('services.' . $index . '.service_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number" name="services[{{ $index }}][amount]"
                                                        value="{{ old('services.' . $index . '.amount', $service->amount ?? $service->service_amount) }}"
                                                        class="ti-form-input rounded-sm form-control-sm service-amount @error('services.' . $index . '.amount') border-red-500 @enderror"
                                                        step="0.01" required>
                                                    @error('services.' . $index . '.amount')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <select name="services[{{ $index }}][vendor_id]"
                                                        data-selected-vendor="{{ old('services.' . $index . '.vendor_id') ?? (isset($service->vendor_id) ? $service->vendor_id : '') }}"
                                                        class="js-example-basic-single w-full form-control-sm vendor-select @error('services.' . $index . '.vendor_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Vendor</option>
                                                        @foreach ($service->vendors as $vendor)
                                                            <option value="{{ $vendor->id }}"
                                                                {{ old('services.' . $index . '.vendor_id') == $vendor->id ? 'selected' : (isset($service->vendor_id) && $service->vendor_id == $vendor->id ? 'selected' : '') }}>
                                                                {{ $vendor->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('services.' . $index . '.vendor_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="services[{{ $index }}][vendor_amount]"
                                                        value="{{ old('services.' . $index . '.vendor_amount', $service->vendor_amount ?? $service->amount ?? $service->service_amount) }}"
                                                        class="ti-form-input rounded-sm form-control-sm vendor-amount"
                                                        step="0.01">
                                                </td>
                                                <td>
                                                    <button type="button"
                                                        class="ti-btn ti-btn-danger ti-btn-sm remove-service"
                                                        title="Remove Service">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Service Button -->
                        <div class="mt-3">
                            <button type="button" id="add-service-vendor" class="ti-btn ti-btn-primary !text-[0.85rem]">
                                <i class="ri-add-circle-line"></i> Add Service & Vendor Detail
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Extra Service & Vendor Information -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Extra Service & Vendor Information</h5>
                            <a href="{{ route('admin.leads.follow-up.create', $lead->id) }}" target="_blank" class="ti-btn ti-btn-secondary !text-[0.85rem] ml-2">
                                <i class="ri-external-link-line"></i> Open Add Follow Up
                            </a>
                    </div>
                    <div class="box-body bg-gray-50">
                        <div class="table-responsive">
                            <table class="table display responsive nowrap table-datatable" width="100%" data-empty-msg="No extra services selected in followup. Click "Add Extra Service & Vendor Detail" to add extra services.">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">
                                        <th>Sr.No</th>
                                        <th>Extra Service Name</th>
                                        <th>Service Amount</th>
                                        <th>Vendor Name</th>
                                        <th>Vendor Amount</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="extra-service-vendor-container">
                                    @php
                                        $oldExtraServices = old('extra_services');
                                    @endphp

                                    @if (!empty($oldExtraServices) && is_array($oldExtraServices))
                                        @foreach ($oldExtraServices as $index => $oldExtra)
                                            @php
                                                $selectedExtraModel = collect($allExtraServices)->firstWhere(
                                                    'id',
                                                    $oldExtra['extra_service_id'] ?? null,
                                                );
                                                $extraVendors = $selectedExtraModel
                                                    ? $selectedExtraModel->vendors
                                                    : collect();
                                                $serviceAmount =
                                                    $oldExtra['amount'] ??
                                                    ($selectedExtraModel->extra_service_amount ?? '');
                                                $vendorAmount = $oldExtra['vendor_amount'] ?? $serviceAmount;
                                            @endphp
                                            <tr class="extra-service-vendor-item border-b border-defaultborder"
                                                data-index="{{ $index }}">
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <select name="extra_services[{{ $index }}][extra_service_id]"
                                                        class="js-example-basic-single w-full form-control-sm extra-service-select @error('extra_services.' . $index . '.extra_service_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Extra Service</option>
                                                        @foreach ($allExtraServices as $availableExtraService)
                                                            <option value="{{ $availableExtraService->id }}"
                                                                {{ (isset($oldExtra['extra_service_id']) && $oldExtra['extra_service_id'] == $availableExtraService->id) || old('extra_services.' . $index . '.extra_service_id') == $availableExtraService->id ? 'selected' : '' }}
                                                                data-vendors='@json($availableExtraService->vendors->pluck('name', 'id'))'
                                                                data-amount="{{ $availableExtraService->extra_service_amount }}">
                                                                {{ $availableExtraService->extra_service }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('extra_services.' . $index . '.extra_service_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="extra_services[{{ $index }}][amount]"
                                                        value="{{ $serviceAmount }}"
                                                        class="ti-form-input rounded-sm form-control-sm extra-service-amount @error('extra_services.' . $index . '.amount') border-red-500 @enderror"
                                                        step="0.01" required>
                                                    @error('extra_services.' . $index . '.amount')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <select name="extra_services[{{ $index }}][vendor_id]"
                                                        data-selected-vendor="{{ old('extra_services.' . $index . '.vendor_id') ?? (isset($extraService->vendor_id) ? $extraService->vendor_id : '') }}"
                                                        class="js-example-basic-single w-full form-control-sm extra-vendor-select @error('extra_services.' . $index . '.vendor_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Vendor</option>
                                                        @foreach ($extraVendors as $vendor)
                                                            <option value="{{ $vendor->id }}"
                                                                {{ isset($oldExtra['vendor_id']) && $oldExtra['vendor_id'] == $vendor->id ? 'selected' : '' }}>
                                                                {{ $vendor->name }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('extra_services.' . $index . '.vendor_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="extra_services[{{ $index }}][vendor_amount]"
                                                        value="{{ $vendorAmount }}"
                                                        class="ti-form-input rounded-sm form-control-sm extra-vendor-amount"
                                                        step="0.01">
                                                </td>
                                                <td>
                                                    <button type="button"
                                                        class="ti-btn ti-btn-danger ti-btn-sm remove-extra-service"
                                                        title="Remove Extra Service">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        @forelse($selectedExtraServices as $index => $extraService)
                                            <tr class="extra-service-vendor-item border-b border-defaultborder"
                                                data-index="{{ $index }}">
                                                <td>{{ $index + 1 }}</td>
                                                <td>
                                                    <select name="extra_services[{{ $index }}][extra_service_id]"
                                                        class="js-example-basic-single w-full form-control-sm extra-service-select @error('extra_services.' . $index . '.extra_service_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Extra Service</option>
                                                        @foreach ($allExtraServices as $availableExtraService)
                                                            <option value="{{ $availableExtraService->id }}"
                                                                {{ old('extra_services.' . $index . '.extra_service_id') == $availableExtraService->id || $extraService->id == $availableExtraService->id ? 'selected' : '' }}
                                                                data-vendors='@json($availableExtraService->vendors->pluck('name', 'id'))'
                                                                data-amount="{{ $availableExtraService->extra_service_amount }}">
                                                                {{ $availableExtraService->extra_service }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('extra_services.{{ $index }}.extra_service_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="extra_services[{{ $index }}][amount]"
                                                        value="{{ old('extra_services.' . $index . '.amount', $extraService->amount ?? $extraService->extra_service_amount) }}"
                                                        class="ti-form-input rounded-sm form-control-sm extra-service-amount @error('extra_services.' . $index . '.amount') border-red-500 @enderror"
                                                        step="0.01" required>
                                                    @error('extra_services.' . $index . '.amount')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <select name="extra_services[{{ $index }}][vendor_id]"
                                                        data-selected-vendor="{{ old('extra_services.' . $index . '.vendor_id') ?? (isset($extraService->vendor_id) ? $extraService->vendor_id : '') }}"
                                                        class="js-example-basic-single w-full form-control-sm extra-vendor-select @error('extra_services.' . $index . '.vendor_id') border-red-500 @enderror"
                                                        required>
                                                        <option value="">Select Vendor</option>
                                                        @foreach ($extraService->vendors as $vendor)
                                                            <option value="{{ $vendor->id }}"
                                                                {{ old('extra_services.' . $index . '.vendor_id') == $vendor->id ? 'selected' : (isset($extraService->vendor_id) && $extraService->vendor_id == $vendor->id ? 'selected' : '') }}>
                                                                {{ $vendor->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    @error('extra_services.' . $index . '.vendor_id')
                                                        <div class="text-red-500 text-sm mt-1 font-medium">
                                                            <small>{{ $message }}</small>
                                                        </div>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number"
                                                        name="extra_services[{{ $index }}][vendor_amount]"
                                                        value="{{ old('extra_services.' . $index . '.vendor_amount', $extraService->vendor_amount ?? $extraService->amount ?? $extraService->extra_service_amount) }}"
                                                        class="ti-form-input rounded-sm form-control-sm extra-vendor-amount"
                                                        step="0.01">
                                                </td>
                                                <td>
                                                    <button type="button"
                                                        class="ti-btn ti-btn-danger ti-btn-sm remove-extra-service"
                                                        title="Remove Extra Service">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @empty
                                            
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Extra Service Button -->
                        <div class="mt-3">
                            <button type="button" id="add-extra-service-vendor"
                                class="ti-btn ti-btn-primary !text-[0.85rem]">
                                <i class="ri-add-circle-line"></i> Add Extra Service & Vendor Detail
                            </button>
                        </div>
                    </div>
                </div>
                <!-- Operation Team -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Operation Team</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Team Member <span
                                        class="text-red-500">*</span></label>
                                <select name="operation_team_user_id"
                                    class="js-example-basic-single w-full form-control-sm">
                                    <option value="">Select Operation Team Member</option>
                                    @foreach ($operationTeam as $member)
                                        <option value="{{ $member->id }}"
                                            data-contact="{{ $member->contact_number ?? '' }}"
                                            {{ old('operation_team_user_id') == $member->id ? 'selected' : (isset($voucher) && $voucher->operation_team_user_id == $member->id ? 'selected' : '') }}>
                                            {{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('operation_team_user_id')
                                    <span class="text-red-500 text-sm mt-1 block">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                                <input type="text" name="contact_number"
                                    value="{{ old('contact_number', isset($voucher) && $voucher->operationTeamUser ? $voucher->operationTeamUser->contact_number : '') }}"
                                    class="ti-form-input rounded-sm form-control-sm" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Personal Details -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Person Details</h5>
                    </div>
                    @if (isset($isAirAmbulance) && $isAirAmbulance)
                        <div class="box-body bg-red-50 border border-red-200 mb-3">
                            <p class="text-red-600 font-semibold text-sm mb-0">
                                <i class="ri-alert-line"></i> Note: Patient information is mandatory for Air Ambulance
                                services. The first passenger cannot be deleted.
                            </p>
                        </div>
                    @endif
                    <div class="box-body bg-gray-50">
                        <div class="table-responsive">
                            <table id="passengers-table" class="table display responsive nowrap table-datatable" width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">
                                        <th>Sr.No</th>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <!-- <th>Contact Number</th> -->
                                        <th>Weight (KG)</th>
                                        <th>Front Document</th>
                                        <th>Back Document</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $visibleIndex = 0; @endphp
                                    @if (isset($voucher) && $voucher->passengers->count() > 0)
                                        @foreach ($voucher->passengers as $passenger)
                                            @if (!$passenger->is_handler && !$passenger->is_additional_person)
                                                @php $i = $visibleIndex++; @endphp
                                                <tr class="passenger-row border-b border-defaultborder"
                                                    data-index="{{ $i }}">
                                                    <input type="hidden" name="passengers[{{ $i }}][id]" value="{{ $passenger->id }}">
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>
                                                        <input type="text"
                                                            name="passengers[{{ $i }}][name]"
                                                            value="{{ old('passengers.' . $i . '.name', $passenger->name) }}"
                                                            class="ti-form-input rounded-sm form-control-sm @error('passengers.' . $i . '.name') border-red-500 @enderror"
                                                            style="width: 200px;" 
                                                            required>
                                                        @error('passengers.' . $i . '.name')
                                                            <span
                                                                class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                        @enderror
                                                    </td>
                                                    <td>
                                                        <input type="number"
                                                            name="passengers[{{ $i }}][age]"
                                                            value="{{ old('passengers.' . $i . '.age', $passenger->age) }}"
                                                            class="ti-form-input rounded-sm form-control-sm @error('passengers.' . $i . '.age') border-red-500 @enderror"
                                                            style="width: 70px;" 
                                                            required>
                                                        @error('passengers.' . $i . '.age')
                                                            <span
                                                                class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                        @enderror
                                                    </td>
                                                    <!-- <td>
                                                                                            <input type="text" name="passengers[{{ $i }}][contact_number]"
                                                                                                value="{{ $passenger->contact_number }}"
                                                                                                class="ti-form-input rounded-sm form-control-sm">
                                                                                        </td> -->
                                                    <td>
                                                        <input type="number"
                                                            name="passengers[{{ $i }}][weight]"
                                                            value="{{ $passenger->weight }}"
                                                            class="ti-form-input rounded-sm form-control-sm"
                                                            style="width: 70px;"
                                                            step="0.1">
                                                        @error('passengers.' . $i . '.weight')
                                                            <span
                                                                class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                        @enderror
                                                    </td>
                                                    <td>
                                                        <input type="file"
                                                            name="passengers[{{ $i }}][front_document]"
                                                            class="ti-form-input rounded-sm form-control-sm"
                                                            accept=".jpg,.jpeg,.png,.pdf">
                                                        @if (!empty($passenger->front_document))
                                                            <div class="mt-2">
                                                                <a href="{{ Storage::url($passenger->front_document) }}"
                                                                    target="_blank"
                                                                    class="text-blue-600 hover:text-blue-800">
                                                                    <small class="block text-blue-600 underline">Click to
                                                                        view image</small>
                                                                </a>
                                                            </div>
                                                        @endif
                                                        <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF |
                                                            Max size: 2MB</small>
                                                        @error('passengers.' . $i . '.front_document')
                                                            <span
                                                                class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                        @enderror
                                                    </td>
                                                    <td>
                                                        <input type="file"
                                                            name="passengers[{{ $i }}][back_document]"
                                                            class="ti-form-input rounded-sm form-control-sm"
                                                            accept=".jpg,.jpeg,.png,.pdf">
                                                        @if (!empty($passenger->back_document))
                                                            <div class="mt-2">
                                                                <a href="{{ Storage::url($passenger->back_document) }}"
                                                                    target="_blank"
                                                                    class="text-blue-600 hover:text-blue-800">
                                                                    <small class="block text-blue-600 underline">Click to
                                                                        view image</small>
                                                                </a>
                                                            </div>
                                                        @endif
                                                        <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF |
                                                            Max size: 2MB</small>
                                                        @error('passengers.' . $i . '.back_document')
                                                            <span
                                                                class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                        @enderror
                                                    </td>
                                                    <td>
                                                        @if ($i == 0 && isset($isAirAmbulance) && $isAirAmbulance)
                                                            <span class="text-red-500 text-sm">Patient (Required)</span>
                                                        @else
                                                            <button type="button"
                                                                class="ti-btn ti-btn-danger ti-btn-sm remove-passenger"
                                                                title="Remove Passenger">
                                                                <i class="ri-delete-bin-line"></i>
                                                            </button>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @elseif (isset($preVoucherPassengers) && $preVoucherPassengers->count() > 0)
                                        {{-- Use pre-registered passengers from registration form --}}
                                        @foreach ($preVoucherPassengers as $passenger)
                                            @php $i = $visibleIndex++; @endphp
                                            <tr class="passenger-row border-b border-defaultborder"
                                                data-index="{{ $i }}">
                                                <input type="hidden" name="passengers[{{ $i }}][id]" value="{{ $passenger->id }}">
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <input type="text" name="passengers[{{ $i }}][name]"
                                                        value="{{ old('passengers.' . $i . '.name', $passenger->name) }}"
                                                        class="ti-form-input rounded-sm form-control-sm @error('passengers.' . $i . '.name') border-red-500 @enderror"
                                                        required>
                                                    @error('passengers.' . $i . '.name')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number" name="passengers[{{ $i }}][age]"
                                                        value="{{ old('passengers.' . $i . '.age', $passenger->age) }}"
                                                        class="ti-form-input rounded-sm form-control-sm @error('passengers.' . $i . '.age') border-red-500 @enderror"
                                                        required>
                                                    @error('passengers.' . $i . '.age')
                                                        <span
                                                            class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                    @enderror
                                                </td>
                                                <td>
                                                    <input type="number" name="passengers[{{ $i }}][weight]"
                                                        value="{{ old('passengers.' . $i . '.weight', $passenger->weight) }}"
                                                        class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                                </td>
                                                <td>
                                                    <input type="file"
                                                        name="passengers[{{ $i }}][front_document]"
                                                        class="ti-form-input rounded-sm form-control-sm"
                                                        accept=".jpg,.jpeg,.png,.pdf">
                                                    @if (!empty($passenger->front_document))
                                                        <div class="mt-2">
                                                            <a href="{{ Storage::url($passenger->front_document) }}"
                                                                target="_blank"
                                                                class="text-blue-600 hover:text-blue-800">
                                                                <small class="block text-blue-600 underline">Click to
                                                                    view image</small>
                                                            </a>
                                                        </div>
                                                    @endif
                                                    <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max
                                                        size: 2MB</small>
                                                </td>
                                                <td>
                                                    <input type="file"
                                                        name="passengers[{{ $i }}][back_document]"
                                                        class="ti-form-input rounded-sm form-control-sm"
                                                        accept=".jpg,.jpeg,.png,.pdf">
                                                    @if (!empty($passenger->back_document))
                                                        <div class="mt-2">
                                                            <a href="{{ Storage::url($passenger->back_document) }}"
                                                                target="_blank"
                                                                class="text-blue-600 hover:text-blue-800">
                                                                <small class="block text-blue-600 underline">Click to
                                                                    view image</small>
                                                            </a>
                                                        </div>
                                                    @endif
                                                    <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max
                                                        size: 2MB</small>
                                                </td>
                                                <td>
                                                    @if ($i == 0 && isset($isAirAmbulance) && $isAirAmbulance)
                                                        <span class="text-red-500 text-sm">Patient (Required)</span>
                                                    @else
                                                        <button type="button"
                                                            class="ti-btn ti-btn-danger ti-btn-sm remove-passenger"
                                                            title="Remove Passenger">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        {{-- Fallback: create one empty row only --}}
                                        <tr class="passenger-row border-b border-defaultborder"
                                            data-index="0">
                                            <td>1</td>
                                            <td>
                                                <input type="text" name="passengers[0][name]"
                                                    value="{{ old('passengers.0.name') }}"
                                                    class="ti-form-input rounded-sm form-control-sm @error('passengers.0.name') border-red-500 @enderror"
                                                    required>
                                                @error('passengers.0.name')
                                                    <span
                                                        class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                @enderror
                                            </td>
                                            <td>
                                                <input type="number" name="passengers[0][age]"
                                                    value="{{ old('passengers.0.age') }}"
                                                    class="ti-form-input rounded-sm form-control-sm @error('passengers.0.age') border-red-500 @enderror"
                                                    required>
                                                @error('passengers.0.age')
                                                    <span
                                                        class="text-red-500 text-sm mt-1 block"><small>{{ $message }}</small></span>
                                                @enderror
                                            </td>
                                            <td>
                                                <input type="number" name="passengers[0][weight]"
                                                    class="ti-form-input rounded-sm form-control-sm" step="0.1">
                                            </td>
                                            <td>
                                                <input type="file"
                                                    name="passengers[0][front_document]"
                                                    class="ti-form-input rounded-sm form-control-sm"
                                                    accept=".jpg,.jpeg,.png,.pdf">
                                                <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max
                                                    size: 2MB</small>
                                            </td>
                                            <td>
                                                <input type="file"
                                                    name="passengers[0][back_document]"
                                                    class="ti-form-input rounded-sm form-control-sm"
                                                    accept=".jpg,.jpeg,.png,.pdf">
                                                <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max
                                                    size: 2MB</small>
                                            </td>
                                            <td>
                                                @if (isset($isAirAmbulance) && $isAirAmbulance)
                                                    <span class="text-red-500 text-sm">Patient (Required)</span>
                                                @else
                                                    <button type="button"
                                                        class="ti-btn ti-btn-danger ti-btn-sm remove-passenger"
                                                        title="Remove Passenger">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>

                        <!-- Add Passenger Button -->
                        <div class="mt-3">
                            <button type="button" id="add-passenger" class="ti-btn ti-btn-primary !text-[0.85rem]">
                                <i class="ri-add-circle-line"></i> Add Passenger Detail
                            </button>
                        </div>
                    </div>
                </div>
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Extra Upload</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-6 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Upload</label>
                                <input type="file" name="extra_upload"
                                    class="ti-form-input rounded-sm form-control-sm"
                                    accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max size: 2MB</small>
                                @if (isset($voucher) && $voucher->extra_upload)
                                    <div class="flex items-center gap-3">
                                        <small class="text-green-600">Current file: {{ basename($voucher->extra_upload) }}</small>
                                        @php
                                            // Public URL to the stored file
                                            $extraUrl = asset('storage/' . ltrim($voucher->extra_upload, '/'));
                                        @endphp
                                        <a href="{{ $extraUrl }}" target="_blank" class="ti-btn ti-btn-outline ti-btn-sm whitespace-nowrap">View</a>
                                        <button type="button" onclick="confirmAndDeleteAttachment('{{ route('admin.vouchers.delete-attachment', $voucher->id) }}')" class="ti-btn ti-btn-danger ti-btn-sm"><i class="ri-delete-bin-line"></i></button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="box">
                    <div class="box-header flex justify-between items-center">
                        <h5 class="box-title">Special Instructions & Notes</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 gap-6">
                            <div class="xl:col-span-12 col-span-12">
                                <textarea name="naration" class="ti-form-input rounded-sm form-control-sm bg-gray-50" rows="3">{{ old('naration', isset($voucher) ? $voucher->naration : '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form Actions -->
                <!-- <div class="box">
                                                        <div class="box-body">
                                                            <div class="flex gap-4">
                                                                <button type="submit" name="action" value="generate" class="ti-btn bg-theme ti-btn-primary-full">
                                                                    <i class="ri-article-line"></i> Generate Voucher
                                                                </button>
                                                                <button type="submit" name="action" value="generate_and_send" class="ti-btn ti-btn-success-full">
                                                                    <i class="ri-mail-send-line"></i> Generate & Send PDF
                                                                </button>
                                                                <a href="{{ route('admin.clients.index') }}" class="ti-btn ti-btn-outline-secondary">
                                                                    <i class="ri-arrow-left-line"></i> Back to Leads
                                                                </a>
                                                            </div>
                                                        </div>
                                                    </div> -->
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (function() {
            const form = document.getElementById('voucher-form');
            if (!form) return;

            // Remove 'required' attributes from passenger inputs
            function removePassengerRequired() {
                const passengerSelectors =
                    'input[name^="passengers"][required], textarea[name^="passengers"][required], select[name^="passengers"][required]';
                document.querySelectorAll(passengerSelectors).forEach(function(el) {
                    el.removeAttribute('required');
                });
            }

            // Attach click listeners directly to submit buttons so we can act before browser validation runs
            const actionButtons = document.querySelectorAll('button[type="submit"][name="action"]');
            actionButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    if (this.value === 'generate') {
                        // disable HTML5 validation for the form and remove passenger required attributes
                        form.noValidate = true;
                        removePassengerRequired();
                        // set a global flag used by the jQuery submit handler
                        window.skipPassengerValidation = true;
                    } else {
                        // clear the flag for other actions
                        window.skipPassengerValidation = false;
                    }
                    // store last clicked value on the form for fallback
                    form.dataset.clickedAction = this.value;
                });
            });

            // Fallback on submit in case a submit occurs without clicking a button (rare)
            form.addEventListener('submit', function(e) {
                const clicked = form.dataset.clickedAction;
                if (clicked === 'generate') {
                    removePassengerRequired();
                }
            });
        })();
    </script>
    <script>
        function confirmAndDeleteAttachment(url) {
            // Use the shared confirmation modal instead of browser confirm
            try {
                showConfirmationModal('Delete Attachment', 'Delete attachment? This cannot be undone.', function() {
                    try {
                        // Create a standalone form (not nested) to submit with method spoofing
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = url;
                        form.style.display = 'none';

                        var tokenInput = document.querySelector('input[name="_token"]');
                        var token = tokenInput ? tokenInput.value : '{{ csrf_token() }}';

                        var inpToken = document.createElement('input');
                        inpToken.type = 'hidden';
                        inpToken.name = '_token';
                        inpToken.value = token;
                        form.appendChild(inpToken);

                        var inpMethod = document.createElement('input');
                        inpMethod.type = 'hidden';
                        inpMethod.name = '_method';
                        inpMethod.value = 'DELETE';
                        form.appendChild(inpMethod);

                        document.body.appendChild(form);
                        form.submit();
                    } catch (e) {
                        showErrorModal('Error', e.message || 'Failed to delete attachment');
                    }
                });
            } catch (e) {
                // Fallback to browser confirm if modal helper missing
                if (confirm('Delete attachment? This cannot be undone.')) {
                    try {
                        var f = document.createElement('form');
                        f.method = 'POST';
                        f.action = url;
                        f.style.display = 'none';
                        var t = document.createElement('input'); t.type = 'hidden'; t.name = '_token'; t.value = '{{ csrf_token() }}'; f.appendChild(t);
                        var m = document.createElement('input'); m.type = 'hidden'; m.name = '_method'; m.value = 'DELETE'; f.appendChild(m);
                        document.body.appendChild(f);
                        f.submit();
                    } catch (err) {
                        alert('Error deleting attachment: ' + (err.message || err));
                    }
                }
            }
        }
    </script>
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            const serviceExtraServicesMap = @json($serviceExtraServicesMap ?? []);

            // Client-side validation for client_name: only letters and spaces
            const clientNameInput = $('input[name="client_name"]');

            function validateClientNameClientSide() {
                const val = clientNameInput.val() || '';
                const regex = /^[\p{L}\s]+$/u; // unicode letters and spaces
                // remove previous inline error
                clientNameInput.siblings('.inline-error.client-name').remove();
                clientNameInput.removeClass('border-red-500');
                if (val.trim() === '') return true; // server still requires non-empty
                if (!regex.test(val.trim())) {
                    clientNameInput.addClass('border-red-500');
                    clientNameInput.after(
                        '<div class="inline-error client-name text-red-500 text-sm mt-1 font-medium">Client name should contain only letters and spaces.</div>'
                    );
                    return false;
                }
                return true;
            }

            clientNameInput.on('blur keyup', function() {
                validateClientNameClientSide();
            });
            // Initialize Select2 for any existing selects and restore vendor selections
            if (typeof $ !== 'undefined' && $.fn.select2) {
                $('.js-example-basic-single').each(function() {
                    if (!$(this).hasClass('select2-hidden-accessible')) {
                        $(this).select2({
                            width: '100%',
                            placeholder: 'Select...'
                        });
                    }
                });

                // Restore vendor selects which have data-selected-vendor attribute
                $('.vendor-select, .extra-vendor-select').each(function() {
                    const sel = $(this).attr('data-selected-vendor') || $(this).data('selected-vendor') ||
                        '';
                    if (sel) {
                        $(this).val(sel).trigger('change.select2');
                    }
                });

                // Restore operation team selection from old input if present and set contact number
                // const oldOperationTeam = {!! json_encode(old('operation_team_user_id')) !!};
                // if (oldOperationTeam) {
                //     const $opSelect = $('select[name="operation_team_user_id"]');
                //     if ($opSelect.length) {
                //         $opSelect.val(oldOperationTeam).trigger('change.select2');
                //         const contact = $opSelect.find('option:selected').attr('data-contact') || '';
                //         $('input[name="contact_number"]').val(contact);
                //     }
                // }

                const oldOperationTeam = {!! json_encode(old('operation_team_user_id')) !!};

                if (oldOperationTeam) {
                    const $opSelect = $('select[name="operation_team_user_id"]');

                    $opSelect.val(oldOperationTeam);

                    // Important: trigger BOTH
                    $opSelect.trigger('change'); 
                    $opSelect.trigger('change.select2');

                    const contact = $opSelect.find('option:selected').data('contact') || '';
                    $('input[name="contact_number"]').val(contact);
                }
                // Force a change event on all select2 selects so UI reflects any server-side selected options
                $('.js-example-basic-single').each(function() {
                    const v = $(this).val();
                    if (v !== undefined && v !== null && v !== '') {
                        $(this).val(v).trigger('change.select2');
                    }
                });
                // Explicitly trigger change on service and extra service selects so their handlers populate vendor and amounts
                $('.service-select, .extra-service-select').each(function() {
                    const v = $(this).val();
                    if (v !== undefined && v !== null && v !== '') {
                        $(this).trigger('change');
                    }
                });
            }
            // Form validation before submission
            $('#voucher-form').on('submit', function(e) {
                // Ensure passenger inputs present across all DataTable pages are included in the form submission.
                // DataTables paginates by removing non-current-page rows from the DOM, so inputs on other pages
                // won't be posted. We clone any non-visible passenger inputs into hidden fields before validation.
                (function() {
                    try {
                        if (typeof jQuery === 'undefined' || typeof $.fn.DataTable === 'undefined') return;
                        if ($.fn.DataTable.isDataTable('#passengers-table')) {
                            var ptable = $('#passengers-table').DataTable();
                            // Remove any previously appended clones
                            $('.passenger-hidden-clone').remove();

                            // Gather all passenger-related inputs across all pages
                            var selector = 'input[name^="passengers"], select[name^="passengers"], textarea[name^="passengers"]';
                            var allInputs = ptable.$(selector, { page: 'all' });

                            var hasFileWithDataOnOtherPage = false;

                            allInputs.each(function() {
                                // If the element is present in the current document, it's already part of the form
                                if (document.contains(this)) return;

                                var $el = $(this);
                                // File inputs cannot be serialized to hidden inputs. If a file input on another page has a file selected,
                                // we must stop and inform the user to go to that page and attach the file before submit.
                                if ($el.is(':file')) {
                                    if (this.files && this.files.length > 0) {
                                        hasFileWithDataOnOtherPage = true;
                                    }
                                    // skip cloning file inputs (cannot clone file data)
                                    return;
                                }

                                var name = $el.attr('name');
                                var val = $el.val();

                                // Append a hidden clone so server receives this field
                                var $hidden = $('<input>').attr('type', 'hidden').attr('name', name).val(val).addClass('passenger-hidden-clone');
                                $('#voucher-form').append($hidden);
                            });

                            if (hasFileWithDataOnOtherPage) {
                                // Let user know about file attachments on other pages
                                alert('One or more passenger file uploads are present on a different page. Please navigate to each passenger page and attach files before submitting the form.');
                                e.preventDefault();
                                return false;
                            }
                        }
                    } catch (err) {
                        console.warn('Error while cloning passenger inputs from other DataTable pages:', err);
                    }
                })();
                // Clear all previous inline errors
                $('.inline-error').remove();
                $('.border-red-500').removeClass('border-red-500');

                let hasError = false;
                let firstErrorField = null;
                // Determine which action was clicked (if any). Fallback to first action button (covers Enter key presses).
                let clickedAction = document.getElementById('voucher-form')?.dataset?.clickedAction || null;
                if (!clickedAction) {
                    const firstActionBtn = document.querySelector('button[type="submit"][name="action"]');
                    if (firstActionBtn) {
                        clickedAction = firstActionBtn.value;
                    }
                }

                // Validate required fields (skip passenger fields when generating)
                $('input[required], select[required]').each(function() {
                    const fieldName = $(this).attr('name') || '';

                    // If Generate was clicked (or global flag set), skip validation for passenger fields entirely
                    if ((clickedAction === 'generate' || window.skipPassengerValidation) &&
                        fieldName.indexOf('passengers') === 0) {
                        return; // continue
                    }

                    if (!$(this).val() || $(this).val().trim() === '') {
                        hasError = true;
                        $(this).addClass('border-red-500');

                        // Get field label for better error message
                        let label = $(this).closest(
                            '.xl\\:col-span-4, .xl\\:col-span-3, .xl\\:col-span-6, td').find(
                            'label').text().trim();
                        if (!label) {
                            label = $(this).attr('placeholder') || 'This field';
                        }
                        label = label.replace('*', '').trim();

                        const errorMsg =
                            `<div class="inline-error text-red-500 text-sm mt-1 font-medium"> ${label} is required</div>`;

                        if ($(this).closest('td').length > 0) {
                            $(this).closest('td').append(errorMsg);
                        } else {
                            $(this).after(errorMsg);
                        }

                        // Track first error field
                        if (!firstErrorField) {
                            firstErrorField = $(this);
                        }
                    }
                });

                // Additional client-side validation for travel text fields (departure/arrival/contact person)
                const letterOnlyRegex = /^[\p{L}\s]+$/u;
                const narrationRegex = /^[\p{L}0-9\s\.,_:;()'"\/\\-]+$/u;

                // Validate ride places and contact person
                $('input[name$="[from_place]"], input[name$="[to_place]"], input[name$="[contact_person]"]')
                    .each(function() {
                        const val = $(this).val() || '';
                        if (val.trim() !== '' && !letterOnlyRegex.test(val.trim())) {
                            hasError = true;
                            $(this).addClass('border-red-500');
                            $(this).after(
                                '<div class="inline-error text-red-500 text-sm mt-1 font-medium">Only letters and spaces are allowed.</div>'
                            );
                            if (!firstErrorField) firstErrorField = $(this);
                        }
                    });

                // Validate narration (additional information)
                const narrationEl = $('textarea[name="naration"]');
              
                // Validate service vendor selections
                $('#service-vendor-container tr:not(.no-services-message)').each(function() {
                    const vendorSelect = $(this).find('select[name*="[vendor_id]"]');
                    const rowNumber = $(this).index() + 1;

                    // if (vendorSelect.length > 0 && !vendorSelect.val()) {
                    //     hasError = true;
                    //     vendorSelect.addClass('border-red-500');
                    //     vendorSelect.closest('td').append(`<div class="inline-error text-red-500 text-sm mt-1 font-medium"> Please select a vendor for service row ${rowNumber}</div>`);

                    //     if (!firstErrorField) {
                    //         firstErrorField = vendorSelect;
                    //     }
                    // }
                });

                // Validate extra service vendor selections
                $('#extra-service-vendor-container tr:not(.no-extra-services-message)').each(function() {
                    const vendorSelect = $(this).find('select[name*="[vendor_id]"]');
                    const rowNumber = $(this).index() + 1;

                    // if (vendorSelect.length > 0 && !vendorSelect.val()) {
                    //     hasError = true;
                    //     vendorSelect.addClass('border-red-500');
                    //     vendorSelect.closest('td').append(`<div class="inline-error text-red-500 text-sm mt-1 font-medium"> Please select a vendor for extra service row ${rowNumber}</div>`);

                    //     if (!firstErrorField) {
                    //         firstErrorField = vendorSelect;
                    //     }
                    // }
                });

                if (hasError) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Show notification instead of scrolling
                    if (!$('.validation-notice').length) {
                        $('body').append(
                            '<div class="validation-notice fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded shadow-lg z-50 max-w-sm">❌ Please fix the validation errors highlighted in red below</div>'
                        );
                        setTimeout(function() {
                            $('.validation-notice').fadeOut(500, function() {
                                $(this).remove();
                            });
                        }, 5000);
                    }

                    // Prevent default browser validation scroll behavior
                    e.preventDefault();
                    e.stopPropagation();
                    e.stopImmediatePropagation();

                    return false;
                }
            });

            // Remove error styling when user fixes the issue
            $(document).on('change keyup', 'select, input', function() {
                if ($(this).val() && $(this).val().trim() !== '') {
                    $(this).removeClass('border-red-500');
                    // Remove inline error for this field
                    $(this).siblings('.inline-error').remove();
                    $(this).closest('td, div').find('.inline-error').remove();
                }
            });
            // Handle TBA checkbox functionality
            $(document).on('change', 'input[type="checkbox"][name*="[is_tba]"]', function() {
                const segment = $(this).attr('id').replace('tba_', '');
                const travelSegment = $(this).closest('.travel-segment');

                if ($(this).is(':checked')) {
                    // If TBA is checked, only disable and clear ride time fields (keep dates intact)
                    travelSegment.find('.ride-time-from, .ride-time-to')
                        .prop('disabled', true)
                        .val('')
                        .prop('required', false);

                    // Clear any calculated total time
                    travelSegment.find('.total-time').val('');
                } else {
                    // If TBA is unchecked, enable ride time fields again and restore 'required'
                    travelSegment.find('.ride-time-from, .ride-time-to')
                        .prop('disabled', false)
                        .prop('required', true);
                }
            });

            // Initialize TBA state on page load
            $('input[type="checkbox"][name*="[is_tba]"]').each(function() {
                if ($(this).is(':checked')) {
                    $(this).trigger('change');
                }
            });

            // Enforce to_date cannot be before from_date on client side
            $(document).on('change', 'input[name$="[from_date]"]', function() {
                const name = $(this).attr('name');
                // derive index from name like rides[0][from_date]
                const matches = name.match(/rides\[(\d+)\]\[from_date\]/);
                if (!matches) return;
                const idx = matches[1];
                const fromVal = $(this).val();
                const toEl = $(`input[name="rides[${idx}][to_date]"]`);
                if (toEl.length) {
                    toEl.attr('min', fromVal);
                    // if current to_date is less than from_date, clear it and show small inline note
                    if (toEl.val() && toEl.val() < fromVal) {
                        toEl.val('');
                        showInlineFieldError(toEl, 'To Date cannot be earlier than From Date.');
                    }
                }
            });

            // helper to show inline small error near a field
            function showInlineFieldError($field, message) {
                $field.addClass('border-red-500');
                $field.closest('div').find('.inline-error').remove();
                $field.after(`<small class="inline-error text-red-600 block mt-1">${message}</small>`);
            }

            // Initialize min attributes for existing ride segments on page load
            function initToDateMins() {
                $('input[name$="[from_date]"]').each(function() {
                    const name = $(this).attr('name');
                    const matches = name.match(/rides\[(\d+)\]\[from_date\]/);
                    if (!matches) return;
                    const idx = matches[1];
                    const fromVal = $(this).val();
                    const toEl = $(`input[name="rides[${idx}][to_date]"]`);
                    if (toEl.length && fromVal) {
                        toEl.attr('min', fromVal);
                        // remove any stale inline error if fixed
                        if (toEl.val() && toEl.val() >= fromVal) {
                            toEl.removeClass('border-red-500');
                            toEl.closest('div').find('.inline-error').remove();
                        }
                    }
                });
            }

            // Validate to_date on change to prevent earlier selection
            $(document).on('change', 'input[name$="[to_date]"]', function() {
                const name = $(this).attr('name');
                const matches = name.match(/rides\[(\d+)\]\[to_date\]/);
                if (!matches) return;
                const idx = matches[1];
                const toVal = $(this).val();
                const fromEl = $(`input[name="rides[${idx}][from_date]"]`);
                const fromVal = fromEl.length ? fromEl.val() : null;
                if (fromVal && toVal && toVal < fromVal) {
                    // invalid: to date before from date
                    $(this).val('');
                    showInlineFieldError($(this), 'To Date cannot be earlier than From Date.');
                } else {
                    // clear error if fixed
                    $(this).removeClass('border-red-500');
                    $(this).closest('div').find('.inline-error').remove();
                }
            });

            // Run initialization on load
            initToDateMins();

            // Calculate total time when time fields change
            $('.ride-time-from, .ride-time-to').on('change', function() {
                const segment = $(this).data('segment');
                const travelSegment = $(`.travel-segment[data-segment="${segment}"]`);
                const fromTime = travelSegment.find(`.ride-time-from[data-segment="${segment}"]`).val();
                const toTime = travelSegment.find(`.ride-time-to[data-segment="${segment}"]`).val();

                // Try to find service_date (single-day) or from_date/to_date (multi-day) within the same segment
                const serviceDateEl = travelSegment.find(`input[name="rides[${segment}][service_date]"]`);
                const fromDateEl = travelSegment.find(`input[name="rides[${segment}][from_date]"]`);
                const toDateEl = travelSegment.find(`input[name="rides[${segment}][to_date]"]`);

                const serviceDate = serviceDateEl.length ? serviceDateEl.val() : null;
                const fromDate = fromDateEl.length ? fromDateEl.val() : null;
                const toDate = toDateEl.length ? toDateEl.val() : null;

                if (fromTime && toTime) {
                    // Helper: convert common date formats (dd-mm-yyyy or dd/mm/yyyy) to ISO yyyy-mm-dd
                    function normalizeToISO(dateStr) {
                        if (!dateStr) return null;
                        // Already ISO-like (yyyy-mm-dd)
                        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
                        // dd-mm-yyyy or dd/mm/yyyy
                    const m = dateStr.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
                        if (m) {
                            const d = m[1].padStart(2, '0');
                            const mon = m[2].padStart(2, '0');
                            const y = m[3];
                            return `${y}-${mon}-${d}`;
                        }
                        // fallback: return original (may or may not parse)
                        return dateStr;
                    }

                    // Helper: parse HH:MM to total minutes
                    function timeToMinutes(t) {
                        if (!t) return 0;
                        // normalize whitespace
                        let s = t.toString().trim();
                        // Handle formats like '12:00 PM', '12:00PM', '12 PM', '12PM'
                        const ampmMatch = s.match(/(am|pm)$/i);
                        let isPM = false;
                        if (ampmMatch) {
                            isPM = ampmMatch[1].toLowerCase() === 'pm';
                            s = s.replace(/(am|pm)$/i, '').trim();
                        }

                        let hh = 0, mm = 0;
                        if (s.indexOf(':') !== -1) {
                            const parts = s.split(':');
                            hh = parseInt(parts[0], 10) || 0;
                            mm = parseInt(parts[1], 10) || 0;
                        } else {
                            hh = parseInt(s, 10) || 0;
                        }

                        if (ampmMatch) {
                            if (isPM && hh < 12) hh += 12;
                            if (!isPM && hh === 12) hh = 0; // 12 AM -> 0
                        }

                        return hh * 60 + mm;
                    }

                    // Determine dates to use (if present)
                    const sd = serviceDate ? serviceDate : (fromDate || (fromDateEl.length ? fromDateEl.val() : null));
                    const ed = toDate ? toDate : (toDateEl.length ? toDateEl.val() : null);

                    let diffMinutes = null;

                    // If we have at least one explicit date, try to compute using full Date objects after normalizing
                    if (sd || ed) {
                        const sDateStr = normalizeToISO(sd || ed);
                        const eDateStr = normalizeToISO(ed || sd);

                        // Build Date objects deterministically from yyyy-mm-dd and HH:MM
                        try {
                            const sParts = (sDateStr || '').split('-');
                            const eParts = (eDateStr || '').split('-');
                            const sY = parseInt(sParts[0], 10) || NaN;
                            const sM = parseInt(sParts[1], 10) - 1 || NaN;
                            const sD = parseInt(sParts[2], 10) || NaN;
                            const eY = parseInt(eParts[0], 10) || NaN;
                            const eM = parseInt(eParts[1], 10) - 1 || NaN;
                            const eD = parseInt(eParts[2], 10) || NaN;
                            const fromParts = (fromTime || '').split(':');
                            const toParts = (toTime || '').split(':');
                            const fh = parseInt(fromParts[0], 10) || 0;
                            const fm = parseInt(fromParts[1], 10) || 0;
                            const th = parseInt(toParts[0], 10) || 0;
                            const tm = parseInt(toParts[1], 10) || 0;

                            if (!isNaN(sY) && !isNaN(sM) && !isNaN(sD) && !isNaN(eY) && !isNaN(eM) && !isNaN(eD)) {
                                const startDate = new Date(sY, sM, sD, fh, fm, 0);
                                const endDate = new Date(eY, eM, eD, th, tm, 0);
                                diffMinutes = Math.round((endDate.getTime() - startDate.getTime()) / (1000 * 60));
                            }
                        } catch (e) {
                            // parsing failed — leave diffMinutes null to fall back to time-only logic
                        }
                    }

                    // If dates are not usable or parsing failed, compute purely from times (handles cross-midnight)
                    if (diffMinutes === null) {
                        const startMin = timeToMinutes(fromTime);
                        let endMin = timeToMinutes(toTime);
                        if (endMin < startMin) {
                            // assume next day
                            endMin += 24 * 60;
                        }
                        diffMinutes = endMin - startMin;
                    }

                    // Defensive: if somehow still negative, wrap around 24h
                    if (diffMinutes < 0) diffMinutes += 24 * 60;

                    const hours = Math.floor(diffMinutes / 60);
                    const minutes = diffMinutes % 60;
                    const totalTime = (diffMinutes / 60);

                    // Debugging info to help diagnose negative totals in client console
                    if (typeof console !== 'undefined' && console.debug) {
                        console.debug('calcTotalTime(segment=', segment, 'serviceDate=', serviceDate, 'fromDate=', fromDate, 'toDate=', toDate, 'fromTime=', fromTime, 'toTime=', toTime, 'startDate=', (typeof startDate !== 'undefined' ? startDate : null), 'endDate=', (typeof endDate !== 'undefined' ? endDate : null), 'diffMinutes=', diffMinutes, 'totalHours=', totalTime.toFixed(2));
                    }

                    $(`.total-time[data-segment="${segment}"]`).val(totalTime.toFixed(2));
                }
            });

            // Initialize total time calculation for existing rides
            $('.ride-time-from, .ride-time-to').trigger('change');

            // Operation team member selection - auto populate contact number
            $(document).on('change', 'select[name="operation_team_user_id"]', function() {
                const selectedOption = $(this).find(':selected');
                const contactNumber = selectedOption.attr('data-contact') || '';
                $('input[name="contact_number"]').val(contactNumber);
            });

            function getMappedExtraServiceIdsFromSelectedServices() {
                const mappedIds = [];

                $('.service-select').each(function() {
                    const serviceId = $(this).val();
                    if (!serviceId) {
                        return;
                    }

                    let extraServiceIds = serviceExtraServicesMap[serviceId] || [];
                    const selectedOptionMap = $(this).find(':selected').attr('data-extra-services');

                    if (selectedOptionMap) {
                        try {
                            extraServiceIds = JSON.parse(selectedOptionMap);
                        } catch (e) {
                            extraServiceIds = serviceExtraServicesMap[serviceId] || [];
                        }
                    }

                    extraServiceIds.forEach(extraServiceId => {
                        const normalizedId = String(extraServiceId);
                        if (normalizedId && !mappedIds.includes(normalizedId)) {
                            mappedIds.push(normalizedId);
                        }
                    });
                });

                return mappedIds;
            }

            function getSelectedExtraServiceRowIds() {
                return $('.extra-service-select').map(function() {
                    return String($(this).val() || '');
                }).get().filter(Boolean);
            }

            function appendExtraServiceVendorRow(selectedExtraServiceId = '', isAutoMapped = false) {
                $('.no-extra-services-message').remove();

                const template = `
            <tr class="extra-service-vendor-item border-b border-defaultborder" data-index="${extraServiceIndex}" data-auto-mapped="${isAutoMapped ? '1' : '0'}">
                <td>${$('#extra-service-vendor-container tr:not(.no-extra-services-message)').length + 1}</td>
                <td>
                    <select name="extra_services[${extraServiceIndex}][extra_service_id]" class="js-example-basic-single w-full form-control-sm extra-service-select" required>
                        <option value="">Select Extra Service</option>
                        @foreach ($allExtraServices as $availableExtraService)
                            <option value="{{ $availableExtraService->id }}"
                                    data-vendors='@json($availableExtraService->vendors->pluck('name', 'id'))'
                                    data-amount="{{ $availableExtraService->extra_service_amount }}">
                                {{ $availableExtraService->extra_service }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" name="extra_services[${extraServiceIndex}][amount]"
                           class="ti-form-input rounded-sm form-control-sm extra-service-amount" step="0.01" required>
                </td>
                <td>
                    <select name="extra_services[${extraServiceIndex}][vendor_id]" class="js-example-basic-single w-full form-control-sm extra-vendor-select" required>
                        <option value="">Select Vendor</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="extra_services[${extraServiceIndex}][vendor_amount]"
                           class="ti-form-input rounded-sm form-control-sm extra-vendor-amount" step="0.01">
                </td>
                <td>
                    <button type="button" class="ti-btn ti-btn-danger ti-btn-sm remove-extra-service" title="Remove Extra Service">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </td>
            </tr>
        `;

                $('#extra-service-vendor-container').append(template);

                const newRow = $('#extra-service-vendor-container tr:last');
                newRow.find('.js-example-basic-single').select2({
                    width: '100%',
                    placeholder: 'Select...'
                });

                if (selectedExtraServiceId) {
                    newRow.find('.extra-service-select').val(selectedExtraServiceId).trigger('change');
                }

                extraServiceIndex++;
                updateRowNumbers('#extra-service-vendor-container');
            }

            function syncMappedExtraServiceRows() {
                const mappedIds = getMappedExtraServiceIdsFromSelectedServices();

                $('.extra-service-vendor-item[data-auto-mapped="1"]').each(function() {
                    const selectedExtraServiceId = String($(this).find('.extra-service-select').val() || '');
                    if (!selectedExtraServiceId || !mappedIds.includes(selectedExtraServiceId)) {
                        $(this).remove();
                    }
                });

                const selectedExtraServiceIds = getSelectedExtraServiceRowIds();
                mappedIds.forEach(extraServiceId => {
                    if (!selectedExtraServiceIds.includes(extraServiceId)) {
                        appendExtraServiceVendorRow(extraServiceId, true);
                        selectedExtraServiceIds.push(extraServiceId);
                    }
                });

                updateRowNumbers('#extra-service-vendor-container');
            }

            // Service selection change handler
            $(document).on('change', '.service-select', function() {
                const row = $(this).closest('tr');
                const vendorSelect = row.find('.vendor-select');
                const serviceAmountInput = row.find('.service-amount');
                const vendorAmountInput = row.find('.vendor-amount');

                const selectedOption = $(this).find(':selected');
                const vendors = JSON.parse(selectedOption.attr('data-vendors') || '{}');
                const serviceAmount = selectedOption.attr('data-amount') || '';

                // Update service amount
                if (serviceAmount) {
                    serviceAmountInput.val(serviceAmount);
                    // Only set vendor amount if the field is empty (preserve saved vendor_amount)
                    if (!vendorAmountInput.val() || vendorAmountInput.val().toString().trim() === '') {
                        vendorAmountInput.val(serviceAmount);
                    }
                }

                // Update vendor dropdown
                const prevSelectedVendor = vendorSelect.attr('data-selected-vendor') || vendorSelect.data(
                    'selected-vendor') || '';
                vendorSelect.empty().append('<option value="">Select Vendor</option>');
                Object.keys(vendors).forEach(vendorId => {
                    vendorSelect.append(
                        `<option value="${vendorId}">${vendors[vendorId]}</option>`);
                });
                if (prevSelectedVendor) {
                    vendorSelect.val(prevSelectedVendor).trigger('change.select2');
                    // remove the data attribute so future changes don't reapply
                    vendorSelect.removeAttr('data-selected-vendor');
                    vendorSelect.data('selected-vendor', '');
                }

                // Update service address dropdowns
                updateServiceAddressDropdowns();
                syncMappedExtraServiceRows();
            });

            // Extra service selection change handler
            $(document).on('change', '.extra-service-select', function() {
                const row = $(this).closest('tr');
                const vendorSelect = row.find('.extra-vendor-select');
                const serviceAmountInput = row.find('.extra-service-amount');
                const vendorAmountInput = row.find('.extra-vendor-amount');

                const selectedOption = $(this).find(':selected');
                const vendors = JSON.parse(selectedOption.attr('data-vendors') || '{}');
                const serviceAmount = selectedOption.attr('data-amount') || '';

                // Update service amount for extra service
                if (serviceAmount) {
                    serviceAmountInput.val(serviceAmount);
                    // Only set extra vendor amount if the field is empty (preserve saved vendor_amount)
                    if (!vendorAmountInput.val() || vendorAmountInput.val().toString().trim() === '') {
                        vendorAmountInput.val(serviceAmount);
                    }
                }

                // Update vendor dropdown
                const prevSelectedExtraVendor = vendorSelect.attr('data-selected-vendor') || vendorSelect
                    .data('selected-vendor') || '';
                vendorSelect.empty().append('<option value="">Select Vendor</option>');
                Object.keys(vendors).forEach(vendorId => {
                    vendorSelect.append(
                        `<option value="${vendorId}">${vendors[vendorId]}</option>`);
                });
                if (prevSelectedExtraVendor) {
                    vendorSelect.val(prevSelectedExtraVendor).trigger('change.select2');
                    vendorSelect.removeAttr('data-selected-vendor');
                    vendorSelect.data('selected-vendor', '');
                }
            });

            // Function to update service address dropdowns based on selected services
            function updateServiceAddressDropdowns() {
                // Get all selected service IDs
                const selectedServiceIds = [];
                $('.service-select').each(function() {
                    const serviceId = $(this).val();
                    if (serviceId) {
                        selectedServiceIds.push(serviceId);
                    }
                });

                console.log('Selected service IDs:', selectedServiceIds);

                // Filter service address dropdowns using existing options
                $('.service-address-select').each(function() {
                    const $select = $(this);
                    const currentValue = $select.val();
                    
                    // Get all original options (stored in data attribute if not already stored)
                    if (!$select.data('original-options')) {
                        $select.data('original-options', $select.html());
                    }
                    
                    // Clear current options and add default
                    $select.empty().append('<option value="">Select Ride Address</option>');
                    
                    if (selectedServiceIds.length > 0) {
                        // Parse original options and filter by service IDs
                        const $originalOptions = $($select.data('original-options'));
                        $originalOptions.each(function() {
                            const $option = $(this);
                            const serviceId = $option.attr('data-service-id');
                            
                            // Show option if it belongs to one of the selected services
                            if (!serviceId || selectedServiceIds.includes(serviceId)) {
                                $select.append($option.clone());
                            }
                        });
                    } else {
                        // No services selected, show all addresses
                        $select.html($select.data('original-options'));
                    }
                    
                    // Restore previous selection if it still exists
                    if (currentValue && $select.find(`option[value="${currentValue}"]`).length > 0) {
                        $select.val(currentValue);
                    }
                    
                    // Refresh Select2
                    $select.trigger('change.select2');
                    
                    console.log('Updated dropdown with', $select.find('option').length - 1, 'addresses');
                });
            }

            // Initialize service address dropdowns on page load
            $(document).ready(function() {
                console.log('Page loaded, initializing service addresses...');
                console.log('Service selects found:', $('.service-select').length);
                
                // Check if there are pre-selected services, if so, filter addresses
                const hasPreSelectedServices = $('.service-select').filter(function() {
                    return $(this).val() && $(this).val() !== '';
                }).length > 0;
                
                if (hasPreSelectedServices) {
                    console.log('Found pre-selected services, filtering addresses...');
                    updateServiceAddressDropdowns();
                } else {
                    console.log('No pre-selected services, addresses already loaded from server');
                }
            });

            // Also call when services are removed
            $(document).on('click', '.remove-service', function() {
                setTimeout(function() {
                    updateServiceAddressDropdowns();
                }, 100); // Small delay to ensure DOM is updated
            });

            // Service Vendor Section
            let serviceIndex = {!! json_encode(
                old('services') ? count(old('services')) : (isset($selectedServices) ? count($selectedServices) : 0),
            ) !!};
            $('#add-service-vendor').click(function() {
                $('.no-services-message').remove();

                const template = `
            <tr class="service-vendor-item border-b border-defaultborder" data-index="${serviceIndex}">
                <td>${$('#service-vendor-container tr:not(.no-services-message)').length + 1}</td>
                <td>
                    <select name="services[${serviceIndex}][service_id]" class="js-example-basic-single w-full form-control-sm service-select" required>
                        <option value="">Select Service</option>
                        @foreach ($allServices as $availableService)
                            <option value="{{ $availableService->id }}" 
                                    data-vendors='@json($availableService->vendors->pluck('name', 'id'))'
                                    data-amount="{{ $availableService->service_amount }}"
                                    data-extra-services='@json($availableService->extraServices->pluck('id')->values())'>
                                {{ $availableService->service_name ?? $availableService->service }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td>
                    <input type="number" name="services[${serviceIndex}][amount]" 
                           class="ti-form-input rounded-sm form-control-sm service-amount" step="0.01" required>
                </td>
                <td>
                    <select name="services[${serviceIndex}][vendor_id]" class="js-example-basic-single w-full form-control-sm vendor-select" required>
                        <option value="">Select Vendor</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="services[${serviceIndex}][vendor_amount]" 
                           class="ti-form-input rounded-sm form-control-sm vendor-amount" step="0.01">
                </td>
                <td>
                    <button type="button" class="ti-btn ti-btn-danger ti-btn-sm remove-service" title="Remove Service">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </td>
            </tr>
        `;
                $('#service-vendor-container').append(template);

                // Reinitialize Select2 for the newly added row
                $('#service-vendor-container tr:last').find('.js-example-basic-single').select2({
                    width: '100%',
                    placeholder: 'Select...'
                });

                serviceIndex++;
            });

            // Extra Service Vendor Section
            let extraServiceIndex = {!! json_encode(
                old('extra_services')
                    ? count(old('extra_services'))
                    : (isset($selectedExtraServices)
                        ? count($selectedExtraServices)
                        : 0),
            ) !!};
            $('#add-extra-service-vendor').click(function() {
                appendExtraServiceVendorRow('', false);
            });

            syncMappedExtraServiceRows();

            // Remove handlers
            $(document).on('click', '.remove-service', function() {
                console.log('remove service');
                $(this).closest('tr').remove();
                updateRowNumbers('#service-vendor-container');
                if ($('#service-vendor-container tr').length === 0) {
                    $('#service-vendor-container').append(`
                <tr class="no-services-message">
                    <td colspan="6" class="text-center text-gray-500 py-4">
                        No services selected in followup. Click "Add Service & Vendor Detail" to add services.
                    </td>
                </tr>
            `);
                }
                syncMappedExtraServiceRows();
            });

            $(document).on('click', '.remove-extra-service', function() {
                $(this).closest('tr').remove();
                updateRowNumbers('#extra-service-vendor-container');
                if ($('#extra-service-vendor-container tr').length === 0) {
                    $('#extra-service-vendor-container').append(`
                <tr class="no-extra-services-message">
                    <td colspan="6" class="text-center text-gray-500 py-4">
                        No extra services selected in followup. Click "Add Extra Service & Vendor Detail" to add extra services.
                    </td>
                </tr>
            `);
                }
            });

            // Update row numbers
            function updateRowNumbers(containerSelector) {
                $(containerSelector + ' tr').each(function(index) {
                    if (!$(this).hasClass('no-services-message') && !$(this).hasClass(
                            'no-extra-services-message')) {
                        $(this).find('td:first').text(index + 1);
                    }
                });
            }

            // Personal Details Section
            // Maximum passengers allowed (from lead.number_of_passengers)
            const MAX_PASSENGERS = {!! json_encode($lead->number_of_passengers ?? 1) !!};

            let passengerIndex = {!! json_encode(
                isset($voucher) && $voucher->passengers->count() > 0
                    ? $voucher->passengers->filter(fn($p) => !$p->is_handler && !$p->is_additional_person)->count()
                    : (isset($preVoucherPassengers) && $preVoucherPassengers->count() > 0
                        ? $preVoucherPassengers->count()
                        : 1),
            ) !!};

            // Enable/disable Add Passenger button based on current count vs MAX_PASSENGERS
            function updateAddPassengerState() {
                const currentCount = $('#passengers-table tbody tr').length;
                const $btn = $('#add-passenger');
                if (currentCount >= MAX_PASSENGERS) {
                    $btn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed');
                } else {
                    $btn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed');
                }
            }

            // Initialize state on page load
            $(document).ready(function() {
                updateAddPassengerState();
            });

            $('#add-passenger').click(function() {
                const currentCount = $('#passengers-table tbody tr').length;
                if (currentCount >= MAX_PASSENGERS) {
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Maximum Passengers Reached', `You can add up to ${MAX_PASSENGERS} passenger(s).`);
                    } else {
                        alert('You can add up to ' + MAX_PASSENGERS + ' passenger(s).');
                    }
                    return;
                }

                const template = `
            <tr class="passenger-row border-b border-defaultborder" data-index="${passengerIndex}">
                <td>${$('#passengers-table tbody tr').length + 1}</td>
                <td>
                    <input type="text" name="passengers[${passengerIndex}][name]" class="ti-form-input rounded-sm form-control-sm" required>
                </td>
                <td>
                    <input type="number" name="passengers[${passengerIndex}][age]" class="ti-form-input rounded-sm form-control-sm">
                </td>
                <td>
                    <input type="number" name="passengers[${passengerIndex}][weight]" class="ti-form-input rounded-sm form-control-sm" step="0.1">
                </td>
                <td>
                    <input type="file" name="passengers[${passengerIndex}][front_document]" class="ti-form-input rounded-sm form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max size: 2MB</small>
                </td>
                <td>
                    <input type="file" name="passengers[${passengerIndex}][back_document]" class="ti-form-input rounded-sm form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted">Allowed formats: JPG, JPEG, PNG, PDF | Max size: 2MB</small>
                </td>
                <td>
                    <button type="button" class="ti-btn ti-btn-danger ti-btn-sm remove-passenger" title="Remove Passenger">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </td>
            </tr>
        `;
                $('#passengers-table tbody').append(template);
                passengerIndex++;
                updateAddPassengerState();
            });

            // Remove passenger
            $(document).on('click', '.remove-passenger', function() {
                const currentRow = $(this).closest('tr');
                const isFirstRow = currentRow.index() === 0;
                const isAirAmbulance = @json(isset($isAirAmbulance) && $isAirAmbulance);

                // Prevent removal of first row if air ambulance service
                if (isFirstRow && isAirAmbulance) {
                    if (typeof showErrorModal === 'function') {
                        showErrorModal('Action Not Allowed',
                            'Patient information cannot be deleted for Air Ambulance services.');
                    } else {
                        alert('Patient information cannot be deleted for Air Ambulance services.');
                    }
                    return false;
                }

                // If the removed row corresponds to an existing passenger (has an id), record it so the server can delete it
                const idInput = currentRow.find('input[name$="[id]"]');
                if (idInput.length) {
                    const pid = idInput.val();
                    if (pid) {
                        // append a hidden input to the form listing deleted ids
                        $('#voucher-form').append('<input type="hidden" name="deleted_passenger_ids[]" value="' + pid + '">');
                    }
                }

                currentRow.remove();
                reindexPassengerRows();
                updatePassengerNumbers();
            });

            // Re-index passenger input field names after deletion
            function reindexPassengerRows() {
                $('#passengers-table tbody tr').each(function(newIndex) {
                    const row = $(this);
                    
                    // Update all input field names to use the new index
                    row.find('input[name^="passengers["]').each(function() {
                        const input = $(this);
                        const name = input.attr('name');
                        
                        // Replace the old index with the new index
                        // Match pattern: passengers[oldIndex][fieldName]
                        const newName = name.replace(/passengers\[\d+\]/, `passengers[${newIndex}]`);
                        input.attr('name', newName);
                    });
                    
                    // Update data-index attribute
                    row.attr('data-index', newIndex);
                });
                
                // Update the global passenger index counter
                passengerIndex = $('#passengers-table tbody tr').length;
            }

            // Update passenger numbers
            function updatePassengerNumbers() {
                $('#passengers-table tbody tr').each(function(index) {
                    $(this).find('td:first').text(index + 1);
                });
                // Ensure Add Passenger button state is updated after any removal/reindexing
                if (typeof updateAddPassengerState === 'function') {
                    updateAddPassengerState();
                }
            }

            // Helper to update contact fields from a service-address select element
            function updateServiceAddressFields($select) {
                // prefer explicit attr read (works whether Select2 is present or not)
                let segmentIndex = $select.attr('data-segment') || $select.data('segment');
                const val = ($select.val() || '').toString().trim();

                // fallback: if no data-segment, try to derive from name like rides[0][service_address_id]
                if (segmentIndex === undefined || segmentIndex === null || segmentIndex === '') {
                    const name = $select.attr('name') || '';
                    const m = name.match(/rides\[(\d+)\]\[service_address_id\]/);
                    if (m) segmentIndex = m[1];
                }

                // try to locate the option by value first (more reliable with Select2)
                let selectedOption = $select.find('option[value="' + val + '"]');
                if (!selectedOption || selectedOption.length === 0) {
                    selectedOption = $select.find('option:selected');
                }

                // defensive: ensure we have an element
                if (!selectedOption || selectedOption.length === 0) {
                    console.debug('updateServiceAddressFields: no selected option found for select', $select[0]);
                    // clear fields if we can't find matching option
                    if (segmentIndex !== undefined && segmentIndex !== null && segmentIndex !== '') {
                        $(`.contact-person-input[data-segment="${segmentIndex}"]`).val('');
                        $(`.contact-number-input[data-segment="${segmentIndex}"]`).val('');
                        $(`.map-link-input[data-segment="${segmentIndex}"]`).val('');
                    }
                    return;
                }

                const contactPerson = (selectedOption.attr('data-contact-person') || '').toString().trim();
                const contactNumber = (selectedOption.attr('data-contact-number') || '').toString().trim();
                const mapLink = (selectedOption.attr('data-map-link') || '').toString().trim();

                console.debug('updateServiceAddressFields: segment=', segmentIndex, 'val=', val, 'contactPerson=',
                    contactPerson, 'contactNumber=', contactNumber, 'mapLink=', mapLink);

                // Update the contact fields if we have a segment; otherwise try to update any matching inputs in the same travel-segment
                if (segmentIndex !== undefined && segmentIndex !== null && segmentIndex !== '') {
                    $(`.contact-person-input[data-segment="${segmentIndex}"]`).val(contactPerson);
                    $(`.contact-number-input[data-segment="${segmentIndex}"]`).val(contactNumber);
                    $(`.map-link-input[data-segment="${segmentIndex}"]`).val(mapLink);
                } else {
                    // best-effort: find nearest travel-segment container and update inputs inside it
                    const $container = $select.closest('.travel-segment');
                    if ($container.length) {
                        $container.find('.contact-person-input').val(contactPerson);
                        $container.find('.contact-number-input').val(contactNumber);
                        $container.find('.map-link-input').val(mapLink);
                    }
                }
            }

            // Handle service address change (native change and Select2 selection event)
            // Listen for native 'change' and Select2's 'select2:select' separately for reliability.
            $(document).on('change', '.service-address-select', function() {
                updateServiceAddressFields($(this));
            });

            $(document).on('select2:select', '.service-address-select', function(e) {
                // select2 triggers this event; use the event target to ensure we reference the real <select>
                const $el = $(e.target || this);
                updateServiceAddressFields($el);
            });

            // Also listen for Select2 change events (some versions trigger this)
            $(document).on('change.select2', '.service-address-select', function(e) {
                const $el = $(e.target || this);
                updateServiceAddressFields($el);
            });

            // Initialize current values on page load. If Select2 is used, initialization already ran earlier,
            // but run again here to be safe and provide debug logs.
            $(document).ready(function() {
                $('.service-address-select').each(function() {
                    updateServiceAddressFields($(this));
                });
            });

            // Calculate total time when ride times change (robust: consider date fields when available)
            $(document).on('change', '.ride-time-from, .ride-time-to', function() {
                const segmentIndex = $(this).data('segment');
                const travelSegment = $(`.travel-segment[data-segment="${segmentIndex}"]`);
                const timeFrom = travelSegment.find(`.ride-time-from[data-segment="${segmentIndex}"]`)
                    .val();
                const timeTo = travelSegment.find(`.ride-time-to[data-segment="${segmentIndex}"]`).val();

                const serviceDateEl = travelSegment.find(
                    `input[name="rides[${segmentIndex}][service_date]"]`);
                const fromDateEl = travelSegment.find(`input[name="rides[${segmentIndex}][from_date]"]`);
                const toDateEl = travelSegment.find(`input[name="rides[${segmentIndex}][to_date]"]`);

                const serviceDate = serviceDateEl.length ? serviceDateEl.val() : null;
                const fromDate = fromDateEl.length ? fromDateEl.val() : null;
                const toDate = toDateEl.length ? toDateEl.val() : null;

                if (timeFrom && timeTo) {
                    // Helpers (local): normalize date strings and convert times to minutes
                    function normalizeToISO(dateStr) {
                        if (!dateStr) return null;
                        if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr;
                        const m = dateStr.match(/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/);
                        if (m) {
                            const d = m[1].padStart(2, '0');
                            const mon = m[2].padStart(2, '0');
                            const y = m[3];
                            return `${y}-${mon}-${d}`;
                        }
                        return dateStr;
                    }

                    function timeToMinutes(t) {
                        if (!t) return 0;
                        let s = t.toString().trim();
                        const ampmMatch = s.match(/(am|pm)$/i);
                        let isPM = false;
                        if (ampmMatch) {
                            isPM = ampmMatch[1].toLowerCase() === 'pm';
                            s = s.replace(/(am|pm)$/i, '').trim();
                        }

                        let hh = 0, mm = 0;
                        if (s.indexOf(':') !== -1) {
                            const parts = s.split(':');
                            hh = parseInt(parts[0], 10) || 0;
                            mm = parseInt(parts[1], 10) || 0;
                        } else {
                            hh = parseInt(s, 10) || 0;
                        }

                        if (ampmMatch) {
                            if (isPM && hh < 12) hh += 12;
                            if (!isPM && hh === 12) hh = 0;
                        }

                        return hh * 60 + mm;
                    }

                    const sd = serviceDate ? serviceDate : (fromDate || (fromDateEl.length ? fromDateEl.val() : null));
                    const ed = toDate ? toDate : (toDateEl.length ? toDateEl.val() : null);

                    let diffMinutes = null;

                    if (sd || ed) {
                        const sDateStr = normalizeToISO(sd || ed);
                        const eDateStr = normalizeToISO(ed || sd);
                        // Deterministic Date construction to avoid locale parsing problems
                        try {
                            const sParts = (sDateStr || '').split('-');
                            const eParts = (eDateStr || '').split('-');
                            const sY = parseInt(sParts[0], 10) || NaN;
                            const sM = parseInt(sParts[1], 10) - 1 || NaN;
                            const sD = parseInt(sParts[2], 10) || NaN;
                            const eY = parseInt(eParts[0], 10) || NaN;
                            const eM = parseInt(eParts[1], 10) - 1 || NaN;
                            const eD = parseInt(eParts[2], 10) || NaN;
                            const fromParts = (timeFrom || '').split(':');
                            const toParts = (timeTo || '').split(':');
                            const fh = parseInt(fromParts[0], 10) || 0;
                            const fm = parseInt(fromParts[1], 10) || 0;
                            const th = parseInt(toParts[0], 10) || 0;
                            const tm = parseInt(toParts[1], 10) || 0;

                            if (!isNaN(sY) && !isNaN(sM) && !isNaN(sD) && !isNaN(eY) && !isNaN(eM) && !isNaN(eD)) {
                                const startDate = new Date(sY, sM, sD, fh, fm, 0);
                                const endDate = new Date(eY, eM, eD, th, tm, 0);
                                diffMinutes = Math.round((endDate.getTime() - startDate.getTime()) / (1000 * 60));
                            }
                        } catch (e) {
                            // parsing failed — leave diffMinutes null to fall back to time-only logic
                        }
                    }

                    if (diffMinutes === null) {
                        const startMin = timeToMinutes(timeFrom);
                        let endMin = timeToMinutes(timeTo);
                        if (endMin < startMin) endMin += 24 * 60; // assume next day
                        diffMinutes = endMin - startMin;
                    }

                    if (diffMinutes < 0) diffMinutes += 24 * 60; // defensive

                    const diffHours = diffMinutes / 60;

                    // Debugging info for browser console
                    if (typeof console !== 'undefined' && console.debug) {
                        console.debug('calcTotalTime(segmentIndex=', segmentIndex, 'serviceDate=', serviceDate, 'fromDate=', fromDate, 'toDate=', toDate, 'timeFrom=', timeFrom, 'timeTo=', timeTo, 'startDate=', (typeof startDate !== 'undefined' ? startDate : null), 'endDate=', (typeof endDate !== 'undefined' ? endDate : null), 'diffMinutes=', diffMinutes, 'diffHours=', diffHours);
                    }

                    if (!isNaN(diffHours) && isFinite(diffHours)) {
                        $(`.total-time[data-segment="${segmentIndex}"]`).val(diffHours.toFixed(2));
                    }
                }
            });

            // --- New: AJAX actions for Send Email, Send WhatsApp, Resend Registration Link ---
            // Build route templates (replace VOUCHER_ID at runtime)
            var sendEmailUrlTemplate = "{{ route('admin.vouchers.send', ['voucher_id' => 'VOUCHER_ID']) }}";
            var sendWhatsAppUrlTemplate =
                "{{ route('admin.vouchers.send-whatsapp', ['voucher_id' => 'VOUCHER_ID']) }}";
            var resendRegistrationUrlTemplate =
                "{{ route('admin.vouchers.resend-registration-link', ['voucher_id' => 'VOUCHER_ID']) }}"; 
            var sendRegistrationLinkUrlTemplate =
                "{{ route('admin.vouchers.send-registration-whatsapp', ['voucher_id' => 'VOUCHER_ID']) }}";

            function ajaxPostAction(url, successMsg, $btn, successAction) {
                $btn.prop('disabled', true);
                $.ajax({
                    url: url,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                }).done(function(resp) {
                    // Use reusable success modal if available, otherwise log
                    if (typeof showSuccessMessage === 'function') {
                        try {
                            showSuccessMessage(successAction || 'success', resp.message || successMsg ||
                                'Action completed');
                        } catch (e) {
                            console.log(resp.message || successMsg || 'Action completed');
                        }
                    } else {
                        console.log(resp.message || successMsg || 'Action completed');
                    }
                }).fail(function(xhr) {
                    var msg = 'Error';
                    try {
                        msg = xhr.responseJSON.message || xhr.responseText;
                    } catch (e) {
                        msg = xhr.responseText;
                    }
                    if (typeof showErrorModal === 'function') {
                        try {
                            showErrorModal('Error', msg);
                        } catch (e) {
                            console.error(msg);
                        }
                    } else {
                        console.error(msg);
                    }
                }).always(function() {
                    $btn.prop('disabled', false);
                });
            }

            $(document).on('click', '#send-email-btn', function(e) {
                var id = $(this).data('voucher-id');
                var url = sendEmailUrlTemplate.replace('VOUCHER_ID', id);
                ajaxPostAction(url, 'Email sent successfully', $(this), 'send-email');
            });

            $(document).on('click', '#send-whatsapp-btn', function(e) {
                var id = $(this).data('voucher-id');
                var url = sendWhatsAppUrlTemplate.replace('VOUCHER_ID', id);
                ajaxPostAction(url, 'WhatsApp sent successfully', $(this), 'send-whatsapp');
            });

            $(document).on('click', '#send-registration-link-btn', function(e) {
                var id = $(this).data('voucher-id');
                // Use the resendRegistrationUrlTemplate so the action sends both email and WhatsApp
                var url = resendRegistrationUrlTemplate.replace('VOUCHER_ID', id);
                ajaxPostAction(url, 'Registration link sent via Email & WhatsApp', $(this),
                    'send-registration-link');
            });

            $(document).on('click', '#resend-registration-btn', function(e) {
                var id = $(this).data('voucher-id');
                var url = resendRegistrationUrlTemplate.replace('VOUCHER_ID', id);
                ajaxPostAction(url, 'Registration link resent', $(this), 'resend-registration');
            });

        });
    </script>
    @include('admin.partials.modals.success-error-modals')
@endpush

@push('scripts')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        // Initialize flatpickr time pickers in 24-hour mode for consistent cross-browser behavior
        (function() {
            try {
                if (typeof flatpickr !== 'undefined') {
                    flatpickr('.ride-time-from, .ride-time-to', {
                        enableTime: true,
                        noCalendar: true,
                        dateFormat: 'H:i',
                        time_24hr: true,
                        defaultHour: 0,
                        defaultMinute: 0,
                        allowInput: true
                    });
                }
            } catch (e) {
                console.warn('flatpickr init failed:', e);
            }
        })();
    </script>
@endpush
