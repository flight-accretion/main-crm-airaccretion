<div
    id="kpiMetricScoreModal"
    style="
        display:none;
        position:fixed;
        inset:0;
        z-index:99999;
        background:rgba(0,0,0,.45);
        padding:24px;
        overflow:auto;
        align-items:center;
        justify-content:center;
    "
>

    <div
        style="
            background:#fff;
            width:100%;
            max-width:1000px;
            border-radius:10px;
            box-shadow:0 20px 50px rgba(0,0,0,.20);
        "
    >

        <div class="flex items-center justify-between p-4 border-b">

            <div>
                <h5
                    id="kpiMetricModalTitle"
                    class="text-lg font-semibold mb-0"
                >
                    How to Improve Score
                </h5>

                <div
                    id="kpiMetricModalDescription"
                    class="text-sm text-gray-500 mt-1"
                ></div>
            </div>

            <button
                type="button"
                id="kpiMetricModalClose"
                class="ti-btn ti-btn-light ti-btn-sm"
            >
                Close
            </button>

        </div>


        {{-- ALL KPI IMPROVEMENTS --}}
        <div
            id="kpiAllImprovementMode"
            class="p-4"
        >

            <div
                id="kpiEmployeeSelectWrapper"
                class="mb-4"
                style="display:none;"
            >

                <label class="form-label">
                    Employee
                </label>

                <select
                    id="kpiImproveEmployee"
                    class="ti-form-select"
                ></select>

            </div>


            <div class="grid grid-cols-12 gap-4 mb-4">

                <div class="col-span-12 md:col-span-4">

                    <div class="border rounded p-4">

                        <div class="text-sm text-gray-500">
                            Employee
                        </div>

                        <div
                            id="kpiImproveEmployeeName"
                            class="text-lg font-semibold mt-1"
                        >
                            -
                        </div>

                    </div>

                </div>


                <div class="col-span-12 md:col-span-4">

                    <div class="border rounded p-4">

                        <div class="text-sm text-gray-500">
                            Overall KPI
                        </div>

                        <div
                            id="kpiImproveOverall"
                            class="text-2xl font-semibold mt-1"
                        >
                            1/5
                        </div>

                    </div>

                </div>

            </div>


            <div class="table-responsive">

                <table class="table whitespace-nowrap min-w-full">

                    <thead>
                        <tr>
                            <th>KPI</th>
                            <th>Current Result</th>
                            <th>Score</th>
                            <th>Next Score</th>
                            <th>How to Improve</th>
                        </tr>
                    </thead>

                    <tbody id="kpiAllImprovementRows"></tbody>

                </table>

            </div>

        </div>


        {{-- SINGLE KPI --}}
        <div
            id="kpiSingleImprovementMode"
            class="p-4"
            style="display:none;"
        >

            <div class="grid grid-cols-12 gap-4 mb-4">

                <div class="md:col-span-4 col-span-12">
                    <div class="border rounded p-3">
                        <div class="text-sm text-gray-500">
                            Current KPI Score
                        </div>
                        <div
                            id="kpiMetricModalScore"
                            class="text-2xl font-semibold"
                        >
                            -
                        </div>
                    </div>
                </div>

                <div class="md:col-span-4 col-span-12">
                    <div class="border rounded p-3">
                        <div class="text-sm text-gray-500">
                            Weightage
                        </div>
                        <div
                            id="kpiMetricModalWeight"
                            class="text-xl font-semibold"
                        >
                            -
                        </div>
                    </div>
                </div>

                <div class="md:col-span-4 col-span-12">
                    <div class="border rounded p-3">
                        <div class="text-sm text-gray-500">
                            Current Result
                        </div>
                        <div
                            id="kpiMetricModalActual"
                            class="font-semibold"
                        >
                            -
                        </div>
                    </div>
                </div>

            </div>


            <div class="border rounded p-4 mb-4">

                <div class="font-semibold mb-2">
                    How to Improve
                </div>

                <div id="kpiMetricModalImprovement">
                    -
                </div>

            </div>


            <div
                id="kpiMetricNextBlock"
                class="border rounded p-4 mb-4"
            >

                <div class="font-semibold mb-2">
                    Next KPI Score
                </div>

                <div id="kpiMetricModalNext">
                    -
                </div>

            </div>


            <div>

                <div class="font-semibold mb-2">
                    CRM Evidence
                </div>

                <div
                    id="kpiMetricEvidence"
                    class="table-responsive"
                ></div>

            </div>

        </div>

    </div>

</div>


