@extends('admin.layouts.header')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('assets/admin/css/crm/whatsapp/admin.css') }}?v=9"
>


@php

    $formTemplate =
        $editingTemplate
        ??
        null;


    /*
     * Helps us reopen the correct modal after validation failure.
     * Controller changes are NOT required.
     * Laravel automatically carries old input after validation redirect.
     */
    $oldFormContext =
        old(
            'form_context'
        );

@endphp


<main class="msg91-page msg91-template-registry">


    {{-- =====================================================
         PAGE HEADER
         ===================================================== --}}

    <header class="msg91-page__heading">

        <div class="msg91-page__heading-copy">

            <p class="msg91-eyebrow">
                MSG91 WhatsApp
            </p>

            <h1>
                Template Registry
            </h1>

            <p>
                Create templates in MSG91. Once MSG91 approves one, it becomes ready to use in CRM automatically.
            </p>

        </div>


        <div class="msg91-page__actions">

            @if($canManageAll)

                <a
                    class="msg91-button msg91-button--secondary"
                    href="{{ route('admin.msg91.admin.index') }}"
                >

                    <i class="ri-user-settings-line"></i>

                    <span>
                        Sender Access
                    </span>

                </a>

            @else

                <a
                    class="msg91-button msg91-button--secondary"
                    href="{{ route('admin.msg91.campaigns.index') }}"
                >

                    <i class="ri-history-line"></i>

                    <span>
                        Campaign Records
                    </span>

                </a>

            @endif

        </div>

    </header>



    {{-- =====================================================
         FLASH MESSAGES
         ===================================================== --}}

    @if(session('success'))

        <div class="alert alert-success mb-3">

            {{ session('success') }}

        </div>

    @endif


    @if($errors->any())

        <div class="alert alert-danger mb-3">

            <ul class="mb-0">

                @foreach($errors->all() as $error)

                    <li>
                        {{ $error }}
                    </li>

                @endforeach

            </ul>

        </div>

    @endif



    {{-- =====================================================
         SYNC APPROVED TEMPLATES
         ===================================================== --}}

    <section class="msg91-card msg91-template-section">

        <header class="msg91-card__header">

            <div>

                <h2>
                            Refresh MSG91 Templates
                </h2>

                <p>
                            Refresh approval status from MSG91 now. CRM also refreshes automatically every five minutes.
                </p>

            </div>


            <span class="msg91-section-icon">

                <i class="ri-refresh-line"></i>

            </span>

        </header>


        <div class="msg91-template-section__body">

            <div class="msg91-info-note">

                <i class="ri-information-line"></i>

                <p>
                    Sync only reads template metadata from MSG91. It does not send any WhatsApp message.
                </p>

            </div>


            <div class="msg91-sync-list">

                @forelse($senders as $sender)

                    <div class="msg91-sync-row">

                        <div class="msg91-sync-row__sender">

                            <span class="msg91-sync-row__icon">

                                <i class="ri-whatsapp-line"></i>

                            </span>


                            <div>

                                <strong>

                                    {{ $sender->integrated_number }}

                                </strong>


                                <span>

                                    {{ $sender->user?->name ?? 'Unknown user' }}

                                </span>

                            </div>

                        </div>


                        <form
                            method="POST"
                            action="{{ route('admin.msg91.templates.sync', $sender) }}"
                        >

                            @csrf


                            <button
                                class="msg91-button msg91-button--secondary"
                                type="submit"
                            >

                                <i class="ri-refresh-line"></i>

                                Refresh Now

                            </button>

                        </form>

                    </div>


                @empty

                    <div class="msg91-empty-state">

                        <span>

                            <i class="ri-whatsapp-line"></i>

                        </span>


                        <strong>
                            No active sender available
                        </strong>


                        <p>
                            Configure an active integrated WhatsApp sender in Sender Access first.
                        </p>

                    </div>

                @endforelse

            </div>

        </div>

    </section>



    {{-- =====================================================
         TEMPLATE ACTIONS
         ===================================================== --}}

    <section class="msg91-template-actions">


        {{-- CREATE TEMPLATE --}}
        <article class="msg91-card msg91-template-action-card">

            <div class="msg91-template-action-card__content">

                <div class="msg91-template-action-card__info">

                    <span class="msg91-template-action-card__icon">

                        <i class="ri-add-circle-line"></i>

                    </span>


                    <div>

                        <h2>
                            Create Template in MSG91
                        </h2>

                        <p>
                            Submit a new WhatsApp template to MSG91 for approval.
                        </p>

                    </div>

                </div>


                <button
                    type="button"
                    class="msg91-button"
                    data-msg91-open-modal="msg91-create-template-modal"
                >

                    <i class="ri-add-line"></i>

                    Create Template

                </button>

            </div>

        </article>



        @if(false) {{-- Old manual registration flow is intentionally unavailable. --}}
        {{-- REGISTER TEMPLATE --}}
        <article class="msg91-card msg91-template-action-card">

            <div class="msg91-template-action-card__content">

                <div class="msg91-template-action-card__info">

                    <span class="msg91-template-action-card__icon">

                        <i class="ri-link"></i>

                    </span>


                    <div>

                        <h2>
                            Register Approved Template
                        </h2>

                        <p>
                            Map an approved MSG91 template into CRM campaigns.
                        </p>

                    </div>

                </div>


                <button
                    type="button"
                    class="msg91-button msg91-button--secondary"
                    data-msg91-open-modal="msg91-register-template-modal"
                >

                    <i class="ri-link"></i>

                    Register Template

                </button>

            </div>

        </article>
        @endif

    </section>



    {{-- =====================================================
         REGISTERED TEMPLATE DIRECTORY
         ===================================================== --}}

    <section class="msg91-card msg91-template-list-card">

        <header class="msg91-card__header">

            <div>

                <h2>
                    Registered Templates
                </h2>

                <p>
                    Review MSG91 approval, CRM availability and sender mappings.
                </p>

            </div>


            <span class="msg91-record-count">

                {{ number_format($templates->total()) }}

                {{
                    Str::plural(
                        'template',
                        $templates->total()
                    )
                }}

            </span>

        </header>



        {{-- =================================================
             DESKTOP TABLE
             ================================================= --}}

        <div class="msg91-template-table-wrap">

            <table class="msg91-template-table">

                <thead>

                    <tr>

                        <th>
                            Template
                        </th>

                        <th>
                            Language
                        </th>

                        <th>
                            Visible in CRM
                        </th>

                        <th>
                            Sender Mapping
                        </th>

                        <th>
                            Last Updated
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($templates as $template)

                        <tr>


                            {{-- TEMPLATE --}}
                            <td>

                                <div class="msg91-template-name">

                                    <strong>

                                        {{ $template->name }}

                                    </strong>


                                    <div>

                                        <span class="msg91-category-badge">

                                            {{ $template->category ?? 'No category' }}

                                        </span>


                                        <span
                                            class="
                                                msg91-provider-status
                                                msg91-provider-status--{{ strtolower($template->status ?? 'unknown') }}
                                            "
                                        >

                                            {{ ucfirst($template->status ?? 'unknown') }}

                                        </span>

                                    </div>

                                </div>

                            </td>



                            {{-- LANGUAGE --}}
                            <td>

                                <span class="msg91-language-badge">

                                    {{ strtoupper($template->language) }}

                                </span>

                            </td>



                            {{-- CRM --}}
                            <td>

                                <span
                                    class="
                                        msg91-crm-status
                                        {{
                                            $template->crm_enabled
                                                ? 'msg91-crm-status--enabled'
                                                : 'msg91-crm-status--disabled'
                                        }}
                                    "
                                >

                                    <span></span>


                                    {{
                                        $template->crm_enabled
                                            ? 'Enabled'
                                            : 'Disabled'
                                    }}

                                </span>

                            </td>



                            {{-- SENDERS --}}
                            <td>

                                @forelse(
                                    $template->bindings
                                    as
                                    $binding
                                )

                                    <div class="msg91-binding">

                                        <div>

                                            <i class="ri-whatsapp-line"></i>


                                            <strong>

                                                {{
                                                    $binding
                                                        ->sender
                                                        ?->integrated_number
                                                    ??
                                                    'Not mapped'
                                                }}

                                            </strong>

                                        </div>


                                        <span>

                                            {{ ucfirst($binding->provider_status ?? 'unknown') }}


                                            @if($binding->last_synced_at)

                                                ·

                                                {{
                                                    optional(
                                                        $binding->last_synced_at
                                                    )
                                                    ->format(
                                                        'd M Y H:i'
                                                    )
                                                }}

                                            @endif

                                        </span>

                                    </div>


                                @empty


                                    <span class="msg91-not-mapped">

                                        Not mapped

                                    </span>


                                @endforelse

                            </td>



                            {{-- DATE --}}
                            <td>

                                <span class="msg91-template-date">

                                    {{
                                        optional(
                                            $template->updated_at
                                        )
                                        ->format(
                                            'd M Y'
                                        )
                                    }}

                                </span>


                                <small>

                                    {{
                                        optional(
                                            $template->updated_at
                                        )
                                        ->format(
                                            'H:i'
                                        )
                                    }}

                                </small>

                            </td>



                            {{-- ACTION --}}
                            <td>

                                @if($canManageVisibility)
                                    <form method="POST" action="{{ route('admin.msg91.templates.save') }}">
                                        @csrf
                                        <input type="hidden" name="template_id" value="{{ $template->id }}">
                                        <input type="hidden" name="crm_enabled" value="{{ $template->crm_enabled ? '0' : '1' }}">
                                        <button type="submit" class="msg91-edit-button">
                                            <i class="ri-eye{{ $template->crm_enabled ? '-off' : '' }}-line"></i>
                                            {{ $template->crm_enabled ? 'Hide' : 'Show' }}
                                        </button>
                                    </form>
                                @else
                                @if($canManageVisibility)
                                <form
                                    method="POST"
                                    action="{{ route('admin.msg91.templates.destroy', $template) }}"
                                    class="d-inline"
                                    onsubmit="return confirm('Delete this template from MSG91 and CRM? It will no longer be available for campaigns. Campaign history will remain محفوظ.');"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="msg91-edit-button"
                                        style="border-color:#dc3545;color:#dc3545;"
                                        title="Delete from MSG91 and CRM"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                        Delete
                                    </button>
                                </form>
                            @endif
                                    <span class="msg91-no-action">—</span>
                                @endif

                            </td>

                        </tr>


                    @empty


                        <tr>

                            <td
                                colspan="6"
                                class="msg91-empty"
                            >

                                <div class="msg91-empty__icon">

                                    <i class="ri-file-list-3-line"></i>

                                </div>


                                <strong>
                                    No templates registered
                                </strong>


                                <span>
                                    Create or register your first MSG91 template.
                                </span>

                            </td>

                        </tr>


                    @endforelse

                </tbody>

            </table>

        </div>



        {{-- =================================================
             MOBILE CARDS
             ================================================= --}}

        <div class="msg91-template-mobile">

            @forelse($templates as $template)

                <article class="msg91-template-mobile-card">


                    <div class="msg91-template-mobile-card__head">

                        <div>

                            <strong>

                                {{ $template->name }}

                            </strong>


                            <span>

                                {{ $template->category ?? 'No category' }}

                            </span>

                        </div>


                        <span
                            class="
                                msg91-provider-status
                                msg91-provider-status--{{ strtolower($template->status ?? 'unknown') }}
                            "
                        >

                            {{ ucfirst($template->status ?? 'unknown') }}

                        </span>

                    </div>



                    <div class="msg91-template-mobile-card__meta">


                        <div>

                            <small>
                                Language
                            </small>

                            <strong>

                                {{ strtoupper($template->language) }}

                            </strong>

                        </div>



                        <div>

                            <small>
                                CRM
                            </small>

                            <strong>

                                {{
                                    $template->crm_enabled
                                        ? 'Enabled'
                                        : 'Disabled'
                                }}

                            </strong>

                        </div>



                        <div>

                            <small>
                                Updated
                            </small>

                            <strong>

                                {{
                                    optional(
                                        $template->updated_at
                                    )
                                    ->format(
                                        'd M Y'
                                    )
                                }}

                            </strong>

                        </div>

                    </div>



                    <div class="msg91-template-mobile-card__bindings">

                        <small>
                            Sender Mapping
                        </small>


                        @forelse(
                            $template->bindings
                            as
                            $binding
                        )

                            <div>

                                <strong>

                                    {{
                                        $binding
                                            ->sender
                                            ?->integrated_number
                                        ??
                                        'Not mapped'
                                    }}

                                </strong>


                                <span>

                                    {{ ucfirst($binding->provider_status ?? 'unknown') }}

                                </span>

                            </div>


                        @empty


                            <span class="msg91-not-mapped">

                                Not mapped

                            </span>


                        @endforelse

                    </div>



                    @if($canManageVisibility)
                        <form method="POST" action="{{ route('admin.msg91.templates.save') }}">
                            @csrf
                            <input type="hidden" name="template_id" value="{{ $template->id }}">
                            <input type="hidden" name="crm_enabled" value="{{ $template->crm_enabled ? '0' : '1' }}">
                            <button type="submit" class="msg91-edit-button msg91-edit-button--mobile">
                                {{ $template->crm_enabled ? 'Hide from CRM' : 'Show in CRM' }}
                            </button>
                        </form>
                    @endif

                    @if($canManageVisibility)
                    <form
                        method="POST"
                        action="{{ route('admin.msg91.templates.destroy', $template) }}"
                        class="d-inline"
                        onsubmit="return confirm('Delete this template from MSG91 and CRM? It will no longer be available for campaigns. Campaign history will remain safe.');"
                    >
                        @csrf
                        @method('DELETE')

                        <button
                            type="submit"
                            class="msg91-edit-button msg91-edit-button--mobile"
                            style="border-color:#dc3545;color:#dc3545;"
                        >
                            Delete from MSG91
                        </button>
                    </form>
                @endif

                </article>


            @empty


                <div class="msg91-empty">

                    No templates registered.

                </div>


            @endforelse

        </div>

    </section>



    {{-- =====================================================
         PAGINATION
         ===================================================== --}}

    @if($templates->hasPages())

        <div class="msg91-pagination">

            {{ $templates->links() }}

        </div>

    @endif

