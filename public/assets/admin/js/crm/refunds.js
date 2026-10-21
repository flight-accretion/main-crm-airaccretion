(() => {
    const root = document.querySelector('.crm-refunds');
    if (!root) return;
    root.addEventListener('click', event => {
        if (event.target.closest('a,button,input,select,textarea,label,[role="button"]')) return;
        if (window.getSelection()?.toString()) return;
        const row = event.target.closest('.refund-list-table tr[data-lead-url]');
        if (row) window.open(row.dataset.leadUrl, '_blank', 'noopener');
    });
    const labelRows = () => {
        root.querySelectorAll('.refund-list-table').forEach(table => {
            const labels = [...table.querySelectorAll('thead tr:first-child th')].map(cell => cell.textContent.trim());
            if (!labels.length) return;
            table.querySelectorAll('tbody tr').forEach(row => {
                [...row.children].forEach((cell, index) => {
                    if (cell.colSpan === 1) cell.dataset.label = labels[index] || '';
                });
            });
        });
    };
    // Approval is tracked only; it does not change the existing Mark as done flow.
    root.addEventListener('click', async event => {
        const button = event.target.closest('.refund-approval-btn');
        if (!button || button.disabled) return;
        const decision = button.dataset.decision;
        let note = null;
        if (decision === 'reject') {
            note = window.prompt('Reason for rejecting this refund:');
            if (note === null) return;
            if (!note.trim()) { window.alert('A reason is required to reject.'); return; }
        } else if (!window.confirm('Approve this refund?')) {
            return;
        }
        const buttons = button.closest('td').querySelectorAll('.refund-approval-btn');
        buttons.forEach(item => { item.disabled = true; });
        try {
            const response = await fetch(button.dataset.url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify({ decision, note }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false) throw new Error(payload.message || 'Could not save the approval.');
            const label = button.closest('tr').querySelector('[data-approval-label]');
            if (label) {
                label.textContent = payload.approval_label;
                label.className = `refund-approval is-${payload.approval_status}`;
            }
            buttons.forEach(item => { item.disabled = item.dataset.decision === decision; });
        } catch (error) {
            buttons.forEach(item => { item.disabled = false; });
            window.alert(error.message);
        }
    });
    labelRows();
    new MutationObserver(labelRows).observe(root, { childList: true, subtree: true });
})();