<script>
document.addEventListener('DOMContentLoaded', function () {

    const modal =
        document.getElementById('kpiMetricScoreModal');

    const closeButton =
        document.getElementById('kpiMetricModalClose');

    const improveButton =
        document.getElementById('kpiImproveAllButton');

    const allMode =
        document.getElementById('kpiAllImprovementMode');

    const singleMode =
        document.getElementById('kpiSingleImprovementMode');

    const employeeSelect =
        document.getElementById('kpiImproveEmployee');

    const employeeWrapper =
        document.getElementById('kpiEmployeeSelectWrapper');

    const employeeName =
        document.getElementById('kpiImproveEmployeeName');

    const overall =
        document.getElementById('kpiImproveOverall');

    const rows =
        document.getElementById('kpiAllImprovementRows');

    const dataElement =
        document.getElementById('kpiImprovementData');

    const title =
        document.getElementById('kpiMetricModalTitle');

    const description =
        document.getElementById('kpiMetricModalDescription');

    const score =
        document.getElementById('kpiMetricModalScore');

    const weight =
        document.getElementById('kpiMetricModalWeight');

    const actual =
        document.getElementById('kpiMetricModalActual');

    const improvement =
        document.getElementById('kpiMetricModalImprovement');

    const nextBlock =
        document.getElementById('kpiMetricNextBlock');

    const next =
        document.getElementById('kpiMetricModalNext');

    const evidence =
        document.getElementById('kpiMetricEvidence');


    let team = [];

    try {
        team = JSON.parse(
            dataElement?.textContent || '[]'
        );
    } catch (error) {
        team = [];
    }


    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }


    function openModal() {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }


    function closeModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }


    function renderEmployee(employee) {

        if (!employee) {
            rows.innerHTML =
                '<tr><td colspan="5">No KPI data available.</td></tr>';
            return;
        }

        employeeName.textContent =
            employee.name || '-';

        overall.textContent =
            Number(employee.overall_score || 1) + '/5';

        const metrics =
            Array.isArray(employee.metrics)
                ? employee.metrics
                : [];

        rows.innerHTML =
            metrics.map(function (metric) {

                const nextScore =
                    metric.next_score
                        ? metric.next_score + '/5'
                        : 'Maintain';

                return `
                    <tr>
                        <td>
                            ${escapeHtml(metric.name || '-')}
                        </td>

                        <td>
                            ${escapeHtml(metric.display_actual || '0')}
                        </td>

                        <td>
                            ${Number(metric.score || 1)}/5
                        </td>

                        <td>
                            ${escapeHtml(nextScore)}
                        </td>

                        <td class="whitespace-normal">
                            ${escapeHtml(metric.improvement_line || '-')}
                        </td>
                    </tr>
                `;

            }).join('');
    }


    function openAll() {

        title.textContent =
            'How to Improve Score';

        description.textContent =
            'Laravel-calculated KPI guidance based on CRM data.';

        singleMode.style.display = 'none';
        allMode.style.display = 'block';

        employeeSelect.innerHTML = '';

        team.forEach(function (employee) {

            const option =
                document.createElement('option');

            option.value = employee.id;
            option.textContent = employee.name;

            employeeSelect.appendChild(option);
        });

        employeeWrapper.style.display =
            team.length > 1
                ? 'block'
                : 'none';

        renderEmployee(team[0] || null);

        openModal();
    }


    improveButton?.addEventListener(
        'click',
        openAll
    );


    employeeSelect?.addEventListener(
        'change',
        function () {

            const selected =
                team.find(function (employee) {

                    return String(employee.id)
                        === String(employeeSelect.value);

                });

            renderEmployee(selected || null);
        }
    );


    document.addEventListener(
        'click',
        function (event) {

            const button =
                event.target.closest('.js-kpi-score');

            if (!button) {
                return;
            }

            let data = {};

            try {
                data = JSON.parse(
                    button.dataset.kpi || '{}'
                );
            } catch (error) {
                return;
            }

            allMode.style.display = 'none';
            singleMode.style.display = 'block';

            title.textContent =
                data.name || 'KPI Detail';

            description.textContent =
                data.description || '';

            score.textContent =
                Number(data.score || 1) + '/5';

            weight.textContent =
                Number(data.weightage || 0) + '%';

            actual.textContent =
                data.display_actual || '0';

            improvement.textContent =
                data.improvement_line || '-';

            if (data.next_score) {

                nextBlock.style.display = 'block';

                next.textContent =
                    data.next_score + '/5';

            } else {

                nextBlock.style.display = 'none';
                next.textContent = '';

            }

            renderEvidence(
                data.evidence || {}
            );

            openModal();
        }
    );


    function renderEvidence(data) {

        evidence.innerHTML = '';

        const entries =
            Object.entries(data || {})
                .filter(function ([key, value]) {

                    return key !== 'note'
                        && value !== null
                        && value !== undefined
                        && typeof value !== 'object';

                });

        if (!entries.length) {

            evidence.textContent =
                'No additional CRM evidence available.';

            return;
        }

        const table =
            document.createElement('table');

        table.className =
            'table table-sm mb-0';

        const tbody =
            document.createElement('tbody');

        entries.forEach(function ([key, value]) {

            const tr =
                document.createElement('tr');

            const th =
                document.createElement('th');

            const td =
                document.createElement('td');

            th.textContent =
                key
                    .replace(/_/g, ' ')
                    .replace(/\b\w/g, function (c) {
                        return c.toUpperCase();
                    });

            td.textContent =
                String(value);

            tr.appendChild(th);
            tr.appendChild(td);
            tbody.appendChild(tr);
        });

        table.appendChild(tbody);
        evidence.appendChild(table);
    }


    closeButton?.addEventListener(
        'click',
        closeModal
    );


    modal.addEventListener(
        'click',
        function (event) {

            if (event.target === modal) {
                closeModal();
            }

        }
    );


    document.addEventListener(
        'keydown',
        function (event) {

            if (event.key === 'Escape') {
                closeModal();
            }

        }
    );

});
</script>