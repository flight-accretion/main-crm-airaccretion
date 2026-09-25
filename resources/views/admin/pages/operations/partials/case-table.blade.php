{{--
    Leads-style table for Operations cases.

    Expects:
    $cases
    $latestFollowups
    $tableId
    $mode ("queue" | "history")
    $filterAction

    Review Queue Indicator:
    Yellow = customer has not replied
    Red    = Review Agent sentiment is negative
    Green  = Review Agent sentiment is positive
    Gray   = neutral / uncertain / still being analysed
--}}

@php
    $isQueue = ($mode ?? 'queue') === 'queue';
    $filters = $filters ?? [];
@endphp


<div class="grid grid-cols-12 gap-6">

    <div class="xl:col-span-12 col-span-12">

        <div class="box custom-box">

            <div
                class="box-header flex justify-between items-center"
            >

                <div class="box-title">
                    {{ $listTitle }}
                </div>

                <div class="flex gap-3 items-center">

                    <div class="flex items-center gap-2">

                        <label
                            for="per-page-select"
                            class="text-sm whitespace-nowrap"
                        >
                            Show
                        </label>

                        <select
                            id="per-page-select"
                            name="per_page"
                            form="filter-form"
                            class="ti-form-select rounded-sm form-control-sm"
                            style="width: 80px;"
                        >

                            @foreach ([10, 25, 50, 100] as $size)

                                <option
                                    value="{{ $size }}"
                                    {{
                                        (int) (
                                            $filters['per_page']
                                            ?? 25
                                        ) === $size
                                            ? 'selected'
                                            : ''
                                    }}
                                >
                                    {{ $size }}
                                </option>

                            @endforeach

                        </select>

                        <label
                            class="text-sm whitespace-nowrap"
                        >
                            entries
                        </label>

                    </div>


                    <div class="search-container">

                        <input
                            type="text"
                            id="global-search"
                            name="search"
                            form="filter-form"
                            class="ti-form-input rounded-sm form-control-sm"
                            placeholder="Search"
                            value="{{ $filters['search'] ?? '' }}"
                            style="min-width: 250px;"
                        >

                    </div>

                </div>

            </div>


            <div class="box-body">

                <div class="table-responsive">

                    <table
                        id="{{ $tableId }}"
                        class="table display responsive nowrap lead-datatable"
                        width="100%"
                    >

                        <thead class="bg-primary text-white">

                            <tr
                                class="border-b border-defaultborder"
                            >

                                <th data-priority="1">
                                    S.No
                                </th>

                                <th data-priority="2">
                                    Client Name
                                </th>

                                <th data-priority="6">
                                    Phone
                                </th>


                                @unless ($isQueue)

                                    <th data-priority="6">
                                        Type
                                    </th>

                                    <th data-priority="6">
                                        Completed
                                    </th>

                                @endunless


                                <th data-priority="6">
                                    Next Follow Up
                                </th>

                                <th data-priority="8">
                                    Assigned:
                                </th>

                                <th data-priority="9">
                                    Service Date:
                                </th>

                                <th
                                    data-priority="10"
                                    class="lead-service-column"
                                >
                                    Service:
                                </th>

                                <th data-priority="1">
                                    Status
                                </th>

                                <th data-priority="1">
                                    Action
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                            @forelse ($cases as $key => $case)

                                @php
                                    $lead =
                                        $case->lead;

                                    $client =
                                        optional(
                                            $lead
                                        )->client;

                                    $segments =
                                        optional(
                                            $lead
                                        )->rideSegments
                                        ?? collect();

                                    $leadStatus =
                                        optional(
                                            $latestFollowups->get(
                                                $case->lead_id
                                            )
                                        )->status;

                                    $leadStatus =
                                        $leadStatus === null
                                            ? null
                                            : (int) $leadStatus;


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Review Response Indicator
                                    |--------------------------------------------------------------------------
                                    |
                                    | Only calculated for active Review queue.
                                    |
                                    | Yellow:
                                    | Customer hasn't replied.
                                    |
                                    | Red:
                                    | Customer replied and Review Agent says negative.
                                    |
                                    | Green:
                                    | Customer replied and Review Agent says positive.
                                    |
                                    | Gray:
                                    | Neutral / uncertain / AI analysis pending.
                                    |
                                    */

                                    $reviewDotColor = null;
                                    $reviewDotLabel = null;

                                    if (
                                        $isQueue
                                        && $case->type === 'review'
                                    ) {

                                        $review =
                                            $case->reviewConversation;

                                        if (
                                            !$review
                                            || !$review->customer_replied
                                        ) {

                                            $reviewDotColor =
                                                '#eab308';

                                            $reviewDotLabel =
                                                'No response';

                                        } elseif (
                                            $review->sentiment === 'negative'
                                        ) {

                                            $reviewDotColor =
                                                '#ef4444';

                                            $reviewDotLabel =
                                                'Angry / Negative';

                                        } elseif (
                                            $review->sentiment === 'positive'
                                        ) {

                                            $reviewDotColor =
                                                '#22c55e';

                                            $reviewDotLabel =
                                                'Good / Positive';

                                        } elseif (
                                            $review->sentiment === 'neutral'
                                        ) {

                                            $reviewDotColor =
                                                '#9ca3af';

                                            $reviewDotLabel =
                                                'Neutral';

                                        } else {

                                            $reviewDotColor =
                                                '#9ca3af';

                                            $reviewDotLabel =
                                                $review
                                                && $review->customer_replied
                                                    ? 'Awaiting AI analysis'
                                                    : 'No response';
                                        }
                                    }
                                @endphp


                                <tr
                                    class="border-b border-defaultborder"
                                >

                                    <td class="text-center">

                                        {{
                                            $cases->firstItem()
                                                ? $cases->firstItem()
                                                    + $key
                                                : $key + 1
                                        }}

                                    </td>


                                    <td>
                                        {{
                                            optional(
                                                $client
                                            )->name
                                            ?? 'N/A'
                                        }}
                                    </td>


                                    <td class="text-center">
                                        {{
                                            optional(
                                                $client
                                            )->contact_number
                                            ?? 'N/A'
                                        }}
                                    </td>


                                    @unless ($isQueue)

                                        <td class="text-center">

                                            {{
                                                ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $case->type
                                                    )
                                                )
                                            }}

                                        </td>


                                        <td class="text-center">

                                            {{
                                                optional(
                                                    $case->completed_at
                                                )->format(
                                                    'd-m-Y H:i'
                                                )
                                                ?? 'N/A'
                                            }}

                                        </td>

                                    @endunless


                                    <td class="text-center">

                                        {{
                                            optional(
                                                $case->next_followup_at
                                            )->format(
                                                'd-m-Y H:i'
                                            )
                                            ?? 'N/A'
                                        }}

                                    </td>


                                    <td>

                                        {{
                                            optional(
                                                optional(
                                                    $lead
                                                )->representative
                                            )->name
                                            ?? 'N/A'
                                        }}

                                    </td>


                                    <td>

                                        @if ($segments->count() > 0)

                                            From:
                                            {{
                                                date(
                                                    'd-m-Y',
                                                    strtotime(
                                                        $segments
                                                            ->first()
                                                            ->from_date
                                                    )
                                                )
                                            }}

                                            To:
                                            {{
                                                date(
                                                    'd-m-Y',
                                                    strtotime(
                                                        $segments
                                                            ->last()
                                                            ->to_date
                                                    )
                                                )
                                            }}

                                        @else

                                            N/A

                                        @endif

                                    </td>


                                    <td class="lead-service-column">

                                        @include(
                                            'admin.pages.leads.partials.service-preview',
                                            [
                                                'serviceNames' =>
                                                    $lead
                                                        ? (
                                                            $lead
                                                                ->service_names
                                                            ?? []
                                                        )
                                                        : [],
                                            ]
                                        )

                                    </td>


                                    <td class="text-center">

                                        @include(
                                            'admin.pages.operations.partials.lead-status-badge',
                                            [
                                                'status' =>
                                                    $leadStatus,
                                            ]
                                        )

                                    </td>


                                    <td>

                                        <div
                                            class="hstack flex gap-3 text-[.9375rem]"
                                            style="align-items:center;"
                                        >

                                            {{--
                                                REVIEW SENTIMENT DOT
                                            --}}

                                            @if (
                                                $isQueue
                                                && $case->type === 'review'
                                                && $reviewDotColor
                                            )

                                                <span
                                                    title="{{ $reviewDotLabel }}"
                                                    aria-label="{{ $reviewDotLabel }}"
                                                    style="
                                                        display:inline-block;
                                                        width:13px;
                                                        height:13px;
                                                        min-width:13px;
                                                        border-radius:50%;
                                                        background-color:{{ $reviewDotColor }};
                                                        border:2px solid rgba(255,255,255,.95);
                                                        box-shadow:
                                                            0 0 0 1px rgba(0,0,0,.12);
                                                    "
                                                ></span>

                                            @endif


                                            {{--
                                                EXISTING ADD FOLLOW-UP
                                            --}}

                                            @if ($isQueue)

                                                <a
                                                    aria-label="Add Follow-up"
                                                    href="{{
                                                        route(
                                                            'admin.operations.followups.create',
                                                            $case
                                                        )
                                                    }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                    title="Add Follow-up"
                                                >

                                                    <i
                                                        class="ri-add-line"
                                                    ></i>

                                                </a>

                                            @endif


                                            {{--
                                                EXISTING VIEW LEAD
                                            --}}

                                            @if ($case->lead_id)

                                                <a
                                                    aria-label="View Lead"
                                                    href="{{
                                                        route(
                                                            'admin.leads.view',
                                                            $case->lead_id
                                                        )
                                                    }}"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full"
                                                    target="_blank"
                                                    title="View Lead"
                                                >

                                                    <i
                                                        class="ri-eye-line"
                                                    ></i>

                                                </a>

                                            @endif


                                            {{--
                                                EXISTING COMPLETE ACTION
                                            --}}

                                            @if ($isQueue)

                                                <button
                                                    type="button"
                                                    class="ti-btn ti-btn-icon ti-btn-sm ti-btn-success-full complete-case-btn"
                                                    data-complete-url="{{
                                                        route(
                                                            'admin.operations.case.complete',
                                                            $case
                                                        )
                                                    }}"
                                                    data-client-name="{{
                                                        optional(
                                                            $client
                                                        )->name
                                                        ?? 'this lead'
                                                    }}"
                                                    title="Complete"
                                                >

                                                    <i
                                                        class="ri-check-line"
                                                    ></i>

                                                </button>

                                            @endif

                                        </div>

                                    </td>

                                </tr>


                            @empty

                                <tr>

                                    <td
                                        colspan="{{ $isQueue ? 9 : 11 }}"
                                        class="text-center"
                                    >
                                        {{ $emptyMessage }}
                                    </td>

                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>


                {{ $cases->links() }}

            </div>

        </div>

    </div>

</div>