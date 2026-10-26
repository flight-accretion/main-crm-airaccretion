@extends('admin.layouts.header')
@section('content')
@php
    $rupees = fn ($amount) => number_format($amount, 2);
    $compact = fn ($amount) => abs($amount) >= 10000000 ? number_format($amount / 10000000, 2).' Cr' : (abs($amount) >= 100000 ? number_format($amount / 100000, 2).' L' : number_format($amount, 2));
    $age = fn ($value) => $value ? max(0, (int) \Carbon\Carbon::parse($value)->startOfDay()->diffInDays(today())).' days' : 'None waiting';
    $cards = [
        ['amber', 'Payments pending approval', number_format($pending['payments']), '&#8377;'.$compact($pending['payment_amount']), $pending['payment_oldest'] ? 'Oldest waiting '.$age($pending['payment_oldest']) : 'None waiting', route('admin.account.payment-review', ['payment_status' => 'pending']), 'Open queue'],
        ['red', 'Vendor payments overdue', number_format($pending['vendor_overdue']), '&#8377;'.$compact($pending['vendor_overdue_amount']), 'Outstanding after service date', route('admin.account.vendor-payments'), 'Vendor payments'],
        ['amber', 'Customer refunds pending', number_format($pending['customer_refunds']), '&#8377;'.$compact($pending['customer_amount']), $pending['customer_oldest'] ? 'Oldest waiting '.$age($pending['customer_oldest']) : 'None waiting', route('admin.refunds.index', ['refund_status' => 'pending']), 'Review refunds'],
        ['amber', 'Vendor refund due', number_format($pending['vendor_refunds']), '&#8377;'.$compact($pending['vendor_refund_amount']), 'Open vendor refunds', route('admin.account.vendor-refunds'), 'Vendor refunds'],
        ['amber', 'Invoice pending', number_format($pending['invoices']), '', 'Awaiting generation', route('admin.account.invoices', ['status' => 'awaiting']), 'Open invoices'],
        ['blue', 'Exceptional entries open', number_format($pending['exceptional']), '&#8377;'.$compact($pending['exceptional_amount']), 'Customer overpayments', route('admin.account.exceptional'), 'Exceptional'],
        ['brand', 'Invoices generated / finalized', $invoices['generated'].' / '.$invoices['finalized'], '', 'Created in '.$month->format('F Y'), route('admin.account.invoices', ['invoice_created_from' => $month->toDateString(), 'invoice_created_to' => $month->copy()->endOfMonth()->toDateString(), 'status' => 'all']), 'View invoices'],
        ['green', 'Approvals within 10 minutes', $approval['percent'] === null ? '--' : $approval['percent'].'%', '', $approval['count'] ? $approval['count'].($approval['count'] === 1 ? ' approval' : ' approvals').' · avg '.$approval['average_minutes'].' min' : 'No timed approvals this month', route('admin.account.payment-review', ['payment_status' => 'approved']), 'Payment approvals'],
    ];
    $peak = max(1, max(array_column($chart, 'amount')));
@endphp
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/accounts-home.css') }}?v=1">
<main class="ah-dashboard">
    <header class="ah-heading">
        <div><h1>Accounts Dashboard</h1><p>{{ $month->format('F Y') }} <span aria-hidden="true">&middot;</span> Updated {{ now()->format('g:i A') }}</p></div>
        <form method="GET" action="{{ route('admin.account.dashboard') }}" class="ah-month">
            <label for="accounts-month">Month</label>
            <input id="accounts-month" type="month" name="month" value="{{ $month->format('Y-m') }}" min="2000-01" max="2099-12" required>
            <button type="submit" aria-label="Apply month" title="Apply month"><i class="ri-check-line" aria-hidden="true"></i></button>
        </form>
    </header>
    <h2 class="ah-strip">Needs decision today</h2>
    <section class="ah-tiles" aria-label="Accounts priorities">
        @foreach($cards as [$tone, $label, $value, $amount, $detail, $href, $action])
            <a class="ah-tile {{ $tone }}" href="{{ $href }}">
                <div class="ah-value"><strong>{{ $value }}</strong>@if($amount)<small>{!! $amount !!}</small>@endif</div>
                <h3>{{ $label }}</h3><p>{{ $detail }}</p>
                <span class="ah-link">{{ $action }} <i class="ri-arrow-right-line" aria-hidden="true"></i></span>
            </a>
        @endforeach
    </section>
    <h2 class="ah-strip">Money this month</h2>
    <section class="ah-money" aria-label="Money summary">
        <div class="ah-money-item">
            <h3>Collected (approved)</h3><strong title="&#8377;{{ $rupees($money['collected']) }}">&#8377;{{ $compact($money['collected']) }}</strong>
            @if($previous['collected'] > 0)
                @php($delta = round(($money['collected'] - $previous['collected']) / $previous['collected'] * 100, 1))
                <p><b class="{{ $delta >= 0 ? 'ah-positive' : 'ah-negative' }}">{{ $delta >= 0 ? '+' : '' }}{{ $delta }}%</b> vs &#8377;{{ $compact($previous['collected']) }} last month</p>
            @else<p>Last month &#8377;{{ $compact($previous['collected']) }}</p>@endif
        </div>
        <div class="ah-money-item"><h3>Paid to vendors</h3><strong title="&#8377;{{ $rupees($money['vendor_paid']) }}">&#8377;{{ $compact($money['vendor_paid']) }}</strong><p>{{ number_format($money['vendor_payments']) }} payments in {{ $month->format('M Y') }}</p></div>
        <div class="ah-money-item"><h3>Refund exposure</h3><strong title="&#8377;{{ $rupees($money['exposure']) }}">&#8377;{{ $compact($money['exposure']) }}</strong><p>Customer &#8377;{{ $compact($pending['customer_amount']) }} &middot; vendor &#8377;{{ $compact($pending['vendor_refund_amount']) }}</p><small>Current outstanding</small></div>
        <div class="ah-money-item"><h3>Net inflow</h3><strong class="{{ $money['net'] < 0 ? 'ah-negative' : '' }}" title="&#8377;{{ $rupees($money['net']) }}">&#8377;{{ $compact($money['net']) }}</strong><p>After vendor payments and actual refunds</p><small>Last month &#8377;{{ $compact($previous['net']) }}</small></div>
    </section>
    <section class="ah-chart-panel" aria-labelledby="accounts-chart-title">
        <header><div><h2 id="accounts-chart-title">Received per day &mdash; last 14 days</h2><p>{{ $chartStart->format('d M') }} &ndash; {{ $chartEnd->format('d M Y') }} &middot; Approved collections</p></div><strong>&#8377;{{ $compact(array_sum(array_column($chart, 'amount'))) }}</strong></header>
        <div class="ah-chart" role="list" aria-label="Daily approved collections">
            @foreach($chart as $day)
                <div class="ah-chart-day" role="listitem" tabindex="0" aria-label="{{ $day['date'] }}: Rs. {{ $rupees($day['amount']) }}" data-amount="{{ $day['amount'] }}">
                    <span class="ah-chart-value">&#8377;{{ $compact($day['amount']) }}</span>
                    <div class="ah-chart-track"><span class="ah-bar {{ $loop->last ? 'current' : '' }}" style="height:{{ max(0, min(100, $day['amount'] / $peak * 100)) }}%"></span></div>
                    <span class="ah-day-label">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
        @if(array_sum(array_column($chart, 'amount')) <= 0)<p class="ah-empty">No approved collections in this period.</p>@endif
    </section>
</main>
@endsection
