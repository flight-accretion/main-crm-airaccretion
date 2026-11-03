@extends('admin.layouts.header')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('assets/admin/css/crm/whatsapp/admin.css') }}?v=9"
>


<main class="msg91-page msg91-campaign-detail-page">

    {{-- =====================================================
         PAGE HEADER
         ===================================================== --}}

    <header class="msg91-page__heading">

        <div class="msg91-page__heading-copy">

            <p class="msg91-eyebrow">
                Campaign Details
            </p>

            <h1>
                {{ $campaign->name }}
            </h1>

            <div class="msg91-campaign-detail-subtitle">

                <span>
                    <i class="ri-file-list-3-line"></i>

                    {{ $campaign->template?->name ?? 'No template' }}
                </span>

                <span class="msg91-campaign-detail-subtitle__separator">
                    •
                </span>

                <span>
                    <i class="ri-whatsapp-line"></i>

                    {{ $campaign->sender?->integrated_number ?? 'No sender' }}
                </span>

            </div>

        </div>


        <div class="msg91-page__actions">

            <a
                class="msg91-button msg91-button--secondary"
                href="{{ route('admin.msg91.campaigns.index') }}"
            >
                <i class="ri-arrow-left-line"></i>

                Campaign Records
            </a>

        </div>

    </header>



    {{-- =====================================================
         CANCEL ACTION
         ===================================================== --}}

    @if(
        in_array(
            $campaign->status,
            ['queued', 'processing'],
            true
        )
    )

        <section class="msg91-campaign-cancel-card">

            <div>

                <span class="msg91-campaign-cancel-card__icon">

                    <i class="ri-alert-line"></i>

                </span>


                <div>

                    <strong>
                        Campaign still processing
                    </strong>

                    <p>
                        You can cancel recipients that have not yet been sent.
                    </p>

                </div>

            </div>


            <form
                method="POST"
                action="{{ route('admin.msg91.campaigns.cancel', $campaign) }}"
            >

                @csrf


                <button
                    type="submit"
                    class="msg91-button msg91-button--danger"
                    onclick="return confirm('Cancel unsent recipients?')"
                >

                    <i class="ri-close-circle-line"></i>

                    Cancel Unsent

                </button>

            </form>

        </section>

    @endif



    {{-- =====================================================
         SUMMARY STATS
         ===================================================== --}}

    <section class="msg91-campaign-detail-stats">


        <article class="msg91-campaign-detail-stat">

            <div class="msg91-campaign-detail-stat__icon">

                <i class="ri-user-follow-line"></i>

            </div>


            <div>

                <span>
                    Eligible
                </span>

                <strong>
                    {{ number_format($campaign->eligible_count) }}
                </strong>

                <small>
                    Total recipients
                </small>

            </div>

        </article>



        <article class="msg91-campaign-detail-stat">

            <div
                class="
                    msg91-campaign-detail-stat__icon
                    msg91-campaign-detail-stat__icon--blue
                "
            >

                <i class="ri-check-double-line"></i>

            </div>


            <div>

                <span>
                    Accepted
                </span>

                <strong>
                    {{ number_format($campaign->accepted_count) }}
                </strong>

                <small>
                    Accepted by MSG91
                </small>

            </div>

        </article>



        <article class="msg91-campaign-detail-stat">

            <div
                class="
                    msg91-campaign-detail-stat__icon
                    msg91-campaign-detail-stat__icon--green
                "
            >

                <i class="ri-checkbox-circle-line"></i>

            </div>


            <div>

                <span>
                    Delivered
                </span>

                <strong>
                    {{ number_format($campaign->delivered_count) }}
                </strong>

                <small>
                    Confirmed delivered
                </small>

            </div>

        </article>



        <article class="msg91-campaign-detail-stat">

            <div
                class="
                    msg91-campaign-detail-stat__icon
                    msg91-campaign-detail-stat__icon--violet
                "
            >

                <i class="ri-eye-line"></i>

            </div>


            <div>

                <span>
                    Read
                </span>

                <strong>
                    {{ number_format($campaign->read_count) }}
                </strong>

                <small>
                    Confirmed read
                </small>

            </div>

        </article>

    </section>



    {{-- =====================================================
         CAMPAIGN INFORMATION
         ===================================================== --}}

    <section class="msg91-card msg91-campaign-info-card">

        <header class="msg91-card__header">

            <div>

                <h2>
                    Campaign Information
                </h2>

                <p>
                    Sender, template and current campaign status.
                </p>

            </div>


            <span
                class="
                    msg91-status
                    msg91-status--{{ strtolower($campaign->status) }}
                "
            >

                {{ ucfirst($campaign->status) }}

            </span>

        </header>


        <div class="msg91-campaign-info-grid">


            <div>

                <small>
                    Campaign
                </small>

                <strong>
                    {{ $campaign->name }}
                </strong>

            </div>



            <div>

                <small>
                    Created By
                </small>

                <strong>
                    {{ $campaign->creator?->name ?? 'Unknown' }}
                </strong>

            </div>



            <div>

                <small>
                    Template
                </small>

                <strong>
                    {{ $campaign->template?->name ?? 'Not available' }}
                </strong>

            </div>



            <div>

                <small>
                    Language
                </small>

                <strong>
                    {{ strtoupper($campaign->template?->language ?? '-') }}
                </strong>

            </div>



            <div>

                <small>
                    Sender
                </small>

                <strong class="msg91-campaign-info-sender">

                    <i class="ri-whatsapp-line"></i>

                    {{ $campaign->sender?->integrated_number ?? 'Not mapped' }}

                </strong>

            </div>



            <div>

                <small>
                    Created
                </small>

                <strong>
                    {{ $campaign->created_at?->format('d M Y, H:i') ?? '—' }}
                </strong>

            </div>

        </div>

    </section>



    {{-- =====================================================
         RECIPIENT DIRECTORY
         ===================================================== --}}

    <section class="msg91-card msg91-campaign-recipient-card">

        <header class="msg91-card__header">

            <div>

                <h2>
                    Recipient Delivery
                </h2>

                <p>
                    Individual delivery status for each campaign recipient.
                </p>

            </div>


            <span class="msg91-record-count">

                {{ number_format($recipients->total()) }}

                {{
                    Str::plural(
                        'recipient',
                        $recipients->total()
                    )
                }}

            </span>

        </header>



        {{-- =================================================
             DESKTOP TABLE
             ================================================= --}}

        <div class="msg91-campaign-recipient-table-wrap">

            <table class="msg91-campaign-recipient-table">

                <thead>

                    <tr>

                        <th>
                            Lead
                        </th>

                        <th>
                            Destination
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Attempts
                        </th>

                        <th>
                            Failure / Reason
                        </th>

                        <th>
                            Updated
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($recipients as $recipient)

                        <tr>


                            {{-- LEAD --}}
                            <td>

                                @if($recipient->lead)

                                    <a
                                        class="msg91-recipient-lead"
                                        href="{{
                                            route(
                                                'admin.leads.view',
                                                $recipient->lead
                                            )
                                        }}"
                                    >

                                        <span class="msg91-recipient-lead__avatar">

                                            {{
                                                strtoupper(
                                                    mb_substr(
                                                        $recipient->lead?->client?->name ?? 'L',
                                                        0,
                                                        1
                                                    )
                                                )
                                            }}

                                        </span>


                                        <span>

                                            <strong>

                                                {{
                                                    $recipient
                                                        ->lead
                                                        ?->client
                                                        ?->name
                                                    ??
                                                    'Lead'
                                                }}

                                            </strong>


                                            <small>
                                                View lead
                                            </small>

                                        </span>

                                    </a>

                                @else

                                    <span class="msg91-deleted-lead">

                                        <i class="ri-delete-bin-line"></i>

                                        Deleted lead

                                    </span>

                                @endif

                            </td>



                            {{-- DESTINATION --}}
                            <td>

                                <span class="msg91-recipient-phone">

                                    <i class="ri-phone-line"></i>

                                    ••••{{
                                        substr(
                                            $recipient->normalized_phone,
                                            -4
                                        )
                                    }}

                                </span>

                            </td>



                            {{-- STATUS --}}
                            <td>

                                <span
                                    class="
                                        msg91-status
                                        msg91-status--{{ strtolower($recipient->status) }}
                                    "
                                >

                                    {{
                                        ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $recipient->status
                                            )
                                        )
                                    }}

                                </span>

                            </td>



                            {{-- ATTEMPTS --}}
                            <td>

                                <span class="msg91-attempt-count">

                                    {{ $recipient->attempts }}

                                </span>

                            </td>



                            {{-- FAILURE --}}
                            <td>

                                @if($recipient->failure_reason)

                                    <span class="msg91-failure-reason">

                                        <i class="ri-error-warning-line"></i>

                                        {{ $recipient->failure_reason }}

                                    </span>

                                @else

                                    <span class="msg91-no-failure">
                                        —
                                    </span>

                                @endif

                            </td>



                            {{-- UPDATED --}}
                            <td>

                                <div class="msg91-recipient-updated">

                                    <strong>

                                        {{
                                            $recipient
                                                ->updated_at
                                                ?->format(
                                                    'd M Y'
                                                )
                                        }}

                                    </strong>


                                    <span>

                                        {{
                                            $recipient
                                                ->updated_at
                                                ?->format(
                                                    'H:i'
                                                )
                                        }}

                                    </span>

                                </div>

                            </td>

                        </tr>


                    @empty


                        <tr>

                            <td
                                colspan="6"
                                class="msg91-empty"
                            >

                                <div class="msg91-empty__icon">

                                    <i class="ri-user-line"></i>

                                </div>


                                <strong>
                                    No recipients found
                                </strong>


                                <span>
                                    No campaign recipients are available.
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

        <div class="msg91-recipient-mobile">

            @forelse($recipients as $recipient)

                <article class="msg91-recipient-mobile-card">


                    <div class="msg91-recipient-mobile-card__header">

                        <div>

                            @if($recipient->lead)

                                <strong>

                                    {{
                                        $recipient
                                            ->lead
                                            ?->client
                                            ?->name
                                        ??
                                        'Lead'
                                    }}

                                </strong>

                            @else

                                <strong>
                                    Deleted Lead
                                </strong>

                            @endif


                            <span>

                                ••••{{
                                    substr(
                                        $recipient->normalized_phone,
                                        -4
                                    )
                                }}

                            </span>

                        </div>


                        <span
                            class="
                                msg91-status
                                msg91-status--{{ strtolower($recipient->status) }}
                            "
                        >

                            {{
                                ucfirst(
                                    str_replace(
                                        '_',
                                        ' ',
                                        $recipient->status
                                    )
                                )
                            }}

                        </span>

                    </div>



                    <div class="msg91-recipient-mobile-card__meta">


                        <div>

                            <small>
                                Attempts
                            </small>

                            <strong>
                                {{ $recipient->attempts }}
                            </strong>

                        </div>


                        <div>

                            <small>
                                Updated
                            </small>

                            <strong>

                                {{
                                    $recipient
                                        ->updated_at
                                        ?->format(
                                            'd M Y, H:i'
                                        )
                                }}

                            </strong>

                        </div>

                    </div>



                    @if($recipient->failure_reason)

                        <div class="msg91-recipient-mobile-card__failure">

                            <small>
                                Failure / Reason
                            </small>

                            <p>
                                {{ $recipient->failure_reason }}
                            </p>

                        </div>

                    @endif



                    @if($recipient->lead)

                        <a
                            class="msg91-view-button msg91-view-button--mobile"
                            href="{{
                                route(
                                    'admin.leads.view',
                                    $recipient->lead
                                )
                            }}"
                        >

                            <i class="ri-external-link-line"></i>

                            View Lead

                        </a>

                    @endif

                </article>


            @empty

                <div class="msg91-empty">

                    No recipients found.

                </div>

            @endforelse

        </div>

    </section>



    {{-- =====================================================
         PAGINATION
         ===================================================== --}}

    @if($recipients->hasPages())

        <div class="msg91-pagination">

            {{ $recipients->links() }}

        </div>

    @endif

</main>

@endsection
