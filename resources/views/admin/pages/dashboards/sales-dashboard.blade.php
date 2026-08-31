@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">

</div>
<div class="grid grid-cols-12">
    <div class="xl:col-span-12  col-span-12">
        <div class="box">
            <div class="hs-accordion-group">
                <div class="hs-accordion" id="ride-status-accordion">
                    <div class="box-header" style="display: block; padding-top: 10px; padding-bottom: 10px;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-sm !rounded-full bg-theme m-0">
                                    <i class="bx bx-bar-chart"></i>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Sales Dashboard</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
{{-- ========================================================== --}}
{{-- MORNING LEAD AVAILABILITY POPUP --}}
{{-- Uses the single shared #assign-more-leads-modal from header.blade.php --}}
{{-- The controller decides whether it should appear today. --}}
{{-- ========================================================== --}}

@if(($popupData['show_popup'] ?? false))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        setTimeout(function () {
            var modalSelector = '#assign-more-leads-modal';

            /*
             * Preferred approach:
             * trigger the same HS Overlay control used by the sidebar.
             * This avoids creating a second modal/backdrop.
             */
            var trigger = document.querySelector(
                '[data-hs-overlay="' + modalSelector + '"]'
            );

            if (trigger) {
                trigger.click();
                return;
            }

            /*
             * Fallback in case the sidebar trigger is not present.
             */
            try {
                if (
                    window.HSOverlay &&
                    typeof window.HSOverlay.open === 'function'
                ) {
                    window.HSOverlay.open(modalSelector);
                    return;
                }
            } catch (e) {
                console.error(
                    '[sales-dashboard] HSOverlay.open failed',
                    e
                );
            }

            /*
             * Final fallback: expose the existing shared modal only.
             * No new modal/backdrop is created.
             */
            var modal = document.querySelector(modalSelector);

            if (!modal) {
                console.error(
                    '[sales-dashboard] Shared Assign More Leads modal not found'
                );
                return;
            }

            modal.classList.remove('hidden');
            modal.classList.add('open');
            modal.style.display = 'block';
            modal.setAttribute('aria-hidden', 'false');
        }, 300);
    });
</script>
@endif

@if(Auth::user()->userType && in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_MANAGER, \App\Models\UserType::SENIOR_SALES_MANAGER]))
<div class="mb-6 rounded-lg border border-defaultborder bg-white p-4 shadow-sm">
    <div class="mb-3 flex items-center justify-between">
        <div>
            <h6 class="text-[1rem] font-semibold text-gray-800">Today's updates from your team</h6>
            <p class="mb-0 text-sm text-gray-600">View the work updates submitted by your sales executives.</p>
        </div>
    </div>
    @if($managerUpdates->isEmpty())
        <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600">No updates submitted today yet.</div>
    @else
        <div class="space-y-3">
            @foreach($managerUpdates as $update)
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h6 class="font-semibold text-gray-800">{{ $update->user->name ?? 'Sales Executive' }}</h6>
                            <p class="mb-0 text-sm text-gray-600">Work hours: {{ number_format((float) $update->work_hours, 2) }}</p>
                        </div>
                        <div class="text-sm text-gray-500">{{ $update->created_at->format('h:i A') }}</div>
                    </div>
                    <div class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $update->task_summary }}</div>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endif

@include('admin.pages.dashboards.partials.sales-presence', [
    'salesPresenceRows' => $salesPresenceRows ?? collect(),
    'canViewAllSalesPresence' => $canViewAllSalesPresence ?? false,
])

