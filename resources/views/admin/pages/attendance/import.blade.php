@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">

    <div>

        <h3
            class="!text-defaulttextcolor text-[1.125rem] font-semibold"
        >
            Attendance Import
        </h3>

    </div>

</div>


@if(session('success'))

    <div
        class="alert alert-success mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded"
    >
        {{ session('success') }}
    </div>

@endif


@if(session('error'))

    <div
        class="alert alert-danger mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"
    >
        {{ session('error') }}
    </div>

@endif


<div
    id="attendanceAjaxError"
    class="hidden alert alert-danger mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded"
></div>


<div class="box">

    <div class="box-header">

        <h5 class="box-title flex items-center">

            <i class="ti ti-upload mr-2"></i>
            &nbsp;

            Upload Attendance

        </h5>

    </div>


    <div class="box-body">

        <form
            id="attendancePreviewForm"
            enctype="multipart/form-data"
        >

            @csrf


            <div
                class="grid grid-cols-1 md:grid-cols-2 gap-6"
            >

               <div>

    <label
        for="from_date"
        class="form-label"
    >
        From Date

        <span class="text-red-500">
            *
        </span>
    </label>

    <input
        type="date"
        name="from_date"
        id="from_date"
        class="form-control"
        value="{{ old('from_date') }}"
        max="{{ now()->toDateString() }}"
        required
    >

</div>


<div>

    <label
        for="to_date"
        class="form-label"
    >
        To Date

        <span class="text-red-500">
            *
        </span>
    </label>

    <input
        type="date"
        name="to_date"
        id="to_date"
        class="form-control"
        value="{{ old('to_date') }}"
        max="{{ now()->toDateString() }}"
        required
    >

    <div class="text-sm text-gray-500 mt-1">
        Only attendance inside this date range
        will be imported.
    </div>

</div>


                <div>

                    <label
                        for="excel_file"
                        class="form-label"
                    >

                        Attendance File

                        <span class="text-red-500">
                            *
                        </span>

                    </label>


                    <input
                        type="file"
                        name="excel_file"
                        id="excel_file"
                        accept=".xlsx,.xls,.csv"
                        class="form-control"
                        required
                    >


                    <div
                        class="text-sm text-gray-500 mt-1"
                    >
                        Supported: XLSX, XLS, CSV.
                        Maximum 10MB.
                    </div>

                </div>

            </div>


            <div class="mt-6">

                <button
                    type="button"
                    id="attendancePreviewButton"
                    class="ti-btn ti-btn-info-full ti-btn-wave"
                >

                    <i class="ti ti-eye mr-2"></i>

                    Preview Data

                </button>

            </div>

        </form>

    </div>

</div>


<div
    id="attendancePreviewSection"
    class="box hidden"
>

    <div
        class="box-header flex items-center justify-between"
    >

        <h5 class="box-title">
            Attendance Preview
        </h5>

        <span
            id="attendancePreviewPeriod"
            class="text-sm text-gray-500"
        ></span>

    </div>


    <div class="box-body">

        <div
            class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6"
        >

            <div class="border rounded p-4">

                <div class="text-sm text-gray-500">
                    Employees
                </div>

                <div
                    id="previewEmployeeCount"
                    class="text-2xl font-semibold"
                >
                    0
                </div>

            </div>


            <div class="border rounded p-4">

                <div class="text-sm text-gray-500">
                    Attendance Rows
                </div>

                <div
                    id="previewRecordCount"
                    class="text-2xl font-semibold"
                >
                    0
                </div>

            </div>


            <div class="border rounded p-4">

                <div class="text-sm text-gray-500">
                    Matched
                </div>

                <div
                    id="previewMatchedCount"
                    class="text-2xl font-semibold"
                >
                    0
                </div>

            </div>


            <div class="border rounded p-4">

                <div class="text-sm text-gray-500">
                    Need Mapping
                </div>

                <div
                    id="previewUnmatchedCount"
                    class="text-2xl font-semibold"
                >
                    0
                </div>

            </div>

        </div>


        <div
            id="futureRowsWarning"
            class="hidden alert alert-warning mb-4 p-3 bg-yellow-50 border border-yellow-200 text-yellow-700 rounded"
        ></div>


        <div class="mb-6">

            <h6 class="font-semibold mb-3">
                Employee Mapping
            </h6>


            <div class="overflow-x-auto">

                <table
                    class="table whitespace-nowrap min-w-full"
                >

                    <thead>

                        <tr>
                            <th>Paycode</th>
                            <th>Employee in File</th>
                            <th>CRM Employee</th>
                            <th>Period</th>
                            <th>Rows</th>
                            <th>Match</th>
                        </tr>

                    </thead>


                    <tbody
                        id="attendanceMappingBody"
                    ></tbody>

                </table>

            </div>

        </div>


        <div class="mb-6">

            <h6 class="font-semibold mb-3">
                Daily Attendance Rows
            </h6>


            <div
                class="overflow-x-auto max-h-[520px]"
            >

                <table
                    class="table whitespace-nowrap min-w-full"
                >

                    <thead
                        class="sticky top-0 bg-white"
                    >

                        <tr>
                            <th>Paycode</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Day</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                        </tr>

                    </thead>


                    <tbody
                        id="attendanceRowsBody"
                    ></tbody>

                </table>

            </div>

        </div>


        <form
            method="POST"
            action="{{
                route(
                    'admin.attendance.import.confirm'
                )
            }}"
            id="attendanceConfirmForm"
        >

            @csrf


            <input
                type="hidden"
                name="import_id"
                id="attendanceImportId"
            >


            <div
                id="attendanceMappingHidden"
            ></div>


            <button
                type="submit"
                class="ti-btn ti-btn-primary-full ti-btn-wave"
            >

                <i class="ti ti-check mr-2"></i>

                Confirm Import

            </button>

        </form>

    </div>

