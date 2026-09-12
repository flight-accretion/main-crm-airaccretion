@php
    /**
     * These variables are supplied by ClientController.
     *
     * @var \App\Models\LeadAiScore|null $latestAiScore
     * @var \App\Models\LeadAiScore|null $aiScore
     * @var bool|null $aiBookedClosed
     */

    $aiBookedClosed =
        (bool) ($aiBookedClosed ?? false);

    $aiScore =
        isset($latestAiScore)
            ? $latestAiScore
            : null;

    $aiMovement = null;

    if (
        $aiScore !== null &&
        $aiScore->score !== null &&
        $aiScore->previousScore !== null &&
        $aiScore->previousScore->score !== null
    ) {
        $aiMovement =
            (int) $aiScore->score
            -
            (int) $aiScore->previousScore->score;
    }

    $temperature = '';

    if (
        $aiScore !== null
    ) {
        $temperature =
            strtolower(
                (string) (
                    $aiScore->displayTemperature(
                        $leadAiScoringSetting ?? null
                    )
                    ?? ''
                )
            );
    }
@endphp


{{-- ==========================================================
     AI LEAD SCORE BUTTON
     ========================================================== --}}
<div class="flex items-center gap-2 flex-wrap">

    <button
        type="button"
        id="lead-ai-score-button"
        class="ti-btn ti-btn-sm
            @if($aiBookedClosed)
                ti-btn-success-full
            @elseif($temperature === 'hot')
                ti-btn-danger-full
            @elseif($temperature === 'neutral')
                ti-btn-warning-full
            @elseif($temperature === 'cold')
                ti-btn-info-full
            @else
                ti-btn-primary-full
            @endif"
            style="width: auto;"
        @if($aiBookedClosed) disabled @endif
    >

        @if($aiBookedClosed)

            BOOKED / CLOSED

        @elseif(!$aiScore)

            <i class="ri-sparkling-line me-1"></i>
            Analyse Lead

        @elseif(
            in_array(
                $aiScore->status,
                ['pending', 'processing'],
                true
            )
        )

            <i class="ri-loader-4-line me-1 animate-spin"></i>
            AI Score Analysing...

        @elseif($aiScore->status === 'failed')

            <i class="ri-error-warning-line me-1"></i>
            AI Score unavailable

        @elseif($aiScore->status === 'completed')

            @if($temperature === 'hot')
                🔥 HOT
            @elseif($temperature === 'cold')
                ❄ COLD
            @else
                ● NEUTRAL
            @endif

            {{ $aiScore->score }}/100

            @if($aiMovement !== null && $aiMovement !== 0)

                @if($aiMovement > 0)

                    ▲ +{{ $aiMovement }}

                @elseif($aiMovement < 0)

                    ▼ {{ $aiMovement }}

                @endif

            @endif

        @else

            <i class="ri-sparkling-line me-1"></i>
            AI Lead Score

        @endif

    </button>

</div>


{{-- ==========================================================
     AI LEAD SCORE MODAL
     ========================================================== --}}
<div
    id="lead-ai-score-modal"
    class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 p-4 overflow-y-auto"
    role="dialog"
    aria-modal="true"
    aria-labelledby="lead-ai-score-modal-title"
