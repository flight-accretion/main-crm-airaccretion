@php($refundSummary = app(\App\Services\Operations\RefundSummaryService::class)->summary())
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/refunds.css') }}">
<header class="refund-heading"><h1>Refunds</h1><p>Customer and vendor refunds</p></header>
<section class="refund-summary" aria-label="Refund summary">
    <div class="refund-stat"><strong>{{ number_format($refundSummary['customer_pending']) }}</strong><span>Customer refund pending</span><small>Not yet completed</small></div>
    <div class="refund-stat"><strong>{{ number_format($refundSummary['vendor_pending']) }}</strong><span>Vendor refund pending</span><small>Not yet completed</small></div>
    <div class="refund-stat"><strong>{{ $refundSummary['waiting_approval'] === null ? '--' : number_format($refundSummary['waiting_approval']) }}</strong><span>Waiting approval</span><small>{{ $refundSummary['waiting_approval'] === null ? 'Approval tracking not set up' : 'Open customer + vendor refunds' }}</small></div>
    <div class="refund-stat"><strong>{{ $refundSummary['average_days'] === null ? '--' : $refundSummary['average_days'].' days' }}</strong><span>Avg time to process</span><small>Completed vendor refunds only</small></div>
</section>
<nav class="refund-tabs" aria-label="Refund type">
    <a href="{{ route('admin.refunds.index') }}" class="{{ request()->routeIs('admin.refunds.*') ? 'active' : '' }}">Customer refunds</a>
    @if(in_array(auth()->user()->userType->user_type, array_merge(\App\Models\UserType::OPERATIONS_ROLES, \App\Models\UserType::ADMIN_ROLES), true))
        <a href="{{ route('admin.operations.vendor-refunds.index') }}" class="{{ request()->routeIs('admin.operations.vendor-refunds.*') || request()->routeIs('admin.account.vendor-refunds') ? 'active' : '' }}">Vendor refunds</a>
    @elseif(in_array(auth()->user()->userType->user_type, \App\Models\UserType::ACCOUNTS_ROLES, true))
        {{-- Accounts can't open the Operations vendor refund page; their tab opens the account Vendor Refund list. --}}
        <a href="{{ route('admin.account.vendor-refunds') }}" class="{{ request()->routeIs('admin.account.vendor-refunds') ? 'active' : '' }}">Vendor refunds</a>
    @endif
</nav>
<script src="{{ asset('assets/admin/js/crm/refunds.js') }}" defer></script>
