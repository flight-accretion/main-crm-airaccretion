(function () {
    'use strict';
    function init() {
        const root = document.querySelector('[data-operations-mobile-navigation]');
        if (!root || !window.CrmNavigation) return;
        const nav = root.querySelector('.operations-mobile-nav');
        const panel = root.querySelector('#operations-mobile-more');
        const toggle = root.querySelector('[data-toggle-operations-more]');
        const links = root.querySelector('[data-operations-more-links]');
        const mobile = window.matchMedia('(max-width:767px)');
        const path = href => new URL(href, location.href).pathname.replace(/\/$/, '');
        const authorized = window.CrmNavigation.authorizedLinks ? window.CrmNavigation.authorizedLinks() : Array.from(document.querySelectorAll('.app-sidebar .main-menu a[href]'));
        const currentPath = path(location.href);
        let primaryActive = false;
        // Copy the authorized sidebar destination, including the configured CRM host.
        root.querySelectorAll('[data-operations-primary]').forEach(link => {
            const source = authorized.find(item => path(item.href) === path(link.href));
            if (!source) { link.hidden = true; return; }
            link.href = source.href;
            if (path(link.href) === currentPath) { link.setAttribute('aria-current', 'page'); primaryActive = true; }
        });
        if (!primaryActive) toggle.setAttribute('aria-current', 'page');
        const columns = root.querySelectorAll('[data-operations-primary]:not([hidden])').length + 1;
        nav.style.gridTemplateColumns = `repeat(${columns},minmax(0,1fr))`;
        document.body.classList.add('crm-operations-mobile-page');
        function close(restoreFocus = true) {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (restoreFocus && mobile.matches) toggle.focus();
        }
        toggle.addEventListener('click', () => {
            if (!panel.hidden) { close(); return; }
            window.CrmNavigation.renderMore(links);
            panel.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
            panel.querySelector('button').focus();
        });
        root.querySelector('[data-close-operations-more]').addEventListener('click', () => close());
        panel.addEventListener('click', event => { if (event.target.closest('a')) close(false); });
        document.addEventListener('click', event => { if (!panel.hidden && !root.contains(event.target)) close(false); });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) { event.preventDefault(); close(); } });
        mobile.addEventListener('change', event => { if (!event.matches) close(false); });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
}());
