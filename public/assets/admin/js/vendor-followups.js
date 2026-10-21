(() => {
    const form = document.getElementById('vendor-followup-form');
    if (!form) return;
    const formatDate = value => {
        if (!value) return 'Not scheduled';
        const date = new Date(`${value.slice(0, 10)}T12:00:00`);
        if (Number.isNaN(date.getTime())) return value;
        const formatted = new Intl.DateTimeFormat('en-GB', {day: '2-digit', month: 'short', year: 'numeric'}).format(date);
        return value.includes('T') ? `${formatted} · ${value.slice(11, 16)}` : formatted;
    };
    const sync = () => {
        document.querySelectorAll('[data-vf-preview]').forEach(field => {
            const input = form.elements[field.dataset.vfPreview];
            let value = input.value;
            if (input.tagName === 'SELECT') value = input.selectedOptions[0]?.textContent || '';
            if (['followup_date', 'tentative_ride_at'].includes(input.name)) value = formatDate(input.value);
            field.textContent = value || 'Not recorded';
        });
        const vendor = form.elements.vendor_id.selectedOptions[0];
        document.getElementById('vf-vendor-contact').hidden = !form.elements.vendor_id.value;
        document.querySelector('[data-vendor-name]').textContent = vendor?.textContent || '';
        document.querySelector('[data-vendor-phone]').textContent = vendor?.dataset.phone || 'Phone not recorded';
        form.querySelectorAll('[data-date-offset]').forEach(button => {
            button.setAttribute('aria-pressed', String(dateForOffset(Number(button.dataset.dateOffset)) === form.elements.followup_date.value));
        });
    };
    const dateForOffset = offset => {
        const date = new Date(`${form.dataset.today}T12:00:00Z`);
        date.setUTCDate(date.getUTCDate() + offset);
        return date.toISOString().slice(0, 10);
    };
    form.querySelectorAll('[data-date-offset]').forEach(button => button.addEventListener('click', () => {
        form.elements.followup_date.value = dateForOffset(Number(button.dataset.dateOffset));
        sync();
    }));
    form.addEventListener('input', sync);
    form.addEventListener('change', sync);
    sync();
})();
