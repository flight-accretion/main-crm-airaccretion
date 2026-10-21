(() => {
    const dialog = document.getElementById('reschedule-cancel-dialog');
    const form = document.getElementById('reschedule-cancel-form');
    if (!dialog || !form) return;
    let submitting = false;
    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-reschedule-cancel]');
        if (!trigger) return;
        const url = new URL(trigger.dataset.cancelUrl, window.location.href);
        if (url.origin !== window.location.origin) return;
        submitting = false;
        form.action = url.href;
        form.querySelector('[type=submit]').disabled = false;
        dialog.querySelectorAll('[data-cancel-detail]').forEach(field => {
            field.textContent = trigger.dataset[field.dataset.cancelDetail] || 'Not recorded';
        });
        dialog.showModal();
        dialog.querySelector('[data-close-cancel]').focus();
    });
    dialog.querySelectorAll('[data-close-cancel]').forEach(button => button.addEventListener('click', () => {
        if (!submitting) dialog.close();
    }));
    dialog.addEventListener('cancel', event => { if (submitting) event.preventDefault(); });
    form.addEventListener('submit', event => {
        if (submitting || !form.getAttribute('action')) { event.preventDefault(); return; }
        submitting = true;
        form.querySelector('[type=submit]').disabled = true;
    });
    window.addEventListener('pageshow', () => {
        submitting = false;
        form.querySelector('[type=submit]').disabled = false;
        if (dialog.open) dialog.close();
    });
})();
