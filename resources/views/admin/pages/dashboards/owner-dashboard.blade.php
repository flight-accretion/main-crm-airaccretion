@extends('admin.layouts.header')
@section('content')
    @php
        $rupees = fn($amount) => number_format((float) $amount, 2);
        $compact = fn($amount) => abs($amount) >= 10000000 ? number_format($amount / 10000000, 2) . ' Cr' : (abs($amount) >= 100000 ? number_format($amount / 100000, 2) . ' L' : number_format($amount, 2));
        $deltaPill = function ($delta) {
            if ($delta === null)
                return '<span class="ow-delta flat">—</span>';
            $up = $delta >= 0;
            return '<span class="ow-delta ' . ($up ? 'up' : 'down') . '"><i class="ri-arrow-' . ($up ? 'up' : 'down') . '-line"></i>' . abs($delta) . '%</span>';
        };
        // sparkline points from the company sales series (14 values)
        $sparkPoints = function (array $series, int $w = 96, int $h = 36) {
            $max = max(1, max($series));
            $step = $w / max(1, count($series) - 1);
            $pts = [];
            foreach (array_values($series) as $i => $v) {
                $pts[] = round($i * $step, 1) . ',' . round($h - 2 - ($v / $max) * ($h - 6), 1);
            }
            return implode(' ', $pts);
        };
        $sparkline = function (array $series, string $color) use ($sparkPoints) {
            $pts = $sparkPoints($series);
            return '<svg width="96" height="36" viewBox="0 0 96 36" fill="none" aria-hidden="true">'
                . '<polyline points="' . $pts . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        };
        $attTone = fn($pct) => $pct === null ? 'flat' : ($pct >= 80 ? 'up' : ($pct >= 50 ? 'flat' : 'down'));
        $attFill = fn($pct) => $pct === null ? '#98a2b3' : ($pct >= 80 ? '#178a4c' : ($pct >= 50 ? '#2f6fed' : '#e5484d'));
        $attBar = fn($pct) => $pct === null ? '#98a2b3' : ($pct >= 80 ? '#178a4c' : ($pct >= 50 ? '#2f6fed' : '#e5484d'));
        $casePalette = ['#2f6fed', '#d97706', '#178a4c', '#7c3aed', '#e5484d', '#0ea5e9'];
        $heartStale = $automation['heartbeat_stale'] ?? true;
        $failedJobs = (int) ($automation['failed_jobs'] ?? 0);
        $failedWarn = (int) config('crm.failed_jobs_warning', 50);
        $automationHealth = $heartStale
            ? ['label' => 'scheduler stale', 'tone' => 'warn']
            : ($failedJobs === 0
                ? ['label' => 'all systems operational', 'tone' => 'ok']
                : ($failedJobs > $failedWarn
                    ? ['label' => 'action required', 'tone' => 'warn']
                    : ['label' => 'operational with warnings', 'tone' => 'warn']));
        $ownerDashboardName = config('crm.owner_dashboard_name', 'Rahul');
        $attentionCount = array_sum(array_map(fn($item) => (int) ($item['count'] ?? 0), $attention));
    @endphp
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/dashboards/owner-dashboard.css') }}?v=1">

    <main class="od2">
        <div class="ow-wrap">

            {{-- ================= GREETING ================= --}}
            <header class="ow-head">
                <div>
                    <h1><i class="ri-vip-crown-line" aria-hidden="true"></i> <span id="ow-greeting">Good day</span>, Owner {{ $ownerDashboardName }} ji</h1>
                    <p>Live executive snapshot &middot; &#8377;{{ $rupees($health['net']) }} net cash inflow this month
                        &middot; {{ number_format($health['active_bookings']) }} active bookings &middot;
                        {{ number_format($attentionCount) }} items need attention</p>
                </div>
                <div class="ow-head-controls">
                    <span
                        class="ow-live {{ $heartStale ? 'stale' : '' }}">{{ $heartStale ? 'Scheduler heartbeat stale' : 'Scheduler heartbeat current' }}</span>
                    <form method="GET" action="{{ route('admin.owner.dashboard') }}" class="ow-month">
                        <span>Month</span>
                        <input type="month" name="month" value="{{ $month->format('Y-m') }}" min="2000-01" max="2099-12">
                        <button type="submit" title="Apply month"><i class="ri-check-line" aria-hidden="true"></i></button>
                    </form>
                </div>
            </header>

            {{-- ================= KPI ROW ================= --}}
            <section class="ow-grid" aria-label="Business health">
                <div class="ow-card ow-kpi s3">
                    <div class="ow-kpi-top"><span class="ow-kpi-label">Net Profit / Loss</span><span
                            class="ow-kpi-ico green"><i class="ri-funds-line" aria-hidden="true"></i></span></div>
                    <div class="ow-kpi-mid">
                        <span class="ow-kpi-value" data-tnum
                            title="&#8377;{{ $rupees($health['profit_loss']) }}">&#8377;{!! $compact($health['profit_loss']) !!}</span>
                        <span
                            class="ow-spark">{!! $sparkline($salesChart['company'], $health['profit_loss_delta'] === null || $health['profit_loss_delta'] >= 0 ? '#178a4c' : '#e5484d') !!}</span>
                    </div>
                    <div>{!! $deltaPill($health['profit_loss_delta']) !!} <span class="ow-kpi-foot">earned revenue less vendor cost &middot; refunds applied &middot; vs previous month</span></div>
                </div>
                <div class="ow-card ow-kpi s3">
                    <div class="ow-kpi-top"><span class="ow-kpi-label">Collected</span><span class="ow-kpi-ico brand"><i
                                class="ri-bank-card-line" aria-hidden="true"></i></span></div>
                    <div class="ow-kpi-mid">
                        <span class="ow-kpi-value" data-tnum
                            title="&#8377;{{ $rupees($health['collected']) }}">&#8377;{!! $compact($health['collected']) !!}</span>
                        <span class="ow-spark">{!! $sparkline($salesChart['company'], '#2f6fed') !!}</span>
                    </div>
                    <div>{!! $deltaPill($health['collected_delta']) !!} <span class="ow-kpi-foot">approved payments</span>
                    </div>
                </div>
                <div class="ow-card ow-kpi s3">
                    <div class="ow-kpi-top"><span class="ow-kpi-label">Paid Out</span><span class="ow-kpi-ico red"><i
                                class="ri-money-rupee-circle-line" aria-hidden="true"></i></span></div>
                    <div class="ow-kpi-mid">
                        <span class="ow-kpi-value" data-tnum
                            title="&#8377;{{ $rupees($health['paid_out']) }}">&#8377;{!! $compact($health['paid_out']) !!}</span>
                        <span class="ow-spark">{!! $sparkline($salesChart['company'], '#e5484d') !!}</span>
                    </div>
                    <div>{!! $deltaPill($health['paid_out_delta']) !!} <span class="ow-kpi-foot">vendors + customer
                            refunds</span></div>
                </div>
                <div class="ow-card ow-kpi s3">
                    <div class="ow-kpi-top"><span class="ow-kpi-label">Active Bookings</span><span
                            class="ow-kpi-ico violet"><i class="ri-plane-line" aria-hidden="true"></i></span></div>
                    <div class="ow-kpi-mid">
                        <span class="ow-kpi-value" data-tnum>{{ number_format($health['active_bookings']) }}</span>
                        <span class="ow-spark">{!! $sparkline($salesChart['company'], '#7c3aed') !!}</span>
                    </div>
                    <div><span class="ow-delta flat"><i class="ri-check-line"></i>confirmed</span> <span
                            class="ow-kpi-foot">not cancelled</span></div>
                </div>
            </section>

            {{-- ================= NEEDS ATTENTION ================= --}}
            <h2 style="font-size:13px;font-weight:700;margin:24px 2px 12px;display:flex;align-items:center;gap:10px">
                Needs your attention
                <span
                    style="font-size:11px;font-weight:650;color:var(--ow-amber);background:var(--ow-amber-soft);border-radius:99px;padding:3px 10px">
                    {{ $attention['payments']['count'] + $attention['vendor_overdue']['count'] + $attention['customer_refunds']['count'] + $attention['vendor_refunds']['count'] }}
                    open items
                </span>
            </h2>
            <section class="ow-grid" aria-label="Needs attention">
                <a class="ow-card ow-att amber s3"
                    href="{{ route('admin.account.payment-review', ['payment_status' => 'pending']) }}">
                    <div class="ow-att-top">
                        <span class="ow-att-ico"><i class="ri-wallet-3-line" aria-hidden="true"></i></span>
                        <div>
                            <div class="ow-att-num" data-tnum>{{ number_format($attention['payments']['count']) }}</div>
                            <div class="ow-att-amt">&#8377;{{ $compact($attention['payments']['amount']) }} waiting</div>
                        </div>
                    </div>
                    <h3>Payments pending approval</h3>
                    <div class="ow-att-sub">
                        @if($attention['payments']['oldest_days'] !== null)<span class="ow-tag hot">oldest
                        {{ $attention['payments']['oldest_days'] }} days</span>@else<span class="ow-tag">none
                            waiting</span>@endif
                    </div>
                    <span class="ow-att-link">Review payments <i class="ri-arrow-right-line" aria-hidden="true"></i></span>
                </a>
                <a class="ow-card ow-att red s3" href="{{ route('admin.account.vendor-payments') }}">
                    <div class="ow-att-top">
                        <span class="ow-att-ico"><i class="ri-alert-line" aria-hidden="true"></i></span>
                        <div>
                            <div class="ow-att-num" data-tnum>{{ number_format($attention['vendor_overdue']['count']) }}
                            </div>
                            <div class="ow-att-amt">&#8377;{{ $compact($attention['vendor_overdue']['amount']) }} overdue
                            </div>
                        </div>
                    </div>
                    <h3>Vendor payments overdue</h3>
                    <div class="ow-att-sub"><span class="ow-tag hot">past service date</span></div>
                    <span class="ow-att-link">Vendor payments <i class="ri-arrow-right-line" aria-hidden="true"></i></span>
                </a>
                <a class="ow-card ow-att amber s3"
                    href="{{ route('admin.refunds.index', ['refund_status' => 'pending']) }}">
                    <div class="ow-att-top">
                        <span class="ow-att-ico"><i class="ri-refund-2-line" aria-hidden="true"></i></span>
                        <div>
                            <div class="ow-att-num" data-tnum>{{ number_format($attention['customer_refunds']['count']) }}
                            </div>
                            <div class="ow-att-amt">&#8377;{{ $compact($attention['customer_refunds']['amount']) }} pending
                            </div>
                        </div>
                    </div>
                    <h3>Customer refunds pending</h3>
                    <div class="ow-att-sub">
                        @if($attention['customer_refunds']['oldest_days'] !== null)<span class="ow-tag hot">oldest
                        {{ $attention['customer_refunds']['oldest_days'] }} days</span>@else<span class="ow-tag">none
                            waiting</span>@endif
                    </div>
                    <span class="ow-att-link">Review refunds <i class="ri-arrow-right-line" aria-hidden="true"></i></span>
                </a>
                <a class="ow-card ow-att amber s3" href="{{ route('admin.operations.vendor-refunds.index') }}">
                    <div class="ow-att-top">
                        <span class="ow-att-ico"><i class="ri-bank-line" aria-hidden="true"></i></span>
                        <div>
                            <div class="ow-att-num" data-tnum>{{ number_format($attention['vendor_refunds']['count']) }}
                            </div>
                            <div class="ow-att-amt">&#8377;{{ $compact($attention['vendor_refunds']['amount']) }} due</div>
                        </div>
                    </div>
                    <h3>Vendor refunds due</h3>
                    <div class="ow-att-sub"><span class="ow-tag">open cases</span></div>
                    <span class="ow-att-link">Vendor refunds <i class="ri-arrow-right-line" aria-hidden="true"></i></span>
                </a>
            </section>

            {{-- ================= SALES CHART + SOURCES ================= --}}
            <section class="ow-grid ow-row" aria-label="Sales and lead sources">
                <div class="ow-card s8">
                    <div class="ow-card-head">
                        <div>
                            <h2>Sales</h2>
                            <p>Booking value closed &middot; last 14 days</p>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                            <select id="ow-person-select" class="ow-person-select" style="display:none"
                                aria-label="Choose salesperson">
                                @foreach(collect($salesChart['people_options'])->groupBy('group') as $group => $people)
                                    <optgroup label="{{ $group }}">
                                        @foreach($people as $person)
                                            <option value="{{ $person['id'] }}">{{ $person['name'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <div class="ow-seg" role="group" aria-label="Sales scope">
                                <button type="button" class="ow-seg-btn active" data-scope="company"
                                    aria-pressed="true">Company</button>
                                <button type="button" class="ow-seg-btn" data-scope="managers"
                                    aria-pressed="false">Managers</button>
                                <button type="button" class="ow-seg-btn" data-scope="people"
                                    aria-pressed="false">People</button>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:baseline;gap:9px;margin:-4px 0 8px">
                        <strong id="ow-sales-total" style="font-size:21px;font-weight:700;letter-spacing:-.3px"
                            data-tnum></strong>
                        <span id="ow-sales-total-sub" style="font-size:11.5px;color:var(--ow-muted)"></span>
                    </div>
                    <div id="ow-sales-chart" class="ow-chart-box" aria-label="Daily sales chart"></div>
                </div>
                <div class="ow-card s4">
                    <div class="ow-card-head">
                        <div>
                            <h2>Lead sources</h2>
                            <p>Where enquiries come from</p>
                        </div>
                        <span class="ow-pill">{{ $month->format('M Y') }}</span>
                    </div>
                    <div class="ow-donut-wrap">
                        <div id="ow-source-chart" aria-label="Lead source donut chart"></div>
                        <div class="ow-donut-center">
                            <div><strong data-tnum>{{ number_format($sources['total']) }}</strong><span>leads this
                                    month</span></div>
                        </div>
                    </div>
                    <div class="ow-legend">
                        @foreach($sources['buckets'] as $bucket)
                            @if($sources['total'] > 0)
                                <div class="ow-legend-row"><i style="background:{{ $bucket['color'] }}"></i>{{ $bucket['label'] }}
                                    <span>&middot; {{ round($bucket['count'] / $sources['total'] * 100) }}%</span><b
                                        data-tnum>{{ number_format($bucket['count']) }}</b></div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ================= SALES PIPELINE + OPS ================= --}}
            <section class="ow-grid ow-row" aria-label="Sales pipeline and operations">
                <div class="ow-card s7">
                    <div class="ow-card-head">
                        <div>
                            <h2>Sales pipeline</h2>
                            <p>{{ $month->format('F Y') }} &middot; {{ number_format($funnel['new']) }} leads</p>
                        </div>
                        @if($funnel['new'] > 0)<span
                            class="ow-pill">{{ round($funnel['confirmed'] / $funnel['new'] * 100) }}%
                        conversion</span>@endif
                    </div>
                    <div class="ow-funnel">
                        @php $funnelRows = ['new' => 'New leads', 'active' => 'Active', 'confirmed' => 'Confirmed', 'paid' => 'Paid in full']; @endphp
                        @foreach($funnelRows as $key => $label)
                            @php $pct = $funnel['new'] > 0 ? round($funnel[$key] / $funnel['new'] * 100) : 0; @endphp
                            <div class="ow-funnel-row">
                                <span>{{ $label }}</span>
                                <div class="ow-funnel-track">
                                    <div class="ow-funnel-fill" style="width:{{ max(4, $pct) }}%">{{ $pct }}%</div>
                                </div>
                                <b data-tnum>{{ number_format($funnel[$key]) }}</b>
                            </div>
                        @endforeach
                    </div>
                    <div class="ow-team">
                        <div class="ow-team-head">Manager target attainment</div>
                        @foreach($attainment as $manager)
                            <div class="ow-team-row">
                                <span class="ow-avatar"
                                    style="background:{{ $attFill($manager['pct']) }}">{{ $manager['initials'] }}</span>
                                <div class="ow-team-meta">
                                    <div class="ow-team-name">{{ $manager['name'] }}<small>{{ $manager['role'] }}</small></div>
                                    <div class="ow-team-track"><span class="ow-team-fill"
                                            style="width:{{ min(100, $manager['pct'] ?? 0) }}%;background:{{ $attBar($manager['pct']) }}"></span>
                                    </div>
                                </div>
                                <span class="ow-team-pct"
                                    data-tnum>{{ $manager['pct'] === null ? '—' : $manager['pct'] . '%' }}</span>
                            </div>
                        @endforeach
                        @if(empty($attainment))
                        <p style="font-size:12px;color:var(--ow-muted)">No sales managers found.</p>@endif
                    </div>
                </div>

                <div class="ow-card s5">
                    <div class="ow-card-head">
                        <div>
                            <h2>Operations &amp; people</h2>
                            <p>Today &middot; {{ now()->format('d M') }}</p>
                        </div>
                    </div>
                    <div class="ow-stats">
                        <div class="ow-stat">
                            <h4>Rides today</h4><strong data-tnum>{{ number_format($operations['rides_today']) }}</strong>
                        </div>
                        <div class="ow-stat">
                            <h4>Rides next 48h</h4><strong data-tnum>{{ number_format($operations['rides_48h']) }}</strong>
                        </div>
                        <div class="ow-stat">
                            <h4>Open cases</h4><strong data-tnum>{{ number_format($operations['cases_total']) }}</strong>
                        </div>
                        <div class="ow-stat">
                            <h4>Daily updates</h4><strong
                                data-tnum>{{ $operations['updates_submitted'] }}<small>/{{ $operations['updates_expected'] }}</small></strong>
                        </div>
                    </div>
                    @if(!empty($operations['cases_by_type']))
                        <div style="margin-top:16px">
                            <h4
                                style="font-size:11px;font-weight:650;text-transform:uppercase;letter-spacing:.5px;color:var(--ow-faint)">
                                Open cases by type</h4>
                            <div class="ow-stagebar" role="img" aria-label="Open cases by type">
                                @foreach($operations['cases_by_type'] as $type => $count)
                                    <span
                                        style="width:{{ round($count / max(1, $operations['cases_total']) * 100, 1) }}%;background:{{ $casePalette[$loop->index % count($casePalette)] }}"></span>
                                @endforeach
                            </div>
                            <div class="ow-stages">
                                @foreach($operations['cases_by_type'] as $type => $count)
                                    <div><i
                                            style="background:{{ $casePalette[$loop->index % count($casePalette)] }}"></i>{{ ucfirst(str_replace('_', ' ', $type)) }}
                                        <b>{{ $count }}</b></div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    <h4
                        style="font-size:11px;font-weight:650;text-transform:uppercase;letter-spacing:.5px;color:var(--ow-faint);margin-top:16px">
                        Attendance today</h4>
                    <div class="ow-pills">
                        <div class="ow-pill-stat ok"><b
                                data-tnum>{{ $operations['attendance']['present'] }}</b><span>Present</span></div>
                        <div class="ow-pill-stat bad"><b
                                data-tnum>{{ $operations['attendance']['absent'] }}</b><span>Absent</span></div>
                        <div class="ow-pill-stat lv"><b
                                data-tnum>{{ $operations['attendance']['leave'] }}</b><span>Leave</span></div>
                    </div>
                    <div class="ow-kpirow">
                        @php $kpiPct = min(100, max(0, (int) $operations['kpi_avg'])); @endphp
                        <div class="ow-ring" role="img"
                            aria-label="Average team KPI score {{ $operations['kpi_avg'] }} out of 100"
                            style="background:conic-gradient(var(--ow-blue) 0 {{ $kpiPct }}%,var(--ow-slate-soft) {{ $kpiPct }}% 100%)">
                            <div data-tnum>{{ $operations['kpi_avg'] }}</div>
                        </div>
                        <div class="ow-ring-meta">
                            <h4>Average team KPI score</h4>
                            <p>Morning daily updates submitted by {{ $operations['updates_submitted'] }} of
                                {{ $operations['updates_expected'] }} salespeople</p>
                            @php $updPct = $operations['updates_expected'] > 0 ? round($operations['updates_submitted'] / $operations['updates_expected'] * 100) : 0; @endphp
                            <div class="ow-mini-track"><span class="ow-mini-fill" style="width:{{ $updPct }}%"></span></div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ================= AUTOMATION + QUICK LINKS ================= --}}
            <section class="ow-grid ow-row" aria-label="Automation health and quick links">
                <div class="ow-card s7">
                    <div class="ow-card-head">
                        <div>
                            <h2>Automation health</h2>
                            <p>The machines working in the background</p>
                        </div>
                        <span class="ow-pill"><span
                                style="color:{{ $automationHealth['tone'] === 'ok' ? 'var(--ow-green)' : 'var(--ow-amber)' }}">&#9679;</span>
                            {{ $automationHealth['label'] }}</span>
                    </div>
                    <div class="ow-auto">
                        @if($automation['whatsapp_ai'] !== null)
                            <div class="ow-auto-row">
                                <span class="ow-auto-ico"><i class="ri-whatsapp-line" aria-hidden="true"></i></span>
                                <div><strong>WhatsApp AI agents</strong>
                                    <p>Auto-replies processed today</p>
                                </div>
                                <div class="ow-auto-state"><b data-tnum>{{ number_format($automation['whatsapp_ai']) }}</b><span
                                        class="ow-status ok">Running</span></div>
                            </div>
                        @endif
                        @if($automation['call_summaries'] !== null)
                            <div class="ow-auto-row">
                                <span class="ow-auto-ico"><i class="ri-mic-line" aria-hidden="true"></i></span>
                                <div><strong>Call summaries</strong>
                                    <p>IVR calls waiting to be processed</p>
                                </div>
                                <div class="ow-auto-state"><b data-tnum>{{ number_format($automation['call_summaries']) }}
                                        queued</b><span
                                        class="ow-status {{ $automation['call_summaries'] > 20 ? 'warn' : 'ok' }}">{{ $automation['call_summaries'] > 20 ? 'Catching up' : 'Processing' }}</span>
                                </div>
                            </div>
                        @endif
                        @if($automation['email_leads_fetched'] !== null)
                            <div class="ow-auto-row">
                                <span class="ow-auto-ico"><i class="ri-mail-open-line" aria-hidden="true"></i></span>
                                <div><strong>Email lead fetch</strong>
                                    <p>Mailbox leads received today</p>
                                </div>
                                <div class="ow-auto-state"><b data-tnum>{{ number_format($automation['email_leads_fetched']) }}
                                        today</b><span class="ow-status ok">Running</span></div>
                            </div>
                        @endif
                        @if($automation['lead_ai_scoring'] !== null)
                            <div class="ow-auto-row">
                                <span class="ow-auto-ico"><i class="ri-brain-line" aria-hidden="true"></i></span>
                                <div><strong>Lead AI scoring</strong>
                                    <p>Priority scoring of new leads</p>
                                </div>
                                <div class="ow-auto-state"><b data-tnum>{{ number_format($automation['lead_ai_scoring']) }}
                                        pending</b><span
                                        class="ow-status {{ $automation['lead_ai_scoring'] > 10 ? 'warn' : 'ok' }}">{{ $automation['lead_ai_scoring'] > 10 ? 'Catching up' : 'Running' }}</span>
                                </div>
                            </div>
                        @endif
                        <div class="ow-auto-row">
                            <span class="ow-auto-ico"><i class="ri-database-2-line" aria-hidden="true"></i></span>
                            <div><strong>Failed jobs / scheduler</strong>
                                <p>vs warning threshold {{ $failedWarn }}</p>
                            </div>
                            <div class="ow-auto-state"><b data-tnum>{{ number_format($failedJobs) }} failed</b><span
                                    class="ow-status {{ $failedJobs > 0 ? 'warn' : 'ok' }}">{{ $failedJobs > $failedWarn ? 'Needs attention' : ($failedJobs > 0 ? 'Warning' : 'Healthy') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ow-card s5">
                    <div class="ow-card-head">
                        <div>
                            <h2>Jump back in</h2>
                            <p>Places you'll want to check</p>
                        </div>
                    </div>
                    <div class="ow-links">
                        <a class="ow-link-row"
                            href="{{ route('admin.account.payment-review', ['payment_status' => 'pending']) }}">
                            <span class="ow-link-ico" style="background:var(--ow-blue-soft);color:var(--ow-blue)"><i
                                    class="ri-wallet-3-line" aria-hidden="true"></i></span>
                            <div><strong>Payment Review</strong>
                                <p>Payments waiting for accounts</p>
                            </div>
                            @if($quickLinks['payment_review'] > 0)<span
                            class="ow-link-badge">{{ $quickLinks['payment_review'] }}</span>@else<i
                                class="ri-arrow-right-s-line chev" aria-hidden="true"></i>@endif
                        </a>
                        <a class="ow-link-row" href="{{ route('admin.refunds.index', ['refund_status' => 'pending']) }}">
                            <span class="ow-link-ico" style="background:var(--ow-red-soft);color:var(--ow-red)"><i
                                    class="ri-refund-2-line" aria-hidden="true"></i></span>
                            <div><strong>Customer Refunds</strong>
                                <p>Refunds pending action</p>
                            </div>
                            @if($quickLinks['customer_refunds'] > 0)<span
                            class="ow-link-badge">{{ $quickLinks['customer_refunds'] }}</span>@else<i
                                class="ri-arrow-right-s-line chev" aria-hidden="true"></i>@endif
                        </a>
                        <a class="ow-link-row" href="{{ route('admin.account.vendor-refunds') }}">
                            <span class="ow-link-ico" style="background:var(--ow-amber-soft);color:var(--ow-amber)"><i
                                    class="ri-bank-line" aria-hidden="true"></i></span>
                            <div><strong>Vendor Refunds</strong>
                                <p>Vendor refund cases</p>
                            </div>
                            @if($quickLinks['vendor_refunds'] > 0)<span
                            class="ow-link-badge">{{ $quickLinks['vendor_refunds'] }}</span>@else<i
                                class="ri-arrow-right-s-line chev" aria-hidden="true"></i>@endif
                        </a>
                        <a class="ow-link-row" href="{{ route('admin.report.profit-loss') }}">
                            <span class="ow-link-ico" style="background:var(--ow-green-soft);color:var(--ow-green)"><i
                                    class="ri-funds-line" aria-hidden="true"></i></span>
                            <div><strong>Profit / Loss</strong>
                                <p>{{ $month->format('F Y') }} P&amp;L report</p>
                            </div>
                            <i class="ri-arrow-right-s-line chev" aria-hidden="true"></i>
                        </a>
                        <a class="ow-link-row" href="{{ route('admin.report.sales') }}">
                            <span class="ow-link-ico" style="background:var(--ow-violet-soft);color:var(--ow-violet)"><i
                                    class="ri-file-chart-line" aria-hidden="true"></i></span>
                            <div><strong>Sales Report</strong>
                                <p>Team-wise numbers for {{ $month->format('F') }}</p>
                            </div>
                            <i class="ri-arrow-right-s-line chev" aria-hidden="true"></i>
                        </a>
                        <a class="ow-link-row" href="{{ route('admin.account.exceptional') }}">
                            <span class="ow-link-ico" style="background:var(--ow-slate-soft);color:var(--ow-brand)"><i
                                    class="ri-alert-line" aria-hidden="true"></i></span>
                            <div><strong>Exceptional Dashboard</strong>
                                <p>Customer overpayments &amp; adjustments</p>
                            </div>
                            <i class="ri-arrow-right-s-line chev" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>
            </section>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var h = new Date().getHours();
            var g = h < 12 ? 'Good morning' : (h < 17 ? 'Good afternoon' : 'Good evening');
            var el = document.getElementById('ow-greeting');
            if (el) el.textContent = g;

            var days = @json($salesChart['days']);
            var companySeries = @json([['name' => 'Company sales', 'data' => $salesChart['company']]]);
            var managerSeries = @json($salesChart['managers']);
            var peopleSeries = @json($salesChart['people']);
            var managerColors = ['#2f6fed', '#7c3aed', '#178a4c', '#d97706'];

            function rupeeL(v) { return '\u20B9' + (v / 100000).toFixed(1) + 'L'; }
            function totalOf(series) {
                return series.reduce(function (a, s) { return a + s.data.reduce(function (x, y) { return x + y; }, 0); }, 0);
            }
            function fmtTotal(series) { return rupeeL(totalOf(series)); }

            var chartEl = document.getElementById('ow-sales-chart');
            if (!chartEl || !window.ApexCharts) return;

            var salesChart = new ApexCharts(chartEl, {
                chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: '"Segoe UI", Arial, sans-serif', animations: { speed: 500 } },
                series: companySeries,
                xaxis: {
                    categories: days,
                    axisBorder: { show: false }, axisTicks: { show: false },
                    labels: { style: { colors: '#98a2b3', fontSize: '11px' }, rotate: 0, hideOverlappingLabels: true }
                },
                yaxis: { labels: { formatter: rupeeL, style: { colors: '#98a2b3', fontSize: '11px' } } },
                stroke: { curve: 'smooth', width: 2.5 },
                colors: ['#102e49'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.02, stops: [0, 100] } },
                grid: { borderColor: '#eef1f5', strokeDashArray: 4, padding: { left: 6, right: 6 } },
                dataLabels: { enabled: false },
                markers: { size: 0, hover: { size: 5 } },
                tooltip: {
                    theme: 'light', shared: true, intersect: false,
                    style: { fontSize: '12px' },
                    y: { formatter: function (v) { return '\u20B9' + Number(v).toLocaleString('en-IN'); } },
                    marker: { show: false }
                },
                legend: { show: false }
            });
            salesChart.render();

            var totalEl = document.getElementById('ow-sales-total');
            var totalSubEl = document.getElementById('ow-sales-total-sub');
            var personSelect = document.getElementById('ow-person-select');

            function setSalesScope(scope) {
                document.querySelectorAll('.ow-seg-btn').forEach(function (b) {
                    var on = b.getAttribute('data-scope') === scope;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-pressed', on ? 'true' : 'false');
                });
                personSelect.style.display = scope === 'people' ? '' : 'none';

                var series, colors, legend, fillOpacity, sub;
                if (scope === 'company') {
                    series = companySeries; colors = ['#102e49']; legend = false; fillOpacity = 0.3;
                    sub = 'total sales this fortnight \u00B7 all teams';
                } else if (scope === 'managers') {
                    series = managerSeries; colors = managerColors; legend = true; fillOpacity = 0.12;
                    sub = 'combined \u00B7 ' + managerSeries.length + ' sales managers';
                } else {
                    var p = peopleSeries[personSelect.value] || { name: 'Sales', data: [] };
                    series = [{ name: p.name + ' \u2014 sales', data: p.data }];
                    colors = ['#2f6fed']; legend = false; fillOpacity = 0.3;
                    sub = p.name;
                }
                salesChart.updateOptions({
                    colors: colors,
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: fillOpacity, opacityTo: 0.02, stops: [0, 100] } },
                    stroke: { curve: 'smooth', width: scope === 'managers' ? 2.2 : 2.5 },
                    legend: { show: legend, position: 'top', horizontalAlign: 'left', fontSize: '11px', fontWeight: 600, markers: { radius: 99 }, itemMargin: { horizontal: 14, vertical: 4 } }
                });
                salesChart.updateSeries(series);
                totalEl.textContent = fmtTotal(series);
                totalSubEl.textContent = sub;
            }

            document.querySelectorAll('.ow-seg-btn').forEach(function (btn) {
                btn.addEventListener('click', function () { setSalesScope(btn.getAttribute('data-scope')); });
            });
            personSelect.addEventListener('change', function () { setSalesScope('people'); });
            setSalesScope('company');

            @if($sources['total'] > 0)
                new ApexCharts(document.getElementById('ow-source-chart'), {
                    chart: { type: 'donut', height: 235, fontFamily: '"Segoe UI", Arial, sans-serif', animations: { speed: 700 } },
                    series: @json(collect($sources['buckets'])->pluck('count')->all()),
                    labels: @json(collect($sources['buckets'])->pluck('label')->all()),
                    colors: @json(collect($sources['buckets'])->pluck('color')->all()),
                    dataLabels: { enabled: false },
                    legend: { show: false },
                    stroke: { width: 3, colors: ['#ffffff'] },
                    plotOptions: { pie: { donut: { size: '74%', labels: { show: false } } } },
                    tooltip: { style: { fontSize: '12px' }, y: { formatter: function (v) { return v + ' leads'; } } },
                    states: { hover: { filter: { type: 'none' } } }
                }).render();
            @endif
        });
    </script>
@endsection
