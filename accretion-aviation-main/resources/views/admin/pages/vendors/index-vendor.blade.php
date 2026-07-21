@extends('admin.layouts.header')
@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">

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

    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="add-vendor-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <i class="ri-store-2-line"></i>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage Vendors</h5>
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="add-vendor-form">
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
                                                Add Vendor
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-vendor-form"
                            class="hs-accordion-content w-full overflow-hidden transition-[height] duration-300 {{ $errors->any() ? '' : 'hidden' }}"
                            aria-labelledby="add-vendor-accordion">
                            <form action="{{ route('admin.vendors.store') }}" method="POST" enctype="multipart/form-data"
                                id="add-vendor-form-element">
                                @csrf
                                <div class="box-body">
                                    <div class="grid lg:grid-cols-4 gap-6">
                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Vendor Name<span class="text-danger">*</span></label>
                                            <input type="text" name="name"
                                                class="ti-form-input rounded-sm form-control-sm" value="{{ old('name') }}">
                                            @error('name')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Email Address</label>
                                            <input type="email" name="email"
                                                class="ti-form-input rounded-sm form-control-sm" value="{{ old('email') }}">
                                            @error('email')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Contact Number<span class="text-danger">*</span></label>
                                            <input type="hidden" name="country_code" id="add_country_code"
                                                value="{{ old('country_code') }}">
                                            <input type="tel" name="contact_number" id="add_contact_number"
                                                class="ti-form-input rounded-sm form-control-sm intl-phone-input"
                                                value="{{ old('contact_number') }}">
                                            @error('country_code')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                            @error('contact_number')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Country</label>
                                            <select name="country_id" id="add_countrySelect"
                                                class="ti-form-select rounded-sm form-control-sm">
                                                <option value="">Select Country</option>
                                                @foreach ($countries as $country)
                                                    <option value="{{ $country->id }}"
                                                        {{ old('country_id') == $country->id ? 'selected' : '' }}
                                                        data-phonecode="{{ $country->isd_code }}">
                                                        {{ $country->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('country_id')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">State</label>
                                            <select name="state_id" id="add_stateSelect"
                                                class="ti-form-select rounded-sm form-control-sm">
                                                <option value="">Select State</option>
                                            </select>
                                            @error('state_id')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">City</label>
                                            <select name="city_id" id="add_citySelect"
                                                class="ti-form-select rounded-sm form-control-sm">
                                                <option value="">Select City</option>
                                            </select>
                                            @error('city_id')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Map Link</label>
                                            <input type="url" name="map_link"
                                                class="ti-form-input rounded-sm form-control-sm"
                                                value="{{ old('map_link') }}">
                                            @error('map_link')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2">
                                            <label class="ti-form-label mb-0">Upload</label>
                                            <input type="file" name="profile_image"
                                                accept="image/*,.jpg,.jpeg,.png,.gif,.webp"
                                                class="ti-form-input rounded-sm form-control-sm">
                                                <small class="text-muted">Allowed formats: JPG, PNG, PDF | Max size: 2MB</small>
                                            @error('profile_image')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                        <div class="space-y-2 col-span-2">
                                            <label class="ti-form-label mb-0">Address</label>
                                            <textarea name="address" class="ti-form-input rounded-sm form-control-sm" rows="4">{{ old('address') }}</textarea>
                                            @error('address')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2 col-span-2">
                                            <label class="ti-form-label mb-0">Bank Details</label>
                                            <textarea name="bank_details" class="ti-form-input rounded-sm form-control-sm" rows="4">{{ old('bank_details') }}</textarea>
                                            @error('bank_details')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>

                                        <div class="space-y-2">
                                            <label for="add_service_ids" class="ti-form-label mb-0">Services<span class="text-danger">*</span></label>
                                            <select class="js-example-basic-multiple w-full form-control-sm"
                                                name="service_ids[]" multiple="multiple" id="add_service_ids">
                                                @foreach ($allServices as $service)
                                                    <option value="{{ $service->id }}"
                                                        {{ in_array($service->id, old('service_ids', [])) ? 'selected' : '' }}>
                                                        {{ $service->service }} ({{ $service->service_amount }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('service_ids')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                            @if ($errors->has('service_validation'))
                                                <span class="text-red-500 text-xs">{{ $errors->first('service_validation') }}</span>
                                            @endif

                                        </div>

                                        <div class="space-y-2">
                                            <label for="add_extra_service_ids" class="ti-form-label mb-0">Extra
                                                Services<span class="text-danger">*</span></label>
                                            <select class="js-example-basic-multiple w-full form-control-sm"
                                                name="extra_service_ids[]" multiple="multiple"
                                                id="add_extra_service_ids">
                                                @foreach ($allExtraServices as $extraService)
                                                    <option value="{{ $extraService->id }}"
                                                        {{ in_array($extraService->id, old('extra_service_ids', [])) ? 'selected' : '' }}>
                                                        {{ $extraService->extra_service }}
                                                        ({{ $extraService->extra_service_amount }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('extra_service_ids')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                            {{-- Show combined service/extra service validation message (server-side key: service_validation) --}}
                                            @if ($errors->has('service_validation'))
                                                <span class="text-red-500 text-xs">{{ $errors->first('service_validation') }}</span>
                                            @endif
                                        </div>

                                        <div class="space-y-2">
                                            <label for="add_product_ids" class="ti-form-label mb-0">Products</label>
                                            <select class="js-example-basic-multiple w-full form-control-sm"
                                                name="product_ids[]" multiple="multiple" id="add_product_ids">
                                                @if (old('product_ids'))
                                                    @foreach ($allProducts->whereIn('id', old('product_ids')) as $product)
                                                        <option value="{{ $product->id }}" selected>
                                                            {{ $product->product }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                            @error('product_ids')
                                                <span class="text-red-500 text-xs">{{ $message }}</span>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="submit"
                                        class="ti-btn ti-btn-primary-full">Submit</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search Filters -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header">
                    <div class="box-title">
                        Search Filters
                        @if (request()->hasAny(['vendor_name', 'email', 'product_id', 'service_id', 'status']))
                            <span class="badge bg-primary/10 text-primary ms-2">Active</span>
                        @endif
                    </div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>
                <div class="box-body hidden" id="filter-section">
                    <form method="GET" action="{{ route('admin.vendors.index') }}" class="filter-form"
                        id="vendor-filter-form">
                        <div class="grid grid-cols-12 gap-4">
                            <!-- Vendor Name Search -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor Name</label>
                                <input type="text" name="vendor_name"
                                    class="ti-form-input w-full rounded-sm form-control-sm"
                                    placeholder="Search by vendor name..." value="{{ request('vendor_name') }}">
                            </div>
                            <!-- Email Search -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email</label>
                                <input type="text" name="email"
                                    class="ti-form-input w-full rounded-sm form-control-sm"
                                    placeholder="Search by email..." value="{{ request('email') }}">
                            </div>
                            <!-- Product Filter -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                                <select name="product_id"
                                    class="ti-form-select w-full rounded-sm form-control-sm product-select"
                                    data-placeholder="Select Product">
                                    <option value="">All Products</option>
                                    @foreach ($allProducts as $product)
                                        <option value="{{ $product->id }}"
                                            {{ request('product_id') == $product->id ? 'selected' : '' }}>
                                            {{ $product->product }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Service Filter -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service</label>
                                <select name="service_id"
                                    class="ti-form-select w-full rounded-sm form-control-sm service-select"
                                    data-placeholder="Select Service">
                                    <option value="">All Services</option>
                                    @foreach ($allServices as $service)
                                        <option value="{{ $service->id }}"
                                            {{ request('service_id') == $service->id ? 'selected' : '' }}>
                                            {{ $service->service }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Status Filter -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <select name="status" class="ti-form-select w-full rounded-sm form-control-sm">
                                    <option value="">All Status</option>
                                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active
                                    </option>
                                    <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                            </div>
                            <!-- Action Buttons -->
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
                                <div class="flex gap-2">
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                        Apply Filters
                                    </button>
                                    <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                        onclick="clearFilters()">
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

    <!-- Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header">
                    <div class="box-title">
                        Vendors List
                        <span class="badge bg-info/10 text-info ms-2">{{ method_exists($vendors, 'total') ? $vendors->total() : count($vendors) }}
                            {{ (method_exists($vendors, 'total') ? $vendors->total() : count($vendors)) == 1 ? 'vendor' : 'vendors' }}</span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive ">
                        <table id="vendors-table" class="table display responsive nowrap table-datatable server-paginated" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">
                                    <th data-priority="1">Sr.No</th>
                                    <th data-priority="2">Name</th>
                                    <th data-priority="3">Contact</th>
                                    <th data-priority="4">Email</th>
                                    <th data-priority="5">Location</th>
                                    <th data-priority="6">Status</th>
                                    <th data-priority="7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vendors as $index => $vendor)
                                    <tr class="border-b border-defaultborder">
                                        <td class="text-center">{{ ($vendors->firstItem() ?? 1) + $index }}</td>
                                        <td>{{ $vendor->name }}</td>
                                        <td class="text-center">
                                            {{ $vendor->contact_number }}<br>
                                        </td>
                                        <td>{{ $vendor->email }}</td>
                                        <td>
                                            {{ $vendor->city->name ?? '' }}, {{ $vendor->city->state->name ?? '' }}<br>
                                            {{ $vendor->city->state->country->name ?? '' }}
                                        </td>
                                        <td class="text-center">
                                            @if ($vendor->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-vendor-btn view-product"
                                                    data-hs-overlay="#view-vendor"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View"
                                                    data-vendor-id="{{ $vendor->id }}" data-id="{{ $vendor->id }}">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-vendor-btn edit-product"
                                                    data-hs-overlay="#edit-vendor"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"
                                                    data-vendor-id="{{ $vendor->id }}" data-id="{{ $vendor->id }}">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <button type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm {{ $vendor->status == 1 ? 'ti-btn-danger-full' : 'ti-btn-success-full' }} open-vendor-modal"
                                                    data-id="{{ $vendor->id }}" data-name="{{ $vendor->name }}"
                                                    data-status="{{ $vendor->status }}" data-bs-toggle="tooltip"
                                                    data-bs-placement="top"
                                                    title="{{ $vendor->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $vendor->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if($vendors->hasPages())
                    <div class="mt-4">
                        {{ $vendors->appends(request()->except('page'))->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Modal -->
    <div id="vendor-status-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-vendor-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
                <p class="mb-4 text-gray-600" id="vendor-modal-message">Confirm action</p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                        id="cancel-vendor-toggle">Decline</button>
                    <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-vendor-toggle">Yes,
                        Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Vendor Modal -->
    <div id="edit-vendor" class="edit-vendor hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-store-2-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Vendor</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#edit-vendor">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ti-offcanvas-body edit-vendor-body">
            <form id="edit-vendor-form" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid lg:grid-cols-2 gap-6">
                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor Name</label>
                                        <input type="text" name="name" id="edit_name"
                                            class="ti-form-input rounded-sm form-control-sm">
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                        <input type="email" name="email" id="edit_email"
                                            class="ti-form-input rounded-sm form-control-sm">
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                                        <input type="hidden" name="country_code" id="edit_country_code" value="">
                                        <input type="tel" name="contact_number" id="edit_contact_number"
                                            class="ti-form-input rounded-sm form-control-sm intl-phone-input">
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Profile Image</label>
                                        <input type="file" name="profile_image"
                                            accept="image/*,.jpg,.jpeg,.png,.gif,.webp"
                                            class="ti-form-input rounded-sm form-control-sm">
                                        <div id="current-image" class="mt-2"></div>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                        <select name="country_id" id="edit_countrySelect"
                                            class="ti-form-select rounded-sm form-control-sm">
                                            <option value="">Select Country</option>
                                            @foreach ($countries as $country)
                                                <option value="{{ $country->id }}"
                                                    data-phonecode="{{ $country->isd_code }}">
                                                    {{ $country->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">State</label>
                                        <select name="state_id" id="edit_stateSelect"
                                            class="ti-form-select rounded-sm form-control-sm">
                                            <option value="">Select State</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                        <select name="city_id" id="edit_citySelect"
                                            class="ti-form-select rounded-sm form-control-sm">
                                            <option value="">Select City</option>
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map Link</label>
                                        <input type="url" name="map_link" id="edit_map_link"
                                            class="ti-form-input rounded-sm form-control-sm">
                                    </div>

                                    <div class="space-y-2 col-span-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                        <textarea name="address" id="edit_address" class="ti-form-input rounded-sm form-control-sm" rows="3"></textarea>
                                    </div>

                                    <div class="space-y-2 col-span-2">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Bank Details</label>
                                        <textarea name="bank_details" id="edit_bank_details" class="ti-form-input rounded-sm form-control-sm"
                                            rows="3"></textarea>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="edit_service_ids" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                                        <select class="js-example-basic-multiple w-full form-control-sm"
                                            name="service_ids[]" multiple="multiple" id="edit_service_ids">
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="edit_extra_service_ids" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra
                                            Services</label>
                                        <select class="js-example-basic-multiple w-full form-control-sm"
                                            name="extra_service_ids[]" multiple="multiple" id="edit_extra_service_ids">
                                        </select>
                                    </div>

                                    <div class="space-y-2">
                                        <label for="edit_product_ids" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
                                        <select class="js-example-basic-multiple w-full form-control-sm"
                                            name="product_ids[]" multiple="multiple" id="edit_product_ids">
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <button type="submit"
                        class="ti-btn bg-theme ti-btn-primary-full">Update</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Vendor Modal -->
    <div id="view-vendor" class="view-vendor hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-store-2-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">View Vendor Details</h5>
                        <div class="text-danger font-semibold">
                            <button type="button"
                                class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                                data-hs-overlay="#view-vendor">
                                <span class="sr-only">Close modal</span>
                                <svg class="w-3.5 h-3.5" viewBox="0 0 8 8" fill="none"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        d="M0.258206 1.00652C0.351976 0.912791 0.479126 0.860131 0.611706 0.860131C0.744296 0.860131 0.871447 0.912791 0.965207 1.00652L3.61171 3.65302L6.25822 1.00652C6.30432 0.958771 6.35952 0.920671 6.42052 0.894471C6.48152 0.868271 6.54712 0.854471 6.61352 0.853901C6.67992 0.853321 6.74572 0.865971 6.80722 0.891111C6.86862 0.916251 6.92442 0.953381 6.97142 1.00032C7.01832 1.04727 7.05552 1.1031 7.08062 1.16454C7.10572 1.22599 7.11842 1.29183 7.11782 1.35822C7.11722 1.42461 7.10342 1.49022 7.07722 1.55122C7.05102 1.61222 7.01292 1.6674 6.96522 1.71352L4.31871 4.36002L6.96522 7.00648C7.05632 7.10078 7.10672 7.22708 7.10552 7.35818C7.10442 7.48928 7.05182 7.61468 6.95912 7.70738C6.86642 7.80018 6.74102 7.85268 6.60992 7.85388C6.47882 7.85498 6.35252 7.80458 6.25822 7.71348L3.61171 5.06702L0.965207 7.71348C0.870907 7.80458 0.744606 7.85498 0.613506 7.85388C0.482406 7.85268 0.357007 7.80018 0.264297 7.70738C0.171597 7.61468 0.119017 7.48928 0.117877 7.35818C0.116737 7.22708 0.167126 7.10078 0.258206 7.00648L2.90471 4.36002L0.258206 1.71352C0.164476 1.61976 0.111816 1.4926 0.111816 1.36002C0.111816 1.22744 0.164476 1.10028 0.258206 1.00652Z"
                                        fill="currentColor" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="ti-offcanvas-body view-vendor-body">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div class="grid grid-cols-12 sm:gap-6">
                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor Name</label>
                                    <p class="text-gray-800 dark:text-white" id="view_name"></p>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                                    <div class="text-gray-800 dark:text-white" id="view_email"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Contact Number</label>
                                    <div class="text-gray-800 dark:text-white" id="view_contact_number">
                                    </div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Profile Image</label>
                                    <div id="view-profile-image" class="mt-2"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                                    <div class="text-gray-800 dark:text-white" id="view_country"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">State</label>
                                    <div class="text-gray-800 dark:text-white" id="view_state"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                                    <div class="text-gray-800 dark:text-white" id="view_city"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Map Link</label>
                                    <div class="text-gray-800 dark:text-white" id="view_map_link"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                                    <div class="text-gray-800 dark:text-white" id="view_address"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Bank Details</label>
                                    <div class="text-gray-800 dark:text-white" id="view_bank_details">
                                    </div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                                    <div class="text-gray-800 dark:text-white" id="view_services"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
                                    <div class="text-gray-800 dark:text-white" id="view_extra_services">
                                    </div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
                                    <div class="text-gray-800 dark:text-white" id="view_products"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                    <div class="text-gray-800 dark:text-white" id="view_status"></div>
                                </div>

                                <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                    <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created Date</label>
                                    <div class="text-gray-800 dark:text-white" id="view_created_at">
                                    </div>
                                </div>
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
        // Global reset function (backup method)
        function resetFilters() {
            window.location.href = '{{ route('admin.vendors.index') }}';
        }

        $(document).ready(function() {
            // Initialize Select2 for search dropdowns
            $('.product-select').select2({
                placeholder: 'Select Product',
                allowClear: true,
                width: '100%'
            });

            $('.service-select').select2({
                placeholder: 'Select Service',
                allowClear: true,
                width: '100%'
            });

            // Reset filters functionality
            $('#reset-filters').on('click', function(e) {
                e.preventDefault();
                

                try {
                    $('#vendor-filter-form input[type="text"]').val('');
                    $('#vendor-filter-form select:not(.product-select, .service-select)').val('');

                    if ($('.product-select').length > 0) {
                        $('.product-select').val(null).trigger('change');
                    }

                    if ($('.service-select').length > 0) {
                        $('.service-select').val(null).trigger('change');
                    }

                    setTimeout(function() {
                        window.location.href = '{{ route('admin.vendors.index') }}';
                    }, 100);

                } catch (error) {
                    window.location.href = '{{ route('admin.vendors.index') }}';
                }
            });

            // Status toggle modal functionality
            let vendorIdToToggle = null;
            let buttonToUpdate = null;

            $(document).on('click', '.open-vendor-modal', function() {
                vendorIdToToggle = $(this).data('id');
                const name = $(this).data('name');
                const status = $(this).data('status');
                buttonToUpdate = $(this);
                $('#vendor-modal-message').text(status == 1 ?
                    `You want to deactivate "${name}"?` :
                    `You want to activate "${name}"?`);
                $('#vendor-status-modal').removeClass('hidden');
            });

            $('#close-vendor-modal, #cancel-vendor-toggle').on('click', function() {
                $('#vendor-status-modal').addClass('hidden');
                // cleanup any leftover overlays/classes
                cleanupOverlays();
            });

            $('#confirm-vendor-toggle').on('click', function() {
                const form = $('<form>', {
                    method: 'POST',
                    action: '/admin/vendors/toggle-status/' + vendorIdToToggle
                });

                form.append($('<input>', {
                    type: 'hidden',
                    name: '_token',
                    value: "{{ csrf_token() }}"
                }));

                form.append($('<input>', {
                    type: 'hidden',
                    name: '_method',
                    value: 'PATCH'
                }));

                $('body').append(form);
                form.submit();
            });

            // ===== ADD VENDOR FUNCTIONALITY =====
            let addIti;

            function initializeAddForm() {
                


                // Initialize phone input for add form
                const addPhoneInput = document.querySelector("#add_contact_number");
                if (addPhoneInput) {
                    
                    if (addIti) {
                        addIti.destroy();
                    }
                    addIti = window.intlTelInput(addPhoneInput, {
                        initialCountry: "in",
                        separateDialCode: true,
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                    });

                    // On form submit, update contact number with formatted value
                    $('#add-vendor-form-element').off('submit').on('submit', function(e) {
                        const countryData = addIti.getSelectedCountryData();
                        document.querySelector("#add_country_code").value = '+' + countryData.dialCode;
                        
                    });
                }



                $('#add_countrySelect, #add_stateSelect, #add_citySelect').select2({
                    width: '100%'
                });

                $('#add_service_ids').select2({
                    width: '100%',
                    placeholder: 'Select services'
                });

                $('#add_extra_service_ids').select2({
                    width: '100%',
                    placeholder: 'Select extra services'
                });

                $('#add_product_ids').select2({
                    width: '100%',
                    placeholder: 'Select services first',
                    disabled: true
                });

                // Country change handler for add form
                $('#add_countrySelect').on('change', function() {
                    
                    const phoneCode = $(this).find('option:selected').data('phonecode');
                    

                    if (phoneCode) {
                        $('#add_country_code').val(phoneCode);
                        
                    }

                    let countryId = $(this).val();
                    
                    let $stateDropdown = $('#add_stateSelect');
                    let $cityDropdown = $('#add_citySelect');

                    // Clear and reset state dropdown
                    $stateDropdown.empty().append('<option value="">Select State</option>');
                    $cityDropdown.empty().append('<option value="">Select City</option>');
                    $stateDropdown.select2('destroy').select2({
                                            width: '100%'
                                        });
                    // Refresh Select2 after clearing
                    if (countryId) {
                        $.get(`/admin/vendors/states/${countryId}`, function(res) {
                            // Clear state dropdown completely
                            $stateDropdown.empty().append('<option value="">Select State</option>');
                            
                            if (res.states?.length) {
                                // Use Set to ensure unique states
                                const addedStates = new Set();
                                
                                res.states.forEach(state => {
                                    if (!addedStates.has(state.id)) {
                                        addedStates.add(state.id);
                                        $stateDropdown.append(
                                            `<option value="${state.id}">${state.name}</option>`
                                        );
                                    }
                                });
                            }
                            
                            // Don't reinitialize Select2, just update it
                            $stateDropdown.trigger('change');

                        }).fail(function() {
                            $stateDropdown.append('<option value="">Error loading states</option>');
                        });
                    }
                });

                // State change handler for add form
                $('#add_stateSelect').off('change').on('change', function() {
                    
                    let stateId = $(this).val();
                    let $cityDropdown = $('#add_citySelect');

                    // Clear and reset city dropdown
                    $cityDropdown.empty().append('<option value="">Select City</option>');
                    $cityDropdown.val('').trigger('change.select2');

                    if (stateId) {
                        
                        $.get(`/admin/vendors/cities/${stateId}`, function(res) {
                            
                            if (res?.length) {
                                res.forEach(city => {
                                    $cityDropdown.append(
                                        `<option value="${city.id}">${city.name}</option>`
                                    );
                                });
                            } else {
                                $cityDropdown.append(
                                    '<option value="">No cities available</option>');
                            }

                            // Retain selected city if available
                            const selectedCity = '{{ old('city_id') }}';
                            if (selectedCity) {
                                $cityDropdown.val(selectedCity).trigger('change.select2');
                            }

                            // Force Select2 to refresh after adding options
                            $cityDropdown.trigger('change.select2');
                        }).fail(function(xhr, status, error) {
                            $cityDropdown.append('<option value="">Error loading cities</option>');
                            $cityDropdown.trigger('change.select2');
                        });
                    }
                });

                // Service selection handler for add form
                $('#add_service_ids').on('change', function() {
                    
                    let selectedServices = $(this).val();

                    if (!selectedServices || selectedServices.length === 0) {
                        $('#add_product_ids').empty().trigger('change');
                        $('#add_product_ids').prop('disabled', true);
                        $('#add_product_ids').select2({
                            placeholder: 'Select services first',
                            width: '100%'
                        });
                        return;
                    }

                    $('#add_product_ids').prop('disabled', false);
                    $('#add_product_ids').select2({
                        placeholder: 'Loading products...',
                        width: '100%'
                    });

                    $.get('/admin/vendors/get-service-products', {
                        service_ids: selectedServices
                    }, function(response) {
                        
                        $('#add_product_ids').empty();

                        if (response.products && response.products.length > 0) {
                            $.each(response.products, function(index, product) {
                                let option = new Option(product.product, product.id, true,
                                    response.selected_product_ids.includes(product.id));
                                $('#add_product_ids').append(option).trigger('change');
                            });
                        }

                        $('#add_product_ids').trigger('change');
                        $('#add_product_ids').select2({
                            placeholder: 'Select products',
                            width: '100%'
                        });
                    }).fail(function(xhr, status, error) {
                        $('#add_product_ids').empty().trigger('change');
                        $('#add_product_ids').select2({
                            placeholder: 'Error loading products',
                            width: '100%'
                        });
                    });
                });

                // Handle old values for add form
                @if (old('country_id'))
                    setTimeout(() => {
                        $('#add_countrySelect').val('{{ old('country_id') }}').trigger('change');
                        

                        @if (old('state_id'))
                            setTimeout(() => {
                                $('#add_stateSelect').val('{{ old('state_id') }}').trigger(
                                    'change');
                                

                                @if (old('city_id'))
                                    setTimeout(() => {
                                        $('#add_citySelect').val('{{ old('city_id') }}')
                                            .trigger('change');
                                        
                                    }, 800);
                                @endif
                            }, 800);
                        @endif
                    }, 300);
                @endif


                @if (old('service_ids') && is_array(old('service_ids')))
                    setTimeout(function() {
                        $('#add_service_ids').val({!! json_encode(old('service_ids')) !!}).trigger('change.select2');
                    }, 100);
                @endif

                @if (old('extra_service_ids') && is_array(old('extra_service_ids')))
                    setTimeout(function() {
                        $('#add_extra_service_ids').val({!! json_encode(old('extra_service_ids')) !!}).trigger('change.select2');
                    }, 100);
                @endif
            }

            // Initialize add form when page loads
            initializeAddForm();

            // Re-initialize add form when accordion opens
            $('.hs-accordion-toggle').on('click', function() {
                setTimeout(function() {
                    

                    // Destroy existing Select2 instances for add form
                    $('#add_countrySelect, #add_stateSelect, #add_citySelect, #add_service_ids, #add_extra_service_ids, #add_product_ids')
                        .each(function() {
                            if ($(this).hasClass('select2-hidden-accessible')) {
                                $(this).select2('destroy');
                            }
                        });

                    // Re-initialize add form
                    initializeAddForm();
                }, 300);
            });

            // ===== VIEW VENDOR FUNCTIONALITY =====

            // View vendor button click handler - product-style: let HSOverlay open, fetch data via AJAX
            $(document).on('click', '.view-product', function(e) {
                e.preventDefault();
                const vendorId = $(this).data('id');

                $.ajax({
                    url: `/admin/vendors/${vendorId}/view-modal`,
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            populateViewModal(response);
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to load vendor data');
                    }
                });
                // Fallback: ensure offcanvas becomes visible even if HSOverlay didn't open it
                ensureOffcanvasVisible('#view-vendor');
                setTimeout(function() { ensureOffcanvasVisible('#view-vendor'); }, 150);
                setTimeout(function() { ensureOffcanvasVisible('#view-vendor'); }, 500);
            });

            function loadVendorViewData(vendorId) {
                
                $.get(`/admin/vendors/${vendorId}/view-modal`, function(response) {
                    
                    if (response.success) {
                        populateViewModal(response);
                        
                    } else {
                        alert('Error loading vendor data');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    alert('Error loading vendor data: ' + textStatus);
                });
            }

            function populateViewModal(response) {
                const vendor = response.vendor;
                const countryCode = response.country_code;
                const contactNumber = response.contact_number;
                const relatedProducts = response.relatedProducts;
                const relatedServices = response.relatedServices;
                const relatedExtraServices = response.relatedExtraServices;

                

                // Populate basic fields
                $('#view_name').text(vendor.name);
                $('#view_email').text(vendor.email);
                $('#view_contact_number').text(countryCode + ' ' + contactNumber);
                $('#view_address').text(vendor.address || 'Not provided');
                $('#view_bank_details').text(vendor.bank_details || 'Not provided');
                $('#view_map_link').html(vendor.map_link ?
                    `<a href="${vendor.map_link}" target="_blank" class="text-blue-600 hover:underline">${vendor.map_link}</a>` :
                    'Not provided'
                );

                // Location details
                $('#view_country').text(vendor.city?.state?.country?.name || 'Not provided');
                $('#view_state').text(vendor.city?.state?.name || 'Not provided');
                $('#view_city').text(vendor.city?.name || 'Not provided');

                // Profile image
                if (vendor.profile_image) {
                    $('#view-profile-image').html(
                        `<img src="/storage/${vendor.profile_image}" class="h-24 w-24 object-cover rounded">`);
                } else {
                    $('#view-profile-image').html('<span class="text-gray-500">No image uploaded</span>');
                }

                // Status
                const statusHtml = vendor.status == 1 ?
                    '<span class="badge bg-success/10 text-success">Active</span>' :
                    '<span class="badge bg-danger/10 text-danger">Inactive</span>';
                $('#view_status').html(statusHtml);

                // Created date
                const createdDate = new Date(vendor.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                $('#view_created_at').text(createdDate);

                // Services
                if (relatedServices && relatedServices.length > 0) {
                    const servicesHtml = relatedServices.map(service =>
                        `<span class="badge bg-primary/10 text-primary me-1 mb-1">${service.service}</span>`
                    ).join('');
                    $('#view_services').html(servicesHtml);
                } else {
                    $('#view_services').text('No services assigned');
                }

                // Extra Services
                if (relatedExtraServices && relatedExtraServices.length > 0) {
                    const extraServicesHtml = relatedExtraServices.map(extraService =>
                        `<span class="badge bg-warning/10 text-warning me-1 mb-1">${extraService.extra_service}</span>`
                    ).join('');
                    $('#view_extra_services').html(extraServicesHtml);
                } else {
                    $('#view_extra_services').text('No extra services assigned');
                }

                // Products
                if (relatedProducts && relatedProducts.length > 0) {
                    const productsHtml = relatedProducts.map(product =>
                        `<span class="badge bg-info/10 text-info me-1 mb-1">${product.product}</span>`
                    ).join('');
                    $('#view_products').html(productsHtml);
                } else {
                    $('#view_products').text('No products assigned');
                }
            }

            // Close view modal handler
            $(document).on('click', '[data-hs-overlay="#view-vendor"]', function() {
                var $el = $('#view-vendor');
                $el.addClass('hidden').removeClass('open');
                $el.css({'transform':'', 'right':'', 'visibility':''});
                // central cleanup to restore page state
                // prevent diagnostic handler from re-opening immediately
                window._hsJustClosed = true;
                setTimeout(function() { window._hsJustClosed = false; }, 300);
                cleanupOverlays();
            });

            // ===== EDIT VENDOR FUNCTIONALITY =====
            let editIti;
            let currentVendorData = null;

            // Edit vendor button click handler - product-style: let HSOverlay open, fetch data via AJAX
            $(document).on('click', '.edit-product', function(e) {
                e.preventDefault();
                const vendorId = $(this).data('id');

                $.ajax({
                    url: `/admin/vendors/${vendorId}/edit`,
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            currentVendorData = response.vendor;
                            populateEditForm(response);
                            // clear error messages if any
                            if (typeof clearErrorMessages === 'function') clearErrorMessages();
                        }
                    },
                    error: function(xhr) {
                        alert('Failed to load vendor data');
                    }
                });
                // Fallback: ensure offcanvas becomes visible even if HSOverlay didn't open it
                ensureOffcanvasVisible('#edit-vendor');
                setTimeout(function() { ensureOffcanvasVisible('#edit-vendor'); }, 150);
                setTimeout(function() { ensureOffcanvasVisible('#edit-vendor'); }, 500);
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
                        $el.addClass('hidden').removeClass('open');
                        $el.css({'transform':'', 'right':'', 'visibility':''});
                    });

                    // As a last resort, enable pointer events on body and main content
                    $('body, #app, .app, .main, .content').css('pointer-events', '');
                } catch (err) {
                }
            }

            // short-lived flag to prevent immediate re-open when a close action occurs
            window._hsJustClosed = false;


            // Diagnostic: log clicks on any overlay triggers and ensure offcanvas visible
            // Avoid re-opening the offcanvas when clicking close buttons inside the offcanvas
            $(document).on('click', '[data-hs-overlay]', function(e) {
                try {
                    // If we just closed a modal, avoid running the reopen logic for a short period
                    if (window._hsJustClosed) {
                        
                        return;
                    }
                    var target = $(this).data('hs-overlay');
                    
                    if (!target) return;

                    // If the click originated from within the target overlay (e.g. a close button inside the modal),
                    // don't force it open again. This prevents the close button from immediately re-opening the offcanvas.
                    var $targetEl = $(target);
                    if ($targetEl.length && $.contains($targetEl[0], e.target)) {
                        
                        return;
                    }

                    // small delay to allow HSOverlay handler to run
                    setTimeout(function() { ensureOffcanvasVisible(target); }, 80);
                } catch (err) {
                }
            });

            // Fallback: after any hs-overlay trigger is clicked, if the target ends up closed, ensure cleanup runs
            $(document).on('click', '[data-hs-overlay]', function(e) {
                try {
                    var target = $(this).data('hs-overlay');
                    if (!target) return;
                    // Wait for HS overlay to toggle, then cleanup if the overlay is not open
                    setTimeout(function() {
                        try {
                            var $t = $(target);
                            if (!$t.length) return;
                            if (!$t.hasClass('open') || $t.hasClass('hidden')) {
                                cleanupOverlays();
                            }
                        } catch (innerErr) {
                            cleanupOverlays();
                        }
                    }, 120);
                } catch (err) {
                    cleanupOverlays();
                }
            });

            // If a close trigger inside an overlay is clicked, mark as just closed and cleanup
            $(document).on('click', '.hs-overlay [data-hs-overlay]', function(e) {
                try {
                    // this is likely a close button inside the overlay; prevent other handlers briefly
                    window._hsJustClosed = true;
                    setTimeout(function() { window._hsJustClosed = false; }, 300);
                    // schedule cleanup after the HS overlay's own handler runs
                    setTimeout(function() { cleanupOverlays(); }, 120);
                } catch (err) {
                }
            });

            // Close custom vendor-status-modal when clicking outside the dialog box
            $(document).on('click', '#vendor-status-modal', function(e) {
                var $dialog = $(this).find('.alert.custom-alert1');
                // If click is outside the dialog, hide the modal
                if ($dialog.length && !$.contains($dialog[0], e.target) && e.target !== $dialog[0]) {
                    $('#vendor-status-modal').addClass('hidden');
                    window._hsJustClosed = true;
                    setTimeout(function() { window._hsJustClosed = false; }, 300);
                    cleanupOverlays();
                }
            });

            function loadVendorData(vendorId) {
                
                $.get(`/admin/vendors/${vendorId}/edit`, function(response) {
                    
                    if (response.success) {
                        currentVendorData = response.vendor;
                        populateEditForm(response);

                        // Note: modal is opened immediately on click for perceived speed; we only populate here.
                        
                    } else {
                        alert('Error loading vendor data');
                    }
                }).fail(function(jqXHR, textStatus, errorThrown) {
                    console.error('AJAX error loading vendor data:', textStatus, errorThrown);
                    alert('Error loading vendor data: ' + textStatus);
                });
            }

            function populateEditForm(response) {
                const vendor = response.vendor;
                const countries = response.countries;
                const states = response.states;
                const cities = response.cities;
                const allServices = response.allServices;
                const allProducts = response.allProducts;
                const allExtraServices = response.allExtraServices;
                const countryCode = response.country_code;
                const contactNumber = response.contact_number;

                
                

                // Set form action
                $('#edit-vendor-form').attr('action', `/admin/vendors/${vendor.id}`);

                // Populate basic fields
                $('#edit_name').val(vendor.name);
                $('#edit_email').val(vendor.email);
                $('#edit_contact_number').val(contactNumber || '');
                $('#edit_address').val(vendor.address);
                $('#edit_bank_details').val(vendor.bank_details || '');
                $('#edit_map_link').val(vendor.map_link || '');

                // Set country code
                $('#edit_country_code').val(countryCode);

                // Initialize phone input for edit form
                const editPhoneInput = document.querySelector("#edit_contact_number");
                if (editPhoneInput) {
                    if (editIti) {
                        editIti.destroy();
                    }

                    editIti = window.intlTelInput(editPhoneInput, {
                        initialCountry: "in",
                        separateDialCode: true,
                        utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@25.3.1/build/js/utils.js"
                    });

                    // Set the contact number
                    editPhoneInput.value = contactNumber;

                    // Form submit handler
                    $('#edit-vendor-form').off('submit.phone').on('submit.phone', function(e) {
                        const countryData = editIti.getSelectedCountryData();
                        document.querySelector("#edit_country_code").value = '+' + countryData.dialCode;
                        
                    });
                }

                // Populate countries
                const countrySelect = $('#edit_countrySelect');
                countrySelect.empty().append('<option value="">Select Country</option>');
                countries.forEach(function(country) {
                    // vendor.city may be null -- guard with optional chaining
                    const isSelected = country.id === (vendor.city?.state?.country_id ?? null);
                    countrySelect.append(
                        `<option value="${country.id}" data-phonecode="${country.isd_code}"${isSelected ? ' selected' : ''}>${country.name}</option>`
                    );
                });

                // Populate states
                const stateSelect = $('#edit_stateSelect');
                stateSelect.empty().append('<option value="">Select State</option>');
                if (states && states.length > 0) {
                    states.forEach(function(state) {
                        // support vendor without a city
                        const isSelected = state.id === (vendor.city?.state_id ?? null);
                        stateSelect.append(
                            `<option value="${state.id}"${isSelected ? ' selected' : ''}>${state.name}</option>`
                        );
                    });
                }

                // Populate cities
                const citySelect = $('#edit_citySelect');
                citySelect.empty().append('<option value="">Select City</option>');
                    if (cities && cities.length > 0) {
                    cities.forEach(function(city) {
                        // vendor.city_id might be undefined for vendors without a city
                        const isSelected = city.id === (vendor.city_id ?? vendor.city?.id ?? null);
                        citySelect.append(
                            `<option value="${city.id}"${isSelected ? ' selected' : ''}>${city.name}</option>`
                        );
                    });
                }

                // Handle profile image
                if (vendor.profile_image) {
                    $('#current-image').html(
                        `<img src="/storage/${vendor.profile_image}" class="h-20 w-20 object-cover rounded">`);
                } else {
                    $('#current-image').empty();
                }

                // Initialize select2 for edit form
                $('#edit_countrySelect, #edit_stateSelect, #edit_citySelect').select2({
                    width: '100%',
                    dropdownParent: $('#edit-vendor')
                });

                // Populate ALL services
                const serviceSelect = $('#edit_service_ids');
                serviceSelect.empty();
                allServices.forEach(function(service) {
                    const isSelected = vendor.service_ids && vendor.service_ids.includes(service.id);
                    serviceSelect.append(
                        `<option value="${service.id}"${isSelected ? ' selected' : ''}>${service.service} (${service.service_amount})</option>`
                    );
                });
                serviceSelect.select2({
                    width: '100%',
                    placeholder: 'Select services',
                    dropdownParent: $('#edit-vendor')
                });

                // Populate ALL extra services
                const extraServiceSelect = $('#edit_extra_service_ids');
                extraServiceSelect.empty();
                allExtraServices.forEach(function(extraService) {
                    const isSelected = vendor.extra_service_ids && vendor.extra_service_ids.includes(
                        extraService.id);
                    extraServiceSelect.append(
                        `<option value="${extraService.id}"${isSelected ? ' selected' : ''}>${extraService.extra_service} (${extraService.extra_service_amount})</option>`
                    );
                });
                extraServiceSelect.select2({
                    width: '100%',
                    placeholder: 'Select extra services',
                    dropdownParent: $('#edit-vendor')
                });

                // Initialize products dropdown
                const productSelect = $('#edit_product_ids');
                productSelect.empty();

                // Check if vendor has service_ids selected
                const hasServices = vendor.service_ids && vendor.service_ids.length > 0;
                productSelect.select2({
                    width: '100%',
                    placeholder: hasServices ? 'Loading products...' : 'Select services first',
                    disabled: !hasServices,
                    dropdownParent: $('#edit-vendor')
                });

                // Set up event handlers for edit form
                setupEditFormHandlers();

                // Load products if services are selected
                if (hasServices) {
                    setTimeout(() => {
                        
                        $('#edit_service_ids').trigger('change');

                        // Select vendor's products after loading
                        setTimeout(() => {
                            if (vendor.product_ids && vendor.product_ids.length > 0) {
                                
                                $('#edit_product_ids').val(vendor.product_ids).trigger('change');
                            }
                        }, 800);
                    }, 300);
                }
            }

            function setupEditFormHandlers() {
                

                // Country change handler for edit form
                $('#edit_countrySelect').off('change.edit').on('change.edit', function() {
                    
                    const phoneCode = $(this).find('option:selected').data('phonecode');
                    if (phoneCode) {
                        $('#edit_country_code').val(phoneCode);
                        
                    }

                    let countryId = $(this).val();
                    let $stateDropdown = $('#edit_stateSelect').empty().append(
                        '<option value="">Select State</option>');
                    $('#edit_citySelect').empty().append('<option value="">Select City</option>');

                    if (countryId) {
                        $.get(`/admin/vendors/states/${countryId}`, function(res) {
                            if (res.states?.length) {
                                res.states.forEach(state => {
                                    $stateDropdown.append(
                                        `<option value="${state.id}">${state.name}</option>`
                                    );
                                });
                            } else {
                                $stateDropdown.append(
                                    '<option value="">No states available</option>');
                            }

                            $('#edit_stateSelect').select2({
                                width: '100%',
                                dropdownParent: $('#edit-vendor')
                            });
                            $('#edit_citySelect').select2({
                                width: '100%',
                                dropdownParent: $('#edit-vendor')
                            });
                        }).fail(function() {
                            $stateDropdown.append('<option value="">Error loading states</option>');
                            $('#edit_stateSelect').select2({
                                width: '100%',
                                dropdownParent: $('#edit-vendor')
                            });
                        });
                    }
                });

                    // ===== AJAX SUBMIT for edit form =====
    $('#edit-vendor-form').off('submit.edit').on('submit.edit', function(e) {
        e.preventDefault();

        // Update country code from phone input
        if (editIti) {
            const countryData = editIti.getSelectedCountryData();
            document.querySelector("#edit_country_code").value = '+' + countryData.dialCode;
        }

        // Clear previous errors
        $('#edit-vendor .edit-error-msg').remove();
        $('#edit-vendor .ti-form-input, #edit-vendor .ti-form-select').removeClass('border-red-500');

        const formData = new FormData(this);

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            success: function(response) {
                if (response.success) {
                    // Close the offcanvas
                    var $el = $('#edit-vendor');
                    $el.addClass('hidden').removeClass('open');
                    $el.css({'transform': '', 'right': '', 'visibility': ''});
                    cleanupOverlays();

                    // Show success message and reload table
                    $('<div class="alert alert-success mb-4">' + response.message + '</div>')
                        .insertBefore('.grid.grid-cols-12:first')
                        .delay(3000).fadeOut(400, function() { $(this).remove(); });

                    // Reload page to refresh table
                    setTimeout(function() { location.reload(); }, 1000);
                }
            },
            error: function(xhr) {
                if (xhr.status === 422 && xhr.responseJSON) {
                    const errors = xhr.responseJSON.errors;

                    // Display a top-level error banner inside the modal
                    var $banner = $('<div class="edit-error-msg alert alert-danger mb-3 mx-4 mt-2 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2">Please fix the errors below and try again.</div>');
                    $('#edit-vendor-form').prepend($banner);

                    // Map error keys to field IDs
                    const fieldMap = {
                        name:              '#edit_name',
                        email:             '#edit_email',
                        contact_number:    '#edit_contact_number',
                        country_code:      '#edit_country_code',
                        country_id:        '#edit_countrySelect',
                        state_id:          '#edit_stateSelect',
                        city_id:           '#edit_citySelect',
                        address:           '#edit_address',
                        bank_details:      '#edit_bank_details',
                        map_link:          '#edit_map_link',
                        service_ids:       '#edit_service_ids',
                        service_validation:'#edit_service_ids',
                        extra_service_ids: '#edit_extra_service_ids',
                        product_ids:       '#edit_product_ids',
                        general:           null,
                    };

                    $.each(errors, function(field, messages) {
                        const msg = Array.isArray(messages) ? messages[0] : messages;
                        const selector = fieldMap[field];

                        if (selector) {
                            const $field = $(selector);
                            $field.addClass('border-red-500');
                            $('<span class="edit-error-msg text-red-500 text-xs block mt-1">' + msg + '</span>')
                                .insertAfter($field.closest('.select2-container').length
                                    ? $field.closest('.select2-container')
                                    : $field);
                        } else {
                            // General error — append to banner
                            $banner.append('<br>' + msg);
                        }
                    });

                    // Scroll to top of offcanvas body
                    $('.ti-offcanvas-body.edit-vendor-body').scrollTop(0);
                } else {
                    alert('An unexpected error occurred. Please try again.');
                }
            }
        });
    });

                // State change handler for edit form
                $('#edit_stateSelect').off('change.edit').on('change.edit', function() {
                    
                    let stateId = $(this).val();
                    let $cityDropdown = $('#edit_citySelect').empty().append(
                        '<option value="">Select City</option>');

                    if (stateId) {
                        $.get(`/admin/vendors/cities/${stateId}`, function(res) {
                            if (res?.length) {
                                res.forEach(city => {
                                    $cityDropdown.append(
                                        `<option value="${city.id}">${city.name}</option>`
                                    );
                                });
                            } else {
                                $cityDropdown.append(
                                    '<option value="">No cities available</option>');
                            }

                            $('#edit_citySelect').select2({
                                width: '100%',
                                dropdownParent: $('#edit-vendor')
                            });
                        }).fail(function() {
                            $cityDropdown.append('<option value="">Error loading cities</option>');
                            $('#edit_citySelect').select2({
                                width: '100%',
                                dropdownParent: $('#edit-vendor')
                            });
                        });
                    }
                });

                // Service selection handler for edit form
                $('#edit_service_ids').off('change.edit').on('change.edit', function() {
                    
                    let selectedServices = $(this).val();

                    // Remember currently selected products
                    const currentlySelectedProducts = $('#edit_product_ids').val() || [];

                    if (!selectedServices || selectedServices.length === 0) {
                        $('#edit_product_ids').empty().trigger('change');
                        $('#edit_product_ids').prop('disabled', true);
                        $('#edit_product_ids').select2({
                            placeholder: 'Select services first',
                            dropdownParent: $('#edit-vendor')
                        });
                        return;
                    }

                    $('#edit_product_ids').prop('disabled', false);
                    $('#edit_product_ids').select2({
                        placeholder: 'Loading products...',
                        dropdownParent: $('#edit-vendor')
                    });

                    $.get('/admin/vendors/get-service-products', {
                        service_ids: selectedServices,
                        vendor_id: currentVendorData ? currentVendorData.id : null
                    }, function(response) {
                        
                        $('#edit_product_ids').empty();

                        if (response.products && response.products.length > 0) {
                            $.each(response.products, function(index, product) {
                                const isSelected = currentlySelectedProducts.includes(
                                    product.id);
                                let option = new Option(product.product, product.id, false,
                                    isSelected);
                                $('#edit_product_ids').append(option);
                            });
                        }

                        $('#edit_product_ids').trigger('change');
                        $('#edit_product_ids').select2({
                            placeholder: 'Select products',
                            dropdownParent: $('#edit-vendor')
                        });
                    }).fail(function(xhr, status, error) {
                        $('#edit_product_ids').empty().trigger('change');
                        $('#edit_product_ids').select2({
                            placeholder: 'Error loading products',
                            dropdownParent: $('#edit-vendor')
                        });
                    });
            });
            }

            // Close edit modal handler
            $(document).on('click', '[data-hs-overlay="#edit-vendor"]', function() {
                var $el = $('#edit-vendor');
                $el.addClass('hidden').removeClass('open');
                $el.css({'transform':'', 'right':'', 'visibility':''});
                window._hsJustClosed = true;
                setTimeout(function() { window._hsJustClosed = false; }, 300);
                cleanupOverlays();
            });
        });

        function clearFilters() {
            $('.filter-form')[0].reset();
            window.location.href = '{{ route('admin.vendors.index') }}';
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Initially hide filter section and set icon to down
            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function() {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });
        });
    </script>
@endpush
