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
<div id="lead-allocation-popup" class="hs-overlay hidden ti-modal" style="z-index: 9999;">
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="ti-modal-content w-full max-w-lg mx-auto" style="background-color: gainsboro;">
            <div class="ti-modal-header flex items-center justify-between px-6 py-4 border-b border-defaultborder">
                <div>
                    <h6 class="modal-title text-[1rem] font-semibold text-gray-800">Receive more leads</h6>
                    <p class="mb-0 text-sm text-gray-800">During working hours, this reminder appears every 2 hours.</p>
                </div>
                <button type="button" class="ti-modal-close-btn text-gray-500 hover:text-gray-700" data-hs-overlay="#lead-allocation-popup" aria-label="Close">
                    <i class="bx bx-x text-[1.2rem]"></i>
                </button>
            </div>
            <div class="ti-modal-body px-6 py-5">
                <div class="mb-4 flex items-start gap-3 rounded-lg border border-primary/20 p-3 bg-gray-50">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <i class="bx bx-bell text-[1.15rem]"></i>
                    </div>
                    <div>
                        <h6 class="font-semibold text-gray-800">{{ $popupData['queue_count'] ?? 0 }} lead(s) are waiting</h6>
                        <p class="mb-0 text-sm text-gray-600">If you click Yes, new leads will be assigned to you automatically.</p>
                    </div>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
                    Leads are only processed during working hours. After working hours, they stay queued and will be assigned the next working day.
                </div>
            </div>
            <div class="ti-modal-footer flex justify-end gap-2 border-t border-defaultborder px-6 py-4">
                <form method="POST" action="{{ route('admin.sales-dashboard.popup.decline') }}">
                    @csrf
                    <button type="submit" class="ti-btn" style="width:170px; padding: 1.2rem 1rem;background-color: #17b9f7; color: white !important;">No, skip for now</button>
                </form>
                <form method="POST" action="{{ route('admin.sales-dashboard.popup.accept') }}">
                    @csrf
                    <button type="submit" class="ti-btn bg-primary text-white">Yes, assign me more leads</button>
                </form>
            </div>
        </div>
    </div>
</div>

@if(($popupData['show_popup'] ?? false))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('lead-allocation-popup');
        if (!modal) return;

        try {
            modal.classList.remove('hidden');
            modal.classList.add('open');
            modal.setAttribute('aria-hidden', 'false');
            modal.style.display = 'block';

            if (window.HSOverlay && typeof window.HSOverlay.open === 'function') {
                try {
                    var instance = null;
                    if (window.HSOverlay.getInstance) {
                        try {
                            instance = window.HSOverlay.getInstance('#lead-allocation-popup');
                        } catch (e) {
                            instance = null;
                        }
                    }
                    if (!instance && window.HSOverlay.autoInit) {
                        try {
                            window.HSOverlay.autoInit();
                            instance = window.HSOverlay.getInstance ? window.HSOverlay.getInstance('#lead-allocation-popup') : null;
                        } catch (e) {
                            instance = null;
                        }
                    }
                    if (instance) {
                        window.HSOverlay.open('#lead-allocation-popup');
                        return;
                    }
                } catch (e) {
                    console.error('[sales-dashboard] HSOverlay.open failed', e);
                }
            }

            var inner = modal.querySelector('.ti-modal-content');
            if (inner) inner.classList.remove('hidden');
        } catch (e) {
            console.error('[sales-dashboard] popup fallback failed', e);
        }
    });
</script>
@endif