</main>



{{-- =========================================================
     CREATE TEMPLATE MODAL
     ========================================================= --}}

<div
    class="msg91-modal"
    id="msg91-create-template-modal"
    aria-hidden="true"
>

    <div
        class="msg91-modal__backdrop"
        data-msg91-close-modal
    ></div>


    <div
        class="msg91-modal__dialog msg91-modal__dialog--large"
        role="dialog"
        aria-modal="true"
        aria-labelledby="msg91-create-template-title"
    >

        <header class="msg91-modal__header">

            <div>

                <p class="msg91-modal__eyebrow">
                    MSG91 WhatsApp
                </p>

                <h2 id="msg91-create-template-title">
                    Create Template
                </h2>

                <p>
                    Submit a new WhatsApp template to MSG91 for approval.
                </p>

            </div>


            <button
                type="button"
                class="msg91-modal__close"
                data-msg91-close-modal
                aria-label="Close"
            >

                <i class="ri-close-line"></i>

            </button>

        </header>


        <form
            method="POST"
            action="{{ route('admin.msg91.templates.create') }}"
            class="msg91-template-form"
            data-msg91-create-template-form
        >

            @csrf


            <input
                type="hidden"
                name="form_context"
                value="create_template"
            >


            <div class="msg91-modal__scroll">
                <div class="msg91-form-feedback" data-msg91-create-feedback role="status" aria-live="polite" hidden></div>

                @if($errors->any() && old('form_context') === 'create_template')
                    <div class="alert alert-danger mb-4" role="alert">
                        <strong>Template creation failed</strong>

                        <ul class="mb-0 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="msg91-modal-info">

                    <i class="ri-information-line"></i>

                    <span>
                        After MSG91 approves the template, CRM will automatically make it ready for campaigns. No mapping is required.
                    </span>

                </div>



                <div class="msg91-form-grid msg91-form-grid--2">


                    {{-- NAME --}}
                    <div class="msg91-field">

                        <label>
                            Template Name
                        </label>


                        <input
                            required
                            name="name"
                            value="{{
                                $oldFormContext === 'create_template'
                                    ? old('name')
                                    : ''
                            }}"
                            placeholder="e.g. accretion_demo_followup"
                            pattern="[a-z0-9_]+"
                            maxlength="191"
                            title="Use lowercase letters, numbers and underscores only. Do not use spaces, hyphens or uppercase letters."
                            aria-describedby="msg91-template-name-help"
                        >


                        <small id="msg91-template-name-help">
                            Use lowercase letters (a-z), numbers (0-9), and underscores (_). Do not use spaces, hyphens, or uppercase letters.
                        </small>

                    </div>



                    {{-- LANGUAGE --}}
                    <div class="msg91-field">

                        <label>
                            Language
                        </label>


                        <select required name="language">
                            <option value="en" {{ $oldFormContext === 'create_template' && old('language', 'en') === 'en' ? 'selected' : '' }}>English</option>
                            <option value="hi" {{ $oldFormContext === 'create_template' && old('language') === 'hi' ? 'selected' : '' }}>Hindi</option>
                        </select>

                    </div>



                    {{-- CATEGORY --}}
                    <div class="msg91-field">

                        <label>
                            Category
                        </label>


                        <select name="category">

                            <option
                                value="UTILITY"
                                {{ $oldFormContext === 'create_template' && old('category', 'UTILITY') === 'UTILITY' ? 'selected' : '' }}
                            >
                                Utility
                            </option>


                            <option
                                value="MARKETING"
                                {{ $oldFormContext === 'create_template' && old('category') === 'MARKETING' ? 'selected' : '' }}
                            >
                                Marketing
                            </option>


                            <option
                                value="AUTHENTICATION"
                                {{ $oldFormContext === 'create_template' && old('category') === 'AUTHENTICATION' ? 'selected' : '' }}
                            >
                                Authentication
                            </option>

                        </select>

                    </div>



                    {{-- SENDER --}}
                    <div class="msg91-field">

                        <label>
                            {{ $canManageAll ? 'Eligible Team Members' : 'Your Verified Sender' }}
                        </label>


                        @if($canManageAll)
                            <div class="msg91-member-selector" data-msg91-member-selector>
                                <label class="msg91-member-selector__all">
                                    <input type="checkbox" data-msg91-select-all-senders>
                                    <span>Select all eligible members</span>
                                </label>
                                <div class="msg91-member-selector__list">
                                    @foreach($senders as $sender)
                                        <label class="msg91-member-selector__option">
                                            <input
                                                type="checkbox"
                                                name="sender_ids[]"
                                                value="{{ $sender->id }}"
                                                data-msg91-sender-checkbox
                                                {{ in_array((string) $sender->id, array_map('strval', (array) old('sender_ids', [])), true) ? 'checked' : '' }}
                                            >
                                            <span>
                                                <strong>{{ $sender->user?->name }}</strong>
                                                <small>{{ $sender->integrated_number }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <small>Select one or more members. The template is submitted to MSG91 for each selected member's verified sender.</small>
                        @else
                            <select required name="sender_ids[]">
                                <option value="">Select sender</option>
                                @foreach($senders as $sender)
                                    <option
                                        value="{{ $sender->id }}"
                                        {{ in_array((string) $sender->id, array_map('strval', (array) old('sender_ids', $senders->take(1)->pluck('id')->all())), true) ? 'selected' : '' }}
                                    >
                                        {{ $sender->integrated_number }} · {{ $sender->user?->name }}
                                    </option>
                                @endforeach
                            </select>
                        @endif

                    </div>

                </div>



                {{-- BODY --}}
                <div class="msg91-field msg91-field--full">

                    <label>
                        Template Body
                    </label>


                    <textarea
                        required
                        name="body"
                        rows="4"
                        placeholder="Hello @{{1}}, this is a message from Accretion Aviation."
                    >{{ $oldFormContext === 'create_template' ? old('body') : '' }}</textarea>


                    <small>
                        MSG91 variables use the format @{{1}}, @{{2}}, @{{3}}...
                    </small>

                </div>



                {{-- FOOTER --}}
                <div class="msg91-field msg91-field--full">

                    <label>
                        Footer
                    </label>


                    <input
                        name="footer"
                        maxlength="60"
                        value="{{
                            $oldFormContext === 'create_template'
                                ? old('footer')
                                : ''
                        }}"
                        placeholder="Optional"
                    >

                </div>



                {{-- VARIABLE MAPPING --}}
                <div class="msg91-variable-block">

                    <div class="msg91-variable-block__heading">

                        <div>

                            <strong>
                                Body Variable Mapping
                            </strong>

                            <span>
                                Select what each variable in this template should show when it is sent.
                            </span>

                        </div>


                        <span class="msg91-variable-help">
                            @{{1}} – @{{6}}
                        </span>

                    </div>


                    <div class="msg91-variable-grid">

                        @foreach(range(1, 6) as $index)

                            <div class="msg91-field">

                                <label>
                                    Body {{ $index }}
                                </label>


                                <select name="body_{{ $index }}">

                                    <option value="">
                                        Not used
                                    </option>


                                    @foreach(
                                        [
                                            'customer_name' =>
                                                'Customer name',

                                            'customer_email' =>
                                                'Customer email',

                                            'product_name' =>
                                                'Product name',

                                            'service_date' =>
                                                'Service date',

                                            'next_followup_date' =>
                                                'Next follow-up date',

                                            'salesperson_name' =>
                                                'Salesperson name',
                                        ]
                                        as
                                        $value
                                        =>
                                        $label
                                    )

                                        <option
                                            value="{{ $value }}"
                                           {{ old('body_'.$index, '') === $value ? 'selected' : '' }}
                                        >

                                            {{ $label }}

                                        </option>

                                    @endforeach

                                </select>

                            </div>

                        @endforeach

                    </div>

                </div>
            </div>



            <footer class="msg91-modal__footer">

                <button
                    type="button"
                    class="msg91-button msg91-button--secondary"
                    data-msg91-close-modal
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="msg91-button"
                    data-msg91-create-submit
                >
                    <i class="ri-send-plane-line" data-msg91-create-submit-icon></i>
                    <span data-msg91-create-submit-label>Submit to MSG91</span>

                </button>

            </footer>

        </form>

    </div>

