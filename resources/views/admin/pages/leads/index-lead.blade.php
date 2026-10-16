@extends('admin.layouts.header')
@section('content')
<style>
    /*
     * Single-line preview visible inside the Leads table.
     */
    .followup-history-preview {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 280px;
        max-width: 280px;
        cursor: pointer;
        outline: none;
    }

    .followup-history-preview-text {
        display: block;
        flex: 1;
        min-width: 0;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    .followup-history-preview-count {
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 23px;
        height: 23px;
        padding: 0 6px;
        border-radius: 50px;
        background: rgba(var(--primary), 0.1);
        color: rgb(var(--primary));
        font-size: 11px;
        font-weight: 600;
    }

    .lead-service-column {
        max-width: 320px;
    }

    .lead-service-preview {
        display: block;
        width: 320px;
        max-width: 100%;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }

    /*
     * Highlight only when Follow-up History column is hovered.
     */
    .followup-history-preview:hover
    .followup-history-preview-text,
    .followup-history-preview:focus
    .followup-history-preview-text {
        color: rgb(var(--primary));
    }

    /*
     * Existing follow-up table is reused as the popup.
     * No new modal or duplicate table is created.
     */
    .followup-history-popup {
        display: none !important;
        position: fixed !important;

        /*
         * Always show in the center of the browser window.
         */
        top: 50% !important;
        left: 50% !important;
        transform: translate(-50%, -50%) !important;

        z-index: 99999;
        box-sizing: border-box;

        width: min(1000px, calc(100vw - 40px)) !important;
        max-width: calc(100vw - 40px) !important;

        /*
         * Small history: no scrollbar.
         * Large history: scrollbar appears automatically.
         */
        height: auto !important;
        max-height: calc(100vh - 80px) !important;

        overflow-x: auto !important;
        overflow-y: auto !important;

        padding: 16px;
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 12px;
        background: #ffffff;
        box-shadow:
            0 25px 65px rgba(15, 23, 42, 0.3),
            0 0 0 100vmax rgba(15, 23, 42, 0.25);

        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }

    .followup-history-popup.followup-popup-visible {
        display: block !important;
    }

    /*
     * Full table width.
     * On desktop, horizontal scrolling normally will not appear.
     */
    .followup-history-popup
    .followup-history-inner-table {
        width: 100% !important;
        min-width: 100% !important;
        margin-bottom: 0;
        table-layout: auto !important;
    }

    /*
     * Keep header visible while scrolling a large history.
     */
    .followup-history-popup thead th {
        position: sticky;
        top: -16px;
        z-index: 2;
        background: rgb(var(--primary));
        color: #ffffff;
    }

    .followup-history-popup th,
    .followup-history-popup td {
        vertical-align: top;
        white-space: nowrap;
    }

    /*
     * Follow-up note may wrap so complete note remains readable.
     */
    .followup-history-popup td:first-child {
        width: 45%;
        min-width: 300px;
        max-width: 480px;
        white-space: normal !important;
        word-break: break-word;
        overflow-wrap: anywhere;
    }

    /*
     * Next-follow-up date may wrap when necessary.
     */
    .followup-history-popup td:nth-child(3) {
        min-width: 145px;
        white-space: normal !important;
    }

    .dark .followup-history-popup {
        border-color: rgba(255, 255, 255, 0.12);
        background: rgb(var(--body-bg));
        color: #ffffff;
        box-shadow:
            0 25px 65px rgba(0, 0, 0, 0.55),
            0 0 0 100vmax rgba(0, 0, 0, 0.45);
    }

    @media (max-width: 767.98px) {
        .followup-history-preview {
            width: 230px;
            max-width: 230px;
        }

        .lead-service-column {
            max-width: 220px;
        }

        .lead-service-preview {
            width: 220px;
        }

        .followup-history-popup {
            width: calc(100vw - 24px) !important;
            max-width: calc(100vw - 24px) !important;
            max-height: calc(100vh - 40px) !important;
            padding: 12px;
        }

        .followup-history-popup thead th {
            top: -12px;
        }
    }
</style>
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/leads/leads-list.css') }}?v=4">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/responsive/leads-list-mobile.css') }}?v=1">
@php
    $listRole = optional(auth()->user()->userType)->user_type;
    $canWriteLeads = in_array($listRole, array_merge(\App\Models\UserType::ADMIN_ROLES, \App\Models\UserType::SALES_ROLES, \App\Models\UserType::OPERATIONS_ROLES), true);
    $canDownloadLeads = $canWriteLeads || $listRole === \App\Models\UserType::HR;
    $canRequestLeads = $listRole === \App\Models\UserType::SUPER_ADMIN || in_array($listRole, \App\Models\UserType::SALES_ROLES, true);
    $dashboardRoute = $listRole === \App\Models\UserType::HR ? 'admin.hr.dashboard' : (in_array($listRole, \App\Models\UserType::OPERATIONS_ROLES, true) ? 'admin.operations.index' : 'admin.sales-dashboard');
@endphp
<div class="crm-leads-list">
    <header class="leads-heading"><div><h1>Leads</h1></div><div class="leads-heading-actions">
        @if($canDownloadLeads)<button type="button" class="leads-button" data-lead-dialog-open="leads-download"><i class="ri-download-2-line" aria-hidden="true"></i>Download</button>@endif
        @if($canRequestLeads)<button type="button" class="leads-button" data-lead-dialog-open="leads-request"><i class="ri-user-shared-line" aria-hidden="true"></i>Request lead</button>@endif
        @if($canWriteLeads)
            <a class="leads-button leads-primary" href="{{ route('admin.clients.create') }}"><i class="ri-add-line" aria-hidden="true"></i>Add lead</a>
        @endif
    </div></header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @include('admin.pages.leads.partials.list-filters')
    <nav class="leads-queues" aria-label="Lead queues">
        @foreach(['all' => 'All leads', 'hot' => 'Hot leads', 'queued' => 'In queue', 'unassigned' => 'Unassigned', 'no_followup' => 'No follow-up date'] as $value => $label)
            @if(($value !== 'all' && $value !== 'queued') || $isSuperAdmin)
                <a href="{{ route('admin.clients.index', array_merge(request()->except(['page', 'lead_queue', 'allocation_status']), ['lead_queue' => $value])) }}" class="leads-queue {{ request('lead_queue', 'all') === $value ? 'is-active' : '' }}" @if(request('lead_queue', 'all') === $value) aria-current="page" @endif>@if($value === 'hot')<i class="ri-fire-line" aria-hidden="true"></i>@endif{{ $label }}<span>{{ $leadQueueCounts[$value] ?? 0 }}</span></a>
            @endif
        @endforeach
    </nav>
    @if(!$isSuperAdmin && request('lead_queue', 'all') !== 'all')<a class="leads-reset" href="{{ route('admin.clients.index', request()->except(['page', 'lead_queue'])) }}">Reset queue filter</a>@endif
    <section class="leads-results" aria-label="Lead results">
        <div class="leads-results-heading"><h2>Leads</h2>
            @if($isSuperAdmin)
