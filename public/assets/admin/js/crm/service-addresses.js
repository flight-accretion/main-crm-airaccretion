(() => {
    'use strict';
    const page = document.querySelector('[data-address-page]');
    if (!page) return;
    const base = page.dataset.base.replace(/\/$/, '');
    const dialogs = Object.fromEntries([...page.querySelectorAll('[data-address-dialog]')].map(d => [d.dataset.addressDialog, d]));
    const editForm = dialogs.edit.querySelector('form');
    let requestController;
    let requestVersion = 0;
    let requestMode;
    let statusTarget;
    let statusBusy = false;
    const get = async (url, signal) => {
        const response = await fetch(url, {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, signal});
        if (!response.ok) throw new Error('Unable to load address data. Please try again.');
        const json = await response.json();
        if (json.success === false) throw new Error(json.message || 'Unable to load address data.');
        return json;
    };
    function error(element, message) { element.textContent = message; element.hidden = !message; }
    function open(mode) {
        Object.values(dialogs).forEach(d => { if (d.open) d.close(); });
        dialogs[mode].showModal();
    }
    function options(select, rows, key, selected = '') {
        const placeholder = select.options[0]?.textContent || 'Select';
        select.replaceChildren(new Option(placeholder, ''));
        rows.forEach(row => select.add(new Option(row[key], row.id)));
        select.value = selected || '';
    }
    function selectedOption(select, id, label) {
        if (!id) return;
        if (![...select.options].some(o => o.value === String(id))) select.add(new Option(label || 'Unavailable selection', id));
        select.value = id;
    }
    function detailRow(container, label, value) {
        const wrapper = document.createElement('div');
        const dt = document.createElement('dt'); dt.textContent = label;
        const dd = document.createElement('dd'); dd.textContent = value || 'Not recorded';
        wrapper.append(dt, dd); container.append(wrapper);
        return dd;
    }
    async function loadRecord(mode, id) {
        open(mode);
        requestController?.abort();
        requestMode = mode;
        requestController = new AbortController();
        const version = ++requestVersion;
        const details = dialogs.view.querySelector('[data-address-details]');
        if (mode === 'view') details.textContent = 'Loading address...';
        else {
            editForm.reset();
            editForm.action = '#';
            editForm.elements._address_id.value = '';
            editForm.querySelector('fieldset').disabled = true;
            editForm.querySelector('[data-save]').disabled = true;
            error(editForm.querySelector('[data-form-error]'), 'Loading address...');
        }
        try {
            const data = await get(`${base}/${encodeURIComponent(id)}/${mode === 'view' ? 'view-modal' : 'edit-details'}`, requestController.signal);
            const record = mode === 'view' ? data.serviceAddress : data.data?.serviceAddress;
            if (version !== requestVersion) return;
            if (!record || String(record.id) !== id) throw new Error('Address did not match the selected record. Please reopen it.');
            if (mode === 'view') {
                const dl = document.createElement('dl'); dl.className = 'sa-details';
                [['Address', record.address], ['Contact person', record.contact_person_name], ['Contact number', record.contact_number], ['Product', record.product?.product], ['Service', record.service?.service], ['Country', record.city?.state?.country?.name], ['State', record.city?.state?.name], ['City', record.city?.name], ['Status', Number(record.status) === 1 ? 'Active' : 'Inactive']].forEach(([label, value]) => detailRow(dl, label, value));
                const map = detailRow(dl, 'Map link', record.map_link);
                try {
                    const url = new URL(record.map_link);
                    if (['http:', 'https:'].includes(url.protocol)) {
                        const link = document.createElement('a'); link.href = url.href; link.textContent = 'View map'; link.target = '_blank'; link.rel = 'noopener noreferrer'; map.replaceChildren(link);
                    }
                } catch (_) { /* Keep an invalid legacy map value as plain text. */ }
                details.replaceChildren(dl);
            } else {
                const source = data.data;
                options(editForm.elements.product_id, source.products, 'product', record.product_id);
                options(editForm.elements.service_id, source.services, 'service', record.service_id);
                options(editForm.elements.country_id, source.countries, 'name', record.city?.state?.country_id);
                options(editForm.elements.state_id, source.states, 'name', record.city?.state_id);
                options(editForm.elements.city_id, source.cities, 'name', record.city_id);
                selectedOption(editForm.elements.product_id, record.product_id, record.product?.product);
                selectedOption(editForm.elements.service_id, record.service_id, record.service?.service);
                selectedOption(editForm.elements.state_id, record.city?.state_id, record.city?.state?.name);
                selectedOption(editForm.elements.city_id, record.city_id, record.city?.name);
                ['contact_person_name', 'address', 'map_link'].forEach(key => { editForm.elements[key].value = record[key] || ''; });
                const phone = String(record.contact_number || '');
                const split = phone.indexOf('-');
                editForm.elements.contact_country_code.value = split >= 0 ? phone.slice(0, split) : '+91';
                editForm.elements.contact_number.value = split >= 0 ? phone.slice(split + 1) : phone;
                editForm.elements._address_id.value = id;
                editForm.action = `${base}/${encodeURIComponent(id)}`;
                editForm.querySelector('fieldset').disabled = false;
                editForm.querySelector('[data-save]').disabled = false;
                error(editForm.querySelector('[data-form-error]'), '');
            }
        } catch (e) {
            if (e.name === 'AbortError' || version !== requestVersion) return;
            if (mode === 'view') details.textContent = e.message;
            else error(editForm.querySelector('[data-form-error]'), e.message);
        }
    }
    page.addEventListener('click', event => {
        const close = event.target.closest('[data-address-close]');
        if (close) { if (!statusBusy) close.closest('dialog').close(); return; }
        if (event.target.closest('[data-address-add]')) { open('add'); return; }
        const view = event.target.closest('[data-address-view]');
        if (view) { loadRecord('view', view.dataset.addressView); return; }
        const edit = event.target.closest('[data-address-edit]');
        if (edit) { loadRecord('edit', edit.dataset.addressEdit); return; }
        const status = event.target.closest('[data-address-status]');
        if (status) {
            statusTarget = status.dataset.addressStatus;
            const action = status.dataset.active === '1' ? 'Deactivate' : 'Activate';
            dialogs.status.querySelector('[data-status-message]').textContent = `${action} this address?`;
            dialogs.status.querySelector('[data-status-confirm]').textContent = action;
            error(dialogs.status.querySelector('[data-status-error]'), '');
            open('status');
        }
    });
    Object.values(dialogs).forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target !== dialog || statusBusy) return;
            const r = dialog.getBoundingClientRect();
            if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
        });
        dialog.addEventListener('cancel', event => { if (statusBusy) event.preventDefault(); });
        dialog.addEventListener('close', () => {
            if (requestMode === dialog.dataset.addressDialog && !dialog.open) { requestController?.abort(); ++requestVersion; }
        });
    });
    dialogs.status.querySelector('[data-status-confirm]').addEventListener('click', async event => {
        if (statusBusy || !statusTarget) return;
        statusBusy = true; event.currentTarget.disabled = true;
        try {
            const response = await fetch(`${base}/${encodeURIComponent(statusTarget)}/toggle-status`, {method: 'PATCH', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': page.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest'}});
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Status could not be changed.');
            location.reload();
        } catch (e) { error(dialogs.status.querySelector('[data-status-error]'), e.message); statusBusy = false; dialogs.status.querySelector('[data-status-confirm]').disabled = false; }
    });
    page.querySelectorAll('[data-address-form]').forEach(form => {
        const country = form.elements.country_id, state = form.elements.state_id, city = form.elements.city_id;
        let countryVersion = 0, stateVersion = 0;
        form.addEventListener('reset', () => { ++countryVersion; ++stateVersion; state.disabled = false; city.disabled = false; });
        country.addEventListener('change', async () => {
            const version = ++countryVersion; ++stateVersion;
            state.disabled = false; city.disabled = false;
            options(state, [], 'name'); options(city, [], 'name');
            error(form.querySelector('[data-form-error]'), '');
            if (!country.value) return;
            state.disabled = true;
            try {
                const data = await get(`${base}/states/${encodeURIComponent(country.value)}`);
                if (version === countryVersion) options(state, data.states || [], 'name');
            } catch (e) { if (version === countryVersion) error(form.querySelector('[data-form-error]'), e.message); }
            finally { if (version === countryVersion) state.disabled = false; }
        });
        state.addEventListener('change', async () => {
            const version = ++stateVersion;
            city.disabled = false;
            options(city, [], 'name');
            error(form.querySelector('[data-form-error]'), '');
            if (!state.value) return;
            city.disabled = true;
            try {
                const data = await get(`${base}/cities/${encodeURIComponent(state.value)}`);
                if (version === stateVersion) options(city, data, 'name');
            } catch (e) { if (version === stateVersion) error(form.querySelector('[data-form-error]'), e.message); }
            finally { if (version === stateVersion) city.disabled = false; }
        });
        form.addEventListener('submit', event => {
            if (form.dataset.submitting || state.disabled || city.disabled || (form === editForm && !form.elements._address_id.value)) { event.preventDefault(); return; }
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); return; }
            form.dataset.submitting = '1'; form.querySelector('[data-save]').disabled = true;
        });
    });
    if (['add', 'edit'].includes(page.dataset.restore)) open(page.dataset.restore);
})();
