@extends('admin.layouts.header')

@section('content')

<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/daily-outreach.css') }}?v=1">

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
@php
    $standardCount = $standard->count();
    $extraCount = $extra->count();
    $completionPercent = $dailyTarget > 0
        ? min(100, (int) round(($standardCompletedToday / $dailyTarget) * 100))
        : 0;
@endphp

<div class="crm-daily-outreach">
<div class="do-page-head">
    <div>
        <h1>Daily Customer Outreach</h1>
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
        <p class="do-muted">
            Complete verified customer outreach from your rolling queue.
        </p>
    </div>

    @if(
    !$isAdminMonitor
    &&
    $canRequestExtra
)
        <form method="POST" action="{{ route('admin.kpi.outreach.extra') }}">
            @csrf
            <button class="do-button do-button--primary">
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

<div class="do-notice">
    Save the DNP or remark action on a queue row. Existing call-verification rules continue to decide when an action counts toward the KPI.
</div>

<section class="do-summary" aria-label="Daily outreach progress">
    <div class="do-summary-card">
        <span class="do-muted">Today's standard target</span>
        <strong>{{ (int) $standardCompletedToday }} / {{ (int) $dailyTarget }}</strong>
        <div class="do-progress" aria-label="{{ $completionPercent }} percent complete"><span style="width: {{ $completionPercent }}%"></span></div>
        <small>{{ max(0, (int) $dailyTarget - (int) $standardCompletedToday) }} remaining</small>
    </div>
    <div class="do-summary-card">
        <strong>{{ (int) $standardCompletedToday }}</strong>
        <small>Completed today</small>
    </div>
    <div class="do-summary-card">
        <strong>{{ (int) $standardCount }}</strong>
        <small>Standard numbers in queue</small>
    </div>
    <div class="do-summary-card">
        <strong>{{ (int) $extraCount }}</strong>
        <small>Extra numbers in queue</small>
    </div>
</section>

<form method="GET" action="{{ route('admin.kpi.outreach.index') }}" class="do-filter">
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
            <div class="do-field">
                <label for="outreach-number">Mobile Number</label>
                <input
                    type="text"
                    id="outreach-number"
                    name="number"
                    value="{{ $filters['number'] ?? '' }}"
                    placeholder="Search mobile number"
                >
            </div>

            <div class="do-field">
                <label for="outreach-date">Outreach Date</label>
                <input
                    type="date"
                    id="outreach-date"
                    name="date"
                    value="{{ $filters['date'] ?? '' }}"
                >
            </div>

            <div class="do-filter-actions">
                <button class="do-button do-button--primary">Filter</button>
                <a href="{{ route('admin.kpi.outreach.index') }}" class="do-button">
                    Reset
                </a>
            </div>
        </form>

<div class="do-queue-head">
    <div>
        <h2>Standard Rolling Queue</h2>
        <p class="do-muted">Search, pagination, and queue actions are kept from the existing page.</p>
    </div>
    <span class="do-muted">{{ $standardCount }} assigned</span>
</div>
@include('admin.pages.kpi.partials.outreach-table', ['rows' => $standard, 'locked' => $standardLocked])

@if($extra->isNotEmpty())
    <div class="do-queue-head mt-6">
        <div>
            <h2>Extra Batch</h2>
            <p class="do-muted">{{ $extraCount }} remaining</p>
        </div>
    </div>
    @include('admin.pages.kpi.partials.outreach-table', ['rows' => $extra, 'locked' => false])
@endif
</div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-copy-phone]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var phone = button.getAttribute('data-copy-phone');

                    if (!phone || !navigator.clipboard) {
                        return;
                    }

                    navigator.clipboard.writeText(phone).then(function () {
                        var original = button.textContent;
                        button.textContent = 'Copied';
                        window.setTimeout(function () {
                            button.textContent = original;
                        }, 1200);
                    });
                });
            });

            if (!window.jQuery || !$.fn.DataTable) {
                return;
            }

            $('.kpi-outreach-datatable').each(function () {
                if ($.fn.DataTable.isDataTable(this)) {
                    return;
                }

                if ($(this).find('tbody td[colspan]').length) {
                    return;
                }

                $(this).DataTable({
                    pageLength: 10,
                    lengthMenu: [10, 25, 50, 100],
                    responsive: false,
                    scrollX: true,
                    autoWidth: false,
                    ordering: true,
                    columnDefs: [
                        { orderable: false, targets: [4, 5, 6] }
                    ]
                });
            });
        });
    </script>
@endpush
