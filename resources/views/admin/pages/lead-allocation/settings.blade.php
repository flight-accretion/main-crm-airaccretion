@extends('admin.layouts.header')

@section('content')

    <div class="block justify-between page-header md:flex">

        <div>

            <h3 class="text-xl font-semibold">
                Lead Allocation Settings
            </h3>

            <p class="text-sm text-gray-500 mt-1">
                Configure office allocation plus Email and WhatCRM product routing.
            </p>

        </div>

    </div>


    {{-- Success Message --}}
    @if(session('success'))

        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>

    @endif


    {{-- Validation Errors --}}
    @if($errors->any())

        <div class="alert alert-danger mb-4">

            @foreach($errors->all() as $error)

                <div>
                    {{ $error }}
                </div>

            @endforeach

        </div>

    @endif


    <form
        method="POST"
        action="{{ route('admin.lead-allocation.settings.update') }}"
    >

        @csrf
        @method('PUT')


        {{-- ========================================================== --}}
        {{-- COMMON LEAD ALLOCATION SETTINGS --}}
        {{-- ========================================================== --}}

        <div class="box">

            <div class="box-header">

                <div class="box-title">
                    Lead Allocation
                </div>

            </div>


            <div class="box-body">

                <div class="grid grid-cols-12 gap-4">


                    {{-- Office Start Time --}}
                    <div
                        class="xl:col-span-4 lg:col-span-4 md:col-span-6 col-span-12"
                    >

                        <label
                            for="office_start_time"
                            class="ti-form-label"
                        >
                            Office Start Time
                        </label>

                        <input
                            type="time"
                            name="office_start_time"
                            id="office_start_time"
                            class="form-control"
                            value="{{ old(
                                'office_start_time',
                                substr(
                                    $settings->office_start_time,
                                    0,
                                    5
                                )
                            ) }}"
                            required
                        >

                    </div>


                    {{-- Office End Time --}}
                    <div
                        class="xl:col-span-4 lg:col-span-4 md:col-span-6 col-span-12"
                    >

                        <label
                            for="office_end_time"
                            class="ti-form-label"
                        >
                            Office End Time
                        </label>

                        <input
                            type="time"
                            name="office_end_time"
                            id="office_end_time"
                            class="form-control"
                            value="{{ old(
                                'office_end_time',
                                substr(
                                    $settings->office_end_time,
                                    0,
                                    5
                                )
                            ) }}"
                            required
                        >

                    </div>


                    {{-- Allocation Method --}}
                    <div
                        class="xl:col-span-4 lg:col-span-4 md:col-span-6 col-span-12"
                    >

                        <label
                            for="allocation_method"
                            class="ti-form-label"
                        >
                            Allocation Method
                        </label>

                        <select
                            name="allocation_method"
                            id="allocation_method"
                            class="ti-form-select"
                            required
                        >

                            <option
                                value="balanced"
                                {{
                                    old(
                                        'allocation_method',
                                        $settings->allocation_method
                                    ) === 'balanced'
                                        ? 'selected'
                                        : ''
                                }}
                            >
                                Balanced
                            </option>

                            <option
                                value="random"
                                {{
                                    old(
                                        'allocation_method',
                                        $settings->allocation_method
                                    ) === 'random'
                                        ? 'selected'
                                        : ''
                                }}
                            >
                                Random
                            </option>

                        </select>

                    </div>


                    {{-- Auto Allocation --}}
                    <div class="col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="auto_allocation_enabled"
                                value="1"
                                {{
                                    old(
                                        'auto_allocation_enabled',
                                        $settings->auto_allocation_enabled
                                    )
                                        ? 'checked'
                                        : ''
                                }}
                            >

                            <span>
                                Auto Lead Allocation Enabled
                            </span>

                        </label>

                    </div>

                </div>

            </div>

        </div>


        {{-- ========================================================== --}}
        {{-- EMAIL / WHATCRM LEAD PRODUCT ROUTING --}}
        {{-- ========================================================== --}}

        <div class="box mt-4">

            <div class="box-header">

                <div>

                    <div class="box-title">
                        Lead Product Assignment
                    </div>

                    <p class="text-sm text-gray-500 mt-1">
                        Select which CRM products each salesperson can receive for Email and WhatCRM leads.
                        The same product can be assigned to multiple Executive.
                        Leave products empty for retail fallback agents.
                        IVR and manual lead allocation remain unchanged.
                    </p>

                </div>

            </div>


            <div class="box-body">

                <div class="overflow-x-auto">

                    <table class="table whitespace-nowrap min-w-full">

                        <thead>

                            <tr class="border-b border-defaultborder">

                                <th class="text-start">
                                    S.No
                                </th>

                                <th class="text-start">
                                    Sales Person
                                </th>

                                <th class="text-start">
                                    Role
                                </th>

                                <th class="text-start">
                                    Email Products
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse($salesUsers as $index => $user)

                                @php

                                    $selectedProducts = old(
                                        'email_product_assignments.' . $user->id,
                                        $emailProductAssignments->get(
                                            $user->id,
                                            []
                                        )
                                    );

                                    $selectedProducts = array_map(
                                        'strval',
                                        (array) $selectedProducts
                                    );

                                @endphp


                                <tr class="border-b border-defaultborder">


                                    {{-- Serial Number --}}
                                    <td>
                                        {{ $index + 1 }}
                                    </td>


                                    {{-- Sales Person --}}
                                    <td>

                                        <div class="font-semibold">
                                            {{ $user->name }}
                                        </div>

                                    </td>


                                    {{-- Sales Role --}}
                                    <td>

                                        <span
                                            class="badge bg-primary/10 text-primary"
                                        >

                                            {{
                                                optional(
                                                    $user->userType
                                                )->user_type
                                                ?? '-'
                                            }}

                                        </span>

                                    </td>


                                    {{-- Email Product Mapping --}}
                                    <td style="min-width: 450px;">

                                        <div data-lead-product-picker>

                                            <div class="rounded-sm border border-defaultborder bg-white dark:bg-bodybg dark:border-white/10">

                                                <div class="p-3 border-b border-defaultborder dark:border-white/10">

                                                    <input
                                                        type="search"
                                                        class="ti-form-input rounded-sm form-control-sm w-full"
                                                        placeholder="Search products"
                                                        aria-label="Search products for {{ $user->name }}"
                                                        data-lead-product-search
                                                    >

                                                </div>

                                                <div
                                                    class="p-3 space-y-2 whitespace-normal"
                                                    style="max-height: 190px; overflow-y: auto;"
                                                    data-lead-product-list
                                                >

                                                    @forelse($products as $product)

                                                        @php
                                                            $productId = (string) $product->id;
                                                            $isSelected = in_array(
                                                                $productId,
                                                                $selectedProducts,
                                                                true
                                                            );
                                                            $searchText = Str::lower(
                                                                trim((string) $product->product)
                                                            );
                                                        @endphp

                                                        <label
                                                            class="lead-product-option flex items-start gap-3 rounded-sm border border-defaultborder/60 p-2 hover:bg-gray-50 dark:border-white/10 dark:hover:bg-white/5"
                                                            data-lead-product-option
                                                            data-search="{{ $searchText }}"
                                                        >

                                                            <input
                                                                type="checkbox"
                                                                name="email_product_assignments[{{ $user->id }}][]"
                                                                value="{{ $product->id }}"
                                                                class="ti-form-checkbox mt-1"
                                                                {{ $isSelected ? 'checked' : '' }}
                                                            >

                                                            <span class="min-w-0">

                                                                <span class="block font-medium text-defaulttextcolor dark:text-defaulttextcolor/70 break-words">
                                                                    {{ $product->product }}
                                                                </span>

                                                            </span>

                                                        </label>

                                                    @empty

                                                        <p class="text-sm text-gray-500 dark:text-white/50">
                                                            No active products available.
                                                        </p>

                                                    @endforelse

                                                    <p
                                                        class="hidden text-sm text-gray-500 dark:text-white/50"
                                                        data-lead-product-empty
                                                    >
                                                        No matching products found.
                                                    </p>

                                                </div>

                                            </div>

                                            <p
                                                class="mt-2 text-xs text-gray-500 dark:text-white/50"
                                                data-lead-product-summary
                                            >
                                                Selected: {{ count($selectedProducts) }}
                                            </p>

                                        </div>

                                    </td>

                                </tr>


                            @empty

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-center py-4 text-gray-500"
                                    >
                                        No active sales users found.
                                    </td>

                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

            </div>

        </div>


        {{-- ========================================================== --}}
        {{-- SAVE --}}
        {{-- ========================================================== --}}

        <div class="flex justify-end mt-4">

            <button
                type="submit"
                class="ti-btn ti-btn-primary"
            >

                <i class="ri-save-line me-1"></i>

                Save Settings

            </button>

        </div>


    </form>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-lead-product-picker]').forEach(function(picker) {
                const searchInput = picker.querySelector('[data-lead-product-search]');
                const options = Array.from(picker.querySelectorAll('[data-lead-product-option]'));
                const emptyMessage = picker.querySelector('[data-lead-product-empty]');
                const summary = picker.querySelector('[data-lead-product-summary]');

                function updatePicker() {
                    const query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
                    let visibleCount = 0;
                    let selectedCount = 0;

                    options.forEach(function(option) {
                        const checkbox = option.querySelector('input[type="checkbox"]');
                        const matches = !query || (option.getAttribute('data-search') || '').includes(query);

                        option.classList.toggle('hidden', !matches);

                        if (matches) {
                            visibleCount++;
                        }

                        if (checkbox && checkbox.checked) {
                            selectedCount++;
                        }
                    });

                    if (emptyMessage) {
                        emptyMessage.classList.toggle('hidden', visibleCount > 0 || options.length === 0);
                    }

                    if (summary) {
                        summary.textContent = 'Selected: ' + selectedCount;
                    }
                }

                if (searchInput) {
                    searchInput.addEventListener('input', updatePicker);
                    searchInput.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                        }
                    });
                }

                options.forEach(function(option) {
                    const checkbox = option.querySelector('input[type="checkbox"]');

                    if (checkbox) {
                        checkbox.addEventListener('change', updatePicker);
                    }
                });

                updatePicker();
            });
        });
    </script>
@endpush
