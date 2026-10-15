document.addEventListener('DOMContentLoaded', () => {
    const page = document.querySelector('.crm-leads-list');
    if (!page) return;
    document.body.classList.add('crm-leads-page');
    const panels = [...page.querySelectorAll('[data-date-panel]')];
    const tabs = [...page.querySelectorAll('[data-date-kind]')];
    function selectDate(kind) {
        tabs.forEach(tab => tab.setAttribute('aria-pressed', String(tab.dataset.dateKind === kind)));
        panels.forEach(panel => panel.hidden = panel.dataset.datePanel !== kind);
    }
    function showDateFilters() {
        const container = page.querySelector('.leads-applied-dates');
        container.replaceChildren();
        panels.forEach(panel => {
            const values = [...panel.querySelectorAll('input,select')].map(input => input.value).filter(Boolean);
            if (!values.length) return;
            const chip = document.createElement('span');
            chip.textContent = `${panel.dataset.datePanel}: ${values.join(' / ')}`;
            container.append(chip);
        });
    }
    tabs.forEach(tab => tab.addEventListener('click', () => selectDate(tab.dataset.dateKind)));
    panels.forEach(panel => panel.addEventListener('change', showDateFilters));
    const populated = panels.find(panel => [...panel.querySelectorAll('input,select')].some(input => input.value));
    if (populated) selectDate(populated.dataset.datePanel);
    showDateFilters();
    // Clone only links already authorized by the server-rendered sidebar.
    const more = page.querySelector('#leads-mobile-more');
    const toggle = page.querySelector('#leads-more-toggle');
    const bottomPaths = new Set([...page.querySelectorAll('.leads-mobile-nav a')].map(link => new URL(link.href).pathname));
    function menuItem(item) {
        const anchor = [...item.children].find(child => child.tagName === 'A');
        if (!anchor) return null;
        const label = (anchor.querySelector('.side-menu__label') || anchor).textContent.trim();
        const submenu = [...item.children].find(child => child.tagName === 'UL');
        if (submenu) {
            const children = [...submenu.children].map(menuItem).filter(Boolean);
            if (!children.length) return null;
            const group = document.createElement('details');
            const summary = document.createElement('summary'); summary.textContent = label; group.append(summary, ...children); return group;
        }
        const href = anchor.getAttribute('href');
        if (!href) return null;
        const url = new URL(href, location.href);
        if (!['http:', 'https:'].includes(url.protocol) || bottomPaths.has(url.pathname)) return null;
        const link = document.createElement('a'); link.href = url.href; link.textContent = label;
        if (anchor.target === '_blank') { link.target = '_blank'; link.rel = 'noopener'; }
        return link;
    }
    const menu = document.querySelector('.app-sidebar .main-menu');
    if (menu) more.replaceChildren(...[...menu.children].map(menuItem).filter(Boolean));
    function closeMore(focus = false) { more.hidden = true; toggle.setAttribute('aria-expanded', 'false'); if (focus) toggle.focus(); }
    toggle.addEventListener('click', () => { more.hidden = !more.hidden; toggle.setAttribute('aria-expanded', String(!more.hidden)); if (!more.hidden) more.querySelector('a,summary')?.focus(); });
    document.addEventListener('click', event => { if (!more.contains(event.target) && !toggle.contains(event.target)) closeMore(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !more.hidden) closeMore(true); });
    window.matchMedia('(max-width:767px)').addEventListener('change', () => closeMore());
    page.querySelectorAll('.leads-actions a[title]').forEach(link => link.setAttribute('aria-label', link.title));
    const table = page.querySelector('.leads-table');
    const initialRows = [...table.tBodies[0].rows];
    const collator = new Intl.Collator(undefined, {numeric:true, sensitivity:'base'});
    [...table.tHead.rows[0].cells].forEach((heading, index) => {
        if (heading.classList.contains('leads-selection') || index === table.tHead.rows[0].cells.length - 1) return;
        const label = heading.textContent;
        const button = document.createElement('button'); button.type = 'button'; button.className = 'leads-sort'; button.textContent = label; button.title = `Sort by ${label}`;
        const glyph = document.createElement('i'); glyph.className = 'ri-arrow-up-down-line'; glyph.setAttribute('aria-hidden','true'); button.append(glyph);
        heading.replaceChildren(button);
        button.addEventListener('click', () => {
            const ascending = heading.getAttribute('aria-sort') !== 'ascending';
            [...table.tHead.rows[0].cells].forEach(cell => cell.removeAttribute('aria-sort'));
            heading.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');
            const rows = [...initialRows].sort((a,b) => {
                const first = a.cells[index], second = b.cells[index];
                if (!first || !second) return 0;
                const result = collator.compare(first.dataset.sortValue ?? first.textContent.trim(), second.dataset.sortValue ?? second.textContent.trim());
                return ascending ? result : -result;
            });
            table.tBodies[0].append(...rows);
        });
    });
});
