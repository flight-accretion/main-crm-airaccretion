@extends('admin.layouts.header')

@section('content')
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="text-[1.125rem] font-semibold">Operations Manual KPI</h3>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-4 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Add Manual Value</h5>
            </div>
            <div class="box-body">
                <form method="POST" action="{{ route('admin.hr.operations-manual-kpi.store') }}" class="grid grid-cols-12 gap-4">
                    @csrf
                    <div class="col-span-12">
                        <label class="ti-form-label">Operations Employee</label>
                        <select class="ti-form-select" name="user_id" required>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}">{{ $user->name }} - {{ optional($user->userType)->user_type }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-span-12">
                        <label class="ti-form-label">Manual KPI</label>
                        <select class="ti-form-select" name="metric_id" required>
                            @foreach($metrics as $metric)
                                <option value="{{ $metric->id }}">{{ $metric->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Year</label>
                        <input class="ti-form-input" type="number" name="year" value="{{ $year }}" min="2025" max="2100" required>
                    </div>
                    <div class="md:col-span-6 col-span-12">
                        <label class="ti-form-label">Month</label>
                        <input class="ti-form-input" type="number" name="month" value="{{ $month }}" min="1" max="12" required>
                    </div>
                    <div class="col-span-12">
                        <label class="ti-form-label">Value / Rating</label>
                        <input class="ti-form-input" type="number" step="0.01" name="value" min="0" max="100" required>
                    </div>
                    <div class="col-span-12">
                        <label class="ti-form-label">Note</label>
                        <textarea class="ti-form-input" name="note" rows="3"></textarea>
                    </div>
                    <div class="col-span-12 flex gap-2">
                        <button class="ti-btn ti-btn-light" name="action" value="draft">Save Draft</button>
                        <button class="ti-btn ti-btn-primary" name="action" value="submit">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="xl:col-span-8 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">Pending Approval</h5>
            </div>
            <div class="box-body overflow-x-auto">
                <table class="table whitespace-nowrap min-w-full">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>KPI</th>
                            <th>Period</th>
                            <th>Value</th>
                            <th>Submitted By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingSubmissions as $submission)
                            <tr>
                                <td>{{ optional($submission->user)->name }}</td>
                                <td>{{ optional($submission->metric)->name }}</td>
                                <td>{{ $submission->month }}/{{ $submission->year }}</td>
                                <td>{{ number_format($submission->value, 2) }}</td>
                                <td>{{ optional($submission->submitter)->name }}</td>
                                <td>
                                    <form method="POST" action="{{ route('admin.hr.operations-manual-kpi.approve', $submission) }}" class="inline-block">
                                        @csrf
                                        <button class="ti-btn ti-btn-success ti-btn-sm">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.hr.operations-manual-kpi.reject', $submission) }}" class="inline-block">
                                        @csrf
                                        <input type="hidden" name="review_note" value="Rejected from HR dashboard.">
                                        <button class="ti-btn ti-btn-danger ti-btn-sm">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">No pending Operations manual KPI submissions.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">Manual KPI History</h5>
    </div>
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Employee</th>
                    <th>KPI</th>
                    <th>Period</th>
                    <th>Value</th>
                    <th>Submitted By</th>
                    <th>Approved By</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($historySubmissions as $submission)
                    <tr>
                        <td>{{ ucfirst($submission->status) }}</td>
                        <td>{{ optional($submission->user)->name }}</td>
                        <td>{{ optional($submission->metric)->name }}</td>
                        <td>{{ $submission->month }}/{{ $submission->year }}</td>
                        <td>{{ number_format($submission->value, 2) }}</td>
                        <td>{{ optional($submission->submitter)->name }}</td>
                        <td>{{ optional($submission->approver)->name }}</td>
                        <td>{{ optional($submission->updated_at)->format('d M Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No manual KPI history yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