<div
    id="bulk-lead-transfer-controls"
    class="hidden flex items-center gap-2"
>
    <span
        id="selected-transfer-count"
        class="text-sm font-semibold whitespace-nowrap"
    >
        0 selected
    </span>

    <button
        type="button"
        id="transfer-selected-leads"
        class="ti-btn ti-btn-primary ti-btn-sm whitespace-nowrap"
        style="width: fit-content;"
    >
        <i class="ri-arrow-left-right-line"></i>

        Transfer Selected
    </button>

    <button
        type="button"
        id="bulk-cancel-selected-leads"
        class="ti-btn ti-btn-danger ti-btn-sm whitespace-nowrap"
        style="width: fit-content;"
    >
        <i class="ri-close-circle-line"></i>

        Bulk Cancel
    </button>
</div>

            @endif
            <label class="leads-per-page">Show <select id="per-page-select">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" @if((int) request('per_page', 10) === $size) selected @endif>{{ $size }}</option>@endforeach</select></label>
        </div>
        @include('admin.pages.leads.partials.list-table')
        <footer class="leads-pagination"><span>{{ $leads->firstItem() ?? 0 }}-{{ $leads->lastItem() ?? 0 }} of {{ $leads->total() }} leads</span>{{ $leads->appends(request()->except('page'))->links() }}</footer>
    </section>
    <nav id="leads-mobile-more" class="leads-mobile-more" aria-label="More navigation" hidden></nav>
    <nav class="leads-mobile-nav" aria-label="Mobile navigation">
        <a href="{{ route($dashboardRoute) }}"><i class="ri-home-4-line" aria-hidden="true"></i><span>Dashboard</span></a>
        <a href="{{ route('admin.clients.index') }}" aria-current="page"><i class="ri-user-3-line" aria-hidden="true"></i><span>Leads</span></a>
        @if(in_array($listRole, array_merge(\App\Models\UserType::ADMIN_ROLES, \App\Models\UserType::SALES_ROLES, [\App\Models\UserType::HR]), true))
            <a href="{{ route('admin.upcoming-follow-up.index') }}"><i class="ri-time-line" aria-hidden="true"></i><span>Follow-up</span></a>
        @else
            <a href="{{ route('admin.operations.vendor-followups.index') }}"><i class="ri-time-line" aria-hidden="true"></i><span>Follow-up</span></a>
        @endif
        <a href="{{ route('admin.kpi.index') }}"><i class="ri-bar-chart-line" aria-hidden="true"></i><span>KPI</span></a>
        <button type="button" id="leads-more-toggle" aria-expanded="false" aria-controls="leads-mobile-more"><i class="ri-apps-line" aria-hidden="true"></i><span>More</span></button>
    </nav>
</div>
@include('admin.pages.leads.partials.download-request-dialogs')
<script src="{{ asset('assets/admin/js/crm/leads-list.js') }}?v=2" defer></script>
<script src="{{ asset('assets/admin/js/crm/leads-dialogs.js') }}?v=2" defer></script>

<!-- Confirm Followup Modal -->
<div id="confirm-followup-modal"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
        <button type="button" class="btn-close ms-auto" id="close-confirm-modal">
            <i class="bi bi-x"></i>
        </button>
        <div class="text-center px-[3rem] pb-0">
            <h5 class="text-xl font-semibold mb-4 text-gray-800">Confirm Lead</h5>
            <div class="grid grid-cols-12 gap-6">
                <div class="xl:col-span-12 col-span-12">
                    <form id="confirm-followup-form">
                        <input type="hidden" name="lead_id" id="confirm-lead-id">
                        <input type="hidden" name="status" value="5">
                        <!-- Status 5 for confirmed -->
                        <div class="mb-4">
                            <label for="confirm-notes"
                                class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="notes" id="confirm-notes" rows="3"
                                class="ti-form-input form-control form-control-sm" required maxlength="1000"
                                pattern="^(?=.*[A-Za-z])[A-Za-z0-9\s]+$"
                                title="Please enter letters and numbers only; at least one letter is required"></textarea>
                        </div>
                        <div>
                            <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                                id="cancel-confirm">Cancel</button>
                            <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Submit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Cancel Followup Modal -->
<div id="cancel-followup-modal"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
        <button type="button" class="btn-close ms-auto" id="close-cancel-modal">
            <i class="bi bi-x"></i>
        </button>
        <div class="text-center px-[3rem] pb-0">
            <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
            <p class="mb-4 text-gray-600">You want to cancel this lead?</p>
            <div class="">
                <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1" id="decline-cancel">No</button>
                <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="proceed-to-cancel">Yes</button>
            </div>
        </div>
    </div>
</div>

