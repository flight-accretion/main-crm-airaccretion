(() => {
    'use strict';
    function init() {
        const form = document.querySelector('[data-service-form]');
        if (form) initForm(form);
        const catalog = document.querySelector('[data-service-catalog]');
        if (catalog) initCatalog(catalog);
    }

    function initForm(form) {
        const field = name => form.elements.namedItem(name);
        const currency = form.dataset.currency || '';
        const money = value => currency + Number(value).toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        const amountInput = field('service_amount');
        const feesInput = field('fees_percent');
        const checks = [...form.querySelectorAll('[name="extra_service_ids[]"]')];
        const set = (selector, text) => {
            const element = form.querySelector(selector);
            if (element.textContent !== text) element.textContent = text;
        };
        function update() {
            const amount = Math.max(0, Number(amountInput.value) || 0);
            const percent = Math.min(100, Math.max(0, Number(feesInput.value) || 0));
            const fees = Math.round((amount * percent / 100 + Number.EPSILON) * 100) / 100;
            const net = Math.max(0, Math.round((amount - fees + Number.EPSILON) * 100) / 100);
            const selected = checks.filter(box => box.checked);
            const extra = selected.reduce((sum, box) => sum + Number(box.dataset.amount || 0), 0);
            set('[data-service-preview-name]', field('service').value.trim() || 'Service name');
            set('[data-service-preview-amount]', money(amount));
            set('[data-service-preview-fees-percent]', `(${percent}%)`);
            set('[data-service-preview-fees]', `− ${money(fees)}`);
            set('[data-service-preview-net]', money(net));
            set('[data-service-preview-extras]', `${selected.length} attached · ${money(extra)}`);
            set('[data-service-preview-total]', money(amount + extra));
            set('[data-service-extras-total]', money(extra));
            set('[data-service-selected-count]', ` · ${selected.length} selected`);
            set('#amount_after_fees_preview', `${money(amount)} − ${percent}% fees = ${money(net)}`);
        }
        form.addEventListener('input', update);
        form.addEventListener('change', update);
        const options = [...form.querySelectorAll('[data-service-extra-option]')];
        form.querySelector('[data-service-extra-search]').addEventListener('input', event => {
            const term = event.target.value.trim().toLowerCase();
            options.forEach(option => { option.hidden = !option.dataset.search.includes(term); });
            form.querySelector('[data-service-extra-empty]').hidden = !options.length || options.some(option => !option.hidden);
        });
        const terms = field('terms_and_conditions');
        const editor = document.getElementById('editor');
        if (window.quill?.root && editor) {
            window.quill.clipboard.dangerouslyPasteHTML(terms.value || '');
            terms.hidden = true;
            window.quill.on('text-change', () => { terms.value = window.quill.root.innerHTML; });
        } else if (editor) {
            editor.hidden = true;
        }
        form.addEventListener('submit', () => {
            if (window.quill?.root) terms.value = window.quill.root.innerHTML;
        });
        if (window.jQuery?.fn.select2) {
            const product = window.jQuery('#product_id');
            if (!product.hasClass('select2-hidden-accessible')) product.select2({width: '100%', placeholder: 'Select product', allowClear: true});
        }
        update();
        if (new URLSearchParams(location.search).get('focus') === 'price') amountInput.focus();
    }

    function initCatalog(root) {
        let page = 1;
        const rows = [...root.querySelectorAll('[data-service-row]')];
        const sizeSelect = root.querySelector('[data-service-page-size]');
        const prev = root.querySelector('[data-service-prev]');
        const next = root.querySelector('[data-service-next]');
        function paginate() {
            const size = Number(sizeSelect.value);
            const pages = Math.max(1, Math.ceil(rows.length / size));
            page = Math.max(1, Math.min(page, pages));
            rows.forEach((row, index) => { row.hidden = index < (page - 1) * size || index >= page * size; });
            prev.disabled = page === 1; next.disabled = page === pages;
            root.querySelector('[data-service-page]').textContent = `${page} / ${pages}`;
            root.querySelector('[data-service-range]').textContent = rows.length ? `${(page - 1) * size + 1}–${Math.min(page * size, rows.length)} of ${rows.length} services` : '0 services';
        }
        prev.addEventListener('click', () => { page--; paginate(); });
        next.addEventListener('click', () => { page++; paginate(); });
        sizeSelect.addEventListener('change', () => { page = 1; paginate(); });
        paginate();

        const viewDialog = root.querySelector('#service-view-dialog');
        const viewContent = root.querySelector('[data-service-view-content]');
        const confirmDialog = root.querySelector('#service-confirm-dialog');
        const confirmButton = root.querySelector('[data-service-confirm]');
        const confirmError = root.querySelector('[data-service-confirm-error]');
        let request = null, sequence = 0, pending = null, busy = false;
        let opener = null;
        function close(dialog) { if (!busy || dialog !== confirmDialog) dialog.close(); }
        root.querySelectorAll('[data-service-close]').forEach(button => button.addEventListener('click', () => close(button.closest('dialog'))));
        [viewDialog, confirmDialog].forEach(dialog => {
            dialog.addEventListener('click', event => { if (event.target === dialog) {
                const bounds = dialog.getBoundingClientRect();
                if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close(dialog);
            }});
            dialog.addEventListener('close', () => { opener?.focus(); });
        });
        viewDialog.addEventListener('close', () => { sequence++; request?.abort(); });
        confirmDialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
        root.addEventListener('click', async event => {
            const view = event.target.closest('[data-service-view]');
            if (view) {
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
                event.preventDefault(); opener = view;
                const token = ++sequence;
                request?.abort(); request = new AbortController();
                viewContent.textContent = 'Loading service…';
                if (!viewDialog.open) viewDialog.showModal();
                try {
                    const response = await fetch(view.href, {signal: request.signal, headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
                    const data = await response.json();
                    if (token !== sequence) return;
                    if (!response.ok || !data.success || data.id !== view.closest('[data-service-row]').dataset.serviceRow || typeof data.html !== 'string') throw new Error('Unable to load this service. Please try again.');
                    viewContent.innerHTML = data.html;
                } catch (error) { if (token === sequence && error.name !== 'AbortError') viewContent.textContent = error.message || 'Unable to load service.'; }
                return;
            }
            const action = event.target.closest('[data-service-toggle], [data-service-delete]');
            if (!action || busy) return;
            opener = action;
            pending = {url: action.dataset.url, deleting: action.hasAttribute('data-service-delete')};
            const verb = pending.deleting ? 'delete' : (Number(action.dataset.status) ? 'deactivate' : 'activate');
            root.querySelector('[data-service-confirm-message]').textContent = `Are you sure you want to ${verb} “${action.dataset.name}”?`;
            confirmButton.textContent = verb.charAt(0).toUpperCase() + verb.slice(1);
            confirmError.textContent = '';
            confirmDialog.showModal();
        });
        confirmButton.addEventListener('click', async () => {
            if (!pending || busy) return;
            const action = {...pending};
            busy = true; confirmButton.disabled = true;
            confirmDialog.querySelectorAll('[data-service-close]').forEach(button => { button.disabled = true; });
            try {
                const response = await fetch(action.url, {method: 'POST', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({_token: root.dataset.csrf, _method: action.deleting ? 'DELETE' : 'PATCH'})});
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message || 'Unable to save this change.');
                // Reload current filters so summary counts and pagination agree with persisted state.
                location.reload();
            } catch (error) { confirmError.textContent = error.message || 'Unable to save this change. Please try again.'; }
            finally {
                busy = false; confirmButton.disabled = false;
                confirmDialog.querySelectorAll('[data-service-close]').forEach(button => { button.disabled = false; });
            }
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
