(() => {
    const form = document.getElementById('reschedule-editor');
    if (!form) return;
    const result = document.getElementById('booking-conflict-result');
    let pending;
    let timer;
    let revision = 0;
    const check = async () => {
        if (!result) return;
        const currentRevision = ++revision;
        pending?.abort();
        if (form.querySelector('[name="no_date"]:checked')?.value === '1') {
            result.textContent = 'No date selected. Availability will be checked when a slot is confirmed.';
            result.className = 'rc-notice';
            return;
        }
        const params = new URLSearchParams();
        for (const key of ['booking_resource_key', 'from_date', 'to_date']) params.set(key, form.elements[key]?.value || '');
        pending = new AbortController();
        result.textContent = 'Checking availability...';
        try {
            const response = await fetch(`${form.dataset.conflictsUrl}?${params}`, {headers: {'Accept': 'application/json'}, signal: pending.signal});
            const data = await response.json();
            if (currentRevision !== revision) return;
            if (!response.ok) throw new Error(response.status === 422 ? 'Enter a valid resource and an end time later than the start.' : 'Availability could not be verified.');
            const messages = {
                unverified: 'Aircraft / vehicle not assigned. Availability unverified.',
                unavailable: 'Booking resource setup is pending. Availability unverified.',
                incomplete: 'Enter both start and end time to check this resource.',
                checked: 'No overlapping slot found among CRM rides with this exact resource ID. Unassigned rides are not covered.',
                conflict: 'Conflict: this resource is already booked or has an incomplete overlapping slot. Saving this slot is blocked.',
            };
            result.textContent = messages[data.status] || 'Availability unverified.';
            result.className = `rc-notice${data.status === 'conflict' ? ' rc-danger' : ''}`;
            if (data.conflicts?.length) result.textContent += ' '+data.conflicts.map(slot => `${slot.from} to ${slot.to || 'end time missing'}`).join('; ');
        } catch (error) {
            if (error.name !== 'AbortError' && currentRevision === revision) {
                result.textContent = error.message || 'Availability could not be verified.';
                result.className = 'rc-notice rc-warning';
            }
        }
    };
    const sync = () => {
        const open = form.querySelector('[name="no_date"]:checked')?.value === '1';
        form.querySelectorAll('[data-date-field]').forEach(input => {
            input.disabled = open;
            input.required = !open && input.name === 'from_date';
        });
        document.getElementById('reschedule-window').hidden = !open;
        form.querySelectorAll('[data-current]').forEach(input => {
            input.classList.toggle('rc-changed', !input.disabled && input.value !== input.dataset.current);
        });
    };
    const changed = event => {
        sync();
        if (['no_date', 'from_date', 'to_date', 'booking_resource_key'].includes(event.target.name)) {
            ++revision;
            pending?.abort();
            clearTimeout(timer);
            if (result) result.textContent = 'Availability not checked for this slot.';
            timer = setTimeout(check, 400);
        }
    };
    form.addEventListener('change', changed);
    form.addEventListener('input', changed);
    document.getElementById('check-booking-conflict')?.addEventListener('click', check);
    sync();
})();
