(function () {
    'use strict';
    function init() {
        const root = document.querySelector('[data-kpi-tool]');
        if (!root) return;
        document.body.classList.add('crm-kpi-tools-page');
        root.querySelectorAll('a[href^="#km-"]').forEach(link => link.addEventListener('click', () => {
            const target = document.getElementById(link.getAttribute('href').slice(1));
            if (!target) return;
            let parent = target;
            while (parent && parent !== root) { if (parent.tagName === 'DETAILS') parent.open = true; parent = parent.parentElement; }
        }));
        root.querySelectorAll('[data-open-template]').forEach(link => link.addEventListener('click', () => {
            const editor = document.getElementById('km-template-' + link.dataset.openTemplate);
            if (editor) editor.open = true;
        }));
        root.querySelectorAll('[data-assign-department]').forEach(link => link.addEventListener('click', () => {
            const select = root.querySelector('form[action$="/assignments"] [name="template_id"]');
            if (!select) return;
            const department = link.dataset.assignDepartment;
            const option = Array.from(select.options).find(item => item.dataset.department === department);
            if (option) select.value = option.value;
            select.focus();
        }));
        function labelTables(scope) {
            scope.querySelectorAll('table').forEach(table => {
                const headers = Array.from(table.querySelectorAll('thead th')).map(cell => cell.textContent.trim());
                table.querySelectorAll('tbody tr').forEach(row => Array.from(row.cells).forEach((cell, index) => {
                    if (!cell.hasAttribute('data-label') && headers[index]) cell.dataset.label = headers[index];
                }));
            });
        }
        labelTables(root);
        const detail = root.querySelector('#work-done-details-body');
        if (detail) new MutationObserver(() => labelTables(detail)).observe(detail, {childList: true, subtree: true});
        const links = Array.from(document.querySelectorAll('#sidebar a.side-menu__item[href]')).filter(link => {
            const value = link.getAttribute('href');
            return value && !value.startsWith('#') && !value.startsWith('javascript:') && new URL(link.href).origin === location.origin;
        });
        const nav = root.querySelector('[data-tool-nav]');
        const used = new Set();
        [['Dashboard', /^(HR Dashboard|Dashboard|Sales Dashboard|Operations Dashboard)$/i, 'ri-home-line'],
            ['Leads', /^(View Leads|All Leads|Leads)$/i, 'ri-user-line'],
            ['Follow-up', /^Today.s Follow-up$/i, 'ri-calendar-check-line'],
            ['KPI', /^(Sales KPI|KPI Dashboard)$/i, 'ri-bar-chart-line']].forEach(([label, pattern, icon]) => {
            const matches = links.filter(link => pattern.test(link.textContent.trim().replace(/\s+/g, ' ')));
            const source = matches.find(link => /HR Dashboard/.test(link.textContent)) || matches[0];
            const item = document.createElement(source ? 'a' : 'button');
            if (source) { item.href = source.href; used.add(source.href); }
            else { item.type = 'button'; item.disabled = true; item.title = label + ' unavailable for this role'; }
            const glyph = document.createElement('i'); glyph.className = icon; glyph.setAttribute('aria-hidden', 'true');
            item.append(glyph, document.createTextNode(label)); nav.appendChild(item);
        });
        const dialog = root.querySelector('[data-tool-more]');
        const more = document.createElement('button'); more.type = 'button'; more.textContent = 'More'; more.setAttribute('aria-haspopup', 'dialog');
        more.addEventListener('click', () => dialog.showModal()); nav.appendChild(more);
        root.querySelector('[data-close-tool-more]').addEventListener('click', () => dialog.close());
        links.forEach(source => {
            if (used.has(source.href)) return;
            used.add(source.href); const link = document.createElement('a'); link.href = source.href;
            link.textContent = source.textContent.trim().replace(/\s+/g, ' '); root.querySelector('[data-tool-links]').appendChild(link);
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
