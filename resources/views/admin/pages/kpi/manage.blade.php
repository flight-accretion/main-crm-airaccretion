@extends('admin.layouts.header')

@section('content')
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
            <div class="box-header"><h5 class="box-title">Assign Template</h5></div>
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
                                <option value="{{ $template->id }}">{{ $template->name }}</option>
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
            <div class="box-header"><h5 class="box-title">Manual KPI Value</h5></div>
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
    <div class="box-header"><h5 class="box-title">Active Templates</h5></div>
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Template</th>
                    <th>Department</th>
                    <th>Metrics</th>
                    <th>Working Days</th>
                </tr>
            </thead>
            <tbody>
                @foreach($templates as $template)
                    <tr>
                        <td>{{ $template->name }}</td>
                        <td>{{ ucfirst($template->department) }}</td>
                        <td>{{ $template->metrics->count() }}</td>
                        <td>{{ $template->working_days_per_month }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
