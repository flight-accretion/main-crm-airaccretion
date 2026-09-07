@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">

    <div>
        <h3
            class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white text-[1.125rem] font-semibold"
        >
            AI Agent
        </h3>

        <p class="text-xs text-gray-500 mt-1">
            Configure OpenAI, WhatsApp AI and Lead Scoring AI.
        </p>
    </div>

</div>


@if(session('success'))
    <div class="alert alert-success mb-4">
        {{ session('success') }}
    </div>
@endif


@if($errors->any())
    <div class="alert alert-danger mb-4">
        <ul class="list-disc ps-5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif


<form
    method="POST"
    action="{{ route('admin.whatsapp.ai-agent.update') }}"
>
    @csrf
    @method('PUT')


    {{-- =====================================================
         TAB BUTTONS
         ===================================================== --}}
    <div class="box mb-5">

        <div class="box-body">

            <div class="flex gap-2 flex-wrap">

                <button
                    type="button"
                    class="ai-settings-tab ti-btn ti-btn-primary-full"
                    data-target="openai-settings"
                >
                    OpenAI Configuration
                </button>

                <button
                    type="button"
                    class="ai-settings-tab ti-btn ti-btn-outline-primary"
                    data-target="whatsapp-settings"
                >
                    WhatsApp AI
                </button>

                <button
                    type="button"
                    class="ai-settings-tab ti-btn ti-btn-outline-primary"
                    data-target="lead-scoring-settings"
                >
                    Lead Scoring AI
                </button>

            </div>

        </div>

    </div>


    {{-- =====================================================
         OPENAI CONFIGURATION
         ===================================================== --}}
    <div
        id="openai-settings"
        class="ai-settings-panel"
    >

        <div class="box">

            <div class="box-header">
                <div class="box-title">
                    OpenAI Configuration
                </div>
            </div>

            <div class="box-body">

                <div class="grid grid-cols-12 gap-6">

                    <div class="md:col-span-6 col-span-12">

                        <label class="ti-form-label">
                            Provider
                        </label>

                        <select
                            name="provider"
                            class="ti-form-select"
                        >
                            <option
                                value="openai"
                                selected
                            >
                                OpenAI
                            </option>
                        </select>

                    </div>


                    <div class="md:col-span-6 col-span-12">

                        <label class="ti-form-label">
                            Default Model
                        </label>

                        <input
                            type="text"
                            name="model"
                            class="ti-form-input"
                            value="{{ old('model', $setting->model) }}"
                            required
                        >

                        <small class="text-gray-500">
                            Lead Scoring uses this model unless an override is configured.
                        </small>

                    </div>


                    <div class="col-span-12">

                        <label class="ti-form-label">
                            OpenAI API Key
                        </label>

                        <input
                            type="password"
                            name="api_key"
                            id="openai-api-key"
                            class="ti-form-input"
                            autocomplete="new-password"
                            placeholder="{{ $setting->api_key_status === 'configured' ? 'API key already configured — leave blank to keep it' : 'Enter OpenAI API key' }}"
                        >

                        <div class="mt-2 text-sm">

                            @if($setting->api_key_status === 'configured')

                                <span class="text-success">
                                    <i class="ri-checkbox-circle-line"></i>
                                    API Key Configured
                                </span>

                            @else

                                <span class="text-warning">
                                    <i class="ri-error-warning-line"></i>
                                    API Key Not Configured
                                </span>

                            @endif

                        </div>

                    </div>


                    <div class="col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="clear_api_key"
                                value="1"
                                class="ti-form-checkbox"
                            >

                            <span class="text-sm text-danger">
                                Remove currently stored API key
                            </span>

                        </label>

                    </div>


                    <div class="col-span-12">

                        <button
                            type="button"
                            id="test-openai-connection"
                            class="ti-btn ti-btn-info-full"
                        >
                            <i class="ri-plug-line me-1"></i>
                            Test Connection
                        </button>

                        <span
                            id="openai-test-result"
                            class="ms-3 text-sm"
                        ></span>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         WHATSAPP AI
         ===================================================== --}}
    <div
        id="whatsapp-settings"
        class="ai-settings-panel hidden"
    >

        <div class="box">

            <div class="box-header">
                <div class="box-title">
                    WhatsApp AI
                </div>
            </div>

            <div class="box-body">

                <div class="grid grid-cols-12 gap-6">


                    <div class="md:col-span-6 col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{ old('enabled', $setting->enabled) ? 'checked' : '' }}
                            >

                            <span>
                                Enable WhatsApp AI Agent
                            </span>

                        </label>

                    </div>


                    <div class="md:col-span-6 col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="auto_reply_enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{ old('auto_reply_enabled', $setting->auto_reply_enabled) ? 'checked' : '' }}
                            >

                            <span>
                                Enable WhatsApp Auto Reply
                            </span>

                        </label>

                    </div>


                    <div class="md:col-span-6 col-span-12">

                        <label class="ti-form-label">
                            Buffer Seconds
                        </label>

                        <input
                            type="number"
                            name="buffer_seconds"
                            min="1"
                            max="300"
                            class="ti-form-input"
                            value="{{ old('buffer_seconds', $setting->buffer_seconds) }}"
                            required
                        >

                    </div>


                    <div class="md:col-span-6 col-span-12">

                        <label class="ti-form-label">
                            Context Message Limit
                        </label>

                        <input
                            type="number"
                            name="context_message_limit"
                            min="1"
                            max="100000"
                            class="ti-form-input"
                            value="{{ old('context_message_limit', $setting->context_message_limit) }}"
                            required
                        >

                    </div>


                    <div class="col-span-12">

                        <label class="ti-form-label">
                            WhatsApp AI Prompt
                        </label>

                        <textarea
                            name="prompt"
                            rows="18"
                            maxlength="12000"
                            class="form-control"
                            required
                        >{{ old('prompt', $setting->prompt) }}</textarea>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         LEAD SCORING AI
         ===================================================== --}}
    <div
        id="lead-scoring-settings"
        class="ai-settings-panel hidden"
    >

        <div class="box">

            <div class="box-header">
                <div class="box-title">
                    Lead Scoring AI
                </div>
            </div>

            <div class="box-body">

                <div class="grid grid-cols-12 gap-6">


                    <div class="md:col-span-6 col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="lead_scoring_enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{ old('lead_scoring_enabled', $leadScoringSetting->enabled) ? 'checked' : '' }}
                            >

                            <span>
                                Enable Lead Scoring
                            </span>

                        </label>

                    </div>


                    <div class="md:col-span-6 col-span-12">

                        <label class="flex items-center gap-2">

                            <input
                                type="checkbox"
                                name="lead_scoring_auto_analyse"
                                value="1"
                                class="ti-form-checkbox"
                                {{ old('lead_scoring_auto_analyse', $leadScoringSetting->auto_analyse) ? 'checked' : '' }}
                            >

                            <span>
                                Automatically Analyse New Eligible Follow-ups
                            </span>

                        </label>

                    </div>


                    <div class="col-span-12">

                        <label class="ti-form-label">
                            Lead Scoring Model Override
                        </label>

                        <input
                            type="text"
                            name="lead_scoring_model"
                            class="ti-form-input"
                            value="{{ old('lead_scoring_model', $leadScoringSetting->model) }}"
                            placeholder="Leave blank to use global model: {{ $setting->model }}"
                        >

                    </div>


                    <div class="md:col-span-4 col-span-12">

                        <label class="ti-form-label">
                            Cold Maximum
                        </label>

                        <input
                            type="number"
                            name="cold_max"
                            min="0"
                            max="98"
                            class="ti-form-input"
                            value="{{ old('cold_max', $leadScoringSetting->cold_max) }}"
                            required
                        >

                    </div>


                    <div class="md:col-span-4 col-span-12">

                        <label class="ti-form-label">
                            Neutral Maximum
                        </label>

                        <input
                            type="number"
                            name="neutral_max"
                            min="1"
                            max="99"
                            class="ti-form-input"
                            value="{{ old('neutral_max', $leadScoringSetting->neutral_max) }}"
                            required
                        >

                    </div>


                    <div class="md:col-span-4 col-span-12">

                        <label class="ti-form-label">
                            Hot Begins At
                        </label>

                        <input
                            type="number"
                            class="ti-form-input"
                            value="{{ ((int) $leadScoringSetting->neutral_max) + 1 }}"
                            disabled
                        >

                    </div>


                    <div class="col-span-12">

                        <label class="ti-form-label">
                            Lead Scoring Business Prompt
                        </label>

                        <textarea
                            name="lead_scoring_prompt"
                            rows="15"
                            maxlength="12000"
                            class="form-control"
                            required
                        >{{ old('lead_scoring_prompt', $leadScoringSetting->prompt) }}</textarea>

                        <small class="text-gray-500">
                            The JSON/output rules are protected in application code. This field only controls your business scoring instructions.
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <div class="flex justify-end mt-4 mb-8">

        <button
            type="submit"
            class="ti-btn ti-btn-primary-full"
        >
            <i class="ri-save-line me-1"></i>
            Save AI Settings
        </button>

    </div>

