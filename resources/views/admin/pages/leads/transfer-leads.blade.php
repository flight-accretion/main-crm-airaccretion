@extends('admin.layouts.header')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/leads/transfer-requests.css') }}?v=1">

@php
    $currentUserId = (string) auth()->id();

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT
    |--------------------------------------------------------------------------
    | Keep the existing transfer permission rules exactly the same.
    | This closure is used only for UI grouping/counting.
    */
    $canRespondToTransfer = function ($transfer) use ($isSuperAdmin, $currentUserId): bool {
        $isOwnerOfferedTransfer =
            (string) $transfer->requested_by === (string) $transfer->from_user_id;

        return $transfer->status === 'pending'
            && (
                $isSuperAdmin
                || (
                    $isOwnerOfferedTransfer
                    && (string) $transfer->to_user_id === $currentUserId
                )
                || (
                    !$isOwnerOfferedTransfer
                    && (string) $transfer->from_user_id === $currentUserId
                )
            );
    };

    $needsActionCount = $transfers
        ->filter(fn ($transfer) => $canRespondToTransfer($transfer))
        ->count();

    $myPendingCount = $transfers
        ->filter(
            fn ($transfer) =>
                $transfer->status === 'pending'
                && (string) $transfer->requested_by === $currentUserId
                && !$canRespondToTransfer($transfer)
        )
        ->count();

    $acceptedThisMonth = $transfers
        ->filter(function ($transfer) {
            if ($transfer->status !== 'accepted') {
                return false;
            }

            $date = $transfer->responded_at ?? $transfer->updated_at;

            return $date
                && $date->isSameMonth(now())
                && $date->isSameYear(now());
        })
        ->count();

    $rejectedThisMonth = $transfers
        ->filter(function ($transfer) {
            if ($transfer->status !== 'rejected') {
                return false;
            }

            $date = $transfer->responded_at ?? $transfer->updated_at;

            return $date
                && $date->isSameMonth(now())
                && $date->isSameYear(now());
        })
        ->count();
@endphp

