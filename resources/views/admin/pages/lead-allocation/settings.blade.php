@extends('admin.layouts.header')

@section('content')

    <div class="block justify-between page-header md:flex">

        <div>

            <h3 class="text-xl font-semibold">
                Lead Allocation Settings
            </h3>

            <p class="text-sm text-gray-500 mt-1">
                Configure office allocation and email lead product routing.
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
        {{-- EMAIL LEAD PRODUCT ROUTING --}}
        {{-- ========================================================== --}}

        <div class="box mt-4">

            <div class="box-header">

                <div>

                    <div class="box-title">
                        Email Lead Product Assignment
                    </div>

                    <p class="text-sm text-gray-500 mt-1">
                        Select which email products each salesperson can receive.
                        The same product can be assigned to multiple Executive.
                        These settings apply only to automatically fetched email leads.
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

                                        <select
                                            name="email_product_assignments[{{ $user->id }}][]"
                                            class="ti-form-select email-product-select"
                                            multiple
                                            data-placeholder="Select Email Products"
                                        >

                                            @foreach($products as $product)

                                                <option
                                                    value="{{ $product->id }}"
                                                    {{
                                                        in_array(
                                                            (string) $product->id,
                                                            $selectedProducts,
                                                            true
                                                        )
                                                            ? 'selected'
                                                            : ''
                                                    }}
                                                >
                                                    {{ $product->product }}
                                                </option>

                                            @endforeach

                                        </select>

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