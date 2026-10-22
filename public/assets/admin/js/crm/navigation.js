(function () {
    'use strict';
    const sidebar = document.querySelector('.app-sidebar');
    const menu = sidebar?.querySelector('.main-menu');
    if (!menu || menu.dataset.crmOrganized) return;
    menu.dataset.crmOrganized = 'true';
    const pathOf = anchor => {
        try { const url = new URL(anchor.getAttribute('href'), location.href); return /^https?:$/.test(url.protocol) ? url.pathname.replace(/\/$/, '') : null; }
        catch (_) { return null; }
    };
    const labelOf = anchor => (anchor.querySelector('.side-menu__label') || anchor).textContent.replace(/\s+/g, ' ').trim();
    const direct = (element, tag) => Array.from(element.children).find(child => child.tagName === tag);
    const currentPath = location.pathname.replace(/\/$/, '');
    const icon = name => { const item = document.createElement('i'); item.className = name; item.setAttribute('aria-hidden', 'true'); return item; };
    const rename = (anchor, label) => {
        const glyph = anchor.querySelector('i');
        const text = document.createElement('span'); text.className = 'side-menu__label'; text.textContent = label;
        anchor.replaceChildren(...(glyph ? [glyph] : []), text);
    };
    function take(path, label) {
        const anchor = Array.from(menu.querySelectorAll('a[href]')).find(item => pathOf(item) === path);
        if (!anchor) return null;
        const item = anchor.closest('li');
        if (!item || direct(item, 'UL')) return null;
        if (label) rename(anchor, label);
        item.remove(); return item;
    }
    function branch(label, key, glyph, children) {
        children = children.filter(Boolean); if (!children.length) return null;
        const item = document.createElement('li'); item.className = 'slide crm-nav-branch'; item.dataset.crmKey = key;
        const details = document.createElement('details'); details.className = 'crm-nav-group';
        const summary = document.createElement('summary');
        summary.setAttribute('aria-label', label);
        if (glyph) summary.appendChild(icon(glyph));
        const text = document.createElement('span'); text.className = 'side-menu__label'; text.textContent = label; summary.append(text, icon('ri-arrow-right-s-line side-menu__angle'));
        const list = document.createElement('ul'); list.className = 'crm-nav-children'; list.append(...children);
        details.append(summary, list); item.appendChild(details); return item;
    }
    // Move only links already rendered by the server; this layer never grants access.
    const hr = take('/admin/hr');
    const attendance = branch('Attendance', 'attendance', 'ri-calendar-check-line', [take('/admin/attendance/import'), take('/admin/attendance/settings')]);
    const dashboard = take('/admin/kpi', 'KPI Dashboard');
    // HR's former second department link is now represented by the dashboard tabs.
    while (Array.from(menu.querySelectorAll('a[href]')).some(anchor => pathOf(anchor) === '/admin/kpi')) {
        const duplicate = take('/admin/kpi'); if (!duplicate) break;
    }
    const work = branch('Daily Work Done', 'daily-work', 'ri-checkbox-circle-line', [take('/admin/kpi/work-done', 'Sales'), take('/admin/operations', 'Operations')]);
    const kpi = branch('KPI', 'kpi', 'ri-bar-chart-line', [dashboard, work, take('/admin/kpi-management'), take('/admin/hr/operations-manual-kpi'), take('/admin/kpi-history'), take('/admin/kpi/outreach')]);
    const target = branch('Target', 'target', 'ri-focus-3-line', [take('/admin/targets', 'Assign Target'), take('/admin/targets/incentives')]);
    const followup = take('/admin/upcoming-follow-up');
    for (const [item, glyph] of [[hr, 'ri-home-4-line'], [followup, 'ri-list-check-3']]) {
        const original = item?.querySelector('a > i'); if (original) original.className = glyph + ' side-menu__icon';
    }
    const leadList = take('/admin/lead', 'View Leads'), dnp = take('/admin/dnp-leads', 'View DNP Leads');
    const oldLeads = Array.from(menu.children).find(item => { const a = direct(item, 'A'); return a && labelOf(a) === 'Leads'; });
    const remainingLeads = oldLeads ? Array.from(direct(oldLeads, 'UL')?.children || []).filter(item => item.querySelector('a[href]') && !item.classList.contains('side-menu__label1')) : [];
    oldLeads?.remove();
    const leads = branch('Leads', 'leads', 'ri-user-3-line', [leadList, dnp, ...remainingLeads]);
    // Convert the remaining authorized menu groups to the same native tree controls.
    function normalize(item) {
        if (item.classList.contains('slide__category') || item.classList.contains('side-menu__label1')) return null;
        const native = direct(item, 'DETAILS');
        if (native) {
            const list = direct(native, 'UL');
            Array.from(list.children).forEach(child => { const next = normalize(child); if (next) list.replaceChild(next, child); else child.remove(); });
            return list.children.length ? item : null;
        }
        const anchor = direct(item, 'A'); if (!anchor) return null;
        const list = direct(item, 'UL');
        if (list) return branch(labelOf(anchor), 'other', anchor.querySelector('i')?.className || 'ri-folder-line', Array.from(list.children).map(normalize));
        if (!pathOf(anchor)) return null;
        item.classList.remove('active', 'open', 'has-sub'); item.classList.add('crm-nav-leaf');
        anchor.classList.remove('active'); anchor.removeAttribute('aria-current');
        if (pathOf(anchor) === currentPath) { item.classList.add('active'); anchor.setAttribute('aria-current', 'page'); }
        return item;
    }
    const primary = [['HR Dashboard', hr], ['Attendance', attendance], ['KPI', kpi], ['Target', target], ["Today's Follow-up", followup], ['Leads', leads]]
        .filter(([, item]) => item).map(([label, item]) => { item.dataset.crmPrimary = label; return normalize(item); }).filter(Boolean);
    const remaining = Array.from(menu.children).map(normalize).filter(Boolean);
    // HR-style groups stay on top; the Operations Dashboard tree comes first after them.
    const operationsIndex = remaining.findIndex(item => item.querySelector(':scope > details > summary > .side-menu__label')?.textContent.trim() === 'Operations Dashboard');
    if (operationsIndex > 0) remaining.unshift(remaining.splice(operationsIndex, 1)[0]);
    menu.replaceChildren(...primary, ...remaining);
    if (remaining.length && primary.length) remaining[0].classList.add('crm-nav-secondary-start');
    menu.querySelectorAll('.crm-nav-group').forEach(details => {
        if (details.querySelector('[aria-current="page"]')) { details.open = true; details.classList.add('crm-nav-current'); }
    });
    sidebar.classList.add('crm-sidebar'); document.documentElement.classList.add('crm-navigation-ready');

    function readTree(list) {
        return Array.from(list.children).map(item => {
            const details = direct(item, 'DETAILS');
            if (details) { const summary = direct(details, 'SUMMARY'); return {label: summary.querySelector('span').textContent, children: readTree(direct(details, 'UL')), active: details.classList.contains('crm-nav-current')}; }
            const anchor = direct(item, 'A'); if (!anchor || !pathOf(anchor)) return null;
            return {label: labelOf(anchor), href: anchor.href, target: anchor.target, active: anchor.getAttribute('aria-current') === 'page'};
        }).filter(Boolean);
    }
    function moreItem(item) {
        if (item.children) {
            const details = document.createElement('details'); details.open = item.active;
            const summary = document.createElement('summary'); summary.textContent = item.label;
            const children = document.createElement('div'); children.className = 'crm-more-children'; children.append(...item.children.map(moreItem));
            details.append(summary, children); return details;
        }
        const anchor = document.createElement('a'); anchor.href = item.href; anchor.textContent = item.label;
        if (item.target) { anchor.target = item.target; anchor.rel = 'noopener'; }
        if (item.active) anchor.setAttribute('aria-current', 'page'); return anchor;
    }
    function renderMore(container) { if (!container) return; container.classList.add('crm-more-tree'); container.replaceChildren(...readTree(menu).map(moreItem)); }
    const containers = '#hr-mobile-more,#leads-mobile-more,#kpi-mobile-more,#target-mobile-more,#attendanceMoreLinks,[data-more-links],[data-tool-links]';
    function refreshMore() { document.querySelectorAll(containers).forEach(renderMore); }
    window.CrmNavigation = {tree: () => readTree(menu), renderMore};
    // Existing page controls still own opening/closing; refresh their contents from one tree.
    document.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (button && button.closest('[class*="mobile-nav"],#attendanceMobileNav') && /^more$/i.test(button.textContent.replace(/\s+/g, ' ').trim())) {
            refreshMore();
            const custom = button.getAttribute('aria-controls');
            if (custom && !document.getElementById(custom)?.querySelector('h2,header')) renderMore(document.getElementById(custom));
        }
    }, true);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => setTimeout(refreshMore, 0));
    else setTimeout(refreshMore, 0);
}());
