@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/kpi/management-work.css') }}?v=1">
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/operations-tools.css') }}?v=1">
<div class="crm-kpi-tool crm-ops-tool" data-kpi-tool data-manual-kpi>
<header class="page-header"><h3>Operations Manual KPI</h3><p>Monthly values for Operations manual metrics</p></header>
@if(session('success'))<p role="status" class="op-pill op-approved">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@unless($canReview)
<form method="GET" class="kt-filter op-period">
<div><label for="period-year">Year</label><input id="period-year" type="number" name="year" min="2025" max="2100" value="{{ $year }}" required></div>
<div><label for="period-month">Month</label><select id="period-month" name="month">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000,$m,1)->format('F') }}</option>@endfor</select></div>
<button class="ti-btn ti-btn-primary">Select period</button>
</form>
@endunless
<div class="op-manual-columns">
<section class="op-section"><h2>Add Manual Value</h2>
<form method="POST" action="{{ route('admin.hr.operations-manual-kpi.store') }}" class="op-entry">
@csrf
<div><label for="manual-user">Operations employee</label><select id="manual-user" name="user_id" required>
@if($canReview)<option value="">Select employee</option>@endif
@foreach($users as $user)<option value="{{ $user->id }}" {{ old('user_id') === $user->id ? 'selected' : '' }}>{{ $user->name }} - {{ $user->userType?->user_type }}</option>@endforeach
</select></div>
<div><label for="manual-metric">Manual KPI</label><select id="manual-metric" name="metric_id" required data-manual-metric>
<option value="">Select metric</option>
@foreach($metrics as $metric)
<option value="{{ $metric->id }}" {{ old('metric_id') === $metric->id ? 'selected' : '' }} data-config="{{ json_encode(['weight'=>$metric->weightage,'type'=>$metric->measurement_type,'rules'=>$metric->score_rules,'direction'=>$metric->direction,'target'=>$metric->target_value]) }}">{{ $metric->name }} (weight {{ $metric->weightage }})</option>
@endforeach
</select>@if($metrics->isEmpty())<p>No assigned manual metrics for this period.</p>@endif</div>
<div class="op-pair">
<div><label for="manual-year">Year</label><input id="manual-year" type="number" name="year" min="2025" max="2100" value="{{ old('year',$year) }}" {{ $canReview ? '' : 'readonly' }} required></div>
<div><label for="manual-month">Month</label>
@if(!$canReview)<input type="hidden" name="month" value="{{ $month }}"><input id="manual-month" value="{{ \Carbon\Carbon::create(2000,$month,1)->format('F') }}" readonly>
@else<select id="manual-month" name="month">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ (int)old('month',$month) === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000,$m,1)->format('F') }}</option>@endfor</select>@endif
</div></div>
<div><label for="manual-value">Value / Rating</label><input id="manual-value" name="value" type="number" step="0.01" min="0" max="100" value="{{ old('value') }}" required data-manual-value><div class="km-bands op-bands" data-manual-bands></div><p data-manual-score aria-live="polite"></p></div>
<div><label for="manual-note">Note</label><textarea id="manual-note" name="note" rows="3" maxlength="2000">{{ old('note') }}</textarea></div>
<div class="op-actions"><button class="ti-btn" name="action" value="draft">Save Draft</button><button class="ti-btn ti-btn-primary" name="action" value="submit" {{ $metrics->isEmpty() ? 'disabled' : '' }}>Submit for Approval</button></div>
</form></section>
<section class="op-section"><header class="op-section-head"><h2>{{ $canReview ? 'Pending Approval' : 'My Pending Submissions' }}</h2><span class="op-pill op-pending">{{ $pendingSubmissions->count() }} awaiting</span></header>
<table><thead><tr><th>Employee</th><th>KPI</th><th>Period</th><th>Value</th><th>Submitted by</th><th>Action</th></tr></thead><tbody>
@forelse($pendingSubmissions as $submission)
<tr><td>{{ $submission->user?->name ?? '-' }}</td><td>{{ $submission->metric?->name ?? '-' }}<small>Weight {{ $submission->metric?->weightage ?? '-' }}</small>@if($submission->note)<details><summary>Note</summary>{{ $submission->note }}</details>@endif</td>
<td>{{ \Carbon\Carbon::create($submission->year,$submission->month,1)->format('M Y') }}</td><td>{{ $submission->value }}</td><td>{{ $submission->submitter?->name ?? '-' }}<small>{{ $submission->submitted_at?->format('d M Y, H:i') }}</small></td><td>
@if($canReview)<div class="op-actions op-review-actions"><form method="POST" action="{{ route('admin.hr.operations-manual-kpi.approve',$submission) }}">@csrf<button class="ti-btn op-approve">Approve</button></form><button type="button" class="ti-btn op-reject" data-reject-url="{{ route('admin.hr.operations-manual-kpi.reject',$submission) }}" data-reject-person="{{ $submission->user?->name }}">Reject</button></div>
@else<span class="op-pill op-pending">Pending</span>@endif</td></tr>
@empty<tr><td colspan="6" class="op-empty"><i class="ri-check-line" aria-hidden="true"></i><p>No submissions awaiting approval.</p></td></tr>@endforelse
</tbody></table></section></div>
<section class="op-section"><h2>Manual KPI History</h2>
<form method="GET" class="kt-filter op-history-filter">
<input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="month" value="{{ $month }}">
<div><label for="history-year">Year</label><input id="history-year" type="number" name="history_year" min="2025" max="2100" value="{{ request('history_year') }}"></div>
<div><label for="history-month">Month</label><select id="history-month" name="history_month"><option value="">All months</option>@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ (int)request('history_month') === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000,$m,1)->format('F') }}</option>@endfor</select></div>
@if($canReview)<div><label for="history-user">Employee</label><select id="history-user" name="history_user_id"><option value="">All employees</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ request('history_user_id') === $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>@endif
<button class="ti-btn ti-btn-primary">Filter</button><a class="ti-btn" href="{{ route('admin.hr.operations-manual-kpi.index',['year'=>$year,'month'=>$month]) }}" title="Reset history filters" aria-label="Reset history filters"><i class="ri-refresh-line"></i></a>
</form>
<table><thead><tr><th>Status</th><th>Employee</th><th>KPI</th><th>Period</th><th>Value</th><th>Submitted by</th><th>Reviewed by</th><th>Updated</th></tr></thead><tbody>
@forelse($historySubmissions as $submission)
<tr><td><span class="op-pill op-{{ $submission->status }}">{{ ucfirst($submission->status) }}</span></td><td>{{ $submission->user?->name ?? '-' }}</td><td>{{ $submission->metric?->name ?? '-' }}@if($submission->note || $submission->review_note)<details><summary>Notes</summary>@if($submission->note)<p>{{ $submission->note }}</p>@endif @if($submission->review_note)<p>{{ $submission->review_note }}</p>@endif</details>@endif</td>
<td>{{ \Carbon\Carbon::create($submission->year,$submission->month,1)->format('M Y') }}</td><td>{{ $submission->value }}</td><td>{{ $submission->submitter?->name ?? '-' }}</td><td>{{ ($submission->status === 'rejected' ? $submission->rejecter?->name : $submission->approver?->name) ?? '-' }}</td><td>{{ $submission->updated_at?->format('d M Y, H:i') }}</td></tr>
@empty<tr><td colspan="8" class="op-empty">No history for the selected filters.</td></tr>@endforelse
</tbody></table><small>Latest {{ $historySubmissions->count() }} records (maximum 75).</small></section>
@if($canReview)<dialog class="op-dialog" data-reject-dialog aria-labelledby="reject-title">
<header class="op-section-head"><h2 id="reject-title">Reject submission</h2><button type="button" class="ti-btn" data-close-reject aria-label="Close"><i class="ri-close-line"></i></button></header>
<p data-reject-description></p><form method="POST" data-reject-form>@csrf<label for="reject-reason">Reason</label><textarea id="reject-reason" name="review_note" required maxlength="2000" rows="4"></textarea><div class="op-actions"><button type="button" class="ti-btn" data-close-reject>Cancel</button><button class="ti-btn op-reject">Reject</button></div></form></dialog>@endif
@include('admin.pages.kpi.partials.tool-mobile-nav')
</div>
@endsection
@push('scripts')
<script src="{{ asset('assets/admin/js/crm/kpi-tools.js') }}?v=1"></script>
<script src="{{ asset('assets/admin/js/crm/operations-manual-kpi.js') }}?v=1"></script>
@endpush
