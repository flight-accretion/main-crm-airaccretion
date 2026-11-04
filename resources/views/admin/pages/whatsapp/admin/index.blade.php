@extends('admin.layouts.header')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('assets/admin/css/crm/whatsapp/admin.css') }}?v=9"
>


<main class="msg91-page msg91-sender-access">

    {{-- =====================================================
         PAGE HEADER
         ===================================================== --}}

    <header class="msg91-page__heading">

        <div class="msg91-page__heading-copy">

            <p class="msg91-eyebrow">
                MSG91 WhatsApp
            </p>

            <h1>
                Sender Access
            </h1>

            <p>
                Manage WhatsApp sender access and integrated numbers for active CRM users.
            </p>

        </div>
        <div class="msg91-page__actions">

            <form method="POST" action="{{ route('admin.msg91.admin.refresh-senders') }}">
                @csrf
                <button type="submit" class="msg91-button msg91-button--secondary">
                    <i class="ri-refresh-line" aria-hidden="true"></i>
                    Refresh MSG91 Sender Status
                </button>
            </form>

            <a
                class="msg91-button msg91-button--secondary"
                href="{{ route('admin.msg91.templates.index') }}"
            >
                <i class="ri-file-list-3-line"></i>

                <span>
                    Template Registry
                </span>
            </a>


            <a
                class="msg91-button msg91-button--secondary"
                href="{{ route('admin.msg91.campaigns.index') }}"
            >
                <i class="ri-history-line"></i>

                <span>
                    Campaign Records
                </span>
            </a>

        </div>

    </header>



    {{-- =====================================================
         FLASH MESSAGE
         ===================================================== --}}

    @if(session('success'))

        <div class="alert alert-success mb-3">

            {{ session('success') }}

        </div>

    @endif


    @if(session('error'))

        <div class="alert alert-danger mb-3">

            {{ session('error') }}

        </div>

    @endif



    {{-- =====================================================
         SENDER DIRECTORY
         ===================================================== --}}

    <section class="msg91-card msg91-access-card">


        <header class="msg91-card__header">

            <div>

                <h2>
                    WhatsApp Sender Access
                </h2>

                <p>
                    Activate or deactivate users and map their integrated WhatsApp number.
                </p>

            </div>


            <span class="msg91-record-count">

                {{ number_format($users->total()) }} users

            </span>

        </header>



        {{-- =================================================
             DESKTOP / TABLET TABLE
             ================================================= --}}

        <div class="msg91-access-table-wrap">

            <table class="msg91-access-table">

                <thead>

                    <tr>

                        <th>
                            Employee
                        </th>

                        <th>
                            Role
                        </th>

                        <th>
                            CRM Status
                        </th>
                        <th>
                            MSG91 Status
                        </th>

                        <th>
                            Integrated Number
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($users as $member)

                        @php

                            /*
                             * Effective access status.
                             *
                             * CRM status is controlled by the CRM sender record.
                             * MSG91 status is displayed separately from the read-only
                             * subscription/wallet mirror record.
                             */

                          $senderActive =
                            (
                                $member
                                    ->msg91CampaignSender
                                    ?->status
                                ??
                                'inactive'
                            )
                            ===
                            'active';


                        $providerStatus = strtolower(trim((string) data_get(
                            $member->msg91CampaignSender?->verification_meta,
                            'provider_status',
                            'not_checked'
                        )));
                        $providerConnected = $providerStatus === 'connected';


                            $accessStatus =
                                $senderActive
                                    ? 'active'
                                    : 'inactive';


                            $integratedNumber =
                                $member
                                    ->msg91CampaignSender
                                    ?->integrated_number;
