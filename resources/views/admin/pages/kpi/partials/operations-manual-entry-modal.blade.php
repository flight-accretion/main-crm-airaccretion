<div
    id="operationsKpiManualEntryModal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center p-4"
    aria-hidden="true"
>
    <div
        class="absolute inset-0 bg-black/50"
        data-operations-kpi-manual-close
    ></div>

    <div
        class="relative w-full max-w-2xl rounded-lg bg-white shadow-xl dark:bg-bodybg"
        style="max-height:90vh;overflow:auto;"
    >
        <div class="flex items-center justify-between border-b p-4 dark:border-white/10">
            <div>
                <h5 class="text-lg font-semibold mb-0">
                    Enter Operations KPI
                </h5>
                <div class="text-xs text-gray-500 mt-1">
                    Manual monthly values only. Automatic CRM KPIs cannot be edited here.
                </div>
            </div>

            <button
                type="button"
                class="ti-btn ti-btn-light"
                data-operations-kpi-manual-close
            >
                Close
            </button>
        </div>

        <form
            method="POST"
            action="{{ route('admin.operations.kpi.manual-value') }}"
            class="p-4"
        >
            @csrf

            <input
                type="hidden"
                name="year"
                value="{{ (int) $filter['to']->year }}"
            >

            <input
                type="hidden"
                name="month"
                value="{{ (int) $filter['to']->month }}"
            >

            <div class="grid grid-cols-12 gap-4">
                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">
                        Operations Member
                    </label>

                    <select
                        name="user_id"
                        class="form-control"
                        required
                    >
                        <option value="">Select Operations Member</option>

                        @foreach(($operationsManualUsers ?? collect()) as $manualUser)
                            <option
                                value="{{ $manualUser->id }}"
                                {{ old('user_id') === (string) $manualUser->id ? 'selected' : '' }}
                            >
                                {{ $manualUser->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">
                        KPI
                    </label>

                    <select
                        name="metric_id"
                        class="form-control"
                        required
                    >
                        <option value="">Select Manual KPI</option>

                        @foreach(($operationsManualMetrics ?? collect()) as $manualMetric)
                            <option
                                value="{{ $manualMetric->id }}"
                                {{ old('metric_id') === (string) $manualMetric->id ? 'selected' : '' }}
                            >
                                {{ $manualMetric->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">
                        Achievement %
                    </label>

                    <input
                        type="number"
                        name="value"
                        class="form-control"
                        min="0"
                        max="100"
                        step="0.01"
                        value="{{ old('value') }}"
                        placeholder="Example: 95"
                        required
                    >
                </div>

                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">
                        Month
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        value="{{ $filter['to']->format('F Y') }}"
                        readonly
                    >
                </div>

                <div class="col-span-12">
                    <label class="ti-form-label">
                        Note / Evidence
                    </label>

                    <textarea
                        name="note"
                        class="form-control"
                        rows="4"
                        maxlength="2000"
                        placeholder="Add a short note or evidence for this KPI value."
                    >{{ old('note') }}</textarea>
                </div>
            </div>

            <div class="mt-4 rounded-md bg-gray-50 p-3 text-xs text-gray-600 dark:bg-black/10 dark:text-gray-300">
                The system converts the percentage into the configured 1-5 KPI score.
                Review, Image Collection, Payment, Voucher Timeliness, Service Timeliness
                and Attendance remain automatic and are not editable here.
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button
                    type="button"
                    class="ti-btn ti-btn-light"
                    data-operations-kpi-manual-close
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    class="ti-btn ti-btn-primary"
                >
                    Save KPI Value
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('operationsKpiManualEntryModal');
    const openButton = document.getElementById('operationsKpiManualEntryButton');

    if (!modal || !openButton) {
        return;
    }

    const openModal = function () {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
    };

    const closeModal = function () {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
    };

    openButton.addEventListener('click', openModal);

    modal
        .querySelectorAll('[data-operations-kpi-manual-close]')
        .forEach(function (element) {
            element.addEventListener('click', closeModal);
        });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    @if($errors->has('user_id') || $errors->has('metric_id') || $errors->has('value') || $errors->has('year') || $errors->has('month'))
        openModal();
    @endif
});
</script>
