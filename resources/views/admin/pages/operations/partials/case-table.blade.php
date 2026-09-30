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

                                            <!-- {{
                                                ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $case->type
                                                    )
                                                )
                                            }} -->

                                            @php
                                        $meta = is_array($case->metadata)
                                            ? $case->metadata
                                            : [];

                                        if (
                                            $case->type === 'review'
                                            && !empty($meta['review_completed'])
                                            && empty($meta['image_collection_completed'])
                                        ) {
                                            $displayStatus = 'Image Collection Pending';
                                        } elseif ($case->status === 'completed') {
                                            $displayStatus = 'Completed';
                                        } else {
                                            $displayStatus =
                                                ucfirst(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $case->status
                                                    )
                                                );
                                        }
                                    @endphp

                                    {{ $displayStatus }}

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
@php
    /*
    |--------------------------------------------------------------------------
    | REVIEW / IMAGE COLLECTION WORKFLOW STATUS
    |--------------------------------------------------------------------------
    |
    | New values:
    |
    | review_status:
    |   pending
    |   completed
    |   cancelled
    |
    | image_collection_status:
    |   pending
    |   completed
    |   cancelled
    |
    | Old boolean fields are still supported for backward compatibility.
    |
    */

    $reviewStatus = 'pending';

    $imageCollectionStatus = 'pending';


    if (
        $case->type
        ===
        \App\Models\OperationCase::TYPE_REVIEW
    ) {

        /*
        |--------------------------------------------------------------------------
        | Review status
        |--------------------------------------------------------------------------
        */
        $reviewStatus =
            data_get(
                $case->metadata,
                'review_status'
            );


        if (!$reviewStatus) {

            if (
                (bool) data_get(
                    $case->metadata,
                    'review_completed',
                    false
                )
            ) {

                $reviewStatus =
                    'completed';

            } elseif (
                (bool) data_get(
                    $case->metadata,
                    'review_cancelled',
                    false
                )
            ) {

                $reviewStatus =
                    'cancelled';

            } else {

                $reviewStatus =
                    'pending';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Image Collection status
        |--------------------------------------------------------------------------
        */
        $imageCollectionStatus =
            data_get(
                $case->metadata,
                'image_collection_status'
            );


        if (!$imageCollectionStatus) {

            if (
                (bool) data_get(
                    $case->metadata,
                    'image_collection_completed',
                    false
                )
            ) {

                $imageCollectionStatus =
                    'completed';

            } elseif (
                (bool) data_get(
                    $case->metadata,
                    'image_collection_cancelled',
                    false
                )
            ) {

                $imageCollectionStatus =
                    'cancelled';

            } else {

                $imageCollectionStatus =
                    'pending';
            }
        }
    }


    $reviewResolved =
        in_array(
            $reviewStatus,
            [
                'completed',
                'cancelled',
            ],
            true
        );


    $imageCollectionResolved =
        in_array(
            $imageCollectionStatus,
            [
                'completed',
                'cancelled',
            ],
            true
        );


            /*
            |--------------------------------------------------------------------------
            | HUMAN-READABLE OPERATIONS STATUS
            |--------------------------------------------------------------------------
            */
            if (
                $case->type
                ===
                \App\Models\OperationCase::TYPE_REVIEW
            ) {

                if (!$reviewResolved) {

                    $operationsDisplayStatus =
                        'Review Pending';

                } elseif (!$imageCollectionResolved) {

                    $operationsDisplayStatus =
                        'Image Collection Pending';

                } else {

                    $operationsDisplayStatus =
                        'Completed';
                }

            } else {

                $operationsDisplayStatus =
                    ucfirst(
                        str_replace(
                            '_',
                            ' ',
                            $case->status
                        )
                    );
            }
        @endphp

                                   <td>
    <div>
        {{ $operationsDisplayStatus }}
    </div>


    @if(
        $case->type
        ===
        \App\Models\OperationCase::TYPE_REVIEW
    )

        <div class="mt-2 flex flex-wrap gap-1">

            {{-- REVIEW RESULT --}}
            @if($reviewStatus === 'completed')

                <span
                    class="
                        badge
                        bg-success/10
                        text-success
                    "
                >
                    Review Done
                </span>

            @elseif($reviewStatus === 'cancelled')

                <span
                    class="
                        badge
                        bg-danger/10
                        text-danger
                    "
                >
                    Review Cancelled
                </span>

            @else

                <span
                    class="
                        badge
                        bg-warning/10
                        text-warning
                    "
                >
                    Review Pending
                </span>

            @endif


            {{-- IMAGE RESULT --}}
            @if($reviewResolved)

                @if(
                    $imageCollectionStatus
                    ===
                    'completed'
                )

                    <span
                        class="
                            badge
                            bg-success/10
                            text-success
                        "
                    >
                        Image Done
                    </span>

                @elseif(
                    $imageCollectionStatus
                    ===
                    'cancelled'
                )

                    <span
                        class="
                            badge
                            bg-danger/10
                            text-danger
                        "
                    >
                        Image Cancelled
                    </span>

                @else

                    <span
                        class="
                            badge
                            bg-warning/10
                            text-warning
                        "
                    >
                        Image Pending
                    </span>

                @endif

            @endif

        </div>

    @endif
</td>


                                   <td>

    @if(
        $case->type
        ===
        \App\Models\OperationCase::TYPE_REVIEW
    )

        {{--
        |--------------------------------------------------------------------------
        | STAGE 1 - REVIEW
        |--------------------------------------------------------------------------
        --}}
        @if(!$reviewResolved)

            <div class="space-y-3">

                {{-- MARK REVIEW DONE --}}
                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.operations.case.review-complete',
                            $case
                        )
                    }}"
                >
                    @csrf

                    <div class="flex gap-2 items-center">

                        <input
                            type="text"
                            name="note"
                            class="form-control"
                            placeholder="Review completion note"
                        >

                        <button
                            type="submit"
                            class="
                                ti-btn
                                ti-btn-success
                                whitespace-nowrap
                            "
                        >
                            Mark Review as Done
                        </button>

                    </div>
                </form>


                {{-- CANCEL REVIEW --}}
                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.operations.case.review-cancel',
                            $case
                        )
                    }}"
                    onsubmit="
                        return confirm(
                            'Customer review was not received. Are you sure you want to cancel Review?'
                        );
                    "
                >
                    @csrf

                    <div class="flex gap-2 items-center">

                        <input
                            type="text"
                            name="reason"
                            class="form-control"
                            placeholder="Reason review not received"
                            required
                        >

                        <button
                            type="submit"
                            class="
                                ti-btn
                                ti-btn-danger
                                whitespace-nowrap
                            "
                        >
                            Cancel Review
                        </button>

                    </div>
                </form>

            </div>


        {{--
        |--------------------------------------------------------------------------
        | STAGE 2 - IMAGE COLLECTION
        |--------------------------------------------------------------------------
        --}}
        @elseif(!$imageCollectionResolved)

            <div class="mb-3 flex flex-wrap gap-2">

                @if(
                    $reviewStatus
                    ===
                    'completed'
                )

                    <span
                        class="
                            badge
                            bg-success/10
                            text-success
                        "
                    >
                        Review Done
                    </span>

                @elseif(
                    $reviewStatus
                    ===
                    'cancelled'
                )

                    <span
                        class="
                            badge
                            bg-danger/10
                            text-danger
                        "
                    >
                        Review Cancelled
                    </span>

                @endif


                <span
                    class="
                        badge
                        bg-warning/10
                        text-warning
                    "
                >
                    Image Collection Pending
                </span>

            </div>


            <div class="space-y-3">

                {{-- MARK IMAGE COLLECTION DONE --}}
                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.operations.case.image-collection-complete',
                            $case
                        )
                    }}"
                >
                    @csrf

                    <div class="flex gap-2 items-center">

                        <input
                            type="text"
                            name="note"
                            class="form-control"
                            placeholder="Image collection note"
                        >

                        <button
                            type="submit"
                            class="
                                ti-btn
                                ti-btn-success
                                whitespace-nowrap
                            "
                        >
                            Mark Image Collection as Done
                        </button>

                    </div>
                </form>


                {{-- CANCEL IMAGE COLLECTION --}}
                <form
                    method="POST"
                    action="{{
                        route(
                            'admin.operations.case.image-collection-cancel',
                            $case
                        )
                    }}"
                    onsubmit="
                        return confirm(
                            'Customer image/video was not received. Are you sure you want to cancel Image Collection?'
                        );
                    "
                >
                    @csrf

                    <div class="flex gap-2 items-center">

                        <input
                            type="text"
                            name="reason"
                            class="form-control"
                            placeholder="Reason image/video not received"
                            required
                        >

                        <button
                            type="submit"
                            class="
                                ti-btn
                                ti-btn-danger
                                whitespace-nowrap
                            "
                        >
                            Cancel Image Collection
                        </button>

                    </div>
                </form>

            </div>


        {{--
        |--------------------------------------------------------------------------
        | ALREADY RESOLVED
        |--------------------------------------------------------------------------
        |
        | Normally completed Review cases should no longer be present in the
        | active Review queue, but keep this fallback safe.
        |
        --}}
        @else

            <div class="flex flex-wrap gap-2">

                @if(
                    $reviewStatus
                    ===
                    'completed'
                )

                    <span
                        class="
                            badge
                            bg-success/10
                            text-success
                        "
                    >
                        Review Done
                    </span>

                @else

                    <span
                        class="
                            badge
                            bg-danger/10
                            text-danger
                        "
                    >
                        Review Cancelled
                    </span>

                @endif


                @if(
                    $imageCollectionStatus
                    ===
                    'completed'
                )

                    <span
                        class="
                            badge
                            bg-success/10
                            text-success
                        "
                    >
                        Image Done
                    </span>

                @else

                    <span
                        class="
                            badge
                            bg-danger/10
                            text-danger
                        "
                    >
                        Image Cancelled
                    </span>

                @endif

            </div>

        @endif


    @else

        {{--
        |--------------------------------------------------------------------------
        | EXISTING NON-REVIEW OPERATIONS FLOW
        |--------------------------------------------------------------------------
        |
        | Keep this unchanged for:
        | Reschedule
        | Refund
        | Cancelled
        | etc.
        |
        --}}
        <form
            method="POST"
            action="{{
                route(
                    'admin.operations.case.complete',
                    $case
                )
            }}"
            class="flex gap-2 items-center"
        >
            @csrf

            <input
                type="text"
                name="note"
                class="form-control"
                placeholder="Completion note"
            >

            <button
                type="submit"
                class="
                    ti-btn
                    ti-btn-success
                    whitespace-nowrap
                "
            >
                Complete
            </button>

        </form>

    @endif

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