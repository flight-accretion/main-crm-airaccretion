@extends('admin.layouts.header')

@section('content')

@php
    /*
     * =========================================================
     * AGENT COLLECTIONS FOR FEATURE SELECTION
     * =========================================================
     */

    $whatsappAgents = $aiAgents->filter(
        function ($agent) {
            return
                $agent->enabled
                &&
                in_array(
                    $agent->agent_type,
                    [
                        'whatsapp',
                        'generic',
                    ],
                    true
                );
        }
    );

    $leadScoringAgents = $aiAgents->filter(
        function ($agent) {
            return
                $agent->enabled
                &&
                in_array(
                    $agent->agent_type,
                    [
                        'lead_scoring',
                        'generic',
                    ],
                    true
                );
        }
    );
@endphp


{{-- ==========================================================
     PAGE HEADER
     ========================================================== --}}
<div class="block justify-between page-header md:flex">

    <div>

        <h3
            class="!text-defaulttextcolor
                   dark:!text-defaulttextcolor/70
                   dark:text-white
                   text-[1.125rem]
                   font-semibold"
        >
            AI Agent
        </h3>

        <p class="text-xs text-gray-500 mt-1">
            Manage reusable AI Models, AI Agents,
            WhatsApp AI and Lead Scoring AI.
        </p>

    </div>

</div>


{{-- ==========================================================
     SUCCESS MESSAGE
     ========================================================== --}}
@if(session('success'))

    <div class="alert alert-success mb-4">

        {{ session('success') }}

    </div>

@endif


{{-- ==========================================================
     ERROR MESSAGE
     ========================================================== --}}
@if(session('error'))

    <div class="alert alert-danger mb-4">

        {{ session('error') }}

    </div>

@endif


{{-- ==========================================================
     VALIDATION ERRORS
     ========================================================== --}}
@if($errors->any())

    <div class="alert alert-danger mb-4">

        <ul class="list-disc ps-5">

            @foreach($errors->all() as $error)

                <li>
                    {{ $error }}
                </li>

            @endforeach

        </ul>

    </div>

@endif


{{-- ==========================================================
     TAB NAVIGATION
     ========================================================== --}}
<div class="box mb-5">

    <div class="box-body">

        <div class="flex gap-2 flex-wrap">

            <button
                type="button"
                class="ai-settings-tab ti-btn ti-btn-primary-full"
                data-target="ai-models-settings"
            >
                <i class="ri-cpu-line me-1"></i>
                AI Models
            </button>


            <button
                type="button"
                class="ai-settings-tab ti-btn ti-btn-outline-primary"
                data-target="ai-agents-settings"
            >
                <i class="ri-robot-2-line me-1"></i>
                AI Agents
            </button>


            <button
                type="button"
                class="ai-settings-tab ti-btn ti-btn-outline-primary"
                data-target="whatsapp-settings"
            >
                <i class="ri-whatsapp-line me-1"></i>
                WhatsApp AI
            </button>


            <button
                type="button"
                class="ai-settings-tab ti-btn ti-btn-outline-primary"
                data-target="lead-scoring-settings"
            >
                <i class="ri-line-chart-line me-1"></i>
                Lead Scoring AI
            </button>

        </div>

    </div>

</div>


{{-- ==========================================================
     AI MODELS TAB
     ========================================================== --}}
<div
    id="ai-models-settings"
    class="ai-settings-panel"
>

    <div class="box">

        <div
            class="box-header flex justify-between items-center"
        >

            <div>

                <div class="box-title">
                    AI Models
                </div>

                <p class="text-xs text-gray-500 mt-1">
                    Store reusable provider, model and API-key
                    configurations.
                </p>

            </div>


            <button
                type="button"
                id="add-ai-model-button"
                class="ti-btn ti-btn-primary-full"
            >
                <i class="ri-add-line me-1"></i>
                Add AI Model
            </button>

        </div>


        <div class="box-body">

            <div class="table-responsive">

                <table class="table whitespace-nowrap">

                    <thead>

                        <tr>

                            <th>
                                Profile Name
                            </th>

                            <th>
                                Provider
                            </th>

                            <th>
                                Model
                            </th>

                            <th>
                                API Key
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Used By Agents
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse(
                            $aiModelProfiles
                            as $profile
                        )

                            @php
                                $profileAgents =
                                    $profile->relationLoaded('agents')
                                        ? $profile->agents
                                        : collect();

                                $profileUsed =
                                    $profileAgents->isNotEmpty();

                                $agentNames =
                                    $profileAgents
                                        ->pluck('name')
                                        ->filter()
                                        ->implode(', ');
                            @endphp


                            <tr>

                                <td>

                                    <div class="font-semibold">

                                        {{ $profile->name }}

                                    </div>

                                </td>


                                <td>

                                    <span
                                        class="badge bg-primary/10 text-primary"
                                    >
                                        {{
                                            strtoupper(
                                                $profile->provider
                                            )
                                        }}
                                    </span>

                                </td>


                                <td>

                                    {{ $profile->model }}

                                </td>


                                <td>

                                    @if(
                                        $profile->api_key_status
                                        === 'configured'
                                    )

                                        <span class="text-success">

                                            <i
                                                class="ri-checkbox-circle-line me-1"
                                            ></i>

                                            Configured

                                        </span>

                                    @else

                                        <span class="text-danger">

                                            <i
                                                class="ri-error-warning-line me-1"
                                            ></i>

                                            Missing

                                        </span>

                                    @endif

                                </td>


                                <td>

                                    @if($profile->enabled)

                                        <span
                                            class="badge bg-success/10 text-success"
                                        >
                                            Active
                                        </span>

                                    @else

                                        <span
                                            class="badge bg-danger/10 text-danger"
                                        >
                                            Inactive
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    @if($profileUsed)

                                        {{ $agentNames }}

                                    @else

                                        <span class="text-gray-400">
                                            Not Used
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    <div
                                        class="flex items-center gap-2 flex-wrap"
                                    >

                                        <button
                                            type="button"
                                            class="edit-ai-model
                                                   ti-btn
                                                   ti-btn-sm
                                                   ti-btn-info-full"
                                            data-id="{{ $profile->id }}"
                                            data-name="{{ $profile->name }}"
                                            data-provider="{{ $profile->provider }}"
                                            data-model="{{ $profile->model }}"
                                            data-enabled="{{ $profile->enabled ? '1' : '0' }}"
                                            style="width: auto;"
                                        >
                                            <i class="ri-edit-line"></i>
                                            Edit
                                        </button>


                                        <button
                                            type="button"
                                            class="test-existing-ai-model
                                                   ti-btn
                                                   ti-btn-sm
                                                   ti-btn-secondary-full"
                                            data-id="{{ $profile->id }}"
                                            data-provider="{{ $profile->provider }}"
                                            data-model="{{ $profile->model }}"
                                            data-result-id="model-test-result-{{ $profile->id }}"
                                             style="width: auto;"
                                        >
                                            <i class="ri-plug-line"></i>
                                            Test
                                        </button>


                                        @if(!$profileUsed)

                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'admin.whatsapp.ai-models.destroy',
                                                        $profile->id
                                                    )
                                                }}"
                                            >

                                                @csrf
                                                @method('DELETE')


                                                <button
                                                    type="submit"
                                                    class="ti-btn
                                                           ti-btn-sm
                                                           ti-btn-danger-full"
                                                    onclick="
                                                        return confirm(
                                                            'Delete this AI Model Profile?'
                                                        );
                                                    "
                                                     style="width: auto;"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                    Delete
                                                </button>

                                            </form>

                                        @endif

                                    </div>


                                    <div
                                        id="model-test-result-{{ $profile->id }}"
                                        class="text-xs mt-2"
                                    ></div>

                                </td>

                            </tr>


                        @empty

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center py-8 text-gray-500"
                                >

                                    No AI Model Profiles found.

                                    <div class="mt-2">

                                        Click

                                        <strong>
                                            Add AI Model
                                        </strong>

                                        to create your first profile.

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