>
    <div
        class="min-h-full flex items-center justify-center px-4 py-8"
    >
    <div
        class="bg-white dark:bg-bodybg rounded-lg shadow-xl overflow-y-auto"
        style="border: 1px solid #dcdbdbe8;
                margin-top: 20px;
                margin-bottom: 20px;   
                width: 60vw;"
    >

        {{-- HEADER --}}
        <div
            class="flex justify-between items-center border-b dark:border-white/10 p-4"
        >

            <div>

                <h3
                    id="lead-ai-score-modal-title"
                    class="font-semibold text-lg"
                >
                    AI Lead Intelligence
                </h3>

                <p class="text-xs text-gray-500">
                    Based on genuine customer / sales follow-up conversation
                </p>

            </div>


            <button
                type="button"
                id="close-lead-ai-score-modal"
                class="text-xl"
                aria-label="Close"
            >
                <i class="ri-close-line"></i>
            </button>

        </div>


        {{-- BODY --}}
        <div class="p-5">

            {{-- BOOKED / CLOSED --}}
            <div
                id="ai-score-closed"
                class="hidden alert alert-success"
            >
                <p
                    id="ai-score-closed-message"
                >
                    Approved payment received. AI scoring has stopped.
                </p>
            </div>

            {{-- LOADING --}}
            <div
                id="ai-score-loading"
                class="hidden text-center py-10"
            >

                <i
                    class="ri-loader-4-line text-3xl animate-spin"
                ></i>

                <p class="mt-3 font-medium">
                    Analysing lead...
                </p>

                <p class="text-xs text-gray-500 mt-1">
                    Reviewing the latest eligible follow-up.
                </p>

            </div>


            {{-- ERROR --}}
            <div
                id="ai-score-error"
                class="hidden alert alert-danger"
            ></div>


            {{-- CONTENT --}}
            <div
                id="ai-score-content"
                class="hidden"
            >

                {{-- SCORE SUMMARY --}}
                <div
                    class="grid grid-cols-12 gap-4 mb-6"
                >

                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <p class="text-xs text-gray-500">
                            Temperature
                        </p>

                        <div
                            id="ai-temperature"
                            class="font-bold text-xl mt-1"
                        ></div>

                    </div>


                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <p class="text-xs text-gray-500">
                            Lead Score
                        </p>

                        <div
                            id="ai-score-value"
                            class="font-bold text-xl mt-1"
                        ></div>

                    </div>


                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <p class="text-xs text-gray-500">
                            AI Confidence
                        </p>

                        <div
                            id="ai-confidence"
                            class="font-bold text-xl mt-1"
                        ></div>

                    </div>

                </div>


                {{-- MOVEMENT --}}
                <div
                    id="ai-movement-section"
                    class="mb-6 hidden"
                >

                    <p class="text-xs text-gray-500">
                        Score Movement
                    </p>

                    <p
                        id="ai-movement"
                        class="font-semibold mt-1"
                    ></p>

                </div>


                {{-- SUMMARY --}}
                <!-- <div class="mb-6">

                    <h5 class="font-semibold mb-2">
                        AI Summary
                    </h5>

                    <ul
                        id="ai-summary-list"
                        class="list-disc ps-5 space-y-2"
                    ></ul>

                </div> -->


                {{-- SCORE REASON --}}
                <!-- <div class="mb-6">

                    <h5 class="font-semibold mb-2">
                        Why This Score
                    </h5>

                    <p
                        id="ai-score-reason"
                        class="text-gray-700 dark:text-white/70"
                    ></p>

                </div> -->


                {{-- SCORE CHANGE REASON --}}
                <!-- <div class="mb-6">

                    <h5 class="font-semibold mb-2">
                        Why Score Changed
                    </h5>

                    <p
                        id="ai-change-reason"
                        class="text-gray-700 dark:text-white/70"
                    ></p>

                </div> -->


                {{-- SALES COACHING --}}
                <div class="mb-6">

                    <h5 class="font-semibold mb-2">
                        Recommended Sales Coaching
                    </h5>

                    <div
                        id="ai-actions-list"
                        class="space-y-3"
                    ></div>

                </div>

                <div class="mb-6">

                    <p class="text-xs text-gray-500">
                        Next objective
                    </p>

                    <p
                        id="ai-next-commitment"
                        class="font-semibold mt-1"
                    ></p>

                </div>

                <div
                    id="ai-ghosting-signal"
                    class="hidden mb-6 p-3 rounded bg-warning/10"
                >
                    Customer Ghosting
                </div>


                {{-- META --}}
                <div
                    class="text-xs text-gray-500 space-y-1"
                >

                    <p>
                        Source follow-up:
                        <span
                            id="ai-source-date"
                        ></span>
                    </p>

                    <p>
                        Analysed:
                        <span
                            id="ai-analysed-date"
                        ></span>
                    </p>

                </div>

            </div>

        </div>


        {{-- FOOTER --}}
        <div
            class="flex justify-end gap-2 border-t dark:border-white/10 p-4"
        >

            @if(
                auth()->user()
                &&
                auth()->user()->isSuperAdmin()
            )

                <button
                    type="button"
                    id="retry-lead-ai-score"
                    class="ti-btn ti-btn-warning-full hidden"
                >
                    <i class="ri-refresh-line me-1"></i>
                    Retry Analysis
                </button>

            @endif


            <button
                type="button"
                id="close-lead-ai-score-modal-footer"
                class="ti-btn ti-btn-secondary-full"
            >
                Close
            </button>

        </div>

    </div>
