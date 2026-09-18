@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">
            Work Done
        </h3>
        <p class="text-sm text-gray-500">
            Lead work and Daily Outreach activity.
        </p>
    </div>
</div>

{{-- Filters --}}
<div class="box">
    <div class="box-body">

        <form
            method="GET"
            action="{{ route('admin.kpi.work-done.index') }}"
            class="grid grid-cols-12 gap-4 items-end"
        >

            <div class="xl:col-span-3 md:col-span-6 col-span-12">
                <label class="ti-form-label">
                    Date
                </label>

                <select
                    name="date_type"
                    id="work-date-type"
                    class="form-control"
                >
                    <option
                        value="today"
                        {{ $filters['date_type'] === 'today' ? 'selected' : '' }}
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        {{ $filters['date_type'] === 'yesterday' ? 'selected' : '' }}
                    >
                        Yesterday
                    </option>

                    <option
                        value="custom"
                        {{ $filters['date_type'] === 'custom' ? 'selected' : '' }}
                    >
                        Custom Date
                    </option>
                </select>
            </div>

            <div
                class="xl:col-span-2 md:col-span-6 col-span-12 custom-date-field"
                style="{{ $filters['date_type'] === 'custom' ? '' : 'display:none' }}"
            >
                <label class="ti-form-label">
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    class="form-control"
                    value="{{ $filters['from_date'] }}"
                >
            </div>

            <div
                class="xl:col-span-2 md:col-span-6 col-span-12 custom-date-field"
                style="{{ $filters['date_type'] === 'custom' ? '' : 'display:none' }}"
            >
                <label class="ti-form-label">
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    class="form-control"
                    value="{{ $filters['to_date'] }}"
                >
            </div>

            @if($users->count() > 1)
                <div class="xl:col-span-3 md:col-span-6 col-span-12">

                    <label class="ti-form-label">
                        Sales Executive
                    </label>

                    <select
                        name="user_id"
                        class="form-control"
                    >
                        <option value="">
                            All Team / All Sales
                        </option>

                        @foreach($users as $user)
                            <option
                                value="{{ $user->id }}"
                                {{ $selectedUserId === $user->id ? 'selected' : '' }}
                            >
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </select>

                </div>
            @endif

            <div class="xl:col-span-2 md:col-span-6 col-span-12">
                <button
                    type="submit"
                    class="ti-btn ti-btn-primary w-full"
                >
                    Filter
                </button>
            </div>

        </form>

    </div>
</div>


{{-- SECTION 1 --}}
<div class="mb-3">
    <h5 class="font-semibold text-[1.05rem]">
        Lead Work
    </h5>
</div>

<div class="grid grid-cols-12 gap-6">

    @php
        $leadCards = [
            [
                'type' => 'completed',
                'label' => 'Completed',
                'value' => $summary['completed'],
            ],
            [
                'type' => 'active',
                'label' => 'Active',
                'value' => $summary['active'],
            ],
            [
                'type' => 'cancelled',
                'label' => 'Cancelled',
                'value' => $summary['cancelled'],
            ],
            [
                'type' => 'total',
                'label' => 'Total',
                'value' => $summary['total'],
            ],
        ];
    @endphp

    @foreach($leadCards as $card)

        <div class="xl:col-span-3 md:col-span-6 col-span-12">

            <button
                type="button"
                class="box w-full text-left work-card"
                data-type="{{ $card['type'] }}"
            >
                <div class="box-body">

                    <p class="text-gray-500 mb-2">
                        {{ $card['label'] }}
                    </p>

                    <h2 class="text-2xl font-semibold">
                        {{ (int) $card['value'] }}
                    </h2>

                </div>
            </button>

        </div>

    @endforeach

</div>


{{-- SECTION 2 --}}
<div class="mt-6 mb-3">
    <h5 class="font-semibold text-[1.05rem]">
        Daily Outreach
    </h5>
</div>

<div class="grid grid-cols-12 gap-6">

    <div class="xl:col-span-3 md:col-span-6 col-span-12">

        <button
            type="button"
            class="box w-full text-left work-card"
            data-type="outreach_completed"
        >
            <div class="box-body">

                <p class="text-gray-500 mb-2">
                    Outreach Completed
                </p>

                <h2 class="text-2xl font-semibold">
                    {{ (int) $summary['outreach_completed'] }}
                </h2>

            </div>
        </button>

    </div>


    <div class="xl:col-span-3 md:col-span-6 col-span-12">

        <button
            type="button"
            class="box w-full text-left work-card"
            data-type="outreach_dnp"
        >
            <div class="box-body">

                <p class="text-gray-500 mb-2">
                    Outreach DNP
                </p>

                <h2 class="text-2xl font-semibold">
                    {{ (int) $summary['outreach_dnp'] }}
                </h2>

            </div>
        </button>

    </div>

</div>


{{-- Details --}}
<div
    id="work-done-details-wrapper"
    class="box mt-6"
    style="display:none"
>
    <div class="box-header flex justify-between">

        <h5
            class="box-title"
            id="work-done-details-title"
        >
            Details
        </h5>

        <button
            type="button"
            id="close-work-details"
            class="ti-btn ti-btn-sm ti-btn-light"
        >
            Close
        </button>

    </div>

    <div
        class="box-body"
        id="work-done-details-body"
    >
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const typeSelect =
        document.getElementById('work-date-type');

    const customFields =
        document.querySelectorAll('.custom-date-field');

    if (typeSelect) {
        typeSelect.addEventListener('change', function () {

            customFields.forEach(function (field) {
                field.style.display =
                    typeSelect.value === 'custom'
                        ? ''
                        : 'none';
            });

        });
    }


    document
        .querySelectorAll('.work-card')
        .forEach(function (button) {

            button.addEventListener('click', async function () {

                const params =
                    new URLSearchParams(
                        window.location.search
                    );

                params.set(
                    'type',
                    button.dataset.type
                );

                const url =
                    '{{ route("admin.kpi.work-done.details") }}'
                    + '?'
                    + params.toString();

                const wrapper =
                    document.getElementById(
                        'work-done-details-wrapper'
                    );

                const title =
                    document.getElementById(
                        'work-done-details-title'
                    );

                const body =
                    document.getElementById(
                        'work-done-details-body'
                    );

                wrapper.style.display = '';

                body.innerHTML =
                    '<div class="p-4">Loading...</div>';

                try {

                    const response =
                        await fetch(
                            url,
                            {
                                headers: {
                                    'Accept':
                                        'application/json'
                                }
                            }
                        );

                    if (!response.ok) {
                        throw new Error(
                            'Unable to load details.'
                        );
                    }

                    const data =
                        await response.json();

                    title.textContent =
                        data.title;

                    body.innerHTML =
                        data.html;

                    wrapper.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                } catch (error) {

                    body.innerHTML =
                        '<div class="text-red-500">'
                        + error.message
                        + '</div>';

                }

            });

        });


    document
        .getElementById('close-work-details')
        ?.addEventListener('click', function () {

            document
                .getElementById(
                    'work-done-details-wrapper'
                )
                .style.display = 'none';

        });

});
</script>

@endsection