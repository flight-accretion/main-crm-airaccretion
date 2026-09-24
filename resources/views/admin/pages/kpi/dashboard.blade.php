@extends('admin.layouts.header')

@section('content')

@php
    $team = collect($team ?? []);

    /*
     * Build one KPI row for each configured metric.
     * Metadata comes from KPI Management / KpiDashboardService.
     */
    $matrixRows = $team
        ->flatMap(function ($row) {
            return collect($row['metrics'] ?? []);
        })
        ->groupBy('code')
        ->map(function ($metrics) {
            return $metrics->first();
        })
        ->sortBy('sort_order');

    $totalWeightage = $matrixRows->sum(
        fn ($metric) =>
            (float) ($metric['weightage'] ?? 0)
    );

    $departmentTitle =
        ($department ?? 'sales') === 'sales'
            ? 'Retail Department'
            : ucfirst($department) . ' Department';
@endphp


<div class="flex justify-betwwen mb-3">
<h3 class="text-[1.125rem] font-semibold p-5">KPI Dashboad</h3>
    <button
        type="button"
        id="kpiImproveAllButton"
        class="ti-btn ti-btn-primary"
    >
        <i class="bx bx-trending-up me-1"></i>
        How to Improve Score
    </button>

</div>

{{-- KPI DATE FILTER --}}
<div class="box mb-4">
    <div class="box-body">

        <form
            method="GET"
            action="{{ route('admin.kpi.index') }}"
            class="grid grid-cols-12 gap-4 items-end"
        >
            @if($canSwitchDepartment ?? false)
                <div class="xl:col-span-3 md:col-span-6 col-span-12">

                    <label class="ti-form-label">
                        Department
                    </label>

                    <select
                        name="department"
                        class="form-control"
                    >
                        @foreach([
                            'sales' => 'Retail Sales',
                            'operations' => 'Operations',
                            'accounts' => 'Accounts',
                        ] as $departmentKey => $departmentLabel)
                            <option
                                value="{{ $departmentKey }}"
                                {{ ($department ?? 'sales') === $departmentKey ? 'selected' : '' }}
                            >
                                {{ $departmentLabel }}
                            </option>
                        @endforeach
                    </select>

                </div>
            @endif

            <div class="xl:col-span-3 md:col-span-6 col-span-12">

                <label class="ti-form-label">
                    Date
                </label>

                <select
                    name="preset"
                    id="kpi-date-type"
                    class="form-control"
                >

                    <option
                        value="this_month"
                        {{ ($filter['preset'] ?? 'this_month') === 'this_month' ? 'selected' : '' }}
                    >
                        This Month
                    </option>

                    <option
                        value="today"
                        {{ ($filter['preset'] ?? '') === 'today' ? 'selected' : '' }}
                    >
                        Today
                    </option>

                    <option
                        value="yesterday"
                        {{ ($filter['preset'] ?? '') === 'yesterday' ? 'selected' : '' }}
                    >
                        Yesterday
                    </option>

                    <option
                        value="custom"
                        {{ ($filter['preset'] ?? '') === 'custom' ? 'selected' : '' }}
                    >
                        Custom Date
                    </option>

                </select>

            </div>


            <div
                class="xl:col-span-3 md:col-span-6 col-span-12 kpi-custom-date-field"
                style="{{ ($filter['preset'] ?? 'this_month') === 'custom' ? '' : 'display:none' }}"
            >

                <label class="ti-form-label">
                    From Date
                </label>

                <input
                    type="date"
                    name="from_date"
                    class="form-control"
                    value="{{ request('from_date', $filter['from']->toDateString()) }}"
                >

            </div>


            <div
                class="xl:col-span-3 md:col-span-6 col-span-12 kpi-custom-date-field"
                style="{{ ($filter['preset'] ?? 'this_month') === 'custom' ? '' : 'display:none' }}"
            >

                <label class="ti-form-label">
                    To Date
                </label>

                <input
                    type="date"
                    name="to_date"
                    class="form-control"
                    value="{{ request('to_date', $filter['to']->toDateString()) }}"
                >

            </div>


            <div class="xl:col-span-3 md:col-span-6 col-span-12">

                <button
                    type="submit"
                    class="ti-btn ti-btn-primary w-full"
                >
                    Filter
                </button>

            </div>

        </form>


        <div class="mt-3 text-sm text-gray-500">
            Showing:
            <strong>
                {{ $filter['from']->format('d M Y') }}
                -
                {{ $filter['to']->format('d M Y') }}
            </strong>
        </div>

    </div>
