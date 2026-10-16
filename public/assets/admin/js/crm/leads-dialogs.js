(function () {
    'use strict';
    const requestDialog = document.getElementById('leads-request');
    const downloadForm = document.getElementById('leads-download-form');
    document.querySelectorAll('[data-lead-dialog-open]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.leadDialogOpen);
            if (!dialog) return;
            dialog.showModal();
            if (dialog === requestDialog && window.refreshLeadTransferPendingCount) window.refreshLeadTransferPendingCount();
        });
    });
    document.querySelectorAll('.crm-lead-dialog').forEach(dialog => {
        dialog.querySelectorAll('[data-lead-dialog-close]').forEach(button => button.addEventListener('click', () => dialog.close()));
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); dialog.close(); }
        });
    });
    if (downloadForm) {
        downloadForm.elements.period.addEventListener('change', () => {
            downloadForm.querySelectorAll('[data-download-month]').forEach(field => { field.hidden = downloadForm.elements.period.value !== 'month'; });
        });
        downloadForm.addEventListener('submit', event => {
            event.preventDefault();
            const error = downloadForm.querySelector('[role=alert]');
            if (!window.buildLeadsExportUrl) { error.hidden = false; error.textContent = 'The page is still loading. Please try again.'; return; }
            const url = new URL(window.buildLeadsExportUrl(downloadForm.elements.file_type.value === 'csv'), window.location.origin);
            const person = downloadForm.elements.person_id.value;
            if (person) url.searchParams.set('representative_user_id', person);
            else url.searchParams.delete('representative_user_id');
            if (downloadForm.elements.period.value === 'month') {
                ['from_date','to_date','from_created_date','to_created_date','from_followup_date','to_followup_date','followup_period'].forEach(key => url.searchParams.delete(key));
                const year = Number(downloadForm.elements.year.value);
                const month = Number(downloadForm.elements.month.value);
                const endDay = new Date(year, month, 0).getDate();
                const start = `${year}-${String(month).padStart(2,'0')}-01`;
                const end = `${year}-${String(month).padStart(2,'0')}-${endDay}`;
                const basis = downloadForm.elements.date_basis.value;
                const suffix = basis === 'created' ? 'created_date' : (basis === 'followup' ? 'followup_date' : 'date');
                url.searchParams.set(`from_${suffix}`, start);
                url.searchParams.set(`to_${suffix}`, end);
            }
            window.location.assign(url.toString());
        });
    }
    if (!requestDialog) return;
    const searchForm = document.getElementById('lead-request-search');
    const input = document.getElementById('lead-request-term');
    const results = document.getElementById('lead-request-results');
    const status = requestDialog.querySelector('[role=status]');
    let controller;
    let generation = 0;
    const element = (tag, text, className) => {
        const node = document.createElement(tag);
        if (text !== undefined) node.textContent = text;
        if (className) node.className = className;
        return node;
    };
    function clearSearch() {
        generation++;
        if (controller) controller.abort();
        results.replaceChildren();
        searchForm.querySelector('button').disabled = false;
        status.textContent = 'Enter a name or phone number to search.';
    }
    input.addEventListener('input', clearSearch);
    requestDialog.addEventListener('close', () => { clearSearch(); input.value = ''; });
    function renderLead(lead, recipients) {
        const section = element('section', undefined, 'lead-result');
        const details = element('dl');
        [['Name',lead.name],['Phone',lead.phone],['Product',lead.products],['Service date',lead.service_date ? String(lead.service_date).slice(0,10) : 'N/A'],['Assigned',lead.owner]].forEach(([label,value]) => {
            const item = element('div'); item.append(element('dt',label),element('dd',value)); details.append(item);
        });
        section.append(details, element('p', lead.action === 'claim' || lead.owner === 'Unassigned' ? 'Unassigned' : (lead.own ? 'Your lead' : 'Other owner'), 'lead-owner-state'));
        if (!lead.action_url) {
            section.append(element('span', lead.action === 'pending' ? 'Transfer pending' : 'Unassigned', 'lead-owner-state'));
            return section;
        }
        const form = element('form'); form.method = 'POST'; form.action = lead.action_url;
        form.append(document.getElementById('lead-request-csrf').content.cloneNode(true));
        const id = element('input'); id.type = 'hidden'; id.name = 'lead_ids[]'; id.value = lead.id; form.append(id);
        if (lead.action === 'offer' || lead.action === 'assign') {
            const label = element('label','Sales employee');
            const select = element('select'); select.name = 'representative_user_id'; select.required = true;
            const placeholder = element('option','Select person'); placeholder.value = ''; select.append(placeholder);
            recipients.forEach(person => { const option = element('option',person.name); option.value = person.id; select.append(option); });
            label.append(select); form.append(label);
        }
        if (lead.action !== 'assign' && lead.action !== 'claim') {
            const label = element('label','Reason (optional)');
            const reason = element('input'); reason.name = 'reason'; reason.maxLength = 1000; label.append(reason); form.append(label);
        }
        const labels = {request:'Request lead',offer:'Offer transfer',assign:'Assign lead',claim:'Assign to me'};
        const button = element('button',labels[lead.action], 'leads-button leads-primary'); button.type = 'submit';
        form.append(button);
        form.addEventListener('submit', () => { button.disabled = true; button.textContent = 'Submitting...'; });
        section.append(form);
        return section;
    }
    searchForm.addEventListener('submit', async event => {
        event.preventDefault();
        clearSearch();
        const term = input.value.trim();
        const phone = term.replace(/[\s()+-]/g,'');
        if (term.length < 3 || /[%_\\]/.test(term) || (/^\d+$/.test(phone) && phone.length < 6)) {
            status.textContent = 'Enter 3+ name characters or 6+ phone digits, without wildcard symbols.'; return;
        }
        controller = new AbortController(); const current = generation;
        searchForm.querySelector('button').disabled = true; status.textContent = 'Searching...';
        try {
            const url = new URL(requestDialog.dataset.searchUrl, window.location.origin); url.searchParams.set('search',term);
            const response = await fetch(url, {signal:controller.signal,credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
            const payload = await response.json();
            if (current !== generation) return;
            if (!response.ok) throw new Error(payload.errors ? Object.values(payload.errors).flat()[0] : 'Search unavailable. Please try again.');
            results.replaceChildren(...payload.leads.map(lead => renderLead(lead,payload.recipients)));
            status.textContent = payload.leads.length ? `${payload.leads.length} matching leads${payload.leads.length === payload.limit ? ' (limit reached; refine your search)' : ''}.` : 'No matching leads.';
        } catch (error) {
            if (error.name !== 'AbortError' && current === generation) status.textContent = error.message;
        } finally { if (current === generation) searchForm.querySelector('button').disabled = false; }
    });
}());