</div>



@if(false) {{-- The former manual registration and mapping screen is intentionally unavailable. --}}
{{-- =========================================================
     REGISTER / EDIT TEMPLATE MODAL
     ========================================================= --}}

<div
    class="msg91-modal"
    id="msg91-register-template-modal"
    aria-hidden="true"
>

    <div
        class="msg91-modal__backdrop"
        data-msg91-close-modal
    ></div>


    <div
        class="msg91-modal__dialog msg91-modal__dialog--large"
        role="dialog"
        aria-modal="true"
        aria-labelledby="msg91-register-template-title"
    >

        <header class="msg91-modal__header">

            <div>

                <p class="msg91-modal__eyebrow">
                    MSG91 WhatsApp
                </p>


                <h2 id="msg91-register-template-title">

                    {{
                        $formTemplate
                            ? 'Edit Template Mapping'
                            : 'Register Approved Template'
                    }}

                </h2>


                <p>

                    {{
                        $formTemplate
                            ? 'Update the CRM mapping for this MSG91 template.'
                            : 'Map an already approved MSG91 template into CRM.'
                    }}

                </p>

            </div>


            <button
                type="button"
                class="msg91-modal__close"
                data-msg91-close-modal
                aria-label="Close"
            >

                <i class="ri-close-line"></i>

            </button>

        </header>


        <form
            method="POST"
            action="{{ route('admin.msg91.templates.save') }}"
            class="msg91-template-form"
        >

            @csrf


            <input
                type="hidden"
                name="form_context"
                value="register_template"
            >


            @if($formTemplate)

                <input
                    type="hidden"
                    name="template_id"
                    value="{{ $formTemplate->id }}"
                >

            @endif


            <div class="msg91-modal__scroll">

                <div class="msg91-modal-info">

                    <i class="ri-information-line"></i>

                    <span>
                        Use this only for a template that already exists in MSG91.
                    </span>

                </div>



                <div class="msg91-form-grid msg91-form-grid--2">


                    {{-- TEMPLATE NAME --}}
                    <div class="msg91-field">

                        <label>
                            Template Name
                        </label>


                        <input
                            required
                            name="name"
                            value="{{
                                old(
                                    'name',
                                    $formTemplate?->name
                                )
                            }}"
                            placeholder="e.g. booking_update"
                        >

                    </div>



                    {{-- LANGUAGE --}}
                    <div class="msg91-field">

                        <label>
                            Language
                        </label>


                        <input
                            required
                            name="language"
                            value="{{
                                old(
                                    'language',
                                    $formTemplate?->language
                                    ??
                                    'en'
                                )
                            }}"
                            placeholder="en"
                        >

                    </div>



                    {{-- CATEGORY --}}
                    <div class="msg91-field">

                        <label>
                            Category
                        </label>


                        <input
                            name="category"
                            value="{{
                                old(
                                    'category',
                                    $formTemplate?->category
                                )
                            }}"
                            placeholder="UTILITY"
                        >

                    </div>



                    {{-- TEMPLATE ID --}}
                    <div class="msg91-field">

                        <label>
                            MSG91 Template ID
                        </label>


                        <input
                            name="provider_template_id"
                            value="{{
                                old(
                                    'provider_template_id',
                                    $formTemplate?->provider_template_id
                                )
                            }}"
                            placeholder="MSG91 template ID"
                        >

                    </div>



                    {{-- SENDER --}}
                    <div class="msg91-field">

                        <label>
                            Verified Sender
                        </label>


                        <select
                            required
                            name="sender_id"
                        >

                            <option value="">
                                Select sender
                            </option>


                            @foreach($senders as $sender)

                                <option
                                    value="{{ $sender->id }}"
                                    {{
                                        (string) old(
                                            'sender_id',
                                            $formTemplate?->bindings->first()?->sender_id
                                        ) === (string) $sender->id
                                            ? 'selected'
                                            : ''
                                    }}
                                >

                                    {{ $sender->integrated_number }}

                                    ·

                                    {{ $sender->user?->name }}

                                </option>

                            @endforeach

                        </select>

                    </div>



                    {{-- STATUS --}}
                    <div class="msg91-field">

                        <label>
                            MSG91 Status
                        </label>


                        <select name="provider_status">

                            @foreach(
                                [
                                    'approved',
                                    'pending',
                                    'rejected',
                                    'disabled',
                                    'unknown',
                                ]
                                as
                                $status
                            )

                                <option
                                    value="{{ $status }}"
                                    {{
                                        old(
                                            'provider_status',
                                            $formTemplate?->status ?? 'approved'
                                        ) === $status
                                            ? 'selected'
                                            : ''
                                    }}
                                >

                                    {{ ucfirst($status) }}

                                </option>

                            @endforeach

                        </select>

                    </div>

                </div>



                {{-- CRM ENABLE --}}
                <div class="msg91-toggle-field">

                    <label>
                        CRM Availability
                    </label>


                    <label class="msg91-switch-row">

                        <input
                            type="checkbox"
                            name="crm_enabled"
                            value="1"
                            {{
                                old(
                                    'crm_enabled',
                                    $formTemplate?->crm_enabled
                                )
                                    ? 'checked'
                                    : ''
                            }}
                        >


                        <span class="msg91-switch">

                            <span></span>

                        </span>


                        <span>
                            Make this template available in CRM campaigns
                        </span>

                    </label>

                </div>



                {{-- VARIABLE MAPPING --}}
                <div class="msg91-variable-block">

                    <div class="msg91-variable-block__heading">

                        <div>

                            <strong>
                                Body Variable Mapping
                            </strong>

                            <span>
                                Map only variables actually present in the MSG91 template.
                            </span>

                        </div>

                    </div>


                    <div class="msg91-variable-grid">

                        @foreach(range(1, 5) as $index)

                            <div class="msg91-field">

                                <label>
                                    Body {{ $index }}
                                </label>


                                <select name="body_{{ $index }}">

                                    <option value="">
                                        Not used
                                    </option>


                                    @foreach(
                                        [
                                            'customer_name' =>
                                                'Customer name',

                                            'product_name' =>
                                                'Product name',

                                            'service_date' =>
                                                'Service date',

                                            'next_followup_date' =>
                                                'Next follow-up date',

                                            'salesperson_name' =>
                                                'Salesperson name',
                                        ]
                                        as
                                        $value
                                        =>
                                        $label
                                    )

                                        <option
                                            value="{{ $value }}"
                                            {{
                                                old(
                                                    'body_'.$index,
                                                    data_get(
                                                        $formTemplate?->variable_schema,
                                                        'body_'.$index.'.source'
                                                    )
                                                ) === $value
                                                    ? 'selected'
                                                    : ''
                                            }}
                                        >

                                            {{ $label }}

                                        </option>

                                    @endforeach

                                </select>

                            </div>

                        @endforeach

                    </div>

                </div>

            </div>



            <footer class="msg91-modal__footer">

                <button
                    type="button"
                    class="msg91-button msg91-button--secondary"
                    data-msg91-close-modal
                >
                    Cancel
                </button>


                <button
                    type="submit"
                    class="msg91-button"
                >

                    <i class="ri-save-line"></i>


                    {{
                        $formTemplate
                            ? 'Update Mapping'
                            : 'Save Mapping'
                    }}

                </button>

            </footer>

        </form>

    </div>

