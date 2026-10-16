@extends('admin.layouts.header')

@section('content')
@php
    $filters = $filters ?? [];
    $uploadedUsers = $uploadedUsers ?? collect();
    $perPage = $perPage ?? 25;
    $statusLabel = fn ($status) => strtoupper((string) $status) === 'MIS' ? 'Miss punch' : ($status ?: '-');
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/attendance/import.css') }}">
<div class="attendance-import" id="attendanceImportPage" data-preview-url="{{ route('admin.attendance.import.preview') }}">
    <header class="ai-heading"><h1>Attendance Import</h1></header>
    @foreach(['success', 'error'] as $messageType)
        @if(session($messageType))
            <div class="ai-notice ai-{{ $messageType }}" role="status">{{ session($messageType) }}</div>
        @endif
    @endforeach
    @if($errors->any())
        <div class="ai-notice ai-error" role="alert">{{ $errors->first() }}</div>
    @endif
    <div id="attendanceAjaxError" class="ai-notice ai-error" role="alert" hidden></div>

    @isset($selectedImport)
    <section class="ai-section" aria-labelledby="ai-detail-heading">
        <div class="ai-section-head"><h2 id="ai-detail-heading">{{ $selectedImport->original_filename }}</h2><a href="{{ route('admin.attendance.import.index') }}">Back to imports</a></div>
        <p class="ai-meta">{{ optional($selectedImport->from_date)->format('d M Y') }} - {{ optional($selectedImport->to_date)->format('d M Y') }} | {{ ucfirst($selectedImport->status) }} | {{ $selectedImport->total_records }} imported rows</p>
        <p class="ai-meta">Current records last written by this import. Rows subsequently updated by another import appear under that newer import.</p>
        <div class="ai-table-wrap"><table><thead><tr><th>Employee</th><th>Paycode</th><th>Date</th><th>Day</th><th>In</th><th>Out</th><th>Status</th></tr></thead><tbody>
        @forelse($selectedRecords as $record)
            <tr><td data-label="Employee">{{ optional($record->user)->name ?: 'Unavailable employee' }}</td><td data-label="Paycode">{{ $record->paycode ?: '-' }}</td><td data-label="Date">{{ optional($record->attendance_date)->format('d M Y') }}</td><td data-label="Day">{{ $record->day_name }}</td><td data-label="In">{{ $record->raw_in ?? $record->in_time ?? '-' }}</td><td data-label="Out">{{ $record->raw_out ?? $record->out_time ?? '-' }}</td><td data-label="Status"><span class="ai-tag">{{ $statusLabel($record->raw_status) }}</span></td></tr>
        @empty
            <tr><td colspan="7">No current records belong to this import.</td></tr>
        @endforelse
        </tbody></table></div>
        <div class="ai-pagination">{{ $selectedRecords->links() }}</div>
    </section>
    @endisset

    <section class="ai-section" aria-labelledby="ai-upload-heading">
        <h2 id="ai-upload-heading">Upload attendance</h2>
        <p class="ai-meta">Only attendance inside the selected date range will be imported.</p>
        <form id="attendancePreviewForm" enctype="multipart/form-data">
            @csrf
            <div class="ai-upload-grid">
                <label>From date <span class="ai-required">*</span><input type="date" name="from_date" id="from_date" value="{{ old('from_date') }}" max="{{ now()->toDateString() }}" required></label>
                <label>To date <span class="ai-required">*</span><input type="date" name="to_date" id="to_date" value="{{ old('to_date') }}" max="{{ now()->toDateString() }}" required></label>
                <label>Attendance file <span class="ai-required">*</span><input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" required></label>
            </div>
            <div class="ai-actions"><span class="ai-meta">XLSX, XLS, CSV | max 10MB | Template: <a href="{{ route('admin.attendance.import.sample') }}" title="Download Excel Format">Excel</a> | <a href="{{ route('admin.attendance.import.sample', ['format' => 'csv']) }}" title="Download CSV Format">CSV</a></span><button type="submit" id="attendancePreviewButton" class="ai-primary"><i class="ti ti-eye" aria-hidden="true"></i> Preview Data</button></div>
        </form>
    </section>

    <section id="attendancePreviewSection" class="ai-section" aria-labelledby="ai-preview-heading" hidden>
        <div class="ai-section-head"><h2 id="ai-preview-heading">Preview <span id="attendancePreviewFilename"></span></h2><span id="attendanceMappingState" class="ai-tag" role="status"></span></div>
        <p id="attendancePreviewPeriod" class="ai-meta"></p>
        <div class="ai-stats">
            <div><strong id="previewEmployeeCount">0</strong><span>Employees</span></div><div><strong id="previewRecordCount">0</strong><span>Attendance rows</span></div><div><strong id="previewMatchedCount">0</strong><span>Matched to CRM employees</span></div><div><strong id="previewUnmatchedCount">0</strong><span>Need mapping</span></div>
        </div>
        <p id="futureRowsWarning" class="ai-meta" hidden></p>
        <h3>Employee mapping</h3>
        <div class="ai-table-wrap"><table><thead><tr><th>Paycode</th><th>Employee in file</th><th>CRM employee</th><th>Period</th><th>Rows</th><th>Match</th></tr></thead><tbody id="attendanceMappingBody"></tbody></table></div>
        <h3>Daily attendance rows</h3>
        <div class="ai-table-wrap"><table><thead><tr><th>Paycode</th><th>Employee</th><th>Date</th><th>Day</th><th>In</th><th>Out</th><th>Status</th></tr></thead><tbody id="attendanceRowsBody"></tbody></table></div>
        <div class="ai-pager"><button type="button" id="attendanceRowsPrevious" class="ai-icon" title="Previous daily rows" aria-label="Previous daily rows"><i class="ti ti-chevron-left"></i></button><span id="attendanceRowsPage" aria-live="polite"></span><button type="button" id="attendanceRowsNext" class="ai-icon" title="Next daily rows" aria-label="Next daily rows"><i class="ti ti-chevron-right"></i></button></div>
        <form method="POST" action="{{ route('admin.attendance.import.confirm') }}" id="attendanceConfirmForm" class="ai-confirm">
            @csrf
            <input type="hidden" name="import_id" id="attendanceImportId"><div id="attendanceMappingHidden"></div>
            <span id="attendanceConfirmHint" class="ai-meta" aria-live="polite"></span><button type="submit" id="attendanceConfirmButton" class="ai-primary" disabled><i class="ti ti-check" aria-hidden="true"></i> Confirm Import</button>
        </form>
    </section>

    <section class="ai-section" aria-labelledby="ai-history-heading">
        <div class="ai-section-head"><h2 id="ai-history-heading">Import history</h2><span class="ai-tag">{{ $imports->total() }} imports</span></div>
        <form method="GET" action="{{ route('admin.attendance.import.index') }}" class="ai-filters">
            <label>From date<input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}"></label>
            <label>To date<input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}"></label>
            <label>Status<select name="status"><option value="">All</option>@foreach(['previewed' => 'Previewed', 'completed' => 'Completed'] as $value => $label)<option value="{{ $value }}" {{ ($filters['status'] ?? '') === $value ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></label>
            <label>Uploaded by<select name="uploaded_by"><option value="">All</option>@foreach($uploadedUsers as $uploader)<option value="{{ $uploader->id }}" {{ ($filters['uploaded_by'] ?? '') === $uploader->id ? 'selected' : '' }}>{{ $uploader->name }}</option>@endforeach</select></label>
            <label class="ai-search">Search<input type="search" name="search" id="attendance-import-history-search" placeholder="File or uploader" value="{{ $filters['search'] ?? '' }}"></label>
            <label class="ai-page-size">Show<select name="per_page">@foreach([10,25,50,100] as $size)<option value="{{ $size }}" {{ (int) $perPage === $size ? 'selected' : '' }}>{{ $size }}</option>@endforeach</select></label>
            <div class="ai-filter-actions"><a href="{{ route('admin.attendance.import.index') }}" class="ai-icon" title="Reset filters" aria-label="Reset filters"><i class="ri-refresh-line"></i></a><button type="submit" class="ai-primary">Filter</button></div>
        </form>
        <div class="ai-table-wrap"><table id="attendance-imports-table"><thead><tr><th>Uploaded</th><th>Selected range</th><th>Period</th><th>File</th><th>Uploaded by</th><th>Employees</th><th>Rows</th><th>New</th><th>Updated</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($imports as $import)
            <tr><td data-label="Uploaded">{{ optional($import->created_at)->format('d M Y, h:i A') }}</td><td data-label="Selected range">{{ optional($import->from_date)->format('d M Y') }} - {{ optional($import->to_date)->format('d M Y') }}</td><td data-label="Period">{{ optional($import->period_from)->format('d M Y') }} - {{ optional($import->period_to)->format('d M Y') }}</td><td data-label="File">{{ $import->original_filename }}</td><td data-label="Uploaded by">{{ optional($import->uploadedBy)->name ?: '-' }}</td><td data-label="Employees">{{ $import->total_employees }}</td><td data-label="Rows">{{ $import->total_records }}</td><td data-label="New">{{ $import->created_records }}</td><td data-label="Updated">{{ $import->updated_records }}</td><td data-label="Status"><span class="ai-tag {{ $import->status === 'completed' ? 'ai-green' : '' }}">{{ ucfirst($import->status) }}</span></td><td data-label="Action"><a href="{{ url('/admin/attendance/import/' . $import->id) }}">View</a></td></tr>
        @empty
            <tr><td colspan="11">No attendance imports found.</td></tr>
        @endforelse
        </tbody></table></div>
        <div class="ai-pagination">{{ $imports->links() }}</div>
    </section>
    <nav class="ai-mobile-nav" id="attendanceMobileNav" aria-label="Mobile navigation"></nav>
    <dialog id="attendanceMoreDialog" class="ai-more"><div class="ai-section-head"><h2>More</h2><button type="button" class="ai-icon" id="attendanceMoreClose" title="Close navigation" aria-label="Close navigation"><i class="ti ti-x"></i></button></div><nav aria-label="More navigation" id="attendanceMoreLinks"></nav></dialog>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/crm/attendance-import.js') }}" defer></script>
@endpush
