document.addEventListener('DOMContentLoaded', () => {
    const root = document.querySelector('[data-manual-kpi]');
    if (!root) return;
    const metric = root.querySelector('[data-manual-metric]');
    const value = root.querySelector('[data-manual-value]');
    const bands = root.querySelector('[data-manual-bands]');
    const output = root.querySelector('[data-manual-score]');
    const update = () => {
        bands.replaceChildren(); output.textContent = '';
        const option = metric.selectedOptions[0];
        if (!option?.dataset.config) return;
        const config = JSON.parse(option.dataset.config);
        const rating = config.type === 'manual_rating';
        value.min = rating ? '1' : '0'; value.max = rating ? '5' : '100'; value.step = rating ? '1' : '0.01';
        for (let score = 5; score >= 1; score--) {
            const chip = document.createElement('span');
            const threshold = config.rules?.[score];
            chip.textContent = rating ? `${score}/5` : `${score}/5 ${threshold == null ? '-' : (config.direction === 'lower_better' ? '\u2264 ' : '\u2265 ') + threshold + (config.direction === 'lower_better' ? '' : '%')}`;
            bands.append(chip);
        }
        if (value.value === '' || !value.validity.valid) return;
        const actual = Number(value.value);
        let score = 1;
        if (rating) score = Math.min(5, Math.max(1, Math.round(actual)));
        else {
            const achieved = Number(config.target) > 0 ? actual / Number(config.target) * 100 : 0;
            for (let band = 5; band >= 1; band--) {
                if (config.direction === 'lower_better' ? actual <= Number(config.rules?.[band] ?? Infinity) : achieved >= Number(config.rules?.[band] ?? 0)) { score = band; break; }
            }
        }
        output.textContent = `${score}/5 \u00b7 Weight ${config.weight} \u00b7 Pending approval`;
    };
    metric.addEventListener('change', update); value.addEventListener('input', update); update();
    const dialog = root.querySelector('[data-reject-dialog]');
    if (!dialog) return;
    root.querySelectorAll('[data-reject-url]').forEach(button => button.addEventListener('click', () => {
        const form = dialog.querySelector('[data-reject-form]');
        form.reset(); form.action = button.dataset.rejectUrl;
        dialog.querySelector('[data-reject-description]').textContent = button.dataset.rejectPerson;
        dialog.showModal(); dialog.querySelector('textarea').focus();
    }));
    dialog.querySelectorAll('[data-close-reject]').forEach(button => button.addEventListener('click', () => dialog.close()));
});