@if(Auth::user()->userType && in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_EXECUTIVE]))
<div class="mb-6 rounded-lg border border-defaultborder bg-white p-4 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h6 class="text-[1rem] font-semibold text-gray-800">Today's work update</h6>
            <p class="mb-0 text-sm text-gray-600">Share your today's tasks and time with your manager.</p>
        </div>
        <div class="rounded-lg bg-primary/5 px-3 py-2 text-sm text-primary">
            Manager: {{ $manager?->name ?? 'Not assigned' }}
        </div>
    </div>

    <form method="POST" action="{{ route('admin.sales-dashboard.daily-update.store') }}" class="mt-4 space-y-4">
        @csrf
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 lg:col-span-8">
                <label class="mb-1 block text-sm font-medium text-gray-700">Today's tasks</label>
                <textarea name="task_summary" rows="4" class="ti-form-input w-full" placeholder="Write your tasks, follow-ups, calls, meetings, and progress..." required>{{ old('task_summary', $dailyUpdate->task_summary ?? '') }}</textarea>
            </div>
            <div class="col-span-12 lg:col-span-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Work hours</label>
                <input type="number" step="0.25" min="0" max="24" name="work_hours" class="ti-form-input w-full" value="{{ old('work_hours', $dailyUpdate->work_hours ?? '') }}" required>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="ti-btn bg-primary text-white">Submit update</button>
        </div>
    </form>