</div>


<div class="box">
    <div class="box-body p-0">

        <div class="grid grid-cols-12 gap-3 items-end p-4 border-b">
            <div class="xl:col-span-5 md:col-span-6 col-span-12">
                <label class="ti-form-label" for="kpi-dashboard-search">
                    Search
                </label>
                <input
                    type="search"
                    id="kpi-dashboard-search"
                    class="form-control"
                    placeholder="Search scope, score, result, employee"
                >
            </div>

            <div class="xl:col-span-2 md:col-span-3 col-span-6">
                <label class="ti-form-label" for="kpi-dashboard-page-size">
                    Rows
                </label>
                <select
                    id="kpi-dashboard-page-size"
                    class="form-control"
                >
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="all">All</option>
                </select>
            </div>

            <div class="xl:col-span-5 md:col-span-3 col-span-6 text-sm text-gray-500 md:text-right">
                <span id="kpi-dashboard-count"></span>
            </div>
        </div>

        <div class="overflow-x-auto">

        <table
            class="table table-bordered whitespace-nowrap min-w-full"
            data-kpi-dashboard-table
        >

            <thead>

                {{-- ============================================
                     HEADER ROW 1
                     ============================================ --}}
                <tr
                    style="
                        background:#f7dea0;
                        color:#111827;
                    "
                >

                    <th
                        rowspan="3"
                        class="align-middle text-center"
                        style="min-width:420px;"
                    >
                        Scope of Work
                    </th>

                    <th
                        rowspan="3"
                        class="align-middle text-center"
                        style="min-width:100px;"
                    >
                        Weightage
                    </th>

                    <th
                        colspan="5"
                        class="text-center"
                    >
                        {{ $departmentTitle }}
                    </th>

                    @foreach($team as $row)

                        <th
                            colspan="2"
                            class="text-center"
                        >
                            {{ $row['user']->name ?? '-' }}
                        </th>

                    @endforeach

                </tr>


                {{-- ============================================
                     HEADER ROW 2
                     ============================================ --}}
                <tr
                    style="
                        background:#f7dea0;
                        color:#111827;
                    "
                >

                    <th
                        colspan="5"
                        class="text-center"
                    >
                        Rating Scale
                    </th>

                    @foreach($team as $row)

                        <th
                            colspan="2"
                            class="text-center"
                        >
                            KPI Result
                        </th>

                    @endforeach

                </tr>


                {{-- ============================================
                     HEADER ROW 3
                     ============================================ --}}
                <tr
                    style="
                        background:#f7dea0;
                        color:#111827;
                    "
                >

                    @foreach([5, 4, 3, 2, 1] as $score)

                        <th class="text-center">
                            {{ $score }}
                        </th>

                    @endforeach


                    @foreach($team as $row)

                        <th class="text-center">
                            Score
                        </th>

                        <th class="text-center">
                            Result
                        </th>

                    @endforeach

                </tr>

            </thead>


            <tbody>

                @forelse(
                    $matrixRows
                    as $code => $metricMeta
                )

                    <tr data-kpi-dashboard-row>

                        {{-- Scope --}}
                        <td
                            class="whitespace-normal"
                            style="min-width:420px;"
                        >

                            <div class="font-semibold">

                                {{
                                    $metricMeta['name']
                                        ?? '-'
                                }}

                            </div>

                            @if(
                                !empty(
                                    $metricMeta[
                                        'description'
                                    ]
                                )
                            )

                                <div
                                    class="
                                        text-xs
                                        text-gray-500
                                        mt-1
                                    "
                                >
                                    {{
                                        $metricMeta[
                                            'description'
                                        ]
                                    }}
                                </div>

                            @endif

                        </td>


                        {{-- Weightage --}}
                        <td class="text-center">

                            {{
                                rtrim(
                                    rtrim(
                                        number_format(
                                            (float) (
                                                $metricMeta[
                                                    'weightage'
                                                ]
                                                ?? 0
                                            ),
                                            2
                                        ),
                                        '0'
                                    ),
                                    '.'
                                )
                            }}

                        </td>


                        {{-- Rating scale --}}
                        @foreach(
                            [5, 4, 3, 2, 1]
                            as $score
                        )

                            <td class="text-center">

                                {{
                                    $metricMeta[
                                        'rating_labels'
                                    ][$score]
                                    ??
                                    $metricMeta[
                                        'score_rules'
                                    ][$score]
                                    ??
                                    '-'
                                }}

                            </td>

                        @endforeach


                        {{-- Employee-specific itemized scores --}}
                        @foreach($team as $row)

                            @php
                                $metric =
                                    collect(
                                        $row['metrics']
                                        ?? []
                                    )
                                    ->firstWhere(
                                        'code',
                                        $code
                                    );
                            @endphp


                            <td class="text-center">

                                @if($metric)

                                    @include(
                                        'admin.pages.kpi.partials.metric-score-button',
                                        [
                                            'metric'
                                                => $metric
                                        ]
                                    )

                                @else

                                    -

                                @endif

                            </td>


                            <td
                                class="
                                    whitespace-normal
                                "
                                style="min-width:170px;"
                            >

                                {{
                                    $metric[
                                        'display_actual'
                                    ]
                                    ?? '-'
                                }}

                            </td>

                        @endforeach

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="{{
                                7
                                +
                                (
                                    $team->count()
                                    * 2
                                )
                            }}"
                            class="text-center py-6"
                        >

                            No KPI template has been
                            assigned.

                        </td>

                    </tr>

                @endforelse


                {{-- ============================================
                     TOTAL
                     ============================================ --}}
                @if($matrixRows->isNotEmpty())

                    <tr
                        class="font-semibold"
                        style="background:#f7dea0;"
                        data-kpi-dashboard-total-row
                    >

                        <td>
                            Total Weightage
                        </td>

                        <td class="text-center">

                            {{
                                rtrim(
                                    rtrim(
                                        number_format(
                                            $totalWeightage,
                                            2
                                        ),
                                        '0'
                                    ),
                                    '.'
                                )
                            }}

                        </td>

                        <td colspan="5"></td>


                        @foreach($team as $row)

                            <td class="text-center">

                                @if(
                                    $row[
                                        'configured'
                                    ]
                                    ?? false
                                )

                                    {{
                                        (int) (
                                            $row[
                                                'overall_score'
                                            ]
                                            ?? 1
                                        )
                                    }}/5

                                @else

                                    Not configured

                                @endif

                            </td>

                            <td>
                                Overall KPI
                            </td>

                        @endforeach

                    </tr>

                @endif

            </tbody>

        </table>

        </div>

        <div
            id="kpi-dashboard-pagination"
            class="flex flex-wrap justify-end gap-2 p-4 border-t"
        ></div>

    </div>