{{-- ==========================================================
     AI AGENTS TAB
     ========================================================== --}}
<div
    id="ai-agents-settings"
    class="ai-settings-panel hidden"
>

    <div class="box">

        <div
            class="box-header flex justify-between items-center"
        >

            <div>

                <div class="box-title">
                    AI Agents
                </div>

                <p class="text-xs text-gray-500 mt-1">
                    Create reusable agents containing their
                    business prompt and selected AI Model Profile.
                </p>

            </div>


            <button
                type="button"
                id="add-ai-agent-button"
                class="ti-btn ti-btn-primary-full"
            >
                <i class="ri-add-line me-1"></i>
                Add AI Agent
            </button>

        </div>


        <div class="box-body">

            <div class="table-responsive">

                <table class="table whitespace-nowrap">

                    <thead>

                        <tr>

                            <th>
                                Agent Name
                            </th>

                            <th>
                                Purpose
                            </th>

                            <th>
                                Model Profile
                            </th>

                            <th>
                                Provider
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Used By
                            </th>

                            <th>
                                Actions
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        @forelse(
                            $aiAgents
                            as $agent
                        )

                            @php
                                $usedBy = [];

                                if (
                                    $setting->ai_agent_id
                                    === $agent->id
                                ) {
                                    $usedBy[] =
                                        'WhatsApp AI';
                                }

                                if (
                                    $leadScoringSetting->ai_agent_id
                                    === $agent->id
                                ) {
                                    $usedBy[] =
                                        'Lead Scoring AI';
                                }

                                $agentInUse =
                                    !empty($usedBy);

                                $agentPromptBase64 =
                                    base64_encode(
                                        (string) $agent->prompt
                                    );
                            @endphp


                            <tr>

                                <td>

                                    <div class="font-semibold">

                                        {{ $agent->name }}

                                    </div>

                                </td>


                                <td>

                                    {{
                                        ucwords(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $agent->agent_type
                                            )
                                        )
                                    }}

                                </td>


                                <td>

                                    {{
                                        optional(
                                            $agent->modelProfile
                                        )->name
                                        ?? 'Not configured'
                                    }}

                                </td>


                                <td>

                                    @if(
                                        $agent->modelProfile
                                    )

                                        <span
                                            class="badge bg-primary/10 text-primary"
                                        >
                                            {{
                                                strtoupper(
                                                    $agent
                                                        ->modelProfile
                                                        ->provider
                                                )
                                            }}
                                        </span>

                                    @else

                                        <span class="text-danger">
                                            Missing
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    @if($agent->enabled)

                                        <span
                                            class="badge bg-success/10 text-success"
                                        >
                                            Active
                                        </span>

                                    @else

                                        <span
                                            class="badge bg-danger/10 text-danger"
                                        >
                                            Inactive
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    @if($agentInUse)

                                        {{ implode(', ', $usedBy) }}

                                    @else

                                        <span class="text-gray-400">
                                            Not Used
                                        </span>

                                    @endif

                                </td>


                                <td>

                                    <div
                                        class="flex items-center gap-2 flex-wrap"
                                    >

                                        <button
                                            type="button"
                                            class="edit-ai-agent
                                                   ti-btn
                                                   ti-btn-sm
                                                   ti-btn-info-full"
                                            data-id="{{ $agent->id }}"
                                            data-name="{{ $agent->name }}"
                                            data-type="{{ $agent->agent_type }}"
                                            data-profile="{{ $agent->ai_model_profile_id }}"
                                            data-enabled="{{ $agent->enabled ? '1' : '0' }}"
                                            data-prompt="{{ $agentPromptBase64 }}"
                                             style="width: auto;"
                                        >
                                            <i class="ri-edit-line"></i>
                                            Edit
                                        </button>


                                        @if(!$agentInUse)

                                            <form
                                                method="POST"
                                                action="{{
                                                    route(
                                                        'admin.whatsapp.ai-agents.destroy',
                                                        $agent->id
                                                    )
                                                }}"
                                            >

                                                @csrf
                                                @method('DELETE')


                                                <button
                                                    type="submit"
                                                    class="ti-btn
                                                           ti-btn-sm
                                                           ti-btn-danger-full"
                                                    onclick="
                                                        return confirm(
                                                            'Delete this AI Agent?'
                                                        );
                                                    "
                                                     style="width: auto;"
                                                >
                                                    <i class="ri-delete-bin-line"></i>
                                                    Delete
                                                </button>

                                            </form>

                                        @endif

                                    </div>

                                </td>

                            </tr>


                        @empty

                            <tr>

                                <td
                                    colspan="7"
                                    class="text-center py-8 text-gray-500"
                                >

                                    No AI Agents found.

                                    <div class="mt-2">

                                        Click

                                        <strong>
                                            Add AI Agent
                                        </strong>

                                        to create your first AI Agent.

                                    </div>

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>