/*
                             * Avatar initials.
                             */
                            $nameParts =
                                preg_split(
                                    '/\s+/',
                                    trim(
                                        $member->name
                                    )
                                );


                            $initials =
                                collect(
                                    $nameParts
                                )
                                ->filter()
                                ->take(2)
                                ->map(
                                    function ($part) {

                                        return strtoupper(
                                            mb_substr(
                                                $part,
                                                0,
                                                1
                                            )
                                        );

                                    }
                                )
                                ->implode('');

                        @endphp


                        <tr class="msg91-access-row">


                            {{-- =============================================
                                 EMPLOYEE
                                 ============================================= --}}

                            <td>

                                <div class="msg91-employee">

                                    <div class="msg91-employee__avatar">

                                        {{ $initials ?: 'U' }}

                                    </div>


                                    <div class="msg91-employee__info">

                                        <strong>

                                            {{ $member->name }}

                                        </strong>


                                        <span>

                                            {{ $member->email }}

                                        </span>

                                    </div>

                                </div>

                            </td>



                            {{-- =============================================
                                 ROLE
                                 ============================================= --}}

                            <td>

                                <span class="msg91-role">

                                    {{ $member->userType?->user_type ?? 'N/A' }}

                                </span>

                            </td>



                            {{-- =============================================
                                 STATUS
                                 ============================================= --}}

                           <td>

                        <span class="msg91-status-label">
                            CRM
                        </span>

                        <span
                            class="
                                msg91-access-status
                                {{
                                    $senderActive
                                        ? 'msg91-access-status--active'
                                        : 'msg91-access-status--inactive'
                                }}
                            "
                        >

                            <span class="msg91-access-status__dot"></span>

                            {{
                                $senderActive
                                    ? 'Active'
                                    : 'Inactive'
                            }}

                        </span>

                    </td>

               <td>

                        <span class="msg91-status-label">
                            MSG91
                        </span>

                        <span
                            class="
                                msg91-access-status
                               {{ in_array($providerStatus, ['activated', 'connected'], true)
                                    ? 'msg91-access-status--active'
                                    : 'msg91-access-status--inactive'
                                }}
                            "
                        >

                            <span class="msg91-access-status__dot"></span>

                            {{
                                $providerConnected ? 'Connected' : ucfirst(str_replace('_', ' ', $providerStatus))
                            }}

                        </span>

                    </td>



                            {{-- =============================================
                                 INTEGRATED NUMBER
                                 ============================================= --}}

                            <td>

                                @if($integratedNumber)

                                    <div class="msg91-integrated-number">

                                        <i class="ri-whatsapp-line"></i>


                                        <span>

                                            {{ $integratedNumber }}

                                        </span>

                                    </div>

                                @else

                                    <span class="msg91-not-mapped">

                                        Not mapped

                                    </span>

                                @endif

                            </td>



                            {{-- =============================================
                                 MANAGE
                                 ============================================= --}}

                            <td>

                                <button
                                    type="button"
                                    class="msg91-manage-open"
                                    data-user-id="{{ $member->id }}"
                                    data-user-name="{{ $member->name }}"
                                    data-user-email="{{ $member->email }}"
                                    data-user-role="{{ $member->userType?->user_type ?? 'N/A' }}"
                                    data-access-status="{{ $accessStatus }}"
                                    data-integrated-number="{{ $integratedNumber ?? '' }}"
                                >

                                    <i class="ri-settings-3-line"></i>

                                    <span>
                                        Manage
                                    </span>

                                </button>

                            </td>

                        </tr>


                    @empty

                        <tr>

                            <td
                                colspan="6"
                                class="msg91-empty"
                            >

                                <div class="msg91-empty__icon">

                                    <i class="ri-whatsapp-line"></i>

                                </div>


                                <strong>
                                    No users found
                                </strong>


                                <span>
                                    No active CRM users are available for WhatsApp sender access.
                                </span>

                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>



        {{-- =================================================
             MOBILE VIEW
             ================================================= --}}

        <div class="msg91-access-mobile">

            @forelse($users as $member)

                @php

    $senderActive =
        (
            $member
                ->msg91CampaignSender
                ?->status
            ??
            'inactive'
        )
        ===
        'active';


    $providerStatus = strtolower(trim((string) data_get(
        $member->msg91CampaignSender?->verification_meta,
        'provider_status',
        'not_checked'
    )));
    $providerConnected = $providerStatus === 'connected';


    $accessStatus =
        $senderActive
            ? 'active'
            : 'inactive';


    $integratedNumber =
        $member
            ->msg91CampaignSender
            ?->integrated_number;
@endphp


                <article class="msg91-mobile-card">


                    <div class="msg91-mobile-card__top">


                        <div class="msg91-mobile-card__person">

                            <strong>

                                {{ $member->name }}

                            </strong>


                            <span>

                                {{ $member->userType?->user_type ?? 'N/A' }}

                            </span>

                        </div>


                        <div class="msg91-mobile-statuses">

    {{-- CRM STATUS --}}
    <div class="msg91-mobile-status">

        <small>
            CRM
        </small>

        <span
            class="
                msg91-access-status
                {{
                    $senderActive
                        ? 'msg91-access-status--active'
                        : 'msg91-access-status--inactive'
                }}
            "
        >
            <span class="msg91-access-status__dot"></span>

            {{
                $senderActive
                    ? 'Active'
                    : 'Inactive'
            }}
        </span>

    </div>


    {{-- MSG91 STATUS --}}
    <div class="msg91-mobile-status">

        <small>
            MSG91
        </small>

        <span
            class="
                msg91-access-status
            {{ in_array($providerStatus, ['activated', 'connected'], true)
                ? 'msg91-access-status--active'
                : 'msg91-access-status--inactive'
            }}
            "
        >
            <span class="msg91-access-status__dot"></span>

            {{
                $providerConnected ? 'Connected' : ucfirst(str_replace('_', ' ', $providerStatus))
            }}
        </span>

    </div>

