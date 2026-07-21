@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Service Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.services.index') }}">
                    Services
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50" aria-current="page">
                {{ $service->service }}
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
                        <a aria-label="anchor" href="{{ route('admin.services.edit', $service->id) }}"
                            class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" data-bs-toggle="tooltip" data-bs-placement="top" title="Edit"> <i class="ri-edit-line"></i>
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $service->service }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Amount</label>
                            <p class="text-gray-800 dark:text-white">{{ config('settings.currency_symbol') }}{{ number_format($service->service_amount, 2) }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                            <p class="text-gray-800 dark:text-white">{{ $service->description ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created At</label>
                            <p class="text-gray-800 dark:text-white">{{ $service->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Updated At</label>
                            <p class="text-gray-800 dark:text-white">{{ $service->updated_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                            <p class="text-gray-800 dark:text-white">
                                @if($service->status == 1)
                                    <span class="badge bg-success/10 text-success">Active</span>
                                @else
                                    <span class="badge bg-danger/10 text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                        <div class="xl:col-span-4 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Related Product</label>
                            <p class="text-gray-800 dark:text-white">
                                @php
                                    $products = $service->getProducts();
                                @endphp
                                @if($products->isNotEmpty())
                                    {{ $products->pluck('product')->join(', ') }}
                                @else
                                    <span class="text-muted">No product assigned</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Terms & Conditions Section -->
            @if($service->terms_and_conditions)
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Terms & Conditions</h5>
                </div>
                <div class="box-body">
                    <div class="prose dark:prose-invert max-w-none">
                        {!! $service->terms_and_conditions !!}
                    </div>
                </div>
            </div>
            @endif

            <!-- Extra Services Section -->
            {{-- <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Extra Services ({{ $service->extraServices->count() }})</h5>
                </div>
                <div class="box-body">
                    @if($service->extraServices->count() > 0)
                        <div class="overflow-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                <thead class="bg-gray-50 dark:bg-black/20">
                                    <tr>
                                        <th class="px-6 py-3 text-start">#</th>
                                        <th class="px-6 py-3 text-start">Service Name</th>
                                        <th class="px-6 py-3 text-start">Amount</th>
                                        <th class="px-6 py-3 text-start">Description</th>
                                        <th class="px-6 py-3 text-start">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                    @foreach($service->extraServices as $index => $extraService)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $index + 1 }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                {{ $extraService->extra_service }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                {{ number_format($extraService->extra_service_amount, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                {{ $extraService->description ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($extraService->status == 1)
                                                    <span class="badge bg-success/10 text-success">Active</span>
                                                @else
                                                    <span class="badge bg-danger/10 text-danger">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">No extra services available for this service.</p>
                    @endif
                </div>
            </div> --}}
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any necessary scripts here
        });
    </script>
@endsection
