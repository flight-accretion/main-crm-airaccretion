@extends('admin.layouts.header')

@section('content')

<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/daily-outreach.css') }}?v=4">

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



@if($standardLocked)
    <div class="alert alert-success">
        Your standard {{ (int) $dailyTarget }} are complete for today. Additional KPI work must use Get More Numbers.
    </div>
@endif

<div class="do-notice">
    Select DNP or enter a remark, then click Save.
    If both are provided, the action is recorded as DNP
    and the remark is preserved as a note.
    Existing call-verification and KPI rules still apply.
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
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Popup Helpers
    |--------------------------------------------------------------------------
    */

    function popup(icon, title, message) {
        return Swal.fire({
            icon: icon,
            title: title,
            text: String(message || ''),
            confirmButtonText: 'OK',
            allowOutsideClick: false,
            allowEscapeKey: true
        });
    }

    function getErrorMessage(xhr) {
        const body = xhr.responseJSON || {};
        const status = xhr.status;

        if (status === 419) {
            return 'Your session has expired. Please refresh the page and sign in again.';
        }

        if (status === 401) {
            return 'Please sign in again.';
        }

        if (status === 403) {
            return 'You do not have permission to perform this action, or this assignment is no longer available.';
        }

        if (status === 422) {
            if (body.errors) {
                const first = Object.values(body.errors)
                    .flat()
                    .find(value => typeof value === 'string');

                if (first) {
                    return first;
                }
            }

            return typeof body.message === 'string'
                ? body.message
                : 'Please check the entered details.';
        }

        if (status >= 500) {
            return 'A server error occurred. Please try again or contact the administrator.';
        }

        if (status === 0) {
            return 'Unable to connect to the server. Please check your connection.';
        }

        return 'Unable to save the outreach action.';
    }

    /*
    |--------------------------------------------------------------------------
    | Existing Flash Messages
    |--------------------------------------------------------------------------
    */

    const flashSuccess = @json(session('success'));
    const flashError = @json($errors->first());

    if (flashError) {
        popup('error', 'Action Failed', flashError);
    } else if (flashSuccess) {
        popup('success', 'Success', flashSuccess);
    }

    /*
    |--------------------------------------------------------------------------
    | Copy Phone Number
    |--------------------------------------------------------------------------
    */

   
/*
|--------------------------------------------------------------------------
| Mobile Number - Desktop Copy / Mobile Dialer
|--------------------------------------------------------------------------
*/

function isMobilePhone() {
    if (
        navigator.userAgentData &&
        typeof navigator.userAgentData.mobile === 'boolean'
    ) {
        return navigator.userAgentData.mobile;
    }

    return /Android|iPhone|iPod/i.test(navigator.userAgent);
}

