/* Phones (<=767px): a data table that is wider than the screen is shown as labelled cards
   (one card per row, "Column: value") so the data never scrolls sideways.
   - Only tables that actually overflow are changed; nothing happens on tablets / desktop.
   - Tables that already have their own phone layout, tables without a header row, calendars,
     DataTables "responsive" tables and anything inside .no-mobile-cards are left alone.
   - Only CSS classes and data-label attributes are added: rows, inputs, buttons and their
     click handlers stay exactly as they are. */
(function () {
    'use strict';
    const mobile = window.matchMedia('(max-width:767px)');
    const PAGE_NAV_TABLES = '.fc, .no-mobile-cards, .dataTables_scrollHead, .dataTables_scrollFoot';
    let queued = false;

    function headerCells(table) {
        // DataTables scrollX keeps the visible header in a separate table.
        const scrollBody = table.closest('.dataTables_scrollBody');
        const source = scrollBody
            ? scrollBody.parentElement.querySelector('.dataTables_scrollHead table')
            : table;
        const head = source && source.tHead;
        if (!head || !head.rows.length) return null;
        if (head.rows.length > 1) return null; // grouped headers: too complex to label safely
        const cells = Array.from(head.rows[0].cells);
        if (cells.some(cell => cell.colSpan > 1)) return null;
        return cells.map(cell => cell.textContent.replace(/\s+/g, ' ').trim());
    }

    function scrollParent(table) {
        return table.closest('.dataTables_scrollBody, .table-responsive, .overflow-x-auto, .overflow-auto, .dataTables_wrapper') || table.parentElement;
    }

    function overflows(table) {
        const box = scrollParent(table);
        const width = table.getBoundingClientRect().width;
        if (!width) return false; // hidden (inactive tab, closed modal) - checked again later
        return width > box.clientWidth + 1 || table.getBoundingClientRect().right > window.innerWidth + 1;
    }

    function hasOwnPhoneLayout(table) {
        // Ignore hidden cells (e.g. a hidden S.No column); look at the first shown one.
        const cells = table.tBodies[0] ? Array.from(table.tBodies[0].querySelectorAll('td')) : [];
        const cell = cells.find(td => getComputedStyle(td).display !== 'none');
        return !!cell && getComputedStyle(cell).display !== 'table-cell';
    }

    function label(table, labels) {
        const hiddenCols = (table.dataset.crmHiddenCols || '').split(',').filter(Boolean).map(Number);
        Array.from(table.tBodies).forEach(body => {
            Array.from(body.rows).forEach(row => {
                let col = 0;
                Array.from(row.cells).forEach(cell => {
                    if (!cell.hasAttribute('data-label') || cell.dataset.crmAutoLabel === '1') {
                        cell.setAttribute('data-label', cell.colSpan > 1 ? '' : (labels[col] || ''));
                        cell.dataset.crmAutoLabel = '1';
                    }
                    // Columns the page hides stay hidden in the card view.
                    cell.classList.toggle('crm-m-hidden', hiddenCols.includes(col));
                    col += cell.colSpan || 1;
                });
            });
        });
    }

    function convert(table) {
        if (table.closest(PAGE_NAV_TABLES)) return;
        if (table.classList.contains('dtr-inline') || table.classList.contains('dtr-column')) return;
        const labels = headerCells(table);
        if (!labels || labels.length < 2) return;
        if (!table.classList.contains('crm-m-cards')) {
            if (hasOwnPhoneLayout(table) || !overflows(table)) return;
            const firstRow = table.tBodies[0] && table.tBodies[0].rows[0];
            if (firstRow) {
                let col = 0; const hidden = [];
                Array.from(firstRow.cells).forEach(cell => { if (getComputedStyle(cell).display === 'none') hidden.push(col); col += cell.colSpan || 1; });
                table.dataset.crmHiddenCols = hidden.join(',');
            }
            table.classList.add('crm-m-cards');
            const wrap = table.closest('.dataTables_wrapper');
            if (wrap) wrap.classList.add('crm-m-cards-wrap');
            const box = scrollParent(table);
            if (box && box !== table.parentElement) box.classList.add('crm-m-cards-box');
            table.parentElement.classList.add('crm-m-cards-box');
        }
        label(table, labels); // also re-labels rows added later (paging, search, AJAX)
    }

    function run() {
        queued = false;
        if (!mobile.matches) return;
        document.querySelectorAll('.main-content table, .ti-modal table, .ti-offcanvas table').forEach(table => {
            try { convert(table); } catch (e) { /* never break the page */ }
        });
    }

    function schedule() {
        if (queued || !mobile.matches) return;
        queued = true;
        setTimeout(run, 120);
    }

    function init() {
        run();
        // DataTables drawing, AJAX-filled tables, modals and tabs.
        new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
        document.addEventListener('click', () => setTimeout(schedule, 60), true);
        window.addEventListener('load', schedule);
        window.addEventListener('resize', schedule);
        mobile.addEventListener('change', schedule);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
