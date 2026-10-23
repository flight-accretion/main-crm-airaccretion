@extends('admin.layouts.header')
@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/libs/fullcalendar/main.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/upcoming-rides.css') }}?v=2">
    @php
        $summary = $schedule['summary'];
        $isDefaultPeriod = $currentFilters['from_date'] === now()->toDateString() && $currentFilters['to_date'] === now()->addDays(13)->toDateString();
        $period = \Carbon\Carbon::parse($currentFilters['from_date'])->format('d M') . ' - ' . \Carbon\Carbon::parse($currentFilters['to_date'])->format('d M Y');
        $hasFilters = request()->hasAny(['from_date', 'to_date', 'status', 'representative_user_id', 'product_id', 'is_tba']);
    @endphp
    <div class="crm-upcoming" id="upcoming-rides" data-user="{{ auth()->id() }}" data-today="{{ now()->toDateString() }}"
        data-period-start="{{ $currentFilters['from_date'] }}" data-period-end="{{ $currentFilters['to_date'] }}"
        data-base-url="{{ route('admin.rides.upcoming') }}">
        <header class="ur-heading">
            <div>
                <h1>Upcoming Rides</h1>
                <p>{{ $period }}</p>
            </div>
            <div class="ur-actions">
                <div class="ur-view-switch" role="tablist" aria-label="Ride view">
                    <button type="button" role="tab" id="ur-list-tab" aria-controls="ur-list" aria-selected="true"
                        data-ride-view="list"><i class="ri-list-check" aria-hidden="true"></i> List</button>
                    <button type="button" role="tab" id="ur-calendar-tab" aria-controls="ur-calendar-view"
                        aria-selected="false" data-ride-view="calendar"><i class="ri-calendar-line" aria-hidden="true"></i>
                        Calendar</button>
                </div>
            </div>
        </header>
        @foreach(['success', 'error'] as $messageType)
            @if(session($messageType))
            <p class="ur-notice" role="status">{{ session($messageType) }}</p>@endif
        @endforeach
        @if($errors->any())
        <p class="ur-notice ur-urgent" role="alert">{{ $errors->first() }}</p>@endif
        <section class="ur-stats" aria-label="Ride summary">
            <div>
                <strong>{{ $summary['total'] }}</strong><span>{{ $isDefaultPeriod ? 'Rides next 14 days' : 'Rides in selected period' }}</span><small>{{ $summary['today'] }}
                    today &middot; {{ $summary['tomorrow'] }} tomorrow</small></div>
            <div><strong>{{ $summary['unconfirmed'] }}</strong><span>Time TBA within 48h</span><small>Service time not
                    confirmed</small></div>
            <div><strong>{{ $summary['payment_pending'] }}</strong><span>Payment pending</span><small>Approved payment
                    balance</small></div>
            <div><strong>{{ $summary['paid_percent'] }}%</strong><span>Fully paid</span><small>{{ $summary['fully_paid'] }}
                    of {{ $summary['total'] }} rides</small></div>
        </section>
        <details class="ur-filters" @if($hasFilters) open @endif>
            <summary><i class="ri-filter-3-line" aria-hidden="true"></i> Filters</summary>
            <form method="GET" action="{{ route('admin.rides.upcoming') }}" id="ur-filter-form">
                <input type="hidden" name="view" id="ur-view-input" value="{{ request('view', 'list') }}">
                <label>From date<input type="date" name="from_date" value="{{ $currentFilters['from_date'] }}"></label>
                <label>To date<input type="date" name="to_date" value="{{ $currentFilters['to_date'] }}"></label>
                <label>Sales representative<select name="representative_user_id">
                        <option value="">All staff</option>
                        <option value="unassigned" @if($currentFilters['representative_user_id'] === 'unassigned') selected
                        @endif>Unassigned</option>@foreach($salesReps as $rep)
                                <option value="{{ $rep->id }}" @if($currentFilters['representative_user_id'] === $rep->id) selected
                            @endif>{{ $rep->name }}</option>@endforeach
                    </select></label>
                <label>Ride status<select name="status">
                        <option value="">All current statuses</option>@foreach($availableStatuses as $id => $label)
                            <option value="{{ $id }}" @if(!is_array($currentFilters['status']) && (string) $currentFilters['status'] === (string) $id) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select></label>
                <label>Product<select name="product_id">
                        <option value="">All products</option>@foreach($products as $product)
                            <option value="{{ $product->id }}" @if($currentFilters['product_id'] === $product->id) selected
                        @endif>{{ $product->product }}</option>@endforeach
                    </select></label>
                <label>Time<select name="is_tba">
                        <option value="">All times</option>
                        <option value="1" @if((string) $currentFilters['is_tba'] === '1') selected @endif>TBA</option>
                        <option value="0" @if((string) $currentFilters['is_tba'] === '0') selected @endif>Time recorded
                        </option>
                    </select></label>
                <div class="ur-filter-actions"><a class="ur-button" href="{{ route('admin.rides.upcoming') }}"
                        title="Reset filters" aria-label="Reset filters"><i class="ri-refresh-line"
                            aria-hidden="true"></i></a><button class="ur-button ur-primary" type="submit">Filter</button>
                </div>
            </form>
        </details>
        <section class="ur-schedule" id="ur-list" role="tabpanel" aria-labelledby="ur-list-tab">
            <div class="ur-section-heading">
                <h2>Ride schedule</h2><span class="ur-tag">{{ $period }}</span>
            </div>
            @forelse($schedule['groups'] as $day => $rides)
                <div class="ur-day-heading">
                    <h3>{{ $day === now()->toDateString() ? 'Today' : ($day === now()->addDay()->toDateString() ? 'Tomorrow' : \Carbon\Carbon::parse($day)->format('l')) }}
                        &middot; {{ \Carbon\Carbon::parse($day)->format('d M') }}</h3><span>{{ $rides->count() }}
                        {{ $rides->count() === 1 ? 'ride' : 'rides' }}</span>
                </div>
                <table class="ur-table">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Product</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Due</th>
                            <th>Owner</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rides as $ride)
                            <tr class="{{ $ride['urgent'] ? 'ur-alert-row' : '' }}" data-ride-id="{{ $ride['id'] }}">
                                <td data-label="Client"><a class="ur-client" href="{{ $ride['lead_url'] }}" target="_blank"
                                        rel="noopener"><span
                                            class="ur-avatar">{{ strtoupper(substr($ride['client_name'], 0, 2)) }}</span><span>{{ $ride['client_name'] }}<small>{{ $ride['contact_number'] }}</small></span></a>
                                </td>
                                <td data-label="Product">
                                    {{ $ride['product_names'] ?: 'Not specified' }}<small>{{ $ride['from_place'] }} &rarr;
                                        {{ $ride['to_place'] }}</small></td>
                                <td data-label="Time">{{ $ride['ride_time'] }}</td>
                                <td data-label="Status"><span
                                        class="ur-tag">{{ $ride['status_name'] }}</span>@unless($ride['has_vendor'])<small
                                        class="ur-vendor-text">Vendor not assigned</small>@endunless</td>
                                <td data-label="Payment"><span
                                        class="ur-tag {{ $ride['fully_paid'] ? 'ur-ready' : ($ride['pending_amount_value'] > 0 ? 'ur-payment' : '') }}">{{ $ride['payment_label'] }}</span>
                                </td>
                                <td data-label="Due">@if($ride['pending_amount_value'] > 0)<span
                                class="ur-tag ur-payment">&#8377;{{ number_format($ride['pending_amount_value'], 2) }}</span>@else
                                        &mdash; @endif</td>
                                <td data-label="Owner">
                                    {{ $ride['sales_person_name'] === 'N/A' ? 'Unassigned' : $ride['sales_person_name'] }}</td>
                                <td data-label="Details"><button type="button" class="ur-button ur-icon"
                                        data-ride-details="{{ $ride['id'] }}" title="View ride details"
                                        aria-label="View ride details for {{ $ride['client_name'] }}"><i class="ri-eye-line"
                                            aria-hidden="true"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty<p class="ur-empty">No rides match these filters.</p>@endforelse
        </section>
        <div class="ur-calendar-layout" id="ur-calendar-view" role="tabpanel" aria-labelledby="ur-calendar-tab" hidden>
            <section class="ur-calendar-section" aria-label="Ride calendar">
                <header class="ur-calendar-heading">
                    <h2 id="ur-month"></h2>
                    <div class="ur-actions"><button class="ur-button ur-icon" type="button" id="ur-prev"
                            title="Previous month" aria-label="Previous month"><i class="ri-arrow-left-line"
                                aria-hidden="true"></i></button><button class="ur-button" type="button"
                            id="ur-today">Today</button><button class="ur-button ur-icon" type="button" id="ur-next"
                            title="Next month" aria-label="Next month"><i class="ri-arrow-right-line"
                                aria-hidden="true"></i></button></div>
                </header>
                <div class="ur-legend"><span class="ur-ready">Paid &amp; vendor assigned</span><span
                        class="ur-vendor">Vendor not assigned</span><span class="ur-payment">Payment pending</span><span
                        class="ur-urgent">Time TBA within 48h</span></div>
                <div id="ur-calendar"></div>
                <p id="ur-calendar-error" role="alert" hidden>Calendar could not load. The list view is still available.</p>
            </section>
            <section class="ur-day-panel" id="ur-day-panel" aria-label="Selected day" aria-live="polite"></section>
        </div>
        <dialog id="ur-details-dialog" class="ur-details-dialog" aria-labelledby="ur-details-title">
            <header>
                <h2 id="ur-details-title">Ride details</h2><button type="button" class="ur-button ur-icon"
                    id="ur-details-close" title="Close" aria-label="Close ride details"><i class="ri-close-line"
                        aria-hidden="true"></i></button>
            </header>
            <div id="ur-details-content"></div>
        </dialog>
        <script type="application/json" id="ur-data">@json($schedule['events'])</script>
    </div>
    <script src="{{ asset('assets/admin/libs/fullcalendar/main.min.js') }}"></script>
    <script src="{{ asset('assets/admin/js/crm/upcoming-rides.js') }}" defer></script>
@endsection