</div>

                    </div>



                    <div class="msg91-mobile-card__contact">

                        {{ $member->email }}

                    </div>



                    <div class="msg91-mobile-card__number">

                        <small>
                            Integrated Number
                        </small>


                        @if($integratedNumber)

                            <strong>

                                <i class="ri-whatsapp-line"></i>

                                {{ $integratedNumber }}

                            </strong>

                        @else

                            <strong class="msg91-mobile-not-mapped">

                                Not mapped

                            </strong>

                        @endif

                    </div>



                    <button
                        type="button"
                        class="
                            msg91-manage-open
                            msg91-manage-open--mobile
                        "
                        data-user-id="{{ $member->id }}"
                        data-user-name="{{ $member->name }}"
                        data-user-email="{{ $member->email }}"
                        data-user-role="{{ $member->userType?->user_type ?? 'N/A' }}"
                        data-access-status="{{ $accessStatus }}"
                        data-integrated-number="{{ $integratedNumber ?? '' }}"
                    >

                        <i class="ri-settings-3-line"></i>

                        Manage Sender

                    </button>

                </article>


            @empty


                <div class="msg91-empty">

                    No users found.

                </div>


            @endforelse

        </div>

    </section>



    {{-- =====================================================
         PAGINATION
         ===================================================== --}}

    @if($users->hasPages())

        <div class="msg91-pagination">

            {{ $users->links() }}

        </div>

    @endif

</main>



{{-- =========================================================
     MANAGE SENDER MODAL
     ========================================================= --}}

<div
    class="msg91-modal"
    id="msg91-manage-modal"
    aria-hidden="true"
>

    {{-- BACKDROP --}}
    <div
        class="msg91-modal__backdrop"
        data-msg91-modal-close
    ></div>



    {{-- DIALOG --}}
    <div
        class="msg91-modal__dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="msg91-modal-title"
    >


        {{-- =============================================
             MODAL HEADER
             ============================================= --}}

        <header class="msg91-modal__header">

            <div>

                <p class="msg91-modal__eyebrow">
                    MSG91 WhatsApp
                </p>


                <h2 id="msg91-modal-title">
                    Manage Sender
                </h2>


                <p id="msg91-modal-user-name">
                    -
                </p>

            </div>



            <button
                type="button"
                class="msg91-modal__close"
                data-msg91-modal-close
                aria-label="Close"
            >

                <i class="ri-close-line"></i>

            </button>

        </header>



        {{-- =============================================
             USER SUMMARY
             ============================================= --}}

        <div class="msg91-modal__user">

            <div class="msg91-modal__user-icon">

                <i class="ri-user-line"></i>

            </div>


            <div>

                <strong id="msg91-modal-user-display-name">

                    -

                </strong>


                <span id="msg91-modal-user-meta">

                    -

                </span>

            </div>

        </div>



        {{-- =============================================
             FORM
             ============================================= --}}

        <form
            method="POST"
            action="{{ route('admin.msg91.admin.save') }}"
            id="msg91-manage-form"
        >

            @csrf

            @method('PUT')



            {{-- =============================================
                 CURRENT BACKEND FIELDS
                 ============================================= --}}

            <input
                type="hidden"
                name="user_id"
                id="msg91-modal-user-id"
            >
{{-- =============================================
                 MODAL BODY
                 ============================================= --}}

            <div class="msg91-modal__body">


                {{-- =========================================
                     STATUS
                     Sender access status.
                     ========================================= --}}

                <div class="msg91-modal-field">

                    <label
                        for="msg91-modal-sender-status"
                    >

                        CRM Status

                    </label>


                    <select
                        id="msg91-modal-sender-status"
                        name="sender_status"
                        required
                    >

                        <option value="active">

                            Active

                        </option>


                        <option value="inactive">

                            Inactive

                        </option>

                    </select>


                    <small>

                        Controls whether this CRM user can use the mapped WhatsApp sender.

                    </small>

                </div>



                {{-- =========================================
                     INTEGRATED NUMBER
                     ========================================= --}}

                <div class="msg91-modal-field">

                    <label
                        for="msg91-modal-integrated-number"
                    >

                        Integrated Number

                    </label>


                    <div class="msg91-number-input">

                        <span>

                            <i class="ri-whatsapp-line"></i>

                        </span>


                        <input
                            type="text"
                            id="msg91-modal-integrated-number"
                            name="integrated_number"
                            placeholder="Example: 919876543210"
                            autocomplete="off"
                            inputmode="numeric"
                        >

                    </div>


                    <small>

                        Enter the MSG91 integrated WhatsApp number including country code.

                    </small>

                </div>

            </div>



            {{-- =============================================
                 FOOTER
                 ============================================= --}}

            <footer class="msg91-modal__footer">


                <button
                    type="button"
                    class="
                        msg91-button
                        msg91-button--secondary
                    "
                    data-msg91-modal-close
                >

                    Cancel

                </button>



                <button
                    type="submit"
                    class="msg91-button"
                >

                    <i class="ri-save-line"></i>

                    Save Changes

                </button>

            </footer>

        </form>

    </div>

