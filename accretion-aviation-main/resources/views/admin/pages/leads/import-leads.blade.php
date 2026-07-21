@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
    <div>
        <h3
            class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
            Import Leads</h3>
    </div>
    <ol class="flex items-center whitespace-nowrap min-w-0">
        <li class="text-[0.813rem] ps-[0.5rem]">
            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                href="javascript:void(0);">
                Lead
                <i
                    class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
            </a>
        </li>
        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
            aria-current="page">
            Import Leads
        </li>
    </ol>
</div>
<!-- Page Header Close -->

<!-- Success/Error Messages -->
@if(session('success'))
<div class="alert alert-success mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
    <div class="flex items-center">
        <i class="ti ti-check-circle mr-2"></i>
        {{ session('success') }}
    </div>

    @if(session('import_summary'))
    <div class="mt-2 text-sm">
        <strong>Import Summary:</strong><br>
        Total Processed: {{ session('import_summary.total') }}<br>
        Successfully Imported: {{ session('import_summary.imported') }}<br>
        @if(session('import_summary.skipped') > 0)
        Skipped: {{ session('import_summary.skipped') }}<br>
        @if(isset(session('import_summary')['skipped_duplicates']))
        &nbsp;&nbsp;• Duplicates: {{ session('import_summary.skipped_duplicates') }}<br>
        @endif
        @if(isset(session('import_summary')['skipped_errors']))
        &nbsp;&nbsp;• Errors: {{ session('import_summary.skipped_errors') }}
        @endif
        @endif
    </div>
    @endif
</div>
@endif

@if(session('error'))
<div class="alert alert-danger mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
    <div class="flex items-center">
        <i class="ti ti-alert-circle mr-2"></i>
        {{ session('error') }}
    </div>
</div>
@endif

@php
$importErrors = session('import_errors');
@endphp
@if($importErrors && count($importErrors) > 0)
<div class="alert alert-warning mb-4 p-4 bg-yellow-100 border border-yellow-400 text-yellow-700 rounded">
    <div class="flex items-center mb-2">
        <i class="ti ti-alert-triangle mr-2"></i>
        <strong>Import Errors:</strong>
    </div>
    <div class="max-h-40 overflow-y-auto">
        @foreach($importErrors as $error)
        <div class="text-sm mb-1">• {{ $error }}</div>
        @endforeach
    </div>
</div>
@endif
@if(session('skipped_reasons') && count(session('skipped_reasons')) > 0)
<div class="alert alert-info mb-4 p-4 bg-blue-50 border border-blue-200 text-blue-700 rounded">
    <div class="flex items-center mb-2">
        <i class="ti ti-info-circle mr-2"></i>
        <strong>Skipped Details:</strong>
    </div>
    <div class="max-h-40 overflow-y-auto">
        @foreach(session('skipped_reasons') as $reason)
        <div class="text-sm mb-1">• {{ $reason }}</div>
        @endforeach
    </div>
</div>
@endif
<!-- Import Form Card -->
<div class="box">
    <div class="box-header">
        <h5 class="box-title flex items-center">
            <i class="ti ti-upload mr-2"></i>&nbsp;
            Upload Excel File
        </h5>
    </div>
    <div class="box-body">
        <form action="{{ route('admin.leads.import.store') }}" method="POST" enctype="multipart/form-data"
            id="importForm">
            @csrf

            <div class="grid grid-cols-1 gap-6">
                <!-- Date Filter for Duplicate Detection -->
                <!-- Date Filter for Duplicate Detection -->
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <div class="flex flex-col md:flex-row gap-6">
                        <!-- From Date -->
                        <div class="w-full md:w-1/2">
                            <label for="filter_from_date" class="form-label flex items-center">
                                Filter From Date
                                <i class="ti ti-info-circle ml-1 text-blue-500"
                                    title="Only check for duplicates from this date onwards"></i>
                            </label>
                            <input type="date" name="filter_from_date" id="filter_from_date" class="form-control"
                                value="{{ date('Y-m-01') }}">
                            <div class="text-sm text-gray-500 mt-1">
                                Check for existing leads from this date
                            </div>
                        </div>

                        <!-- To Date -->
                        <div class="w-full md:w-1/2">
                            <label for="filter_to_date" class="form-label flex items-center">
                                Filter To Date
                                <i class="ti ti-info-circle ml-1 text-blue-500"
                                    title="Only check for duplicates up to this date"></i>
                            </label>
                            <input type="date" name="filter_to_date" id="filter_to_date" class="form-control"
                                value="{{ date('Y-m-d') }}">
                            <div class="text-sm text-gray-500 mt-1">
                                Check for existing leads up to this date
                            </div>
                        </div>
                    </div>
                </div>



                <!-- File Upload -->
                <div>
                    <label for="excel_file" class="form-label">
                        Select Excel File <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv"
                            class="form-control @error('excel_file') is-invalid @enderror" required>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <i class="ti ti-file-excel text-green-500"></i>
                        </div>
                    </div>
                    @error('excel_file')
                    <div class="text-red-500 text-sm mt-1">{{ $message }}</div>
                    @enderror
                    <div class="text-sm text-gray-500 mt-1">
                        Supported formats: .xlsx, .xls, .csv (Max size: 10MB)
                    </div>
                </div>

                <!-- File Info Display -->
                <div id="fileInfo" class="hidden p-4 bg-gray-50 border border-gray-200 rounded">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <i class="ti ti-file-excel text-green-500 mr-2"></i>
                            <span id="fileName" class="font-medium"></span>
                        </div>
                        <div class="text-sm text-gray-500">
                            <span id="fileSize"></span>
                        </div>
                    </div>
                </div>

                <!-- Import Buttons -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-4">
                    <div class="flex flex-row gap-3">
                        <button type="button"
                            class="ti-btn ti-btn-info-full ti-btn-wave flex items-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition"
                            id="previewBtn">
                            <i class="ti ti-eye mr-2"></i>
                            Preview Data
                        </button>
                        <button type="button"
                            class="ti-btn ti-btn-primary-full ti-btn-wave flex items-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition"
                            id="importBtn">
                            <i class="ti ti-upload mr-2"></i>
                            Direct Import
                        </button>
                    </div>
                    <div class="flex flex-row gap-3">
                        <a href="{{ route('admin.leads.import.sample') }}"
                            class="ti-btn ti-btn-success-full ti-btn-wave flex items-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition"
                            target="_blank">
                            <i class="ti ti-download mr-2"></i>
                            <span class="sm:inline">Download Template</span><span class="sm:hidden">Template</span>
                        </a>
                        <a href="{{ route('admin.clients.index') }}"
                            class="ti-btn ti-btn-light ti-btn-wave flex items-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition">
                            <i class="ti ti-arrow-left mr-2"></i>
                            <span class="hidden sm:inline">Back to Leads</span><span class="sm:hidden">Back</span>
                        </a>
                    </div>
                </div>

                <!-- Processing Time Notice -->
                <div class="mt-4 text-center">
                    <div class="text-sm text-gray-500">
                        <i class="ti ti-clock mr-1"></i>
                        Large files may take several minutes to process
                    </div>
                </div>
            </div>

        </form>
    </div>