{{-- ==========================================================
     FEATURE SETTINGS FORM
     ========================================================== --}}
<form
    method="POST"
    action="{{
        route(
            'admin.whatsapp.ai-agent.update'
        )
    }}"
>

    @csrf
    @method('PUT')


    {{-- ======================================================
         WHATSAPP AI TAB
         ====================================================== --}}
    <div
        id="whatsapp-settings"
        class="ai-settings-panel hidden"
    >

        <div class="box">

            <div class="box-header">

                <div>

                    <div class="box-title">
                        WhatsApp AI
                    </div>

                    <p class="text-xs text-gray-500 mt-1">
                        Select which reusable AI Agent should handle
                        WhatsApp conversations.
                    </p>

                </div>

            </div>


            <div class="box-body">

                <div class="grid grid-cols-12 gap-6">


                    {{-- ENABLE --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label
                            class="flex items-center gap-2"
                        >

                            <input
                                type="checkbox"
                                name="enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{
                                    old(
                                        'enabled',
                                        $setting->enabled
                                    )
                                        ? 'checked'
                                        : ''
                                }}
                            >

                            <span>
                                Enable WhatsApp AI
                            </span>

                        </label>

                    </div>


                    {{-- AUTO REPLY --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label
                            class="flex items-center gap-2"
                        >

                            <input
                                type="checkbox"
                                name="auto_reply_enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{
                                    old(
                                        'auto_reply_enabled',
                                        $setting
                                            ->auto_reply_enabled
                                    )
                                        ? 'checked'
                                        : ''
                                }}
                            >

                            <span>
                                Enable WhatsApp Auto Reply
                            </span>

                        </label>

                    </div>


                    {{-- SELECTED AGENT --}}
                    <div class="col-span-12">

                        <label class="ti-form-label">
                            Selected AI Agent
                        </label>


                        <select
                            name="whatsapp_ai_agent_id"
                            class="ti-form-select"
                        >

                            <option value="">
                                Select WhatsApp AI Agent
                            </option>


                            @foreach(
                                $whatsappAgents
                                as $agent
                            )

                                <option
                                    value="{{ $agent->id }}"
                                    {{
                                        old(
                                            'whatsapp_ai_agent_id',
                                            $setting->ai_agent_id
                                        )
                                        === $agent->id
                                            ? 'selected'
                                            : ''
                                    }}
                                >

                                    {{ $agent->name }}

                                    —

                                    {{
                                        optional(
                                            $agent->modelProfile
                                        )->name
                                        ?? 'No Model'
                                    }}

                                </option>

                            @endforeach

                        </select>


                        <small class="text-gray-500">

                            Prompt, provider, model and API key
                            are managed through the selected
                            AI Agent and AI Model Profile.

                        </small>

                    </div>


                    {{-- BUFFER --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label class="ti-form-label">
                            Buffer Seconds
                        </label>

                        <input
                            type="number"
                            name="buffer_seconds"
                            min="1"
                            max="300"
                            class="ti-form-input"
                            value="{{
                                old(
                                    'buffer_seconds',
                                    $setting->buffer_seconds
                                )
                            }}"
                            required
                        >

                        <small class="text-gray-500">
                            Wait before processing grouped customer
                            WhatsApp messages.
                        </small>

                    </div>


                    {{-- CONTEXT --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label class="ti-form-label">
                            Context Message Limit
                        </label>

                        <input
                            type="number"
                            name="context_message_limit"
                            min="1"
                            max="100000"
                            class="ti-form-input"
                            value="{{
                                old(
                                    'context_message_limit',
                                    $setting
                                        ->context_message_limit
                                )
                            }}"
                            required
                        >

                        <small class="text-gray-500">
                            Maximum conversation context supplied
                            to the selected WhatsApp Agent.
                        </small>

                    </div>


                    {{-- CURRENT AGENT INFORMATION --}}
                    @if($setting->aiAgent)

                        <div class="col-span-12">

                            <div
                                class="p-4 rounded border
                                       dark:border-white/10
                                       bg-light"
                            >

                                <div class="font-semibold mb-2">
                                    Current WhatsApp Agent
                                </div>

                                <div class="text-sm space-y-1">

                                    <div>

                                        <strong>
                                            Agent:
                                        </strong>

                                        {{
                                            $setting
                                                ->aiAgent
                                                ->name
                                        }}

                                    </div>


                                    <div>

                                        <strong>
                                            Model Profile:
                                        </strong>

                                        {{
                                            optional(
                                                $setting
                                                    ->aiAgent
                                                    ->modelProfile
                                            )->name
                                            ?? 'Not configured'
                                        }}

                                    </div>


                                    <div>

                                        <strong>
                                            Provider:
                                        </strong>

                                        {{
                                            strtoupper(
                                                optional(
                                                    $setting
                                                        ->aiAgent
                                                        ->modelProfile
                                                )->provider
                                                ?? '-'
                                            )
                                        }}

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endif


                    <div class="col-span-12">

                        <div class="flex justify-end">

                            <button
                                type="submit"
                                class="ti-btn ti-btn-primary-full"
                            >
                                <i class="ri-save-line me-1"></i>
                                Save WhatsApp AI Settings
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- ======================================================
         LEAD SCORING AI TAB
         ====================================================== --}}
    <div
        id="lead-scoring-settings"
        class="ai-settings-panel hidden"
    >

        <div class="box">

            <div class="box-header">

                <div>

                    <div class="box-title">
                        Lead Scoring AI
                    </div>

                    <p class="text-xs text-gray-500 mt-1">
                        Select the AI Agent that will analyse genuine
                        sales/customer follow-ups.
                    </p>

                </div>

            </div>


            <div class="box-body">

                <div class="grid grid-cols-12 gap-6">


                    {{-- ENABLE --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label
                            class="flex items-center gap-2"
                        >

                            <input
                                type="checkbox"
                                name="lead_scoring_enabled"
                                value="1"
                                class="ti-form-checkbox"
                                {{
                                    old(
                                        'lead_scoring_enabled',
                                        $leadScoringSetting
                                            ->enabled
                                    )
                                        ? 'checked'
                                        : ''
                                }}
                            >

                            <span>
                                Enable Lead Scoring
                            </span>

                        </label>

                    </div>


                    {{-- AUTO ANALYSE --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label
                            class="flex items-center gap-2"
                        >

                            <input
                                type="checkbox"
                                name="lead_scoring_auto_analyse"
                                value="1"
                                class="ti-form-checkbox"
                                {{
                                    old(
                                        'lead_scoring_auto_analyse',
                                        $leadScoringSetting
                                            ->auto_analyse
                                    )
                                        ? 'checked'
                                        : ''
                                }}
                            >

                            <span>
                                Automatically Analyse New Eligible Follow-ups
                            </span>

                        </label>

                    </div>


                    {{-- AGENT --}}
                    <div class="col-span-12">

                        <label class="ti-form-label">
                            Selected AI Agent
                        </label>


                        <select
                            name="lead_scoring_ai_agent_id"
                            class="ti-form-select"
                        >

                            <option value="">
                                Select Lead Scoring AI Agent
                            </option>


                            @foreach(
                                $leadScoringAgents
                                as $agent
                            )

                                <option
                                    value="{{ $agent->id }}"
                                    {{
                                        old(
                                            'lead_scoring_ai_agent_id',
                                            $leadScoringSetting
                                                ->ai_agent_id
                                        )
                                        === $agent->id
                                            ? 'selected'
                                            : ''
                                    }}
                                >

                                    {{ $agent->name }}

                                    —

                                    {{
                                        optional(
                                            $agent->modelProfile
                                        )->name
                                        ?? 'No Model'
                                    }}

                                </option>

                            @endforeach

                        </select>


                        <small class="text-gray-500">

                            Lead scoring prompt and AI provider
                            are configured inside the selected AI Agent.

                        </small>

                    </div>


                    {{-- COLD --}}
                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <label class="ti-form-label">
                            Cold Maximum
                        </label>

                        <input
                            type="number"
                            name="cold_max"
                            id="cold-max"
                            min="0"
                            max="98"
                            class="ti-form-input"
                            value="{{
                                old(
                                    'cold_max',
                                    $leadScoringSetting
                                        ->cold_max
                                )
                            }}"
                            required
                        >

                        <small class="text-gray-500">
                            Example: 0–39
                        </small>

                    </div>


                    {{-- NEUTRAL --}}
                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <label class="ti-form-label">
                            Neutral Maximum
                        </label>

                        <input
                            type="number"
                            name="neutral_max"
                            id="neutral-max"
                            min="1"
                            max="99"
                            class="ti-form-input"
                            value="{{
                                old(
                                    'neutral_max',
                                    $leadScoringSetting
                                        ->neutral_max
                                )
                            }}"
                            required
                        >

                        <small class="text-gray-500">
                            Example: 40–69
                        </small>

                    </div>


                    {{-- HOT --}}
                    <div
                        class="md:col-span-4 col-span-12"
                    >

                        <label class="ti-form-label">
                            Hot Begins At
                        </label>

                        <input
                            type="number"
                            id="hot-begins-at"
                            class="ti-form-input"
                            value="{{
                                (
                                    (
                                        (int)
                                        old(
                                            'neutral_max',
                                            $leadScoringSetting
                                                ->neutral_max
                                        )
                                    )
                                    + 1
                                )
                            }}"
                            disabled
                        >

                        <small class="text-gray-500">
                            Calculated automatically.
                        </small>

                    </div>


                    {{-- CURRENT AGENT --}}
                    @if(
                        $leadScoringSetting->aiAgent
                    )

                        <div class="col-span-12">

                            <div
                                class="p-4 rounded border
                                       dark:border-white/10
                                       bg-light"
                            >

                                <div class="font-semibold mb-2">
                                    Current Lead Scoring Agent
                                </div>


                                <div class="text-sm space-y-1">

                                    <div>

                                        <strong>
                                            Agent:
                                        </strong>

                                        {{
                                            $leadScoringSetting
                                                ->aiAgent
                                                ->name
                                        }}

                                    </div>


                                    <div>

                                        <strong>
                                            Model Profile:
                                        </strong>

                                        {{
                                            optional(
                                                $leadScoringSetting
                                                    ->aiAgent
                                                    ->modelProfile
                                            )->name
                                            ?? 'Not configured'
                                        }}

                                    </div>


                                    <div>

                                        <strong>
                                            Provider:
                                        </strong>

                                        {{
                                            strtoupper(
                                                optional(
                                                    $leadScoringSetting
                                                        ->aiAgent
                                                        ->modelProfile
                                                )->provider
                                                ?? '-'
                                            )
                                        }}

                                    </div>

                                </div>

                            </div>

                        </div>

                    @endif


                    <div class="col-span-12">

                        <div class="flex justify-end">

                            <button
                                type="submit"
                                class="ti-btn ti-btn-primary-full"
                            >
                                <i class="ri-save-line me-1"></i>
                                Save Lead Scoring Settings
                            </button>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</form>