<!-- Cancel Notes Modal -->
<div id="cancel-notes-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="alert custom-alert1 alert-primary !bg-white dark:!bg-bodybg w-[90%] max-w-md">
        <button type="button" class="btn-close ms-auto" id="close-cancel-notes-modal">
            <i class="bi bi-x"></i>
        </button>
        <div class="text-center px-[3rem] pb-0">
            <h5 class="text-xl font-semibold mb-4 text-gray-800">Cancel Lead</h5>
            <form id="cancel-followup-form">
                <input type="hidden" name="lead_id" id="cancel-lead-id">
                <input type="hidden" name="status" value="2">
                <!-- Status 2 for cancelled -->
                <div class="mb-4">
                    <label for="cancel-notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="cancel-notes" rows="3" class="ti-form-textarea" required maxlength="1000"
                        pattern="^(?=.*[A-Za-z])[A-Za-z0-9\s]+$"
                        title="Please enter letters and numbers only; at least one letter is required"></textarea>
                </div>
                <div>
                    <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1"
                        id="cancel-cancel-notes">Cancel</button>
                    <button type="submit" class="ti-btn bg-primary text-white px-4 py-1">Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Generate Voucher Modal Removed - Now redirects to separate page -->

<!-- Delete Confirmation Alert Modal -->
<div id="custom-delete-alert" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
        <button type="button" class="float-right text-gray-500 hover:text-black" id="close-alert">
            <i class="bi bi-x"></i>
        </button>
        <h5 class="text-xl font-semibold mb-2 text-gray-800">Are you sure?</h5>
        <p class="mb-4 text-gray-600">You want to deactivate this client?</p>
        <div class="flex justify-center gap-4">
            <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1" id="decline-delete">Decline</button>
            <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-delete">Yes,
                Deactivate</button>
        </div>
    </div>
</div>
<!-- Lead Delete Confirmation Modal -->
<div id="custom-delete-lead-alert"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
        <button type="button" class="float-right text-gray-500 hover:text-black" id="close-lead-delete-alert">
            <i class="bi bi-x"></i>
        </button>
        <h5 class="text-xl font-semibold mb-2 text-gray-800">Delete Lead</h5>
        <p class="mb-4 text-gray-600">This will permanently delete the lead and all related data (followups, payments,
            vouchers, passengers, rides, vendor payments, refunds etc.). Are you sure?</p>
        <div class="flex justify-center gap-4">
            <button type="button" class="ti-btn ti-btn-outline-danger px-4 py-1"
                id="decline-lead-delete">Decline</button>
            <button type="button" class="ti-btn bg-primary text-white px-4 py-1" id="confirm-lead-delete">Yes,
                Delete</button>
        </div>
    </div>
</div>
<div id="toggle-status-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white p-6 rounded-lg shadow-lg w-[90%] max-w-md text-center">
        <button type="button" class="float-right text-gray-500 hover:text-black" id="close-toggle-modal">
            <i class="bi bi-x"></i>
        </button>
        <h5 class="text-xl font-semibold mb-2 text-gray-800">Confirm Status Change</h5>
        <p class="mb-4 text-gray-600" id="status-modal-message"></p>
        <div class="flex justify-center gap-4">
            <button type="button" class="ti-btn ti-btn-outline-secondary px-4 py-1" id="cancel-toggle">Cancel</button>
            <button type="button" class="ti-btn bg-primary text-white px-4 py-1"
                id="confirm-status-toggle">Confirm</button>
        </div>
    </div>
</div>
@if ($isSuperAdmin ?? false)

<div
    id="lead-direct-transfer-modal"
    class="
        hidden
        fixed
        inset-0
        z-50
        flex
        items-center
        justify-center
        bg-black
        bg-opacity-50
    "
>
    <div
        class="
            bg-white
            dark:!bg-bodybg
            p-6
            rounded-lg
            shadow-lg
            w-[90%]
            max-w-lg
        "
    >

        <div
            class="flex justify-between items-center mb-4"
        >
            <h5
                class="text-xl font-semibold"
            >
                Transfer Lead
            </h5>

            <button
                type="button"
                id="close-lead-direct-transfer-modal"
                class="text-gray-500 hover:text-black"
            >
                <i class="bi bi-x"></i>
            </button>
        </div>

        <form
            method="POST"
            action="{{
                route(
                    'admin.leads.transfer.direct-assign'
                )
            }}"
            id="lead-direct-transfer-form"
        >

            @csrf

            <div
                id="direct-transfer-lead-inputs"
            ></div>

            <div
                class="
                    mb-4
                    p-3
                    bg-gray-50
                    dark:bg-black/20
                    rounded
                "
            >
                <div
                    id="direct-transfer-summary"
                    class="text-sm"
                ></div>
            </div>

            <div class="mb-4">

                <label
                    for="direct-transfer-representative"
                    class="ti-form-label"
                >
                    Assign To Representative
                </label>

                <select
                    name="representative_user_id"
                    id="direct-transfer-representative"
                    class="ti-form-select w-full"
                    required
                >

                    <option value="">
                        Select Representative
                    </option>

                    @foreach (
                        $transferRepresentatives
                        ?? collect()
                        as $representative
                    )

                        <option
                            value="{{
                                $representative->id
                            }}"
                        >
                            {{
                                $representative->name
                            }}
                        </option>

                    @endforeach

                </select>

            </div>

            <div
                class="
                    flex
                    justify-end
                    gap-3
                "
            >

                <button
                    type="button"
                    id="cancel-lead-direct-transfer"
                    class="
                        ti-btn
                        ti-btn-outline-secondary
                    "
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    id="confirm-lead-direct-transfer"
                    class="
                        ti-btn
                        bg-primary
                        text-white
                    "
                >
                    Transfer Lead
                </button>

            </div>

        </form>

    </div>
</div>

@endif
@if ($isSuperAdmin ?? false)

<div
    id="bulk-lead-cancel-modal"
    class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
>
    <div class="bg-white dark:!bg-bodybg p-6 rounded-lg shadow-lg w-[90%] max-w-lg">
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-xl font-semibold">Bulk Cancel Leads</h5>

            <button
                type="button"
                id="close-bulk-lead-cancel-modal"
                class="text-gray-500 hover:text-black"
            >
                <i class="bi bi-x"></i>
            </button>
        </div>

        <form id="bulk-lead-cancel-form">
            <div id="bulk-cancel-lead-inputs"></div>

            <div class="mb-4 p-3 bg-gray-50 dark:bg-black/20 rounded">
                <div id="bulk-cancel-summary" class="text-sm"></div>
            </div>

            <div class="mb-4">
                <label for="bulk-cancel-reason" class="ti-form-label">
                    Cancellation Reason
                </label>
                <textarea
                    id="bulk-cancel-reason"
                    name="reason"
                    rows="4"
                    class="ti-form-textarea"
                    required
                    minlength="3"
                    maxlength="1000"
                ></textarea>
            </div>

            <div id="bulk-cancel-result" class="hidden mb-4 text-sm"></div>

            <div class="flex justify-end gap-3">
                <button
                    type="button"
                    id="cancel-bulk-lead-cancel"
                    class="ti-btn ti-btn-outline-secondary"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    id="confirm-bulk-lead-cancel"
                    class="ti-btn ti-btn-danger"
                >
                    Cancel Selected Leads
                </button>
            </div>
        </form>
    </div>