</div>
<!-- Instructions Card -->
<!-- <div class="box mb-6">
        <div class="box-header">
            <h5 class="box-title flex items-center">
                <i class="ti ti-info-circle mr-2"></i>&nbsp;
                Import Instructions
            </h5>
        </div>
        <div class="box-body">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <h6 class="font-semibold mb-4 text-red-800 flex items-center">
                        <i class="ti ti-asterisk text-red-500 mr-2 text-sm"></i>&nbsp;
                        Required Fields
                    </h6>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>Full Name</strong> - Complete name of the client</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>Phone Number</strong> - Primary contact number (used for duplicate detection)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>Country</strong> - Client's country</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>Number of Passengers</strong> - Positive integer</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>From Date & Place</strong> - Trip departure details</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>To Date & Place</strong> - Trip arrival details</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-red-500 mr-2">•</span>
                            <span><strong>Staff Representative</strong> - Must match existing staff member</span>
                        </li>
                    </ul>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h6 class="font-semibold mb-4 text-blue-800 flex items-center">
                        <i class="ti ti-info-circle text-blue-500 mr-2"></i>&nbsp;
                        Optional Fields
                    </h6>
                    <ul class="space-y-2 text-sm text-gray-700">
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Service</strong> - Service type (no validation)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Product</strong> - Product name (no validation)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Email Address</strong> - Must be unique in system</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>WhatsApp Number</strong> - Alternative contact number</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Date of Birth</strong> - Format: YYYY-MM-DD</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Address</strong> - Complete address of client</span>
                        </li>
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>City</strong> - Will be created if doesn't exist</span>
                        </li>
                        
                        <li class="flex items-start">
                            <span class="text-blue-500 mr-2">•</span>
                            <span><strong>Next Follow-up</strong> - Format: YYYY-MM-DD HH:MM</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded">
                <p class="text-sm text-yellow-800">
                    <i class="ti ti-alert-triangle mr-1"></i>
                    <strong>Duplicate Detection:</strong> Leads are identified as duplicates based on phone number and existing leads within the selected date range. Existing leads will be highlighted in red and unchecked by default.
                </p>
            </div>
        </div>
    </div> -->

<!-- Sample File Download Card -->
<!-- <div class="box mb-6">
        <div class="box-header">
            <h5 class="box-title flex items-center">
                <i class="ti ti-download mr-2"></i>&nbsp;
                Sample Excel File
            </h5>
        </div>
        <div class="box-body">
            <p class="text-gray-600 mb-4">Download the sample Excel file to understand the required format and see example data.</p>
            <a href="{{ route('admin.leads.import.sample') }}" 
               class="ti-btn ti-btn-success-full ti-btn-wave">
                <i class="ti ti-file-excel mr-2"></i>
                Download Sample Excel File
            </a>
        </div>
    </div> -->

