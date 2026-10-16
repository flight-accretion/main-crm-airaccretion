(function () {
    'use strict';
    const page = document.getElementById('attendanceImportPage');
    if (!page) return;
    const byId = id => document.getElementById(id);
    const form = byId('attendancePreviewForm');
    const confirmForm = byId('attendanceConfirmForm');
    const confirmButton = byId('attendanceConfirmButton');
    const previewButton = byId('attendancePreviewButton');
    const error = byId('attendanceAjaxError');
    let payload = null;
    let rowPage = 0;
    let version = 0;
    let pending = false;
    const pageSize = 50;
    const displayStatus = value => String(value || '').toUpperCase() === 'MIS' ? 'Miss punch' : (value || '-');
    function showError(message) { error.textContent = message; error.hidden = !message; }
    function cell(row, label, text) {
        const td = document.createElement('td');
        td.dataset.label = label;
        td.textContent = text == null || text === '' ? '-' : String(text);
        row.appendChild(td);
        return td;
    }
    function mappingsValid() {
        const selects = [...page.querySelectorAll('.attendance-user-map')];
        const counts = new Map();
        selects.forEach(select => { if (select.value) counts.set(select.value, (counts.get(select.value) || 0) + 1); });
        let needs = 0;
        selects.forEach(select => {
            const valid = Boolean(select.value) && counts.get(select.value) === 1;
            select.setAttribute('aria-invalid', String(!valid));
            if (!valid) needs++;
        });
        byId('previewMatchedCount').textContent = selects.length - needs;
        byId('previewUnmatchedCount').textContent = needs;
        const valid = Boolean(payload) && selects.length === payload.employees.length && selects.length > 0 && needs === 0;
        confirmButton.disabled = !valid || pending;
        byId('attendanceMappingState').textContent = valid ? 'Ready to import' : 'Needs mapping';
        byId('attendanceMappingState').classList.toggle('ai-green', valid);
        byId('attendanceConfirmHint').textContent = valid ? '' : 'Select a different CRM employee for every employee in this file.';
        return valid;
    }
    function renderRows() {
        const body = byId('attendanceRowsBody');
        body.replaceChildren();
        const rows = payload ? payload.rows : [];
        const start = rowPage * pageSize;
        rows.slice(start, start + pageSize).forEach(record => {
            const row = document.createElement('tr');
            cell(row, 'Paycode', record.paycode);
            cell(row, 'Employee', record.file_name);
            cell(row, 'Date', record.attendance_date);
            cell(row, 'Day', record.day_name);
            cell(row, 'In', record.raw_in);
            cell(row, 'Out', record.raw_out);
            cell(row, 'Status', displayStatus(record.raw_status));
            body.appendChild(row);
        });
        byId('attendanceRowsPage').textContent = `${rows.length ? start + 1 : 0}-${Math.min(start + pageSize, rows.length)} of ${rows.length}`;
        byId('attendanceRowsPrevious').disabled = rowPage === 0;
        byId('attendanceRowsNext').disabled = start + pageSize >= rows.length;
    }
    function renderPreview(result) {
        payload = result;
        rowPage = 0;
        byId('attendanceImportId').value = result.import_id;
        byId('attendancePreviewFilename').textContent = result.filename || byId('excel_file').files[0].name;
        byId('attendancePreviewPeriod').textContent = `${result.from_date} - ${result.to_date}`;
        byId('previewEmployeeCount').textContent = result.total_employees;
        byId('previewRecordCount').textContent = result.total_records;
        const warning = byId('futureRowsWarning');
        warning.hidden = !result.outside_range_rows;
        warning.textContent = `${result.outside_range_rows} rows outside the selected date range excluded.`;
        const body = byId('attendanceMappingBody');
        body.replaceChildren();
        result.employees.forEach(employee => {
            const row = document.createElement('tr');
            cell(row, 'Paycode', employee.paycode);
            cell(row, 'Employee in file', employee.file_name);
            const td = cell(row, 'CRM employee', '');
            td.textContent = '';
            const select = document.createElement('select');
            select.className = 'attendance-user-map';
            select.dataset.mappingKey = employee.mapping_key;
            select.setAttribute('aria-label', `CRM employee for ${employee.file_name}`);
            select.appendChild(new Option('Select employee', ''));
            result.users.forEach(user => select.appendChild(new Option(`${user.name}${user.role ? ' (' + user.role + ')' : ''}`, user.id)));
            select.value = employee.user_id || '';
            select.addEventListener('change', mappingsValid);
            td.appendChild(select);
            cell(row, 'Period', `${employee.period_from} - ${employee.period_to}`);
            cell(row, 'Rows', employee.record_count);
            const match = cell(row, 'Match', employee.match_type === 'saved_paycode' ? 'Saved paycode' : employee.match_type === 'exact_name' ? 'Exact name' : 'Select employee');
            select.addEventListener('change', () => { match.textContent = select.value ? 'Selected' : 'Select employee'; });
            body.appendChild(row);
        });
        renderRows();
        mappingsValid();
        byId('attendancePreviewSection').hidden = false;
    }
    function invalidate() {
        version++;
        payload = null;
        byId('attendanceImportId').value = '';
        byId('attendancePreviewSection').hidden = true;
        confirmButton.disabled = true;
    }
    form.addEventListener('input', invalidate);
    form.addEventListener('change', invalidate);
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (pending || !form.reportValidity()) return;
        if (byId('to_date').value < byId('from_date').value) { showError('To date cannot be before From date.'); return; }
        invalidate();
        const requestVersion = version;
        showError('');
        pending = true;
        previewButton.disabled = true;
        previewButton.textContent = 'Reading attendance...';
        try {
            const response = await fetch(page.dataset.previewUrl, {method: 'POST', body: new FormData(form), credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Attendance preview failed.');
            if (requestVersion === version) renderPreview(result);
        } catch (e) { if (requestVersion === version) showError(e.message || 'Attendance preview failed.'); }
        finally {
            pending = false;
            previewButton.disabled = false;
            previewButton.textContent = 'Preview Data';
            if (payload) mappingsValid();
        }
    });
    byId('attendanceRowsPrevious').addEventListener('click', () => { if (rowPage > 0) { rowPage--; renderRows(); } });
    byId('attendanceRowsNext').addEventListener('click', () => { if (payload && (rowPage + 1) * pageSize < payload.rows.length) { rowPage++; renderRows(); } });
    confirmForm.addEventListener('submit', event => {
        if (pending || !mappingsValid()) { event.preventDefault(); showError('Select a unique CRM employee for every employee before confirming.'); return; }
        const hidden = byId('attendanceMappingHidden');
        hidden.replaceChildren();
        page.querySelectorAll('.attendance-user-map').forEach(select => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `mappings[${select.dataset.mappingKey}]`;
            input.value = select.value;
            hidden.appendChild(input);
        });
        pending = true;
        confirmButton.disabled = true;
    });
    window.addEventListener('pageshow', () => { pending = false; if (payload) mappingsValid(); });

    const history = byId('attendance-imports-table');
    const historyBody = history.tBodies[0];
    if (!historyBody.querySelector('td[colspan]')) {
        [...history.tHead.rows[0].cells].slice(0, -1).forEach((heading, index) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'ai-sort';
            button.textContent = heading.textContent;
            button.title = `Sort by ${heading.textContent}`;
            const icon = document.createElement('i');
            icon.className = 'ti ti-arrows-sort';
            icon.setAttribute('aria-hidden', 'true');
            button.appendChild(icon);
            heading.replaceChildren(button);
            button.addEventListener('click', () => {
                const direction = heading.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
                [...history.tHead.rows[0].cells].forEach(th => th.removeAttribute('aria-sort'));
                heading.setAttribute('aria-sort', direction === 1 ? 'ascending' : 'descending');
                [...historyBody.rows].sort((a, b) => direction * a.cells[index].textContent.trim().localeCompare(b.cells[index].textContent.trim(), undefined, {numeric: true})).forEach(row => historyBody.appendChild(row));
            });
        });
    }

    // Reuse only links already rendered by the permission-aware sidebar.
    const links = [...document.querySelectorAll('#sidebar a.side-menu__item')].filter(link => {
        const href = link.getAttribute('href') || '';
        return href && !href.startsWith('javascript:') && !href.startsWith('#') && new URL(link.href).origin === location.origin;
    });
    const nav = byId('attendanceMobileNav');
    const entries = [
        ['Dashboard', 'ti ti-layout-dashboard', /dashboard/i],
        ['Leads', 'ti ti-users', /view\s+leads/i],
        ['Follow-up', 'ti ti-calendar-event', /today.*follow|follow.*today/i],
        ['KPI', 'ti ti-chart-bar', /^(sales kpi|kpi dashboard)$/i]
    ];
    entries.forEach(([label, icon, pattern]) => {
        const source = links.find(link => pattern.test(link.textContent.trim().replace(/\s+/g, ' ')));
        const item = document.createElement(source ? 'a' : 'button');
        if (source) { item.href = source.href; if (source.pathname === location.pathname) item.setAttribute('aria-current', 'page'); }
        else { item.type = 'button'; item.disabled = true; item.title = `${label} unavailable for your role`; }
        const symbol = document.createElement('i'); symbol.className = icon; symbol.setAttribute('aria-hidden', 'true');
        item.append(symbol, document.createTextNode(label));
        nav.appendChild(item);
    });
    const more = document.createElement('button');
    more.type = 'button';
    more.innerHTML = '<i class="ti ti-dots" aria-hidden="true"></i>More';
    more.setAttribute('aria-haspopup', 'dialog');
    const dialog = byId('attendanceMoreDialog');
    more.addEventListener('click', () => dialog.showModal());
    nav.appendChild(more);
    byId('attendanceMoreClose').addEventListener('click', () => dialog.close());
    const seen = new Set();
    links.forEach(source => {
        if (seen.has(source.href)) return;
        seen.add(source.href);
        const link = document.createElement('a'); link.href = source.href; link.textContent = source.textContent.trim().replace(/\s+/g, ' ');
        byId('attendanceMoreLinks').appendChild(link);
    });
}());
