@extends('admin.layouts.header')

@section('content')

<link
    rel="stylesheet"
    href="{{ asset('assets/admin/css/crm/whatsapp/admin.css') }}?v=9"
>


<main class="msg91-page msg91-campaign-page">

    {{-- =====================================================
         PAGE HEADER
         ===================================================== --}}

    <header class="msg91-page__heading">

        <div class="msg91-page__heading-copy">

            <p class="msg91-eyebrow">
                WhatsApp Campaigns
            </p>

            <h1>
                Campaign Records
            </h1>

            <p>
                Track MSG91 campaign delivery activity and message outcomes.
            </p>

        </div>


        <div class="msg91-page__actions">

            @if($canManageMsg91)

                <a
                    class="msg91-button msg91-button--secondary"
                    href="{{ route('admin.msg91.admin.index') }}"
                >
                    <i class="ri-user-settings-line"></i>

                    Sender Access
                </a>

            @endif

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



    {{-- =====================================================
         SUMMARY STATS
         ===================================================== --}}

    <section class="msg91-campaign-stats">


        {{-- CAMPAIGNS --}}
        <article class="msg91-campaign-stat">

            <div class="msg91-campaign-stat__top">

                <span class="msg91-campaign-stat__icon">

                    <i class="ri-megaphone-line"></i>

                </span>


                <span class="msg91-campaign-stat__label">

                    Campaigns

                </span>

            </div>


            <strong>

                {{ number_format($campaigns->total()) }}

            </strong>


            <small>

                Total visible campaigns

            </small>

        </article>



        {{-- ACCEPTED --}}
        <article class="msg91-campaign-stat">

            <div class="msg91-campaign-stat__top">

                <span
                    class="
                        msg91-campaign-stat__icon
                        msg91-campaign-stat__icon--blue
                    "
                >

                    <i class="ri-check-double-line"></i>

                </span>


                <span class="msg91-campaign-stat__label">

                    Accepted

                </span>

            </div>


            <strong>

                {{ number_format($campaigns->sum('accepted_count')) }}

            </strong>


            <small>

                Accepted by MSG91

            </small>

        </article>



        {{-- DELIVERED --}}
        <article class="msg91-campaign-stat">

            <div class="msg91-campaign-stat__top">

                <span
                    class="
                        msg91-campaign-stat__icon
                        msg91-campaign-stat__icon--green
                    "
                >

                    <i class="ri-checkbox-circle-line"></i>

                </span>


                <span class="msg91-campaign-stat__label">

                    Delivered

                </span>

            </div>


            <strong>

                {{ number_format($campaigns->sum('delivered_count')) }}

            </strong>


            <small>

                Confirmed delivered

            </small>

        </article>



        {{-- FAILED --}}
        <article class="msg91-campaign-stat">

            <div class="msg91-campaign-stat__top">

                <span
                    class="
                        msg91-campaign-stat__icon
                        msg91-campaign-stat__icon--red
                    "
                >

                    <i class="ri-error-warning-line"></i>

                </span>


                <span class="msg91-campaign-stat__label">

                    Failed / Unknown

                </span>

            </div>


            <strong>

                {{
                    number_format(
                        $campaigns->sum('failed_count')
                        +
                        $campaigns->sum('unknown_count')
                    )
                }}

            </strong>


            <small>

                Failed or unresolved

            </small>

        </article>

    </section>



    {{-- =====================================================
         FILTERS
         ===================================================== --}}

    <section class="msg91-card msg91-campaign-filter-card">

        <form
            class="msg91-campaign-filter"
            method="GET"
        >

            <div class="msg91-campaign-search">

                <i class="ri-search-line"></i>


                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search campaign name, sender or template"
                >

            </div>


            <div class="msg91-campaign-filter__status">

                <select name="status">

                    <option value="">
                        All statuses
                    </option>


                    @foreach(
                        [
                            'queued',
                            'processing',
                            'completed',
                            'cancelled'
                        ]
                        as
                        $status
                    )

                        <option
                            value="{{ $status }}"
                            {{
                                request('status') === $status
                                    ? 'selected'
                                    : ''
                            }}
                        >
                            {{ ucfirst($status) }}
                        </option>

                    @endforeach

                </select>

            </div>


            <button
                class="msg91-button"
                type="submit"
            >

                <i class="ri-filter-3-line"></i>

                Filter

            </button>


            @if(
                request('search')
                ||
                request('status')
            )

                <a
                    href="{{ route('admin.msg91.campaigns.index') }}"
                    class="msg91-button msg91-button--secondary"
                >

                    <i class="ri-close-line"></i>

                    Clear

                </a>

            @endif

        </form>

    </section>



    {{-- =====================================================
         CAMPAIGN DIRECTORY
         ===================================================== --}}

    <section class="msg91-card msg91-campaign-list-card">

        <header class="msg91-card__header">

            <div>

                <h2>
                    Campaign History
                </h2>

                <p>
                    Accepted means MSG91 accepted the request; delivered is a separate confirmed event.
                </p>

            </div>


            <span class="msg91-record-count">

                {{ number_format($campaigns->total()) }}

                {{
                    Str::plural(
                        'campaign',
                        $campaigns->total()
                    )
                }}

            </span>

        </header>



        {{-- =================================================
             DESKTOP TABLE
             ================================================= --}}

        <div class="msg91-campaign-table-wrap">

            <table class="msg91-campaign-table">

                <thead>

                    <tr>

                        <th>
                            Campaign
                        </th>

                        <th>
                            Created
                        </th>

                        <th>
                            Sender
                        </th>

                        <th>
                            Template
                        </th>

                        <th class="msg91-number-col">
                            Eligible
                        </th>

                        <th class="msg91-number-col">
                            Accepted
                        </th>

                        <th class="msg91-number-col">
                            Delivered
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($campaigns as $campaign)

                        <tr>


                            {{-- CAMPAIGN --}}
                            <td>

                                <div class="msg91-campaign-name">

                                    <strong>

                                        {{ $campaign->name }}

                                    </strong>


                                    <span>

                                        <i class="ri-user-line"></i>

                                        {{ $campaign->creator?->name ?? 'Unknown' }}

                                    </span>

                                </div>

                            </td>



                            {{-- CREATED --}}
                            <td>

                                <div class="msg91-campaign-date">

                                    <strong>

                                        {{
                                            $campaign
                                                ->created_at
                                                ?->format(
                                                    'd M Y'
                                                )
                                        }}

                                    </strong>


                                    <span>

                                        {{
                                            $campaign
                                                ->created_at
                                                ?->format(
                                                    'H:i'
                                                )
                                        }}

                                    </span>

                                </div>

                            </td>



                            {{-- SENDER --}}
                            <td>

                                <div class="msg91-campaign-sender">

                                    <i class="ri-whatsapp-line"></i>


                                    <span>

                                        {{
                                            $campaign
                                                ->sender
                                                ?->integrated_number
                                            ??
                                            'Not mapped'
                                        }}

                                    </span>

                                </div>

                            </td>



                            {{-- TEMPLATE --}}
                            <td>

                                <div class="msg91-campaign-template">

                                    <strong>

                                        {{
                                            $campaign
                                                ->template
                                                ?->name
                                            ??
                                            'N/A'
                                        }}

                                    </strong>


                                    <span>

                                        {{
                                            strtoupper(
                                                $campaign
                                                    ->template
                                                    ?->language
                                                ??
                                                ''
                                            )
                                        }}

                                    </span>

                                </div>

                            </td>



                            {{-- ELIGIBLE --}}
                            <td class="msg91-number-col">

                                <span class="msg91-count-badge">

                                    {{ number_format($campaign->eligible_count) }}

                                </span>

                            </td>



                            {{-- ACCEPTED --}}
                            <td class="msg91-number-col">

                                <span
                                    class="
                                        msg91-count-badge
                                        msg91-count-badge--accepted
                                    "
                                >

                                    {{ number_format($campaign->accepted_count) }}

                                </span>

                            </td>



                            {{-- DELIVERED --}}
                            <td class="msg91-number-col">

                                <span
                                    class="
                                        msg91-count-badge
                                        msg91-count-badge--delivered
                                    "
                                >

                                    {{ number_format($campaign->delivered_count) }}

                                </span>

                            </td>



                            {{-- STATUS --}}
                            <td>

                                <span
                                    class="
                                        msg91-status
                                        msg91-status--{{ strtolower($campaign->status) }}
                                    "
                                >

                                    {{ ucfirst($campaign->status) }}

                                </span>

                            </td>



                            {{-- ACTION --}}
                            <td>

                                <a
                                    class="msg91-view-button"
                                    href="{{
                                        route(
                                            'admin.msg91.campaigns.show',
                                            $campaign
                                        )
                                    }}"
                                >

                                    <i class="ri-eye-line"></i>

                                    View

                                </a>

                            </td>

                        </tr>


                    @empty


                        <tr>

                            <td
                                colspan="9"
                                class="msg91-empty"
                            >

                                <div class="msg91-empty__icon">

                                    <i class="ri-megaphone-line"></i>

                                </div>


                                <strong>

                                    No campaigns found

                                </strong>


                                <span>

                                    No campaign records are currently visible to you.

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

        <div class="msg91-campaign-mobile">

            @forelse($campaigns as $campaign)

                <article class="msg91-campaign-mobile-card">


                    {{-- HEADER --}}
                    <div class="msg91-campaign-mobile-card__header">

                        <div>

                            <strong>

                                {{ $campaign->name }}

                            </strong>


                            <span>

                                {{ $campaign->creator?->name ?? 'Unknown' }}

                            </span>

                        </div>


                        <span
                            class="
                                msg91-status
                                msg91-status--{{ strtolower($campaign->status) }}
                            "
                        >

                            {{ ucfirst($campaign->status) }}

                        </span>

                    </div>



                    {{-- DETAILS --}}
                    <div class="msg91-campaign-mobile-card__details">


                        <div>

                            <small>
                                Created
                            </small>

                            <strong>

                                {{
                                    $campaign
                                        ->created_at
                                        ?->format(
                                            'd M Y, H:i'
                                        )
                                }}

                            </strong>

                        </div>



                        <div>

                            <small>
                                Sender
                            </small>

                            <strong>

                                {{
                                    $campaign
                                        ->sender
                                        ?->integrated_number
                                    ??
                                    'Not mapped'
                                }}

                            </strong>

                        </div>



                        <div>

                            <small>
                                Template
                            </small>

                            <strong>

                                {{
                                    $campaign
                                        ->template
                                        ?->name
                                    ??
                                    'N/A'
                                }}

                            </strong>

                        </div>



                        <div>

                            <small>
                                Language
                            </small>

                            <strong>

                                {{
                                    strtoupper(
                                        $campaign
                                            ->template
                                            ?->language
                                        ??
                                        '-'
                                    )
                                }}

                            </strong>

                        </div>

                    </div>



                    {{-- COUNTS --}}
                    <div class="msg91-campaign-mobile-card__counts">


                        <div>

                            <span>
                                Eligible
                            </span>

                            <strong>

                                {{ number_format($campaign->eligible_count) }}

                            </strong>

                        </div>



                        <div>

                            <span>
                                Accepted
                            </span>

                            <strong>

                                {{ number_format($campaign->accepted_count) }}

                            </strong>

                        </div>



                        <div>

                            <span>
                                Delivered
                            </span>

                            <strong>

                                {{ number_format($campaign->delivered_count) }}

                            </strong>

                        </div>

                    </div>



                    <a
                        class="msg91-view-button msg91-view-button--mobile"
                        href="{{
                            route(
                                'admin.msg91.campaigns.show',
                                $campaign
                            )
                        }}"
                    >

                        <i class="ri-eye-line"></i>

                        View Campaign

                    </a>

                </article>


            @empty


                <div class="msg91-empty">

                    No campaigns found.

                </div>


            @endforelse

        </div>

    </section>



    {{-- =====================================================
         PAGINATION
         ===================================================== --}}

    @if($campaigns->hasPages())

        <div class="msg91-pagination">

            {{ $campaigns->links() }}

        </div>

    @endif

</main>

@endsection
