
(function () {
    'use strict';

    const root = document.getElementById('customer-refund-two-step');

    if (!root) {
        return;
    }

    const el = id => document.getElementById(id);

    let rideId = null;
    let settlement = null;
    let legacyBlocked = false;
    let canCorrectPayment = false;
    let activeRequestId = null;
    let assessmentEditing = false;
    let assessmentSaving = false;
    let paymentSaving = false;
    let loading = false;
    let loadSequence = 0;

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    const cents = value => {
        const number = Number(value);
        return Number.isFinite(number)
            ? Math.round(number * 100)
            : 0;
    };

    const money = value => (value / 100).toFixed(2);

    const url = type => {
        const template = root.dataset[type + 'Url'];

        if (!template || !rideId) {
            throw new Error('Customer refund endpoint is unavailable.');
        }

        return template.replace(
            '__RIDE__',
            encodeURIComponent(rideId)
        );
    };

    const csrf = () => {
        return root.querySelector('input[name="_token"]')?.value
            || document.querySelector('meta[name="csrf-token"]')?.content
            || '';
    };

    const showAlert = (message, error = false) => {
        const target = el('cr2-alert');

        if (!target) {
            return;
        }

        target.hidden = !message;
        target.classList.toggle('is-error', !!error);
        target.textContent = message || '';
    };

    const setHidden = (id, hidden) => {
        const target = el(id);

        if (target) {
            target.hidden = hidden;
        }
    };

    const setDisabled = (id, disabled) => {
        const target = el(id);

        if (target) {
            target.disabled = disabled;
        }
    };

    const setReadOnly = (id, readOnly) => {
        const target = el(id);

        if (target) {
            target.readOnly = readOnly;
        }
    };

    /*
    |--------------------------------------------------------------------------
    | API Request
    |--------------------------------------------------------------------------
    */

    async function call(path, options = {}) {
        const response = await fetch(path, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'X-CSRF-TOKEN': csrf(),
                'Accept': 'application/json',
                ...(options.headers || {})
            }
        });

        const body = await response.json().catch(() => ({}));

        if (!response.ok || !body.success) {
            const firstError = body.errors
                ? Object.values(body.errors).flat()[0]
                : null;

            throw new Error(
                firstError
                || body.message
                || `Request failed (${response.status})`
            );
        }

        return body;
    }

    /*
    |--------------------------------------------------------------------------
    | Assessment State
    |--------------------------------------------------------------------------
    */

    function hasRecordedPayments() {
        if (!settlement) {
            return false;
        }

        const transactions = Array.isArray(settlement.transactions)
            ? settlement.transactions
            : [];

        return cents(settlement.already_refunded) > 0
            || transactions.length > 0;
    }

    function canEditAssessment() {
        if (!settlement || legacyBlocked || loading) {
            return false;
        }

        if (hasRecordedPayments()) {
            return false;
        }

        // A zero-refund assessment is treated as finalized.
        if (cents(settlement.refund_due) === 0) {
            return false;
        }

        return true;
    }

    function restoreAssessmentValues() {
        if (!settlement) {
            return;
        }

        el('cr2-charge').value =
            settlement.cancellation_amount ?? '0.00';

        el('cr2-reason').value =
            settlement.reason ?? '';
    }

    function setAssessmentEditMode(editing) {
        const exists = !!settlement;
        const editable = canEditAssessment();

        assessmentEditing = Boolean(
            editing && editable && !assessmentSaving
        );

        const locked = legacyBlocked || loading || assessmentSaving;

        const showEdit =
            exists && editable && !assessmentEditing;

        const showSave =
            !exists || assessmentEditing;

        const showCancel =
            exists && assessmentEditing;

        setHidden('cr2-edit-assessment', !showEdit);
        setHidden('cr2-assess-btn', !showSave);
        setHidden('cr2-cancel-assessment-edit', !showCancel);

        setDisabled(
            'cr2-edit-assessment',
            locked || !editable
        );

        setDisabled(
            'cr2-assess-btn',
            locked || !showSave
        );

        setDisabled(
            'cr2-cancel-assessment-edit',
            locked
        );

        setReadOnly(
            'cr2-charge',
            locked || (exists && !assessmentEditing)
        );

        setReadOnly(
            'cr2-reason',
            locked || (exists && !assessmentEditing)
        );

        const saveButton = el('cr2-assess-btn');

        if (saveButton) {
            saveButton.textContent = assessmentSaving
                ? 'Saving...'
                : (exists ? 'Save Changes' : 'Save Cancellation');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Render Refund History
    |--------------------------------------------------------------------------
    */

    function renderHistory() {
        const history = el('cr2-history');

        if (!history) {
            return;
        }

        history.replaceChildren();

        if (
            !settlement ||
            !Array.isArray(settlement.transactions) ||
            !settlement.transactions.length
        ) {
            return;
        }

        const title = document.createElement('strong');
        title.textContent = 'Recorded customer refunds';

        history.appendChild(title);

        const table = document.createElement('table');

        const thead = document.createElement('thead');
        const headingRow = document.createElement('tr');

        ['Date', 'Amount', 'Method', 'Status', 'Action'].forEach(name => {
            const th = document.createElement('th');
            th.textContent = name;
            headingRow.appendChild(th);
        });

        thead.appendChild(headingRow);
        table.appendChild(thead);

        const tbody = document.createElement('tbody');

        settlement.transactions.forEach(item => {
            const row = document.createElement('tr');

            const values = [
                item.date || '-',
                '₹' + (item.amount ?? '0.00'),
                item.method || '-',
                item.reversed ? 'Corrected' : 'Recorded'
            ];

            values.forEach(value => {
                const td = document.createElement('td');
                td.textContent = String(value);
                row.appendChild(td);
            });

            const action = document.createElement('td');
            if (canCorrectPayment && !item.reversed && cents(settlement.remaining) > 0) {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'ti-btn ti-btn-sm ti-btn-outline-primary';
                button.textContent = 'Correct payment';
                button.addEventListener('click', () => correctPayment(item.id));
                action.appendChild(button);
            } else {
                action.textContent = '-';
            }
            row.appendChild(action);

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        history.appendChild(table);
    }

    async function correctPayment(transactionId) {
        const reason = window.prompt('Why does this payment need correction?');
        if (reason === null) return;
        if (!reason.trim()) {
            showAlert('Please enter a correction reason.', true);
            return;
        }
        try {
            const form = new FormData();
            form.set('transaction_id', transactionId);
            form.set('reason', reason.trim());
            const body = await call(url('correct'), { method: 'POST', body: form });
            settlement = body.settlement;
            activeRequestId = null;
            showAlert(body.message);
            render();
        } catch (error) {
            showAlert(error.message, true);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Render Main Form
    |--------------------------------------------------------------------------
    */

    function render() {
        const paid = cents(el('cr2-paid').value);
        const charge = cents(el('cr2-charge').value);

        const previewDue = Math.max(0, paid - charge);

        // Before saving, this is a preview only.
        // The backend remains authoritative.
        el('cr2-due').value = money(previewDue);

        const exists = !!settlement;

        const remaining = exists
            ? Math.max(0, cents(settlement.remaining))
            : 0;

        el('cr2-refund-total').value = exists
            ? settlement.refund_due
            : '0.00';

        el('cr2-already').value = exists
            ? settlement.already_refunded
            : '0.00';

        el('cr2-remaining').value = exists
            ? settlement.remaining
            : '0.00';

        el('cr2-status').textContent = exists
            ? settlement.label
            : 'Assessment Pending';

        const paymentLocked =
            !exists ||
            remaining === 0 ||
            legacyBlocked ||
            loading ||
            assessmentSaving ||
            paymentSaving ||
            assessmentEditing;

        if (exists && remaining === 0 && !legacyBlocked) {
            showAlert('Customer refund completed. Refund payments are locked. Contact Accounts if a formal correction is required.');
        }

        const amountInput = el('cr2-amount');

        amountInput.max = money(remaining);
        amountInput.disabled = paymentLocked;

        setDisabled('cr2-pay-btn', paymentLocked);

        const payButton = el('cr2-pay-btn');

        if (payButton) {
            payButton.textContent = paymentSaving
                ? 'Recording...'
                : 'Save Refund';
        }

        setAssessmentEditMode(assessmentEditing);
        renderHistory();
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Selected Ride
    |--------------------------------------------------------------------------
    */

    function resolveRide() {
        const explicitRideId = root.dataset.rideId;

        if (explicitRideId) {
            return explicitRideId;
        }

        const section = document.getElementById(
            'refund-information-section'
        );

        const withinCard = section
            ? section.closest('[data-ride-card]')
            : null;

        if (withinCard) {
            return withinCard.getAttribute('data-ride-card');
        }

        const expanded = document.querySelector(
            '.rs-status-toggle[aria-expanded="true"]'
        );

        return expanded
            ? expanded.getAttribute('data-ride-id')
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Load Saved Assessment
    |--------------------------------------------------------------------------
    */

    async function load() {
        const nextRideId = resolveRide();

        if (!nextRideId) {
            showAlert(
                'Unable to identify the selected ride. Reopen Ride Status.',
                true
            );
            return;
        }

        if (assessmentSaving || paymentSaving) {
            return;
        }

        const requestSequence = ++loadSequence;

        rideId = nextRideId;
        settlement = null;
        legacyBlocked = false;
        assessmentEditing = false;
        activeRequestId = null;
        loading = true;

        el('cr2-paid').value = '0.00';
        el('cr2-charge').value = '0.00';
        el('cr2-reason').value = '';
        el('cr2-amount').value = '';

        render();
        showAlert('Loading customer refund...');

        try {
            const body = await call(url('read'));

            // Ignore results from an older ride request.
            if (requestSequence !== loadSequence) {
                return;
            }

            legacyBlocked = !!body.legacy_blocked;
            canCorrectPayment = !!body.can_correct_payment;
            settlement = body.settlement || null;

            el('cr2-paid').value =
                body.approved_paid ?? '0.00';

            restoreAssessmentValues();

            loading = false;
            render();

            showAlert(
                body.message || '',
                legacyBlocked
            );

        } catch (error) {
            if (requestSequence !== loadSequence) {
                return;
            }

            loading = false;
            render();
            showAlert(error.message, true);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Open Customer Refund Form
    |--------------------------------------------------------------------------
    */

    document.addEventListener('click', event => {
        if (event.target.closest('#refund-note-btn')) {
            setTimeout(load, 0);
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Recalculate Cancellation Amount
    |--------------------------------------------------------------------------
    */

    el('cr2-charge').addEventListener('input', () => {
        if (legacyBlocked || loading) {
            return;
        }

        render();
    });

    /*
    |--------------------------------------------------------------------------
    | Edit Saved Cancellation
    |--------------------------------------------------------------------------
    */

    const editButton = el('cr2-edit-assessment');

    if (editButton) {
        editButton.addEventListener('click', () => {
            if (!canEditAssessment()) {
                showAlert(
                    'This cancellation assessment cannot be edited.',
                    true
                );
                return;
            }

            setAssessmentEditMode(true);
            render();

            el('cr2-charge').focus();

            showAlert(
                'Editing cancellation assessment. Save changes or cancel.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Cancel Editing
    |--------------------------------------------------------------------------
    */

    const cancelEditButton = el('cr2-cancel-assessment-edit');

    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', () => {
            if (assessmentSaving) {
                return;
            }

            restoreAssessmentValues();

            assessmentEditing = false;

            render();

            showAlert(
                'Changes discarded. Original cancellation values restored.'
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Save / Update Cancellation Assessment
    |--------------------------------------------------------------------------
    */

    el('cr2-assessment-form').addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            if (
                !rideId ||
                legacyBlocked ||
                loading ||
                assessmentSaving ||
                paymentSaving
            ) {
                return;
            }

            if (settlement && !assessmentEditing) {
                showAlert(
                    'Click Edit Cancellation before changing the saved assessment.',
                    true
                );
                return;
            }

            if (settlement && !canEditAssessment()) {
                showAlert(
                    'This assessment is locked because refund payments exist or the assessment is finalized.',
                    true
                );
                return;
            }

            const paid = cents(el('cr2-paid').value);
            const chargeValue = Number(el('cr2-charge').value);
            const reason = el('cr2-reason').value.trim();

            if (
                !Number.isFinite(chargeValue) ||
                chargeValue < 0 ||
                cents(chargeValue) > paid
            ) {
                showAlert(
                    'Cancellation charge must be between ₹0 and the approved payment amount.',
                    true
                );
                return;
            }

            if (!reason) {
                showAlert(
                    'Please enter the cancellation reason.',
                    true
                );
                return;
            }

            assessmentSaving = true;
            render();

            try {
                const form = new FormData(event.currentTarget);

                const body = await call(
                    url('assess'),
                    {
                        method: 'POST',
                        body: form
                    }
                );

                if (!body.settlement) {
                    throw new Error(
                        'The server did not return the saved assessment.'
                    );
                }

                settlement = body.settlement;
                assessmentEditing = false;

                restoreAssessmentValues();

                showAlert(
                    'Customer cancellation assessment saved successfully.'
                );

            } catch (error) {
                showAlert(error.message, true);

            } finally {
                assessmentSaving = false;
                render();
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | Generate Idempotency Key
    |--------------------------------------------------------------------------
    */

    function makeRequestId() {
        if (
            window.crypto &&
            typeof window.crypto.randomUUID === 'function'
        ) {
            return window.crypto.randomUUID();
        }

        if (
            window.crypto &&
            typeof window.crypto.getRandomValues === 'function'
        ) {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);

            bytes[6] = (bytes[6] & 0x0f) | 0x40;
            bytes[8] = (bytes[8] & 0x3f) | 0x80;

            const hex = Array.from(
                bytes,
                byte => byte.toString(16).padStart(2, '0')
            ).join('');

            return [
                hex.slice(0, 8),
                hex.slice(8, 12),
                hex.slice(12, 16),
                hex.slice(16, 20),
                hex.slice(20)
            ].join('-');
        }

        throw new Error(
            'Secure request ID generation is unavailable.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Record Actual Customer Refund
    |--------------------------------------------------------------------------
    */

    el('cr2-payment-form').addEventListener(
        'submit',
        async event => {
            event.preventDefault();

            if (
                !rideId ||
                !settlement ||
                legacyBlocked ||
                loading ||
                assessmentSaving ||
                paymentSaving ||
                assessmentEditing
            ) {
                return;
            }

            const amount = Number(el('cr2-amount').value);
            const remaining = cents(settlement.remaining);

            if (
                !Number.isFinite(amount) ||
                cents(amount) <= 0 ||
                cents(amount) > remaining
            ) {
                showAlert(
                    'Refund amount must be greater than zero and cannot exceed the remaining balance.',
                    true
                );
                return;
            }

            /*
             * Reuse the same request ID after a failed or
             * uncertain response. Do not create a new one
             * automatically on retry.
             */
            try {
                if (!activeRequestId) {
                    activeRequestId = makeRequestId();
                }
            } catch (error) {
                showAlert(error.message, true);
                return;
            }


            // IMPORTANT:
            // Create FormData before render() disables the refund amount input.
            const form = new FormData(event.currentTarget);

            form.set('amount', el('cr2-amount').value);
            form.set('request_id', activeRequestId);

            paymentSaving = true;
            render();

            try {
                const body = await call(
                    url('pay'),
                    {
                        method: 'POST',
                        body: form
                    }
                );

                if (!body.settlement) {
                    throw new Error(
                        'Payment response could not be verified. Check refund history before retrying.'
                    );
                }

                settlement = body.settlement;

                activeRequestId = null;

                event.currentTarget.reset();

                showAlert(
                    body.duplicate
                        ? 'Previously recorded refund payment confirmed.'
                        : 'Customer refund payment recorded successfully.'
                );

            } catch (error) {
                showAlert(
                    error.message +
                    ' Verify payment history before retrying. Do not use a different request ID for the same payment.',
                    true
                );

            } finally {
                paymentSaving = false;
                render();
            }

        }
    );

    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */

    render();

})();
