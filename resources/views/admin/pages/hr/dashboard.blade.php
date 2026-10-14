@extends('admin.layouts.header')

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/dashboards/hr-dashboard.css') }}?v=5">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/responsive/hr-dashboard-mobile.css') }}?v=5">

    @php
        $reportPeriod = \Carbon\Carbon::createFromFormat('!Y-m', $month);
        $reportLabel = $filter['from']->isSameDay($filter['to'])
            ? $filter['from']->format('d M Y')
            : $filter['from']->format('d M Y') . ' - ' . $filter['to']->format('d M Y');
        $targetLabel = $targetAvailable ? $reportPeriod->format('F Y') . ' (full month)' : 'Select dates within one month';
        $targetPercentage = $targetAvailable && $salesTarget > 0 ? ($salesAchieved / $salesTarget) * 100 : null;
        $firstName = explode(' ', trim((string) optional(auth()->user())->name))[0];
        $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening');
        $kpiPeriod = [
            'preset' => 'custom',
            'from_date' => $filter['from']->toDateString(),
            'to_date' => $filter['to']->toDateString(),
        ];
        $summaryTiles = [
            ['value' => $attendanceSummary['recorded'] > 0 ? number_format($attendanceSummary['present']) . ' / ' . number_format($filter['from']->isSameDay($filter['to']) ? $attendanceSummary['staff'] : $attendanceSummary['recorded']) : 'N/A', 'label' => $filter['preset'] === 'today' ? 'Present today' : 'Attendance', 'badge' => $filter['from']->isSameDay($filter['to']) ? 'Present / active staff' : 'Present / recorded employee-days'],
            ['value' => number_format($todayFollowups), 'label' => $filter['preset'] === 'today' ? "Today's follow-ups" : 'Follow-ups due', 'badge' => $filter['from']->isSameDay($filter['to']) ? 'View follow-ups' : $reportLabel, 'url' => $filter['from']->isSameDay($filter['to']) ? route('admin.upcoming-follow-up.index', $kpiPeriod) : null],
            ['value' => number_format($missedFollowups), 'label' => 'Missed follow-ups', 'badge' => 'Outstanding'],
            ['value' => number_format($monthLeads), 'label' => 'Leads created', 'badge' => $reportLabel],
            ['value' => number_format($wantService), 'label' => 'Want service', 'badge' => $reportLabel],
            ['value' => $targetPercentage !== null ? number_format($targetPercentage, 1) : 'N/A', 'suffix' => $targetPercentage !== null ? '%' : '', 'label' => 'Monthly target completed', 'badge' => $targetLabel],
        ];
    @endphp

    <div class="crm-hr-dashboard">
        <header class="hr-heading" id="hr-dashboard-top">
            <div>
                <p class="hr-eyebrow">HR Dashboard</p>
                <h1>{{ $greeting }}{{ $firstName !== '' ? ', ' . $firstName : '' }}</h1>
                <p class="hr-subtitle">Employee, attendance and performance overview</p>
            </div>
            <form method="GET" action="{{ route('admin.hr.dashboard') }}" class="hr-month-form">
                <label for="hr-dashboard-preset">Period</label>
                <select id="hr-dashboard-preset" name="preset">
                    @foreach(['today' => 'Today', 'yesterday' => 'Yesterday', 'custom' => 'Custom'] as $value => $label)
                        <option value="{{ $value }}" @if($filter['preset'] === $value) selected @endif>{{ $label }}</option>
                    @endforeach
                </select>
                <div class="hr-custom-dates" @if($filter['preset'] !== 'custom') hidden @endif>
                    <label for="hr-from-date">From</label>
                    <input id="hr-from-date" type="date" name="from_date" value="{{ $filter['from']->toDateString() }}" required>
                    <label for="hr-to-date">To</label>
                    <input id="hr-to-date" type="date" name="to_date" value="{{ $filter['to']->toDateString() }}" required>
                </div>
                <button type="submit" class="hr-apply">Apply</button>
            </form>
        </header>

        @if($errors->any())
            <div role="alert" class="hr-tag hr-tag-red">{{ $errors->first() }}</div>
        @endif
        <div class="hr-stats" aria-label="HR summary">
            @foreach($summaryTiles as $tile)
                <article class="hr-stat">
                    <strong>{{ $tile['value'] }}@if(!empty($tile['suffix']))<small>{{ $tile['suffix'] }}</small>@endif</strong>
                    <h2>{{ $tile['label'] }}</h2>
                    @if(isset($tile['url']))
                        <a class="hr-tag hr-tag-blue" href="{{ $tile['url'] }}">{{ $tile['badge'] }}</a>
                    @else
                        <span class="hr-tag hr-tag-neutral">{{ $tile['badge'] }}</span>
                    @endif
                </article>
            @endforeach
        </div>
        @if($attendanceSummary['missing'] > 0)
            <p class="hr-section-meta">{{ number_format($attendanceSummary['missing']) }} employee-days have no imported attendance record.</p>
        @endif

        <div class="hr-columns">
            <section class="hr-section" aria-labelledby="hr-kpi-heading">
                <div class="hr-section-heading">
                    <h2 id="hr-kpi-heading">KPI Report</h2>
                    <span class="hr-meta">{{ $reportLabel }}</span>
                </div>
                <div class="hr-tabs" role="tablist" aria-label="KPI department">
                    <button type="button" class="hr-tab" id="hr-sales-tab" role="tab" aria-selected="true" aria-controls="hr-sales-panel" data-hr-tab="sales">Sales</button>
                    <button type="button" class="hr-tab" id="hr-operations-tab" role="tab" aria-selected="false" aria-controls="hr-operations-panel" tabindex="-1" data-hr-tab="operations">Operations</button>
                </div>
                @foreach(['sales' => 'Sales', 'operations' => 'Operations'] as $department => $departmentLabel)
                    <div id="hr-{{ $department }}-panel" role="tabpanel" aria-labelledby="hr-{{ $department }}-tab" @if($department === 'operations') hidden @endif>
                        <div class="hr-table-wrap" tabindex="0" role="region" aria-label="{{ $departmentLabel }} KPI report">
                            <table class="hr-table">
                                <thead><tr><th scope="col">Person</th><th scope="col">Overall KPI</th><th scope="col">Weakest metric</th><th scope="col">Status</th></tr></thead>
                                <tbody>
                                    @forelse($departmentRows[$department] as $row)
                                        <tr class="hr-person-row" data-person-url="{{ route('admin.kpi.index', array_merge($kpiPeriod, ['department' => $department, 'user_id' => $row['user']->id])) }}" tabindex="0">
                                            <td><a class="hr-person-link" href="{{ route('admin.kpi.index', array_merge($kpiPeriod, ['department' => $department, 'user_id' => $row['user']->id])) }}" target="_blank" rel="noopener">{{ $row['user']->name }}</a></td>
                                            <td><span class="hr-tag hr-tag-{{ $row['tone'] }}">{{ $row['score'] !== null ? $row['score'] . '/5' : 'N/A' }}</span></td>
                                            <td>{{ $row['weakest'] }}</td>
                                            <td><span class="hr-tag hr-tag-{{ $row['tone'] }}">{{ $row['status'] }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="hr-empty">No active {{ strtolower($departmentLabel) }} employees</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        @include('admin.pages.hr.partials.pagination', ['pager' => $departmentRows[$department]])
                        <div class="hr-section-footer">
                            <a class="hr-text-link" href="{{ route('admin.kpi.index', array_merge($kpiPeriod, ['department' => $department])) }}" target="_blank" rel="noopener">View {{ $departmentLabel }} KPI <i class="ri-external-link-line" aria-hidden="true"></i></a>
                        </div>
                    </div>
                @endforeach
            </section>
            <section class="hr-section" aria-labelledby="hr-incentives-heading">
                <div class="hr-section-heading">
                    <h2 id="hr-incentives-heading">Accounts &amp; Incentives</h2>
                    <span class="hr-meta">{{ $reportLabel }}</span>
                </div>
                <div class="hr-account-row">
                    <div><h3>Incentive eligible this month</h3><p>Target &ge; 100% &middot; Attendance &ge; 95% &middot; KPI &ge; 3/5</p></div>
                    <span class="hr-tag hr-tag-neutral">Not configured</span>
                </div>
                <div class="hr-account-row">
                    <div><h3>Attendance imports confirmed</h3><p>{{ $reportLabel }}</p></div>
                    <span class="hr-tag hr-tag-neutral">{{ number_format($attendanceImports) }}</span>
                </div>
                <div class="hr-account-row">
                    <div><h3>Missing manual operations KPI entries</h3><p>{{ $targetLabel }}</p></div>
                    <span class="hr-tag hr-tag-neutral">{{ $pendingManualKpis !== null ? number_format($pendingManualKpis) : 'N/A' }}</span>
                </div>
                <div class="hr-target-summary">
                    <div><span>Monthly target</span><strong>{{ $targetAvailable ? 'Rs. ' . number_format($salesTarget, 2) : 'N/A' }}</strong></div>
                    <div><span>Monthly achieved</span><strong>{{ $targetAvailable ? 'Rs. ' . number_format($salesAchieved, 2) : 'N/A' }}</strong></div>
                </div>
            </section>
        </div>

        <div class="hr-columns hr-work-columns">
            @foreach(['sales' => 'Sales', 'operations' => 'Operations'] as $department => $departmentLabel)
                <section class="hr-section" id="hr-{{ $department }}-work" aria-labelledby="hr-{{ $department }}-work-heading">
                    <div class="hr-section-heading">
                        <h2 id="hr-{{ $department }}-work-heading">{{ $filter['preset'] === 'today' ? 'Work Done Today' : 'Work Done' }} &middot; {{ $departmentLabel }}</h2>
                        <span class="hr-tag hr-tag-neutral">All employees</span>
                    </div>
                    <p class="hr-section-meta">{{ $reportLabel }}</p>
                    <div class="hr-table-wrap" tabindex="0" role="region" aria-label="{{ $departmentLabel }} work done today">
                        <table class="hr-table hr-work-table {{ $department === 'operations' ? 'hr-operations-table' : '' }}">
                            <thead><tr>
                                <th scope="col">Person</th>
                                @if($department === 'sales')
                                    <th scope="col" class="hr-num">Completed lead work</th>
                                    <th scope="col" class="hr-num">Active</th>
                                    <th scope="col" class="hr-num">Outreach done</th>
                                @endif
                                @if($department === 'operations')
                                    <th scope="col" class="hr-num">Pending tasks</th>
                                    <th scope="col" class="hr-num">Review</th>
                                    <th scope="col" class="hr-num">Vendor follow-up</th>
                                @endif
                                <th scope="col">Attendance</th>
                            </tr></thead>
                            <tbody>
                                @forelse($departmentRows[$department] as $row)
                                    <tr>
                                        <td>{{ $row['user']->name }}</td>
                                        @if($department === 'sales')
                                            @foreach(['completed', 'active', 'outreach'] as $metric)
                                                <td class="hr-num">{{ $row['work'][$metric] ?? 'N/A' }}</td>
                                            @endforeach
                                        @endif
                                        @if($department === 'operations')
                                            <td class="hr-num">{{ $row['work']['pending'] }}@if($row['work']['overdue'] > 0)<br><span class="hr-tag hr-tag-red">{{ $row['work']['overdue'] }} overdue</span>@endif</td>
                                            <td class="hr-num">{{ $row['work']['review'] }}</td>
                                            <td class="hr-num">{{ $row['work']['vendor'] }}</td>
                                        @endif
                                        <td><span class="hr-tag hr-tag-{{ $row['attendance']['tone'] }}">{{ $row['attendance']['label'] }}</span></td>
                                    </tr>
                                    @if($row['work']['notice'])
                                        <tr><td colspan="5" class="hr-meta">{{ $row['work']['notice'] }}</td></tr>
                                    @endif
                                @empty
                                    <tr><td colspan="5" class="hr-empty">No active {{ strtolower($departmentLabel) }} employees</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @include('admin.pages.hr.partials.pagination', ['pager' => $departmentRows[$department]])
                </section>
            @endforeach
        </div>

        <nav class="hr-access" aria-label="HR tools">
            <a href="{{ route('admin.attendance.import.index') }}"><i class="ri-upload-2-line" aria-hidden="true"></i> Attendance Import</a>
            <a href="{{ route('admin.attendance.settings.index') }}"><i class="ri-settings-3-line" aria-hidden="true"></i> Attendance Settings</a>
        </nav>

        <nav class="hr-mobile-more" id="hr-mobile-more" aria-label="More HR tools" hidden>
            <a href="{{ route('admin.attendance.import.index') }}"><i class="ri-upload-2-line" aria-hidden="true"></i> Attendance Import</a>
            <a href="{{ route('admin.attendance.settings.index') }}"><i class="ri-settings-3-line" aria-hidden="true"></i> Attendance Settings</a>
            <a href="{{ route('admin.kpi.index', $kpiPeriod) }}"><i class="ri-bar-chart-line" aria-hidden="true"></i> KPI Dashboard</a>
        </nav>
        <nav class="hr-mobile-nav" aria-label="HR mobile navigation">
            <a href="#hr-dashboard-top" aria-current="location"><i class="ri-home-4-line" aria-hidden="true"></i><span>Dashboard</span></a>
            <a href="{{ route('admin.clients.index') }}"><i class="ri-group-line" aria-hidden="true"></i><span>Leads</span></a>
            <a href="#hr-sales-work"><i class="ri-checkbox-circle-line" aria-hidden="true"></i><span>Work Done Sales</span></a>
            <a href="#hr-operations-work"><i class="ri-briefcase-line" aria-hidden="true"></i><span>Operations</span></a>
            <button type="button" id="hr-more-toggle" aria-expanded="false" aria-controls="hr-mobile-more"><i class="ri-apps-line" aria-hidden="true"></i><span>More</span></button>
        </nav>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const dashboard = document.querySelector('.crm-hr-dashboard');
            if (!dashboard) return;
            document.body.classList.add('crm-hr-page');
            const preset = dashboard.querySelector('#hr-dashboard-preset');
            const customDates = dashboard.querySelector('.hr-custom-dates');
            const syncDates = () => {
                customDates.hidden = preset.value !== 'custom';
                customDates.querySelectorAll('input').forEach(input => input.disabled = customDates.hidden);
            };
            preset.addEventListener('change', syncDates);
            syncDates();
            dashboard.querySelectorAll('[data-person-url]').forEach(row => {
                row.addEventListener('click', event => {
                    if (!event.target.closest('a')) window.open(row.dataset.personUrl, '_blank', 'noopener');
                });
                row.addEventListener('keydown', event => {
                    if (event.target === row && ['Enter', ' '].includes(event.key)) {
                        event.preventDefault();
                        window.open(row.dataset.personUrl, '_blank', 'noopener');
                    }
                });
            });
            const tabs = Array.from(dashboard.querySelectorAll('[data-hr-tab]'));
            function selectTab(selected) {
                tabs.forEach(tab => {
                    const active = tab === selected;
                    tab.setAttribute('aria-selected', String(active));
                    tab.tabIndex = active ? 0 : -1;
                    document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
                });
            }
            tabs.forEach((tab, index) => {
                tab.addEventListener('click', () => selectTab(tab));
                tab.addEventListener('keydown', event => {
                    let next;
                    if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
                    if (event.key === 'ArrowLeft') next = tabs[(index - 1 + tabs.length) % tabs.length];
                    if (event.key === 'Home') next = tabs[0];
                    if (event.key === 'End') next = tabs[tabs.length - 1];
                    if (!next) return;
                    event.preventDefault();
                    selectTab(next);
                    next.focus();
                });
            });

            const moreToggle = dashboard.querySelector('#hr-more-toggle');
            const morePanel = dashboard.querySelector('#hr-mobile-more');
            const mobileNav = dashboard.querySelector('.hr-mobile-nav');
            const sidebarMenu = document.querySelector('.app-sidebar .main-menu');
            const bottomRoutes = new Set([
                @json(route('admin.hr.dashboard')),
                @json(route('admin.clients.index')),
                @json(route('admin.kpi.work-done.index')),
                @json(route('admin.operations.index')),
            ].map(href => new URL(href, location.href).pathname.replace(/\/$/, '')));

            // Read the server-rendered sidebar so More inherits the current user's menu permissions.
            function sidebarItem(item) {
                const anchor = Array.from(item.children).find(child => child.tagName === 'A');
                if (!anchor) return null;
                const label = (anchor.querySelector('.side-menu__label') || anchor).textContent.trim();
                const submenu = Array.from(item.children).find(child => child.tagName === 'UL');
                if (submenu) {
                    const children = Array.from(submenu.children).map(sidebarItem).filter(Boolean);
                    if (!children.length) return null;
                    const group = document.createElement('details');
                    const summary = document.createElement('summary');
                    summary.textContent = label;
                    group.append(summary);
                    children.forEach(child => group.append(child));
                    return group;
                }
                const href = anchor.getAttribute('href');
                if (!href) return null;
                const url = new URL(href, location.href);
                if (!['http:', 'https:'].includes(url.protocol)) return null;
                if (url.origin === location.origin && bottomRoutes.has(url.pathname.replace(/\/$/, ''))) return null;
                const link = document.createElement('a');
                link.href = url.href;
                if (anchor.target === '_blank') {
                    link.target = '_blank';
                    link.rel = 'noopener';
                }
                const sidebarIcon = anchor.querySelector('.side-menu__icon');
                if (sidebarIcon) {
                    const icon = document.createElement('i');
                    icon.className = sidebarIcon.className;
                    icon.setAttribute('aria-hidden', 'true');
                    link.append(icon);
                }
                link.append(document.createTextNode(label));
                return link;
            }
            if (sidebarMenu) {
                const links = Array.from(sidebarMenu.children).map(sidebarItem).filter(Boolean);
                morePanel.replaceChildren(...links);
            }
            function closeMore(returnFocus = false) {
                morePanel.hidden = true;
                moreToggle.setAttribute('aria-expanded', 'false');
                if (returnFocus) moreToggle.focus();
            }
            moreToggle.addEventListener('click', () => {
                const open = morePanel.hidden;
                morePanel.hidden = !open;
                moreToggle.setAttribute('aria-expanded', String(open));
                if (open) morePanel.querySelector('summary, a')?.focus();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape' && !morePanel.hidden) closeMore(true);
            });
            document.addEventListener('click', event => {
                if (!morePanel.contains(event.target) && !moreToggle.contains(event.target)) closeMore();
            });
            mobileNav.querySelectorAll('a[href^="#"]').forEach(link => {
                link.addEventListener('click', () => {
                    mobileNav.querySelectorAll('a').forEach(item => item.removeAttribute('aria-current'));
                    link.setAttribute('aria-current', 'location');
                    closeMore();
                });
            });
            window.matchMedia('(max-width: 767px)').addEventListener('change', () => closeMore());
        })();
    </script>
@endpush