<!-- Preview Section (Initially Hidden) -->
<div id="previewSection" class="box mb-6 hidden">
    <div class="box-header">
        <h5 class="box-title flex items-center">
            <i class="ti ti-eye mr-2"></i>
            Preview Imported Data
            <span class="ml-2 text-sm font-normal text-gray-500" id="dateRangeInfo"></span>
        </h5>
        <div class="flex flex-col sm:flex-row items-start sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
            <div class="text-sm text-gray-600 order-2 sm:order-1">
                Total: <span id="totalCount">0</span> |
                New: <span id="newCount">0</span> |
                Existing: <span id="existingCount">0</span> |
                Selected: <span id="selectedCount">0</span>
            </div>
            <div class="flex flex-wrap space-x-2 space-y-1 md:space-y-0 order-1 sm:order-2">
                <button type="button" id="selectAllNewBtn" class="ti-btn ti-btn-sm ti-btn-success-full">
                    <i class="ti ti-check mr-1"></i><span class="hidden sm:inline">Select All New</span><span
                        class="sm:hidden">New</span>
                </button>
                <button type="button" id="deselectAllBtn" class="ti-btn ti-btn-sm ti-btn-secondary-full">
                    <i class="ti ti-x mr-1"></i><span class="hidden sm:inline">Deselect All</span><span
                        class="sm:hidden">None</span>
                </button>
            </div>
        </div>
    </div>
    <div class="box-body">
        <!-- Mobile/Tablet Warning -->
        <div class="block md:hidden mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
            <p class="text-sm text-blue-800">
                <i class="ti ti-info-circle mr-1"></i>
                For better experience, scroll horizontally to view all columns or use landscape mode.
            </p>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden lg:block">
            <div class="overflow-x-auto overflow-y-auto overflow-y-height">
                <table class="table table-bordered w-full min-w-[1400px]" id="previewTable">
                    <thead>
                        <tr class="bg-gray-50">
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 sticky left-0 bg-gray-50 z-10 min-w-[100px]">
                                <input type="checkbox" id="masterCheckbox" class="form-check-input">
                                <br><small>Select</small>
                            </th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                Full Name</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[200px]">
                                Email Address</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[130px]">
                                Phone Number</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[130px]">
                                WhatsApp Number</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                Date of Birth</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                Address</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                Country</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                City</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                Service</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                Product</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[100px]">
                                No. of Passengers</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                From Date</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                From Place</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                To Date</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[120px]">
                                To Place</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                Next Follow-up</th>
                            <th
                                class="!p-3 !border-b !border-defaultborder dark:!border-defaultborder/10 min-w-[150px]">
                                Staff Representative</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody">
                        <!-- Preview data will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile/Tablet Card View -->
        <div class="block lg:hidden" id="previewCards">
            <!-- Cards will be populated here -->
        </div>

        <!-- Import Form -->
        <form id="finalImportForm" method="POST" action="{{ route('admin.leads.import.store') }}" class="mt-6">
            @csrf
            <input type="hidden" name="final_import" value="1">
            <input type="hidden" id="selectedLeadsData" name="selected_leads" value="">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <!-- Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="submit"
                        class="ti-btn ti-btn-success-full ti-btn-wave flex items-center justify-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition w-full sm:w-auto"
                        id="finalImportBtn">
                        <i class="ti ti-upload mr-2"></i>
                        <span class="hidden sm:inline">Import Selected Leads</span>
                        <span class="sm:hidden">Import</span>
                        (<span id="importCount">0</span>)
                    </button>

                    <button type="button" onclick="location.reload()"
                        class="ti-btn ti-btn-secondary-full ti-btn-wave flex items-center justify-center px-5 py-2 rounded-xl shadow-md hover:shadow-lg transition w-full sm:w-auto">
                        <i class="ti ti-refresh mr-2"></i>
                        Start Over
                    </button>
                </div>

                <!-- Info text -->
                <div class="text-sm text-gray-500 text-center sm:text-right">
                    <i class="ti ti-info-circle mr-1"></i>
                    Only checked leads will be imported
                </div>
            </div>

        </form>
    </div>
</div>

<!-- Loading Modal -->
<div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-sm mx-4">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
            <h4 class="text-lg font-semibold mb-2" id="loadingText">Processing Import</h4>
            <p class="text-gray-600" id="loadingSubtext">Please wait while we import your leads...</p>
            <p class="text-sm text-gray-500 mt-2">This may take a few minutes for large files.</p>
        </div>
    </div>
</div>

@include('admin.partials.modals.success-error-modals')