</div>


{{--
    Hidden popup only.
    Nothing is displayed until a score is clicked.
    This preserves the previously approved one-line
    Laravel improvement guidance.
--}}

<script
    type="application/json"
    id="kpiImprovementData"
>
@json($improvementUsers ?? [])
</script>

@include(
    'admin.pages.kpi.partials.metric-score-modal'
)


<script>
function initializeKpiDashboardTable() {
    const table =
        document.querySelector(
            '[data-kpi-dashboard-table]'
        );

    if (!table) {
        return;
    }

    const searchInput =
        document.getElementById(
            'kpi-dashboard-search'
        );

    const pageSizeInput =
        document.getElementById(
            'kpi-dashboard-page-size'
        );

    const countLabel =
        document.getElementById(
            'kpi-dashboard-count'
        );

    const pagination =
        document.getElementById(
            'kpi-dashboard-pagination'
        );

    const rows =
        Array.from(
            table.querySelectorAll(
                '[data-kpi-dashboard-row]'
            )
        );

    const totalRow =
        table.querySelector(
            '[data-kpi-dashboard-total-row]'
        );

    let currentPage = 1;

    function currentPageSize() {
        if (!pageSizeInput || pageSizeInput.value === 'all') {
            return rows.length || 1;
        }

        return Number(pageSizeInput.value) || 25;
    }

    function matchingRows() {
        const term =
            (searchInput?.value || '')
                .trim()
                .toLowerCase();

        if (!term) {
            return rows;
        }

        return rows.filter(function (row) {
            return row.textContent
                .toLowerCase()
                .includes(term);
        });
    }

    function renderPagination(totalPages) {
        if (!pagination) {
            return;
        }

        pagination.innerHTML = '';

        if (totalPages <= 1) {
            return;
        }

        const previous =
            document.createElement('button');
        previous.type = 'button';
        previous.className = 'ti-btn ti-btn-light';
        previous.textContent = 'Previous';
        previous.disabled = currentPage === 1;
        previous.addEventListener('click', function () {
            currentPage = Math.max(1, currentPage - 1);
            render();
        });
        pagination.appendChild(previous);

        for (let page = 1; page <= totalPages; page += 1) {
            const button =
                document.createElement('button');
            button.type = 'button';
            button.className =
                page === currentPage
                    ? 'ti-btn ti-btn-primary'
                    : 'ti-btn ti-btn-light';
            button.textContent = page;
            button.addEventListener('click', function () {
                currentPage = page;
                render();
            });
            pagination.appendChild(button);
        }

        const next =
            document.createElement('button');
        next.type = 'button';
        next.className = 'ti-btn ti-btn-light';
        next.textContent = 'Next';
        next.disabled = currentPage === totalPages;
        next.addEventListener('click', function () {
            currentPage = Math.min(totalPages, currentPage + 1);
            render();
        });
        pagination.appendChild(next);
    }

    function render() {
        const filtered = matchingRows();
        const pageSize = currentPageSize();
        const totalPages =
            Math.max(
                1,
                Math.ceil(filtered.length / pageSize)
            );

        currentPage =
            Math.min(currentPage, totalPages);

        const start =
            (currentPage - 1) * pageSize;

        const end =
            start + pageSize;

        rows.forEach(function (row) {
            row.style.display = 'none';
        });

        filtered
            .slice(start, end)
            .forEach(function (row) {
                row.style.display = '';
            });

        if (totalRow) {
            totalRow.style.display =
                filtered.length ? '' : 'none';
        }

        if (countLabel) {
            countLabel.textContent =
                filtered.length
                    ? 'Showing '
                        + (start + 1)
                        + '-'
                        + Math.min(end, filtered.length)
                        + ' of '
                        + filtered.length
                        + ' KPI rows'
                    : 'No KPI rows found';
        }

        renderPagination(totalPages);
    }

    searchInput?.addEventListener(
        'input',
        function () {
            currentPage = 1;
            render();
        }
    );

    pageSizeInput?.addEventListener(
        'change',
        function () {
            currentPage = 1;
            render();
        }
    );

    render();
}

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const typeSelect =
            document.getElementById(
                'kpi-date-type'
            );

        const customFields =
            document.querySelectorAll(
                '.kpi-custom-date-field'
            );

        if (!typeSelect) {
            return;
        }

        function toggleCustomDates() {

            customFields.forEach(
                function (field) {

                    field.style.display =
                        typeSelect.value === 'custom'
                            ? ''
                            : 'none';
                }
            );
        }

        typeSelect.addEventListener(
            'change',
            toggleCustomDates
        );

        toggleCustomDates();

        initializeKpiDashboardTable();
    }
);
</script>

@endsection
