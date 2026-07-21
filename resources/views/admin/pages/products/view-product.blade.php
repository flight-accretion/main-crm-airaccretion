@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Product Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.products.index') }}">
                    Products
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50" aria-current="page">
                {{ $product->product }}
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Basic Information</h5>
                    <div class="flex gap-2">
                        <a aria-label="anchor" href="{{ route('admin.products.edit', $product->id) }}" 
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit">
                            <i class="ri-edit-line"></i>
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 sm:gap-6">
                        <!-- <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Vendor</label>
                            <p class="text-gray-800 dark:text-white">{{ $product->vendor->name ?? 'N/A' }}</p>
                        </div> -->
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $product->product }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                            <p class="text-gray-800 dark:text-white">
                                @if($product->status == 1)
                                    <span class="badge bg-success/10 text-success">Active</span>
                                @else
                                    <span class="badge bg-danger/10 text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Is Private</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" disabled {{ $product->is_private ? 'checked' : '' }}>
                            </div>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Is Air Ambulance</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" disabled {{ $product->is_airambulance ? 'checked' : '' }}>
                            </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any necessary scripts here
        });
    </script>
@endsection