</div>


<div class="box mt-6">

    <div class="box-header">

        <h5 class="box-title">
            Recent Attendance Imports
        </h5>

    </div>


    <div
        class="box-body overflow-x-auto"
    >

        <table
            class="table whitespace-nowrap min-w-full"
        >

            <thead>

                <tr>
                    <th>Uploaded</th>
                   <th>Selected Range</th>
                    <th>Period</th>
                    <th>File</th>
                    <th>Uploaded By</th>
                    <th>Employees</th>
                    <th>Rows</th>
                    <th>New</th>
                    <th>Updated</th>
                    <th>Status</th>
                </tr>

            </thead>


            <tbody>

                @forelse(
                    $imports
                    as $import
                )

                    <tr>

                        <td>
                            {{
                                optional(
                                    $import->created_at
                                )->format(
                                    'd M Y, h:i A'
                                )
                            }}
                        </td>


                      <td>

    {{
        optional(
            $import->from_date
        )->format('d M Y')
        ?: '-'
    }}

    -

    {{
        optional(
            $import->to_date
        )->format('d M Y')
        ?: '-'
    }}

</td>


                        <td>

                            {{
                                optional(
                                    $import->period_from
                                )->format(
                                    'd M Y'
                                )
                                ?: '-'
                            }}

                            @if(
                                $import->period_to
                            )

                                -
                                {{
                                    $import
                                        ->period_to
                                        ->format(
                                            'd M Y'
                                        )
                                }}

                            @endif

                        </td>


                        <td>
                            {{
                                $import
                                    ->original_filename
                            }}
                        </td>


                        <td>
                            {{
                                optional(
                                    $import->uploadedBy
                                )->name
                                ?: '-'
                            }}
                        </td>


                        <td>
                            {{
                                $import
                                    ->total_employees
                            }}
                        </td>


                        <td>
                            {{
                                $import
                                    ->total_records
                            }}
                        </td>


                        <td>
                            {{
                                $import
                                    ->created_records
                            }}
                        </td>


                        <td>
                            {{
                                $import
                                    ->updated_records
                            }}
                        </td>


                        <td>

                            <span
                                class="badge {{
                                    $import->status
                                    === 'completed'
                                        ? 'bg-success/10 text-success'
                                        : 'bg-warning/10 text-warning'
                                }}"
                            >

                                {{
                                    ucfirst(
                                        $import->status
                                    )
                                }}

                            </span>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="10"
                            class="text-center text-gray-500"
                        >
                            No attendance imports yet.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

</div>