</div>

@endif
@endsection
@push('scripts')
@if ($isSuperAdmin ?? false)

<script>
$(document).ready(function () {

    const modal =
        $('#lead-direct-transfer-modal');

    const inputsContainer =
        $('#direct-transfer-lead-inputs');

    const summary =
        $('#direct-transfer-summary');

    const transferButton =
        $('#confirm-lead-direct-transfer');

    const cancelModal =
        $('#bulk-lead-cancel-modal');

    const cancelInputsContainer =
        $('#bulk-cancel-lead-inputs');

    const cancelSummary =
        $('#bulk-cancel-summary');

    const cancelResult =
        $('#bulk-cancel-result');

    const cancelButton =
        $('#confirm-bulk-lead-cancel');

    let bulkCancelOperationId = null;

    function selectedCheckboxes() {

        return $(
            '.lead-transfer-checkbox:checked'
        );
    }

    function updateBulkTransferControls() {

        const count =
            selectedCheckboxes().length;

        $('#selected-transfer-count')
            .text(
                count +
                ' selected'
            );

        if (count > 0) {

            $('#bulk-lead-transfer-controls')
                .removeClass('hidden');

        } else {

            $('#bulk-lead-transfer-controls')
                .addClass('hidden');
        }

        const total =
            $('.lead-transfer-checkbox')
                .length;

        $('#select-all-transfer-leads')
            .prop(
                'checked',
                total > 0 &&
                count === total
            );
    }

    function openTransferModal(
        leads
    ) {

        if (!leads.length) {
            return;
        }

        inputsContainer.empty();

        const ownerCounts = {};

        leads.forEach(
            function (lead) {

                $('<input>', {
                    type: 'hidden',
                    name: 'lead_ids[]',
                    value: lead.id
                }).appendTo(
                    inputsContainer
                );

                const owner =
                    lead.currentRepresentative
                    || 'N/A';

                ownerCounts[owner] =
                    (ownerCounts[owner] || 0)
                    + 1;
            }
        );

        const owners =
            Object.entries(
                ownerCounts
            )
            .map(
                function (entry) {

                    return (
                        entry[0] +
                        ' (' +
                        entry[1] +
                        ')'
                    );
                }
            )
            .join(', ');

        if (leads.length === 1) {

            summary.text(
                'Client: ' +
                leads[0].clientName +
                ' | Current Representative: ' +
                leads[0].currentRepresentative
            );

            transferButton.text(
                'Transfer Lead'
            );

        } else {

            summary.text(
                leads.length +
                ' leads selected. Current Representatives: ' +
                owners
            );

            transferButton.text(
                'Transfer ' +
                leads.length +
                ' Leads'
            );
        }

        $('#direct-transfer-representative')
            .val('');

        modal.removeClass(
            'hidden'
        );
    }

    function selectedLeads() {

        const leads = [];

        selectedCheckboxes()
            .each(
                function () {

                    leads.push({
                        id:
                            $(this)
                                .val(),

                        clientName:
                            $(this)
                                .data(
                                    'client-name'
                                ),

                        currentRepresentative:
                            $(this)
                                .data(
                                    'current-representative'
                                )
                    });
                }
            );

        return leads;
    }

    function randomUuid() {

        if (
            window.crypto
            && typeof window.crypto.randomUUID === 'function'
        ) {
            return window.crypto.randomUUID();
        }

        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'
            .replace(
                /[xy]/g,
                function (char) {
                    const value =
                        Math.random() * 16 | 0;

                    const uuidValue =
                        char === 'x'
                            ? value
                            : (value & 0x3 | 0x8);

                    return uuidValue.toString(16);
                }
            );
    }

    function openBulkCancelModal(leads) {

        if (!leads.length) {
            showToast(
                'error',
                'Please select at least one lead.'
            );
            return;
        }

        if (leads.length > 100) {
            showToast(
                'error',
                'Please select at most 100 leads at a time.'
            );
            return;
        }

        bulkCancelOperationId =
            randomUuid();

        cancelInputsContainer.empty();
        cancelResult
            .addClass('hidden')
            .empty();

        leads.forEach(
            function (lead) {
                $('<input>', {
                    type: 'hidden',
                    name: 'lead_ids[]',
                    value: lead.id
                }).appendTo(
                    cancelInputsContainer
                );
            }
        );

        cancelSummary.text(
            leads.length +
            ' lead(s) selected for cancellation.'
        );

        $('#bulk-cancel-reason')
            .val('');

        cancelButton
            .prop('disabled', false)
            .text('Cancel ' + leads.length + ' Lead(s)');

        cancelModal.removeClass(
            'hidden'
        );
    }

    /*
     * Select all leads displayed on THIS PAGE only.
     */
    $('#select-all-transfer-leads')
        .on(
            'change',
            function () {

                $('.lead-transfer-checkbox')
                    .prop(
                        'checked',
                        this.checked
                    );

                updateBulkTransferControls();
            }
        );

    $(document)
        .on(
            'change',
            '.lead-transfer-checkbox',
            function () {

                updateBulkTransferControls();
            }
        );

    /*
     * Single lead action button.
     */
    $(document)
        .on(
            'click',
            '.lead-direct-transfer-btn',
            function () {

                openTransferModal([
                    {
                        id:
                            $(this)
                                .data(
                                    'lead-id'
                                ),

                        clientName:
                            $(this)
                                .data(
                                    'client-name'
                                ),

                        currentRepresentative:
                            $(this)
                                .data(
                                    'current-representative'
                                )
                    }
                ]);
            }
        );

    /*
     * Bulk transfer button.
     */
    $('#transfer-selected-leads')
        .on(
            'click',
            function () {

                openTransferModal(
                    selectedLeads()
                );
            }
        );

    $('#bulk-cancel-selected-leads')
        .on(
            'click',
            function () {
                openBulkCancelModal(
                    selectedLeads()
                );
            }
        );

    function closeTransferModal() {

        modal.addClass(
            'hidden'
        );

        inputsContainer.empty();
    }

    $('#close-lead-direct-transfer-modal')
        .on(
            'click',
            closeTransferModal
        );

    $('#cancel-lead-direct-transfer')
        .on(
            'click',
            closeTransferModal
        );

    function closeBulkCancelModal() {

        cancelModal.addClass(
            'hidden'
        );

        cancelInputsContainer.empty();
        cancelResult
            .addClass('hidden')
            .empty();
        bulkCancelOperationId = null;
    }

    $('#close-bulk-lead-cancel-modal, #cancel-bulk-lead-cancel')
        .on(
            'click',
            closeBulkCancelModal
        );

    /*
     * Protect against double submit.
     */
    $('#lead-direct-transfer-form')
        .on(
            'submit',
            function () {

                transferButton
                    .prop(
                        'disabled',
                        true
                    )
                    .text(
                        'Transferring...'
                    );
            }
        );

    $('#bulk-lead-cancel-form')
        .on(
            'submit',
            function (event) {
                event.preventDefault();

                const leadIds =
                    selectedLeads()
                        .map(function (lead) {
                            return lead.id;
                        });

                const reason =
                    $('#bulk-cancel-reason')
                        .val()
                        .trim();

                if (!reason || reason.length < 3) {
                    showToast(
                        'error',
                        'Cancellation reason is required.'
                    );
                    return;
                }

                cancelButton
                    .prop('disabled', true)
                    .text('Cancelling...');

                $.ajax({
                    url: "{{ route('admin.leads.bulk-cancel') }}",
                    type: 'POST',
                    data: JSON.stringify({
                        lead_ids: leadIds,
                        reason: reason,
                        operation_id: bulkCancelOperationId
                    }),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json'
                    },
                    success: function (response) {
                        const counts =
                            response.counts || {};

                        const skipped =
                            response.details
                            && response.details.skipped
                                ? response.details.skipped
                                : [];

                        const failed =
                            response.details
                            && response.details.failed
                                ? response.details.failed
                                : [];

                        const messages = [
                            'Cancelled: ' + (counts.cancelled || 0),
                            'Skipped: ' + (counts.skipped || 0),
                            'Failed: ' + (counts.failed || 0)
                        ];

                        const details =
                            skipped
                                .concat(failed)
                                .slice(0, 5)
                                .map(function (item) {
                                    return item.reason;
                                })
                                .join(' | ');

                        cancelResult
                            .removeClass('hidden')
                            .text(
                                messages.join(', ')
                                + (details ? '. ' + details : '')
                            );

                        showToast(
                            (counts.cancelled || 0) > 0
                                ? 'success'
                                : 'error',
                            messages.join(', ')
                        );

                        setTimeout(function () {
                            location.reload();
                        }, 1800);
                    },
                    error: function (xhr) {
                        let errorMessage =
                            'Bulk cancellation failed.';

                        if (
                            xhr.responseJSON
                            && xhr.responseJSON.message
                        ) {
                            errorMessage =
                                xhr.responseJSON.message;
                        }

                        showToast(
                            'error',
                            errorMessage
                        );

                        cancelButton
                            .prop('disabled', false)
                            .text('Cancel Selected Leads');
                    }
                });
            }
        );

    updateBulkTransferControls();
});
</script>

