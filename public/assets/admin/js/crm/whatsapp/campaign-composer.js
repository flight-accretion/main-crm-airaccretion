(() => {
    const composer = document.querySelector('[data-msg91-composer]');
    if (!composer) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const endpoint = composer.dataset.previewUrl;
    const storeEndpoint = composer.dataset.storeUrl;
    const selected = new Set();
    const form = composer.querySelector('[data-msg91-form]');
    const summary = composer.querySelector('[data-msg91-summary]');
    const template = form.elements.template_id;
    const submit = composer.querySelector('[data-msg91-submit]');
    const error = composer.querySelector('[data-msg91-error]');
    const preview = composer.querySelector('[data-msg91-preview]');
    const variables = composer.querySelector('[data-msg91-variables]');
    let previewData = null;

    const showError = message => { error.textContent = message || ''; error.hidden = !message; };
    const refreshToolbar = () => {
        document.querySelectorAll('[data-msg91-selection-count]').forEach(node => node.textContent = String(selected.size));
        document.querySelectorAll('[data-msg91-selection-toolbar]').forEach(node => node.classList.toggle('is-visible', selected.size > 0));
    };
    const resetPreview = () => {
        previewData = null; template.innerHTML = '<option value="">Loading approved templates…</option>'; template.disabled = true;
        submit.disabled = true; preview.hidden = true; variables.replaceChildren();
    };
    const fetchPreview = async () => {
        resetPreview(); showError('');
        if (!selected.size) { summary.textContent = 'Select one or more eligible leads to continue.'; return; }
        summary.textContent = 'Checking recipient eligibility…';
        try {
            const response = await fetch(endpoint, {method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({lead_ids:[...selected]})});
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Could not validate recipients.');
            previewData = data;
            summary.textContent = `${data.eligible_count} eligible of ${data.selected_count} selected · ${data.excluded.duplicates} duplicate · ${data.excluded.invalid + data.excluded.suppressed + data.excluded.unauthorized} excluded`;
            template.innerHTML = '<option value="">Select an approved template</option>';
            data.templates.forEach(item => {
                const option = document.createElement('option'); option.value = item.id; option.textContent = `${item.name} (${item.language})`; template.append(option);
            });
            template.disabled = data.templates.length === 0 || data.eligible_count === 0;
            Object.entries(data.sample || {}).forEach(([key,value]) => { const dt=document.createElement('dt'),dd=document.createElement('dd');dt.textContent=key.replaceAll('_',' ');dd.textContent=value || 'Not available';variables.append(dt,dd); });
            preview.hidden = variables.children.length === 0;
        } catch (exception) {
            summary.textContent = 'Recipient validation failed.'; showError(exception.message);
        }
    };
    const open = () => { composer.hidden=false; document.body.classList.add('msg91-composer-open'); fetchPreview(); };
    const close = () => { composer.hidden=true; document.body.classList.remove('msg91-composer-open'); };

    document.addEventListener('change', event => {
        const box = event.target.closest('[data-msg91-lead-checkbox]');
        if (!box) return;
        box.checked ? selected.add(box.value) : selected.delete(box.value);
        refreshToolbar();
    });
    document.addEventListener('change', event => {
        const all = event.target.closest('[data-msg91-select-page]');
        if (!all) return;
        document.querySelectorAll('[data-msg91-lead-checkbox]').forEach(box => {
            if (box.closest('tr')?.hidden) return;
            box.checked = all.checked;
            all.checked ? selected.add(box.value) : selected.delete(box.value);
        });
        refreshToolbar();
    });
    document.querySelectorAll('[data-msg91-open]').forEach(button => button.addEventListener('click', open));
    document.querySelectorAll('[data-msg91-clear]').forEach(button => button.addEventListener('click', () => { selected.clear();document.querySelectorAll('[data-msg91-lead-checkbox]').forEach(box => box.checked=false);refreshToolbar(); }));
    composer.querySelectorAll('[data-msg91-close]').forEach(button=>button.addEventListener('click',close));
    template.addEventListener('change', () => { submit.disabled = !(template.value && previewData?.eligible_count); });
    form.addEventListener('submit', async event => {
        event.preventDefault(); showError('');
        if (!template.value || !selected.size) return;
        submit.disabled=true; submit.textContent='Queueing…';
        try {
            const response = await fetch(storeEndpoint,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf},body:JSON.stringify({name:form.elements.name.value,template_id:template.value,lead_ids:[...selected]})});
            const data=await response.json(); if(!response.ok) throw new Error(data.message || 'Could not queue campaign.');
            window.location.assign(composer.dataset.recordsUrl + '/' + data.campaign_id);
        } catch(exception) { showError(exception.message); submit.disabled=false; submit.textContent='Queue campaign'; }
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !composer.hidden) close(); });
})();