</div>
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
                                    <h5 class="font-semibold mb-1 text-[1.25rem]">{{ $leads['leadsCount'] }}</h5>
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
                                    <div class="text-warning text-[0.85rem]">
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
                                    <h5 class="font-semibold mb-1 text-[1.25rem]">{{ $todayFollowUpsCount ?? count($todayFollowUps) }}</h5>
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
                                    <h5 class="font-semibold mb-1 text-[1.25rem]">{{ count($dnpLeads) }}</h5>
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
                                <span class="text-sm text-gray-800 dark:text-white">{{
                                    $targetProgress['achievement_percentage'] }}%</span>
                            </div>
                            <div class="progress progress-xl !rounded-full" role="progressbar"
                                aria-valuenow="{{ $targetProgress['achievement_percentage'] }}" aria-valuemin="0"
                                aria-valuemax="100">
                                <div class="progress-bar bg-primary !rounded-full"
                                    style="width: {{ $targetProgress['achievement_percentage'] }}%"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 justify-center">
                            <div class="xl:col-span-12 col-span-12">
                                <div class="">
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Achieved :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($targetProgress['achieved_amount'], 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Sales Amount :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($targetProgress['sales_amount'] ??
                                            $targetProgress['achieved_amount'], 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Target :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold">₹{{
                                            number_format($targetProgress['target_amount'], 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Remaining :<span
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-warning">₹{{
                                            number_format($targetProgress['remaining_amount'], 2) }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12">
                                <div
                                    class="box !shadow-none border border-defaultborder/10 dark:border-defaultborder/10">
                                    <div class="box-body text-center">
                                        <h6 class="text-[1rem] font-semibold mb-2">Target Month</h6>
                                        <p class="text-[0.85rem] text-[#8c9097] dark:text-white/50 mb-0">{{
                                            $currentMonthTarget->month_name }} {{ $currentMonthTarget->year }}</p>
                                    </div>
                                </div>
                            </div>
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
                        <h5 class="font-semibold mb-0 leading-none text-[1.25rem]">Total Completed Sales vs Target</h5>
                    </div>
                </div>
                <div class="hs-dropdown ti-dropdown ms-4">
                    <div class="relative inline-block">
                        <select id="manager_exec_select"
                            class="appearance-none form-control-sm py-2 pr-8 pl-2 border rounded-md text-sm">
                            <option value="">Team (All)</option>
                            {{-- If logged-in user is a sales manager, include themselves as an explicit option so they
                            can view their individual totals --}}
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
                        <!-- @if(in_array(Auth::user()->userType->user_type, [\App\Models\UserType::ADMIN, \App\Models\UserType::SUPER_ADMIN]))
                                                <div class="text-[0.75rem] text-gray-500 mt-1">Team (All) includes all Sales Managers and Sales Executives and aggregates their targets and achieved amounts.</div>
                                            @endif -->
                        <span id="manager_loading_spinner" class="hidden ms-2 text-sm text-muted" aria-hidden="true"
                            style="display:none;">
                            <!-- simple inline spinner -->
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
                        <div class="mb-[2rem]">
                            <div class="mb-2 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-800 dark:text-white">Progress</h3>
                                <span id="manager_progress_percent" class="text-sm text-gray-800 dark:text-white">{{
                                    $teamTargetProgress['achievement_percentage'] ?? 0 }}%</span>
                            </div>
                            <div class="progress progress-xl !rounded-full" role="progressbar"
                                aria-valuenow="{{ $teamTargetProgress['achievement_percentage'] ?? 0 }}"
                                aria-valuemin="0" aria-valuemax="100">
                                <div id="manager_progress_bar" class="progress-bar bg-primary !rounded-full"
                                    data-percent="{{ $teamTargetProgress['achievement_percentage'] ?? 0 }}"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-12 justify-center">
                            <div class="xl:col-span-12 col-span-12">
                                <div class="">
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Achieved :<span id="manager_achieved"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-success">₹{{
                                            number_format($teamTargetProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Target :<span id="manager_target"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold">₹{{
                                            number_format($teamTargetProgress['target_amount'] ?? 0, 2) }}</span>
                                    </p>
                                    {{-- <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Sales Amount :<span id="manager_sales"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-primary">₹{{
                                            number_format($teamTargetProgress['sales_amount'] ??
                                            $teamTargetProgress['achieved_amount'] ?? 0, 2) }}</span>
                                    </p> --}}
                                    <p class="text-[1rem] font-semibold mb-5 text-[#8c9097] dark:text-white/50">
                                        Remaining :<span id="manager_remaining"
                                            class="ltr:float-right rtl:float-left text-[1rem] font-semibold text-warning">₹{{
                                            number_format($teamTargetProgress['remaining_amount'] ?? 0, 2) }}</span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="xxl:col-span-6 xl:col-span-6 lg:col-span-12 col-span-12">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12">
                                <div
                                    class="box !shadow-none border border-defaultborder/10 dark:border-defaultborder/10">
                                    <div class="box-body text-center">
                                        <h6 class="text-[1rem] font-semibold mb-2">Target Month</h6>
                                        <p id="manager_target_month"
                                            class="text-[0.85rem] text-[#8c9097] dark:text-white/50 mb-0">{{ date('F Y')
                                            }}</p>
                                    </div>
                                </div>
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
                                <div class="hs-dropdown ti-dropdown">
                                    <div class="relative inline-block d-flex">
                                        @if(!in_array(Auth::user()->userType->user_type,
                                        [\App\Models\UserType::SALES_EXECUTIVE]))
                                        <select id="today_rep_select"
                                            class="appearance-none w-full form-control-sm py-2 pr-8 pl-2 border rounded-md text-sm">
                                            <option value="">Team (All)</option>
                                            @if(in_array(Auth::user()->userType->user_type,
                                            [\App\Models\UserType::SALES_MANAGER,
                                            \App\Models\UserType::SENIOR_SALES_MANAGER]))
                                            <option value="{{ Auth::id() }}">{{ Auth::user()->name }} (You)</option>
                                            @endif
                                            @foreach(($assignedExecutivesToday ?? $assignedExecutives ?? collect()) as $exec)
                                            @php $typeLabel = $exec->userType->user_type ?? ''; @endphp
                                            <option value="{{ $exec->id }}">{{ $exec->name }} @if($typeLabel) ({{
                                                $typeLabel }}) @endif</option>
                                            @endforeach
                                        </select>
                                        @endif
                                        <span id="today_loading_spinner" class="hidden ms-2 text-sm text-muted"
                                            aria-hidden="true" style="display:none;">
                                            <svg class="inline-block" width="16" height="16" viewBox="0 0 24 24"
                                                fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <circle cx="12" cy="12" r="10" stroke="#6b7280" stroke-opacity="0.25"
                                                    stroke-width="4"></circle>
                                                <path d="M22 12a10 10 0 0 0-10-10" stroke="#3b82f6" stroke-width="4"
                                                    stroke-linecap="round">
                                                    <animateTransform attributeName="transform" type="rotate"
                                                        from="0 12 12" to="360 12 12" dur="0.9s"
                                                        repeatCount="indefinite" />
                                                </path>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
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
                            <table class="table whitespace-nowrap min-w-full caption-bottom">
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
                <div class="ms-4">
                    <div class="relative inline-block">
                        @if(!in_array(Auth::user()->userType->user_type, [\App\Models\UserType::SALES_EXECUTIVE]))
                        <select id="product_rep_select"
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
                        @endif
                        <span id="product_loading_spinner" class="hidden ms-2 text-sm text-muted" aria-hidden="true"
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
                        </span>
                    </div>
                </div>
            </div>
            <div class="box-body">
                <nav class="tab-style-1 sm:flex bg-light p-[0.65rem] rounded-sm" aria-label="Tabs" role="tablist">
                    <a class="hs-tab-active:bg-primary hs-tab-active:text-white text-defaulttextcolor py-[0.35rem] px-4 flex-grow block text-sm font-medium text-center rounded-md hover:text-primary active"
                        id="product-date" data-hs-tab="#product-date2" aria-controls="product-date2">
                        By Service Date
                    </a>
                    <a class="hs-tab-active:bg-primary hs-tab-active:text-white text-defaulttextcolor py-[0.35rem] px-4 text-sm flex-grow block font-medium text-center  rounded-md hover:text-primary "
                        id="created-date" data-hs-tab="#created-date2" aria-controls="created-date2">
                        By Created Date
                    </a>
                </nav>
                <div class="tab-content">
                    <div class="tab-pane !border-0 show active !p-0" id="product-date2" role="tabpanel">
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
        (function(){
            var leadPopupModal = document.getElementById('lead-allocation-popup');
            if (leadPopupModal && leadPopupModal.classList.contains('hidden')) {
                leadPopupModal.classList.remove('hidden');
            }

            // Product summary filter: fetch and replace product x status tables based on selected representative/team
            (function(){
                var url = "{{ route('admin.sales-dashboard.product-summary') }}";
                var select = document.getElementById('product_rep_select');
                var spinner = document.getElementById('product_loading_spinner');

                function escapeHtml(text){
                    var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                    return String(text).replace(/[&<>\"']/g, function(m){ return map[m]; });
                }

                function buildRows(dataObj){
                    var html = '';
                    if (!dataObj || Object.keys(dataObj).length === 0) {
                        html += '<tr><td colspan="9" class="text-center">No Data Available</td></tr>';
                        return html;
                    }
                    Object.keys(dataObj).forEach(function(product){
                        var item = dataObj[product] || {Active:0, Cancelled:0, 'Confirmed/Complete':0};
                        html += '<tr class="border-b border-defaultborder">';
                        html += '<th scope="row" class="text-start">' + escapeHtml(product) + '</th>';
                        html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Active'] || 0) + '</span></td>';
                        html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Cancelled'] || 0) + '</span></td>';
                        html += '<td class="text-center"><span class="badge !rounded-full bg-black/10">' + (item['Confirmed/Complete'] || 0) + '</span></td>';
                        html += '</tr>';
                    });
                    return html;
                }

                if (select) {
                    select.addEventListener('change', function(e){
                        var val = e.target.value;
                        var query = val ? ('?user_id=' + encodeURIComponent(val)) : '';
                        try { select.disabled = true; } catch (err) {}
                        if (spinner) { spinner.style.display = 'inline-block'; spinner.classList.remove('hidden'); }

                        fetch(url + query, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then(function(resp){ if (!resp.ok) throw resp; return resp.json(); })
                        .then(function(json){
                            var d = (json.data || {});
                            var byService = d.dataByProductDate || {};
                            var byCreated = d.dataByCreatedDate || {};

                            var serviceBody = document.getElementById('product_by_service_tbody');
                            var createdBody = document.getElementById('product_by_created_tbody');
                            if (serviceBody) serviceBody.innerHTML = buildRows(byService);
                            if (createdBody) createdBody.innerHTML = buildRows(byCreated);
                        }).catch(function(err){
                            console.error('Failed to load product summary', err);
                        }).finally(function(){
                            try { select.disabled = false; } catch (err) {}
                            if (spinner) { spinner.style.display = 'none'; spinner.classList.add('hidden'); }
                        });
                    });
                }
            })();

            var url = "{{ route('admin.sales-dashboard.target-progress') }}";

            function updateManagerDisplay(data) {
                var d = data.data || {};
                var pct = d.achievement_percentage || 0;
                var achieved = d.achieved_amount || 0;
                var target = d.target_amount || 0;
                var remaining = d.remaining_amount || 0;

                var pctEl = document.getElementById('manager_progress_percent');
                if (pctEl) pctEl.innerText = pct + '%';

                var bar = document.getElementById('manager_progress_bar');
                if (bar) {
                    // Set width via cssText to avoid template + percent parsing issues in some linters
                    bar.style.cssText = 'width: ' + pct + '%';
                    bar.setAttribute('aria-valuenow', pct);
                }

                var elA = document.getElementById('manager_achieved');
                var elT = document.getElementById('manager_target');
                var elS = document.getElementById('manager_sales');
                var elR = document.getElementById('manager_remaining');
                if (elA) elA.innerText = '₹' + Number(achieved).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                if (elS) elS.innerText = '₹' + Number(d.sales_amount || d.achieved_amount || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                if (elT) elT.innerText = '₹' + Number(target).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                if (elR) elR.innerText = '₹' + Number(remaining).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            }

            var select = document.getElementById('manager_exec_select');
            var spinner = document.getElementById('manager_loading_spinner');
            // initialize progress bar width from server-rendered data-percent if present
            (function setInitialBar(){
                var bar = document.getElementById('manager_progress_bar');
                var pctEl = document.getElementById('manager_progress_percent');
                if (bar) {
                    var pct = bar.getAttribute('data-percent');
                    if ((!pct || pct === 'undefined') && pctEl) {
                        pct = pctEl.innerText.replace('%','') || 0;
                    }
                    try { bar.style.cssText = 'width: ' + (pct || 0) + '%'; bar.setAttribute('aria-valuenow', pct || 0); } catch(err) {}
                }
            })();
            if (select) {
                select.addEventListener('change', function(e){
                    var val = e.target.value;
                    var query = val ? ('?user_id=' + encodeURIComponent(val)) : '';

                    // UI: disable select and show spinner
                    try { select.disabled = true; } catch (err) {}
                    if (spinner) { spinner.style.display = 'inline-block'; spinner.classList.remove('hidden'); }
                    var pctEl = document.getElementById('manager_progress_percent');
                    if (pctEl) pctEl.innerText = '...';

                    fetch(url + query, {
                        credentials: 'same-origin',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function(resp){
                        if (!resp.ok) throw resp;
                        return resp.json();
                    }).then(function(json){
                        updateManagerDisplay(json);
                    }).catch(function(err){
                        console.error('Failed to load target progress', err);
                        updateManagerDisplay({ data: { achievement_percentage: 0, achieved_amount: 0, target_amount: 0, remaining_amount: 0 } });
                    }).finally(function(){
                        try { select.disabled = false; } catch (err) {}
                        if (spinner) { spinner.style.display = 'none'; spinner.classList.add('hidden'); }
                    });
                });
            }
        })();
    </script>
    <script>
        (function(){
            var url = "{{ route('admin.sales-dashboard.today-followups') }}";
            var select = document.getElementById('today_rep_select');
            var spinner = document.getElementById('today_loading_spinner');

            function escapeHtml(text){
                var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return String(text).replace(/[&<>\"']/g, function(m){ return map[m]; });
            }

            function buildRows(items){
                if (!items || items.length === 0) {
                    return '<tr><td colspan="7" class="text-center">No Data Available</td></tr>';
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
                return html;
            }

            if (select) {
                select.addEventListener('change', function(e){
                    var val = e.target.value;
                    var query = val ? ('?user_id=' + encodeURIComponent(val)) : '';
                    try { select.disabled = true; } catch (err) {}
                    if (spinner) { spinner.style.display = 'inline-block'; spinner.classList.remove('hidden'); }

                    fetch(url + query, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function(resp){ if (!resp.ok) throw resp; return resp.json(); })
                    .then(function(json){
                        var items = json.data || [];
                        var tbody = document.getElementById('today_followups_tbody');
                        if (tbody) tbody.innerHTML = buildRows(items);
                    }).catch(function(err){
                        console.error('Failed to load today followups', err);
                    }).finally(function(){
                        try { select.disabled = false; } catch (err) {}
                        if (spinner) { spinner.style.display = 'none'; spinner.classList.add('hidden'); }
                    });
                });
            }
        })();
    </script>
@stop