</div>



{{-- =========================================================
     MODAL SCRIPT
     ========================================================= --}}

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const modal =
            document.getElementById(
                'msg91-manage-modal'
            );


        if (!modal) {
            return;
        }


        const form =
            document.getElementById(
                'msg91-manage-form'
            );


        const userId =
            document.getElementById(
                'msg91-modal-user-id'
            );


        const userName =
            document.getElementById(
                'msg91-modal-user-name'
            );


        const userDisplayName =
            document.getElementById(
                'msg91-modal-user-display-name'
            );


        const userMeta =
            document.getElementById(
                'msg91-modal-user-meta'
            );


        const senderStatus =
            document.getElementById(
                'msg91-modal-sender-status'
            );


        const integratedNumber =
            document.getElementById(
                'msg91-modal-integrated-number'
            );
let lastTrigger = null;



        /*
        |--------------------------------------------------------------------------
        | OPEN MODAL
        |--------------------------------------------------------------------------
        */

        function openModal(
            button
        ) {

            lastTrigger =
                button;


            const name =
                button.dataset.userName
                ||
                'User';


            const email =
                button.dataset.userEmail
                ||
                '';


            const role =
                button.dataset.userRole
                ||
                '';


            const accessStatus =
                button.dataset.accessStatus
                ||
                'inactive';


            userId.value =
                button.dataset.userId
                ||
                '';


            userName.textContent =
                name;


            userDisplayName.textContent =
                name;


            userMeta.textContent =
                [
                    role,
                    email
                ]
                .filter(Boolean)
                .join(
                    ' · '
                );


            senderStatus.value =
                accessStatus;


            integratedNumber.value =
                button.dataset.integratedNumber
                ||
                '';
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


            setTimeout(
                function () {

                    senderStatus.focus();

                },
                70
            );

        }



        /*
        |--------------------------------------------------------------------------
        | CLOSE MODAL
        |--------------------------------------------------------------------------
        */

        function closeModal() {

            modal.classList.remove(
                'is-open'
            );


            modal.setAttribute(
                'aria-hidden',
                'true'
            );


            document.body.classList.remove(
                'msg91-modal-open'
            );


            if (
                lastTrigger
            ) {

                lastTrigger.focus();

            }

        }



        /*
        |--------------------------------------------------------------------------
        | OPEN / CLOSE CLICK HANDLER
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'click',
            function (
                event
            ) {

                const openButton =
                    event.target.closest(
                        '.msg91-manage-open'
                    );


                if (openButton) {

                    event.preventDefault();


                    openModal(
                        openButton
                    );


                    return;

                }


                const closeButton =
                    event.target.closest(
                        '[data-msg91-modal-close]'
                    );


                if (closeButton) {

                    event.preventDefault();


                    closeModal();

                }

            }
        );



        /*
        |--------------------------------------------------------------------------
        | SUBMIT SAFETY
        |--------------------------------------------------------------------------
        */

        form.addEventListener(
            'submit',
            function () {

                /*
                 * Trim spaces from integrated number.
                 */
                integratedNumber.value =
                    integratedNumber.value
                        .replace(
                            /\s+/g,
                            ''
                        )
                        .trim();

            }
        );



        /*
        |--------------------------------------------------------------------------
        | ESCAPE KEY
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            'keydown',
            function (
                event
            ) {

                if (
                    event.key
                    ===
                    'Escape'
                    &&
                    modal.classList.contains(
                        'is-open'
                    )
                ) {

                    closeModal();

                }

            }
        );

    }
);

</script>

@endsection
