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
    <!-- Products Management with Add Form -->
    <div class="grid grid-cols-12">
        <div class="xl:col-span-12 col-span-12">
            <div class="box">
                <div class="hs-accordion-group">
                    <div class="hs-accordion" id="add-product-accordion">
                        <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                        <i class="ri-product-hunt-line"></i>
                                    </span>
                                </div>
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Manage Products</h5>
                                        <div class="text-danger font-semibold">
                                            <button type="button"
                                                class="hs-accordion-toggle ti-btn bg-theme ti-btn-primary-full !py-1 !px-2 ti-btn-wave"
                                                aria-controls="add-product-form">
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
                                                Add Product
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="add-product-form"
                            class="hs-accordion-content hidden w-full overflow-hidden transition-[height] duration-300"
                            aria-labelledby="add-product-accordion">
                            <form id="add-product-form-element">
                                @csrf
                                <div class="box-body">
                                    <div class="grid grid-cols-12 gap-6">
                                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label mb-0">Product Name <span
                                                    class="text-red-500">*</span></label>
                                            <input type="text" name="product"
                                                class="ti-form-input rounded-sm form-control-sm" required>
                                            <span class="text-red-500 text-xs error-message" id="add-product-error"></span>
                                        </div>

                                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
                                            <div class="flex items-center">
                                                <input type="checkbox" name="is_private" id="add-is_private"
                                                    class="ti-form-checkbox me-2" value="1">
                                                <p for="add-is_private" class="text-gray-800 dark:text-white">Private
                                                    Charter</p>
                                            </div>
                                            <span class="text-red-500 text-xs error-message" id="add-private-error"></span>
                                        </div>

                                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
                                            <div class="flex items-center">
                                                <input type="checkbox" name="is_airambulance" id="add-is_airambulance"
                                                    class="ti-form-checkbox me-2" value="1">
                                                <p for="add-is_airambulance" class="text-gray-800 dark:text-white">Air
                                                    Ambulance</p>
                                            </div>
                                            <span class="text-red-500 text-xs error-message"
                                                id="add-airambulance-error"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="box-footer">
                                    <button type="button" class="ti-btn ti-btn-outline-secondary me-2"
                                        id="cancel-add-product">
                                        Cancel
                                    </button>
                                    <button type="submit" class="ti-btn bg-theme ti-btn-primary-full"
                                        id="save-add-product">
                                        Save Product
                                    </button>
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
            <div class="box">
                <div class="box-header">
                    <div class="box-title">
                        Search Filters
                    </div>
                    <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                        <i class="ti ti-chevron-up" id="filter-icon"></i>
                    </button>
                </div>
                <div class="box-body" id="filter-section">
                    <form class="ti-custom-validation product-filters" method="GET"
                        action="{{ route('admin.products.index') }}" id="filter-form" novalidate>
                        <div class="grid grid-cols-12 sm:gap-6 flex items-center">
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="product-name" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product
                                    Name</label>
                                <input type="text" name="product" class="ti-form-input rounded-sm form-control-sm"
                                    id="product-name" value="{{ request('product') }}"
                                    placeholder="Search by product name">
                            </div>
                            <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                <label for="status"
                                    class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                <select name="status" class="ti-form-select rounded-sm form-control-sm" id="status">
                                    <option value="">All Statuses</option>
                                    <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                                    <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive
                                    </option>
                                </select>
                            </div>
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

    <!-- Products Table -->
    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-12 col-span-12">
            <div class="box custom-box">
                <div class="box-header justify-between flex">
                    <div class="box-title">
                        All Products
                    </div>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table display responsive nowrap table-datatable" width="100%">
                            <thead class="bg-primary text-white">
                                <tr class="border-b border-defaultborder">

                                    <th data-priority="1">S.No</th>
                                    <th data-priority="3">Product Name</th>
                                    <th data-priority="4">Private Charter</th>
                                    <th data-priority="5">Air Ambulance</th>
                                    <th data-priority="6">Status</th>
                                    <th data-priority="7">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($products as $key => $product)
                                    <tr class="border-b border-defaultborder">

                                        <td class="text-center">{{ $key + 1 }}</td>
                                        <td>{{ Str::limit($product->product, 50) }}</td>
                                        <td class="text-center">
                                            @if ($product->is_private == 1)
                                                <span class="badge bg-success/10 text-success">Yes</span>
                                            @else
                                                <span class="badge bg-secondary/10 text-secondary">No</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($product->is_airambulance == 1)
                                                <span class="badge bg-success/10 text-success">Yes</span>
                                            @else
                                                <span class="badge bg-secondary/10 text-secondary">No</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($product->status == 1)
                                                <span class="badge bg-success/10 text-success">Active</span>
                                            @else
                                                <span class="badge bg-danger/10 text-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="hstack flex gap-3 text-[.9375rem]">
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full view-product"
                                                    data-id="{{ $product->id }}" data-hs-overlay="#view-product"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="View">
                                                    <i class="ri-eye-line"></i>
                                                </a>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full edit-product"
                                                    data-id="{{ $product->id }}" data-hs-overlay="#edit-product"
                                                    data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                <a aria-label="anchor" href="javascript:void(0);"
                                                    class="ti-btn ti-btn-icon ti-btn-sm {{ $product->status ? 'ti-btn-danger-full' : 'ti-btn-success-full' }} toggle-product-status"
                                                    data-id="{{ $product->id }}" data-status="{{ $product->status }}"
                                                    data-bs-toggle="tooltip" data-bs-placement="top"
                                                    title="{{ $product->status ? 'Deactivate' : 'Activate' }}">
                                                    <i
                                                        class="{{ $product->status ? 'ri-lock-line' : 'ri-check-line' }}"></i>
                                                </a>
                                                @if ($isSuperAdmin)
                                                    <form method="POST"
                                                        action="{{ route('admin.products.destroy', $product->id) }}"
                                                        style="display:inline;" class="delete-product-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="button"
                                                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-danger-full delete-product-btn"
                                                            data-bs-toggle="tooltip" data-bs-placement="top"
                                                            title="Delete" data-product-name="{{ $product->product }}">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Product Modal -->
    <div id="view-product" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-eye-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">View Product</h5>
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#view-product">
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
        <div class="ti-offcanvas-body">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12">
                    <div class="box">
                        <div class="box-body bg-gray-50">
                            <div id="view-product-content">
                                <!-- Product details will be loaded here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Product Modal -->
    <div id="edit-product" class="hs-overlay hidden ti-offcanvas ti-offcanvas-right" tabindex="-1">
        <div class="ti-offcanvas-header">
            <div class="flex items-center">
                <div class="me-4 gap-0">
                    <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                        <i class="ri-edit-box-line"></i>
                    </span>
                </div>
                <div class="flex-grow">
                    <div class="flex items-center justify-between">
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Edit Product</h5>
                        <button type="button"
                            class="ti-btn flex-shrink-0 p-0 transition-none text-gray-500 hover:text-gray-700 focus:ring-gray-400 focus:ring-offset-white dark:text-[#8c9097] dark:text-white/50 dark:hover:text-white/80 dark:focus:ring-white/10 dark:focus:ring-offset-white/10"
                            data-hs-overlay="#edit-product">
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
        <form class="ti-custom-validation" id="edit-product-form" method="POST" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" id="edit-product-id" name="product_id">
            <div class="ti-offcanvas-body">
                <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12">
                        <div class="box">
                            <div class="box-body bg-gray-50">
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <div class="xl:col-span-12 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product Name <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" name="product" id="edit-product-name"
                                            class="ti-form-input rounded-sm form-control-sm" required>
                                        @error('product', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                        <span class="text-red-500 text-xs error-message" id="edit-product-error"></span>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">

                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_private" id="edit-is_private"
                                                class="ti-form-checkbox me-2" value="1">
                                            <label for="edit-is_private"
                                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Private
                                                Charter</label>
                                        </div>
                                        @error('is_private', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                        <span class="text-red-500 text-xs error-message" id="edit-private-error"></span>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">

                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_airambulance" id="edit-is_airambulance"
                                                class="ti-form-checkbox me-2" value="1">
                                            <label for="edit-is_airambulance"
                                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Air
                                                Ambulance</label>
                                        </div>
                                        @error('is_airambulance', 'edit')
                                            <span class="text-red-500 text-xs">{{ $message }}</span>
                                        @enderror
                                        <span class="text-red-500 text-xs error-message"
                                            id="edit-airambulance-error"></span>
                                    </div>
                                </div>
                                <div class="mt-5">
                                    <button type="submit"
                                        class="ti-btn bg-theme ti-btn-primary-full ti-custom-validate-btn">Update
                                        Product</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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
    <!-- Delete Confirmation Modal -->
    <div id="delete-product-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
        <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
            <button type="button" class="btn-close ms-auto" id="close-delete-modal">
                <i class="bi bi-x"></i>
            </button>
            <div class="text-center px-[3rem] pb-0">
                <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Delete</h5>
                <p class="mb-4 text-gray-600" id="delete-modal-message"></p>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="cancel-delete">Cancel</button>
                    <button type="button" class="ti-btn bg-danger text-white px-4 py-1"
                        id="confirm-delete-product">Delete</button>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM Content Loaded');

            let productIdToToggle = null;
            let currentStatus = null;
            let $buttonElement = null;
            let $deleteForm = null;
            let $deleteButton = null;
            let deleteProductName = '';

            // Add Product Form Submission
            $('#add-product-form-element').on('submit', function(e) {
                e.preventDefault();
                clearErrorMessages();

                const formData = new FormData(this);

                $.ajax({
                    url: "{{ route('admin.products.store') }}",
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            $('#add-product-form-element')[0].reset();
                            $('#add-is_airambulance').prop('checked', true); // Reset to default
                            // Hide the accordion
                            $('#add-product-form').slideUp();
                            $('.hs-accordion-toggle svg').removeClass('hidden');
                            $('.hs-accordion-toggle svg:first-child').addClass('block')
                                .removeClass('hidden');
                            $('.hs-accordion-toggle svg:last-child').addClass('hidden')
                                .removeClass('block');
                            // Reload the page to show the new product
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        if (response && response.errors) {
                            displayValidationErrors(response.errors, 'add');
                        } else {
                            showToast('error', response?.message || 'An error occurred');
                        }
                    }
                });
            });

            // Checkbox validation for add form
            $('#add-is_private, #add-is_airambulance').on('change', function() {
                const isPrivate = $('#add-is_private').is(':checked');
                const isAirambulance = $('#add-is_airambulance').is(':checked');

                if (isPrivate && isAirambulance) {
                    $(this).prop('checked', false);
                    showToast('error', 'You cannot select both Private Charter and Air Ambulance');
                }
            });

            // View Product - Updated to use HSOverlay and fetch data
            $(document).on('click', '.view-product', function(e) {
                e.preventDefault();
                const productId = $(this).data('id');
                console.log('View product clicked, ID:', productId);

                $.ajax({
                    url: "{{ route('admin.products.view', ':id') }}".replace(':id', productId),
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        console.log('View response:', response);
                        if (response.success) {
                            const product = response.product;
                            const content = `
                                <div class="grid grid-cols-12 sm:gap-6">
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product Name</label>
                                        <p class="text-gray-800 dark:text-white">${product.product}</p>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Private Charter</label>
                                        <p class="text-gray-800 dark:text-white">${product.is_private == 1 ? '<span class="badge bg-success/10 text-success">Yes</span>' : '<span class="badge bg-secondary/10 text-secondary">No</span>'}</p>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Air Ambulance</label>
                                        <p class="text-gray-800 dark:text-white">${product.is_airambulance == 1 ? '<span class="badge bg-success/10 text-success">Yes</span>' : '<span class="badge bg-secondary/10 text-secondary">No</span>'}</p>
                                    </div>                                  
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                                        <p class="text-gray-800 dark:text-white">${product.status == 1 ? '<span class="badge bg-success/10 text-success">Active</span>' : '<span class="badge bg-danger/10 text-danger">Inactive</span>'}</p>
                                    </div>
                                    <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                                        <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created Date</label>
                                        <p class="text-gray-800 dark:text-white">${new Date(product.created_at).toLocaleDateString()}</p>
                                    </div>                                  
                                </div>
                            `;
                            $('#view-product-content').html(content);
                            // Open the HSOverlay programmatically to ensure it appears
                            const modalEl = document.getElementById('view-product');
                            if (window.HSOverlay && window.HSOverlay.open) {
                                window.HSOverlay.open(modalEl);
                            } else {
                                // fallback: toggle class if HSOverlay not available
                                $(modalEl).removeClass('hidden');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('View error:', xhr);
                        const response = xhr.responseJSON;
                        showToast('error', response?.message || 'Failed to load product data');
                    }
                });
            });

            // Edit Product - Updated to use HSOverlay and fetch data
            $(document).on('click', '.edit-product', function(e) {
                e.preventDefault();
                const productId = $(this).data('id');
                console.log('Edit product clicked, ID:', productId);

                $.ajax({
                    url: "{{ route('admin.products.edit', ':id') }}".replace(':id', productId),
                    type: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        console.log('Edit response:', response);
                        if (response.success) {
                            const product = response.product;
                            $('#edit-product-id').val(product.id);
                            $('#edit-product-name').val(product.product);
                            $('#edit-is_private').prop('checked', product.is_private == 1);
                            $('#edit-is_airambulance').prop('checked', product
                                .is_airambulance == 1);
                            clearErrorMessages();

                            // Open the edit HSOverlay programmatically
                            const editModalEl = document.getElementById('edit-product');
                            if (window.HSOverlay && window.HSOverlay.open) {
                                window.HSOverlay.open(editModalEl);
                            } else {
                                $(editModalEl).removeClass('hidden');
                            }
                        }
                    },
                    error: function(xhr) {
                        console.error('Edit error:', xhr);
                        const response = xhr.responseJSON;
                        showToast('error', response?.message || 'Failed to load product data');
                    }
                });
            });

            // Edit Product Form Submission
            $('#edit-product-form').on('submit', function(e) {
                e.preventDefault();
                clearErrorMessages();

                const productId = $('#edit-product-id').val();
                const formData = new FormData(this);

                $.ajax({
                    url: "{{ route('admin.products.update', ':id') }}".replace(':id', productId),
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    success: function(response) {
                        if (response.success) {
                            showToast('success', response.message);
                            // Close the modal using HSOverlay
                            const modal = document.getElementById('edit-product');
                            if (window.HSOverlay && window.HSOverlay.close) {
                                window.HSOverlay.close(modal);
                            }
                            // Update the table row
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        if (response && response.errors) {
                            displayValidationErrors(response.errors, 'edit');
                        } else {
                            showToast('error', response?.message || 'An error occurred');
                        }
                    }
                });
            });

            // Checkbox validation for edit form
            $('#edit-is_private, #edit-is_airambulance').on('change', function() {
                const isPrivate = $('#edit-is_private').is(':checked');
                const isAirambulance = $('#edit-is_airambulance').is(':checked');

                if (isPrivate && isAirambulance) {
                    $(this).prop('checked', false);
                    showToast('error', 'You cannot select both Private Charter and Air Ambulance');
                }
            });

            $('#cancel-add-product').on('click', function() {
                $('#add-product-form-element')[0].reset();
                $('#add-is_airambulance').prop('checked', true);
                clearErrorMessages();
            });

            // Toggle product status functionality
            $(document).on('click', '.toggle-product-status', function(e) {
                e.preventDefault();
                productIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                $buttonElement = $(this);
                const productName = $(this).closest('tr').find('td:nth-child(2)').text().trim();

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(
                    `Are you sure you want to ${action} "${productName}" product?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            // Delete product functionality
            $(document).on('click', '.delete-product-btn', function(e) {
                e.preventDefault();
                $deleteButton = $(this);
                $deleteForm = $deleteButton.closest('form');
                deleteProductName = $deleteButton.data('product-name');
                $('#delete-modal-message').text(
                    `Are you sure you want to delete "${deleteProductName}" product?`);
                $('#delete-product-modal').removeClass('hidden');
            });

            $('#confirm-delete-product').click(function() {
                if ($deleteForm) {
                    $deleteForm.submit();
                }
                $('#delete-product-modal').addClass('hidden');
                $deleteForm = null;
                $deleteButton = null;
                deleteProductName = '';
            });

            $('#cancel-delete, #close-delete-modal').click(function() {
                $('#delete-product-modal').addClass('hidden');
                $deleteForm = null;
                $deleteButton = null;
                deleteProductName = '';
            });

            $('#confirm-status-toggle').click(function() {
                if (!productIdToToggle) return;

                const url = "{{ route('admin.products.toggle-status', ':id') }}".replace(':id',
                    productIdToToggle);

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

                            const newStatus = response.new_status;

                            $buttonElement.find('i')
                                .removeClass(newStatus ? 'ri-check-line' : 'ri-lock-line')
                                .addClass(newStatus ? 'ri-lock-line' : 'ri-check-line');

                            $buttonElement
                                .removeClass(newStatus ? 'ti-btn-success-full' :
                                    'ti-btn-danger-full')
                                .addClass(newStatus ? 'ti-btn-danger-full' :
                                    'ti-btn-success-full');
                            $buttonElement.attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            // Status column is the 5th column in the table (S.No, Product Name, Private Charter, Air Ambulance, Status, Actions)
                            const statusCell = $buttonElement.closest('tr').find('td:nth-child(5)');
                            statusCell.html(newStatus ?
                                '<span class="badge bg-success/10 text-success">Active</span>' :
                                '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                            );
                            // Update the data-status attribute on the toggle button to reflect the new status
                            $buttonElement.data('status', newStatus);
                        } else {
                            showToast('error', response.message || "Operation failed");
                        }
                    },
                    error: function(xhr) {
                        showToast('error', xhr.responseJSON?.message ||
                            "Server error occurred");
                        $('#toggle-status-modal').addClass('hidden');
                    }
                });
            });

            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                productIdToToggle = null;
                currentStatus = null;
                $buttonElement = null;
            });

            // Utility functions
            function clearErrorMessages() {
                $('.error-message').text('');
            }

            function displayValidationErrors(errors, prefix) {
                $.each(errors, function(field, messages) {
                    $(`#${prefix}-${field.replace('_', '-')}-error`).text(messages[0]);
                });
            }

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

        function clearFilters() {
            $('#filter-form')[0].reset();
            window.location.href = "{{ route('admin.products.index') }}";
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

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

            // Add Product Accordion Toggle
            $('.hs-accordion-toggle').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('aria-controls');
                const targetElement = $('#' + target);
                const svgIcons = $(this).find('svg');

                if (targetElement.hasClass('hidden')) {
                    targetElement.removeClass('hidden').slideDown(300);
                    svgIcons.eq(0).addClass('hidden').removeClass('block');
                    svgIcons.eq(1).addClass('block').removeClass('hidden');
                } else {
                    targetElement.slideUp(300, function() {
                        targetElement.addClass('hidden');
                    });
                    svgIcons.eq(0).addClass('block').removeClass('hidden');
                    svgIcons.eq(1).addClass('hidden').removeClass('block');
                }
            });
        });
    </script>
@endpush