@endif
<script>
    $(document).ready(function() {
            let currentLeadId = null;
            // Confirm button click
            $(document).on('click', '.confirm-lead', function() {
                currentLeadId = $(this).data('lead-id');
                $('#confirm-lead-id').val(currentLeadId);
                $('#confirm-followup-modal').removeClass('hidden');
            });

            // Cancel button click - first confirmation
            $(document).on('click', '.cancel-lead', function() {
                currentLeadId = $(this).data('lead-id');
                $('#cancel-followup-modal').removeClass('hidden');
            });

            // Proceed to cancel notes after confirming cancellation
            $('#proceed-to-cancel').click(function() {
                $('#cancel-followup-modal').addClass('hidden');
                $('#cancel-lead-id').val(currentLeadId);
                $('#cancel-notes-modal').removeClass('hidden');
            });

            // Close modals
            $('#close-confirm-modal, #cancel-confirm').click(function() {
                $('#confirm-followup-modal').addClass('hidden');
            });

            $('#close-cancel-modal, #decline-cancel').click(function() {
                $('#cancel-followup-modal').addClass('hidden');
            });

            $('#close-cancel-notes-modal, #cancel-cancel-notes').click(function() {
                $('#cancel-notes-modal').addClass('hidden');
            });

            // Form submissions
            $('#confirm-followup-form').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serializeArray();
                // Remove any existing status if present
                formData = formData.filter(item => item.name !== 'status');
                // Add the correct status
                formData.push({
                    name: 'status',
                    value: '5'
                });
                submitFollowup(formData, 'confirmed');
            });

            $('#cancel-followup-form').submit(function(e) {
                e.preventDefault();
                let formData = $(this).serializeArray();
                // Remove any existing status if present
                formData = formData.filter(item => item.name !== 'status');
                // Add the correct status
                formData.push({
                    name: 'status',
                    value: '2'
                });
                submitFollowup(formData, 'cancelled');
            });

            function submitFollowup(formData, action) {
                // Convert array to object
                const data = {};
                formData.forEach(item => {
                    data[item.name] = item.value;
                });

                // Get the lead ID from the row
                const leadId = currentLeadId;
                $.ajax({
                    url: `/admin/leads/${leadId}/follow-up`,
                    type: 'POST',
                    data: data,
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        console.log('Response:', response); // Debug log
                        if (response.success) {
                            // Close the appropriate modal
                            if (action === 'confirmed') {
                                $('#confirm-followup-modal').addClass('hidden');
                            } else {
                                $('#cancel-notes-modal').addClass('hidden');
                            }

                            showToast('success', response.message || 'Lead ' + action +
                                ' successfully!');

                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('error', response.message || 'Error processing request');
                        }
                    },
                    error: function(xhr) {
                        console.error('Error:', xhr.responseText); // Debug log
                        let errorMessage = 'Something went wrong';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.statusText) {
                            errorMessage = xhr.statusText;
                        }
                        showToast('error', errorMessage);
                    }
                });
            }


            window.showToast = function(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }

            // Export functionality - attach to header buttons and include all filters
            function buildExportUrl(isCsv = false) {
                const filters = {
                search: $('#global-search').val(),
                name: $('input[name="name"]').val(),
                email: $('input[name="email"]').val(),
                phone: $('input[name="phone"]').val(),
                representative_user_id: $('select[name="representative_user_id"]').val(),
                from_date: $('input[name="from_date"]').val(),
                to_date: $('input[name="to_date"]').val(),
                from_created_date: $('input[name="from_created_date"]').val(),
                to_created_date: $('input[name="to_created_date"]').val(),
                from_followup_date: $('input[name="from_followup_date"]').val(),
                to_followup_date: $('input[name="to_followup_date"]').val(),
                followup_period: $('select[name="followup_period"]').val(),
                lead_queue: $('input[name="lead_queue"]').val(),
                status: $('select[name="status"]').val(),
                allocation_status: $('select[name="allocation_status"]').val(),
                service_ids: $('select[name="service_ids"]').val(),
                product_ids: $('select[name="product_ids"]').val()
            };

                const exportPath = '{{ route('admin.leads.export') }}';
                const params = new URLSearchParams();

                Object.keys(filters).forEach(key => {
                    const val = filters[key];
                    if (val === undefined || val === null || val === '') return;
                    // If it's an array (multi-select), append each value
                    if (Array.isArray(val)) {
                        val.forEach(v => params.append(key + '[]', v));
                    } else {
                        params.append(key, val);
                    }
                });

                if (isCsv) params.append('format', 'csv');

                return params.toString() ? exportPath + '?' + params.toString() : exportPath;
            }

            window.buildLeadsExportUrl = buildExportUrl;
            $(document).on('click', '.export-excel-btn', function(e) {
                e.preventDefault();
                const url = buildExportUrl(false);
                showToast('success', 'Export started! Download will begin shortly...');
                window.location.href = url;
            });

            $(document).on('click', '.export-csv-btn', function(e) {
                e.preventDefault();
                const url = buildExportUrl(true);
                showToast('success', 'Export started! Download will begin shortly...');
                window.location.href = url;
            });

            // Global Search Functionality with Debounce
            let searchTimeout;
            $('#global-search').on('keyup', function() {
                clearTimeout(searchTimeout);
                const searchValue = $(this).val();

                searchTimeout = setTimeout(function() {
                    // Get current URL and add/update search parameter
                    const url = new URL(window.location.href);

                    if (searchValue.trim() !== '') {
                        url.searchParams.set('search', searchValue);
                    } else {
                        url.searchParams.delete('search');
                    }

                    // Reset to page 1 when searching
                    url.searchParams.delete('page');

                    // Redirect to new URL with search parameter
                    window.location.href = url.toString();
                }, 500); // 500ms debounce delay
            });

            // Allow Enter key to trigger search immediately
            $('#global-search').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    clearTimeout(searchTimeout);
                    $(this).trigger('keyup');
                }
            });
        });
