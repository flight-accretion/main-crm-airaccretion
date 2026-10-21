(() => {
    const root = document.querySelector('.booking-email-page');
    if (!root) return;
    const saved = JSON.parse(document.getElementById('booking-template-data').textContent);
    saved.body = saved.body.replace(/\r\n?/g, '\n');
    const form = document.getElementById('booking-template-form');
    const subject = document.getElementById('subject');
    const body = document.getElementById('body');
    const discard = document.getElementById('booking-discard');
    const state = document.getElementById('booking-dirty');
    const warning = document.getElementById('booking-variable-warning');
    const note = document.getElementById('booking-change-note');
    const samples = saved.samples;
    let target = body;
    let submitting = false;
    const dirty = () => subject.value !== saved.subject || body.value !== saved.body || note.value !== '';
    // Match the two placeholder forms supported by the existing email service.
    const merge = text => {
        saved.variables.forEach(key => {
            const value = samples[key] ?? `[Sample ${key.replaceAll('_', ' ')}]`;
            text = text.split(`{{${key}}}`).join(value).split(`{{ ${key} }}`).join(value);
        });
        return text;
    };
    const update = () => {
        document.getElementById('booking-preview-subject').textContent = merge(subject.value);
        document.getElementById('booking-preview-body').textContent = merge(body.value);
        discard.disabled = !dirty();
        state.textContent = dirty() ? 'Unsaved changes' : 'Saved';
        const unresolved = [...new Set((merge(subject.value + '\n' + body.value).match(/\{\{[^{}]+\}\}/g) || []))];
        warning.hidden = !unresolved.length;
        warning.textContent = unresolved.length ? `Unrecognized variables: ${unresolved.join(', ')}` : '';
    };
    [subject, body].forEach(field => {
        field.addEventListener('focus', () => { target = field; });
        field.addEventListener('input', update);
    });
    note.addEventListener('input', update);
    root.querySelectorAll('[data-booking-variable]').forEach(button => {
        button.addEventListener('click', () => {
            const token = `{{${button.dataset.bookingVariable}}}`;
            if (target.value.length - (target.selectionEnd - target.selectionStart) + token.length > target.maxLength) return;
            target.setRangeText(token, target.selectionStart, target.selectionEnd, 'end');
            target.focus();
            update();
        });
    });
    discard.addEventListener('click', () => {
        if (!dirty() || !window.confirm('Discard unsaved changes and restore the saved template?')) return;
        subject.value = saved.subject;
        body.value = saved.body;
        note.value = '';
        update();
    });
    document.querySelectorAll('.be-restore-form').forEach(restore => {
        restore.addEventListener('submit', event => {
            if (!window.confirm(`Restore v${restore.dataset.version} as a new live version?${dirty() ? ' Unsaved edits will be discarded.' : ''}`)) {
                event.preventDefault();
            } else submitting = true;
        });
    });
    const sendTest = document.getElementById('booking-send-test');
    const result = document.getElementById('booking-test-result');
    sendTest.addEventListener('click', async () => {
        if (!form.reportValidity() || sendTest.disabled) return;
        sendTest.disabled = true;
        result.hidden = false;
        result.textContent = 'Sending test email...';
        try {
            const response = await fetch(saved.testUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':form.querySelector('[name="_token"]').value},
                body: JSON.stringify({subject:subject.value, body:body.value})
            });
            const payload = await response.json();
            if (!response.ok) throw new Error(payload.message || 'Test email could not be sent.');
            result.textContent = payload.message;
        } catch (error) {
            result.textContent = error instanceof SyntaxError ? 'Test email could not be sent. Please refresh and retry.' : error.message;
        } finally {
            sendTest.disabled = false;
        }
    });
    form.addEventListener('submit', () => { submitting = true; });
    window.addEventListener('beforeunload', event => {
        if (!submitting && dirty()) { event.preventDefault(); event.returnValue = ''; }
    });
    update();
})();