{{-- ==========================================================
     ADD / EDIT AI MODEL MODAL
     ========================================================== --}}
<div
    id="ai-model-modal"
    class="hidden fixed inset-0 z-[9999]
           bg-black/50
           overflow-y-auto"
>
 <div
        class="min-h-full
               flex
               items-center
               justify-center
               px-4
               py-8"
    >
    <div
        class="bg-white
               dark:bg-bodybg
               rounded-lg
               shadow-xl
               max-h-[90vh]
               overflow-y-auto"
               style="border: 1px solid #dcdbdbe8;
                margin-top: 20px;
                margin-bottom: 20px;   
                width: 40vw;"
    >

        <form
            id="ai-model-form"
            method="POST"
            action="{{
                route(
                    'admin.whatsapp.ai-models.store'
                )
            }}"
        >

            @csrf


            <input
                type="hidden"
                name="_method"
                id="ai-model-method"
                value=""
            >


            <input
                type="hidden"
                id="ai-model-profile-id"
                value=""
            >


            <div
                class="p-4
                       border-b
                       dark:border-white/10
                       flex
                       justify-between
                       items-center"
            >

                <div>

                    <h4
                        id="ai-model-modal-title"
                        class="font-semibold text-lg"
                    >
                        Add AI Model
                    </h4>

                    <p class="text-xs text-gray-500 mt-1">
                        Provider, model and API-key configuration.
                    </p>

                </div>


                <button
                    type="button"
                    id="close-ai-model-modal"
                    aria-label="Close"
                >
                    <i class="ri-close-line text-xl"></i>
                </button>

            </div>


            <div class="p-5 space-y-5">


                {{-- NAME --}}
                <div>

                    <label class="ti-form-label">
                        Profile Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="ai-model-name"
                        class="ti-form-input"
                        placeholder="Example: WhatsApp OpenAI"
                        required
                    >

                </div>


                {{-- PROVIDER --}}
                <div>

                    <label class="ti-form-label">
                        Provider
                    </label>

                    <select
                        name="provider"
                        id="ai-model-provider"
                        class="ti-form-select"
                        required
                    >

                        <option value="openai">
                            OpenAI
                        </option>

                        <option value="gemini">
                            Gemini
                        </option>

                    </select>

                </div>


                {{-- MODEL --}}
                <div>

                    <label class="ti-form-label">
                        Model
                    </label>

                    <input
                        type="text"
                        name="model"
                        id="ai-model-model"
                        class="ti-form-input"
                        placeholder="Example: gpt-4o-mini"
                        required
                    >

                    <small class="text-gray-500">
                        Enter the exact model identifier supported
                        by the selected provider.
                    </small>

                </div>


                {{-- API KEY --}}
                <div>

                    <label class="ti-form-label">
                        API Key
                    </label>

                    <input
                        type="password"
                        name="api_key"
                        id="ai-model-api-key"
                        class="ti-form-input"
                        autocomplete="new-password"
                        placeholder="Enter API key"
                    >


                    <small
                        id="ai-model-key-help"
                        class="text-gray-500"
                    >
                        Required when creating a new model profile.
                    </small>

                </div>


                {{-- CLEAR API KEY --}}
                <div
                    id="clear-api-key-wrapper"
                    class="hidden"
                >

                    <label
                        class="flex items-center gap-2"
                    >

                        <input
                            type="checkbox"
                            name="clear_api_key"
                            id="clear-ai-model-api-key"
                            value="1"
                            class="ti-form-checkbox"
                        >

                        <span class="text-danger">
                            Remove currently stored API key
                        </span>

                    </label>

                </div>


                {{-- ACTIVE --}}
                <div>

                    <label
                        class="flex items-center gap-2"
                    >

                        <input
                            type="checkbox"
                            name="enabled"
                            id="ai-model-enabled"
                            value="1"
                            class="ti-form-checkbox"
                            checked
                        >

                        Active

                    </label>

                </div>


                {{-- TEST --}}
                <div>

                    <button
                        type="button"
                        id="test-ai-model"
                        class="ti-btn ti-btn-info-full"
                    >
                        <i class="ri-plug-line me-1"></i>
                        Test Connection
                    </button>


                    <span
                        id="ai-model-test-result"
                        class="ms-2 text-sm"
                    ></span>

                </div>

            </div>


            <div
                class="p-4
                       border-t
                       dark:border-white/10
                       flex
                       justify-end
                       gap-2"
            >

                <button
                    type="button"
                    id="cancel-ai-model-modal"
                    class="ti-btn ti-btn-secondary-full"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="ti-btn ti-btn-primary-full"
                >
                    <i class="ri-save-line me-1"></i>
                    Save Model
                </button>

            </div>

        </form>

    </div>
 </div>
