@extends('admin.layouts.header')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<div class="crm-kpi-management crm-kpi-tool" data-kpi-tool>
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">KPI Management</h3>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@php
    $defaultKpiDefinitions = $defaultKpiDefinitions ?? [];
    $defaultKpiCoverage = $defaultKpiCoverage ?? [];
@endphp


@if(!empty($defaultKpiDefinitions))
    <div class="box mb-6">
        <div class="box-header flex justify-between items-center">
            <div>
                <h5 class="box-title mb-0">Automated KPI Setup</h5>
                <small class="text-muted">
                    Sync the standard KPI table into active templates and assign it to employees without an active KPI assignment.
                </small>
            </div>
        </div>
        <div class="box-body">
            @foreach($defaultKpiDefinitions as $departmentKey => $definition)
                @php
                    $coverage = $defaultKpiCoverage[$departmentKey] ?? [];
                    $metrics = collect($definition['metrics'] ?? []);
                @endphp

                <div class="km-department">
                    <div class="flex flex-wrap justify-between gap-3 mb-4">
                        <div>
                            <h6 class="font-semibold mb-1">
                                {{ $definition['department_label'] ?? ucfirst($departmentKey) }} Department KPI
                            </h6>
                            <div class="km-pill {{ ($coverage['assigned_users'] ?? 0) < ($coverage['eligible_users'] ?? 0) ? 'km-amber' : 'km-green' }}">
                                {{ $coverage['assigned_users'] ?? 0 }} / {{ $coverage['eligible_users'] ?? 0 }} eligible users assigned.
                            </div>
                            <a href="#km-assignment" class="km-link" data-assign-department="{{ $departmentKey }}">Assign users</a>
                            @if(!empty($definition['updated_at']))<small>Template updated {{ $definition['updated_at']->format('d M Y, H:i') }}</small>@endif
                        </div>
                            <form method="POST" action="{{ route('admin.kpi.automation.sync') }}">
                                @csrf
                                <input type="hidden" name="department" value="{{ $departmentKey }}">
                                <button class="ti-btn ti-btn-primary">
                                    Sync {{ ucfirst($departmentKey) }} KPI
                                </button>
                            </form>
                    </div>

                    <div class="km-metrics">
                        @foreach($metrics as $metric)
                            <div class="km-metric">
                                <div><strong>{{ $metric['name'] ?? '-' }}</strong><p>{{ in_array($metric['code'] ?? '', ['attendance', 'operations_attendance'], true) ? 'Presence on working days; P and MIS count as present. Holidays and approved offs are excluded.' : ($metric['description'] ?? '') }}</p></div>
                                <div class="km-weight"><strong>{{ (float) ($metric['weightage'] ?? 0) }}</strong><progress max="100" value="{{ (float) ($metric['weightage'] ?? 0) }}" aria-label="Metric weight"></progress></div>
                                <div class="km-bands">@foreach([5,4,3,2,1] as $score)<span><b>{{ $score }}/5</b> {{ $metric['score_labels'][$score] ?? $metric['score_rules'][$score] ?? '-' }}</span>@endforeach</div>
                                @if(($metric['measurement_type'] ?? '') === 'automatic')<span class="km-pill km-blue">Auto · {{ $metric['source_key'] ?? '-' }}</span>@else<a class="km-pill km-amber" href="#km-manual">Manual entry</a>@endif
                            </div>
                        @endforeach
                        <div class="km-total"><strong>Total weightage</strong><strong>{{ (float) $metrics->sum(fn ($metric) => (float) ($metric['weightage'] ?? 0)) }}</strong><span>{{ $metrics->count() }} metrics · {{ $metrics->where('measurement_type', 'automatic')->count() }} automatic · {{ $metrics->where('measurement_type', '!=', 'automatic')->count() }} manual</span></div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

