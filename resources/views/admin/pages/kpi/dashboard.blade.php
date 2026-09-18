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
<h3 class="text-[1.125rem] font-semibold p-5">KPI Dashboad</h3>

<div class="box">
    <div class="box-body overflow-x-auto p-0">

        <table
            class="table table-bordered whitespace-nowrap min-w-full"
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

                    <tr>

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

</div>


{{--
    Hidden popup only.
    Nothing is displayed until a score is clicked.
    This preserves the previously approved one-line
    Laravel improvement guidance.
--}}
@include(
    'admin.pages.kpi.partials.metric-score-modal'
)

@endsection