<div class="crm-transfer-requests" data-transfer-workspace>

    <header class="tr-heading">
        <div>
            <h1>Lead Transfer Requests</h1>
            <p>Review transfer requests, pending approvals and processed history.</p>
        </div>

        <a href="{{ route('admin.clients.index') }}" class="tr-btn tr-btn--secondary">
            <i class="ri-arrow-left-line" aria-hidden="true"></i>
            Back to Leads
        </a>
    </header>

    @if(session('success'))
        <div class="alert alert-success mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger mb-4">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger mb-4">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <section class="tr-summary" aria-label="Lead transfer summary">
        <button
            type="button"
            class="tr-summary-card tr-summary-card--navy is-active"
            data-transfer-summary-tab="action"
        >
            <strong>{{ $needsActionCount }}</strong>
            <span>Needs My Action</span>
            <small>Pending your approval</small>
        </button>

        <button
            type="button"
            class="tr-summary-card tr-summary-card--blue"
            data-transfer-summary-tab="mine"
        >
            <strong>{{ $myPendingCount }}</strong>
            <span>My Pending Requests</span>
            <small>Waiting for response</small>
        </button>

        <button
            type="button"
            class="tr-summary-card tr-summary-card--green"
            data-transfer-summary-tab="history"
        >
            <strong>{{ $acceptedThisMonth }}</strong>
            <span>Accepted This Month</span>
            <small>Completed transfers</small>
        </button>

        <button
            type="button"
            class="tr-summary-card tr-summary-card--red"
            data-transfer-summary-tab="history"
        >
            <strong>{{ $rejectedThisMonth }}</strong>
            <span>Rejected This Month</span>
            <small>Processed with response</small>
        </button>
    </section>

    <section class="tr-filter-panel">
        <div class="tr-filter-search">
            <label for="transfer-search">Search</label>
            <div class="tr-search-wrap">
                <i class="ri-search-line" aria-hidden="true"></i>
                <input
                    id="transfer-search"
                    type="search"
                    placeholder="Search customer, phone, owner or requester"
                    autocomplete="off"
                >
            </div>
        </div>

        <div>
            <label for="transfer-status-filter">Status</label>
            <select id="transfer-status-filter">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>

        <div class="tr-filter-actions">
            <button type="button" class="tr-btn tr-btn--secondary" data-reset-transfer-filter>
                Reset
            </button>
        </div>
    </section>

    <section class="tr-workspace">
        <nav class="tr-tabs" aria-label="Transfer request views">
            <button type="button" class="is-active" data-transfer-tab="action">
                Needs My Action
                @if($needsActionCount > 0)
                    <span>{{ $needsActionCount }}</span>
                @endif
            </button>

            <button type="button" data-transfer-tab="mine">
                My Requests
                @if($myPendingCount > 0)
                    <span>{{ $myPendingCount }}</span>
                @endif
            </button>

            <button type="button" data-transfer-tab="history">
                History
            </button>
        </nav>

        <div class="tr-table-wrap">
            <table id="lead-transfer-table" class="tr-table">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Requested</th>
                        <th>Transfer</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th class="tr-text-right">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($transfers as $transfer)
                        @php
                            $isOwnerOfferedTransfer =
                                (string) $transfer->requested_by
                                ===
                                (string) $transfer->from_user_id;

                            /*
                            |--------------------------------------------------------------------------
                            | EXISTING PERMISSION LOGIC - DO NOT CHANGE
                            |--------------------------------------------------------------------------
                            */
                            $canApprove =
                                $transfer->status === 'pending'
                                &&
                                (
                                    $isSuperAdmin
                                    ||
                                    (
                                        $isOwnerOfferedTransfer
                                        &&
                                        (string) $transfer->to_user_id
                                        ===
                                        (string) auth()->id()
                                    )
                                    ||
                                    (
                                        !$isOwnerOfferedTransfer
                                        &&
                                        (string) $transfer->from_user_id
                                        ===
                                        (string) auth()->id()
                                    )
                                );

                            $isRequester =
                                (string) $transfer->requested_by
                                ===
                                (string) auth()->id();

                            if ($canApprove) {
                                $viewBucket = 'action';
                            } elseif (
                                $transfer->status === 'pending'
                                && $isRequester
                            ) {
                                $viewBucket = 'mine';
                            } elseif ($transfer->status !== 'pending') {
                                $viewBucket = 'history';
                            } else {
                                /*
                                 * Fallback for any existing edge-case visible to the user.
                                 * Keep it visible rather than silently hiding it.
                                 */
                                $viewBucket = 'mine';
                            }

                            $clientName =
                                optional(optional($transfer->lead)->client)->name
                                ?? 'N/A';

                            $clientPhone =
                                optional(optional($transfer->lead)->client)->contact_number
                                ?? 'N/A';

                            $fromName =
                                optional($transfer->fromUser)->name
                                ?? 'N/A';

                            $toName =
                                optional($transfer->toUser)->name
                                ?? 'N/A';

                            $requestedByName =
                                optional($transfer->requestedBy)->name
                                ?? 'N/A';

                            $respondedByName =
                                optional($transfer->respondedBy)->name
                                ?? '-';

                            $requestDate =
                                optional($transfer->created_at)->format('d M Y, h:i A')
                                ?? '-';

                            $responseDate =
                                $transfer->responded_at
                                    ? $transfer->responded_at->format('d M Y, h:i A')
                                    : '-';

                            $reason = $transfer->reason ?: '-';
                            $responseNote = $transfer->response_note ?: '-';
                        @endphp

                        <tr
                            data-transfer-row
                            data-transfer-view="{{ $viewBucket }}"
                            data-transfer-status="{{ $transfer->status }}"
                        >
                            <td data-label="Customer">
                                <div class="tr-client">
                                    <span class="tr-client-avatar">
                                        {{
                                            strtoupper(
                                                substr(
                                                    trim($clientName) !== ''
                                                        ? trim($clientName)
                                                        : 'C',
                                                    0,
                                                    1
                                                )
                                            )
                                        }}
                                    </span>

                                    <div>
                                        @if($transfer->lead_id)
                                            <a
                                                href="{{ route('admin.leads.view', $transfer->lead_id) }}"
                                                class="tr-client-name"
                                                target="_blank"
                                            >
                                                {{ $clientName }}
                                            </a>
                                        @else
                                            <strong class="tr-client-name">
                                                {{ $clientName }}
                                            </strong>
                                        @endif

                                        <small>{{ $clientPhone }}</small>
                                    </div>
                                </div>
                            </td>

                            <td
                                data-label="Requested"
                                data-order="{{ optional($transfer->created_at)->timestamp ?? 0 }}"
                            >
                                <strong>{{ $requestDate }}</strong>
                                <small>By {{ $requestedByName }}</small>
                            </td>

                            <td data-label="Transfer">
                                <div class="tr-transfer-path">
                                    <strong>{{ $fromName }}</strong>
                                    <i class="ri-arrow-right-line" aria-hidden="true"></i>
                                    <strong>{{ $toName }}</strong>
                                </div>

                                @if(
                                    (string) $transfer->to_user_id
                                    ===
                                    (string) auth()->id()
                                )
                                    <span class="tr-badge tr-badge--info">
                                        You are recipient
                                    </span>
                                @endif
                            </td>

                            <td data-label="Reason">
                                <p class="tr-reason" title="{{ $reason }}">
                                    {{ $reason }}
                                </p>
                            </td>

                            <td data-label="Status" data-order="{{ $transfer->status }}">
                                @if($transfer->status === 'pending')
                                    <span class="tr-badge tr-badge--warning">
                                        Pending
                                    </span>
                                @elseif($transfer->status === 'accepted')
                                    <span class="tr-badge tr-badge--success">
                                        Accepted
                                    </span>
                                @elseif($transfer->status === 'rejected')
                                    <span class="tr-badge tr-badge--danger">
                                        Rejected
                                    </span>
                                @elseif($transfer->status === 'cancelled')
                                    <span class="tr-badge tr-badge--neutral">
                                        Cancelled
                                    </span>
                                @else
                                    <span class="tr-badge tr-badge--neutral">
                                        {{ ucfirst($transfer->status) }}
                                    </span>
                                @endif
                            </td>

                            <td data-label="Action" class="tr-action-cell">
                                <div class="tr-row-actions">
                                    <button
                                        type="button"
                                        class="tr-icon-btn transfer-detail-btn"
                                        title="View transfer details"
                                        aria-label="View transfer details"
                                        data-client="{{ $clientName }}"
                                        data-phone="{{ $clientPhone }}"
                                        data-from="{{ $fromName }}"
                                        data-to="{{ $toName }}"
                                        data-requested-by="{{ $requestedByName }}"
                                        data-request-date="{{ $requestDate }}"
                                        data-reason="{{ $reason }}"
                                        data-status="{{ ucfirst($transfer->status) }}"
                                        data-responded-by="{{ $respondedByName }}"
                                        data-response-date="{{ $responseDate }}"
                                        data-response-note="{{ $responseNote }}"
                                    >
                                        <i class="ri-eye-line" aria-hidden="true"></i>
                                    </button>

                                    @if($canApprove)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.leads.transfer.accept', $transfer->id) }}"
                                            class="tr-inline-form"
                                            data-transfer-accept-form
                                        >
                                            @csrf

                                            <button
                                                type="submit"
                                                class="tr-btn tr-btn--success"
                                            >
                                                <i class="ri-check-line" aria-hidden="true"></i>
                                                Accept
                                            </button>
                                        </form>

                                        <button
                                            type="button"
                                            class="tr-btn tr-btn--danger reject-transfer-btn"
                                            data-transfer-id="{{ $transfer->id }}"
                                            data-client-name="{{ $clientName }}"
                                        >
                                            <i class="ri-close-line" aria-hidden="true"></i>
                                            Reject
                                        </button>
                                    @elseif(
                                        $transfer->status === 'pending'
                                        &&
                                        $isRequester
                                    )
                                        <span class="tr-waiting">
                                            Waiting for approval
                                        </span>
                                    @elseif($transfer->status === 'pending')
                                        <span class="tr-waiting">
                                            Pending
                                        </span>
                                    @else
                                        <span class="tr-waiting">
                                            Processed
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="tr-empty-state" data-transfer-empty hidden>
                <i class="ri-inbox-line" aria-hidden="true"></i>
                <strong>No transfer requests found</strong>
                <span>Try another tab, search term or status filter.</span>
            </div>
        </div>
    </section>