<details class="km-editor km-configuration" id="km-configuration"><summary>Manage templates, assignments, calendar and manual values</summary>
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header"><h5 class="box-title">Create KPI Template</h5></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.kpi.templates.store') }}" class="grid grid-cols-12 gap-4">
                    @csrf
                    <div class="col-span-12">
                        <label class="ti-form-label">Name</label>
                        <input class="ti-form-input" name="name" placeholder="Retail Sales KPI" required>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Department</label>
                        <select class="ti-form-select" name="department" required>
                            <option value="sales">Sales</option>
                            <option value="operations">Operations</option>
                            <option value="accounts">Accounts</option>
                        </select>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Working Days / Month</label>
                        <input class="ti-form-input" type="number" name="working_days_per_month" value="22" min="1" max="31" required>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Effective From</label>
                        <input class="ti-form-input" type="date" name="effective_from">
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Effective To</label>
                        <input class="ti-form-input" type="date" name="effective_to">
                    </div>
                    <div class="col-span-12">
                        <button class="ti-btn ti-btn-primary">Create Template</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header" id="km-assignment"><h5 class="box-title">Assign Template</h5></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.kpi.assignments.store') }}" class="grid grid-cols-12 gap-4">
                    @csrf
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Employee</label>
                        <select class="ti-form-select" name="user_id" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} - {{ optional($user->userType)->user_type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Template</label>
                        <select class="ti-form-select" name="template_id" required>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" data-department="{{ $template->department }}">{{ $template->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Effective From</label>
                        <input class="ti-form-input" type="date" name="effective_from" value="{{ now()->startOfMonth()->toDateString() }}" required>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Effective To</label>
                        <input class="ti-form-input" type="date" name="effective_to">
                    </div>
                    <div class="col-span-12">
                        <button class="ti-btn ti-btn-primary">Assign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header"><h5 class="box-title">Create Metric</h5></div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.kpi.metrics.store') }}" class="grid grid-cols-12 gap-4">
            @csrf
            <div class="md:col-span-4 col-span-12">
                <label class="ti-form-label">Template</label>
                <select class="ti-form-select" name="template_id" required>
                    @foreach($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="md:col-span-4 col-span-12">
                <label class="ti-form-label">Code</label>
                <input class="ti-form-input" name="code" placeholder="daily_outreach" required>
            </div>
            <div class="md:col-span-4 col-span-12">
                <label class="ti-form-label">Weightage</label>
                <input class="ti-form-input" type="number" step="0.01" name="weightage" min="0" max="100" required>
            </div>
            <div class="md:col-span-6 col-span-12">
                <label class="ti-form-label">Name</label>
                <input class="ti-form-input" name="name" required>
            </div>
            <div class="md:col-span-6 col-span-12">
                <label class="ti-form-label">Description</label>
                <input class="ti-form-input" name="description">
            </div>
            <div class="md:col-span-3 col-span-12">
                <label class="ti-form-label">Measurement</label>
                <select class="ti-form-select" name="measurement_type" required>
                    <option value="automatic">Automatic</option>
                    <option value="manual_numeric">Manual Numeric</option>
                    <option value="manual_rating">Manual Rating</option>
                </select>
            </div>
            <div class="md:col-span-3 col-span-12">
                <label class="ti-form-label">Source Key</label>
                <input class="ti-form-input" name="source_key" placeholder="sales_outreach">
            </div>
            <div class="md:col-span-3 col-span-12">
                <label class="ti-form-label">Target Value</label>
                <input class="ti-form-input" type="number" step="0.01" name="target_value">
            </div>
            <div class="md:col-span-3 col-span-12">
                <label class="ti-form-label">Direction</label>
                <select class="ti-form-select" name="direction" required>
                    <option value="higher_better">Higher Better</option>
                    <option value="lower_better">Lower Better</option>
                </select>
            </div>
            @foreach([5, 4, 3, 2, 1] as $score)
                <div class="md:col-span-2 col-span-6">
                    <label class="ti-form-label">{{ $score }}/5 rule</label>
                    <input class="ti-form-input" type="number" step="0.01" name="score_{{ $score }}" value="{{ $score === 5 ? 100 : ($score === 4 ? 90 : ($score === 3 ? 80 : ($score === 2 ? 70 : 0))) }}" required>
                </div>
            @endforeach
            <div class="md:col-span-2 col-span-6">
                <label class="ti-form-label">Sort</label>
                <input class="ti-form-input" type="number" name="sort_order" value="0">
            </div>
            <div class="col-span-12">
                <button class="ti-btn ti-btn-primary">Create Metric</button>
            </div>
        </form>
    </div>
</div>

<div class="grid grid-cols-12 gap-6 mt-6">
    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header"><h5 class="box-title">Working Calendar</h5></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.kpi.working-days.store') }}" class="grid grid-cols-12 gap-4">
                    @csrf
                    <div class="md:col-span-4 col-span-12">
                        <label class="ti-form-label">Date</label>
                        <input class="ti-form-input" type="date" name="work_date" required>
                    </div>
                    <div class="md:col-span-4 col-span-12">
                        <label class="ti-form-label">Working Day</label>
                        <select class="ti-form-select" name="is_working_day" required>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="md:col-span-4 col-span-12">
                        <label class="ti-form-label">Note</label>
                        <input class="ti-form-input" name="note">
                    </div>
                    <div class="col-span-12">
                        <button class="ti-btn ti-btn-primary">Save Date</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header" id="km-manual"><h5 class="box-title">Manual KPI Value</h5></div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.kpi.manual-values.store') }}" class="grid grid-cols-12 gap-4">
                    @csrf
                    <div class="md:col-span-4 col-span-12">
                        <label class="ti-form-label">Employee</label>
                        <select class="ti-form-select" name="user_id" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-4 col-span-12">
                        <label class="ti-form-label">Metric</label>
                        <select class="ti-form-select" name="metric_id" required>
                            @foreach($templates as $template)
                                @foreach($template->metrics as $metric)
                                    <option value="{{ $metric->id }}">{{ $template->name }} - {{ $metric->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-2 col-span-6">
                        <label class="ti-form-label">Year</label>
                        <input class="ti-form-input" type="number" name="year" value="{{ now()->year }}" required>
                    </div>
                    <div class="md:col-span-2 col-span-6">
                        <label class="ti-form-label">Month</label>
                        <input class="ti-form-input" type="number" name="month" min="1" max="12" value="{{ now()->month }}" required>
                    </div>
                    <div class="md:col-span-3 col-span-12">
                        <label class="ti-form-label">Value</label>
                        <input class="ti-form-input" type="number" step="0.01" name="value">
                    </div>
                    <div class="md:col-span-3 col-span-12">
                        <label class="ti-form-label">Rating</label>
                        <input class="ti-form-input" type="number" min="1" max="5" name="rating">
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Note</label>
                        <input class="ti-form-input" name="note">
                    </div>
                    <div class="col-span-12">
                        <button class="ti-btn ti-btn-primary">Save Manual Value</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">KPI Team Scope - Accounts / Operations</h5>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.kpi.team-memberships.store') }}" class="grid grid-cols-12 gap-4 mb-6">
            @csrf

            <div class="md:col-span-3 col-span-12">
                <label class="ti-form-label">Department</label>
                <select class="ti-form-select" name="department" required>
                    <option value="accounts">Accounts</option>
                    <option value="operations">Operations</option>
                </select>
            </div>

            <div class="md:col-span-4 col-span-12">
                <label class="ti-form-label">Manager</label>
                <select class="ti-form-select" name="manager_user_id" required>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->name }} - {{ optional($user->userType)->user_type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-4 col-span-12">
                <label class="ti-form-label">Team Member</label>
                <select class="ti-form-select" name="member_user_id" required>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">
                            {{ $user->name }} - {{ optional($user->userType)->user_type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="md:col-span-1 col-span-12 flex items-end">
                <button class="ti-btn ti-btn-primary">Add</button>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="table whitespace-nowrap min-w-full">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Manager</th>
                        <th>Member</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($teamMemberships as $membership)
                        <tr>
                            <td>{{ ucfirst($membership->department) }}</td>
                            <td>{{ optional($membership->manager)->name }}</td>
                            <td>{{ optional($membership->member)->name }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.kpi.team-memberships.delete', $membership) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ti-btn ti-btn-danger ti-btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No Accounts/Operations KPI team memberships configured.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

</details>
<div class="box mt-6">
    <div class="box-header"><h5 class="box-title">Templates</h5></div>
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Department</th>
                    <th>Metrics</th>
                    <th>Working Days</th>
                    <th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                    <tr>
                        <td data-label="Template">{{ $template->name }}</td>
                        <td data-label="Department">{{ ucfirst($template->department) }}</td>
                        <td data-label="Metrics">{{ $template->metrics->where('active', true)->count() }}</td>
                        <td data-label="Working days">{{ $template->working_days_per_month }}</td>
                        <td data-label="Status">{{ $template->active ? 'Active' : 'Inactive' }}</td>
                        <td data-label="Action"><a href="#km-template-{{ $template->id }}" data-open-template="{{ $template->id }}">View / Edit</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@include('admin.pages.kpi.partials.management-editors')
@include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1" defer></script>
@endpush