</div>


{{-- ==========================================================
     ADD / EDIT AI AGENT MODAL
     ========================================================== --}}
<div
    id="ai-agent-modal"
    class="hidden fixed inset-0 z-[9999] bg-black/50 overflow-y-auto"
>

    <div
        class="min-h-full
               flex
               items-center
               justify-center
               px-4
               py-8"
    >

    <div
        class="bg-white
               dark:bg-bodybg
               rounded-lg
               shadow-xl
               max-h-[90vh]
               overflow-y-auto"
               style="border: 1px solid #dcdbdbe8;
                margin-top: 20px;
                margin-bottom: 20px;   
                width: 40vw;"
    >

        <form
            id="ai-agent-form"
            method="POST"
            action="{{
                route(
                    'admin.whatsapp.ai-agents.store'
                )
            }}"
        >

            @csrf


            <input
                type="hidden"
                name="_method"
                id="ai-agent-method"
                value=""
            >


            <div
                class="p-4
                       border-b
                       dark:border-white/10
                       flex
                       justify-between
                       items-center"
            >

                <div>

                    <h4
                        id="ai-agent-modal-title"
                        class="font-semibold text-lg"
                    >
                        Add AI Agent
                    </h4>

                    <p class="text-xs text-gray-500 mt-1">
                        Select a reusable AI Model Profile and define
                        the business prompt for this agent.
                    </p>

                </div>


                <button
                    type="button"
                    id="close-ai-agent-modal"
                    aria-label="Close"
                >
                    <i class="ri-close-line text-xl"></i>
                </button>

            </div>


            <div class="p-5">

                <div class="grid grid-cols-12 gap-5">


                    {{-- AGENT NAME --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label class="ti-form-label">
                            Agent Name
                        </label>

                        <input
                            type="text"
                            name="name"
                            id="ai-agent-name"
                            class="ti-form-input"
                            placeholder="Example: WhatsApp Sales Agent"
                            required
                        >

                    </div>


                    {{-- AGENT TYPE --}}
                    <div
                        class="md:col-span-6 col-span-12"
                    >

                        <label class="ti-form-label">
                            Agent Purpose
                        </label>

                        <select
                            name="agent_type"
                            id="ai-agent-type"
                            class="ti-form-select"
                            required
                        >

                            <option value="whatsapp">
                                WhatsApp
                            </option>

                            <option value="lead_scoring">
                                Lead Scoring
                            </option>

                            <option value="generic">
                                Generic / Future Use
                            </option>

                        </select>

                    </div>


                    {{-- MODEL PROFILE --}}
                    <div class="col-span-12">

                        <label class="ti-form-label">
                            AI Model Profile
                        </label>

                        <select
                            name="ai_model_profile_id"
                            id="ai-agent-profile"
                            class="ti-form-select"
                            required
                        >

                            <option value="">
                                Select AI Model Profile
                            </option>


                            @foreach(
                                $aiModelProfiles
                                as $profile
                            )

                                <option
                                    value="{{ $profile->id }}"
                                    {{
                                        !$profile->enabled
                                            ? 'disabled'
                                            : ''
                                    }}
                                >

                                    {{ $profile->name }}

                                    —

                                    {{
                                        strtoupper(
                                            $profile->provider
                                        )
                                    }}

                                    /

                                    {{ $profile->model }}

                                    @if(!$profile->enabled)

                                        (Inactive)

                                    @endif

                                </option>

                            @endforeach

                        </select>

                    </div>


                    {{-- PROMPT --}}
                    <div class="col-span-12">

                        <label class="ti-form-label">
                            Agent Prompt
                        </label>

                        <textarea
                            name="prompt"
                            id="ai-agent-prompt"
                            rows="18"
                            maxlength="30000"
                            class="form-control"
                            placeholder="Enter the complete business prompt for this agent..."
                            required
                        ></textarea>


                        <small class="text-gray-500">

                            The AI Model Profile controls provider,
                            model and API key.

                            This prompt controls the behaviour and
                            business role of this AI Agent.

                        </small>

                    </div>


                    {{-- ACTIVE --}}
                    <div class="col-span-12">

                        <label
                            class="flex items-center gap-2"
                        >

                            <input
                                type="checkbox"
                                name="enabled"
                                id="ai-agent-enabled"
                                value="1"
                                class="ti-form-checkbox"
                                checked
                            >

                            Active

                        </label>

                    </div>

                </div>

            </div>


            <div
                class="p-4
                       border-t
                       dark:border-white/10
                       flex
                       justify-end
                       gap-2"
            >

                <button
                    type="button"
                    id="cancel-ai-agent-modal"
                    class="ti-btn ti-btn-secondary-full"
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="ti-btn ti-btn-primary-full"
                >
                    <i class="ri-save-line me-1"></i>
                    Save Agent
                </button>

            </div>

        </form>

    </div>
</div>
</div>

@endsection


@push('scripts')

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
         * =====================================================
         * COMMON
         * =====================================================
         */

        const csrf =
            document
                .querySelector(
                    'meta[name="csrf-token"]'
                )
                ?.getAttribute(
                    'content'
                );


        /*
         * =====================================================
         * TAB SYSTEM
         * =====================================================
         */

        const tabs =
            document.querySelectorAll(
                '.ai-settings-tab'
            );


        const panels =
            document.querySelectorAll(
                '.ai-settings-panel'
            );


        function activateTab(
            target
        ) {

            panels.forEach(
                function (
                    panel
                ) {

                    panel
                        .classList
                        .add(
                            'hidden'
                        );

                }
            );


            tabs.forEach(
                function (
                    item
                ) {

                    item
                        .classList
                        .remove(
                            'ti-btn-primary-full'
                        );

                    item
                        .classList
                        .add(
                            'ti-btn-outline-primary'
                        );

                }
            );


            const panel =
                document.getElementById(
                    target
                );


            if (!panel) {
                return;
            }


            panel
                .classList
                .remove(
                    'hidden'
                );


            const activeButton =
                document.querySelector(
                    '.ai-settings-tab[data-target="'
                    + target
                    + '"]'
                );


            if (activeButton) {

                activeButton
                    .classList
                    .remove(
                        'ti-btn-outline-primary'
                    );

                activeButton
                    .classList
                    .add(
                        'ti-btn-primary-full'
                    );

            }


            try {

                localStorage.setItem(
                    'ai-agent-active-tab',
                    target
                );

            } catch (
                error
            ) {
                // Ignore browser storage error.
            }

        }


        tabs.forEach(
            function (
                tab
            ) {

                tab.addEventListener(
                    'click',
                    function () {

                        activateTab(
                            this.getAttribute(
                                'data-target'
                            )
                        );

                    }
                );

            }
        );


        let savedTab =
            null;


        try {

            savedTab =
                localStorage.getItem(
                    'ai-agent-active-tab'
                );

        } catch (
            error
        ) {
            savedTab =
                null;
        }


        if (
            savedTab
            &&
            document.getElementById(
                savedTab
            )
        ) {

            activateTab(
                savedTab
            );

        } else {

            activateTab(
                'ai-models-settings'
            );

        }


        /*
         * =====================================================
         * HOT SCORE AUTO CALCULATION
         * =====================================================
         */

        const neutralMax =
            document.getElementById(
                'neutral-max'
            );


        const hotBegins =
            document.getElementById(
                'hot-begins-at'
            );


        function updateHotScore() {

            if (
                !neutralMax
                ||
                !hotBegins
            ) {
                return;
            }


            const neutral =
                parseInt(
                    neutralMax.value,
                    10
                );


            hotBegins.value =
                Number.isNaN(
                    neutral
                )
                    ? ''
                    : neutral + 1;

        }


        neutralMax
            ?.addEventListener(
                'input',
                updateHotScore
            );


        /*
         * =====================================================
         * AI MODEL MODAL
         * =====================================================
         */

        const modelModal =
            document.getElementById(
                'ai-model-modal'
            );


        const modelForm =
            document.getElementById(
                'ai-model-form'
            );


        const modelMethod =
            document.getElementById(
                'ai-model-method'
            );


        const modelProfileId =
            document.getElementById(
                'ai-model-profile-id'
            );


        const modelName =
            document.getElementById(
                'ai-model-name'
            );


        const modelProvider =
            document.getElementById(
                'ai-model-provider'
            );


        const modelModel =
            document.getElementById(
                'ai-model-model'
            );


        const modelApiKey =
            document.getElementById(
                'ai-model-api-key'
            );


        const modelEnabled =
            document.getElementById(
                'ai-model-enabled'
            );


        const clearApiKey =
            document.getElementById(
                'clear-ai-model-api-key'
            );


        const clearApiKeyWrapper =
            document.getElementById(
                'clear-api-key-wrapper'
            );


        const modelTitle =
            document.getElementById(
                'ai-model-modal-title'
            );


        const modelTestResult =
            document.getElementById(
                'ai-model-test-result'
            );


        const modelStoreUrl =
            @json(
                route(
                    'admin.whatsapp.ai-models.store'
                )
            );


        const modelUpdateUrl =
            @json(
                route(
                    'admin.whatsapp.ai-models.update',
                    'PROFILE_ID'
                )
            );


        const modelTestUrl =
            @json(
                route(
                    'admin.whatsapp.ai-models.test-connection'
                )
            );


        function openModelModal() {

            modelModal
                ?.classList
                .remove(
                    'hidden'
                );

        }


        function closeModelModal() {

            modelModal
                ?.classList
                .add(
                    'hidden'
                );

        }


        function resetModelModal() {

            if (!modelForm) {
                return;
            }


            modelForm.action =
                modelStoreUrl;


            modelMethod.value =
                '';


            modelProfileId.value =
                '';


            modelName.value =
                '';


            modelProvider.value =
                'openai';


            modelModel.value =
                '';


            modelApiKey.value =
                '';


            modelApiKey.required =
                true;


            modelEnabled.checked =
                true;


            if (clearApiKey) {
                clearApiKey.checked =
                    false;
            }


            clearApiKeyWrapper
                ?.classList
                .add(
                    'hidden'
                );


            modelTitle.textContent =
                'Add AI Model';


            modelTestResult.textContent =
                '';

        }


        document
            .getElementById(
                'add-ai-model-button'
            )
            ?.addEventListener(
                'click',
                function () {

                    resetModelModal();

                    openModelModal();

                }
            );


        document
            .querySelectorAll(
                '.edit-ai-model'
            )
            .forEach(
                function (
                    button
                ) {

                    button.addEventListener(
                        'click',
                        function () {

                            const id =
                                this.dataset.id;


                            modelForm.action =
                                modelUpdateUrl
                                    .replace(
                                        'PROFILE_ID',
                                        id
                                    );


                            modelMethod.value =
                                'PUT';


                            modelProfileId.value =
                                id;


                            modelName.value =
                                this.dataset.name
                                || '';


                            modelProvider.value =
                                this.dataset.provider
                                || 'openai';


                            modelModel.value =
                                this.dataset.model
                                || '';


                            modelApiKey.value =
                                '';


                            modelApiKey.required =
                                false;


                            modelEnabled.checked =
                                this.dataset.enabled
                                === '1';


                            if (
                                clearApiKey
                            ) {
                                clearApiKey.checked =
                                    false;
                            }


                            clearApiKeyWrapper
                                ?.classList
                                .remove(
                                    'hidden'
                                );


                            modelTitle.textContent =
                                'Edit AI Model';


                            modelTestResult.textContent =
                                '';


                            openModelModal();

                        }
                    );

                }
            );


        document
            .getElementById(
                'close-ai-model-modal'
            )
            ?.addEventListener(
                'click',
                closeModelModal
            );


        document
            .getElementById(
                'cancel-ai-model-modal'
            )
            ?.addEventListener(
                'click',
                closeModelModal
            );


        /*
         * =====================================================
         * TEST MODEL IN MODAL
         * =====================================================
         */

        document
            .getElementById(
                'test-ai-model'
            )
            ?.addEventListener(
                'click',
                async function () {

                    modelTestResult.textContent =
                        'Testing...';


                    modelTestResult.className =
                        'ms-2 text-sm text-info';


                    try {

                        const response =
                            await fetch(
                                modelTestUrl,
                                {
                                    method:
                                        'POST',

                                    headers: {
                                        'Content-Type':
                                            'application/json',

                                        'Accept':
                                            'application/json',

                                        'X-CSRF-TOKEN':
                                            csrf,

                                        'X-Requested-With':
                                            'XMLHttpRequest'
                                    },

                                    credentials:
                                        'same-origin',

                                    body:
                                        JSON.stringify({
                                            profile_id:
                                                modelProfileId.value
                                                || null,

                                            provider:
                                                modelProvider.value,

                                            model:
                                                modelModel.value,

                                            api_key:
                                                modelApiKey.value
                                                || null
                                        })
                                }
                            );


                        const data =
                            await response.json();


                        if (!response.ok) {

                            throw new Error(
                                data.message
                                ||
                                'Connection failed.'
                            );

                        }


                        modelTestResult.textContent =
                            '✓ '
                            + data.message;


                        modelTestResult.className =
                            'ms-2 text-sm text-success';

                    } catch (
                        error
                    ) {

                        modelTestResult.textContent =
                            '✗ '
                            + (
                                error.message
                                ||
                                'Connection failed.'
                            );


                        modelTestResult.className =
                            'ms-2 text-sm text-danger';

                    }

                }
            );


        /*
         * =====================================================
         * TEST EXISTING MODEL
         * =====================================================
         */

        document
            .querySelectorAll(
                '.test-existing-ai-model'
            )
            .forEach(
                function (
                    button
                ) {

                    button.addEventListener(
                        'click',
                        async function () {

                            const result =
                                document.getElementById(
                                    this.dataset.resultId
                                );


                            if (result) {

                                result.textContent =
                                    'Testing...';

                                result.className =
                                    'text-xs mt-2 text-info';

                            }


                            try {

                                const response =
                                    await fetch(
                                        modelTestUrl,
                                        {
                                            method:
                                                'POST',

                                            headers: {
                                                'Content-Type':
                                                    'application/json',

                                                'Accept':
                                                    'application/json',

                                                'X-CSRF-TOKEN':
                                                    csrf,

                                                'X-Requested-With':
                                                    'XMLHttpRequest'
                                            },

                                            credentials:
                                                'same-origin',

                                            body:
                                                JSON.stringify({
                                                    profile_id:
                                                        this.dataset.id,

                                                    provider:
                                                        this.dataset.provider,

                                                    model:
                                                        this.dataset.model,

                                                    api_key:
                                                        null
                                                })
                                        }
                                    );


                                const data =
                                    await response.json();


                                if (!response.ok) {

                                    throw new Error(
                                        data.message
                                        ||
                                        'Connection failed.'
                                    );

                                }


                                if (result) {

                                    result.textContent =
                                        '✓ Connection successful';

                                    result.className =
                                        'text-xs mt-2 text-success';

                                }

                            } catch (
                                error
                            ) {

                                if (result) {

                                    result.textContent =
                                        '✗ '
                                        + (
                                            error.message
                                            ||
                                            'Connection failed.'
                                        );

                                    result.className =
                                        'text-xs mt-2 text-danger';

                                }

                            }

                        }
                    );

                }
            );


        /*
         * =====================================================
         * AI AGENT MODAL
         * =====================================================
         */

        const agentModal =
            document.getElementById(
                'ai-agent-modal'
            );


        const agentForm =
            document.getElementById(
                'ai-agent-form'
            );


        const agentMethod =
            document.getElementById(
                'ai-agent-method'
            );


        const agentName =
            document.getElementById(
                'ai-agent-name'
            );


        const agentType =
            document.getElementById(
                'ai-agent-type'
            );


        const agentProfile =
            document.getElementById(
                'ai-agent-profile'
            );


        const agentPrompt =
            document.getElementById(
                'ai-agent-prompt'
            );


        const agentEnabled =
            document.getElementById(
                'ai-agent-enabled'
            );


        const agentTitle =
            document.getElementById(
                'ai-agent-modal-title'
            );


        const agentStoreUrl =
            @json(
                route(
                    'admin.whatsapp.ai-agents.store'
                )
            );


        const agentUpdateUrl =
            @json(
                route(
                    'admin.whatsapp.ai-agents.update',
                    'AGENT_ID'
                )
            );


        function openAgentModal() {

            agentModal
                ?.classList
                .remove(
                    'hidden'
                );

        }


        function closeAgentModal() {

            agentModal
                ?.classList
                .add(
                    'hidden'
                );

        }


        function resetAgentModal() {

            agentForm.action =
                agentStoreUrl;


            agentMethod.value =
                '';


            agentName.value =
                '';


            agentType.value =
                'generic';


            agentProfile.value =
                '';


            agentPrompt.value =
                '';


            agentEnabled.checked =
                true;


            agentTitle.textContent =
                'Add AI Agent';

        }


        function decodeBase64Utf8(
            value
        ) {

            if (!value) {
                return '';
            }


            try {

                const binary =
                    atob(
                        value
                    );


                const bytes =
                    Uint8Array.from(
                        binary,
                        function (
                            character
                        ) {
                            return character
                                .charCodeAt(0);
                        }
                    );


                return new TextDecoder(
                    'utf-8'
                ).decode(
                    bytes
                );

            } catch (
                error
            ) {

                return '';

            }

        }


        document
            .getElementById(
                'add-ai-agent-button'
            )
            ?.addEventListener(
                'click',
                function () {

                    resetAgentModal();

                    openAgentModal();

                }
            );


        document
            .querySelectorAll(
                '.edit-ai-agent'
            )
            .forEach(
                function (
                    button
                ) {

                    button.addEventListener(
                        'click',
                        function () {

                            const id =
                                this.dataset.id;


                            agentForm.action =
                                agentUpdateUrl
                                    .replace(
                                        'AGENT_ID',
                                        id
                                    );


                            agentMethod.value =
                                'PUT';


                            agentName.value =
                                this.dataset.name
                                || '';


                            agentType.value =
                                this.dataset.type
                                || 'generic';


                            agentProfile.value =
                                this.dataset.profile
                                || '';


                            agentPrompt.value =
                                decodeBase64Utf8(
                                    this.dataset.prompt
                                    || ''
                                );


                            agentEnabled.checked =
                                this.dataset.enabled
                                === '1';


                            agentTitle.textContent =
                                'Edit AI Agent';


                            openAgentModal();

                        }
                    );

                }
            );


        document
            .getElementById(
                'close-ai-agent-modal'
            )
            ?.addEventListener(
                'click',
                closeAgentModal
            );


        document
            .getElementById(
                'cancel-ai-agent-modal'
            )
            ?.addEventListener(
                'click',
                closeAgentModal
            );


        /*
         * =====================================================
         * BACKDROP CLICK
         * =====================================================
         */

        modelModal
            ?.addEventListener(
                'click',
                function (
                    event
                ) {

                    if (
                        event.target
                        === modelModal
                    ) {
                        closeModelModal();
                    }

                }
            );


        agentModal
            ?.addEventListener(
                'click',
                function (
                    event
                ) {

                    if (
                        event.target
                        === agentModal
                    ) {
                        closeAgentModal();
                    }

                }
            );


        /*
         * =====================================================
         * ESCAPE KEY
         * =====================================================
         */

        document.addEventListener(
            'keydown',
            function (
                event
            ) {

                if (
                    event.key
                    !== 'Escape'
                ) {
                    return;
                }


                if (
                    modelModal
                    &&
                    !modelModal
                        .classList
                        .contains(
                            'hidden'
                        )
                ) {

                    closeModelModal();

                }


                if (
                    agentModal
                    &&
                    !agentModal
                        .classList
                        .contains(
                            'hidden'
                        )
                ) {

                    closeAgentModal();

                }

            }
        );

    }
);

</script>

@endpush