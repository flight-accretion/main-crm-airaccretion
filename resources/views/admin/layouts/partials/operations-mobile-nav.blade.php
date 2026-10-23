@php
    $navigationRole = auth()->user()?->userType?->user_type;
    $mobileTabs = match (true) {
        $navigationRole === \App\Models\UserType::HR => 'hr',
        in_array($navigationRole, \App\Models\UserType::OPERATIONS_ROLES, true) => 'operations',
        in_array($navigationRole, \App\Models\UserType::ACCOUNTS_ROLES, true) => 'accounts',
        in_array($navigationRole, \App\Models\UserType::SALES_ROLES, true) => 'sales',
        default => 'admin',
    };
    // The browser also checks each primary link against the authorized sidebar.
    $mobileTabLinks = match ($mobileTabs) {
        'hr' => [
            [route('admin.hr.dashboard'), 'ri-home-4-line', 'Dashboard'],
            [route('admin.clients.index'), 'ri-group-line', 'Leads'],
            [route('admin.kpi.work-done.index'), 'ri-checkbox-circle-line', 'Work Done'],
            [route('admin.operations.index'), 'ri-briefcase-line', 'Operations'],
        ],
        'operations' => [
            [route('admin.operations.dashboard'), 'ri-home-4-line', 'Dashboard'],
            [route('admin.operations.queue', 'review'), 'ri-star-line', 'Review'],
            [route('admin.rides.upcoming'), 'ri-calendar-event-line', 'Rides'],
        ],
        'accounts' => [
            [route('admin.account.payment-review'), 'ri-bank-card-line', 'Payments'],
            [route('admin.account.vendor-payments'), 'ri-wallet-3-line', 'Vendors'],
            [route('admin.rides.ride-status'), 'ri-calendar-check-line', 'Rides'],
        ],
        'sales' => [
            [route('admin.sales-dashboard'), 'ri-home-4-line', 'Dashboard'],
            [route('admin.clients.index'), 'ri-group-line', 'Leads'],
            [route('admin.upcoming-follow-up.index'), 'ri-time-line', 'Follow-up'],
            [route('admin.kpi.index'), 'ri-bar-chart-line', 'KPI'],
        ],
        default => [
            [route('admin.sales-dashboard'), 'ri-home-4-line', 'Dashboard'],
            [route('admin.clients.index'), 'ri-group-line', 'Leads'],
            [route('admin.operations.index'), 'ri-briefcase-line', 'Operations'],
            [route('admin.kpi.index'), 'ri-bar-chart-line', 'KPI'],
        ],
    };
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-mobile-nav.css') }}?v=3">
<div data-operations-mobile-navigation>
    <section class="operations-mobile-more" id="operations-mobile-more" role="dialog" aria-labelledby="operations-mobile-more-title" hidden>
        <header><h2 id="operations-mobile-more-title">More</h2><button type="button" data-close-operations-more aria-label="Close navigation" title="Close"><i class="ri-close-line" aria-hidden="true"></i></button></header>
        <nav data-operations-more-links aria-label="More tools"></nav>
    </section>
    <nav class="operations-mobile-nav" aria-label="Mobile navigation">
        @foreach ($mobileTabLinks as [$tabHref, $tabIcon, $tabLabel])
            <a href="{{ $tabHref }}" data-operations-primary><i class="{{ $tabIcon }}" aria-hidden="true"></i><span>{{ $tabLabel }}</span></a>
        @endforeach
        <button type="button" data-toggle-operations-more aria-expanded="false" aria-controls="operations-mobile-more" aria-haspopup="dialog"><i class="ri-apps-line" aria-hidden="true"></i><span>More</span></button>
    </nav>
</div>
<script src="{{ asset('assets/admin/js/crm/operations-mobile-nav.js') }}?v=3" defer></script>
