<div
    class="modal fade"
    id="kpiMetricScoreModal"
    tabindex="-1"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="kpiMetricModalTitle">KPI Detail</h5>
                    <div class="text-muted small" id="kpiMetricModalDescription"></div>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>
            </div>

            <div class="modal-body">
                <div class="grid grid-cols-12 gap-4 mb-4">
                    <div class="md:col-span-4 col-span-12">
                        <div class="border rounded p-3 h-full">
                            <div class="text-muted small">Current KPI Score</div>
                            <div class="fs-3 fw-bold" id="kpiMetricModalScore">-</div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-12">
                        <div class="border rounded p-3 h-full">
                            <div class="text-muted small">Weightage</div>
                            <div class="fs-5 fw-bold" id="kpiMetricModalWeight">-</div>
                        </div>
                    </div>

                    <div class="md:col-span-4 col-span-12">
                        <div class="border rounded p-3 h-full">
                            <div class="text-muted small">Current Result</div>
                            <div class="fw-bold" id="kpiMetricModalActual">-</div>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-4">
                    <div class="fw-bold mb-2">How to improve</div>
                    <div id="kpiMetricModalImprovement">-</div>
                </div>

                <div id="kpiMetricNextBlock" class="border rounded p-3 mb-4">
                    <div class="fw-bold mb-2">Next KPI Score</div>
                    <div id="kpiMetricModalNext">-</div>
                </div>

                <div>
                    <div class="fw-bold mb-2">CRM Evidence</div>
                    <div id="kpiMetricEvidence" class="table-responsive"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalElement = document.getElementById('kpiMetricScoreModal');
    if (!modalElement || typeof bootstrap === 'undefined') return;

    const modal = new bootstrap.Modal(modalElement);
    const title = document.getElementById('kpiMetricModalTitle');
    const description = document.getElementById('kpiMetricModalDescription');
    const score = document.getElementById('kpiMetricModalScore');
    const weight = document.getElementById('kpiMetricModalWeight');
    const actual = document.getElementById('kpiMetricModalActual');
    const improvement = document.getElementById('kpiMetricModalImprovement');
    const nextBlock = document.getElementById('kpiMetricNextBlock');
    const next = document.getElementById('kpiMetricModalNext');
    const evidence = document.getElementById('kpiMetricEvidence');

    function humanize(key) {
        return String(key)
            .replace(/_/g, ' ')
            .replace(/\b\w/g, function (char) {
                return char.toUpperCase();
            });
    }

    function renderEvidence(data) {
        const entries = Object.entries(data || {})
            .filter(function ([key, value]) {
                return key !== 'note'
                    && value !== null
                    && value !== undefined
                    && typeof value !== 'object';
            });

        evidence.replaceChildren();

        if (!entries.length) {
            evidence.textContent = 'No additional evidence available.';
            return;
        }

        const table = document.createElement('table');
        table.className = 'table table-sm mb-0';
        const tbody = document.createElement('tbody');

        entries.forEach(function ([key, value]) {
            const row = document.createElement('tr');
            const label = document.createElement('th');
            const cell = document.createElement('td');

            label.textContent = humanize(key);
            cell.textContent = String(value);
            row.appendChild(label);
            row.appendChild(cell);
            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        evidence.replaceChildren(table);
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.js-kpi-score');
        if (!button) return;

        let data = {};

        try {
            data = JSON.parse(button.dataset.kpi || '{}');
        } catch (error) {
            return;
        }

        title.textContent = data.name || 'KPI Detail';
        description.textContent = data.description || '';
        score.textContent = `${Number(data.score || 1)}/5`;
        weight.textContent = `${Number(data.weightage || 0)}%`;
        actual.textContent = data.display_actual || '-';
        improvement.textContent = data.improvement_line || '-';

        if (data.next_score) {
            nextBlock.classList.remove('d-none');
            const threshold = data.next_threshold !== null && data.next_threshold !== undefined
                ? ` at threshold ${data.next_threshold}`
                : '';
            next.textContent = `${data.next_score}/5${threshold}`;
        } else {
            nextBlock.classList.add('d-none');
            next.textContent = '';
        }

        renderEvidence(data.evidence || {});
        modal.show();
    });
});
</script>