</div>

{{-- ============================================================= --}}
{{-- Transfer Details Drawer --}}
{{-- ============================================================= --}}
<div
    class="tr-drawer-backdrop"
    data-transfer-drawer-backdrop
    hidden
></div>

<aside
    class="tr-drawer"
    data-transfer-drawer
    aria-hidden="true"
    aria-labelledby="transfer-drawer-title"
>
    <header class="tr-drawer-head">
        <div>
            <h2 id="transfer-drawer-title">Transfer Details</h2>
            <p>Lead transfer request information</p>
        </div>

        <button
            type="button"
            class="tr-icon-btn"
            data-close-transfer-drawer
            aria-label="Close transfer details"
        >
            <i class="ri-close-line" aria-hidden="true"></i>
        </button>
    </header>

    <section class="tr-drawer-client">
        <span class="tr-drawer-avatar" data-drawer-avatar>C</span>
        <div>
            <strong data-drawer-client>Client</strong>
            <small data-drawer-phone>-</small>
        </div>
    </section>

    <section class="tr-detail-section">
        <h3>Transfer</h3>

        <div class="tr-detail-grid">
            <div>
                <small>Current / Original Owner</small>
                <strong data-drawer-from>-</strong>
            </div>

            <div>
                <small>Requested For</small>
                <strong data-drawer-to>-</strong>
            </div>

            <div>
                <small>Requested By</small>
                <strong data-drawer-requested-by>-</strong>
            </div>

            <div>
                <small>Requested On</small>
                <strong data-drawer-request-date>-</strong>
            </div>
        </div>
    </section>

    <section class="tr-detail-section">
        <h3>Request Reason</h3>
        <p data-drawer-reason>-</p>
    </section>

    <section class="tr-detail-section">
        <h3>Response</h3>

        <div class="tr-detail-grid">
            <div>
                <small>Status</small>
                <strong data-drawer-status>-</strong>
            </div>

            <div>
                <small>Responded By</small>
                <strong data-drawer-responded-by>-</strong>
            </div>

            <div>
                <small>Responded On</small>
                <strong data-drawer-response-date>-</strong>
            </div>
        </div>

        <div class="tr-response-note">
            <small>Response Note</small>
            <p data-drawer-response-note>-</p>
        </div>
    </section>
