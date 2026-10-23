@extends('admin.layouts.header')
@section('content')
    @php
        $scope = $team ? [] : ['operations_user_id' => auth()->id()];
        $queue = fn($type, $extra = []) => route('admin.operations.queue', array_merge(['type' => $type], $scope, $extra));
        $caseUrl = fn($case) => match ($case->type) {
            'vendor_followup' => route('admin.operations.vendor-followups.index', $team ? [] : ['assigned_to' => auth()->id()]),
            'reschedule' => route('admin.operations.reschedules.index'),
            'refund' => route('admin.refunds.index'),
            default => $queue($case->type, ['search' => $case->lead_id]),
        };
        $date = fn($value) => $value ? \Carbon\Carbon::parse($value)->format('d M') : 'None pending';
        $vendorUrl = route('admin.operations.vendor-followups.index', $team ? [] : ['assigned_to' => auth()->id()]);
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-home.css') }}?v=1">
    <main class="oh-dashboard">
        <header class="oh-heading">
            <div>
                <h1>Operations Dashboard</h1>
                <p>{{ now()->format('l, d F Y') }} &middot; Your priorities, deadlines and performance</p>
            </div><span class="oh-badge blue">{{ $team ? 'Team workspace' : 'Personal workspace' }}</span>
        </header>
        <div class="oh-label">
            <h2>Today &middot; needs your attention</h2>
            <span>{{ $team ? 'All Operations work' : 'My assigned work' }}</span>
        </div>
        <div class="oh-today">
            <a href="{{ route('admin.rides.upcoming', ['from_date' => now()->toDateString(), 'to_date' => now()->toDateString()]) }}"
                class="oh-metric">
                <div class="oh-metric-top"><i class="ri-flight-takeoff-line" aria-hidden="true"></i><i
                        class="ri-arrow-right-up-line" aria-hidden="true"></i></div>
                <strong>{{ number_format($todayRides) }}</strong>
                <h3>Today's rides</h3>
                <p>Next departure <b>{{ $nextRide?->from_date?->format('d M, g:i A') ?? 'None scheduled' }}</b></p>
            </a>
            <a href="#oh-cases" class="oh-metric">
                <div class="oh-metric-top"><i class="ri-list-check-3" aria-hidden="true"></i><i
                        class="ri-arrow-right-up-line" aria-hidden="true"></i></div>
                <strong>{{ number_format($openCount) }}</strong>
                <h3>{{ $team ? 'Open cases' : 'My open cases' }}</h3>
                <p>Oldest five cases</p>
            </a>
            <a href="{{ route('admin.operations.dashboard', ['cases' => 'due']) }}#oh-results" class="oh-metric">
                <div class="oh-metric-top"><i class="ri-time-line" aria-hidden="true"></i><i class="ri-arrow-right-up-line"
                        aria-hidden="true"></i></div><strong>{{ number_format($dueToday) }}</strong>
                <h3>Due today</h3>
                <p><span class="oh-badge amber">{{ $dueToday ? 'Action required' : 'Up to date' }}</span>
                    {{ $nextDue ? 'Next ' . \Carbon\Carbon::parse($nextDue)->format('g:i A') : '' }}</p>
            </a>
        </div>
         <div class="oh-label">
            <h2>Waiting on us</h2><span>Customer &amp; vendor follow-ups</span>
        </div>
        <div class="oh-two"><a class="oh-summary" href="{{ $vendorUrl }}">
                <h2>Vendor follow-ups <span class="oh-badge amber">Action needed</span></h2><strong>{{ $vendorCount }}
                    <small>open</small></strong>
                <p><span class="oh-red">{{ $vendorOverdue }} overdue</span> &middot; Oldest
                    {{ $date($vendorOldest) }}<br>Outstanding vendor dates and slots <i class="ri-arrow-right-up-line"
                        aria-hidden="true"></i></p>
            </a><a class="oh-summary" href="{{ $queue('review', ['segment' => 'waiting']) }}">
                <h2>Awaiting customer review <span class="oh-badge amber">Pending reply</span></h2>
                <strong>{{ $reviewCount }} <small>reviews</small></strong>
                <p>Oldest waiting {{ $date($reviewOldest) }}<br>Customer follow-ups <i class="ri-arrow-right-up-line"
                        aria-hidden="true"></i></p>
            </a></div>
        <div class="oh-label">
            <h2>Money exposure</h2><span>{{ $team ? 'Team bookings' : 'Assigned bookings' }}</span>
        </div>
        <div class="oh-two"><a class="oh-summary" href="{{ route('admin.refunds.index') }}">
                <h2>Refund amount pending <span class="oh-badge red">Pending</span></h2>
                <strong>&#8377;{{ number_format($customerRefund + $vendorRefund, 2) }}</strong>
                <p>Customer &#8377;{{ number_format($customerRefund, 2) }} &middot; Vendor
                    &#8377;{{ number_format($vendorRefund, 2) }}<br>Current pending refund records</p>
            </a><a class="oh-summary" href="{{ route('admin.account.payment-review') }}">
                <h2>Part-paid bookings nearing ride <span class="oh-badge amber">Within 7 days</span></h2>
                <strong>{{ $partPaidCount }} <small>bookings</small></strong>
                <p>Outstanding balance &#8377;{{ number_format($partPaidBalance, 2) }}<br>Latest CRM follow-up amounts</p>
            </a></div>
        <div class="oh-label">
            <h2>My standing</h2><span>{{ $team ? 'Personal KPI not selected' : 'Existing KPI score' }}</span>
        </div>
        <div class="oh-standing"><a class="oh-panel" href="{{ route('admin.operations.kpi') }}">
                <div class="oh-panel-head">
                    <h2>My KPI progress</h2><span class="oh-badge blue">{{ now()->format('F Y') }}</span>
                </div>@foreach(['day' => "Today's score", 'month' => 'Monthly score'] as $period => $label)
                    <div class="oh-progress">
                        <span>{{ $label }}</span><b>{{ $kpi[$period] !== null ? $kpi[$period] . ' / 5' : 'Not configured' }}</b>
                    </div><progress value="{{ $kpi[$period] ?? 0 }}" max="5" aria-label="{{ $label }}">{{ $kpi[$period] ?? 0 }}
                / 5</progress>@endforeach<p class="oh-muted">KPI breakdown <i class="ri-arrow-right-up-line"
                        aria-hidden="true"></i></p>
            </a>
            <aside class="oh-incentive">
                <h2><i class="ri-sparkling-line" aria-hidden="true"></i> Incentive &middot; {{ now()->format('F Y') }}</h2>
                @if($incentive['status'] === 'configured')
                    <strong>&#8377;{{ number_format($incentive['amount'], 2) }} <small>estimated</small></strong>
                    <p>Based on your active CRM incentive settings and qualifying sales. Estimate only, not a confirmed payout.</p>
                @else
                    <strong>{{ $incentive['status'] === 'duplicate' ? 'Check configuration' : ($team ? 'Personal view required' : 'Not configured') }}</strong>
                    <p>{{ $incentive['status'] === 'duplicate' ? 'Multiple active targets exist for this month. Ask HR or Super Admin to resolve them.' : ($team ? 'Individual incentives are shown in the employee Operations dashboard.' : 'No active incentive configured for this month.') }}</p>
                @endif
            </aside>
        </div>
        <div class="oh-columns">
            <section class="oh-panel" id="oh-cases">
                <div class="oh-panel-head">
                    <h2>{{ $team ? 'Open cases' : 'My open cases' }}</h2><a class="oh-link"
                        href="{{ route('admin.operations.dashboard', ['cases' => 'all']) }}#oh-results">View all
                        {{ $openCount }} <i class="ri-arrow-right-up-line" aria-hidden="true"></i></a>
                </div>
                @forelse($oldest as $case)
                    @php $overdue = $case->next_followup_at && $case->next_followup_at->lt(now()); @endphp
                    <a href="{{ $caseUrl($case) }}" class="oh-case">
                        <div>
                            <h3>{{ ucwords(str_replace('_', ' ', $case->type)) }} &middot;
                                {{ $case->lead?->client?->name ?? 'Customer unavailable' }}</h3>
                            <p>#{{ substr($case->id, 0, 8) }} &middot;
                                {{ $case->next_followup_at ? 'Due ' . $case->next_followup_at->format('d M, g:i A') : 'No follow-up date' }}
                            </p>
                        </div>
                        <div class="oh-case-right">
                            <p>{{ $case->opened_at ? max(0, $case->opened_at->diffInDays(now())) : 0 }} days old</p><span
                                class="oh-badge {{ $overdue ? 'red' : 'amber' }}">{{ $overdue ? 'Overdue' : ucwords(str_replace('_', ' ', $case->status)) }}</span>
                        </div>
                    </a>
                @empty <p class="oh-empty">No open cases.</p> @endforelse
            </section>
            <section class="oh-panel">
                <div class="oh-panel-head">
                    <h2>Next departure</h2><a class="oh-link" href="{{ route('admin.rides.upcoming') }}">Upcoming rides <i
                            class="ri-arrow-right-up-line" aria-hidden="true"></i></a>
                </div>
                @if($nextRide)
                    <div class="oh-next"><strong>{{ $nextRide->from_date->format('H:i') }}</strong>
                        <div><b>{{ $nextRide->from_place ?: 'Departure' }} &rarr;
                                {{ $nextRide->to_place ?: 'Destination' }}</b><small>{{ $nextRide->from_date->format('d M Y') }}</small>
                        </div>
                </div>@else<p class="oh-empty">No upcoming departure.</p>@endif
                <div class="oh-ride-counts"><a
                        href="{{ route('admin.rides.upcoming') }}"><b>{{ $unconfirmed }}</b><span>Rides within 48h
                            unconfirmed</span></a><a
                        href="{{ route('admin.operations.reschedules.index', ['segment' => 'expiring']) }}"><b>{{ $expiring }}</b><span>Reschedules
                            expiring soon</span></a></div>
                <p class="oh-muted">Within 10 days of the original service date's 90-day expiry.</p>
            </section>
        </div>
        <div class="oh-label">
            <h2>Aging &amp; deadline alerts</h2><span>Open cases</span>
        </div>
        <section class="oh-panel">
            <div class="oh-age">
                @foreach([['recent', '0-1 days'], ['week', '2-7 days'], ['older', '&gt; 7 days']] as [$key, $label])<a
                    href="{{ route('admin.operations.dashboard', ['cases' => $key]) }}#oh-results"><b
                        class="{{ $key === 'older' ? 'oh-red' : '' }}">{{ $age[$key] }}</b><span>{!! $label !!}</span><small>Open
                cases</small></a>@endforeach</div>
            <div class="oh-alerts"><a href="{{ $queue('customer_call') }}">
                    <div>
                        <h3>Customer calls backlog <span class="oh-badge red">Aging</span></h3>
                        <p>Oldest pending {{ $date($callsOldest) }}</p>
                    </div><b>{{ number_format($callsCount) }} <i class="ri-arrow-right-up-line" aria-hidden="true"></i></b>
                </a><a href="{{ route('admin.rides.upcoming') }}">
                    <div>
                        <h3>Rides within 48h without confirmation</h3>
                        <p>Current CRM confirmation status</p>
                    </div><b>{{ $unconfirmed }} <i class="ri-arrow-right-up-line" aria-hidden="true"></i></b>
                </a><a href="{{ route('admin.operations.reschedules.index', ['segment' => 'expiring']) }}">
                    <div>
                        <h3>Reschedule validity ending</h3>
                        <p>90-day window &middot; next 10 days</p>
                    </div><b>{{ $expiring }} <i class="ri-arrow-right-up-line" aria-hidden="true"></i></b>
                </a></div>
        </section>
       
        <div class="oh-label">
            <h2>Recent activity</h2><a class="oh-link" href="{{ route('admin.operations.history', $scope) }}">Completed work
                history <i class="ri-arrow-right-up-line" aria-hidden="true"></i></a>
        </div>
        <section class="oh-panel">@forelse($activities as $activity)<a class="oh-activity"
            href="{{ $activity->case ? $caseUrl($activity->case) : route('admin.operations.history', $scope) }}"><i
                class="ri-checkbox-blank-circle-line" aria-hidden="true"></i>
            <div><b>{{ ucwords(str_replace('_', ' ', $activity->action)) }}</b>
                <p>{{ $activity->case?->lead?->client?->name ?? 'Customer unavailable' }} &middot;
                    {{ $activity->user?->name ?? 'System' }}</p>
            </div><time
                datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time>
        </a>@empty<p class="oh-empty">No recent activity.</p>@endforelse</section>
        @if($caseRows)
            <div class="oh-label" id="oh-results">
                <h2>{{ ['all' => 'All open cases', 'due' => 'Due today', 'recent' => 'Open 0-1 days', 'week' => 'Open 2-7 days', 'older' => 'Open over 7 days'][$caseBucket] }}
                </h2><a class="oh-link" href="{{ route('admin.operations.dashboard') }}">Close list</a>
            </div>
            <section class="oh-panel">@forelse($caseRows as $case)<a class="oh-case" href="{{ $caseUrl($case) }}">
                <div>
                    <h3>{{ ucwords(str_replace('_', ' ', $case->type)) }} &middot;
                        {{ $case->lead?->client?->name ?? 'Customer unavailable' }}</h3>
                    <p>{{ $case->next_followup_at ? 'Due ' . $case->next_followup_at->format('d M, g:i A') : 'No follow-up date' }}
                    </p>
                </div><span class="oh-badge amber">{{ ucwords(str_replace('_', ' ', $case->status)) }}</span>
            </a>@empty<p class="oh-empty">No open cases for this selection.</p>@endforelse<div class="oh-panel-head">
                    <span>{{ $caseRows->firstItem() ?? 0 }}-{{ $caseRows->lastItem() ?? 0 }} of
                        {{ $caseRows->total() }}</span>@if($caseRows->previousPageUrl())<a
                        href="{{ $caseRows->previousPageUrl() }}#oh-results" class="oh-link">Previous</a>@endif
                    @if($caseRows->nextPageUrl())<a href="{{ $caseRows->nextPageUrl() }}#oh-results"
                    class="oh-link">Next</a>@endif</div>
            </section>
        @endif
    </main>
@endsection
