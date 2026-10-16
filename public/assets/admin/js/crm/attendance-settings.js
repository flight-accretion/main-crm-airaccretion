(function () {
    'use strict';

    function init() {
        const root = document.querySelector('[data-attendance-settings]');
        if (!root) return;
        document.body.classList.add('crm-attendance-settings-page');
        const newPolicy = root.querySelector('#as-new-policy');
        root.querySelector('a[href="#as-new-policy"]').addEventListener('click', function () { newPolicy.open = true; });
        const assignments = root.querySelector('[data-assignments-form]');
        const rows = Array.from(assignments.querySelectorAll('[data-attendance-user-option]'));
        const search = assignments.querySelector('[data-attendance-user-search]');
        const selectVisible = assignments.querySelector('[data-select-visible]');
        function updateSelection() {
            const visible = rows.filter(row => !row.hidden);
            const selected = rows.filter(row => row.querySelector('[name="user_ids[]"]').checked);
            root.querySelector('[data-selection-count]').textContent = selected.length + ' selected';
            const checkedVisible = visible.filter(row => row.querySelector('[name="user_ids[]"]').checked).length;
            selectVisible.checked = visible.length > 0 && checkedVisible === visible.length;
            selectVisible.indeterminate = checkedVisible > 0 && checkedVisible < visible.length;
            selectVisible.disabled = visible.length === 0;
            assignments.querySelector('[data-search-empty]').hidden = rows.length === 0 || visible.length > 0;
        }
        search.addEventListener('input', function () {
            const value = search.value.trim().toLowerCase();
            rows.forEach(row => { row.hidden = !row.dataset.attendanceUserName.includes(value); });
            updateSelection();
        });
        selectVisible.addEventListener('change', function () {
            rows.filter(row => !row.hidden).forEach(row => { row.querySelector('[name="user_ids[]"]').checked = selectVisible.checked; });
            updateSelection();
        });
        assignments.addEventListener('change', updateSelection);
        assignments.addEventListener('submit', function (event) {
            if (!rows.some(row => row.querySelector('[name="user_ids[]"]').checked)) {
                event.preventDefault();
                const first = rows.find(row => !row.hidden);
                if (first) {
                    const checkbox = first.querySelector('[name="user_ids[]"]');
                    checkbox.setCustomValidity('Select at least one employee.');
                    checkbox.reportValidity();
                    checkbox.setCustomValidity('');
                }
            }
        });
        updateSelection();
        const weekoffs = root.querySelector('[data-weekoffs-form]');
        root.querySelectorAll('[data-edit-schedule]').forEach(button => {
            button.addEventListener('click', function () {
                weekoffs.elements.user_id.value = button.dataset.user;
                weekoffs.elements.effective_from.value = button.dataset.effective;
                const weekdays = JSON.parse(button.dataset.weekdays);
                weekoffs.querySelectorAll('[name="weekdays[]"]').forEach(input => { input.checked = weekdays.map(String).includes(input.value); });
                weekoffs.scrollIntoView({ block: 'center' });
                weekoffs.elements.effective_from.focus({ preventScroll: true });
            });
        });
        const holidays = root.querySelector('[data-holidays-form]');
        if (holidays) {
            const dates = holidays.querySelector('[data-holiday-dates]');
            function updateDates() {
                const inputs = Array.from(dates.querySelectorAll('input'));
                const values = inputs.map(input => input.value).filter(Boolean);
                inputs.forEach(input => input.setCustomValidity(input.value && values.filter(value => value === input.value).length > 1 ? 'This date is already selected.' : ''));
                holidays.querySelector('[data-date-count]').textContent = new Set(values).size + ' selected dates';
            }
            holidays.querySelector('[data-add-date]').addEventListener('click', function () {
                dates.appendChild(holidays.querySelector('[data-holiday-template]').content.cloneNode(true));
                dates.lastElementChild.querySelector('input').focus();
                updateDates();
            });
            dates.addEventListener('click', function (event) {
                const remove = event.target.closest('[data-remove-date]');
                if (remove) {
                    remove.closest('.as-date-row').remove();
                    updateDates();
                    holidays.querySelector('[data-add-date]').focus();
                }
            });
            dates.addEventListener('input', updateDates);
            updateDates();
        }
        root.querySelectorAll('[data-confirm-swap]').forEach(form => {
            form.addEventListener('submit', function (event) {
                if (!window.confirm('Undo this approved off-day swap?')) event.preventDefault();
            });
        });
        // Reuse only links rendered by the sidebar's existing role checks.
        const sidebarLinks = Array.from(document.querySelectorAll('#sidebar a.side-menu__item[href]')).filter(link => {
            const href = link.getAttribute('href');
            return href && !href.startsWith('javascript:') && !href.startsWith('#');
        });
        const nav = root.querySelector('[data-mobile-navigation]');
        const dialog = root.querySelector('[data-more-dialog]');
        const used = new Set();
        const items = [
            ['Dashboard', /^(HR Dashboard|Sales Dashboard|Operations Dashboard)$/i, 'bx-home-alt'],
            ['Leads', /^View\s+Leads$/i, 'bx-trending-up'],
            ['Follow-up', /^Today.s Follow-up$/i, 'bx-task'],
            ['KPI', /^(Sales KPI|KPI Dashboard)$/i, 'bx-bar-chart-alt-2']
        ];
        function addIcon(element, icon, label) {
            const glyph = document.createElement('i');
            glyph.className = 'bx ' + icon;
            glyph.setAttribute('aria-hidden', 'true');
            element.append(glyph, document.createTextNode(label));
        }
        items.forEach(([label, pattern, icon]) => {
            const matches = sidebarLinks.filter(link => pattern.test(link.textContent.replace(/\s+/g, ' ').trim()));
            const source = label === 'Dashboard' ? matches.find(link => /HR Dashboard/i.test(link.textContent)) || matches[0] : matches[0];
            if (!source) return;
            const link = document.createElement('a');
            link.href = source.href;
            addIcon(link, icon, label);
            nav.appendChild(link);
            used.add(source.href);
        });
        const moreLinks = root.querySelector('[data-more-links]');
        const seen = new Set(used);
        sidebarLinks.forEach(source => {
            if (seen.has(source.href)) return;
            seen.add(source.href);
            const link = document.createElement('a');
            link.href = source.href;
            link.textContent = source.textContent.replace(/\s+/g, ' ').trim();
            if (source.href === window.location.href) link.setAttribute('aria-current', 'page');
            moreLinks.appendChild(link);
        });
        if (moreLinks.children.length) {
            const more = document.createElement('button');
            more.type = 'button';
            more.setAttribute('aria-haspopup', 'dialog');
            addIcon(more, 'bx-grid-alt', 'More');
            more.addEventListener('click', () => dialog.showModal());
            root.querySelector('[data-close-more]').addEventListener('click', () => dialog.close());
            nav.appendChild(more);
        }
        nav.hidden = nav.children.length === 0;
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