</script>
<script>
    $(document).ready(function() {
            let selectedClientId = null;

            // When delete button clicked
            $(document).on('click', '.delete-client', function(e) {
                e.preventDefault();
                selectedClientId = $(this).data('id');
                $('#custom-delete-alert').removeClass('hidden');
            });

            // When confirm delete
            $('#confirm-delete').click(function() {
                if (!selectedClientId) return;

                $.ajax({
                    url: "{{ url('admin/clients') }}/" + selectedClientId,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        console.log('Client delete response:', response);
                        if (response.success) {
                            $('#custom-delete-alert').addClass('hidden');
                            location.reload();
                        } else {
                            showToast('error', response.message || 'Failed to delete client');
                            $('#custom-delete-alert').addClass('hidden');
                            console.warn('Client delete returned falsy success:', response);
                        }
                    },
                    error: function(xhr) {
                        console.error('Client delete error:', xhr);
                        let msg = 'Something went wrong.';
                        try {
                            const json = JSON.parse(xhr.responseText || '{}');
                            if (json.message) msg = json.message;
                        } catch (e) {}
                        showToast('error', msg);
                        $('#custom-delete-alert').addClass('hidden');
                    }
                });
            });

            // Cancel delete
            $('#decline-delete, #close-alert').click(function() {
                $('#custom-delete-alert').addClass('hidden');
                selectedClientId = null;
            });

            // Lead deletion
            let selectedLeadId = null;
            $(document).on('click', '.delete-lead', function(e) {
                e.preventDefault();
                selectedLeadId = $(this).data('lead-id');
                $('#custom-delete-lead-alert').removeClass('hidden');
            });

            $('#confirm-lead-delete').click(function() {
                if (!selectedLeadId) return;

                $.ajax({
                    url: "{{ url('admin/leads') }}/" + selectedLeadId,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        _method: 'DELETE',
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        console.log('Lead delete response:', response);
                        if (response.success) {
                            $('#custom-delete-lead-alert').addClass('hidden');
                            showToast('success', response.message ||
                                'Lead deleted successfully');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            showToast('error', response.message || 'Failed to delete lead');
                        }
                    },
                    error: function(xhr) {
                        console.error('Lead delete error:', xhr);
                        let msg = 'Something went wrong.';
                        if (xhr && xhr.responseJSON && xhr.responseJSON.message) msg = xhr
                            .responseJSON.message;
                        else if (xhr && xhr.responseText) {
                            try {
                                const parsed = JSON.parse(xhr.responseText);
                                if (parsed && parsed.message) msg = parsed.message;
                            } catch (e) {
                                // fallback to raw text
                                msg = xhr.responseText.substring(0, 200);
                            }
                        }
                        showToast('error', msg);
                        $('#custom-delete-lead-alert').addClass('hidden');
                    }
                });
            });

            $('#decline-lead-delete, #close-lead-delete-alert').click(function() {
                $('#custom-delete-lead-alert').addClass('hidden');
                selectedLeadId = null;
            });
        });
        $(document).ready(function() {
            let clientIdToToggle = null;
            let currentStatus = null;

            // Initialize tooltips
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function(tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }

            // Status toggle functionality
            $(document).on('click', '.toggle-client-status', function(e) {
                e.preventDefault();
                clientIdToToggle = $(this).data('id');
                currentStatus = $(this).data('status');
                const clientName = $(this).data('name');

                const action = currentStatus ? 'deactivate' : 'activate';
                $('#status-modal-message').text(`Are you sure you want to ${action} ${clientName}?`);
                $('#toggle-status-modal').removeClass('hidden');
            });

            $('#confirm-status-toggle').click(function() {
                if (!clientIdToToggle) return;

                $.ajax({
                    url: "{{ route('admin.clients.toggle-status', '') }}/" + clientIdToToggle,
                    type: 'PATCH',
                    data: {
                        _token: "{{ csrf_token() }}"
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#toggle-status-modal').addClass('hidden');
                            showToast('success', response.message);

                            // Update the UI elements
                            const button = $(
                                `.toggle-client-status[data-id="${clientIdToToggle}"]`);
                            const newStatus = response.new_status;

                            // Update button icon and tooltip
                            button.find('i')
                                .removeClass(newStatus ? 'ri-lock-line' : 'ri-check-line')
                                .addClass(newStatus ? 'ri-check-line' : 'ri-lock-line');

                            button.attr('title', newStatus ? 'Deactivate' : 'Activate')
                                .data('status', newStatus);

                            // Update status badge (10th column)
                            const statusCell = button.closest('tr').find('.leads-status');
                            if (newStatus) {
                                statusCell.html(
                                    '<span class="badge bg-success/10 text-success">Active</span>'
                                );
                            } else {
                                statusCell.html(
                                    '<span class="badge bg-danger/10 text-danger">Inactive</span>'
                                );
                            }
                        } else {
                            showToast('error', response.message);
                        }
                    },
                    error: function(xhr) {
                        console.error(xhr.responseText);
                        showToast('error', xhr.responseJSON?.message ||
                            "Something went wrong.");
                    }
                });
            });

            // Cancel toggle
            $('#cancel-toggle, #close-toggle-modal').click(function() {
                $('#toggle-status-modal').addClass('hidden');
                clientIdToToggle = null;
                currentStatus = null;
            });

            function showToast(type, message) {
                const toast = document.createElement('div');
                toast.className = `fixed top-4 right-4 z-50 px-4 py-2 rounded-md text-white ${
                type === 'success' ? 'bg-green-500' : 'bg-red-500'
            }`;
                toast.textContent = message;
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 3000);
            }
        });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let activePopup = null;
        let activeTrigger = null;

        /*
         * Existing follow-up popup ki original position store karta hai.
         */
        const popupRecords = new WeakMap();

        function restorePopup(popup) {
            if (!popup) {
                return;
            }

            const record = popupRecords.get(popup);

            popup.classList.remove(
                'followup-popup-visible'
            );

            /*
             * Popup ko original Follow-up History cell mein wapas rakhein.
             */
            if (
                record &&
                record.placeholder &&
                record.placeholder.parentNode
            ) {
                record.placeholder.parentNode.insertBefore(
                    popup,
                    record.placeholder.nextSibling
                );
            }

            if (record && record.trigger) {
                record.trigger.setAttribute(
                    'aria-expanded',
                    'false'
                );
            }

            if (activePopup === popup) {
                activePopup = null;
                activeTrigger = null;
            }
        }

        function showPopup(trigger, popup) {
            /*
             * Agar kisi doosri row ka popup open hai to pehle close karein.
             */
            if (
                activePopup &&
                activePopup !== popup
            ) {
                restorePopup(activePopup);
            }

            activePopup = popup;
            activeTrigger = trigger;

            /*
             * Existing popup ko body mein move karte hain,
             * taaki DataTable container usko clip na kare.
             */
            document.body.appendChild(popup);

            popup.classList.add(
                'followup-popup-visible'
            );

            trigger.setAttribute(
                'aria-expanded',
                'true'
            );
        }

        document
            .querySelectorAll(
                '.followup-history-preview'
            )
            .forEach(function(trigger) {
                const historyCell = trigger.closest('td');

                if (!historyCell) {
                    return;
                }

                const popup = historyCell.querySelector(
                    '.followup-history-popup'
                );

                /*
                 * Follow-up data nahi hai to click action nahi lagega.
                 */
                if (!popup) {
                    return;
                }

                const placeholder =
                    document.createComment(
                        'followup-history-original-position'
                    );

                popup.parentNode.insertBefore(
                    placeholder,
                    popup
                );

                popupRecords.set(popup, {
                    placeholder: placeholder,
                    trigger: trigger
                });

                /*
                 * Click par popup open hoga.
                 * Same column dobara click karne par close hoga.
                 */
                trigger.addEventListener(
                    'click',
                    function(event) {
                        event.preventDefault();
                        event.stopPropagation();

                        if (
                            activePopup === popup &&
                            popup.classList.contains(
                                'followup-popup-visible'
                            )
                        ) {
                            restorePopup(popup);
                            return;
                        }

                        showPopup(trigger, popup);
                    }
                );

                /*
                 * Enter ya Space key se bhi modal toggle hoga.
                 */
                trigger.addEventListener(
                    'keydown',
                    function(event) {
                        if (
                            event.key !== 'Enter' &&
                            event.key !== ' '
                        ) {
                            return;
                        }

                        event.preventDefault();

                        if (
                            activePopup === popup &&
                            popup.classList.contains(
                                'followup-popup-visible'
                            )
                        ) {
                            restorePopup(popup);
                            return;
                        }

                        showPopup(trigger, popup);
                    }
                );

                /*
                 * Popup ke andar click karne par modal close nahi hoga.
                 * View attachment links bhi kaam karenge.
                 */
                popup.addEventListener(
                    'click',
                    function(event) {
                        event.stopPropagation();
                    }
                );

                /*
                 * Popup ke andar scrolling normal chalegi.
                 */
                popup.addEventListener(
                    'wheel',
                    function(event) {
                        event.stopPropagation();
                    },
                    {
                        passive: true
                    }
                );
            });

        /*
         * Modal ke bahar click karne par close.
         */
        document.addEventListener(
            'click',
            function(event) {
                if (!activePopup) {
                    return;
                }

                const clickedInsidePopup =
                    activePopup.contains(event.target);

                const clickedOnTrigger =
                    activeTrigger &&
                    activeTrigger.contains(event.target);

                if (
                    !clickedInsidePopup &&
                    !clickedOnTrigger
                ) {
                    restorePopup(activePopup);
                }
            }
        );

        /*
         * Escape key se close.
         */
        document.addEventListener(
            'keydown',
            function(event) {
                if (
                    event.key === 'Escape' &&
                    activePopup
                ) {
                    restorePopup(activePopup);
                }
            }
        );

        /*
         * Browser tab change hone par popup safely close.
         */
        document.addEventListener(
            'visibilitychange',
            function() {
                if (
                    document.hidden &&
                    activePopup
                ) {
                    restorePopup(activePopup);
                }
            }
        );
    });


        function clearFilters() {
            $('#filter-form')[0].reset();
            $('#global-search').val(''); // Clear global search as well
            window.location.href = '{{ route('admin.clients.index') }}';
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Initially hide filter section and set icon to down
            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function() {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });
        });

        // Handle per-page selection change
        $(document).ready(function() {
            $('#per-page-select').on('change', function() {
                const perPage = $(this).val();
                const params = new URLSearchParams(window.location.search);
                params.set('per_page', perPage);
                params.delete('page'); // reset to first page
                const qs = params.toString();
                const newUrl = window.location.pathname + (qs ? '?' + qs : '');
                window.location.href = newUrl;
            });
        });