@endsection


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const form =
            document.getElementById(
                'attendancePreviewForm'
            );

        const previewButton =
            document.getElementById(
                'attendancePreviewButton'
            );

        const previewSection =
            document.getElementById(
                'attendancePreviewSection'
            );

        const mappingBody =
            document.getElementById(
                'attendanceMappingBody'
            );

        const rowsBody =
            document.getElementById(
                'attendanceRowsBody'
            );

        const importId =
            document.getElementById(
                'attendanceImportId'
            );

        const hiddenMappings =
            document.getElementById(
                'attendanceMappingHidden'
            );

        const confirmForm =
            document.getElementById(
                'attendanceConfirmForm'
            );

        const errorBox =
            document.getElementById(
                'attendanceAjaxError'
            );

        const futureWarning =
            document.getElementById(
                'futureRowsWarning'
            );


        let previewPayload = null;


        function escapeHtml(value) {

            return String(value ?? '')
                .replace(
                    /&/g,
                    '&amp;'
                )
                .replace(
                    /</g,
                    '&lt;'
                )
                .replace(
                    />/g,
                    '&gt;'
                )
                .replace(
                    /"/g,
                    '&quot;'
                )
                .replace(
                    /'/g,
                    '&#039;'
                );
        }


        function showError(message) {

            errorBox.textContent =
                message;

            errorBox
                .classList
                .remove(
                    'hidden'
                );

            errorBox.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }


        function clearError() {

            errorBox.textContent = '';

            errorBox
                .classList
                .add(
                    'hidden'
                );
        }


        function userOptions(
            users,
            selectedId
        ) {

            let html =
                '<option value="">'
                + 'Select CRM Employee'
                + '</option>';


            users.forEach(
                function (user) {

                    const selected =
                        String(
                            user.id
                        )
                        ===
                        String(
                            selectedId
                            || ''
                        )
                            ? ' selected'
                            : '';

                    const role =
                        user.role
                            ? ' - '
                                + user.role
                            : '';

                    html +=
                        '<option value="'
                        + escapeHtml(
                            user.id
                        )
                        + '"'
                        + selected
                        + '>'
                        + escapeHtml(
                            user.name
                            + role
                        )
                        + '</option>';
                }
            );


            return html;
        }


        function renderPreview(
            payload
        ) {

            previewPayload =
                payload;

            importId.value =
                payload.import_id;


            document
                .getElementById(
                    'previewEmployeeCount'
                )
                .textContent =
                    payload
                        .total_employees
                    || 0;


            document
                .getElementById(
                    'previewRecordCount'
                )
                .textContent =
                    payload
                        .total_records
                    || 0;


            const matched =
                (
                    payload.employees
                    || []
                )
                .filter(
                    function (
                        employee
                    ) {
                        return !!employee
                            .user_id;
                    }
                )
                .length;


            document
                .getElementById(
                    'previewMatchedCount'
                )
                .textContent =
                    matched;


            document
                .getElementById(
                    'previewUnmatchedCount'
                )
                .textContent =
                    (
                        payload
                            .total_employees
                        || 0
                    )
                    - matched;


document
    .getElementById(
        'attendancePreviewPeriod'
    )
    .textContent =
        'Selected: '
        + (
            payload.from_date
            || '-'
        )
        + ' to '
        + (
            payload.to_date
            || '-'
        )
        + ' | Imported period: '
        + (
            payload.period_from
            || '-'
        )
        + ' to '
        + (
            payload.period_to
            || '-'
        );


      if (
    (
        payload.outside_range_rows
        || 0
    )
    > 0
) {

                futureWarning
                    .textContent =
                       payload.outside_range_rows
+ ' attendance row(s) outside the selected From/To date range were ignored.';

                futureWarning
                    .classList
                    .remove(
                        'hidden'
                    );

            } else {

                futureWarning
                    .classList
                    .add(
                        'hidden'
                    );

                futureWarning
                    .textContent =
                        '';
            }


            mappingBody.innerHTML =
                '';


            (
                payload.employees
                || []
            )
            .forEach(
                function (
                    employee
                ) {

                    const tr =
                        document
                            .createElement(
                                'tr'
                            );


                    const matchLabel =
                        employee
                            .match_type
                        ===
                        'saved_paycode'

                            ? 'Saved Paycode'

                            : (
                                employee
                                    .match_type
                                ===
                                'exact_name'

                                    ? 'Exact Name'

                                    : 'Mapping Required'
                            );


                    tr.innerHTML =
                        `
                        <td>
                            ${escapeHtml(
                                employee.paycode
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                employee.file_name
                            )}
                        </td>

                        <td style="min-width:320px;">
                            <select
                                class="ti-form-select attendance-user-map"
                                data-paycode="${escapeHtml(
                                    employee.paycode
                                )}"
                            >
                                ${userOptions(
                                    payload.users || [],
                                    employee.user_id
                                )}
                            </select>
                        </td>

                        <td>
                            ${escapeHtml(
                                employee.period_from
                            )}
                            -
                            ${escapeHtml(
                                employee.period_to
                            )}
                        </td>

                        <td>
                            ${Number(
                                employee.record_count
                                || 0
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                matchLabel
                            )}
                        </td>
                        `;


                    mappingBody
                        .appendChild(
                            tr
                        );
                }
            );


            rowsBody.innerHTML =
                '';


            (
                payload.rows
                || []
            )
            .forEach(
                function (row) {

                    const tr =
                        document
                            .createElement(
                                'tr'
                            );


                    tr.innerHTML =
                        `
                        <td>
                            ${escapeHtml(
                                row.paycode
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.file_name
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.attendance_date
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.day_name
                                || '-'
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.raw_in
                                || '-'
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.raw_out
                                || '-'
                            )}
                        </td>

                        <td>
                            ${escapeHtml(
                                row.raw_status
                                || '-'
                            )}
                        </td>
                        `;


                    rowsBody
                        .appendChild(
                            tr
                        );
                }
            );


            previewSection
                .classList
                .remove(
                    'hidden'
                );


            previewSection
                .scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
        }


        previewButton
            .addEventListener(
                'click',
                function () {

                    clearError();


                   const fromDateInput =
                    document.getElementById(
                        'from_date'
                    );

                const toDateInput =
                    document.getElementById(
                        'to_date'
                    );


                    const fileInput =
                        document
                            .getElementById(
                                'excel_file'
                            );


if (!fromDateInput.value) {

    showError(
        'Please select From Date.'
    );

    return;
}


if (!toDateInput.value) {

    showError(
        'Please select To Date.'
    );

    return;
}


if (
    toDateInput.value
    <
    fromDateInput.value
) {

    showError(
        'To Date cannot be before From Date.'
    );

    return;
}


                    if (
                        !fileInput
                            .files
                            .length
                    ) {

                        showError(
                            'Please select an XLSX, XLS, or CSV attendance file.'
                        );

                        return;
                    }


                    const formData =
                        new FormData(
                            form
                        );


                    previewButton
                        .disabled =
                            true;


                    previewButton
                        .innerHTML =
                            '<i class="ti ti-loader animate-spin mr-2"></i> Reading Attendance...';


                    fetch(
                        @json(
                            route(
                                'admin.attendance.import.preview'
                            )
                        ),
                        {
                            method:
                                'POST',

                            body:
                                formData,

                            headers: {
                                'Accept':
                                    'application/json',

                                'X-Requested-With':
                                    'XMLHttpRequest'
                            },

                            credentials:
                                'same-origin'
                        }
                    )
                    .then(
                        async function (
                            response
                        ) {

                            const payload =
                                await response
                                    .json()
                                    .catch(
                                        function () {
                                            return {};
                                        }
                                    );


                            if (
                                !response.ok
                            ) {

                                const errors =
                                    payload.errors
                                    || {};


                                const firstError =
                                    Object
                                        .values(
                                            errors
                                        )
                                        .flat()[0];


                                throw new Error(
                                    firstError
                                    ||
                                    payload.message
                                    ||
                                    'Attendance preview failed.'
                                );
                            }


                            return payload;
                        }
                    )
                    .then(
                        renderPreview
                    )
                    .catch(
                        function (
                            error
                        ) {

                            showError(
                                error.message
                                ||
                                'Attendance preview failed.'
                            );
                        }
                    )
                    .finally(
                        function () {

                            previewButton
                                .disabled =
                                    false;


                            previewButton
                                .innerHTML =
                                    '<i class="ti ti-eye mr-2"></i> Preview Data';
                        }
                    );
                }
            );


        confirmForm
            .addEventListener(
                'submit',
                function (
                    event
                ) {

                    clearError();


                    if (
                        !previewPayload
                        ||
                        !importId.value
                    ) {

                        event
                            .preventDefault();


                        showError(
                            'Please preview the attendance file before confirming the import.'
                        );


                        return;
                    }


                    hiddenMappings
                        .innerHTML =
                            '';


                    const selects =
                        document
                            .querySelectorAll(
                                '.attendance-user-map'
                            );


                    const selectedUsers =
                        new Set();


                    for (
                        const select
                        of selects
                    ) {

                        const paycode =
                            select
                                .dataset
                                .paycode;


                        const userId =
                            select
                                .value;


                        if (!userId) {

                            event
                                .preventDefault();


                            showError(
                                'Please map every paycode to a CRM employee before confirming.'
                            );


                            select.focus();


                            return;
                        }


                        if (
                            selectedUsers
                                .has(
                                    userId
                                )
                        ) {

                            event
                                .preventDefault();


                            showError(
                                'The same CRM employee cannot be selected for two paycodes in this import.'
                            );


                            select.focus();


                            return;
                        }


                        selectedUsers
                            .add(
                                userId
                            );


                        const input =
                            document
                                .createElement(
                                    'input'
                                );


                        input.type =
                            'hidden';


                        input.name =
                            'mappings['
                            + paycode
                            + ']';


                        input.value =
                            userId;


                        hiddenMappings
                            .appendChild(
                                input
                            );
                    }
                }
            );
    }
);

</script>

@endpush