</div>
</div>


@push('scripts')

<script>

(function () {

    /*
     * =========================================================
     * ELEMENTS
     * =========================================================
     */

    const button =
        document.getElementById(
            'lead-ai-score-button'
        );

    if (!button) {
        return;
    }


    const modal =
        document.getElementById(
            'lead-ai-score-modal'
        );

    const loading =
        document.getElementById(
            'ai-score-loading'
        );

    const closed =
        document.getElementById(
            'ai-score-closed'
        );

    const closedMessage =
        document.getElementById(
            'ai-score-closed-message'
        );

    const content =
        document.getElementById(
            'ai-score-content'
        );

    const errorBox =
        document.getElementById(
            'ai-score-error'
        );

    const retryButton =
        document.getElementById(
            'retry-lead-ai-score'
        );


    /*
     * =========================================================
     * ROUTES
     * =========================================================
     */

    const showUrl =
        @json(
            route(
                'admin.leads.ai-score.show',
                $lead->id
            )
        );


    const analyseUrl =
        @json(
            route(
                'admin.leads.ai-score.analyse',
                $lead->id
            )
        );


    const retryUrl =
        @json(
            route(
                'admin.leads.ai-score.retry',
                $lead->id
            )
        );


    const csrf =
        document
            .querySelector(
                'meta[name="csrf-token"]'
            )
            ?.getAttribute(
                'content'
            );


    /*
     * =========================================================
     * STATE
     * =========================================================
     */

    let pollingTimer =
        null;

    let requestInProgress =
        false;

    let pollCount =
        0;

    /*
     * 4 seconds × 75 attempts
     * = maximum 5 minutes.
     */
    const MAX_POLLS =
        75;


    /*
     * =========================================================
     * MODAL HELPERS
     * =========================================================
     */

    function openModal() {

        if (!modal) {
            return;
        }

        modal
            .classList
            .remove(
                'hidden'
            );

    }


    function closeModal() {

        if (!modal) {
            return;
        }

        modal
            .classList
            .add(
                'hidden'
            );

    }


    /*
     * =========================================================
     * DISPLAY STATES
     * =========================================================
     */

    function resetDisplay() {

        loading
            ?.classList
            .add(
                'hidden'
            );

        closed
            ?.classList
            .add(
                'hidden'
            );

        content
            ?.classList
            .add(
                'hidden'
            );

        errorBox
            ?.classList
            .add(
                'hidden'
            );

        if (errorBox) {
            errorBox.textContent =
                '';
        }

        retryButton
            ?.classList
            .add(
                'hidden'
            );

    }


    function setLoading() {

        resetDisplay();

        loading
            ?.classList
            .remove(
                'hidden'
            );

    }


    function showError(
        message,
        allowRetry = false
    ) {

        resetDisplay();

        if (errorBox) {

            errorBox.textContent =
                message
                ||
                'Unable to analyse this lead right now.';

            errorBox
                .classList
                .remove(
                    'hidden'
                );

        }

        if (
            allowRetry
            &&
            retryButton
        ) {
            retryButton
                .classList
                .remove(
                    'hidden'
                );
        }

    }


    /*
     * =========================================================
     * TEMPERATURE
     * =========================================================
     */

    function temperatureLabel(
        value
    ) {

        value =
            String(
                value || ''
            )
                .toLowerCase();


        if (
            value === 'hot'
        ) {
            return '🔥 HOT';
        }


        if (
            value === 'cold'
        ) {
            return '❄ COLD';
        }


        return '● NEUTRAL';

    }


    /*
     * =========================================================
     * BUTTON STATE
     * =========================================================
     */

    function setButtonAnalysing() {

        button.disabled =
            true;

        button.innerHTML =
            '<i class="ri-loader-4-line me-1 animate-spin"></i> AI Score Analysing...';

    }


    function setButtonFailed() {

        button.disabled =
            false;

        button.innerHTML =
            '<i class="ri-error-warning-line me-1"></i> AI Score unavailable';

    }


    function setButtonNoScore() {

        button.disabled =
            false;

        button.innerHTML =
            '<i class="ri-sparkling-line me-1"></i> Analyse Lead';

        button.style.setProperty(
        'width',
        'auto',
        'important'
    );

    }

    function setButtonBookedClosed() {

        button.disabled =
            true;

        button.textContent =
            'BOOKED / CLOSED';

    }


    function showBookedClosedState(
        message
    ) {

        resetDisplay();

        if (closedMessage) {
            closedMessage.textContent =
                message
                ||
                'Approved payment received. AI scoring has stopped.';
        }

        closed
            ?.classList
            .remove(
                'hidden'
            );

    }


    /*
     * =========================================================
     * RENDER COMPLETED SCORE
     * =========================================================
     */

    function renderCompleted(
        data
    ) {

        stopPolling();

        resetDisplay();

        content
            ?.classList
            .remove(
                'hidden'
            );


        /*
         * Temperature
         */
        const temperatureElement =
            document.getElementById(
                'ai-temperature'
            );

        if (temperatureElement) {

            temperatureElement.textContent =
                temperatureLabel(
                    data.temperature
                );

        }


        /*
         * Score
         */
        const scoreElement =
            document.getElementById(
                'ai-score-value'
            );

        if (scoreElement) {

            scoreElement.textContent =
                data.score !== null
                &&
                data.score !== undefined
                    ? `${data.score}/100`
                    : '-';

        }


        /*
         * Confidence
         */
        const confidenceElement =
            document.getElementById(
                'ai-confidence'
            );

        if (confidenceElement) {

            confidenceElement.textContent =
                data.confidence !== null
                &&
                data.confidence !== undefined
                    ? `${data.confidence}%`
                    : '-';

        }


        /*
         * Movement
         */
        const movementSection =
            document.getElementById(
                'ai-movement-section'
            );

        const movementElement =
            document.getElementById(
                'ai-movement'
            );


        if (
            data.movement !== null
            &&
            data.movement !== undefined
            &&
            data.previous_score !== null
            &&
            data.previous_score !== undefined
        ) {

            movementSection
                ?.classList
                .remove(
                    'hidden'
                );


            if (movementElement) {

                if (
                    data.movement > 0
                ) {

                    movementElement.textContent =
                        `${data.previous_score} → ${data.score}  ▲ +${data.movement}`;

                } else if (
                    data.movement < 0
                ) {

                    movementElement.textContent =
                        `${data.previous_score} → ${data.score}  ▼ ${data.movement}`;

                } else {

                    movementElement.textContent =
                        `${data.previous_score} -> ${data.score} (no change)`;

                }

            }

        } else {

            movementSection
                ?.classList
                .add(
                    'hidden'
                );

        }


        /*
         * Summary
         */
        const summaryList =
            document.getElementById(
                'ai-summary-list'
            );

        if (summaryList) {

            summaryList.innerHTML =
                '';

            const summary =
                Array.isArray(
                    data.summary
                )
                    ? data.summary
                    : [];


            summary
                .slice(
                    0,
                    2
                )
                .forEach(
                    function (
                        text
                    ) {

                        const li =
                            document.createElement(
                                'li'
                            );

                        /*
                         * Important:
                         * Never inject AI response
                         * using innerHTML.
                         */
                        li.textContent =
                            String(
                                text
                            );

                        summaryList
                            .appendChild(
                                li
                            );

                    }
                );


            if (
                summary.length === 0
            ) {

                const li =
                    document.createElement(
                        'li'
                    );

                li.textContent =
                    'No summary available.';

                summaryList
                    .appendChild(
                        li
                    );

            }

        }


        /*
         * Score reason
         */
        const scoreReason =
            document.getElementById(
                'ai-score-reason'
            );

        if (scoreReason) {

            scoreReason.textContent =
                data.score_reason
                || '-';

        }


        /*
         * Change reason
         */
        const changeReason =
            document.getElementById(
                'ai-change-reason'
            );

        if (changeReason) {

            changeReason.textContent =
                data.score_change_reason
                || '-';

        }


        /*
         * Sales coaching actions
         */
        const actionsContainer =
            document.getElementById(
                'ai-actions-list'
            );

        if (actionsContainer) {

            actionsContainer.innerHTML =
                '';

            const actions =
                Array.isArray(
                    data.actions
                )
                    ? data.actions.slice(
                        0,
                        3
                    )
                    : [];

            actions.forEach(
                function (
                    item,
                    index
                ) {
                    const wrapper =
                        document.createElement(
                            'div'
                        );

                    wrapper.className =
                        'p-3 border rounded dark:border-white/10';

                    const title =
                        document.createElement(
                            'div'
                        );

                    title.className =
                        'font-semibold';

                    const channel =
                        String(
                            item.channel
                            || ''
                        )
                            .toUpperCase();

                    title.textContent =
                        `${index + 1}. ${channel} - ${String(item.action || '')}`;

                    const script =
                        document.createElement(
                            'p'
                        );

                    script.className =
                        'mt-2 text-gray-700 dark:text-white/70';

                    script.textContent =
                        `Say/Send: "${String(item.script || '')}"`;

                    wrapper.appendChild(
                        title
                    );

                    wrapper.appendChild(
                        script
                    );

                    actionsContainer.appendChild(
                        wrapper
                    );
                }
            );

            if (actions.length === 0) {
                const empty =
                    document.createElement(
                        'p'
                    );

                empty.textContent =
                    'No coaching actions available.';

                actionsContainer.appendChild(
                    empty
                );
            }

        }

        const nextCommitment =
            document.getElementById(
                'ai-next-commitment'
            );

        if (nextCommitment) {
            nextCommitment.textContent =
                data.next_commitment
                || '-';
        }

        const ghostingSignal =
            document.getElementById(
                'ai-ghosting-signal'
            );

        ghostingSignal
            ?.classList
            .toggle(
                'hidden',
                !data.customer_ghosting
            );


        /*
         * Source date
         */
        const sourceDate =
            document.getElementById(
                'ai-source-date'
            );

        if (sourceDate) {

            sourceDate.textContent =
                data.source_followup_at
                || '-';

        }


        /*
         * Analysis date
         */
        const analysedDate =
            document.getElementById(
                'ai-analysed-date'
            );

        if (analysedDate) {

            analysedDate.textContent =
                data.analysed_at
                || '-';

        }


        /*
         * Header button
         */
        let movementText =
            '';


        if (
            data.movement !== null
            &&
            data.movement !== undefined
        ) {

            if (
                data.movement > 0
            ) {

                movementText =
                    ` ▲ +${data.movement}`;

            } else if (
                data.movement < 0
            ) {

                movementText =
                    ` ▼ ${data.movement}`;

            }

        }


        button.disabled =
            false;

        button.textContent =
            `${temperatureLabel(
                data.temperature
            )} ${data.score}/100${movementText}`;

    }


    /*
     * =========================================================
     * RENDER API RESPONSE
     * =========================================================
     */

    function render(
        data
    ) {

        if (!data) {
            stopPolling();

            showError(
                'Unable to load AI score.'
            );

            return;
        }

        if (
            data.status === 'closed'
            ||
            data.lifecycle === 'booked_closed'
        ) {

            stopPolling();

            setButtonBookedClosed();

            showBookedClosedState(
                data.message
                ||
                'Approved payment received. AI scoring has stopped.'
            );

            return;

        }

        if (
            data.has_score === false
        ) {

            stopPolling();

            setButtonNoScore();

            return;

        }


        if (
            data.status === 'pending'
            ||
            data.status === 'processing'
        ) {

            setLoading();

            setButtonAnalysing();

            startPolling();

            return;

        }


        if (
            data.status === 'failed'
        ) {

            stopPolling();

            setButtonFailed();

            showError(
                'AI analysis could not be completed. The lead and follow-up remain unchanged.',
                true
            );

            return;

        }

        if (
            data.status === 'skipped'
        ) {

            stopPolling();

            setButtonFailed();

            showError(
                'AI analysis was skipped because this lead is no longer eligible for scoring.',
                false
            );

            return;

        }


        if (
            data.status === 'completed'
        ) {

            renderCompleted(
                data
            );

            return;

        }


        stopPolling();

        showError(
            'Unknown AI analysis status.'
        );

    }


    /*
     * =========================================================
     * SAFE JSON RESPONSE
     * =========================================================
     */

    async function getJson(
        response
    ) {

        const contentType =
            response.headers.get(
                'content-type'
            )
            || '';


        if (
            !contentType.includes(
                'application/json'
            )
        ) {

            throw new Error(
                'Server returned an invalid response.'
            );

        }


        return await response.json();

    }


    /*
     * =========================================================
     * FETCH CURRENT SCORE
     * =========================================================
     */

    async function fetchScore() {

        if (
            requestInProgress
        ) {
            return null;
        }


        requestInProgress =
            true;


        try {

            const response =
                await fetch(
                    showUrl,
                    {
                        method:
                            'GET',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest'
                        },

                        credentials:
                            'same-origin'
                    }
                );


            const data =
                await getJson(
                    response
                );


            if (
                !response.ok
            ) {

                throw new Error(
                    data.message
                    ||
                    'Unable to load AI score.'
                );

            }


            render(
                data
            );


            return data;

        } catch (
            error
        ) {

            stopPolling();

            button.disabled =
                false;

            showError(
                error.message
                ||
                'Unable to load AI analysis.'
            );


            return null;

        } finally {

            requestInProgress =
                false;

        }

    }


    /*
     * =========================================================
     * START ANALYSIS
     * =========================================================
     */

    async function analyse() {

        if (
            requestInProgress
        ) {
            return;
        }


        requestInProgress =
            true;

        openModal();

        setLoading();

        setButtonAnalysing();


        try {

            const response =
                await fetch(
                    analyseUrl,
                    {
                        method:
                            'POST',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',

                            'X-CSRF-TOKEN':
                                csrf
                        },

                        credentials:
                            'same-origin'
                    }
                );


            const data =
                await getJson(
                    response
                );


            if (
                !response.ok
            ) {

                throw new Error(
                    data.message
                    ||
                    'Unable to analyse lead.'
                );

            }


            startPolling();

            /*
             * Fetch once quickly so if queue uses
             * sync driver during local testing,
             * result appears immediately.
             */
            setTimeout(
                fetchScore,
                800
            );

        } catch (
            error
        ) {

            stopPolling();

            setButtonNoScore();

            showError(
                error.message
                ||
                'Unable to analyse lead right now.'
            );

        } finally {

            requestInProgress =
                false;

        }

    }


    /*
     * =========================================================
     * RETRY FAILED ANALYSIS
     * =========================================================
     */

    async function retry() {

        if (
            requestInProgress
        ) {
            return;
        }


        requestInProgress =
            true;

        setLoading();

        setButtonAnalysing();


        try {

            const response =
                await fetch(
                    retryUrl,
                    {
                        method:
                            'POST',

                        headers: {
                            'Accept':
                                'application/json',

                            'X-Requested-With':
                                'XMLHttpRequest',

                            'X-CSRF-TOKEN':
                                csrf
                        },

                        credentials:
                            'same-origin'
                    }
                );


            const data =
                await getJson(
                    response
                );


            if (
                !response.ok
            ) {

                throw new Error(
                    data.message
                    ||
                    'Unable to retry analysis.'
                );

            }


            retryButton
                ?.classList
                .add(
                    'hidden'
                );


            startPolling();


            setTimeout(
                fetchScore,
                800
            );

        } catch (
            error
        ) {

            setButtonFailed();

            showError(
                error.message
                ||
                'Unable to retry AI analysis.',
                true
            );

        } finally {

            requestInProgress =
                false;

        }

    }


    /*
     * =========================================================
     * POLLING
     * =========================================================
     */

    function startPolling() {

        if (
            pollingTimer
        ) {
            return;
        }


        pollCount =
            0;


        pollingTimer =
            setInterval(
                async function () {

                    pollCount++;


                    if (
                        pollCount >
                        MAX_POLLS
                    ) {

                        stopPolling();

                        button.disabled =
                            false;

                        showError(
                            'AI analysis is taking longer than expected. You can close this window and check again shortly.'
                        );

                        return;

                    }


                    await fetchScore();

                },
                4000
            );

    }


    function stopPolling() {

        if (
            !pollingTimer
        ) {
            return;
        }


        clearInterval(
            pollingTimer
        );


        pollingTimer =
            null;

        pollCount =
            0;

    }


    /*
     * =========================================================
     * MAIN BUTTON
     * =========================================================
     */

    button.addEventListener(
        'click',
        async function () {

            if (
                button.disabled
            ) {
                return;
            }


            openModal();

            setLoading();


            const data =
                await fetchScore();


            /*
             * Existing score available.
             * render() has already displayed it.
             */
            if (
                data
                &&
                data.has_score
            ) {
                return;
            }


            /*
             * No existing score.
             *
             * Start analysis directly instead of
             * first displaying a misleading error.
             */
            if (
                data
                &&
                data.has_score === false
            ) {

                await analyse();

            }

        }
    );


    /*
     * =========================================================
     * CLOSE BUTTONS
     * =========================================================
     */

    document
        .getElementById(
            'close-lead-ai-score-modal'
        )
        ?.addEventListener(
            'click',
            closeModal
        );


    document
        .getElementById(
            'close-lead-ai-score-modal-footer'
        )
        ?.addEventListener(
            'click',
            closeModal
        );


    /*
     * Close when clicking backdrop.
     */
    modal
        ?.addEventListener(
            'click',
            function (
                event
            ) {

                if (
                    event.target
                    === modal
                ) {
                    closeModal();
                }

            }
        );


    /*
     * ESC close.
     */
    document.addEventListener(
        'keydown',
        function (
            event
        ) {

            if (
                event.key === 'Escape'
                &&
                modal
                &&
                !modal.classList.contains(
                    'hidden'
                )
            ) {

                closeModal();

            }

        }
    );


    /*
     * =========================================================
     * RETRY
     * =========================================================
     */

    retryButton
        ?.addEventListener(
            'click',
            retry
        );


    /*
     * =========================================================
     * PAGE LOAD
     * =========================================================
     *
     * If an AI job was already running when
     * this page loaded, continue monitoring it.
     */
    @if(
        !$aiBookedClosed
        &&
        $aiScore
        &&
        in_array(
            $aiScore->status,
            [
                'pending',
                'processing'
            ],
            true
        )
    )

        setButtonAnalysing();

        startPolling();

    @endif


})();

</script>

@endpush