</script>
<!-- Add service & vendor information script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("serviceVendor-container");
            const addBtn = document.getElementById("add-serviceVendor-detail");

            if (!container || !addBtn) return; // guard when element not present on page

            // Function to update numbering and toggle remove button visibility
            function updateDetails() {
                const boxes = container.querySelectorAll(".box");
                boxes.forEach((box, index) => {
                    box.querySelector(".badge").textContent = `#${index + 1}`;
                    const removeBtn = box.querySelector(".remove-serviceVendor-detail");
                    if (index === 0) {
                        removeBtn.classList.add("hidden");
                    } else {
                        removeBtn.classList.remove("hidden");
                    }
                });
            }

            // Add new box
            addBtn.addEventListener("click", function() {
                const firstBox = container.querySelector(".box");
                const newBox = firstBox.cloneNode(true);

                // Clear inputs & selects
                newBox.querySelectorAll("input").forEach(input => input.value = "");
                newBox.querySelectorAll("select").forEach(select => select.selectedIndex = 0);

                container.appendChild(newBox);
                updateDetails();
            });

            // Remove box (event delegation)
            container.addEventListener("click", function(e) {
                if (e.target.closest(".remove-serviceVendor-detail")) {
                    const box = e.target.closest(".box");
                    if (box) {
                        box.remove();
                        updateDetails();
                    }
                }
            });

            // Initial setup
            updateDetails();
        });
