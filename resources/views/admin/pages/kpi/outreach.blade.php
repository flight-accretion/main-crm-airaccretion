@extends('admin.layouts.header')

@section('content')

@php
    $isAdminMonitor =
        $isAdminMonitor ?? false;

    $availableSalesUsers =
        $availableSalesUsers
        ?? collect();

    $selectedSalesUser =
        $selectedSalesUser
        ?? null;
@endphp
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">Daily Customer Outreach</h3>
        @if($isAdminMonitor)

    <div class="box mb-4">

        <div class="box-body">

            <form
                method="GET"
                action="{{
                    route(
                        'admin.kpi.outreach.index'
                    )
                }}"
                class="
                    grid
                    grid-cols-12
                    gap-4
                    items-end
                "
            >

                <div
                    class="
                        col-span-12
                        md:col-span-5
                    "
                >

                    <label class="form-label">
                        Sales Executive
                    </label>

                    <select
                        name="user_id"
                        class="ti-form-select"
                        required
                    >

                        @foreach(
                            $availableSalesUsers
                            as $salesUser
                        )

                            <option
                                value="{{
                                    $salesUser->id
                                }}"
                                {{
                                    optional(
                                        $selectedSalesUser
                                    )->id
                                    ===
                                    $salesUser->id
                                        ? 'selected'
                                        : ''
                                }}
                            >

                                {{
                                    $salesUser->name
                                }}

                            </option>

                        @endforeach

                    </select>

                </div>


                <div
                    class="
                        col-span-12
                        md:col-span-2
                    "
                >

                    <button
                        type="submit"
                        class="
                            ti-btn
                            ti-btn-primary
                        "
                    >
                        View Queue
                    </button>

                </div>

            </form>


            <div
                class="
                    mt-3
                    text-sm
                    text-gray-500
                "
            >
                Super Admin monitor mode.
                Viewing this page does not
                allocate or replenish customer
                numbers.
            </div>

        </div>

    </div>

@endif
        <p class="text-sm text-gray-500">
            Standard completed today: {{ (int) $standardCompletedToday }} / {{ (int) $dailyTarget }}
        </p>
    </div>

    @if(
    !$isAdminMonitor
    &&
    $canRequestExtra
)
        <form method="POST" action="{{ route('admin.kpi.outreach.extra') }}">
            @csrf
            <button class="ti-btn ti-btn-primary">
                Get More Numbers (+{{ (int) $extraBatchSize }})
            </button>
        </form>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if($standardLocked)
    <div class="alert alert-success">
        Your standard {{ (int) $dailyTarget }} are complete for today. Additional KPI work must use Get More Numbers.
    </div>
@endif

<div class="box mb-4">
    <div class="box-body">
        <form method="GET" action="{{ route('admin.kpi.outreach.index') }}" class="grid grid-cols-12 gap-3 items-end">
            @if(
    $isAdminMonitor
    &&
    $selectedSalesUser
)

    <input
        type="hidden"
        name="user_id"
        value="{{
            $selectedSalesUser->id
        }}"
    >

@endif
            <div class="col-span-12 md:col-span-4">
                <label class="form-label">Mobile Number</label>
                <input
                    type="text"
                    name="number"
                    value="{{ $filters['number'] ?? '' }}"
                    class="ti-form-input"
                    placeholder="Search mobile number"
                >
            </div>

            <div class="col-span-12 md:col-span-3">
                <label class="form-label">Outreach Date</label>
                <input
                    type="date"
                    name="date"
                    value="{{ $filters['date'] ?? '' }}"
                    class="ti-form-input"
                >
            </div>

            <div class="col-span-12 md:col-span-5 flex gap-2">
                <button class="ti-btn ti-btn-primary">Filter</button>
                <a href="{{ route('admin.kpi.outreach.index') }}" class="ti-btn ti-btn-light">
                    Reset
                </a>
            </div>
        </form>
    </div>
</div>

<h5 class="font-semibold mb-2">Standard Rolling Queue ({{ $standard->count() }})</h5>
@include('admin.pages.kpi.partials.outreach-table', ['rows' => $standard, 'locked' => $standardLocked])

@if($extra->isNotEmpty())
    <h5 class="font-semibold mb-2 mt-6">Extra Batch ({{ $extra->count() }} remaining)</h5>
    @include('admin.pages.kpi.partials.outreach-table', ['rows' => $extra, 'locked' => false])
@endif
@endsection