@stop
@push('styles')
<style>
    /* Responsive table improvements */
    .table-responsive {
        border-radius: 0.5rem;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
    }

    /* Desktop table scrolling */
    @media (min-width: 1024px) {
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }

        /* Sticky header */
        #previewTable thead th {
            position: sticky;
            top: 0;
            z-index: 5;
            background-color: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        /* Sticky first column */
        #previewTable tbody td:first-child {
            position: sticky;
            left: 0;
            z-index: 4;
            border-right: 2px solid #e5e7eb;
        }

        /* Hover effect for table rows */
        #previewTable tbody tr:hover {
            background-color: #f8fafc;
        }

        #previewTable tbody tr.existing-lead-row:hover {
            background-color: #fecaca !important;
        }
    }

    /* Tablet specific styles */
    @media (min-width: 768px) and (max-width: 1023px) {
        .table-responsive {
            overflow-x: auto;
        }

        #previewTable {
            min-width: 1200px;
        }

        #previewTable th,
        #previewTable td {
            white-space: nowrap;
            min-width: 120px;
        }

        #previewTable th:first-child,
        #previewTable td:first-child {
            min-width: 100px;
        }
    }

    /* Mobile card styling */
    @media (max-width: 1023px) {
        .form-control-sm {
            font-size: 0.875rem;
            padding: 0.375rem 0.5rem;
        }

        #previewCards .border {
            transition: all 0.2s ease;
        }

        #previewCards .border:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        /* Mobile form responsive improvements */
        .grid.grid-cols-1.sm\\:grid-cols-2 {
            gap: 0.75rem;
        }

        /* Better spacing for mobile */
        .space-y-4>*+* {
            margin-top: 1rem;
        }

        @media (max-width: 640px) {
            .space-y-4>*+* {
                margin-top: 0.75rem;
            }
        }
    }

    /* Existing lead highlighting */
    .existing-lead-row {
        background-color: #fee2e2 !important;
        border-left: 4px solid #dc2626 !important;
    }

    .existing-lead-row:hover {
        background-color: #fecaca !important;
    }

    /* Form controls in existing rows */
    .existing-lead-row input,
    .existing-lead-row select,
    .existing-lead-row textarea {
        background-color: #fef2f2;
        border-color: #fca5a5;
    }

    /* Existing lead cards */
    .bg-red-50 {
        border-left: 4px solid #dc2626 !important;
    }

    .bg-red-50:hover {
        background-color: #fecaca !important;
    }

    /* Loading states */
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    @keyframes pulse {

        0%,
        100% {
            opacity: 1;
        }

        50% {
            opacity: .5;
        }
    }

    /* Custom scrollbar for webkit browsers */
    .table-responsive::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #a8a8a8;
    }

    /* Mobile responsive improvements */
    @media (max-width: 767px) {
        .box-header h5 {
            font-size: 1rem;
        }

        .box-header .flex {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .ti-btn {
            font-size: 0.875rem;
            padding: 0.5rem 1rem;
        }

        /* Make form inputs more touch-friendly */
        .form-control-sm {
            min-height: 2.5rem;
            font-size: 1rem;
        }

        /* Improve card layout */
        #previewCards .grid {
            grid-template-columns: 1fr;
        }

        #previewCards .sm\\:grid-cols-2 {
            grid-template-columns: 1fr;
        }
    }

    /* Tablet improvements */
    @media (min-width: 768px) and (max-width: 1023px) {
        #previewCards .sm\\:grid-cols-2 {
            grid-template-columns: repeat(2, 1fr);
        }

        /* Horizontal scroll for table */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
    }

    /* Improve form field focus states */
    .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
    }

    /* Better button hover states */
    .ti-btn:hover {
        transform: translateY(-1px);
        transition: all 0.2s ease;
    }

    .ti-btn:active {
        transform: translateY(0);
    }

    /* Button responsive improvements */
    @media (max-width: 640px) {
        .ti-btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .ti-btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.8125rem;
        }
    }

    /* Form improvements */
    .form-control {
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }

    .form-control:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Alert improvements */
    .alert {
        border-radius: 0.5rem;
        animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Responsive utility classes */
    @media (max-width: 640px) {
        .text-sm {
            font-size: 0.8125rem;
        }

        .p-4 {
            padding: 0.75rem;
        }

        .space-x-4>*+* {
            margin-left: 0.75rem;
        }
    }
</style>
@endpush
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const fileInput = document.getElementById('excel_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const importForm = document.getElementById('importForm');
        const importBtn = document.getElementById('importBtn');
        const previewBtn = document.getElementById('previewBtn');
        const loadingModal = document.getElementById('loadingModal');
        const loadingText = document.getElementById('loadingText');
        const loadingSubtext = document.getElementById('loadingSubtext');
        const previewSection = document.getElementById('previewSection');
        const previewTableBody = document.getElementById('previewTableBody');
        const masterCheckbox = document.getElementById('masterCheckbox');
        const selectAllNewBtn = document.getElementById('selectAllNewBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const finalImportForm = document.getElementById('finalImportForm');

        let previewData = [];
        let products = [];
        let staff = [];

        // File input change handler
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('hidden');

                // Validate file type
                const allowedTypes = ['.xlsx', '.xls', '.csv'];
                const fileExtension = '.' + file.name.split('.').pop().toLowerCase();

                if (!allowedTypes.includes(fileExtension)) {
                    alert('Please select a valid Excel file (.xlsx, .xls, or .csv)');
                    fileInput.value = '';
                    fileInfo.classList.add('hidden');
                    return;
                }

                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    alert('File size cannot exceed 10MB');
                    fileInput.value = '';
                    fileInfo.classList.add('hidden');
                    return;
                }
            } else {
                fileInfo.classList.add('hidden');
            }
        });

        // Form submit handler (removed - now using button clicks)
        // importForm.addEventListener('submit', function(e) {
        //     e.preventDefault();
        //     
        //     if (!fileInput.files[0]) {
        //         alert('Please select an Excel file to import');
        //         return;
        //     }
        //     
        //     // Check which button was clicked
        //     const clickedButton = document.activeElement;
        //     const isDirectImport = clickedButton.id === 'importBtn';
        //     
        //     if (isDirectImport) {
        //         handleDirectImport();
        //     } else {
        //         handlePreview();
        //     }
        // });

        // Preview button click handler
        previewBtn.addEventListener('click', function() {
            if (!fileInput.files[0]) {
                alert('Please select an Excel file to import');
                return;
            }

            // Validate date range
            const fromDate = document.getElementById('filter_from_date').value;
            const toDate = document.getElementById('filter_to_date').value;

            if (fromDate && toDate && fromDate > toDate) {
                alert('The "Filter To Date" must be after or equal to the "Filter From Date"');
                return;
            }

            handlePreview();
        });

        // Direct import button click handler
        importBtn.addEventListener('click', function() {
            if (!fileInput.files[0]) {
                alert('Please select an Excel file to import');
                return;
            }
            handleDirectImport();
        });

        // Handle preview functionality
        async function handlePreview() {
           //console.log('Preview button clicked'); // Debug
            showLoadingModal('Generating Preview...', 'Analyzing your Excel file...');

            previewBtn.disabled = true;
            previewBtn.innerHTML = '<i class="ti ti-loader animate-spin mr-2"></i>Loading Preview...';
            importBtn.disabled = true;

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('preview', '1');
            formData.append('filter_from_date', document.getElementById('filter_from_date').value);
            formData.append('filter_to_date', document.getElementById('filter_to_date').value);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            // console.log('Preview request data:', {
            //     from_date: document.getElementById('filter_from_date').value,
            //     to_date: document.getElementById('filter_to_date').value
            // });

            try {
                const response = await fetch('{{ route("admin.leads.import.store") }}', {
                    method: 'POST',
                    body: formData
                });

                // console.log('Response status:', response.status);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Response error:', errorText);
                    throw new Error('Network response was not ok: ' + response.status);
                }

                const result = await response.json();
                // console.log('Preview result:', result);

                if (result.success) {
                    displayPreviewData(result.data);
                    hideLoadingModal();
                } else {
                    throw new Error(result.message || 'Preview failed');
                }
            } catch (error) {
                console.error('Preview error:', error);
                alert('Preview failed: ' + error.message);
                hideLoadingModal();
            } finally {
                previewBtn.disabled = false;
                previewBtn.innerHTML = '<i class="ti ti-eye mr-2"></i>Preview Data';
                importBtn.disabled = false;
            }
        }

        // Handle direct import functionality
        function handleDirectImport() {
            showConfirmationModal(
                'Confirm Direct Import',
                'Are you sure you want to import all leads directly without preview?',
                function() {
                    proceedWithDirectImport();
                }
            );
        }

        function proceedWithDirectImport() {
            showLoadingModal('Processing Import...', 'Importing your leads...');

            importBtn.disabled = true;
            importBtn.innerHTML = '<i class="ti ti-loader animate-spin mr-2"></i>Processing...';
            previewBtn.disabled = true;

            // Add filter dates to the form
            const filterFromInput = document.createElement('input');
            filterFromInput.type = 'hidden';
            filterFromInput.name = 'filter_from_date';
            filterFromInput.value = document.getElementById('filter_from_date').value;
            importForm.appendChild(filterFromInput);

            const filterToInput = document.createElement('input');
            filterToInput.type = 'hidden';
            filterToInput.name = 'filter_to_date';
            filterToInput.value = document.getElementById('filter_to_date').value;
            importForm.appendChild(filterToInput);

            // Submit the form normally for direct import
            importForm.submit();
        }

        // Display preview data in table
        function displayPreviewData(data) {
            previewData = data.leads || [];
            products = data.products || [];
            staff = data.staff || [];

            // Clear existing table data
            previewTableBody.innerHTML = '';
            const previewCards = document.getElementById('previewCards');
            if (previewCards) {
                previewCards.innerHTML = '';
            }

            let newCount = 0;
            let existingCount = 0;

            previewData.forEach((row, index) => {
                const isExisting = row.existing === true;
                if (isExisting) {
                    existingCount++;
                } else {
                    newCount++;
                }

                // Create desktop table row
                const tr = document.createElement('tr');
                tr.dataset.existing = isExisting;
                tr.dataset.index = index;

                if (isExisting) {
                    tr.style.backgroundColor = '#fee2e2';
                    tr.style.color = '#991b1b';
                    tr.classList.add('existing-lead-row');
                }

                tr.innerHTML = `
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10 sticky left-0 ${isExisting ? 'bg-red-50' : 'bg-white'} z-10">
                    <input type="checkbox" class="lead-checkbox form-check-input" 
                           data-index="${index}" ${!isExisting ? 'checked' : ''}>
                    ${isExisting ? '<small class="text-red-600 block font-semibold">Existing Lead</small>' : '<small class="text-green-600 block font-semibold">New Lead</small>'}
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][full_name]" value="${row.full_name || ''}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="email" name="leads[${index}][email_address]" value="${row.email_address || ''}" 
                           class="form-control form-control-sm w-full">
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][phone_number]" value="${row.phone_number || ''}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][whatsapp_number]" value="${row.whatsapp_number || ''}" 
                           class="form-control form-control-sm w-full">
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="date" name="leads[${index}][date_of_birth]" value="${row.date_of_birth || ''}" 
                           class="form-control form-control-sm w-full">
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <textarea name="leads[${index}][address]" class="form-control form-control-sm w-full" rows="2">${row.address || ''}</textarea>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][country]" value="${row.country || ''}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][city]" value="${row.city || ''}" 
                           class="form-control form-control-sm w-full">
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10" style="min-width:220px;">
                    <select name="leads[${index}][service]" class="js-example-basic-single w-full form-control-sm service-select" style="min-width:200px;">
                        <option value="">Select Service</option>
                        ${(data.services || []).map(s => `<option value="${s.service}" data-id="${s.id}" ${(row.service || '') === s.service ? 'selected' : ''}>${s.service}</option>`).join('')}
                    </select>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10" style="min-width:220px;">
                    <select name="leads[${index}][product]" class="js-example-basic-single w-full form-control-sm product-select" data-index="${index}" style="min-width:200px;">
                        <option value="">Select Product</option>
                        ${products.map(product => 
                            `<option value="${product.id}" data-name="${product.product}" ${(row.product_id && row.product_id == product.id) || (row.product && row.product === product.product) ? 'selected' : ''}>
                                ${product.product}
                            </option>`
                        ).join('')}
                    </select>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="number" name="leads[${index}][number_of_passengers]" value="${row.number_of_passengers || 1}" 
                           min="1" class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="datetime-local" name="leads[${index}][from_date]" value="${formatDateTimeForInput(row.from_date)}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][from_place]" value="${row.from_place || ''}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="datetime-local" name="leads[${index}][to_date]" value="${formatDateTimeForInput(row.to_date)}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="text" name="leads[${index}][to_place]" value="${row.to_place || ''}" 
                           class="form-control form-control-sm w-full" required>
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <input type="datetime-local" name="leads[${index}][next_follow_up]" value="${formatDateTimeForInput(row.next_follow_up)}" 
                           class="form-control form-control-sm w-full">
                </td>
                <td class="!p-2 !border-b !border-defaultborder dark:!border-defaultborder/10">
                    <select name="leads[${index}][staff_representative]" class="form-control form-control-sm w-full" required>
                        <option value="">Select Staff</option>
                        ${staff.map(staffMember => 
                            `<option value="${staffMember.name}" ${(row.staff_representative || '') === staffMember.name ? 'selected' : ''}>
                                ${staffMember.name}
                            </option>`
                        ).join('')}
                    </select>
                </td>
            `;

                previewTableBody.appendChild(tr);

                // Create mobile card view
                if (previewCards) {
                    const card = document.createElement('div');
                    card.className = `border rounded-lg p-4 mb-4 ${isExisting ? 'bg-red-50 border-red-200' : 'bg-white border-gray-200'}`;
                    card.dataset.existing = isExisting;
                    card.dataset.index = index;

                    card.innerHTML = `
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <input type="checkbox" class="lead-checkbox form-check-input mr-3" 
                                   data-index="${index}" ${!isExisting ? 'checked' : ''}>
                            <div>
                                <h6 class="font-semibold ${isExisting ? 'text-red-800' : 'text-gray-800'}">${row.full_name || 'No Name'}</h6>
                                ${isExisting ? '<span class="text-red-600 text-sm font-semibold">Existing Lead - Found in date range</span>' : '<span class="text-green-600 text-sm font-semibold">New Lead</span>'}
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="text-sm text-gray-500">Lead #${index + 1}</span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Full Name *</label>
                            <input type="text" name="leads[${index}][full_name]" value="${row.full_name || ''}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Email Address</label>
                            <input type="email" name="leads[${index}][email_address]" value="${row.email_address || ''}" 
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Phone Number *</label>
                            <input type="text" name="leads[${index}][phone_number]" value="${row.phone_number || ''}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">WhatsApp Number</label>
                            <input type="text" name="leads[${index}][whatsapp_number]" value="${row.whatsapp_number || ''}" 
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Date of Birth</label>
                            <input type="date" name="leads[${index}][date_of_birth]" value="${row.date_of_birth || ''}" 
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Country *</label>
                            <input type="text" name="leads[${index}][country]" value="${row.country || ''}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">City</label>
                            <input type="text" name="leads[${index}][city]" value="${row.city || ''}" 
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Service</label>
                            <select name="leads[${index}][service]" class="js-example-basic-single w-full form-control-sm service-select" style="min-width:180px;">
                                <option value="">Select Service</option>
                                ${(data.services || []).map(s => `<option value="${s.service}" data-id="${s.id}" ${(row.service || '') === s.service ? 'selected' : ''}>${s.service}</option>`).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Product</label>
                            <select name="leads[${index}][product]" class="js-example-basic-single w-full form-control-sm product-select" data-index="${index}" style="min-width:180px;">
                                <option value="">Select Product</option>
                                ${products.map(product => 
                                    `<option value="${product.id}" data-name="${product.product}" ${(row.product_id && row.product_id == product.id) || (row.product && row.product === product.product) ? 'selected' : ''}>
                                        ${product.product}
                                    </option>`
                                ).join('')}
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">No. of Passengers *</label>
                            <input type="number" name="leads[${index}][number_of_passengers]" value="${row.number_of_passengers || 1}" 
                                   min="1" class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">From Date *</label>
                            <input type="datetime-local" name="leads[${index}][from_date]" value="${formatDateTimeForInput(row.from_date)}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">From Place *</label>
                            <input type="text" name="leads[${index}][from_place]" value="${row.from_place || ''}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">To Date *</label>
                            <input type="datetime-local" name="leads[${index}][to_date]" value="${formatDateTimeForInput(row.to_date)}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">To Place *</label>
                            <input type="text" name="leads[${index}][to_place]" value="${row.to_place || ''}" 
                                   class="form-control form-control-sm" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Next Follow-up</label>
                            <input type="datetime-local" name="leads[${index}][next_follow_up]" value="${formatDateTimeForInput(row.next_follow_up)}" 
                                   class="form-control form-control-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Staff Representative *</label>
                            <select name="leads[${index}][staff_representative]" class="form-control form-control-sm" required>
                                <option value="">Select Staff</option>
                                ${staff.map(staffMember => 
                                    `<option value="${staffMember.name}" ${(row.staff_representative || '') === staffMember.name ? 'selected' : ''}>
                                        ${staffMember.name}
                                    </option>`
                                ).join('')}
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Address</label>
                            <textarea name="leads[${index}][address]" class="form-control form-control-sm" rows="2">${row.address || ''}</textarea>
                        </div>
                    </div>
                    
                    ${isExisting ? `<div class="mt-3 p-2 bg-red-100 border border-red-200 rounded text-xs text-red-700">
                        <i class="ti ti-alert-triangle mr-1"></i>
                        This lead already exists in the selected date range and will be skipped during import.
                        ${row.matched_leads && row.matched_leads.length ? `<div class="text-xs text-gray-600 mt-1">Matched DB leads: ${row.matched_leads.map(m=>m.id).join(', ')}</div>` : ''}
                    </div>` : ''}
                `;

                    previewCards.appendChild(card);
                }
            });

            // Add a summary card at the top for mobile view
            if (previewCards && previewData.length > 0) {
                const summaryCard = document.createElement('div');
                summaryCard.className = 'bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4';
                summaryCard.innerHTML = `
                <div class="flex items-center justify-between">
                    <h6 class="font-semibold text-blue-800">Import Summary</h6>
                    <div class="flex space-x-2">
                        <button type="button" class="ti-btn ti-btn-xs ti-btn-success-full" onclick="document.getElementById('selectAllNewBtn').click()">
                            <i class="ti ti-check text-xs"></i>
                        </button>
                        <button type="button" class="ti-btn ti-btn-xs ti-btn-secondary-full" onclick="document.getElementById('deselectAllBtn').click()">
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-2 mt-2 text-sm">
                    <div class="text-center">
                        <div class="font-semibold text-gray-800">${previewData.length}</div>
                        <div class="text-gray-600">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-green-600">${newCount}</div>
                        <div class="text-gray-600">New</div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-red-600">${existingCount}</div>
                        <div class="text-gray-600">Existing</div>
                    </div>
                    <div class="text-center">
                        <div class="font-semibold text-blue-600" id="mobileSelectedCount">0</div>
                        <div class="text-gray-600">Selected</div>
                    </div>
                </div>
            `;
                previewCards.insertBefore(summaryCard, previewCards.firstChild);
            }

            // Update counts
            document.getElementById('totalCount').textContent = previewData.length;
            document.getElementById('newCount').textContent = newCount;
            document.getElementById('existingCount').textContent = existingCount;

            // Update date range info
            const fromDate = document.getElementById('filter_from_date').value;
            const toDate = document.getElementById('filter_to_date').value;
            const dateRangeInfo = document.getElementById('dateRangeInfo');
            if (dateRangeInfo && (fromDate || toDate)) {
                dateRangeInfo.textContent = `(Checking duplicates: ${fromDate || 'start'} to ${toDate || 'end'})`;
            }

            // Show preview section
            previewSection.classList.remove('hidden');

            // Initialize event listeners
            initializePreviewEventListeners();
            updateCounts();
            addResponsiveFeatures();
            // Wire up product -> service auto-selection
            try {
                const productServiceMap = data.product_service_map || {};
                document.querySelectorAll('.product-select').forEach(ps => {
                    const idx = ps.dataset.index;
                    ps.addEventListener('change', function() {
                        const selectedProductId = ps.value;
                        const serviceIds = productServiceMap[selectedProductId] || [];
                        // pick first service id if any
                        if (serviceIds.length > 0) {
                            // find service-select in same row (by data-index or DOM)
                            let serviceSelect = null;
                            // Try by name attribute
                            serviceSelect = document.querySelector(`select[name='leads[${idx}][service]']`);
                            if (serviceSelect) {
                                // find option with matching data-id
                                const targetId = serviceIds[0];
                                const opt = serviceSelect.querySelector(`option[data-id='${targetId}']`);
                                if (opt) {
                                    serviceSelect.value = opt.value;
                                }
                            }
                        }
                    });
                });
            } catch (e) {
                // ignore
            }

            // Scroll to preview section
            previewSection.scrollIntoView({
                behavior: 'smooth'
            });
        }

        // Initialize event listeners for preview functionality
        function initializePreviewEventListeners() {
            const leadCheckboxes = document.querySelectorAll('.lead-checkbox');

            // Master checkbox functionality
            if (masterCheckbox) {
                masterCheckbox.addEventListener('change', function() {
                    // Sync all checkboxes (desktop + mobile) to master state
                    document.querySelectorAll('.lead-checkbox').forEach(checkbox => {
                        checkbox.checked = masterCheckbox.checked;
                    });
                    updateCounts();
                });
            }

            // Individual checkbox change - sync sibling checkboxes (desktop + mobile share same data-index)
            leadCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Sync all checkboxes with the same data-index (desktop table + mobile card)
                    const idx = this.dataset.index;
                    document.querySelectorAll(`.lead-checkbox[data-index="${idx}"]`).forEach(cb => {
                        cb.checked = this.checked;
                    });

                    updateCounts();

                    // Update master checkbox state based on unique indices
                    const uniqueTotal = new Set(Array.from(leadCheckboxes).map(cb => cb.dataset.index)).size;
                    const uniqueChecked = new Set(
                        Array.from(document.querySelectorAll('.lead-checkbox:checked')).map(cb => cb.dataset.index)
                    ).size;

                    if (masterCheckbox) {
                        masterCheckbox.indeterminate = uniqueChecked > 0 && uniqueChecked < uniqueTotal;
                        masterCheckbox.checked = uniqueChecked === uniqueTotal;
                    }
                });
            });

            // Select all new leads
            if (selectAllNewBtn) {
                selectAllNewBtn.addEventListener('click', function() {
                    // Select checkboxes in both desktop table and mobile cards
                    document.querySelectorAll('tr[data-existing="false"] .lead-checkbox, div[data-existing="false"] .lead-checkbox').forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    updateCounts();
                });
            }

            // Deselect all
            if (deselectAllBtn) {
                deselectAllBtn.addEventListener('click', function() {
                    leadCheckboxes.forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    updateCounts();
                });
            }

            // Final import form handler
            if (finalImportForm) {
                finalImportForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
                    if (checkedBoxes.length === 0) {
                        showErrorModal('No Selection', 'Please select at least one lead to import');
                        return;
                    }

                    // Compute unique selected indices to avoid double-counting (desktop + mobile views)
                    const uniqueIndices = new Set();
                    checkedBoxes.forEach(cb => {
                        const idx = cb.dataset.index;
                        if (typeof idx !== 'undefined') uniqueIndices.add(idx);
                    });

                    const uniqueCount = uniqueIndices.size;

                    showConfirmationModal(
                        'Confirm Import',
                        `Are you sure you want to import ${uniqueCount} selected lead${uniqueCount === 1 ? '' : 's'}?`,
                        function() {
                            proceedWithSelectedImport(Array.from(uniqueIndices));
                        }
                    );
                });
            }

            function proceedWithSelectedImport(selectedIndices) {
                // selectedIndices: array of unique preview indices (strings)
                const selectedLeads = [];

                // Deduplicate and collect current form values for each unique index
                selectedIndices.forEach(index => {
                    const row = previewData[index];
                    if (row) {
                        // Get current form values for this row
                        const rowData = {
                            ...row,
                            full_name: (document.querySelector(`input[name="leads[${index}][full_name]"]`) || {}).value || '',
                            email_address: (document.querySelector(`input[name="leads[${index}][email_address]"]`) || {}).value || '',
                            phone_number: (document.querySelector(`input[name="leads[${index}][phone_number]"]`) || {}).value || '',
                            whatsapp_number: (document.querySelector(`input[name="leads[${index}][whatsapp_number]"]`) || {}).value || '',
                            date_of_birth: (document.querySelector(`input[name="leads[${index}][date_of_birth]"]`) || {}).value || '',
                            address: (document.querySelector(`textarea[name="leads[${index}][address]"]`) || {}).value || '',
                            country: (document.querySelector(`input[name="leads[${index}][country]"]`) || {}).value || '',
                            city: (document.querySelector(`input[name="leads[${index}][city]"]`) || {}).value || '',
                            service: (function() {
                                const s = document.querySelector(`select[name="leads[${index}][service]"]`);
                                return s ? s.value : '';
                            })(),
                            product: (function() {
                                const p = document.querySelector(`select[name="leads[${index}][product]"]`);
                                return p ? p.options[p.selectedIndex].dataset.name || p.value : '';
                            })(),
                            number_of_passengers: (document.querySelector(`input[name="leads[${index}][number_of_passengers]"]`) || {}).value || '',
                            from_date: (document.querySelector(`input[name="leads[${index}][from_date]"]`) || {}).value || '',
                            from_place: (document.querySelector(`input[name="leads[${index}][from_place]"]`) || {}).value || '',
                            to_date: (document.querySelector(`input[name="leads[${index}][to_date]"]`) || {}).value || '',
                            to_place: (document.querySelector(`input[name="leads[${index}][to_place]"]`) || {}).value || '',
                            next_follow_up: (document.querySelector(`input[name="leads[${index}][next_follow_up]"]`) || {}).value || '',
                            staff_representative: (document.querySelector(`select[name="leads[${index}][staff_representative]"]`) || {}).value || ''
                        };
                        selectedLeads.push(rowData);
                    }
                });

                document.getElementById('selectedLeadsData').value = JSON.stringify(selectedLeads);

                // Submit the form
                showLoadingModal('Importing Leads...', 'Processing your selected leads...');
                finalImportForm.submit();
            }

        }

        // Update counts in the UI (moved to enhanced version above)
        // function updateCounts() {
        //     const checkedCount = document.querySelectorAll('.lead-checkbox:checked').length;
        //     document.getElementById('selectedCount').textContent = checkedCount;
        //     document.getElementById('importCount').textContent = checkedCount;
        // }

        // Show loading modal
        function showLoadingModal(title, subtitle) {
            loadingText.textContent = title;
            loadingSubtext.textContent = subtitle;
            loadingModal.classList.remove('hidden');
            loadingModal.classList.add('flex');
        }

        // Hide loading modal
        function hideLoadingModal() {
            loadingModal.classList.add('hidden');
            loadingModal.classList.remove('flex');
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Format datetime for input field (produce a local YYYY-MM-DDTHH:MM string)
        function formatDateTimeForInput(datetime) {
            if (!datetime) return '';
            try {
                // Normalize common server formats to a parsable form
                let s = String(datetime);
                // Replace space between date and time with 'T' if present
                s = s.replace(' ', 'T');

                const date = new Date(s);
                if (isNaN(date.getTime())) return '';

                const pad = (n) => n.toString().padStart(2, '0');
                const yyyy = date.getFullYear();
                const mm = pad(date.getMonth() + 1);
                const dd = pad(date.getDate());
                const hh = pad(date.getHours());
                const min = pad(date.getMinutes());

                return `${yyyy}-${mm}-${dd}T${hh}:${min}`;
            } catch (e) {
                return '';
            }
        }

        // Add responsive view toggle functionality
        function addResponsiveFeatures() {
            // Add view toggle button for tablets
            const previewHeader = document.querySelector('#previewSection .box-header');
            if (previewHeader && window.innerWidth >= 768 && window.innerWidth < 1024) {
                const viewToggle = document.createElement('button');
                viewToggle.type = 'button';
                viewToggle.className = 'ti-btn ti-btn-sm ti-btn-primary-full ml-4';
                viewToggle.innerHTML = '<i class="ti ti-layout-cards mr-1"></i>Card View';
                viewToggle.id = 'viewToggle';

                viewToggle.addEventListener('click', function() {
                    const desktopView = document.querySelector('.hidden.lg\\:block');
                    const mobileView = document.querySelector('.block.lg\\:hidden');

                    if (desktopView.classList.contains('hidden')) {
                        // Switch to table view
                        desktopView.classList.remove('hidden');
                        mobileView.classList.add('hidden');
                        viewToggle.innerHTML = '<i class="ti ti-layout-cards mr-1"></i>Card View';
                    } else {
                        // Switch to card view
                        desktopView.classList.add('hidden');
                        mobileView.classList.remove('hidden');
                        viewToggle.innerHTML = '<i class="ti ti-table mr-1"></i>Table View';
                    }
                });

                previewHeader.querySelector('.flex.items-center.space-x-4').appendChild(viewToggle);
            }
        }

        // Enhanced update counts function
        function updateCounts() {
            // Count unique preview indices of checked checkboxes to avoid double-counting desktop+mobile
            const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
            const uniqueIndices = new Set();
            checkedBoxes.forEach(cb => {
                const idx = cb.dataset.index;
                if (typeof idx !== 'undefined') uniqueIndices.add(idx);
            });
            const checkedCount = uniqueIndices.size;
            document.getElementById('selectedCount').textContent = checkedCount;
            document.getElementById('importCount').textContent = checkedCount;

            // Update mobile summary if it exists
            const mobileSelectedCount = document.getElementById('mobileSelectedCount');
            if (mobileSelectedCount) {
                mobileSelectedCount.textContent = checkedCount;
            }

            // Update final import button state
            const finalImportBtn = document.getElementById('finalImportBtn');
            if (finalImportBtn) {
                if (checkedCount === 0) {
                    finalImportBtn.disabled = true;
                    finalImportBtn.classList.add('opacity-50');
                } else {
                    finalImportBtn.disabled = false;
                    finalImportBtn.classList.remove('opacity-50');
                }
            }
        }

        // Auto-hide success/error messages after 10 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (alert.classList.contains('alert-success') || alert.classList.contains('alert-warning')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }
            });
        }, 10000);
    });
</script>
@endpush