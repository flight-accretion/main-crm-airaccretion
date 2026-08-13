@extends('admin.layouts.header')

@section('content')

    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3
                class="!text-defaulttextcolor dark:!text-defaulttextcolor/70
                       dark:text-white text-[1.125rem] font-semibold"
            >
                Lead Transfer Requests
            </h3>
        </div>

        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a
                    class="flex items-center text-primary hover:text-primary truncate"
                    href="{{ route('admin.clients.index') }}"
                >
                    Leads

                    <i
                        class="ti ti-chevrons-right flex-shrink-0
                               text-[#8c9097] dark:text-white/50
                               px-[0.5rem] overflow-visible
                               rtl:rotate-180"
                    ></i>
                </a>
            </li>

            <li
                class="text-[0.813rem] text-defaulttextcolor
                       font-semibold dark:text-white/50"
                aria-current="page"
            >
                Transfer Leads
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->


    <!-- Success Message -->
    @if(session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif


    <!-- Error Message -->
    @if(session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif


    <!-- Validation Errors -->
    @if($errors->any())
        <div class="alert alert-danger mb-4">

            @foreach($errors->all() as $error)
                <div>
                    {{ $error }}
                </div>
            @endforeach

        </div>
    @endif


    <div class="grid grid-cols-12 gap-6">

        <div class="xl:col-span-12 col-span-12">

            <div class="box custom-box">

                <!-- Box Header -->
                <div class="box-header flex justify-between items-center">

                    <div class="box-title">
                        Lead Transfer Requests
                    </div>

                    <div>
                        @if($isSuperAdmin)
                            <span class="badge bg-primary/10 text-primary">
                                Showing All Requests
                            </span>
                        @else
                            <span class="badge bg-info/10 text-info">
                                My Transfer Requests
                            </span>
                        @endif
                    </div>

                </div>
                <!-- Box Header Close -->


                <div class="box-body">

                    <!--
                        DataTables itself creates the horizontal scroll wrapper.
                        overflow is also kept as fallback.
                    -->
                    <div class="overflow-auto">

                        <table
                            id="lead-transfer-table"
                            class="table display nowrap lead-transfer-datatable"
                            style="width:100%"
                        >

                            <thead class="bg-primary text-white">

                                <tr class="border-b border-defaultborder">

                                    <th data-priority="1">
                                        S.No
                                    </th>

                                    <th data-priority="2">
                                        Request Date
                                    </th>

                                    <th data-priority="3">
                                        Client Name
                                    </th>

                                    <th data-priority="4">
                                        Phone
                                    </th>

                                    <th data-priority="5">
                                        Lead Owner
                                    </th>

                                    <th data-priority="6">
                                        Requested By
                                    </th>

                                    <th data-priority="7">
                                        Requested For
                                    </th>

                                    <th data-priority="8">
                                        Reason
                                    </th>

                                    <th data-priority="9">
                                        Status
                                    </th>

                                    <th data-priority="10">
                                        Responded By
                                    </th>

                                    <th data-priority="11">
                                        Responded Date
                                    </th>

                                    <th data-priority="12">
                                        Response Note
                                    </th>

                                    <th
                                        data-priority="13"
                                        class="text-center"
                                        style="width:220px; min-width:220px;"
                                    >
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @foreach($transfers as $index => $transfer)

                                    @php

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Permission
                                        |--------------------------------------------------------------------------
                                        |
                                        | Super Admin:
                                        |     Can approve/reject any pending request.
                                        |
                                        | Normal Sales User:
                                        |     Can approve/reject only when the lead
                                        |     currently belongs to them.
                                        |
                                        */

                                        $canApprove =
                                            $transfer->status === 'pending'
                                            &&
                                            (
                                                $isSuperAdmin
                                                ||
                                                (string) $transfer->from_user_id
                                                    ===
                                                    (string) auth()->id()
                                            );

                                        $isRequester =
                                            (string) $transfer->requested_by
                                            ===
                                            (string) auth()->id();

                                    @endphp


                                    <tr class="border-b border-defaultborder">

                                        <!-- S.No -->
                                        <td>
                                            {{ $index + 1 }}
                                        </td>


                                        <!-- Request Date -->
                                        <td
                                            data-order="{{ optional($transfer->created_at)->timestamp ?? 0 }}"
                                        >
                                            {{ optional($transfer->created_at)->format('d-m-Y H:i') ?? '-' }}
                                        </td>


                                        <!-- Client Name -->
                                        <td>

                                            @if($transfer->lead && $transfer->lead->client)

                                                <a
                                                    href="{{ route(
                                                        'admin.leads.view',
                                                        $transfer->lead_id
                                                    ) }}"
                                                    class="text-primary hover:underline font-medium"
                                                    target="_blank"
                                                >
                                                    {{ $transfer->lead->client->name }}
                                                </a>

                                            @else

                                                <span class="text-muted">
                                                    N/A
                                                </span>

                                            @endif

                                        </td>


                                        <!-- Phone -->
                                        <td>
                                            {{
                                                optional(
                                                    optional($transfer->lead)->client
                                                )->contact_number
                                                ?? 'N/A'
                                            }}
                                        </td>


                                        <!-- Current / Original Lead Owner -->
                                        <td>
                                            {{
                                                optional($transfer->fromUser)->name
                                                ?? 'N/A'
                                            }}
                                        </td>


                                        <!-- Requested By -->
                                        <td>
                                            {{
                                                optional($transfer->requestedBy)->name
                                                ?? 'N/A'
                                            }}
                                        </td>


                                        <!-- Requested For -->
                                        <td>

                                            {{
                                                optional($transfer->toUser)->name
                                                ?? 'N/A'
                                            }}

                                            @if(
                                                (string) $transfer->to_user_id
                                                ===
                                                (string) auth()->id()
                                            )

                                                <span
                                                    class="badge bg-info/10 text-info ms-1"
                                                >
                                                    You
                                                </span>

                                            @endif

                                        </td>


                                        <!-- Reason -->
                                        <td
                                            style="
                                                min-width:220px;
                                                max-width:350px;
                                                white-space:normal;
                                            "
                                        >

                                            {{ $transfer->reason ?: '-' }}

                                        </td>


                                        <!-- Status -->
                                        <td data-order="{{ $transfer->status }}">

                                            @if($transfer->status === 'pending')

                                                <span
                                                    class="badge bg-warning/10 text-warning"
                                                >
                                                    Pending
                                                </span>


                                            @elseif($transfer->status === 'accepted')

                                                <span
                                                    class="badge bg-success/10 text-success"
                                                >
                                                    Accepted
                                                </span>


                                            @elseif($transfer->status === 'rejected')

                                                <span
                                                    class="badge bg-danger/10 text-danger"
                                                >
                                                    Rejected
                                                </span>


                                            @elseif($transfer->status === 'cancelled')

                                                <span
                                                    class="badge bg-default/10 text-default"
                                                >
                                                    Cancelled
                                                </span>


                                            @else

                                                <span
                                                    class="badge bg-default/10 text-default"
                                                >
                                                    {{ ucfirst($transfer->status) }}
                                                </span>

                                            @endif

                                        </td>


                                        <!-- Responded By -->
                                        <td>

                                            {{
                                                optional($transfer->respondedBy)->name
                                                ?? '-'
                                            }}

                                        </td>


                                        <!-- Responded Date -->
                                        <td
                                            data-order="{{
                                                optional($transfer->responded_at)->timestamp
                                                ?? 0
                                            }}"
                                        >

                                            {{
                                                $transfer->responded_at
                                                    ? $transfer->responded_at->format(
                                                        'd-m-Y H:i'
                                                    )
                                                    : '-'
                                            }}

                                        </td>


                                        <!-- Response Note -->
                                        <td
                                            style="
                                                min-width:200px;
                                                max-width:350px;
                                                white-space:normal;
                                            "
                                        >

                                            {{ $transfer->response_note ?: '-' }}

                                        </td>


                                        <!-- Action -->
                                        <td
                                            class="text-center"
                                            style="width:220px; min-width:220px;"
                                        >

                                            <div
                                                class="inline-flex gap-3 items-center justify-center whitespace-nowrap"
                                                style="min-width:190px;"
                                            >

                                                <!-- View Lead -->
                                                <!-- @if($transfer->lead_id)

                                                    <a
                                                        href="{{ route(
                                                            'admin.leads.view',
                                                            $transfer->lead_id
                                                        ) }}"
                                                        class="
                                                            ti-btn
                                                            ti-btn-icon
                                                            ti-btn-sm
                                                            ti-btn-primary-full
                                                        "
                                                        target="_blank"
                                                        title="View Lead"
                                                    >
                                                        <i class="ri-eye-line"></i>
                                                    </a>

                                                @endif -->


                                                <!-- Can Approve / Reject -->
                                                @if($canApprove)


                                                    <!-- Accept -->
                                                    <form
                                                        method="POST"
                                                        action="{{ route(
                                                            'admin.leads.transfer.accept',
                                                            $transfer->id
                                                        ) }}"
                                                        class="inline-block"
                                                    >

                                                        @csrf

                                                        <button
                                                            type="submit"
                                                            class="
                                                                ti-btn
                                                                ti-btn-sm
                                                                ti-btn-success-full
                                                                inline-flex
                                                                items-center
                                                                justify-center
                                                                gap-1
                                                                whitespace-nowrap
                                                                !px-3
                                                                !py-1.5
                                                                !text-white
                                                            "
                                                            style="
                                                                min-width:84px;
                                                                background-color:#16a34a;
                                                                border-color:#16a34a;
                                                            "
                                                            onclick="
                                                                return confirm(
                                                                    'Are you sure you want to approve this lead transfer request?'
                                                                );
                                                            "
                                                        >
                                                            <i
                                                                class="ri-check-line me-1"
                                                            ></i>

                                                            Accept
                                                        </button>

                                                    </form>


                                                    <!-- Reject Button -->
                                                    <button
                                                        type="button"
                                                        class="
                                                            ti-btn
                                                            ti-btn-sm
                                                            ti-btn-danger-full
                                                            inline-flex
                                                            items-center
                                                            justify-center
                                                            gap-1
                                                            whitespace-nowrap
                                                            !px-3
                                                            !py-1.5
                                                            !text-white
                                                            reject-transfer-btn
                                                        "
                                                        style="
                                                            min-width:84px;
                                                            background-color:#dc2626;
                                                            border-color:#dc2626;
                                                        "
                                                        data-transfer-id="{{ $transfer->id }}"
                                                        data-client-name="{{
                                                            optional(
                                                                optional($transfer->lead)->client
                                                            )->name
                                                            ?? 'this lead'
                                                        }}"
                                                    >

                                                        <i
                                                            class="ri-close-line me-1"
                                                        ></i>

                                                        Reject

                                                    </button>


                                                @elseif(
                                                    $transfer->status === 'pending'
                                                    &&
                                                    $isRequester
                                                )

                                                    <span
                                                        class="badge bg-warning/10 text-warning"
                                                    >
                                                        Waiting for Approval
                                                    </span>


                                                @elseif(
                                                    $transfer->status === 'pending'
                                                )

                                                    <span
                                                        class="text-muted"
                                                    >
                                                        Pending
                                                    </span>


                                                @else

                                                    <span
                                                        class="text-muted"
                                                    >
                                                        Processed
                                                    </span>

                                                @endif

                                            </div>

                                        </td>

                                    </tr>

                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>



    {{-- ============================================================= --}}
    {{-- Reject Transfer Modal --}}
    {{-- ============================================================= --}}

    <div
        id="lead-transfer-reject-modal"
        class="hs-overlay hidden ti-modal"
    >

        <div
            class="
                hs-overlay-open:mt-7
                ti-modal-box
                mt-0
                ease-out
            "
        >

            <div class="ti-modal-content">

                <div class="ti-modal-header">

                    <h6 class="modal-title">
                        Reject Lead Transfer
                    </h6>

                    <button
                        type="button"
                        class="
                            hs-dropdown-toggle
                            !text-[1rem]
                            !font-semibold
                            !text-defaulttextcolor
                        "
                        data-hs-overlay="#lead-transfer-reject-modal"
                    >
                        <span class="sr-only">
                            Close
                        </span>

                        <i class="ri-close-line"></i>
                    </button>

                </div>


                <form
                    method="POST"
                    id="reject-transfer-form"
                    action=""
                >

                    @csrf

                    <div class="ti-modal-body">

                        <div class="mb-4">

                            <p
                                id="reject-transfer-message"
                                class="
                                    text-sm
                                    text-defaulttextcolor
                                    dark:text-white/70
                                "
                            >
                                Are you sure you want to reject this request?
                            </p>

                        </div>


                        <div>

                            <label class="ti-form-label">
                                Rejection Note
                            </label>

                            <textarea
                                name="response_note"
                                class="form-control"
                                rows="4"
                                maxlength="1000"
                                placeholder="Enter rejection reason (optional)"
                            ></textarea>

                        </div>

                    </div>


                    <div class="ti-modal-footer">

                        <button
                            type="button"
                            class="ti-btn ti-btn-light"
                            data-hs-overlay="#lead-transfer-reject-modal"
                        >
                            Cancel
                        </button>


                        <button
                            type="submit"
                            class="ti-btn ti-btn-danger"
                        >

                            <i class="ri-close-line me-1"></i>

                            Reject Transfer

                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>



    {{-- ============================================================= --}}
    {{-- DataTable + Reject Modal JS --}}
    {{-- ============================================================= --}}

    <script>

        $(document).ready(function () {

            /*
            |--------------------------------------------------------------------------
            | Lead Transfer DataTable
            |--------------------------------------------------------------------------
            |
            | Uses the DataTables libraries already loaded by the CRM.
            | No separate CSS or JS library is required.
            |
            */

            if (
                $.fn.DataTable
                &&
                !$.fn.DataTable.isDataTable(
                    '#lead-transfer-table'
                )
            ) {

                var transferTable =
                    $('#lead-transfer-table').DataTable({

                        /*
                        |--------------------------------------------------------------------------
                        | Search
                        |--------------------------------------------------------------------------
                        */

                        searching: true,


                        /*
                        |--------------------------------------------------------------------------
                        | Pagination + Show Rows
                        |--------------------------------------------------------------------------
                        */

                        paging: true,

                        lengthChange: true,

                        pageLength: 25,

                        lengthMenu: [
                            [10, 25, 50, 100, -1],
                            [10, 25, 50, 100, 'All']
                        ],


                        /*
                        |--------------------------------------------------------------------------
                        | Table Information
                        |--------------------------------------------------------------------------
                        */

                        info: true,


                        /*
                        |--------------------------------------------------------------------------
                        | Horizontal scrolling
                        |--------------------------------------------------------------------------
                        |
                        | No columns will be hidden.
                        | User can scroll left/right automatically when needed.
                        |
                        */

                        responsive: false,

                        scrollX: true,

                        scrollCollapse: true,

                        autoWidth: false,


                        /*
                        |--------------------------------------------------------------------------
                        | Default Sorting
                        |--------------------------------------------------------------------------
                        |
                        | Request Date = second column.
                        |
                        */

                        order: [
                            [1, 'desc']
                        ],


                        /*
                        |--------------------------------------------------------------------------
                        | Action column should not sort
                        |--------------------------------------------------------------------------
                        */

                        columnDefs: [

                            {
                                targets: 12,
                                orderable: false,
                                searchable: false
                            }

                        ],


                        /*
                        |--------------------------------------------------------------------------
                        | Language
                        |--------------------------------------------------------------------------
                        */

                        language: {

                            search:
                                'Search:',

                            lengthMenu:
                                'Show _MENU_ rows',

                            info:
                                'Showing _START_ to _END_ of _TOTAL_ requests',

                            infoEmpty:
                                'Showing 0 to 0 of 0 requests',

                            zeroRecords:
                                'No matching lead transfer requests found',

                            emptyTable:
                                'No lead transfer requests found'

                        },


                        /*
                        |--------------------------------------------------------------------------
                        | Adjust table after initial render
                        |--------------------------------------------------------------------------
                        */

                        initComplete: function () {

                            this.api()
                                .columns
                                .adjust();

                        },


                        drawCallback: function () {

                            var api =
                                this.api();

                            /*
                             * Re-number S.No based on current
                             * filtered / sorted page.
                             */
                            var pageInfo =
                                api.page.info();

                            api.column(
                                0,
                                {
                                    page: 'current'
                                }
                            )
                            .nodes()
                            .each(
                                function (
                                    cell,
                                    index
                                ) {

                                    cell.innerHTML =
                                        pageInfo.start
                                        +
                                        index
                                        +
                                        1;

                                }
                            );

                            api.columns.adjust();

                        }

                    });


                /*
                 * Recalculate widths when browser size changes.
                 */
                $(window).on(
                    'resize.leadTransferTable',
                    function () {

                        transferTable
                            .columns
                            .adjust();

                    }
                );

            }



            /*
            |--------------------------------------------------------------------------
            | Reject Transfer
            |--------------------------------------------------------------------------
            */

            $(document).on(
                'click',
                '.reject-transfer-btn',
                function () {

                    var transferId =
                        $(this).data(
                            'transfer-id'
                        );

                    var clientName =
                        $(this).data(
                            'client-name'
                        );


                    /*
                     * Build route from Laravel route pattern.
                     */
                    var rejectUrl =
                        "{{ route(
                            'admin.leads.transfer.reject',
                            ':transfer'
                        ) }}";

                    rejectUrl =
                        rejectUrl.replace(
                            ':transfer',
                            transferId
                        );


                    $('#reject-transfer-form')
                        .attr(
                            'action',
                            rejectUrl
                        );


                    $('#reject-transfer-message')
                        .text(
                            'Are you sure you want to reject the lead transfer request for '
                            +
                            clientName
                            +
                            '?'
                        );


                    /*
                     * Open existing CRM HS Overlay modal.
                     */
                    if (
                        window.HSOverlay
                        &&
                        typeof HSOverlay.open
                            ===
                            'function'
                    ) {

                        HSOverlay.open(
                            document.querySelector(
                                '#lead-transfer-reject-modal'
                            )
                        );

                    } else {

                        $('#lead-transfer-reject-modal')
                            .removeClass(
                                'hidden'
                            );

                    }

                }
            );

        });

    </script>

@endsection
