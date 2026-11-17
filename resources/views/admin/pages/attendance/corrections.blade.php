@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/attendance/import.css') }}">
<div class="attendance-import">
    <header class="ai-heading"><h1>Attendance corrections</h1><a class="ti-btn" href="{{ route('admin.attendance.import.index') }}">Attendance imports</a></header>
    @if(session('success'))<p role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <section class="ai-section">
        <h2>Employee attendance</h2>
        <form method="GET" class="ai-filters">
            <label>Date<input type="date" name="date" value="{{ request('date') }}"></label>
            <label>Employee ID<input name="user_id" value="{{ request('user_id') }}"></label>
            <button class="ti-btn">Filter</button>
        </form>
        @foreach($records as $record)
            @php($closed = $periods->contains('month', $record->attendance_date->copy()->startOfMonth()->toDateString()))
            <details class="box mt-3 p-3">
                <summary>Edit Attendance · {{ $record->user?->name }} · {{ $record->attendance_date->toDateString() }} · {{ $record->raw_status }} · {{ $closed ? 'Closed payroll period' : 'Open payroll period' }}</summary>
                <form method="POST" action="{{ route('admin.attendance.corrections.store', $record) }}" class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-3">
                    @csrf
                    <input type="hidden" name="version" value="{{ $service->version($record) }}">
                    <label>Status<input class="form-control" name="raw_status" maxlength="20" value="{{ $record->raw_status }}" required></label>
                    <label>In<input class="form-control" type="time" name="in_time" value="{{ substr($record->in_time ?? '', 0, 5) }}"></label>
                    <label>Out<input class="form-control" type="time" name="out_time" value="{{ substr($record->out_time ?? '', 0, 5) }}"></label>
                    <label>Correction reason<textarea class="form-control" name="reason" maxlength="2000" required></textarea></label>
                    <button class="ti-btn ti-btn-primary">{{ $closed ? 'Submit for Approval' : 'Save Correction' }}</button>
                </form>
            </details>
        @endforeach
        {{ $records->links() }}
    </section>
    <section class="ai-section">
        <h2>Correction history and approvals</h2>
        <p>Applied corrections in closed periods require payroll-adjustment review. This page does not calculate salary adjustments.</p>
        @foreach($corrections as $correction)
            <details class="box mt-3 p-3">
                <summary>{{ $correction->attendance_date }} · {{ $correction->user_id }} · {{ ucfirst($correction->status) }} · {{ $correction->source }}</summary>
                @if(in_array($correction->id, $reviewIds, true))<p role="status">Pending payroll-adjustment review</p>@endif
                <p>Reason · {{ $correction->reason }}</p>
                <p>Requested by {{ $correction->requested_by }} at {{ $correction->created_at }}</p>
                <p>Decision by {{ $correction->decided_by ?: 'Pending' }} · {{ $correction->decided_at }} · {{ $correction->decision_reason }}</p>
                <p>Source import · {{ $correction->source_import_id ?: 'Individual action' }}</p>
                <pre class="whitespace-pre-wrap break-words">Before {{ json_encode(json_decode($correction->before_values), JSON_PRETTY_PRINT) }}
After {{ json_encode(json_decode($correction->after_values), JSON_PRETTY_PRINT) }}</pre>
                @if($correction->status === 'pending' && auth()->user()->userType?->user_type === \App\Models\UserType::SUPER_ADMIN)
                    <form method="POST" action="{{ route('admin.attendance.corrections.decide', $correction->id) }}">
                        @csrf
                        <label>Decision reason<input class="form-control" name="reason" required maxlength="2000"></label>
                        <button class="ti-btn ti-btn-primary" name="decision" value="approve">Approve</button>
                        <button class="ti-btn" name="decision" value="reject">Reject</button>
                    </form>
                @endif
                @if($correction->status === 'applied')
                    <form method="POST" action="{{ route('admin.attendance.corrections.reverse', $correction->id) }}">
                        @csrf
                        <label>Reversal reason<input class="form-control" name="reason" required maxlength="2000"></label>
                        <button class="ti-btn">Request Reversal</button>
                    </form>
                @endif
            </details>
        @endforeach
        {{ $corrections->links() }}
        <h3>Audit events</h3>
        @foreach($events as $event)
            <p>{{ $event->created_at }} · {{ $event->action }} · {{ $event->actor_id }} · {{ $event->details }}</p>
        @endforeach
    </section>
    @if(auth()->user()->userType?->user_type === \App\Models\UserType::SUPER_ADMIN)
        <section class="ai-section">
            <h2>Explicit payroll closure</h2>
            <p>Closure applies to attendance corrections for every employee in the selected calendar month.</p>
            <form method="POST" action="{{ route('admin.attendance.periods.close') }}">
                @csrf
                <label>Payroll month<input type="month" name="month" required></label>
                <label>Closure reason<input class="form-control" name="reason" maxlength="2000" required></label>
                <button class="ti-btn">Mark Period Closed</button>
            </form>
            @foreach($periods as $period)<p>{{ $period->month }} · Closed {{ $period->closed_at }} · {{ $period->closure_reason }}</p>@endforeach
        </section>
    @endif
</div>
@endsection
