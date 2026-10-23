@extends('admin.layouts.header')
@section('content')
@php
    // Result of the last import run (flashed by ClientController@importLeads / handleFinalImport).
    $importSummary = session('import_summary');
    $importErrors = session('import_errors');
    $skippedReasons = session('skipped_reasons');
    $hasImportResult = session('success') || session('error') || $importSummary
        || ($importErrors && count($importErrors) > 0) || ($skippedReasons && count($skippedReasons) > 0);
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/shared/kimi-tokens.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/leads/import-leads.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/leads/import-leads-mobile.css') }}?v=1">

<div class="crm-kimi il-page">
    <header class="il-heading">
        <div>
            <h1>Import Leads</h1>
            <p>Bulk-import leads from Excel / CSV — upload, validate against existing CRM records, import the clean rows</p>
        </div>
        <div class="il-heading-actions">
            <a href="{{ route('admin.leads.import.sample') }}" class="k-ghost" target="_blank">
                <i class="ri-download-2-line" aria-hidden="true"></i> Download Excel template
            </a>
            <a href="{{ route('admin.clients.index') }}" class="k-ghost">
                <i class="ri-arrow-left-line" aria-hidden="true"></i> Back to Leads
            </a>
        </div>
    </header>

    {{-- Step indicator: updated by the script when a preview is shown; step 3 is active after an import run. --}}
    <nav class="il-steps" aria-label="Import steps">
        <span class="il-step {{ $hasImportResult ? 'done' : 'on' }}" data-step="1"><b>{{ $hasImportResult ? '✓' : '1' }}</b>Upload file</span>
        <span class="il-step {{ $hasImportResult ? 'done' : '' }}" data-step="2"><b>{{ $hasImportResult ? '✓' : '2' }}</b>Preview &amp; validate</span>
        <span class="il-step {{ $hasImportResult ? 'on' : '' }}" data-step="3"><b>3</b>Import result</span>
    </nav>

    @if ($hasImportResult)
        <section class="k-section il-result" aria-labelledby="il-result-title" id="importResult">
            <div class="k-section-head">
                <h2 id="il-result-title">Import result</h2>
                <span class="k-tag neutral">last run · {{ now()->format('d M Y, H:i') }}</span>
            </div>
            @if (session('success'))
                <div class="k-section-meta">{{ session('success') }}</div>
            @endif

            @if ($importSummary)
                <div class="il-logline">
                    <span class="k-tag green">✓ Imported</span>
                    <b>{{ $importSummary['imported'] ?? 0 }} {{ \Illuminate\Support\Str::plural('lead', $importSummary['imported'] ?? 0) }}</b>
                    <span class="k-muted">out of {{ $importSummary['total'] ?? 0 }} processed</span>
                </div>
                @if (($importSummary['skipped'] ?? 0) > 0)
                    <div class="il-logline">
                        <span class="k-tag amber">↷ Skipped</span>
                        <b>{{ $importSummary['skipped'] }} {{ \Illuminate\Support\Str::plural('row', $importSummary['skipped']) }}</b>
                        <span class="k-muted">
                            @if (isset($importSummary['skipped_duplicates']))
                                {{ $importSummary['skipped_duplicates'] }} duplicates
                            @endif
                            @if (isset($importSummary['skipped_errors']))
                                · {{ $importSummary['skipped_errors'] }} errors
                            @endif
                        </span>
                    </div>
                @endif
            @endif

            @if (session('error'))
                <div class="il-logline is-error">
                    <span class="k-tag red">✕ Failed</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if ($importErrors && count($importErrors) > 0)
                <div class="il-logline is-list">
                    <div class="il-logline-head"><span class="k-tag red">✕ Errors</span><b>{{ count($importErrors) }} {{ \Illuminate\Support\Str::plural('row', count($importErrors)) }}</b></div>
                    <ul class="il-loglist">
                        @foreach ($importErrors as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($skippedReasons && count($skippedReasons) > 0)
                <div class="il-logline is-list">
                    <div class="il-logline-head"><span class="k-tag amber">↷ Skipped details</span><b>{{ count($skippedReasons) }}</b></div>
                    <ul class="il-loglist">
                        @foreach ($skippedReasons as $reason)
                            <li>{{ $reason }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <!-- Upload -->
    <section class="k-section" aria-labelledby="il-upload-title">
        <div class="k-section-head"><h2 id="il-upload-title">1 · Upload file</h2></div>
        <form action="{{ route('admin.leads.import.store') }}" method="POST" enctype="multipart/form-data" id="importForm">
            @csrf
            <label class="il-dz @error('excel_file') is-invalid @enderror" for="excel_file" id="dropZone">
                <span class="il-dz-icon" aria-hidden="true"><i class="ri-upload-cloud-2-line"></i></span>
                <span class="il-dz-title"><b>Drop your Excel / CSV here</b> or <span class="il-link">browse files</span></span>
                <span class="il-dz-cols">Columns: Full Name · Email · Phone · WhatsApp · DOB · Address · Country · City · Service · Product · No. of Passengers · From Date/Place · To Date/Place · Next Follow-up · Staff Representative</span>
                <span class="il-dz-note">Supported formats: .xlsx, .xls, .csv (max 10MB)</span>
                <input type="file" name="excel_file" id="excel_file" accept=".xlsx,.xls,.csv" class="il-file-input" required>
            </label>
            @error('excel_file')
                <div class="il-field-error">{{ $message }}</div>
            @enderror

            <!-- File Info Display -->
            <div id="fileInfo" class="hidden il-fileinfo">
                <i class="ri-file-excel-2-line" aria-hidden="true"></i>
                <span id="fileName" class="il-fileinfo-name"></span>
                <span id="fileSize" class="k-muted"></span>
            </div>

            <div class="k-section-meta il-dupe-note">Duplicate check matches the <b>phone number</b> against clients with leads created in this date range — matching rows are shown as <b>Existing</b> and left unchecked.</div>

            <div class="k-filt il-upload-grid">
                <div class="k-fg">
                    <label for="filter_from_date">Existing-lead scan — from</label>
                    <input type="date" name="filter_from_date" id="filter_from_date" value="{{ date('Y-m-01') }}">
                </div>
                <div class="k-fg">
                    <label for="filter_to_date">Existing-lead scan — to</label>
                    <input type="date" name="filter_to_date" id="filter_to_date" value="{{ date('Y-m-d') }}">
                </div>
                <div class="il-upload-actions">
                    <button type="button" class="k-primary" id="previewBtn">
                        <i class="ri-eye-line" aria-hidden="true"></i> Check &amp; preview
                    </button>
                    <button type="button" class="k-ghost" id="importBtn" title="Import every row without preview">
                        <i class="ri-upload-2-line" aria-hidden="true"></i> Direct import
                    </button>
                </div>
            </div>
            <div class="il-hint"><i class="ri-time-line" aria-hidden="true"></i> Large files may take several minutes to process</div>
        </form>
    </section>

    <!-- Preview Section (Initially Hidden) -->
    <section id="previewSection" class="k-section hidden" aria-labelledby="il-preview-title">
        <div class="k-section-head">
            <h2 id="il-preview-title">2 · Preview &amp; validate</h2>
            <span class="il-counts">
                <span class="k-tag neutral">Total <span id="totalCount">0</span></span>
                <span class="k-tag green">New <span id="newCount">0</span></span>
                <span class="k-tag amber">Existing <span id="existingCount">0</span></span>
                <span class="k-tag blue">Selected <span id="selectedCount">0</span></span>
            </span>
        </div>
        <div class="k-section-meta">
            Every row is checked before import. <b>Only checked (selected) rows are imported</b> — new rows are selected, existing rows are left unchecked. Only the <b>phone number</b> is compulsory. Change values directly in the row; <b>More</b> opens the other fields (email, service, travel dates, follow-up, address…).
            <span id="dateRangeInfo"></span>
        </div>
        <div class="il-preview-tools">
            <button type="button" id="selectAllNewBtn" class="k-ghost k-sm"><i class="ri-checkbox-multiple-line" aria-hidden="true"></i> Select all new</button>
            <button type="button" id="deselectAllBtn" class="k-ghost k-sm"><i class="ri-checkbox-blank-line" aria-hidden="true"></i> Deselect all</button>
        </div>

        <div class="k-tblwrap il-tblwrap">
            <table class="il-table no-horizontal-scroll" id="previewTable">
                <thead>
                    <tr>
                        <th class="il-col-check"><input type="checkbox" id="masterCheckbox" class="form-check-input" aria-label="Select all rows"></th>
                        <th>Full name</th>
                        <th>Phone</th>
                        <th>Passengers</th>
                        <th>Product</th>
                        <th>Staff rep</th>
                        <th>Check result</th>
                        <th><span class="sr-only">More</span></th>
                    </tr>
                </thead>
                <tbody id="previewTableBody">
                    <!-- Preview data will be populated here -->
                </tbody>
            </table>
        </div>

        <!-- Import Form -->
        <form id="finalImportForm" method="POST" action="{{ route('admin.leads.import.store') }}" class="il-final">
            @csrf
            <input type="hidden" name="final_import" value="1">
            <input type="hidden" id="selectedLeadsData" name="selected_leads" value="">

            <button type="submit" class="k-primary" id="finalImportBtn">
                <i class="ri-upload-2-line" aria-hidden="true"></i>
                Import <span id="importCount">0</span> selected leads
            </button>
            <button type="button" onclick="location.reload()" class="k-ghost">
                <i class="ri-refresh-line" aria-hidden="true"></i> Start over
            </button>
            <span class="k-muted il-final-note" id="finalNote">Only checked leads will be imported</span>
        </form>
    </section>
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
        const dropZone = document.getElementById('dropZone');

        let previewData = [];
        let products = [];
        let staff = [];

        const esc = (value) => String(value ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));

        function setStep(active) {
            document.querySelectorAll('.il-step').forEach(step => {
                const n = Number(step.dataset.step);
                step.classList.toggle('on', n === active);
                step.classList.toggle('done', n < active);
                step.querySelector('b').textContent = n < active ? '✓' : n;
            });
        }

        function showSelectedFile(file) {
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
        }

        // File input change handler
        fileInput.addEventListener('change', function(e) {
            showSelectedFile(e.target.files[0]);
        });

        // Drag & drop onto the upload box puts the file in the same file input.
        ['dragenter', 'dragover'].forEach(type => dropZone.addEventListener(type, function(e) {
            e.preventDefault();
            dropZone.classList.add('is-drag');
        }));
        ['dragleave', 'drop'].forEach(type => dropZone.addEventListener(type, function(e) {
            e.preventDefault();
            dropZone.classList.remove('is-drag');
        }));
        dropZone.addEventListener('drop', function(e) {
            const file = e.dataTransfer && e.dataTransfer.files[0];
            if (!file) return;
            try {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                fileInput.files = transfer.files;
            } catch (err) {
                alert('Please use "browse files" to select the file');
                return;
            }
            showSelectedFile(file);
        });

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
            showLoadingModal('Generating Preview...', 'Analyzing your Excel file...');

            const previewLabel = previewBtn.innerHTML;
            previewBtn.disabled = true;
            previewBtn.innerHTML = '<i class="ri-loader-4-line animate-spin" aria-hidden="true"></i> Loading preview...';
            importBtn.disabled = true;

            const formData = new FormData();
            formData.append('excel_file', fileInput.files[0]);
            formData.append('preview', '1');
            formData.append('filter_from_date', document.getElementById('filter_from_date').value);
            formData.append('filter_to_date', document.getElementById('filter_to_date').value);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            try {
                const response = await fetch('{{ route("admin.leads.import.store") }}', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Response error:', errorText);
                    throw new Error('Network response was not ok: ' + response.status);
                }

                const result = await response.json();

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
                previewBtn.innerHTML = previewLabel;
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
            importBtn.innerHTML = '<i class="ri-loader-4-line animate-spin" aria-hidden="true"></i> Processing...';
            previewBtn.disabled = true;

            // The date inputs are already inside #importForm, so they are submitted with the file.
            importForm.submit();
        }

        // ---- Preview rendering -------------------------------------------------------------
        // Every field exists once: the main columns are edited in the row, the rest under "Edit".

        const fieldValue = (index, name, tag = 'input') => {
            const el = document.querySelector(`${tag}[name="leads[${index}][${name}]"]`);
            return el ? el.value : '';
        };

        // Current values of one row (the same named fields the import has always read).
        function currentRow(index) {
            const product = document.querySelector(`select[name="leads[${index}][product]"]`);
            return {
                full_name: fieldValue(index, 'full_name'),
                email_address: fieldValue(index, 'email_address'),
                phone_number: fieldValue(index, 'phone_number'),
                whatsapp_number: fieldValue(index, 'whatsapp_number'),
                date_of_birth: fieldValue(index, 'date_of_birth'),
                address: fieldValue(index, 'address', 'textarea'),
                country: fieldValue(index, 'country'),
                city: fieldValue(index, 'city'),
                service: fieldValue(index, 'service', 'select'),
                product: product && product.value ? (product.options[product.selectedIndex].dataset.name || product.value) : '',
                number_of_passengers: fieldValue(index, 'number_of_passengers'),
                from_date: fieldValue(index, 'from_date'),
                from_place: fieldValue(index, 'from_place'),
                to_date: fieldValue(index, 'to_date'),
                to_place: fieldValue(index, 'to_place'),
                next_follow_up: fieldValue(index, 'next_follow_up'),
                staff_representative: fieldValue(index, 'staff_representative', 'select')
            };
        }

        const serviceOptions = (row, data) => (data.services || []).map(s =>
            `<option value="${esc(s.service)}" data-id="${esc(s.id)}" ${(row.service || '') === s.service ? 'selected' : ''}>${esc(s.service)}</option>`).join('');
        const productOptions = (row) => products.map(product =>
            `<option value="${esc(product.id)}" data-name="${esc(product.product)}" ${(row.product_id && row.product_id == product.id) || (row.product && row.product === product.product) ? 'selected' : ''}>${esc(product.product)}</option>`).join('');
        const staffOptions = (row) => staff.map(staffMember =>
            `<option value="${esc(staffMember.name)}" ${(row.staff_representative || '') === staffMember.name ? 'selected' : ''}>${esc(staffMember.name)}</option>`).join('');

        // Main row: one value per column, editable in place. Only the phone number is compulsory.
        function rowCellsHtml(row, index, data, isExisting) {
            return `
                <td data-label="Select" class="il-col-check"><input type="checkbox" class="lead-checkbox form-check-input" data-index="${index}" ${!isExisting ? 'checked' : ''} aria-label="Select row ${index + 1}"></td>
                <td data-label="Full name"><input type="text" class="il-in" name="leads[${index}][full_name]" value="${esc(row.full_name)}" placeholder="Full name" aria-label="Full name"></td>
                <td data-label="Phone"><input type="text" class="il-in" name="leads[${index}][phone_number]" value="${esc(row.phone_number)}" placeholder="Phone *" aria-label="Phone number (required)" required></td>
                <td data-label="Passengers"><input type="number" class="il-in il-in-pax" name="leads[${index}][number_of_passengers]" value="${esc(row.number_of_passengers || 1)}" min="1" aria-label="No. of passengers"></td>
                <td data-label="Product"><select name="leads[${index}][product]" class="il-in js-example-basic-single product-select" data-index="${index}" aria-label="Product">
                    <option value="">Select Product</option>${productOptions(row)}
                </select></td>
                <td data-label="Staff rep"><select name="leads[${index}][staff_representative]" class="il-in" aria-label="Staff representative">
                    <option value="">Select Staff</option>${staffOptions(row)}
                </select></td>
                <td data-label="Check result">${isExisting ? '<span class="k-tag amber">Old</span>' : '<span class="k-tag green">New</span>'}</td>
                <td data-label="More" class="il-col-edit"><button type="button" class="k-rowbtn il-edit-toggle" data-index="${index}" aria-expanded="false" title="Email, service, travel dates, follow-up and other details">More <i class="ri-arrow-down-s-line" aria-hidden="true"></i></button></td>`;
        }

        // "More": all other fields of the same lead.
        function editFieldsHtml(row, index, data) {
            return `
                <div class="il-edit-title">More details for this lead</div>
                <div class="il-editgrid">
                    <div class="k-fg"><label>Email Address</label><input type="email" name="leads[${index}][email_address]" value="${esc(row.email_address)}"></div>
                    <div class="k-fg"><label>WhatsApp Number</label><input type="text" name="leads[${index}][whatsapp_number]" value="${esc(row.whatsapp_number)}"></div>
                    <div class="k-fg"><label>Date of Birth</label><input type="date" name="leads[${index}][date_of_birth]" value="${esc(row.date_of_birth)}"></div>
                    <div class="k-fg"><label>Service</label>
                        <select name="leads[${index}][service]" class="js-example-basic-single service-select">
                            <option value="">Select Service</option>${serviceOptions(row, data)}
                        </select>
                    </div>
                    <div class="k-fg"><label>From Date</label><input type="datetime-local" name="leads[${index}][from_date]" value="${formatDateTimeForInput(row.from_date)}"></div>
                    <div class="k-fg"><label>From Place</label><input type="text" name="leads[${index}][from_place]" value="${esc(row.from_place)}"></div>
                    <div class="k-fg"><label>To Date</label><input type="datetime-local" name="leads[${index}][to_date]" value="${formatDateTimeForInput(row.to_date)}"></div>
                    <div class="k-fg"><label>To Place</label><input type="text" name="leads[${index}][to_place]" value="${esc(row.to_place)}"></div>
                    <div class="k-fg"><label>Next Follow-up</label><input type="datetime-local" name="leads[${index}][next_follow_up]" value="${formatDateTimeForInput(row.next_follow_up)}"></div>
                    <div class="k-fg"><label>Country</label><input type="text" name="leads[${index}][country]" value="${esc(row.country)}"></div>
                    <div class="k-fg"><label>City</label><input type="text" name="leads[${index}][city]" value="${esc(row.city)}"></div>
                    <div class="k-fg il-span-2"><label>Address</label><textarea name="leads[${index}][address]" rows="2">${esc(row.address)}</textarea></div>
                </div>
                <div class="il-editfoot"><button type="button" class="k-ghost k-sm il-edit-done" data-index="${index}">Done</button></div>`;
        }

        // Point to "Edit" when a required field inside it is empty.
        function markEditNeeded(index) {
            const tr = previewTableBody.querySelector(`tr.il-row[data-index="${index}"]`);
            const editTr = previewTableBody.querySelector(`tr[data-edit-index="${index}"]`);
            if (!tr || !editTr) return;
            const needs = editTr.querySelectorAll('input:invalid, textarea:invalid').length > 0;
            tr.classList.toggle('needs-more', needs);
            tr.querySelector('.il-edit-toggle').title = needs
                ? 'Some details are empty - click More to fill them'
                : 'Email, service, travel dates, follow-up and other details';
        }

        // Display preview data in table
        function displayPreviewData(data) {
            previewData = data.leads || [];
            products = data.products || [];
            staff = data.staff || [];

            // Clear existing table data
            previewTableBody.innerHTML = '';

            let newCount = 0;
            let existingCount = 0;

            previewData.forEach((row, index) => {
                const isExisting = row.existing === true;
                if (isExisting) {
                    existingCount++;
                } else {
                    newCount++;
                }

                // New rows are checked, existing rows unchecked - as before.
                const tr = document.createElement('tr');
                tr.className = 'il-row' + (isExisting ? ' existing-lead-row' : '');
                tr.dataset.existing = isExisting;
                tr.dataset.index = index;
                tr.innerHTML = rowCellsHtml(row, index, data, isExisting);
                previewTableBody.appendChild(tr);

                const editTr = document.createElement('tr');
                editTr.className = 'il-edit-row';
                editTr.dataset.editIndex = index;
                editTr.hidden = true;
                editTr.innerHTML = `<td colspan="8"><div class="il-edit-inner">${editFieldsHtml(row, index, data)}</div></td>`;
                previewTableBody.appendChild(editTr);

                markEditNeeded(index);
            });

            // Update counts
            document.getElementById('totalCount').textContent = previewData.length;
            document.getElementById('newCount').textContent = newCount;
            document.getElementById('existingCount').textContent = existingCount;

            if (previewData.length === 0) {
                previewTableBody.innerHTML = '<tr><td colspan="8" class="il-empty">No rows found in this file.</td></tr>';
            }

            // Update date range info
            const fromDate = document.getElementById('filter_from_date').value;
            const toDate = document.getElementById('filter_to_date').value;
            const dateRangeInfo = document.getElementById('dateRangeInfo');
            if (dateRangeInfo && (fromDate || toDate)) {
                dateRangeInfo.textContent = `(Checking duplicates: ${fromDate || 'start'} to ${toDate || 'end'})`;
            }

            // Show preview section
            previewSection.classList.remove('hidden');
            setStep(2);

            // Initialize event listeners
            initializePreviewEventListeners();
            updateCounts();
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
                            const serviceSelect = document.querySelector(`select[name='leads[${idx}][service]']`);
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

        // Keep the Edit panel as wide as the visible table area (the table may scroll sideways).
        const tableWrap = document.querySelector('.il-tblwrap');
        if (tableWrap && window.ResizeObserver) {
            new ResizeObserver(() => tableWrap.style.setProperty('--il-view', tableWrap.clientWidth + 'px')).observe(tableWrap);
        }

        // Edit toggle (opens the rest of the row's fields).
        previewTableBody.addEventListener('click', function(e) {
            const toggle = e.target.closest('.il-edit-toggle, .il-edit-done');
            if (!toggle) return;
            const idx = toggle.dataset.index;
            const editTr = previewTableBody.querySelector(`tr[data-edit-index="${idx}"]`);
            const btn = previewTableBody.querySelector(`.il-edit-toggle[data-index="${idx}"]`);
            const open = editTr.hidden;
            editTr.hidden = !open;
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
            btn.closest('tr').classList.toggle('is-editing', open);
            if (!open) btn.focus();
        });
        ['input', 'change'].forEach(type => previewTableBody.addEventListener(type, function(e) {
            const editTr = e.target.closest('tr.il-edit-row');
            if (editTr) markEditNeeded(editTr.dataset.editIndex);
        }));

        // Initialize event listeners for preview functionality
        function initializePreviewEventListeners() {
            const leadCheckboxes = document.querySelectorAll('.lead-checkbox');

            // Master checkbox functionality
            if (masterCheckbox) {
                masterCheckbox.onchange = function() {
                    document.querySelectorAll('.lead-checkbox').forEach(checkbox => {
                        checkbox.checked = masterCheckbox.checked;
                    });
                    updateCounts();
                };
            }

            // Individual checkbox change
            leadCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateCounts();

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
                selectAllNewBtn.onclick = function() {
                    document.querySelectorAll('tr[data-existing="false"] .lead-checkbox').forEach(checkbox => {
                        checkbox.checked = true;
                    });
                    updateCounts();
                };
            }

            // Deselect all
            if (deselectAllBtn) {
                deselectAllBtn.onclick = function() {
                    document.querySelectorAll('.lead-checkbox').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    updateCounts();
                };
            }
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

            selectedIndices.forEach(index => {
                const row = previewData[index];
                if (row) {
                    // Original row + current (possibly edited) form values - same payload as before.
                    selectedLeads.push({
                        ...row,
                        ...currentRow(index)
                    });
                }
            });

            document.getElementById('selectedLeadsData').value = JSON.stringify(selectedLeads);

            // Submit the form
            showLoadingModal('Importing Leads...', 'Processing your selected leads...');
            finalImportForm.submit();
        }

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

        // Selected count, button state and the per-row "will import / skipped" pill.
        function updateCounts() {
            const checkedBoxes = document.querySelectorAll('.lead-checkbox:checked');
            const uniqueIndices = new Set();
            checkedBoxes.forEach(cb => {
                const idx = cb.dataset.index;
                if (typeof idx !== 'undefined') uniqueIndices.add(idx);
            });
            const checkedCount = uniqueIndices.size;
            document.getElementById('selectedCount').textContent = checkedCount;
            document.getElementById('importCount').textContent = checkedCount;


            const skipped = previewTableBody.querySelectorAll('tr.il-row').length - checkedCount;
            document.getElementById('finalNote').textContent =
                `Only checked leads will be imported${skipped > 0 ? ' · ' + skipped + ' not selected' : ''}`;

            const finalImportBtn = document.getElementById('finalImportBtn');
            if (finalImportBtn) {
                finalImportBtn.disabled = checkedCount === 0;
                finalImportBtn.classList.toggle('opacity-50', checkedCount === 0);
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
