(() => {
    'use strict';
    const root = document.getElementById('upcoming-rides');
    if (!root) return;
    const events = JSON.parse(document.getElementById('ur-data').textContent);
    const rows = events.map(event => event.extendedProps.ride);
    const tabs = [...root.querySelectorAll('[data-ride-view]')];
    const storageKey = `crm.upcoming.view.${root.dataset.user}`;
    const params = new URLSearchParams(location.search);
    const today = root.dataset.today;
    const defaultDay = today >= root.dataset.periodStart && today <= root.dataset.periodEnd ? today : root.dataset.periodStart;
    let selectedDay = /^\d{4}-\d{2}-\d{2}$/.test(params.get('day') || '') ? params.get('day') : defaultDay;
    let calendar;

    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    const money = value => new Intl.NumberFormat('en-IN', {style: 'currency', currency: 'INR', maximumFractionDigits: 2}).format(Number(value));
    const dateObject = day => new Date(`${day}T12:00:00`);
    const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

    function showRideDetails(ride) {
        const dialog = document.getElementById('ur-details-dialog');
        const content = document.getElementById('ur-details-content');
        document.getElementById('ur-details-title').textContent = ride.client_name;
        content.replaceChildren();
        const facts = element('dl');
        [
            ['Phone', ride.contact_number], ['Product', ride.product_names], ['Service', ride.service_names],
            ['Service date', ride.ride_date], ['Time', ride.ride_time], ['From', ride.from_place], ['To', ride.to_place],
            ['Status', ride.status_name], ['Payment', ride.payment_label], ['Due', money(ride.pending_amount_value)],
            ['Owner', ride.sales_person_name], ['Extra services', ride.extra_service_names],
            ['Passengers', ride.number_of_passengers], ['Occasion', ride.occasion], ['Description', ride.description],
        ].forEach(([name, value]) => {
            const fact = element('div');
            fact.append(element('dt', name), element('dd', value ? String(value) : 'Not recorded'));
            facts.append(fact);
        });
        content.append(facts);
        const link = element('a', 'Open lead', 'ur-button');
        link.href = ride.lead_url; link.target = '_blank'; link.rel = 'noopener';
        content.append(link);
        dialog.showModal();
    }
    root.querySelectorAll('[data-ride-details]').forEach(button => button.addEventListener('click', () => {
        const ride = rows.find(row => String(row.id) === button.dataset.rideDetails);
        if (ride) showRideDetails(ride);
    }));
    document.getElementById('ur-details-close').addEventListener('click', () => document.getElementById('ur-details-dialog').close());

    function showDay(day) {
        selectedDay = day;
        const panel = document.getElementById('ur-day-panel');
        panel.replaceChildren();
        const rides = rows.filter(ride => ride.ride_day === day);
        const inPeriod = day >= root.dataset.periodStart && day <= root.dataset.periodEnd;
        const urgent = rides.filter(ride => ride.urgent);
        if (urgent.length) panel.append(element('div', `${urgent.length} ${urgent.length === 1 ? 'ride has' : 'rides have'} an unconfirmed service time within 48 hours.`, 'ur-notice ur-urgent'));
        const label = new Intl.DateTimeFormat('en-IN', {weekday: 'short', day: 'numeric', month: 'short'}).format(dateObject(day));
        panel.append(element('h2', `${day === today ? 'Today · ' : ''}${label}`));
        panel.append(element('p', inPeriod ? `${rides.length} ${rides.length === 1 ? 'ride' : 'rides'} · ${rides.filter(ride => ride.pending_amount_value > 0).length} with payment pending` : 'Outside selected period'));
        if (!rides.length && inPeriod) panel.append(element('p', 'No rides on this day.', 'ur-empty'));
        rides.forEach(ride => {
            const card = element('article', undefined, 'ur-day-card');
            card.dataset.rideId = ride.id;
            const heading = element('header');
            heading.append(element('strong', ride.is_tba ? 'TBA' : ride.ride_start.slice(11, 16)));
            const identity = element('div');
            identity.append(element('h3', ride.client_name));
            identity.append(element('p', `${ride.product_names || ride.service_names || 'Service not recorded'} · ${ride.sales_person_name === 'N/A' ? 'Unassigned' : ride.sales_person_name}`));
            const tags = element('div', undefined, 'ur-day-tags');
            tags.append(element('span', ride.status_name, 'ur-tag'));
            tags.append(element('span', ride.payment_label, `ur-tag ${ride.fully_paid ? 'ur-ready' : ride.pending_amount_value > 0 ? 'ur-payment' : ''}`));
            if (!ride.has_vendor) tags.append(element('span', 'Vendor not assigned', 'ur-tag ur-vendor'));
            if (ride.pending_amount_value > 0) tags.append(element('span', `Due ${money(ride.pending_amount_value)}`, 'ur-tag ur-payment'));
            identity.append(tags);
            heading.append(identity);
            card.append(heading);
            const details = element('details');
            details.append(element('summary', 'View details'));
            const facts = element('dl');
            [
                ['Phone', ride.contact_number], ['Service', ride.service_names], ['Product', ride.product_names],
                ['Route', `${ride.from_place} → ${ride.to_place}`], ['Service time', ride.ride_time],
                ['Extra services', ride.extra_service_names], ['Passengers', ride.number_of_passengers],
                ['Occasion', ride.occasion], ['Description', ride.description],
            ].forEach(([name, value]) => {
                if (!value || value === 'N/A') return;
                const fact = element('div');
                fact.append(element('dt', name), element('dd', String(value)));
                facts.append(fact);
            });
            details.append(facts);
            const link = element('a', 'Open lead', 'ur-button');
            link.href = ride.lead_url;
            link.target = '_blank';
            link.rel = 'noopener';
            details.append(link);
            card.append(details);
            panel.append(card);
        });
        const listLink = element('a', 'Open full day in list view →', 'ur-button ur-day-open');
        const url = new URL(root.dataset.baseUrl, location.origin);
        url.search = location.search;
        url.searchParams.set('view', 'list');
        url.searchParams.set('from_date', day);
        url.searchParams.set('to_date', day);
        url.searchParams.delete('day');
        url.searchParams.delete('download');
        listLink.href = url.href;
        panel.append(listLink);
        root.querySelectorAll('.fc-daygrid-day').forEach(cell => cell.classList.toggle('ur-selected-day', cell.dataset.date === day));
    }

    function ensureCalendar() {
        if (calendar) { calendar.updateSize(); return; }
        if (!window.FullCalendar) {
            document.getElementById('ur-calendar-error').hidden = false;
            return;
        }
        calendar = new FullCalendar.Calendar(document.getElementById('ur-calendar'), {
            initialView: 'dayGridMonth', initialDate: root.dataset.periodStart, firstDay: 1,
            headerToolbar: false, height: 'auto', fixedWeekCount: false, dayMaxEvents: 3,
            displayEventTime: false, events,
            datesSet: info => {
                document.getElementById('ur-month').textContent = info.view.title;
                showDay(selectedDay);
            },
            dateClick: info => showDay(info.dateStr),
            eventClick: info => { info.jsEvent.preventDefault(); showDay(info.event.extendedProps.ride.ride_day); },
            moreLinkClick: info => { showDay(dateKey(info.date)); return false; },
            dayCellDidMount: info => {
                const day = dateKey(info.date);
                const count = rows.filter(ride => ride.ride_day === day).length;
                info.el.tabIndex = 0;
                info.el.setAttribute('aria-label', `${day}: ${count} rides`);
                info.el.addEventListener('keydown', event => {
                    if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); showDay(day); }
                });
                if (count) {
                    const badge = element('span', String(count), 'ur-day-count');
                    badge.title = `${count} rides`;
                    info.el.querySelector('.fc-daygrid-day-top')?.prepend(badge);
                }
            },
            eventContent: info => {
                const ride = info.event.extendedProps.ride;
                const content = element('div');
                content.title = `${ride.client_name} · ${ride.status_name} · ${ride.payment_label}`;
                content.append(element('strong', `${ride.is_tba ? 'TBA' : ride.ride_start.slice(11, 16)} · ${ride.client_name}`));
                content.append(element('small', ride.product_names || ride.service_names || ride.status_name));
                return {domNodes: [content]};
            },
        });
        calendar.render();
        showDay(selectedDay);
    }

    function setView(view) {
        const active = view === 'calendar' ? 'calendar' : 'list';
        tabs.forEach(tab => { tab.setAttribute('aria-selected', String(tab.dataset.rideView === active)); tab.tabIndex = tab.dataset.rideView === active ? 0 : -1; });
        document.getElementById('ur-list').hidden = active !== 'list';
        document.getElementById('ur-calendar-view').hidden = active !== 'calendar';
        document.getElementById('ur-view-input').value = active;
        try { localStorage.setItem(storageKey, active); } catch (_) { /* Storage can be unavailable in private sessions. */ }
        const url = new URL(location.href);
        url.searchParams.set('view', active);
        history.replaceState(null, '', url);
        if (active === 'calendar') ensureCalendar();
    }

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => setView(tab.dataset.rideView));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + 1) % tabs.length;
            tabs[next].focus();
            setView(tabs[next].dataset.rideView);
        });
    });
    function navigateMonth(offset, reset = false) {
        const date = reset ? dateObject(today) : new Date(calendar.getDate().getFullYear(), calendar.getDate().getMonth() + offset, 1);
        const from = new Date(date.getFullYear(), date.getMonth(), 1);
        const to = new Date(date.getFullYear(), date.getMonth() + 1, 0);
        const url = new URL(location.href);
        url.searchParams.set('from_date', dateKey(from));
        url.searchParams.set('to_date', dateKey(to));
        url.searchParams.set('view', 'calendar');
        url.searchParams.delete('download');
        location.assign(url);
    }
    document.getElementById('ur-prev').addEventListener('click', () => { if (calendar) navigateMonth(-1); });
    document.getElementById('ur-next').addEventListener('click', () => { if (calendar) navigateMonth(1); });
    document.getElementById('ur-today').addEventListener('click', () => { if (calendar) navigateMonth(0, true); });
    let preferred = 'list';
    try { preferred = localStorage.getItem(storageKey) || preferred; } catch (_) { /* Use list when storage is unavailable. */ }
    setView(params.get('view') || preferred);
})();