document.addEventListener('click', async function (event) {

    const phoneLink = event.target.closest(
        '.crm-daily-outreach [data-outreach-phone]'
    );

    if (!phoneLink) {
        return;
    }

    const phone = (
        phoneLink.getAttribute('data-outreach-phone') || ''
    ).trim();

    if (!phone) {
        event.preventDefault();
        return;
    }

    // Mobile: open the native phone dialer.
    if (isMobilePhone()) {
        return;
    }

    // Desktop: copy number instead of opening dialer.
    event.preventDefault();

    try {
        if (!navigator.clipboard?.writeText) {
            throw new Error('Clipboard unavailable');
        }

        await navigator.clipboard.writeText(phone);

        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Number Copied',
                text: phone,
                toast: true,
                position: 'top-end',
                timer: 1500,
                showConfirmButton: false,
                timerProgressBar: true
            });
        }

    } catch (error) {
        if (window.Swal) {
            Swal.fire({
                icon: 'error',
                title: 'Copy Failed',
                text: 'Unable to copy the phone number.',
                confirmButtonText: 'OK'
            });
        }
    }
});


    /*
    |--------------------------------------------------------------------------
    | Unified DNP / Remark Save
    |--------------------------------------------------------------------------
    */

    document.addEventListener('submit', async function (event) {
        const form = event.target.closest(
            '.do-outreach-save-form'
        );

        if (!form) {
            return;
        }

        event.preventDefault();

        if (form.dataset.saving === '1') {
            return;
        }

        const formId = form.id;

        const dnp = form.querySelector(
            'input[name="dnp"]'
        );

        const remark = document.querySelector(
            'textarea[name="remark"][form="' + formId + '"]'
        );

        const button = document.querySelector(
            'button[type="submit"][form="' + formId + '"]'
        );

        if (!dnp || !remark || !button) {
            await popup(
                'error',
                'Form Error',
                'Outreach form is incomplete. Please refresh the page.'
            );
            return;
        }

        if (button.disabled) {
            return;
        }

        const isDnp = dnp.checked;
        const remarkText = remark.value.trim();

        /*
         * No action selected.
         */
        if (!isDnp && !remarkText) {
            await popup(
                'warning',
                'Action Required',
                'Please select DNP or enter a remark.'
            );
            return;
        }

        /*
         * Determine one completion type.
         */
        let actionDescription = '';

        if (isDnp && remarkText) {
            actionDescription =
                'Mark this number as DNP and save the remark?';
        } else if (isDnp) {
            actionDescription =
                'Mark this number as DNP?';
        } else {
            actionDescription =
                'Save this remark and complete the outreach action?';
        }

        const confirmation = await Swal.fire({
            icon: 'question',
            title: 'Confirm Outreach Action',
            text: actionDescription,
            showCancelButton: true,
            confirmButtonText: 'Confirm & Save',
            cancelButtonText: 'Cancel',
            reverseButtons: true,
            focusCancel: true
        });

        if (!confirmation.isConfirmed) {
            return;
        }

        /*
         * Prevent duplicate requests.
         */
        if (form.dataset.saving === '1') {
            return;
        }

        form.dataset.saving = '1';

        const originalButtonText = button.textContent;

        button.disabled = true;
        dnp.disabled = true;
        remark.readOnly = true;
        button.textContent = 'Saving...';

        try {
            const formData = new FormData(form);

            /*
             * External form-associated textarea is
             * included by modern browsers, but set
             * explicitly for predictable behavior.
             */
            formData.set('remark', remarkText);

            if (isDnp) {
                formData.set('dnp', '1');
            } else {
                formData.delete('dnp');
            }

            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const contentType = response.headers.get(
                'content-type'
            ) || '';

            let result = {};

            if (contentType.includes('application/json')) {
                result = await response.json();
            }

            if (!response.ok || !result.success) {
                throw {
                    status: response.status,
                    responseJSON: result
                };
            }

            await popup(
                'success',
                'Saved Successfully',
                result.message || 'Outreach action saved successfully.'
            );

            /*
             * Reload to fetch the updated rolling
             * queue, KPI progress and replacement row.
             */
            window.location.reload();

        } catch (error) {
            let message = getErrorMessage(error);

            if (error instanceof TypeError) {
                message = 'Unable to connect to the server. Please check your connection.';
            }

            await popup(
                'error',
                'Unable to Save',
                message
            );

        } finally {
            delete form.dataset.saving;

            button.disabled = false;
            dnp.disabled = false;
            remark.readOnly = false;
            button.textContent = originalButtonText;
        }
    });

    /*
    |--------------------------------------------------------------------------
    | DataTables Initialization
    |--------------------------------------------------------------------------
    */

    if (
        !window.jQuery ||
        !jQuery.fn ||
        !jQuery.fn.DataTable
    ) {
        return;
    }

    jQuery('.kpi-outreach-datatable').each(function () {
        const table = this;

        if (jQuery.fn.DataTable.isDataTable(table)) {
            return;
        }

        const headerCount = table.querySelectorAll(
            'thead tr:first-child th'
        ).length;

        /*
         * Verify table structure before initializing.
         */
        const rows = Array.from(
            table.querySelectorAll('tbody tr')
        );

        const valid = rows.every(function (row) {
            return row.children.length === headerCount &&
                Array.from(row.children).every(function (cell) {
                    return cell.tagName === 'TD' &&
                        cell.colSpan === 1;
                });
        });

        if (!valid || headerCount !== 6) {
            console.error(
                'Daily Outreach table structure is invalid. DataTables initialization skipped.'
            );
            return;
        }

        jQuery(table).DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            responsive: false,
            scrollX: false,
            autoWidth: false,
            ordering: true,
            columnDefs: [
                {
                    orderable: false,
                    targets: [3, 4, 5]
                }
            ],
            language: {
                emptyTable: 'No outreach numbers available.',
                zeroRecords: 'No matching outreach numbers found.'
            }
        });
    });
});
</script>
@endpush

