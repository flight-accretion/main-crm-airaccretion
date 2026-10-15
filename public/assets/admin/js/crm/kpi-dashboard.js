document.addEventListener('DOMContentLoaded', () => {
    const dashboard = document.querySelector('.crm-kpi-dashboard');
    if (!dashboard) return;
    document.body.classList.add('crm-kpi-page');
    const panels = [...dashboard.querySelectorAll('[data-person-panel]')];
    const selectors = [...dashboard.querySelectorAll('button[data-select-person]')];
    const search = dashboard.querySelector('#kpi-dashboard-search');
    const size = dashboard.querySelector('#kpi-dashboard-page-size');
    const pagination = dashboard.querySelector('#kpi-dashboard-pagination');
    let selected = '0'; let currentPage = 1;
    function render() {
        const panel = panels.find(item => item.dataset.personPanel === selected);
        if (!panel) return;
        const rows = [...panel.querySelectorAll('[data-kpi-dashboard-row]')];
        const term = search.value.trim().toLowerCase();
        const filtered = rows.filter(row => !term || (row.dataset.search || row.textContent).toLowerCase().includes(term));
        const pageSize = size.value === 'all' ? (rows.length || 1) : Number(size.value) || 25;
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        currentPage = Math.min(currentPage, totalPages);
        const start = (currentPage - 1) * pageSize;
        rows.forEach(row => row.style.display = 'none');
        filtered.slice(start,start + pageSize).forEach(row => row.style.display = '');
        const total = panel.querySelector('[data-kpi-dashboard-total-row]');
        if (total) total.hidden = !filtered.length;
        panel.querySelector('.kpi-search-empty').hidden = !rows.length || Boolean(filtered.length);
        dashboard.querySelector('#kpi-dashboard-count').textContent = filtered.length ? `Showing ${start + 1}-${Math.min(start + pageSize, filtered.length)} of ${filtered.length} KPI rows` : 'No KPI rows found';
        pagination.replaceChildren();
        if (totalPages <= 1) return;
        function button(label, page, disabled = false, active = false) {
            const element = document.createElement('button'); element.type = 'button'; element.textContent = label; element.disabled = disabled;
            if (active) element.setAttribute('aria-current','page');
            element.onclick = () => { currentPage = page; render(); }; pagination.append(element);
        }
        button('Previous', currentPage - 1, currentPage === 1);
        for (let page = 1; page <= totalPages; page++) button(String(page),page,false,page === currentPage);
        button('Next', currentPage + 1, currentPage === totalPages);
    }
    function selectPerson(index) {
        if (!panels.some(panel => panel.dataset.personPanel === index)) return;
        selected = index; currentPage = 1;
        panels.forEach(panel => panel.hidden = panel.dataset.personPanel !== selected);
        selectors.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.selectPerson === selected)));
        dashboard.querySelectorAll('tr[data-select-person]').forEach(row => row.classList.toggle('is-selected',row.dataset.selectPerson === selected));
        render();
    }
    dashboard.querySelectorAll('[data-select-person]').forEach(element => element.addEventListener('click', () => selectPerson(element.dataset.selectPerson)));
    search.addEventListener('input', () => { currentPage = 1; render(); });
    size.addEventListener('change', () => { currentPage = 1; render(); });
    const preset = dashboard.querySelector('#kpi-date-type');
    function customDates() { dashboard.querySelectorAll('.kpi-custom-date-field').forEach(field => field.style.display = preset.value === 'custom' ? '' : 'none'); }
    preset.addEventListener('change',customDates); customDates();
    dashboard.querySelectorAll('.js-kpi-score').forEach(button => {
        try {
            const score = Number(JSON.parse(button.dataset.kpi).score);
            button.classList.add(score >= 4 ? 'kpi-green' : score >= 3 ? 'kpi-amber' : 'kpi-red');
        } catch (_) { /* The existing modal handles unavailable score payloads. */ }
    });
    const more = dashboard.querySelector('#kpi-mobile-more');
    const toggle = dashboard.querySelector('#kpi-more-toggle');
    const nav = dashboard.querySelector('.kpi-mobile-nav');
    const sidebar = document.querySelector('.app-sidebar .main-menu');
    const sidebarLinks = sidebar ? [...sidebar.querySelectorAll('a[href]')] : [];
    function authorizedLink(pattern) {
        return sidebarLinks.find(link => pattern.test(new URL(link.getAttribute('href'),location.href).pathname));
    }
    const defaults = {dashboard: authorizedLink(/(?:sales-dashboard|operations(?:\/dashboard)?|hr)\/?$/), followup: authorizedLink(/(?:upcoming-follow-up|vendor-followups)\/?$/)};
    Object.entries(defaults).forEach(([key,source]) => {
        const link = nav.querySelector(`[data-kpi-bottom="${key}"]`);
        if (source) link.href = source.href; else link.remove();
    });
    // Bottom shortcuts and More inherit only the user's server-rendered menu.
    nav.querySelectorAll('a[href]').forEach(link => {
        const path = new URL(link.href).pathname;
        if (!sidebarLinks.some(source => new URL(source.getAttribute('href'),location.href).pathname === path) && !link.hasAttribute('aria-current')) link.remove();
    });
    nav.style.gridTemplateColumns = `repeat(${nav.children.length},minmax(0,1fr))`;
    const bottomPaths = new Set([...nav.querySelectorAll('a[href]')].map(link => new URL(link.href).pathname));
    function menuItem(item) {
        const anchor = [...item.children].find(child => child.tagName === 'A'); if (!anchor) return null;
        const submenu = [...item.children].find(child => child.tagName === 'UL');
        const label = (anchor.querySelector('.side-menu__label') || anchor).textContent.trim();
        if (submenu) {
            const children = [...submenu.children].map(menuItem).filter(Boolean); if (!children.length) return null;
            const group = document.createElement('details'); const summary = document.createElement('summary'); summary.textContent = label; group.append(summary,...children); return group;
        }
        const href = anchor.getAttribute('href'); if (!href) return null;
        const url = new URL(href,location.href); if (!['http:','https:'].includes(url.protocol) || bottomPaths.has(url.pathname)) return null;
        const link = document.createElement('a'); link.href = url.href; link.textContent = label;
        if (anchor.target === '_blank') { link.target = '_blank'; link.rel = 'noopener'; } return link;
    }
    if (sidebar) more.replaceChildren(...[...sidebar.children].map(menuItem).filter(Boolean));
    function closeMore(focus = false) { more.hidden = true; toggle.setAttribute('aria-expanded','false'); if (focus) toggle.focus(); }
    toggle.onclick = () => { more.hidden = !more.hidden; toggle.setAttribute('aria-expanded',String(!more.hidden)); if (!more.hidden) more.querySelector('a,summary')?.focus(); };
    document.addEventListener('click', event => { if (!more.contains(event.target) && !toggle.contains(event.target)) closeMore(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !more.hidden) closeMore(true); });
    window.matchMedia('(max-width:767px)').addEventListener('change', () => closeMore());
    selectPerson('0');
});