</form>

@endsection


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const tabs =
            document.querySelectorAll(
                '.ai-settings-tab'
            );

        const panels =
            document.querySelectorAll(
                '.ai-settings-panel'
            );


        tabs.forEach(function (tab) {

            tab.addEventListener(
                'click',
                function () {

                    const target =
                        this.getAttribute(
                            'data-target'
                        );

                    panels.forEach(
                        function (panel) {

                            panel.classList.add(
                                'hidden'
                            );

                        }
                    );

                    tabs.forEach(
                        function (item) {

                            item.classList.remove(
                                'ti-btn-primary-full'
                            );

                            item.classList.add(
                                'ti-btn-outline-primary'
                            );

                        }
                    );

                    document
                        .getElementById(
                            target
                        )
                        ?.classList
                        .remove('hidden');

                    this.classList.remove(
                        'ti-btn-outline-primary'
                    );

                    this.classList.add(
                        'ti-btn-primary-full'
                    );

                }
            );

        });


        const testButton =
            document.getElementById(
                'test-openai-connection'
            );

        testButton?.addEventListener(
            'click',
            async function () {

                const result =
                    document.getElementById(
                        'openai-test-result'
                    );

                result.textContent =
                    'Testing...';

                result.className =
                    'ms-3 text-sm text-info';

                try {

                    const response =
                        await fetch(
                            @json(
                                route(
                                    'admin.whatsapp.ai-agent.test-connection'
                                )
                            ),
                            {
                                method: 'POST',

                                headers: {
                                    'Content-Type':
                                        'application/json',

                                    'Accept':
                                        'application/json',

                                    'X-CSRF-TOKEN':
                                        document
                                            .querySelector(
                                                'meta[name="csrf-token"]'
                                            )
                                            ?.getAttribute(
                                                'content'
                                            )
                                },

                                body:
                                    JSON.stringify(
                                        {
                                            api_key:
                                                document
                                                    .getElementById(
                                                        'openai-api-key'
                                                    )
                                                    ?.value
                                                || null,

                                            model:
                                                document
                                                    .querySelector(
                                                        '[name="model"]'
                                                    )
                                                    ?.value
                                                || null
                                        }
                                    )
                            }
                        );

                    const data =
                        await response.json();

                    if (!response.ok) {
                        throw new Error(
                            data.message
                            || 'Connection failed'
                        );
                    }

                    result.textContent =
                        '✓ '
                        + data.message;

                    result.className =
                        'ms-3 text-sm text-success';

                } catch (error) {

                    result.textContent =
                        '✗ '
                        + (
                            error.message
                            || 'Connection failed'
                        );

                    result.className =
                        'ms-3 text-sm text-danger';

                }

            }
        );

    }
);

</script>

@endpush