</aside>

{{-- ============================================================= --}}
{{-- Existing Reject Transfer Modal --}}
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
                    <span class="sr-only">Close</span>
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

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var workspace = document.querySelector('[data-transfer-workspace]');

    if (!workspace) {
        return;
    }

    var activeView = 'action';
    var tableElement = document.getElementById('lead-transfer-table');
    var searchInput = document.getElementById('transfer-search');
    var statusFilter = document.getElementById('transfer-status-filter');
    var emptyState = document.querySelector('[data-transfer-empty]');
    var dataTable = null;

    function setActiveTab(view) {
        activeView = view;

        document
            .querySelectorAll('[data-transfer-tab]')
            .forEach(function (button) {
                button.classList.toggle(
                    'is-active',
                    button.dataset.transferTab === view
                );
            });

        document
            .querySelectorAll('[data-transfer-summary-tab]')
            .forEach(function (button) {
                button.classList.toggle(
                    'is-active',
                    button.dataset.transferSummaryTab === view
                );
            });

        if (dataTable) {
            dataTable.draw();
        } else {
            applyFallbackFilter();
        }
    }

    function rowMatchesView(row) {
        if (!row) {
            return false;
        }

        return row.dataset.transferView === activeView;
    }

    function applyFallbackFilter() {
        var term = (searchInput.value || '').trim().toLowerCase();
        var selectedStatus = statusFilter.value || '';
        var visible = 0;

        document
            .querySelectorAll('[data-transfer-row]')
            .forEach(function (row) {
                var matchesView = rowMatchesView(row);
                var matchesStatus =
                    !selectedStatus
                    || row.dataset.transferStatus === selectedStatus;
                var matchesSearch =
                    !term
                    || row.textContent.toLowerCase().includes(term);

                var show =
                    matchesView
                    && matchesStatus
                    && matchesSearch;

                row.hidden = !show;

                if (show) {
                    visible++;
                }
            });

        if (emptyState) {
            emptyState.hidden = visible !== 0;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DataTable
    |--------------------------------------------------------------------------
    | Keep existing CRM DataTables dependency and pagination/search behaviour,
    | but remove horizontal scrolling because the redesigned table has only
    | six compact columns.
    */
    if (
        window.jQuery
        && $.fn.DataTable
        && tableElement
    ) {
        $.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex) {
                if (settings.nTable !== tableElement) {
                    return true;
                }

                var row = settings.aoData[dataIndex]
                    ? settings.aoData[dataIndex].nTr
                    : null;

                return rowMatchesView(row);
            }
        );

        dataTable = $('#lead-transfer-table').DataTable({
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            searching: true,
            paging: true,
            info: true,
            responsive: false,
            scrollX: false,
            autoWidth: false,
            order: [
                [1, 'desc']
            ],
            columnDefs: [
                {
                    targets: 5,
                    orderable: false,
                    searchable: false
                }
            ],
            dom:
                "<'tr-dt-head'l>"
                + "t"
                + "<'tr-dt-footer'ip>",
            language: {
                lengthMenu: 'Show _MENU_ requests',
                info: 'Showing _START_ to _END_ of _TOTAL_ requests',
                infoEmpty: 'Showing 0 to 0 of 0 requests',
                zeroRecords: 'No matching transfer requests found',
                emptyTable: 'No transfer requests found'
            }
        });

        searchInput.addEventListener(
            'input',
            function () {
                dataTable
                    .search(this.value)
                    .draw();
            }
        );

        statusFilter.addEventListener(
            'change',
            function () {
                dataTable
                    .column(4)
                    .search(
                        this.value
                            ? '^' + this.value + '$'
                            : '',
                        true,
                        false
                    )
                    .draw();
            }
        );
    } else {
        searchInput.addEventListener(
            'input',
            applyFallbackFilter
        );

        statusFilter.addEventListener(
            'change',
            applyFallbackFilter
        );
    }

    document
        .querySelectorAll('[data-transfer-tab]')
        .forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    setActiveTab(
                        this.dataset.transferTab
                    );
                }
            );
        });

    document
        .querySelectorAll('[data-transfer-summary-tab]')
        .forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    setActiveTab(
                        this.dataset.transferSummaryTab
                    );
                }
            );
        });

    var resetButton =
        document.querySelector(
            '[data-reset-transfer-filter]'
        );

    if (resetButton) {
        resetButton.addEventListener(
            'click',
            function () {
                searchInput.value = '';
                statusFilter.value = '';

                if (dataTable) {
                    dataTable
                        .search('')
                        .column(4)
                        .search('')
                        .draw();
                } else {
                    applyFallbackFilter();
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accept confirmation
    |--------------------------------------------------------------------------
    | Same route and POST action as before.
    */
    document
        .querySelectorAll('[data-transfer-accept-form]')
        .forEach(function (form) {
            form.addEventListener(
                'submit',
                function (event) {
                    if (
                        !window.confirm(
                            'Are you sure you want to approve this lead transfer request?'
                        )
                    ) {
                        event.preventDefault();
                    }
                }
            );
        });

    /*
    |--------------------------------------------------------------------------
    | Reject modal
    |--------------------------------------------------------------------------
    | Preserve the existing rejection endpoint and optional response note.
    */
    $(document).on(
        'click',
        '.reject-transfer-btn',
        function () {
            var transferId =
                $(this).data('transfer-id');

            var clientName =
                $(this).data('client-name');

            var rejectUrl =
                "{{ route('admin.leads.transfer.reject', ':transfer') }}";

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
                    + clientName
                    + '?'
                );

            if (
                window.HSOverlay
                && typeof HSOverlay.open === 'function'
            ) {
                HSOverlay.open(
                    document.querySelector(
                        '#lead-transfer-reject-modal'
                    )
                );
            } else {
                $('#lead-transfer-reject-modal')
                    .removeClass('hidden');
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Details drawer
    |--------------------------------------------------------------------------
    | Read-only. No business logic or database action.
    */
    var drawer =
        document.querySelector(
            '[data-transfer-drawer]'
        );

    var drawerBackdrop =
        document.querySelector(
            '[data-transfer-drawer-backdrop]'
        );

    function setDrawerText(selector, value) {
        var element =
            document.querySelector(selector);

        if (element) {
            element.textContent =
                value || '-';
        }
    }

    function closeDrawer() {
        if (!drawer) {
            return;
        }

        drawer.classList.remove('is-open');
        drawer.setAttribute(
            'aria-hidden',
            'true'
        );

        if (drawerBackdrop) {
            drawerBackdrop.hidden = true;
        }

        document.body.classList.remove(
            'tr-drawer-open'
        );
    }

    document
        .querySelectorAll('.transfer-detail-btn')
        .forEach(function (button) {
            button.addEventListener(
                'click',
                function () {
                    var data = this.dataset;

                    setDrawerText(
                        '[data-drawer-client]',
                        data.client
                    );

                    setDrawerText(
                        '[data-drawer-phone]',
                        data.phone
                    );

                    setDrawerText(
                        '[data-drawer-from]',
                        data.from
                    );

                    setDrawerText(
                        '[data-drawer-to]',
                        data.to
                    );

                    setDrawerText(
                        '[data-drawer-requested-by]',
                        data.requestedBy
                    );

                    setDrawerText(
                        '[data-drawer-request-date]',
                        data.requestDate
                    );

                    setDrawerText(
                        '[data-drawer-reason]',
                        data.reason
                    );

                    setDrawerText(
                        '[data-drawer-status]',
                        data.status
                    );

                    setDrawerText(
                        '[data-drawer-responded-by]',
                        data.respondedBy
                    );

                    setDrawerText(
                        '[data-drawer-response-date]',
                        data.responseDate
                    );

                    setDrawerText(
                        '[data-drawer-response-note]',
                        data.responseNote
                    );

                    setDrawerText(
                        '[data-drawer-avatar]',
                        (data.client || 'C')
                            .trim()
                            .charAt(0)
                            .toUpperCase()
                    );

                    drawer.classList.add(
                        'is-open'
                    );

                    drawer.setAttribute(
                        'aria-hidden',
                        'false'
                    );

                    if (drawerBackdrop) {
                        drawerBackdrop.hidden = false;
                    }

                    document.body.classList.add(
                        'tr-drawer-open'
                    );
                }
            );
        });

    document
        .querySelectorAll(
            '[data-close-transfer-drawer]'
        )
        .forEach(function (button) {
            button.addEventListener(
                'click',
                closeDrawer
            );
        });

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener(
            'click',
            closeDrawer
        );
    }

    document.addEventListener(
        'keydown',
        function (event) {
            if (event.key === 'Escape') {
                closeDrawer();
            }
        }
    );

    setActiveTab('action');
});
</script>
@endpush
