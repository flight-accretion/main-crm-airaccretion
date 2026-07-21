@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
                Registration</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                    href="javascript:void(0);">
                    Leads
                    <i
                        class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
                aria-current="page">
                Registeration
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

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <!-- <div class="download-data text-right mb-3">
                        <button type="button" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave"> Send Mail for registration link <i class="ri-link"></i></button>
                        <button type="button" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave"> Send Mail To Customer <i class="ri-whatsapp-line"></i></button>
                        <button type="button" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave"> Send Mail To Vendor <i class="ri-whatsapp-line"></i></button>
                        <button type="button" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave"> Print <i class="ri-printer-line"></i></button>
                    </div> -->
            <form action="{{ route('admin.vouchers.store', $lead->id) }}" method="POST" enctype="multipart/form-data">
                @csrf

                <!-- Client Information -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Client Information</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Name</label>
                                <p class="text-gray-800 dark:text-white">{{ $client->name ?? 'N/A' }}</p>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                <p class="text-gray-800 dark:text-white">{{ $client->email ?? 'N/A' }}</p>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone No</label>
                                <p class="text-gray-800 dark:text-white">{{ $client->contact_number ?? 'N/A' }}</p>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Pending Amount</label>
                                <p class="text-gray-800 dark:text-white">{{ number_format($lead->pending_amount ?? 0, 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Service Details -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Service Details</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="vendor_id" class="ti-form-label mb-0">Vendor Name</label>
                                <select class="js-example-basic-multiple w-full" name="vendor_id[]" id="vendor_id" multiple>
                                    @foreach ($vendors as $vendor)
                                        <option value="{{ $vendor->id }}"
                                            data-products="{{ implode(',', $vendor->product_ids ?? []) }}">
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('vendor_id')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="products" class="ti-form-label mb-0">Products</label>
                                <select class="js-example-basic-multiple w-full" name="products[]" id="products" multiple>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}"
                                            data-is-private="{{ $product->is_private ? 'true' : 'false' }}"
                                            data-vendor-id="{{ $product->vendor_id }}"
                                            data-services="{{ implode(',', $product->services->pluck('id')->toArray()) }}"
                                            selected>{{ $product->product }}@if ($product->is_private)
                                                (Private)
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('products')
                                    <span class="text-red-500 text-sm">{{ $message }}</span>
                                @enderror
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Services -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Services</h5>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="" class="table display responsive nowrap services-table" width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">

                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">Service</th>
                                        <th data-priority="3">Amount</th>
                                        <th data-priority="4">Service</th>
                                        <th data-priority="5">Amount</th>
                                        <th data-priority="6"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>

                                        <td>1</td>
                                        <td>
                                            <select class="ti-form-select rounded-sm form-control-sm service-dropdown"
                                                name="services[0][service_1]" data-service-type="regular">
                                                <option value="">Select Service</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}"
                                                        data-extra-services="{{ implode(',', $service->extraServices->pluck('id')->toArray()) }}"
                                                        data-amount="{{ $service->service_amount ?? 0 }}"
                                                        style="display: none;">
                                                        {{ $service->service }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="services[0][amount_1]" placeholder="0.00" step="0.01">
                                        </td>
                                        <td>
                                            <select class="ti-form-select rounded-sm form-control-sm service-dropdown"
                                                name="services[0][service_2]" data-service-type="regular">
                                                <option value="">Select Service</option>
                                                @foreach ($services as $service)
                                                    <option value="{{ $service->id }}"
                                                        data-extra-services="{{ implode(',', $service->extraServices->pluck('id')->toArray()) }}"
                                                        data-amount="{{ $service->service_amount ?? 0 }}"
                                                        style="display: none;">
                                                        {{ $service->service }}
                                                    </option>
                                                @endforeach
                                            </select>

                                        </td>
                                        <td>
                                            <input type="number" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="services[0][amount_2]" placeholder="0.00" step="0.01">
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-primary/10">
                                    <tr>
                                        <td colspan="7" class="text-end"><i
                                                class="ri-add-line text-green-500 cursor-pointer add-services-row"></i>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Extra Services -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Extra Services</h5>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="" class="table display responsive nowrap extra-services-table"
                                width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">

                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">Extra Service</th>
                                        <th data-priority="3">Amount</th>
                                        <th data-priority="4">Extra Service</th>
                                        <th data-priority="5">Amount</th>
                                        <th data-priority="6"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>

                                        <td>1</td>
                                        <td>
                                            <select
                                                class="ti-form-select rounded-sm form-control-sm extra-service-dropdown"
                                                name="extra_services[0][service_1]" data-service-type="extra">
                                                <option value="">Select Extra Service</option>
                                                @foreach ($extraServices as $extraService)
                                                    <option value="{{ $extraService->id }}"
                                                        data-service-id="{{ $extraService->service_id }}"
                                                        data-amount="{{ $extraService->extra_service_amount ?? 0 }}"
                                                        style="display: none;">
                                                        {{ $extraService->extra_service }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="extra_services[0][amount_1]" placeholder="0.00" step="0.01">
                                        </td>
                                        <td>
                                            <select
                                                class="ti-form-select rounded-sm form-control-sm extra-service-dropdown"
                                                name="extra_services[0][service_2]" data-service-type="extra">
                                                <option value="">Select Extra Service</option>
                                                @foreach ($extraServices as $extraService)
                                                    <option value="{{ $extraService->id }}"
                                                        data-service-id="{{ $extraService->service_id }}"
                                                        data-amount="{{ $extraService->extra_service_amount ?? 0 }}"
                                                        style="display: none;">
                                                        {{ $extraService->extra_service }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="extra_services[0][amount_2]" placeholder="0.00" step="0.01">
                                        </td>
                                        <td></td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-primary/10">
                                    <tr>
                                        <td colspan="7" class="text-end"><i
                                                class="ri-add-line text-green-500 cursor-pointer add-extra-services-row"></i>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Operation Team -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Operation Team</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="operation_team_id" class="ti-form-label mb-0">Operation Team Name</label>
                                <select class="ti-form-select rounded-sm form-control-sm" name="operation_team_id"
                                    id="operation_team_id" required>
                                    <option value="">Select Operation Team Member</option>
                                    @foreach ($operationTeam as $member)
                                        <option value="{{ $member->id }}"
                                            data-contact="{{ $member->contact_number ?? '' }}">{{ $member->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="operation_team_contact" class="ti-form-label mb-0">Operation Team Contact
                                    No</label>
                                <input type="tel" id="otContact"
                                    class="ti-form-input w-full rounded-sm form-control-sm intl-phone-input iti bg-gray-100 dark:bg-gray-700"
                                    name="operation_team_contact" placeholder="Contact will be auto-filled"
                                    style="background-color: #f8f9fa;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rides Details -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Rides Details</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="ride_date" class="ti-form-label mb-0">Ride Date</label>
                                <div class="form-group">
                                    <div class="input-group">
                                        <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                class="ri-calendar-line"></i> </div>
                                        <input type="text"
                                            class="form-control ti-form-input w-full rounded-sm form-control-sm"
                                            id="ride_date" name="ride_date" placeholder="Choose date with time" required>
                                    </div>
                                </div>
                            </div>
                            <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="" class="ti-form-label mb-0">Ride Time From - To</label>
                                <div class="grid grid-cols-12 sm:gap-6 items-center">
                                    <div
                                        class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12 justify-center">
                                        <label class="form-check-label mb-0" for="is_tba">TBA</label>
                                        <input class="form-check-input" type="checkbox" name="is_tba" id="is_tba"
                                            value="1">
                                    </div>
                                    <div class="xl:col-span-5 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                        class="ri-time-line"></i> </div>
                                                <input type="text"
                                                    class="form-control ti-form-input w-full rounded-sm form-control-sm"
                                                    id="ride_from_time" name="ride_from_time" placeholder="14:30">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="xl:col-span-5 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <div class="form-group">
                                            <div class="input-group">
                                                <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                        class="ri-time-line"></i> </div>
                                                <input type="text"
                                                    class="form-control ti-form-input w-full rounded-sm form-control-sm"
                                                    id="ride_to_time" name="ride_to_time" placeholder="16:00">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="total_time" class="ti-form-label mb-0">Total Time (In Hours)</label>
                                <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                    id="total_time" name="total_time" placeholder="00:00" readonly>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="ride_address" class="ti-form-label mb-0">Ride Address</label>
                                <select class="ti-form-select rounded-sm form-control-sm" name="ride_address"
                                    id="ride_address" required>
                                    <option value="">Select Service Address</option>
                                    @foreach ($serviceAddresses as $serviceAddress)
                                        <option value="{{ $serviceAddress->address }}"
                                            data-contact="{{ $serviceAddress->contact_number }}"
                                            data-person="{{ $serviceAddress->contact_person_name }}"
                                            data-service="{{ $serviceAddress->service->service ?? '' }}"
                                            data-product="{{ $serviceAddress->product->product ?? '' }}">
                                            {{ $serviceAddress->address }} {{ $serviceAddress->city->name }}
                                            <!-- @if ($serviceAddress->service)
    ({{ $serviceAddress->service->service }})
    @endif
                                                    @if ($serviceAddress->product)
    - {{ $serviceAddress->product->product }}
    @endif -->
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="ride_contact" class="ti-form-label mb-0">Ride Contact No</label>
                                <input type="tel" id="rideContact"
                                    class="ti-form-input intl-phone-input iti w-full rounded-sm form-control-sm"
                                    name="ride_contact" placeholder="" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Multiple Trip Add -->
                <div class="box" id="multipleTripSection" style="display: none;">
                    <div class="box-header">
                        <h5 class="box-title">Multiple Trip Add</h5>
                        <div class="text-sm text-blue-600 mt-1">
                            <i class="ri-information-line"></i> This section is available because you have selected private
                            products
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="" class="table display responsive nowrap multipletrip-table"
                                width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">

                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">From Address</th>
                                        <th data-priority="3">From Vendor</th>
                                        <th data-priority="4">To Address</th>
                                        <th data-priority="5">To Vendor</th>
                                        <th data-priority="6">TBA</th>
                                        <th data-priority="7">To Ride Date</th>
                                        <th data-priority="8">In Ride Time</th>
                                        <th data-priority="9"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>

                                        <td>1</td>
                                        <td>
                                            <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="trips[0][from_address]" placeholder="">
                                        </td>
                                        <td>
                                            <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="trips[0][from_vendor]" placeholder="">
                                        </td>
                                        <td>
                                            <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="trips[0][to_address]" placeholder="">
                                        </td>
                                        <td>
                                            <input type="text" class="ti-form-input w-full rounded-sm form-control-sm"
                                                name="trips[0][to_vendor]" placeholder="">
                                        </td>
                                        <td class="text-center">
                                            <input class="form-check-input" type="checkbox" name="trips[0][is_tba]"
                                                value="1">
                                        </td>
                                        <td>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                            class="ri-calendar-line"></i> </div>
                                                    <input type="text" class="form-control form-control-sm"
                                                        name="trips[0][ride_date]" placeholder="Choose date with time">
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <div class="input-group-text text-[#8c9097] dark:text-white/50"> <i
                                                            class="ri-time-line"></i> </div>
                                                    <input type="text" class="form-control form-control-sm"
                                                        name="trips[0][ride_time]" placeholder="Choose time">
                                                </div>
                                            </div>
                                        </td>
                                        <td><i class="ri-close-line text-red-500 cursor-pointer remove-row"></i></td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-primary/10">
                                    <tr>
                                        <td colspan="10" class="text-end"><i
                                                class="ri-add-line text-green-500 cursor-pointer add-multipletrip-row"></i>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Person Detail -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Personal Details</h5>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="" class="table display responsive nowrap persondetail-table"
                                width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">

                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">Person Name</th>
                                        <th data-priority="3">Age</th>
                                        <th data-priority="4">Traveller Type</th>
                                        <th data-priority="5">Weight</th>
                                        <th data-priority="6">Front Document</th>
                                        <th data-priority="7">#</th>
                                        <th data-priority="8">Back Document</th>
                                        <th data-priority="9">#</th>
                                        <th data-priority="10"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (range(1, $lead->number_of_passengers ?? 1) as $index)
                                        <tr>

                                            <td>{{ $index }}</td>
                                            <td>
                                                <input type="text"
                                                    class="ti-form-input w-full rounded-sm form-control-sm"
                                                    name="passengers[{{ $index }}][name]" placeholder="" required>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    class="ti-form-input w-full rounded-sm form-control-sm"
                                                    name="passengers[{{ $index }}][age]" placeholder="">
                                            </td>
                                            <td>
                                                <select class="ti-form-select rounded-sm form-control-sm"
                                                    name="passengers[{{ $index }}][traveller_type]">
                                                    <option value="">Select Type</option>
                                                    <option value="Adult">Adult</option>
                                                    <option value="Child">Child</option>
                                                    <option value="Infant">Infant</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number"
                                                    class="ti-form-input w-full rounded-sm form-control-sm"
                                                    name="passengers[{{ $index }}][weight]" placeholder=""
                                                    step="0.01">
                                            </td>
                                            <td>
                                                <input type="file"
                                                    name="passengers[{{ $index }}][front_document]"
                                                    class="block w-full border border-gray-200 focus:shadow-sm dark:focus:shadow-white/10 rounded-sm text-sm focus:z-10 focus:outline-0 focus:border-gray-200 dark:focus:border-white/10 dark:border-white/10 dark:text-white/50
                                            file:border-0
                                            file:bg-light file:me-4
                                            file:py-2 file:px-4
                                            dark:file:bg-black/20 dark:file:text-white/50">
                                            <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                            </td>
                                            <td>
                                                <span class="document-preview"></span>
                                            </td>
                                            <td>
                                                <input type="file"
                                                    name="passengers[{{ $index }}][back_document]"
                                                    class="block w-full border border-gray-200 focus:shadow-sm dark:focus:shadow-white/10 rounded-sm text-sm focus:z-10 focus:outline-0 focus:border-gray-200 dark:focus:border-white/10 dark:border-white/10 dark:text-white/50
                                            file:border-0
                                            file:bg-light file:me-4
                                            file:py-2 file:px-4
                                            dark:file:bg-black/20 dark:file:text-white/50">
                                            <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                            </td>
                                            <td>
                                                <span class="document-preview"></span>
                                            </td>
                                            <td>
                                                @if ($index > 1)
                                                    <i class="ri-close-line text-red-500 cursor-pointer remove-row"></i>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-primary/10">
                                    <tr>
                                        <td colspan="11" class="text-end"><i
                                                class="ri-add-line text-green-500 cursor-pointer add-persondetail-row"></i>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>



                <!-- Extra Ticket Upload -->
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Extra Ticket Upload</h5>
                    </div>
                    <div class="box-body">
                        <div class="grid grid-cols-12 sm:gap-6">
                            <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <input type="file" name="extra_ticket" class="filepond basic-filepond"
                                    data-allow-reorder="true" data-max-file-size="3MB" data-max-files="1">
                            </div>
                        </div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="ti-btn ti-btn-primary-full ti-custom-validate-btn">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Function to initialize flatpickr
            function initializeFlatpickr() {
                // Initialize time pickers
                $('#ride_from_time, #ride_to_time').each(function() {
                    if (!this._flatpickr) {
                        flatpickr(this, {
                            enableTime: true,
                            noCalendar: true,
                            dateFormat: "H:i",
                            time_24hr: true,
                            locale: {
                                time_24hr: true
                            },
                            onChange: function(selectedDates, dateStr, instance) {
                                calculateTotalTime();
                            }
                        });
                    }
                });

                // Initialize date and time pickers
                $('#ride_date').each(function() {
                    if (!this._flatpickr) {
                        flatpickr(this, {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                        });
                    }
                });
            }

            // Function to calculate total time
            function calculateTotalTime() {
                const fromTime = $('#ride_from_time').val();
                const toTime = $('#ride_to_time').val();

                if (fromTime && toTime) {
                    // Parse time strings (24-hour format)
                    const [fromHour, fromMin] = fromTime.split(':').map(Number);
                    const [toHour, toMin] = toTime.split(':').map(Number);

                    // Convert to minutes since midnight
                    let fromMinutes = fromHour * 60 + fromMin;
                    let toMinutes = toHour * 60 + toMin;

                    // Handle overnight case (if to time is before from time)
                    if (toMinutes < fromMinutes) {
                        toMinutes += 24 * 60; // Add 24 hours in minutes
                    }

                    // Calculate difference in minutes
                    const diffInMinutes = toMinutes - fromMinutes;

                    // Convert to hours and minutes
                    const hours = Math.floor(diffInMinutes / 60);
                    const minutes = diffInMinutes % 60;

                    // Format as HH:MM
                    const totalTime = String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');

                    // Set the total time field
                    $('#total_time').val(totalTime);
                }
            }

            // Initialize flatpickr on page load
            initializeFlatpickr();

            // Initialize flatpickr for multiple trip fields
            function initializeMultipleTripFlatpickr() {
                // Initialize date pickers for multiple trip fields (date only, no time)
                $('[name$="[ride_date]"]').each(function() {
                    if (!this._flatpickr) {
                        flatpickr(this, {
                            enableTime: false,
                            dateFormat: "Y-m-d",
                        });
                    }
                });

                // Initialize time pickers for multiple trip fields
                $('[name$="[ride_time]"]').each(function() {
                    if (!this._flatpickr) {
                        flatpickr(this, {
                            enableTime: true,
                            noCalendar: true,
                            dateFormat: "H:i",
                            time_24hr: true,
                            locale: {
                                time_24hr: true
                            }
                        });
                    }
                });
            }

            // Initialize multiple trip flatpickr on page load
            initializeMultipleTripFlatpickr();

            // Add additional event listeners for time calculation (fallback)
            $('#ride_from_time, #ride_to_time').on('change blur keyup', function() {
                calculateTotalTime();
            });

            $('.js-example-basic-multiple').select2({
                placeholder: "Select options",
                allowClear: true
            });

            // Function to handle vendor-product relationship
            function updateVendorProductRelationship() {
                // Initialize relationship maps from server data
                const productServiceMap = @json($productServiceMap ?? []);
                // const serviceExtraServiceMap = @json($serviceExtraServiceMap ?? []);
                const productVendorMap = @json($productVendorMap ?? []);
                const vendorProductMap = @json($vendorProductMap ?? []);

                // Make service and extra service amount maps available globally
                window.serviceAmountMap = @json($serviceAmountMap ?? []);
                window.extraServiceAmountMap = @json($extraServiceAmountMap ?? []);

                // Function to update services based on selected products
                function updateServicesBasedOnProducts() {
                    let selectedProducts = $('#products').val() || [];
                    let availableServices = new Set();

                    // Get all services for selected products
                    selectedProducts.forEach(function(productId) {
                        if (productServiceMap[productId]) {
                            productServiceMap[productId].forEach(function(serviceId) {
                                availableServices.add(serviceId.toString());
                            });
                        }
                    });

                    // Update service dropdowns
                    $('.service-dropdown option').each(function() {
                        const $option = $(this);
                        const serviceId = $option.val();

                        if (serviceId === '') {
                            $option.show(); // Always show the "Select Service" option
                        } else if (availableServices.has(serviceId)) {
                            $option.show();
                        } else {
                            $option.hide();
                            // Deselect if currently selected
                            if ($option.is(':selected')) {
                                $option.prop('selected', false);
                            }
                        }
                    });

                    // Trigger change to update extra services
                    $('.service-dropdown').trigger('change');
                }

                // Function to update extra services based on selected services
                function updateExtraServicesBasedOnServices() {
                    let selectedServices = [];
                    $('.service-dropdown').each(function() {
                        const val = $(this).val();
                        if (val) selectedServices.push(val);
                    });

                    let availableExtraServices = new Set();

                    // selectedServices.forEach(function(serviceId) {
                    //     if (serviceExtraServiceMap[serviceId]) {
                    //         serviceExtraServiceMap[serviceId].forEach(function(extraServiceId) {
                    //             availableExtraServices.add(extraServiceId.toString());
                    //         });
                    //     }
                    // });

                    // Update extra service dropdowns
                    $('.extra-service-dropdown option').each(function() {
                        const $option = $(this);
                        const extraServiceId = $option.val();

                        if (extraServiceId === '') {
                            $option.show(); // Always show the "Select Extra Service" option
                        } else if (availableExtraServices.has(extraServiceId)) {
                            $option.show();
                        } else {
                            $option.hide();
                            // Deselect if currently selected
                            if ($option.is(':selected')) {
                                $option.prop('selected', false);
                            }
                        }
                    });
                }

                // Function to update vendors based on selected products
                function updateVendorsBasedOnProducts() {
                    let selectedProducts = $('#products').val() || [];
                    let relatedVendors = new Set();

                    // Get vendors for selected products
                    selectedProducts.forEach(function(productId) {
                        if (productVendorMap[productId]) {
                            productVendorMap[productId].forEach(function(vendorId) {
                                if (vendorId) relatedVendors.add(vendorId.toString());
                            });
                        }
                    });

                    // Auto-select related vendors
                    let currentVendors = $('#vendor_id').val() || [];
                    let newVendors = [...new Set([...currentVendors, ...Array.from(relatedVendors)])];
                    $('#vendor_id').val(newVendors).trigger('change');
                }

                // Function to update products based on selected vendors
                function updateProductsBasedOnVendors() {
                    let selectedVendors = $('#vendor_id').val() || [];
                    let relatedProducts = new Set();

                    selectedVendors.forEach(function(vendorId) {
                        if (vendorProductMap[vendorId]) {
                            vendorProductMap[vendorId].forEach(function(productId) {
                                if (productId) relatedProducts.add(productId.toString());
                            });
                        }
                    });

                    // Auto-select related products
                    let currentProducts = $('#products').val() || [];
                    let newProducts = [...new Set([...currentProducts, ...Array.from(relatedProducts)])];
                    $('#products').val(newProducts).trigger('change');
                }

                // Event handlers
                $('#products').on('select2:select select2:unselect', function(e) {
                    setTimeout(function() {
                        updateVendorsBasedOnProducts();
                        updateServicesBasedOnProducts();
                    }, 100);
                });

                $('#vendor_id').on('select2:select select2:unselect', function(e) {
                    setTimeout(function() {
                        updateProductsBasedOnVendors();
                        updateServicesBasedOnProducts();
                    }, 100);
                });

                // Service dropdown change handler
                $(document).on('change', '.service-dropdown', function() {
                    updateExtraServicesBasedOnServices();
                });

                // Initial load
                updateVendorsBasedOnProducts();
                updateServicesBasedOnProducts();
            }

            // Initialize vendor-product relationship
            updateVendorProductRelationship();

            // Operation Team Contact Auto-Fill
            function handleOperationTeamSelection() {
                $('#operation_team_id').on('change', function() {
                    const selectedOption = $(this).find('option:selected');
                    const contactNumber = selectedOption.data('contact') || '';

                    if (contactNumber) {
                        // Try multiple approaches to set the contact number
                        const otContactInput = document.querySelector('#otContact');

                        // Method 1: Try using intlTelInput if available
                        try {
                            if (window.intlTelInputGlobals && window.intlTelInputGlobals.getInstance) {
                                const iti = window.intlTelInputGlobals.getInstance(otContactInput);
                                if (iti) {
                                    iti.setNumber(contactNumber);
                                } else {
                                    throw new Error('intlTelInput instance not found');
                                }
                            } else {
                                throw new Error('intlTelInputGlobals not available');
                            }
                        } catch (error) {
                            // Method 2: Direct value setting as fallback
                            $('#otContact').val(contactNumber);

                            // Method 3: Trigger input event to ensure the value is registered
                            $('#otContact').trigger('input').trigger('change');
                        }
                    } else {
                        // Clear the field if no contact number
                        $('#otContact').val('');
                    }
                });
            }

            // Initialize operation team contact functionality with multiple retries
            let initAttempts = 0;
            const maxAttempts = 5;

            function initOperationTeamContact() {
                initAttempts++;

                const otContactInput = document.querySelector('#otContact');
                if (otContactInput && window.intlTelInput) {
                    // Check if intlTelInput is properly initialized
                    const hasIntl = otContactInput.classList.contains('iti__tel-input') ||
                        otContactInput.parentElement.classList.contains('iti');

                    if (hasIntl || initAttempts >= maxAttempts) {
                        handleOperationTeamSelection();
                    } else if (initAttempts < maxAttempts) {
                        setTimeout(initOperationTeamContact, 200);
                    }
                } else if (initAttempts < maxAttempts) {
                    setTimeout(initOperationTeamContact, 200);
                } else {
                    handleOperationTeamSelection();
                }
            }

            // Start initialization
            setTimeout(initOperationTeamContact, 100);

            function populateFollowupData() {
                @if ($followupServices->isNotEmpty())
                    let followupServices = @json($followupServices->pluck('id')->toArray());
                    let serviceIndex = 0;
                    followupServices.forEach(function(serviceId) {
                        if (serviceIndex < 2) { // Only populate first 2 service slots
                            let fieldName = serviceIndex === 0 ? 'service_1' : 'service_2';
                            $(`select[name="services[0][${fieldName}]"] option[value="${serviceId}"]`)
                                .show();
                            $(`select[name="services[0][${fieldName}]"]`).val(serviceId);
                            serviceIndex++;
                        }
                    });
                @endif

                @if ($followupExtraServices->isNotEmpty())
                    let followupExtraServices = @json($followupExtraServices->pluck('id')->toArray());
                    let extraServiceIndex = 0;
                    followupExtraServices.forEach(function(extraServiceId) {
                        if (extraServiceIndex < 2) { // Only populate first 2 extra service slots
                            let fieldName = extraServiceIndex === 0 ? 'service_1' : 'service_2';
                            $(`select[name="extra_services[0][${fieldName}]"] option[value="${extraServiceId}"]`)
                                .show();
                            $(`select[name="extra_services[0][${fieldName}]"]`).val(extraServiceId);
                            extraServiceIndex++;
                        }
                    });
                @endif

                @if ($latestFollowup && $latestFollowup->vendor_id)
                    // Auto-select vendor from followup
                    let followupVendorId = '{{ $latestFollowup->vendor_id }}';
                    if (followupVendorId) {
                        let currentVendors = $('#vendor_id').val() || [];
                        if (!currentVendors.includes(followupVendorId)) {
                            currentVendors.push(followupVendorId);
                            $('#vendor_id').val(currentVendors).trigger('change');
                        }
                    }
                @endif
            }

            // Call after relationships are set up
            setTimeout(populateFollowupData, 500);

            // Function to check if any selected product is private
            function checkPrivateProducts() {
                let hasPrivateProduct = false;

                // Check all selected products
                $('#products option:selected').each(function() {
                    const isPrivateAttr = $(this).data('is-private');
                    // Handle both string and boolean values from PostgreSQL
                    const isPrivate = isPrivateAttr === 'true' || isPrivateAttr === true ||
                        isPrivateAttr === 1;

                    if (isPrivate) {
                        hasPrivateProduct = true;
                    }
                });

                // Show/hide Multiple Trip section based on private products
                if (hasPrivateProduct) {
                    $('#multipleTripSection').slideDown(300);
                    // Add required attributes when section is visible
                    $('#multipleTripSection input[name$="[from_address]"], #multipleTripSection input[name$="[from_vendor]"], #multipleTripSection input[name$="[to_address]"], #multipleTripSection input[name$="[to_vendor]"]')
                        .attr('required', true);
                    // Initialize flatpickr for multiple trip fields when section becomes visible
                    setTimeout(function() {
                        initializeMultipleTripFlatpickr();
                    }, 400);
                } else {
                    $('#multipleTripSection').slideUp(300);
                    // Remove required attributes when section is hidden
                    $('#multipleTripSection input[name$="[from_address]"], #multipleTripSection input[name$="[from_vendor]"], #multipleTripSection input[name$="[to_address]"], #multipleTripSection input[name$="[to_vendor]"]')
                        .removeAttr('required');
                }
            }

            // Initially ensure trip fields don't have required attributes since section is hidden
            $('#multipleTripSection input[name$="[from_address]"], #multipleTripSection input[name$="[from_vendor]"], #multipleTripSection input[name$="[to_address]"], #multipleTripSection input[name$="[to_vendor]"]')
                .removeAttr('required');

            // Check on page load with multiple attempts to ensure Select2 is ready
            setTimeout(function() {
                checkPrivateProducts();
            }, 100);

            setTimeout(function() {
                checkPrivateProducts();
            }, 500);

            setTimeout(function() {
                checkPrivateProducts();
            }, 1000);

            // Check when products selection changes (Select2 specific events)
            $('#products').on('select2:select select2:unselect select2:close', function(e) {
                // Small delay to ensure the selection has been processed
                setTimeout(function() {
                    checkPrivateProducts();
                }, 100);
            });

            // Check when vendor selection changes
            $('#vendor_id').on('select2:select select2:unselect select2:close', function(e) {
                // Small delay to ensure the selection has been processed
                setTimeout(function() {
                    checkPrivateProducts();
                }, 100);
            });

            // Fallback for regular change event
            $('#products').on('change', function() {
                setTimeout(function() {
                    checkPrivateProducts();
                }, 100);
            });

            // Handle ride address selection to auto-populate contact
            $('#ride_address').on('change', function() {
                const selectedOption = $(this).find('option:selected');
                const contactNumber = selectedOption.data('contact');
                const contactPerson = selectedOption.data('person');

                if (contactNumber) {
                    // Set the ride contact field
                    $('#rideContact').val(contactNumber);

                    // If there's a contact person, you could show it in a tooltip or info
                    if (contactPerson) {
                        $(this).attr('title', 'Contact Person: ' + contactPerson);
                    }
                }
            });

            // Add new row for services
            $(document).on('click', '.add-services-row', function() {
                let $table = $('.services-table');
                let $firstRow = $table.find('tbody tr:first');
                let $newRow = $firstRow.clone(true);
                let currentRowCount = $table.find('tbody tr').length;
                let newRowIndex = currentRowCount;

                // Update the name attributes with new index
                $newRow.find('input, select').each(function() {
                    let name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + newRowIndex + ']');
                        $(this).attr('name', name);
                        $(this).val('');
                    }
                });

                // Add remove button
                $newRow.find('td:last').html(
                    '<i class="ri-close-line text-red-500 cursor-pointer remove-row"></i>');

                // Update serial number
                $newRow.find('td:nth-child(2)').text(currentRowCount + 1);

                $table.find('tbody').append($newRow);
            });

            // Add new row for extra services
            $(document).on('click', '.add-extra-services-row', function() {
                let $table = $('.extra-services-table');
                let $firstRow = $table.find('tbody tr:first');
                let $newRow = $firstRow.clone(true);
                let currentRowCount = $table.find('tbody tr').length;
                let newRowIndex = currentRowCount;

                // Update the name attributes with new index
                $newRow.find('input, select').each(function() {
                    let name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + newRowIndex + ']');
                        $(this).attr('name', name);
                        $(this).val('');
                    }
                });

                // Add remove button
                $newRow.find('td:last').html(
                    '<i class="ri-close-line text-red-500 cursor-pointer remove-row"></i>');

                // Update serial number
                $newRow.find('td:nth-child(2)').text(currentRowCount + 1);

                $table.find('tbody').append($newRow);
            });

            // Add new row for multiple trips
            $(document).on('click', '.add-multipletrip-row', function() {
                let $table = $('.multipletrip-table');
                let $firstRow = $table.find('tbody tr:first');
                let $newRow = $firstRow.clone(true);
                let currentRowCount = $table.find('tbody tr').length;
                let newRowIndex = currentRowCount;

                // Update the name attributes with new index
                $newRow.find('input, select').each(function() {
                    let name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + newRowIndex + ']');
                        $(this).attr('name', name);
                        $(this).val('');
                    }
                });

                // Reset checkbox
                $newRow.find('[type="checkbox"]').prop('checked', false);

                // Update serial number
                $newRow.find('td:nth-child(2)').text(currentRowCount + 1);

                // Add remove button
                $newRow.find('td:last').html(
                    '<i class="ri-close-line text-red-500 cursor-pointer remove-row"></i>');

                // Add required attributes if section is visible
                if ($('#multipleTripSection').is(':visible')) {
                    $newRow.find(
                        'input[name$="[from_address]"], input[name$="[from_vendor]"], input[name$="[to_address]"], input[name$="[to_vendor]"]'
                    ).attr('required', true);
                }

                $table.find('tbody').append($newRow);

                // Initialize flatpickr for new row
                $newRow.find('[name$="[ride_date]"]').each(function() {
                    if (this._flatpickr) {
                        this._flatpickr.destroy();
                    }
                    flatpickr(this, {
                        enableTime: false,
                        dateFormat: "Y-m-d",
                    });
                });

                $newRow.find('[name$="[ride_time]"]').each(function() {
                    if (this._flatpickr) {
                        this._flatpickr.destroy();
                    }
                    flatpickr(this, {
                        enableTime: true,
                        noCalendar: true,
                        dateFormat: "H:i",
                        time_24hr: true,
                        locale: {
                            time_24hr: true
                        }
                    });
                });
            });

            // Add new row for person details
            $(document).on('click', '.add-persondetail-row', function() {
                let $table = $('.persondetail-table');
                let $firstRow = $table.find('tbody tr:first');
                let $newRow = $firstRow.clone(true);
                let currentRowCount = $table.find('tbody tr').length;
                let newRowIndex = currentRowCount;

                // Update the name attributes with new index
                $newRow.find('input, select').each(function() {
                    let name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[\d+\]/, '[' + newRowIndex + ']');
                        $(this).attr('name', name);
                        $(this).val('');
                    }
                });

                // Clear file inputs and previews
                $newRow.find('[type="file"]').val('');
                $newRow.find('.document-preview').html('');

                // Add remove button
                $newRow.find('td:last').html(
                    '<i class="ri-close-line text-red-500 cursor-pointer remove-row"></i>');

                // Update serial number
                $newRow.find('td:nth-child(2)').text(currentRowCount + 1);

                $table.find('tbody').append($newRow);
            });

            // Remove row handler for all tables
            $(document).on('click', '.remove-row', function() {
                let $table = $(this).closest('table');
                let $rows = $table.find('tbody tr');

                if ($rows.length > 1) {
                    $(this).closest('tr').remove();

                    // Update serial numbers and reindex name attributes
                    $table.find('tbody tr').each(function(index) {
                        $(this).find('td:nth-child(2)').text(index + 1);

                        // Reindex name attributes for all dynamic tables
                        if ($table.hasClass('services-table') || $table.hasClass(
                                'extra-services-table') ||
                            $table.hasClass('multipletrip-table') || $table.hasClass(
                                'persondetail-table')) {
                            $(this).find('input, select').each(function() {
                                let name = $(this).attr('name');
                                if (name) {
                                    // Replace the index in brackets with the new index
                                    name = name.replace(/\[\d+\]/, '[' + index + ']');
                                    $(this).attr('name', name);
                                }
                            });
                        }
                    });
                }
            });

            // Initialize FilePond for extra ticket upload
            if (typeof FilePond !== 'undefined') {
                const extraTicketInput = document.querySelector('input[name="extra_ticket"]');
                if (extraTicketInput) {
                    FilePond.create(extraTicketInput, {
                        allowMultiple: false,
                        maxFiles: 1,
                        maxFileSize: '3MB',
                        labelIdle: 'Drag & Drop your files or <span class="filepond--label-action">Browse</span>'
                    });
                }
            } else {
                console.warn('FilePond is not loaded. Regular file input will be used.');
                // Remove filepond classes to show regular file input
                $('input[name="extra_ticket"]').removeClass('filepond basic-filepond').addClass(
                    'block w-full border border-gray-200 focus:shadow-sm dark:focus:shadow-white/10 rounded-sm text-sm focus:z-10 focus:outline-0 focus:border-gray-200 dark:focus:border-white/10 dark:border-white/10 dark:text-white/50 file:border-0 file:bg-light file:me-4 file:py-2 file:px-4 dark:file:bg-black/20 dark:file:text-white/50'
                );
            }

            // Initialize phone inputs
            if (window.intlTelInput) {
                const rideContactInput = document.querySelector("#rideContact");
                const otContactInput = document.querySelector("#otContact");

                if (rideContactInput) {
                    window.intlTelInput(rideContactInput, {
                        separateDialCode: true,
                        initialCountry: "in",
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                    });
                }

                if (otContactInput) {
                    // Initialize intlTelInput for operation team contact to maintain country code display
                    window.intlTelInput(otContactInput, {
                        separateDialCode: true,
                        initialCountry: "in",
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                    });
                }

            }

            // Form submission handler to prevent validation errors on hidden fields
            $('form').on('submit', function(e) {
                // Remove required attributes from hidden fields
                $('input[required], select[required], textarea[required]').each(function() {
                    var $field = $(this);
                    if (!$field.is(':visible') || $field.closest('#multipleTripSection').is(
                            ':hidden')) {
                        $field.removeAttr('required');
                    }
                });
            });

            // Handle service selection to auto-populate amounts
            $(document).on('change', '.service-dropdown', function() {
                const $select = $(this);
                const selectedOption = $select.find('option:selected');
                const serviceId = selectedOption.val();

                // Try to get amount from data attribute first, then from server mapping
                let amount = selectedOption.data('amount') || '';

                // Fallback to server mapping if needed
                if (!amount && serviceId && window.serviceAmountMap && window.serviceAmountMap[serviceId]) {
                    amount = window.serviceAmountMap[serviceId];
                }

                // Find the corresponding amount field
                const $amountField = $select.closest('td').next('td').find('input[type="number"]');

                if (amount && $amountField.length) {
                    $amountField.val(amount);
                } else if (!serviceId && $amountField.length) {
                    // Clear amount field if no service selected
                    $amountField.val('');
                }
            });

            // Handle extra service selection to auto-populate amounts
            $(document).on('change', '.extra-service-dropdown', function() {
                const $select = $(this);
                const selectedOption = $select.find('option:selected');
                const extraServiceId = selectedOption.val();

                // Try to get amount from data attribute first, then from server mapping
                let amount = selectedOption.data('amount') || '';

                // Fallback to server mapping if needed
                if (!amount && extraServiceId && window.extraServiceAmountMap && window
                    .extraServiceAmountMap[extraServiceId]) {
                    amount = window.extraServiceAmountMap[extraServiceId];
                }

                // Find the corresponding amount field
                const $amountField = $select.closest('td').next('td').find('input[type="number"]');

                if (amount && $amountField.length) {
                    $amountField.val(amount);
                } else if (!extraServiceId && $amountField.length) {
                    // Clear amount field if no extra service selected
                    $amountField.val('');
                }
            });
        });
    </script>
@stop