<div class="grid grid-cols-12 gap-x-6">
    <div class="xxl:col-span-9 col-span-12">
        <div class="grid grid-cols-12 gap-x-6">
            <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-12 col-span-12">
                <div class="box">
                    <div class="box-body">
                        <a href="{{ route('admin.clients.index') }}">
                            <div class="flex items-start justify-between pb-5">
                                <div class="flex-grow">
                                    <span class="font-semibold text-[#8c9097] dark:text-white/50 block mb-1">Total
                                        Leads</span>
                                    <h5 id="total_leads_count" class="font-semibold mb-1 text-[1.25rem]">{{ $leads['leadsCount'] }}</h5>
                                </div>
                                <div class="flex">
                                    <span
                                        class="avatar avatar-lg bg-primary/10 !text-primary inline-flex items-center justify-center">
                                        <i class="bx bx-trending-up text-[1.5rem]"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="text-[0.75rem] mb-0 text-primary">
                                <div class="flex items-start justify-between">
                                    <div id="total_leads_percentage" class="text-warning text-[0.85rem]">
                                        <i
                                            class="bx {{ $leads['percentageChange'] > 0 ? 'bx-trending-up' : 'bx-trending-down' }} text-[1rem]"></i>
                                        {{ $leads['percentageChange'] > 0 ? '+' . $leads['percentageChange'] . '%' :
                                        $leads['percentageChange'] . '%' }}
                                        vs last month
                                    </div>

                                    <div>
                                        <i class="ti ti-chevron-right text-[1rem]"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-12 col-span-12">
                <div class="box">
                    <div class="box-body">
                        <a href="{{ route('admin.upcoming-follow-up.index') }}">
                            <div class="flex items-start justify-between pb-5">
                                <div class="flex-grow">
                                    <span class="font-semibold text-[#8c9097] dark:text-white/50 block mb-1">Today's
                                        Follow-up</span>
                                    <h5 id="today_followups_count" class="font-semibold mb-1 text-[1.25rem]">{{ $todayFollowUpsCount ?? count($todayFollowUps) }}</h5>
                                </div>
                                <div class="flex">
                                    <span
                                        class="avatar avatar-lg bg-primary/10 !text-primary inline-flex items-center justify-center">
                                        <i class="ri-calendar-2-line text-[1.5rem]"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="text-[0.75rem] mb-0 text-primary">
                                <div class="flex items-start justify-between">
                                    <div class="text-primary text-[0.85rem]">
                                        This month
                                    </div>
                                    <div>
                                        <i class="ti ti-chevron-right text-[1rem]"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            <div class="xxl:col-span-4 xl:col-span-4 lg:col-span-12 col-span-12">
                <div class="box">
                    <div class="box-body">
                        <a href="{{ route('admin.leads.dnp') }}">
                            <div class="flex items-start justify-between pb-5">
                                <div class="flex-grow">
                                    <span class="font-semibold text-[#8c9097] dark:text-white/50 block mb-1">DNP
                                        Report</span>
                                    <h5 id="dnp_report_count" class="font-semibold mb-1 text-[1.25rem]">{{ count($dnpLeads) }}</h5>
                                </div>
                                <div class="flex">
                                    <span
                                        class="avatar avatar-lg bg-primary/10 !text-primary inline-flex items-center justify-center">
                                        <i class="ri-phone-line text-[1.5rem]"></i>
                                    </span>
                                </div>
                            </div>
                            <div class="text-[0.75rem] mb-0 text-primary">
                                <div class="flex items-start justify-between">
                                    <div class="text-danger text-[0.85rem]">
                                        Calls not connected
                                    </div>
                                    <div>
                                        <i class="ti ti-chevron-right text-[1rem]"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Target Progress Section for Sales Executives -->
    @if(Auth::user()->userType->user_type === \App\Models\UserType::SALES_EXECUTIVE)
    @if(isset($currentMonthTarget))
    @php
        $executiveInitialProgress = $targetProgress['daily_progress'] ?? $targetProgress;
        $executiveInitialPercent = min(100, max(0, (float) ($executiveInitialProgress['achievement_percentage'] ?? 0)));
    @endphp
    <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header justify-between flex flex-wrap gap-3">
                <div class="flex-grow">
                    <div class="flex items-center">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                <i class="bx bx-trending-up text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Total Completed Sales vs Target
                        </h5>
                    </div>
                </div>
                <div class="text-sm text-[#8c9097] dark:text-white/50">
                    Target Month:
                    <span id="executive_target_month" class="font-semibold text-gray-800 dark:text-white">
                        {{ $currentMonthTarget->month_name }} {{ $currentMonthTarget->year }}
                    </span>
                </div>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-x-6">
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="mb-4 inline-flex overflow-hidden rounded-md border border-defaultborder target-mode-tabs"
                            data-target-prefix="executive">
                            <button type="button"
                                class="target-mode-tab active px-3 py-1.5 text-sm font-semibold bg-primary text-white"
                                data-target-prefix="executive" data-target-mode="daily">Daily Target</button>
                            <button type="button"
                                class="target-mode-tab px-3 py-1.5 text-sm font-semibold text-[#8c9097]"
                                data-target-prefix="executive" data-target-mode="monthly">Monthly Target</button>
                        </div>
                        <div class="mb-[2rem]">
                            <div class="mb-2 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Progress</h3>
                                <span id="executive_progress_percent" class="text-sm text-gray-800 dark:text-white">{{
                                    $executiveInitialProgress['achievement_percentage'] ?? 0 }}%</span>
                            </div>
                            <div class="progress progress-xl !rounded-full" role="progressbar"
                                aria-valuenow="{{ $executiveInitialPercent }}" aria-valuemin="0"
                                aria-valuemax="100">
                                <div id="executive_progress_bar" class="progress-bar bg-primary !rounded-full"
                                    data-percent="{{ $executiveInitialPercent }}"
                                    style="width: {{ $executiveInitialPercent }}%"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 justify-center">
                            <div class="xl:col-span-12 col-span-12">
                                <div class="">
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="executive_achieved_label">Achieved</span> :<span id="executive_achieved"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($executiveInitialProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Sales Amount :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($executiveInitialProgress['sales_amount'] ??
                                            $executiveInitialProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="executive_target_label">Target</span> :<span id="executive_target"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold">₹{{
                                            number_format($executiveInitialProgress['target_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="executive_remaining_label">Remaining</span> :<span id="executive_remaining"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-warning">₹{{
                                            number_format($executiveInitialProgress['remaining_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Required Daily Target :<span id="executive_required_daily"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-primary">â‚¹{{
                                            number_format($targetProgress['required_daily_target'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Current Run Rate :<span id="executive_run_rate"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">â‚¹{{
                                            number_format($targetProgress['current_run_rate'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Projected Month End :<span id="executive_projected"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">â‚¹{{
                                            number_format($targetProgress['projected_month_end_sales'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Gap vs Target :<span id="executive_gap"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-warning">&#8377;{{
                                            number_format($targetProgress['gap_vs_target'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Working Days Left :<span id="executive_remaining_days"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">{{
                                            $targetProgress['remaining_days'] ?? 0 }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-0 text-[#8c9097] dark:text-white/50">
                                        Status :<span id="executive_target_status"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-primary">{{
                                            $targetProgress['target_status'] ?? 'Active' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-6">
                                <div
                                    class="box !shadow-none border border-defaultborder/10 dark:border-defaultborder/10">
                                    <div class="box-body text-center">
                                        <h6 class="text-[0.85rem] font-semibold mb-2">Status</h6>
                                        <span
                                            class="badge {{ $currentMonthTarget->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{
                                            ucfirst($currentMonthTarget->status) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-span-6">
                                <div
                                    class="box !shadow-none border border-defaultborder/10 dark:border-defaultborder/10">
                                    <div class="box-body text-center">
                                        <h6 class="text-[0.85rem] font-semibold mb-2">Assigned By</h6>
                                        <p class="text-[0.75rem] text-[#8c9097] dark:text-white/50 mb-0">{{
                                            $currentMonthTarget->assignedBy->name ?? 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            @if($currentMonthTarget->description)
                            <div class="col-span-12">
                                <div
                                    class="box !shadow-none border border-defaultborder/10 dark:border-defaultborder/10">
                                    <div class="box-body">
                                        <h6 class="text-[0.85rem] font-semibold mb-2">Description</h6>
                                        <p class="text-[0.75rem] text-[#8c9097] dark:text-white/50 mb-0">{{
                                            $currentMonthTarget->description }}</p>
                                    </div>
                                </div>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @else
    <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <div class="flex items-center">
                    <div class="me-4 gap-0">
                        <span class="avatar avatar-md p-2 !rounded-md bg-warning/10 m-0">
                            <i class="ri-target-line text-[1.5rem] text-warning"></i>
                        </span>
                    </div>
                    <div class="flex-grow">
                        <div class="flex items-center justify-between">
                            <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Monthly Target
                            </h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="box-body text-center">
                <div class="mb-4">
                    <i class="ri-target-line text-[3rem] text-warning"></i>
                </div>
                <h6 class="font-semibold mb-2">No Target Assigned</h6>
                <p class="text-[#8c9097] dark:text-white/50 mb-0">
                    No target has been assigned for {{ date('F Y') }}. Please contact your manager for target
                    assignment.
                </p>
            </div>
        </div>
    </div>
    @endif
    @endif

    {{-- Target Progress Section for Sales Managers (team view + filter) --}}
    @if(in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_MANAGER,
    \App\Models\UserType::SENIOR_SALES_MANAGER, \App\Models\UserType::ADMIN, \App\Models\UserType::SUPER_ADMIN]))
    @php
        $managerInitialProgress = $teamTargetProgress['daily_progress'] ?? ($teamTargetProgress ?? []);
        $managerInitialPercent = min(100, max(0, (float) ($managerInitialProgress['achievement_percentage'] ?? 0)));
    @endphp
    <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header justify-between flex flex-wrap gap-3">
                <div class="flex-grow">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                <i class="bx bx-trending-up text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Total Completed Sales vs Target</h5>
                        <span class="text-sm text-[#8c9097] dark:text-white/50">
                            Target Month:
                            <span id="manager_target_month" class="font-semibold text-gray-800 dark:text-white">{{
                                $teamTargetProgress['month_name'] ?? date('F') }} {{ $teamTargetProgress['year'] ?? date('Y') }}</span>
                        </span>
                    </div>
                </div>
                <div class="ms-4">
                    <div class="relative inline-block">
                        <select id="dashboard_common_select"
                            class="appearance-none form-control-sm py-2 pr-8 pl-2 border rounded-md text-sm">
                            <option value="">Team (All)</option>
                            @if(in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_MANAGER,
                            \App\Models\UserType::SENIOR_SALES_MANAGER]))
                            <option value="{{ Auth::id() }}">{{ Auth::user()->name }} (You)</option>
                            @endif
                            @foreach(($assignedExecutivesAll ?? $assignedExecutives ?? collect()) as $exec)
                            @php $typeLabel = $exec->userType->user_type ?? ''; @endphp
                            <option value="{{ $exec->id }}">{{ $exec->name }} @if($typeLabel) ({{ $typeLabel }}) @endif
                            </option>
                            @endforeach
                        </select>
                        <span id="dashboard_loading_spinner" class="hidden ms-2 text-sm text-muted" aria-hidden="true"
                            style="display:none;">
                            <svg class="inline-block" width="16" height="16" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="12" cy="12" r="10" stroke="#6b7280" stroke-opacity="0.25" stroke-width="4">
                                </circle>
                                <path d="M22 12a10 10 0 0 0-10-10" stroke="#3b82f6" stroke-width="4"
                                    stroke-linecap="round">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12"
                                        to="360 12 12" dur="0.9s" repeatCount="indefinite" />
                                </path>
                            </svg>
                            <span class="sr-only">Loading</span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-x-6">
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="mb-4 inline-flex overflow-hidden rounded-md border border-defaultborder target-mode-tabs"
                            data-target-prefix="manager">
                            <button type="button"
                                class="target-mode-tab active px-3 py-1.5 text-sm font-semibold bg-primary text-white"
                                data-target-prefix="manager" data-target-mode="daily">Daily Target</button>
                            <button type="button"
                                class="target-mode-tab px-3 py-1.5 text-sm font-semibold text-[#8c9097]"
                                data-target-prefix="manager" data-target-mode="monthly">Monthly Target</button>
                        </div>
                        <div class="mb-[2rem]">
                            <div class="mb-2 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Progress</h3>
                                <span id="manager_progress_percent" class="text-sm text-gray-800 dark:text-white">{{
                                    $managerInitialProgress['achievement_percentage'] ?? 0 }}%</span>
                            </div>
                            <div class="progress progress-xl !rounded-full" role="progressbar"
                                aria-valuenow="{{ $managerInitialPercent }}"
                                aria-valuemin="0" aria-valuemax="100">
                                <div id="manager_progress_bar" class="progress-bar bg-primary !rounded-full"
                                    data-percent="{{ $managerInitialPercent }}"
                                    style="width: {{ $managerInitialPercent }}%"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 justify-center">
                            <div class="xl:col-span-12 col-span-12">
                                <div class="">
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="manager_achieved_label">Achieved</span> :<span id="manager_achieved"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($managerInitialProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="manager_target_label">Target</span> :<span id="manager_target"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold">₹{{
                                            number_format($managerInitialProgress['target_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    {{-- <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Sales Amount :<span id="manager_sales"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-primary">₹{{
                                            number_format($teamTargetProgress['sales_amount'] ??
                                            $teamTargetProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p> --}}
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        <span id="manager_remaining_label">Remaining</span> :<span id="manager_remaining"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-warning">₹{{
                                            number_format($managerInitialProgress['remaining_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Required Daily Target :<span id="manager_required_daily"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-primary">&#8377;{{
                                            number_format($teamTargetProgress['required_daily_target'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Current Run Rate :<span id="manager_run_rate"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">&#8377;{{
                                            number_format($teamTargetProgress['current_run_rate'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Projected Month End :<span id="manager_projected"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">&#8377;{{
                                            number_format($teamTargetProgress['projected_month_end_sales'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Gap vs Target :<span id="manager_gap"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-warning">&#8377;{{
                                            number_format($teamTargetProgress['gap_vs_target'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-4 text-[#8c9097] dark:text-white/50">
                                        Working Days Left :<span id="manager_remaining_days"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold">{{
                                            $teamTargetProgress['remaining_days'] ?? 0 }}</span>
                                    </p>
                                    <p class="hidden text-[0.9rem] font-semibold mb-0 text-[#8c9097] dark:text-white/50">
                                        Status :<span id="manager_target_status"
                                            class="ltr:float-right rtl:float-left text-[0.9rem] font-semibold text-primary">{{
                                            $teamTargetProgress['target_status'] ?? 'Active' }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="rounded-md border border-defaultborder/10 p-4" style="height: 17rem;">
                            <div class="mb-3 flex items-center justify-between">
                                <h6 class="text-[1rem] font-semibold mb-0">Team Member Progress</h6>
                                <span id="manager_team_progress_mode_label"
                                    class="text-[0.75rem] text-[#8c9097] dark:text-white/50">Daily</span>
                            </div>
                            <div id="manager_team_progress_list" class="space-y-2 overflow-y-auto pr-1" style="max-height: 13rem;">
                                @forelse(($teamMemberProgress ?? collect()) as $member)
                                @php
                                    $memberInitialProgress = $member['daily_progress'] ?? $member;
                                    $memberPercent = min(100, max(0, (float) ($memberInitialProgress['achievement_percentage'] ?? 0)));
                                @endphp
                                <div class="rounded-md border border-defaultborder/10 p-2">
                                    <div class="mb-2 flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="mb-0 truncate text-sm font-semibold text-gray-800 dark:text-white">{{ $member['user_name'] ?? 'Team Member' }}</p>
                                            <p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">
                                                Achieved: &#8377;{{ number_format($memberInitialProgress['achieved_amount'] ?? 0, 2) }}
                                            </p>
                                            <p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">
                                                Target: &#8377;{{ number_format($memberInitialProgress['target_amount'] ?? 0, 2) }}
                                            </p>
                                            <p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">
                                                Remaining: &#8377;{{ number_format($memberInitialProgress['remaining_amount'] ?? 0, 2) }}
                                            </p>
                                        </div>
                                        <span class="text-sm font-semibold text-primary">{{ $memberInitialProgress['achievement_percentage'] ?? 0 }}%</span>
                                    </div>
                                    <div class="progress progress-xs !rounded-full" role="progressbar"
                                        aria-valuenow="{{ $memberPercent }}" aria-valuemin="0" aria-valuemax="100">
                                        <div class="progress-bar bg-primary !rounded-full" style="width: {{ $memberPercent }}%"></div>
                                    </div>
                                </div>
                                @empty
                                <div class="rounded-md bg-gray-50 p-3 text-sm text-[#8c9097] dark:text-white/50">
                                    No team target data available.
                                </div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
    {{-- <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <div class="flex-grow">
                    <div class="flex items-center">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                <i class="bx bx-trending-up text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Total Completed Sales vs Target
                        </h5>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-x-6">
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="mb-[2rem]">
                            <div class="mb-2 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Progress</h3>
                                <span class="text-sm text-gray-800 dark:text-white">32%</span>
                            </div>
                            <div class="progress progress-xl !rounded-full" role="progressbar" aria-valuenow="50"
                                aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar bg-primary !rounded-full w-2/4"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 justify-center">
                            <div class="xl:col-span-12 col-span-12">
                                <div class="">
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Achieved :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹45,000</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Target :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold">₹200,000</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Remaining :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-warning">₹155,000</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div id="donut-update"></div>
                    </div>
                </div>
            </div>
        </div>
    </div> --}}
    <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="grid grid-cols-12 gap-x-6">
            <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                <div class="box">
                    <div class="box-header">
                        <div class="flex-grow">
                            <div class="flex items-center">
                                <div class="me-4 gap-0">
                                    <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                        <i class="ri-calendar-2-line text-[1.5rem] text-white"></i>
                                    </span>
                                </div>
                                <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Today's Follow-up</h5>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table class="table whitespace-nowrap min-w-full caption-bottom">
                                <thead>
                                    <tr class="border-b border-defaultborder">
                                        <th scope="col" class="text-start">S.No.</th>
                                        <th scope="col" class="text-start">Name</th>
                                        <th scope="col" class="text-start">Number</th>
                                        <th scope="col" class="text-start">Representative</th>
                                        <th scope="col" class="text-start">Service</th>
                                        <th scope="col" class="text-start">Next Follow-up</th>
                                        <th scope="col" class="text-start">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="today_followups_tbody">
                                    @forelse($todayFollowUps as $intKey => $obj)
                                    <tr class="border-b border-defaultborder">
                                        <td scope="row">{{ $intKey + 1 }}</td>
                                        <td>{{ $obj->enquiry->client->name }}</td>
                                        <td>{{ $obj->enquiry->client->contact_number }}</td>
                                        <td>{{ $obj->enquiry->representative->name ?? '--' }}</td>
                                        <td>
                                            @php
                                            $serviceNames = $obj->enquiry->service_names ?? [];
                                            @endphp
                                            @if (!empty($serviceNames) && is_array($serviceNames))
                                            {{ Str::limit(implode(', ', $serviceNames), 25) }}
                                            @else
                                            N/A
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{ $obj->next_followup_date ? $obj->next_followup_date->format('d M, H:i') :
                                            '--' }}
                                        </td>
                                        <td>
                                            <a aria-label="anchor"
                                                href="{{ route('admin.leads.follow-up.create', $obj->enquiry->id) }}"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full" target="_blank"
                                                title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <!-- <a aria-label="anchor"
                                                        href="{{ route('admin.leads.edit', $obj->enquiry->id) }}"
                                                        class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full"
                                                        title="Edit">
                                                        <i class="ri-edit-line"></i>
                                                    </a> -->
                                        </td>
                                    </tr>

                                    {{-- Stop after 5 records --}}
                                    @if ($intKey + 1 === 5)
                                    @break
                                    @endif
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No Data Available</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if (isset($todayFollowUps) && count($todayFollowUps) > 0)
                                <caption class="text-primary mt-3"><a
                                        href="{{ route('admin.upcoming-follow-up.index') }}">Show More</a>
                                </caption>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                <div class="box">
                    <div class="box-header" style="display: block;">
                        <div class="flex items-center">
                            <div class="me-4 gap-0">
                                <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                    <i class="ri-phone-line text-[1.5rem] text-white"></i>
                                </span>
                            </div>
                            <div class="flex-grow">
                                <div class="flex items-center justify-between">
                                    <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">DNP Report
                                    </h5>
                                    {{-- <div class="hs-dropdown ti-dropdown">
                                        <a href="javascript:void(0);"
                                            class="ti-btn ti-btn-primary !py-1 !px-2 !text-[0.75rem] !m-0 !gap-0 !font-medium"
                                            aria-expanded="false">
                                            All Products<i
                                                class="ri-arrow-down-s-line align-middle ms-1 inline-block"></i>
                                        </a>
                                        <ul class="hs-dropdown-menu ti-dropdown-menu hidden" role="menu">
                                            <li><a class="ti-dropdown-item !py-2 !px-[0.9375rem] !text-[0.8125rem] !font-medium block"
                                                    href="javascript:void(0);">Buy</a></li>
                                            <li><a class="ti-dropdown-item !py-2 !px-[0.9375rem] !text-[0.8125rem] !font-medium block"
                                                    href="javascript:void(0);">Sell</a></li>
                                        </ul>
                                    </div> --}}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="table-responsive">
                            <table id="dnp-report-table" class="table whitespace-nowrap min-w-full caption-bottom">
                                <thead>
                                    <tr class="border-b border-defaultborder">
                                        <th scope="col" class="text-start">S.No.</th>
                                        <th scope="col" class="text-start">Name</th>
                                        <th scope="col" class="text-start">Number</th>
                                        <th scope="col" class="text-start">Service</th>
                                        <th scope="col" class="text-start">Last Follow-up</th>
                                        <th scope="col" class="text-start">Representative</th>
                                        <th scope="col" class="text-start">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($nextWeekDnpLeads as $intKey => $obj)
                                    <tr class="border-b border-defaultborder">
                                        <td scope="row">{{ $intKey + 1 }}</td>
                                        <td>{{ $obj->client->name }}</td>
                                        <td>{{ $obj->client->contact_number }}</td>
                                        <td>
                                            @php
                                            $serviceNames = $obj->service_names ?? [];
                                            @endphp
                                            @if (!empty($serviceNames) && is_array($serviceNames))
                                            {{ implode(', ', $serviceNames) }}
                                            @else
                                            N/A
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            {{ $obj->updated_at ? $obj->updated_at->format('d M, H:i') : '--' }}
                                        </td>
                                        <td>
                                            @if($obj->representative)
                                            {{ $obj->representative->name }}
                                            @else
                                            N/A
                                            @endif
                                        </td>
                                        <td>
                                            <a aria-label="anchor"
                                                href="{{ route('admin.clients.view', $obj->client->id) }}"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full" title="View">
                                                <i class="ri-eye-line"></i>
                                            </a>
                                            <a aria-label="anchor"
                                                href="{{ route('admin.clients.edit', $obj->client->id) }}"
                                                class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" title="Edit">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        </td>
                                    </tr>

                                    {{-- Stop after 5 records --}}
                                    @if ($intKey + 1 === 5)
                                    @break
                                    @endif
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No Data Available</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                                @if (isset($nextWeekDnpLeads) && count($nextWeekDnpLeads) > 0)
                                <caption class="text-primary mt-3"><a href="{{ route('admin.leads.dnp') }}">Show
                                        More</a></caption>
                                @endif
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="xxl:col-span-12 xl:col-span-12 lg:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <div class="flex-grow">
                    <div class="flex items-center">
                        <div class="me-4 gap-0">
                            <span class="avatar avatar-md p-2 !rounded-md bg-theme m-0">
                                <i class="bx bx-trending-up text-[1.5rem] text-white"></i>
                            </span>
                        </div>
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Product x Status Summary</h5>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <nav id="product-status-summary-tabs"
                    class="tab-style-1 sm:flex bg-light p-[0.65rem] rounded-sm --prevent-on-load-init"
                    aria-label="Product status summary tabs" role="tablist">
                    <a class="hs-tab-active:bg-primary hs-tab-active:text-white text-defaulttextcolor py-[0.35rem] px-4 flex-grow block text-sm font-medium text-center rounded-md hover:text-primary active"
                        id="product-date" href="javascript:void(0);" data-hs-tab="#product-date2" aria-controls="product-date2"
                        aria-selected="true" role="tab" tabindex="0">
                        By Service Date
                    </a>
                    <a class="hs-tab-active:bg-primary hs-tab-active:text-white text-defaulttextcolor py-[0.35rem] px-4 text-sm flex-grow block font-medium text-center  rounded-md hover:text-primary "
                        id="created-date" href="javascript:void(0);" data-hs-tab="#created-date2" aria-controls="created-date2"
                        aria-selected="false" role="tab" tabindex="-1">
                        By Created Date
                    </a>
                </nav>
                <div class="tab-content">
                    <div class="tab-pane !border-0 show active !p-0" id="product-date2" role="tabpanel"
                        aria-labelledby="product-date">
                        <div class="box">
                            <div class="box-header">
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">By Service Date
                                            -
                                            {{ $currentMonth }}
                                        </h5>
                                    </div>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table whitespace-nowrap min-w-full caption-bottom">
                                        <thead>
                                            <tr class="border-b border-defaultborder">
                                                <th scope="col" class="text-start">Product</th>
                                                <th scope="col" class="text-center text-primary">Active</th>
                                                <th scope="col" class="text-center text-danger">Cancelled</th>
                                                <th scope="col" class="text-center text-success">Lead Complete
                                                </th>
                                                <!-- <th scope="col" class="text-center text-warning">Other</th> -->
                                                <th scope="col" class="text-center">Total Leads
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="product_by_service_tbody">
                                            @forelse ($productSummary['dataByProductDate'] as $key => $service)
                                            <tr class="border-b border-defaultborder">
                                                <th scope="row" class="text-start">{{ $key }}</th>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Active'] }}</span>
                                                </td>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Cancelled'] }}</span>
                                                </td>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Confirmed/Complete'] }}</span>
                                                </td>
                                                <!-- <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Other'] ?? 0 }}</span> -->
                                                </td>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Total'] }}</span>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="9" class="text-center">No Data Available</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                        {{-- @if (isset($productSummary['dataByProductDate']) &&
                                        count($productSummary['dataByProductDate']) > 0)
                                        <caption class="text-primary mt-3"><a
                                                href="{{ route('admin.rides.ride-status') }}">Show Moreeee</a>
                                        </caption>
                                        @endif --}}
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane !border-0 !p-0 hidden" id="created-date2" role="tabpanel"
                        aria-labelledby="created-date">
                        <div class="box">
                            <div class="box-header">
                                <div class="flex-grow">
                                    <div class="flex items-center justify-between">
                                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">By Created Date
                                            -
                                            {{ $currentMonth }}
                                        </h5>
                                        {{-- <div class="hs-dropdown ti-dropdown">
                                            <a href="javascript:void(0);"
                                                class="ti-btn ti-btn-primary !py-1 !px-2 !text-[0.75rem] !m-0 !gap-0 !font-medium"
                                                aria-expanded="false">
                                                All Products<i
                                                    class="ri-arrow-down-s-line align-middle ms-1 inline-block"></i>
                                            </a>
                                            <ul class="hs-dropdown-menu ti-dropdown-menu hidden" role="menu">
                                                <li><a class="ti-dropdown-item !py-2 !px-[0.9375rem] !text-[0.8125rem] !font-medium block"
                                                        href="javascript:void(0);">Buy</a></li>
                                                <li><a class="ti-dropdown-item !py-2 !px-[0.9375rem] !text-[0.8125rem] !font-medium block"
                                                        href="javascript:void(0);">Sell</a></li>
                                            </ul>
                                        </div> --}}
                                    </div>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="table-responsive">
                                    <table class="table whitespace-nowrap min-w-full caption-bottom">
                                        <thead>
                                            <tr class="border-b border-defaultborder">
                                                <th scope="col" class="text-start">Product</th>
                                                <th scope="col" class="text-center text-primary">Active</th>
                                                <th scope="col" class="text-center text-danger">Cancelled</th>
                                                <th scope="col" class="text-center text-success">Lead Complete
                                                </th>
                                                <!-- <th scope="col" class="text-center text-warning">Other</th> -->
                                                <th scope="col" class="text-center">Total Leads
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="product_by_created_tbody">
                                            @forelse ($productSummary['dataByCreatedDate'] as $key => $service)
                                            <tr class="border-b border-defaultborder">
                                                <th scope="row" class="text-start">{{ $key }}</th>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Active'] }}</span>
                                                </td>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Cancelled'] }}</span>
                                                </td>
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Confirmed/Complete'] }}</span>
                                                </td>
                                                <!-- <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Other'] ?? 0 }}</span>
                                                </td> -->
                                                <td class="text-center"><span class="badge !rounded-full bg-black/10">{{
                                                        $service['Total'] }}</span>
                                                </td>
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="9" class="text-center">No Data Available</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                        {{-- @if (isset($productSummary['dataByCreatedDate']) &&
                                        count($productSummary['dataByCreatedDate']) > 0)
                                        <caption class="text-primary mt-3"><a
                                                href="{{ route('admin.rides.ride-status') }}">Show More</a>
                                        </caption>
                                        @endif --}}
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        (function() {
            var tabList = document.getElementById('product-status-summary-tabs');
            if (!tabList) return;

            var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[data-hs-tab]'));
            if (!tabs.length) return;

            function activateProductStatusTab(activeTab) {
                tabs.forEach(function(tab) {
                    var isActive = tab === activeTab;
                    var pane = document.querySelector(tab.getAttribute('data-hs-tab'));

                    tab.classList.toggle('active', isActive);
                    tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    tab.setAttribute('tabindex', isActive ? '0' : '-1');

                    if (pane) {
                        pane.classList.toggle('hidden', !isActive);
                        pane.classList.toggle('show', isActive);
                        pane.classList.toggle('active', isActive);
                    }
                });
            }

            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(event) {
                    event.preventDefault();
                    activateProductStatusTab(tab);
                });
            });

            activateProductStatusTab(tabs.find(function(tab) {
                return tab.classList.contains('active');
            }) || tabs[0]);
        })();
    </script>
    <script>
        (function(){
            var commonSelect = document.getElementById('dashboard_common_select');
            var spinner = document.getElementById('dashboard_loading_spinner');
            var overviewUrl = "{{ route('admin.sales-dashboard.overview-data') }}";
            var targetProgressState = {
                manager: @json($teamTargetProgress ?? []),
                executive: @json($targetProgress ?? [])
            };
            var teamMemberProgressState = @json($teamMemberProgress ?? []);
            var activeTargetModes = {
                manager: 'daily',
                executive: 'daily'
            };

            function escapeHtml(text){
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return String(text).replace(/[&<>\"']/g, function(m){ return map[m]; });
            }

            function formatCurrency(value){
                var amount = Number(value || 0);
                return '\u20B9' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function getModeProgress(data, mode) {
                if (!data) return {};
                if (mode === 'daily') return data.daily_progress || data;
                return data.monthly_progress || data;
            }

            function setText(id, value) {
                var el = document.getElementById(id);
                if (el) el.innerText = value;
            }

            function updateTargetDisplay(prefix, data, mode) {
                if (!data) return;
                var selectedMode = mode || activeTargetModes[prefix] || 'daily';
                var progress = getModeProgress(data, selectedMode);
                var pct = Number(progress.achievement_percentage || 0);
                var displayPct = Math.round(pct * 100) / 100;
                var barPct = Math.min(100, Math.max(0, pct));

                setText(prefix + '_progress_percent', displayPct + '%');
                var bar = document.getElementById(prefix + '_progress_bar');
                if (bar) {
                    bar.style.cssText = 'width: ' + barPct + '%';
                    bar.setAttribute('aria-valuenow', displayPct);
                }

                setText(prefix + '_achieved_label', 'Achieved');
                setText(prefix + '_target_label', 'Target');
                setText(prefix + '_remaining_label', 'Remaining');
                setText(prefix + '_achieved', formatCurrency(progress.achieved_amount || 0));
                setText(prefix + '_target', formatCurrency(progress.target_amount || 0));
                setText(prefix + '_sales', formatCurrency(progress.sales_amount || progress.achieved_amount || 0));
                setText(prefix + '_remaining', formatCurrency(progress.remaining_amount || 0));
                setText(prefix + '_required_daily', formatCurrency(data.required_daily_target || 0));
                setText(prefix + '_run_rate', formatCurrency(data.current_run_rate || 0));
                setText(prefix + '_projected', formatCurrency(data.projected_month_end_sales || 0));
                setText(prefix + '_gap', formatCurrency(data.gap_vs_target || 0));
                setText(prefix + '_remaining_days', data.remaining_days || 0);
                setText(prefix + '_target_status', data.target_status || data.status || 'Active');
                setText(prefix + '_target_month', ((data.month_name || '') + ' ' + (data.year || '')).trim());
            }

            function updateManagerDisplay(data){
                targetProgressState.manager = data || {};
                updateTargetDisplay('manager', targetProgressState.manager, activeTargetModes.manager);
            }

            function renderTeamMemberProgress(items, mode) {
                var list = document.getElementById('manager_team_progress_list');
                if (!list) return;

                var selectedMode = mode || activeTargetModes.manager || 'daily';
                setText('manager_team_progress_mode_label', selectedMode === 'daily' ? 'Daily' : 'Monthly');

                if (!items || !items.length) {
                    list.innerHTML = '<div class="rounded-md bg-gray-50 p-3 text-sm text-[#8c9097] dark:text-white/50">No team target data available.</div>';
                    return;
                }

                var html = '';
                items.forEach(function(item) {
                    var progress = getModeProgress(item, selectedMode);
                    var pct = Number(progress.achievement_percentage || 0);
                    var displayPct = Math.round(pct * 100) / 100;
                    var barPct = Math.min(100, Math.max(0, pct));
                    html += '<div class="rounded-md border border-defaultborder/10 p-2">';
                    html += '<div class="mb-2 flex items-center justify-between gap-3">';
                    html += '<div class="min-w-0">';
                    html += '<p class="mb-0 truncate text-sm font-semibold text-gray-800 dark:text-white">' + escapeHtml(item.user_name || 'Team Member') + '</p>';
                    html += '<p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">Achieved: ' + formatCurrency(progress.achieved_amount || 0) + '</p>';
                    html += '<p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">Target: ' + formatCurrency(progress.target_amount || 0) + '</p>';
                    html += '<p class="mb-0 text-[0.75rem] text-[#8c9097] dark:text-white/50">Remaining: ' + formatCurrency(progress.remaining_amount || 0) + '</p>';
                    html += '</div>';
                    html += '<span class="text-sm font-semibold text-primary">' + displayPct + '%</span>';
                    html += '</div>';
                    html += '<div class="progress progress-xs !rounded-full" role="progressbar" aria-valuenow="' + barPct + '" aria-valuemin="0" aria-valuemax="100">';
                    html += '<div class="progress-bar bg-primary !rounded-full" style="width: ' + barPct + '%"></div>';
                    html += '</div>';
                    html += '</div>';
                });
                list.innerHTML = html;
            }

            function updateSummaryCards(data){
                var totalCountEl = document.getElementById('total_leads_count');
                if (totalCountEl) totalCountEl.innerText = data.leads_count ?? 0;

                var changeEl = document.getElementById('total_leads_percentage');
                if (changeEl) {
                    var change = Number(data.percentage_change || 0);
                    var icon = change > 0 ? 'bx-trending-up' : 'bx-trending-down';
                    changeEl.innerHTML = '<i class="bx ' + icon + ' text-[1rem]"></i> ' + (change > 0 ? '+' : '') + change + '% vs last month';
                    changeEl.classList.remove('text-warning', 'text-danger', 'text-success');
                    changeEl.classList.add(change >= 0 ? 'text-warning' : 'text-danger');
                }

                var todayCountEl = document.getElementById('today_followups_count');
                if (todayCountEl) todayCountEl.innerText = data.today_followups_count ?? 0;

                var dnpCountEl = document.getElementById('dnp_report_count');
                if (dnpCountEl) dnpCountEl.innerText = data.dnp_count ?? 0;
            }

            function renderTodayRows(items){
                var tbody = document.getElementById('today_followups_tbody');
                if (!tbody) return;
                if (!items || items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No Data Available</td></tr>';
                    return;
                }
                var html = '';
                items.forEach(function(it, idx){
                    html += '<tr class="border-b border-defaultborder">';
                    html += '<td scope="row">' + (idx + 1) + '</td>';
                    html += '<td>' + escapeHtml(it.client_name) + '</td>';
                    html += '<td>' + escapeHtml(it.contact_number) + '</td>';
                    html += '<td>' + escapeHtml(it.representative_name) + '</td>';
                    html += '<td>' + escapeHtml(it.service_text) + '</td>';
                    html += '<td class="text-center">' + escapeHtml(it.next_followup) + '</td>';
                    html += '<td>' + (it.followup_route ? ('<a aria-label="anchor" href="' + escapeHtml(it.followup_route) + '" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full" target="_blank" title="View"><i class="ri-eye-line"></i></a>') : '') + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            }

            function renderDnpRows(items){
                var tbody = document.querySelector('#dnp-report-table tbody');
                if (!tbody) return;
                if (!items || items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center">No Data Available</td></tr>';
                    return;
                }
                var html = '';
                items.forEach(function(it, idx){
                    html += '<tr class="border-b border-defaultborder">';
                    html += '<td scope="row">' + (idx + 1) + '</td>';
                    html += '<td>' + escapeHtml(it.name) + '</td>';
                    html += '<td>' + escapeHtml(it.number) + '</td>';
                    html += '<td>' + escapeHtml(it.service_text) + '</td>';
                    html += '<td class="text-center">' + escapeHtml(it.last_followup) + '</td>';
                    html += '<td>' + escapeHtml(it.representative_name) + '</td>';
                    html += '<td>' + (it.view_route ? '<a aria-label="anchor" href="' + escapeHtml(it.view_route) + '" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-primary-full" title="View"><i class="ri-eye-line"></i></a>' : '') + (it.edit_route ? '<a aria-label="anchor" href="' + escapeHtml(it.edit_route) + '" class="ti-btn ti-btn-icon ti-btn-sm ti-btn-info-full" title="Edit"><i class="ri-edit-line"></i></a>' : '') + '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            }

            function renderProductRows(byService, byCreated){
                var serviceBody = document.getElementById('product_by_service_tbody');
                var createdBody = document.getElementById('product_by_created_tbody');
                if (serviceBody) serviceBody.innerHTML = buildProductRows(byService);
                if (createdBody) createdBody.innerHTML = buildProductRows(byCreated);
            }

            function buildProductRows(dataObj){
                var html = '';
                if (!dataObj || Object.keys(dataObj).length === 0) {
                    return '<tr><td colspan="5" class="text-center">No Data Available</td></tr>';
                }
                Object.keys(dataObj).forEach(function(product){
                    var item = dataObj[product] || { Active: 0, Cancelled: 0, 'Confirmed/Complete': 0, Total: 0 };
                    html += '<tr class="border-b border-defaultborder">';
                    html += '<th scope="row" class="text-start">' + escapeHtml(product) + '</th>';
                    html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Active'] || 0) + '</span></td>';
                    html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Cancelled'] || 0) + '</span></td>';
                    html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Confirmed/Complete'] || 0) + '</span></td>';
                    html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Total'] || 0) + '</span></td>';
                    html += '</tr>';
                });
                return html;
            }

            function refreshDashboard(userId){
                var query = userId ? ('?user_id=' + encodeURIComponent(userId)) : '';
                try { commonSelect.disabled = true; } catch (err) {}
                if (spinner) { spinner.style.display = 'inline-block'; spinner.classList.remove('hidden'); }
                var pctEl = document.getElementById('manager_progress_percent');
                if (pctEl) pctEl.innerText = '...';

                fetch(overviewUrl + query, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(resp){ if (!resp.ok) throw resp; return resp.json(); })
                    .then(function(json){
                        var data = json.data || {};
                        updateSummaryCards(data);
                        if (data.target_progress) {
                            updateManagerDisplay(Object.assign({}, data.target_progress, {
                                month_name: data.target_month_name || '',
                                year: data.target_month_year || ''
                            }));
                        }
                        teamMemberProgressState = data.team_member_progress || [];
                        renderTeamMemberProgress(teamMemberProgressState, activeTargetModes.manager);
                        renderTodayRows(data.today_followups || []);
                        renderDnpRows(data.dnp_leads || []);
                        renderProductRows(data.product_summary && data.product_summary.dataByProductDate ? data.product_summary.dataByProductDate : {}, data.product_summary && data.product_summary.dataByCreatedDate ? data.product_summary.dataByCreatedDate : {});
                    }).catch(function(err){
                        console.error('Failed to load dashboard overview', err);
                    }).finally(function(){
                        try { commonSelect.disabled = false; } catch (err) {}
                        if (spinner) { spinner.style.display = 'none'; spinner.classList.add('hidden'); }
                    });
            }

            if (commonSelect) {
                commonSelect.addEventListener('change', function(e){ refreshDashboard(e.target.value); });
            }

            document.querySelectorAll('.target-mode-tab').forEach(function(tab) {
                tab.addEventListener('click', function() {
                    var prefix = tab.getAttribute('data-target-prefix');
                    var mode = tab.getAttribute('data-target-mode') || 'monthly';
                    activeTargetModes[prefix] = mode;

                    document.querySelectorAll('.target-mode-tab[data-target-prefix="' + prefix + '"]').forEach(function(item) {
                        var isActive = item === tab;
                        item.classList.toggle('active', isActive);
                        item.classList.toggle('bg-primary', isActive);
                        item.classList.toggle('text-white', isActive);
                        item.classList.toggle('text-[#8c9097]', !isActive);
                    });

                    updateTargetDisplay(prefix, targetProgressState[prefix] || {}, mode);
                    if (prefix === 'manager') {
                        renderTeamMemberProgress(teamMemberProgressState, mode);
                    }
                });
            });

            (function initializeTargetCards(){
                updateTargetDisplay('manager', targetProgressState.manager || {}, activeTargetModes.manager);
                updateTargetDisplay('executive', targetProgressState.executive || {}, activeTargetModes.executive);
                renderTeamMemberProgress(teamMemberProgressState, activeTargetModes.manager);
            })();
        })();
    </script>
@stop