</div>
@endif



{{-- =========================================================
     MODAL JAVASCRIPT
     ========================================================= --}}

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
        |--------------------------------------------------------------------------
        | OPEN MODAL
        |--------------------------------------------------------------------------
        */

        function openModal(
            modalId
        ) {

            const modal =
                document.getElementById(
                    modalId
                );


            if (!modal) {
                return;
            }


            modal.classList.add(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'false'
            );


            document.body.classList.add(
                'msg91-modal-open'
            );


            const firstInput =
                modal.querySelector(
                    'input:not([type="hidden"]), select, textarea'
                );


            if (firstInput) {

                setTimeout(
                    function () {

                        firstInput.focus();

                    },
                    80
                );

            }

        }



        /*
        |--------------------------------------------------------------------------
        | CLOSE MODAL
        |--------------------------------------------------------------------------
        */

      function closeModal(modal) {

    if (!modal) {
        return;
    }

    modal.classList.remove('is-open');

    modal.setAttribute(
        'aria-hidden',
        'true'
    );


    /*
    |--------------------------------------------------------------------------
    | REMOVE EDIT PARAMETER FROM URL
    |--------------------------------------------------------------------------
    |
    | When Edit Template is opened, the URL becomes:
    |
    | /admin/msg91-whatsapp/templates?edit=UUID
    |
    | Closing the Edit modal should return the browser URL to:
    |
    | /admin/msg91-whatsapp/templates
    |
    | without reloading the page.
    |
    */

    if (
        modal.id ===
        'msg91-register-template-modal'
    ) {

        const currentUrl =
            new URL(
                window.location.href
            );


        if (
            currentUrl.searchParams.has(
                'edit'
            )
        ) {

            currentUrl.searchParams.delete(
                'edit'
            );


            window.history.replaceState(
                {},
                '',
                currentUrl.pathname
                +
                (
                    currentUrl.search
                    ?
                    currentUrl.search
                    :
                    ''
                )
                +
                currentUrl.hash
            );

        }

    }


    if (
        !document.querySelector(
            '.msg91-modal.is-open'
        )
    ) {

        document.body.classList.remove(
            'msg91-modal-open'
        );

    }

}



        /*
        |--------------------------------------------------------------------------
        | CLICK
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (
                event
            ) {

                const openButton =
                    event.target.closest(
                        '[data-msg91-open-modal]'
                    );


                if (openButton) {

                    event.preventDefault();


                    openModal(
                        openButton.dataset.msg91OpenModal
                    );


                    return;

                }


                const closeButton =
                    event.target.closest(
                        '[data-msg91-close-modal]'
                    );


                if (closeButton) {

                    event.preventDefault();

                    const modal = closeButton.closest('.msg91-modal');
                    const createForm = modal ? modal.querySelector('[data-msg91-create-template-form]') : null;

                    if (createForm && createForm.classList.contains('is-submitting')) {
                        return;
                    }

                    closeModal(
                        modal
                    );

                }

            }
        );

        document.querySelectorAll('[data-msg91-member-selector]').forEach(function (selector) {
            const selectAll = selector.querySelector('[data-msg91-select-all-senders]');
            const members = Array.from(selector.querySelectorAll('[data-msg91-sender-checkbox]'));

            function refreshSelectAll() {
                selectAll.checked = members.length > 0 && members.every(function (member) {
                    return member.checked;
                });
                selectAll.indeterminate = members.some(function (member) {
                    return member.checked;
                }) && !selectAll.checked;
            }

            selectAll.addEventListener('change', function () {
                members.forEach(function (member) {
                    member.checked = selectAll.checked;
                });
                refreshSelectAll();
            });

            members.forEach(function (member) {
                member.addEventListener('change', refreshSelectAll);
            });

            refreshSelectAll();
        });

        const createTemplateForm = document.querySelector('[data-msg91-create-template-form]');

        if (createTemplateForm) {
            const templateName = createTemplateForm.querySelector('[name="name"]');
            const feedback = createTemplateForm.querySelector('[data-msg91-create-feedback]');
            const submitButton = createTemplateForm.querySelector('[data-msg91-create-submit]');
            const submitLabel = createTemplateForm.querySelector('[data-msg91-create-submit-label]');
            const submitIcon = createTemplateForm.querySelector('[data-msg91-create-submit-icon]');

            function setTemplateNameValidity() {
                const name = templateName.value.trim();

                if (name === '') {
                    templateName.setCustomValidity('Enter a template name.');
                } else if (!/^[a-z0-9_]+$/.test(name)) {
                    templateName.setCustomValidity('Use only lowercase letters (a-z), numbers (0-9), and underscores (_). Do not use spaces, hyphens, or uppercase letters.');
                } else {
                    templateName.setCustomValidity('');
                }
            }

            function showCreateFeedback(message, type) {
                feedback.textContent = message;
                feedback.hidden = false;
                feedback.classList.toggle('is-error', type === 'error');
                feedback.classList.toggle('is-success', type === 'success');
            }

            function setCreateSubmitting(submitting) {
                createTemplateForm.classList.toggle('is-submitting', submitting);
                submitButton.disabled = submitting;
                submitLabel.textContent = submitting ? 'Submitting…' : 'Submit to MSG91';
                submitIcon.className = submitting ? 'ri-loader-4-line ri-spin' : 'ri-send-plane-line';
                createTemplateForm.querySelectorAll('[data-msg91-close-modal]').forEach(function (button) {
                    button.disabled = submitting;
                });
            }

            templateName.addEventListener('input', setTemplateNameValidity);
            templateName.addEventListener('invalid', setTemplateNameValidity);

            createTemplateForm.addEventListener('submit', function (event) {
                setTemplateNameValidity();

                if (!createTemplateForm.checkValidity()) {
                    return;
                }

                event.preventDefault();

                if (createTemplateForm.classList.contains('is-submitting')) {
                    return;
                }

                feedback.hidden = true;
                setCreateSubmitting(true);

                fetch(createTemplateForm.action, {
                    method: 'POST',
                    body: new FormData(createTemplateForm),
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                   .then(async function (response) {
                    const rawBody = await response.text();
                    let payload = {};

                    if (rawBody.trim() !== '') {
                        try {
                            payload = JSON.parse(rawBody);
                        } catch (error) {
                            // Never display raw HTML or unexpected server output.
                            const isHtml =
                                /<!doctype\s+html|<html\b/i.test(rawBody);

                            const cleanBody = rawBody
                                .replace(/<[^>]*>/g, ' ')
                                .replace(/[\x00-\x1F\x7F]+/g, ' ')
                                .replace(/\s+/g, ' ')
                                .trim()
                                .slice(0, 300);

                            payload = {
                                message: !isHtml && cleanBody
                                    ? cleanBody
                                    : 'Server returned an unexpected response.'
                            };
                        }
                    }

                    return {
                        ok: response.ok,
                        status: response.status,
                        payload: payload
                    };
                })
                    .then(function (result) {
                        if (!result.ok) {
                            const errors = result.payload.errors || {};
                            const details = Object.keys(errors).map(function (key) {
                                return Array.isArray(errors[key]) ? errors[key].join(' ') : errors[key];
                            }).filter(Boolean);
                            throw new Error(details.join(' ') || result.payload.message || 'Template could not be submitted. Please review the details and try again.');
                        }

                        showCreateFeedback(result.payload.message || 'Template submitted to MSG91. The page will refresh shortly.', 'success');
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 1600);
                    })
                    .catch(function (error) {
                        showCreateFeedback(error.message || 'Template could not be submitted. Please try again.', 'error');
                        setCreateSubmitting(false);
                    });
            });
        }



        /*
        |--------------------------------------------------------------------------
        | ESC
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (
                event
            ) {

                if (
                    event.key
                    !==
                    'Escape'
                ) {
                    return;
                }


                const openModalElement =
                    document.querySelector(
                        '.msg91-modal.is-open'
                    );


                if (
                    openModalElement
                ) {

                    const createForm = openModalElement.querySelector('[data-msg91-create-template-form]');
                    if (createForm && createForm.classList.contains('is-submitting')) {
                        return;
                    }

                    closeModal(
                        openModalElement
                    );

                }

            }
        );



        /*
        |--------------------------------------------------------------------------
        | AUTO OPEN EDIT
        |--------------------------------------------------------------------------
        */

        @if($formTemplate)

            openModal(
                'msg91-register-template-modal'
            );

        @endif



        /*
        |--------------------------------------------------------------------------
        | AUTO OPEN AFTER VALIDATION FAILURE
        |--------------------------------------------------------------------------
        */

        @if(
            session('msg91_open_create_template')
            ||
            ($errors->any() && $oldFormContext === 'create_template')
        )

            openModal(
                'msg91-create-template-modal'
            );

        @elseif(
            $errors->any()
            &&
            $oldFormContext === 'register_template'
        )

            openModal(
                'msg91-register-template-modal'
            );

        @endif

    }
);

</script>

@endsection