</script>

<!-- Add extra service & vendor information script -->
<script>
    document.addEventListener("DOMContentLoaded", function() {
            const container = document.getElementById("extraServiceVendor-container");
            const addBtn = document.getElementById("add-extraServiceVendor-detail");

            if (!container || !addBtn) return; // guard when elements not present

            // Add new box
            addBtn.addEventListener("click", function() {
                // Clone first box
                const firstBox = container.querySelector(".box");
                const newBox = firstBox.cloneNode(true);

                // Show close button for new boxes
                const closeBtn = newBox.querySelector(".remove-extraServiceVendor-detail");
                closeBtn.classList.remove("hidden");

                // Update badge number
                const totalBoxes = container.querySelectorAll(".box").length + 1;
                newBox.querySelector(".badge").textContent = "#" + totalBoxes;

                // Clear inputs
                newBox.querySelectorAll("input").forEach(input => input.value = "");
                newBox.querySelectorAll("select").forEach(select => select.selectedIndex = 0);

                container.appendChild(newBox);
            });

            // Remove box (event delegation)
            container.addEventListener("click", function(e) {
                if (e.target.closest(".remove-extraServiceVendor-detail")) {
                    const box = e.target.closest(".box");
                    if (box) box.remove();

                    // Re-number badges after removal
                    container.querySelectorAll(".badge").forEach((badge, index) => {
                        badge.textContent = "#" + (index + 1);
                    });
                }
            });
        });
</script>

<!-- Add personal detail information script -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('personal-details-container');
            const addBtn = document.getElementById('add-personal-detail');

            if (!container || !addBtn) return;

            addBtn.addEventListener('click', () => {
                // Clone the first box
                const original = container.querySelector('.personal-detail');
                if (!original) return;
                const clone = original.cloneNode(true);

                // Reset form values
                clone.querySelectorAll('input').forEach(input => input.value = '');

                // Update badge number
                const count = container.querySelectorAll('.personal-detail').length + 1;
                const badge = clone.querySelector('.badge');
                if (badge) badge.innerText = `#${count}`;

                // Show the close button
                const removeBtn = clone.querySelector('.remove-personal-detail');
                if (removeBtn) removeBtn.classList.remove('hidden');

                // Append clone
                container.appendChild(clone);
            });

            // Delegate removal
            container.addEventListener('click', function(e) {
                if (e.target.closest('.remove-personal-detail')) {
                    const detailBox = e.target.closest('.personal-detail');
                    if (detailBox && container.contains(detailBox)) container.removeChild(detailBox);

                    // Re-number remaining badges
                    container.querySelectorAll('.badge').forEach((badge, index) => {
                        badge.innerText = `#${index + 1}`;
                    });
                }
            });
        });
</script>
@endpush
