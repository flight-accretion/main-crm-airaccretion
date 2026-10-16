@extends('admin.layouts.header')

@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/attendance/settings.css') }}?v=1">
@php
    $calendar = $calendar ?? [];
    $calendarReady = !empty($calendar);
    $calendarYear = (int) ($calendar['year'] ?? now()->year);
    $calendarMonth = (int) ($calendar['month'] ?? now()->month);
    $calendarStart = \Carbon\Carbon::create($calendarYear, $calendarMonth, 1)->startOfDay();
    $selectedCalendarUser = $calendar['selectedUser'] ?? null;
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    $assignedEmployees = $users->filter(fn ($employee) => $currentAssignments->has($employee->id))->count();
    $defaultPolicy = $policies->first(fn ($policy) => $policy->is_active && $policy->is_default);
    $onDefault = $defaultPolicy ? $users->filter(function ($employee) use ($currentAssignments, $defaultPolicy) {
        $assignment = $currentAssignments->get($employee->id);
        return !$assignment || (string) optional($assignment->shiftPolicy)->id === (string) $defaultPolicy->id;
    })->count() : 0;
    $formatDate = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('d M Y') : '-';
    $workingDates = collect(data_get($calendar, 'summary.dates', []))->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString())->flip();
@endphp
<div class="attendance-settings" data-attendance-settings>
    <header class="as-heading">
        <div><p class="as-eyebrow">Attendance</p><h1>Attendance Settings</h1></div>
        <a class="as-button as-secondary" href="#as-new-policy"><i class="bx bx-plus" aria-hidden="true"></i> New policy</a>
    </header>
    @if(session('success'))
        <div class="as-notice as-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="as-notice as-error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <dl class="as-stats">
        <div><dt>Active policies</dt><dd>{{ $policies->where('is_active', true)->count() }}</dd></div>
        <div><dt>Employees assigned</dt><dd>{{ $assignedEmployees }}</dd></div>
        <div><dt>On default</dt><dd>{{ $onDefault }}</dd></div>
    </dl>
    <section class="as-section" aria-labelledby="as-policies-heading">
        <div class="as-section-heading"><h2 id="as-policies-heading">Office time policies</h2><span class="as-tag">{{ $policies->count() }} total</span></div>
        <div class="as-policy-list">
            @forelse($policies as $policy)
                <form method="POST" action="{{ route('admin.attendance.settings.policies.update', $policy) }}" class="as-policy-form">
                    @csrf
                    @method('PUT')
                    <div class="as-policy-fields">
                        <label>Policy name<input name="name" value="{{ $policy->name }}" required></label>
                        <label>In time<input type="time" name="start_time" value="{{ substr((string) $policy->start_time, 0, 5) }}" required></label>
                        <label>Out time<input type="time" name="end_time" value="{{ $policy->end_time ? substr((string) $policy->end_time, 0, 5) : '' }}"></label>
                        <label>Grace (minutes)<input type="number" name="grace_minutes" min="0" max="240" value="{{ $policy->grace_minutes }}" required></label>
                    </div>
                    <div class="as-form-footer">
                        <label class="as-check"><input type="checkbox" name="is_default" value="1" {{ $policy->is_default ? 'checked' : '' }}> Default</label>
                        <label class="as-check"><input type="checkbox" name="is_active" value="1" {{ $policy->is_active ? 'checked' : '' }}> Active</label>
                        <span class="as-tag">{{ $policy->assignments_count }} assignments</span>
                        <button class="as-button as-secondary as-push" type="submit"><i class="bx bx-save" aria-hidden="true"></i> Update policy</button>
                    </div>
                </form>
            @empty
                <p class="as-empty">No office time policies yet.</p>
            @endforelse
        </div>
        <details id="as-new-policy" class="as-details" {{ $policies->isEmpty() ? 'open' : '' }}>
            <summary>New policy</summary>
            <form method="POST" action="{{ route('admin.attendance.settings.policies.store') }}">
                @csrf
                <div class="as-policy-fields">
                    <label>Policy name<input name="name" required></label>
                    <label>In time<input type="time" name="start_time" required></label>
                    <label>Out time<input type="time" name="end_time"></label>
                    <label>Grace (minutes)<input type="number" name="grace_minutes" min="0" max="240" value="15" required></label>
                </div>
                <div class="as-form-footer">
                    <label class="as-check"><input type="checkbox" name="is_default" value="1"> Default</label>
                    <label class="as-check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                    <button class="as-button as-push" type="submit"><i class="bx bx-plus" aria-hidden="true"></i> Save policy</button>
                </div>
            </form>
        </details>
    </section>
    <section class="as-section" aria-labelledby="as-assignments-heading">
        <div class="as-section-heading"><h2 id="as-assignments-heading">Employee office time assignments</h2><output class="as-tag" data-selection-count aria-live="polite">0 selected</output></div>
        <form method="POST" action="{{ route('admin.attendance.settings.assignments.store') }}" data-assignments-form>
            @csrf
            <div class="as-toolbar">
                <label>Office time<select name="shift_policy_id" required><option value="">Select office time</option>@foreach($policies->where('is_active', true) as $policy)<option value="{{ $policy->id }}">{{ $policy->name }} ({{ substr((string) $policy->start_time, 0, 5) }} + {{ $policy->grace_minutes }} min)</option>@endforeach</select></label>
                <label>Search employees<input type="search" data-attendance-user-search placeholder="Name or role"></label>
                <button class="as-button" type="submit"><i class="bx bx-user-check" aria-hidden="true"></i> Assign selected</button>
            </div>
            <div class="as-employee-list">
                <table class="as-table"><thead><tr><th scope="col"><input type="checkbox" data-select-visible aria-label="Select visible employees"></th><th scope="col">Employee</th><th scope="col">Role</th><th scope="col">Current office time</th></tr></thead><tbody>
                @forelse($users as $user)
                    @php
                        $current = $currentAssignments->get($user->id);
                    @endphp
                    <tr data-attendance-user-option data-attendance-user-name="{{ strtolower($user->name.' '.optional($user->userType)->user_type) }}">
                        <td data-label="Select"><input type="checkbox" name="user_ids[]" value="{{ $user->id }}" aria-label="Select {{ $user->name }}"></td>
                        <td data-label="Employee" class="as-person">{{ $user->name }}</td>
                        <td data-label="Role">{{ optional($user->userType)->user_type ?: '-' }}</td>
                        <td data-label="Current office time">{{ optional(optional($current)->shiftPolicy)->name ?: 'No custom assignment' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="as-empty">No employees available.</td></tr>
                @endforelse
                </tbody></table>
                <p class="as-empty" data-search-empty hidden>No employees match your search.</p>
            </div>
        </form>
    </section>
    @if($calendarReady)
    <section class="as-section" aria-labelledby="as-calendar-heading">
        <div class="as-section-heading"><h2 id="as-calendar-heading">Monthly attendance calendar</h2><span class="as-tag">{{ $calendarStart->format('F Y') }}</span></div>
        <form method="GET" action="{{ route('admin.attendance.settings.index') }}" class="as-toolbar">
            <label>Year<input type="number" name="year" min="2000" max="2100" value="{{ $calendarYear }}" required></label>
            <label>Month<select name="month">@for($month = 1; $month <= 12; $month++)<option value="{{ $month }}" {{ $month === $calendarMonth ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $month, 1)->format('F') }}</option>@endfor</select></label>
            <label>Employee<select name="calendar_user_id"><option value="">{{ $users->isEmpty() ? 'No employees available' : 'Select employee' }}</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ (string) optional($selectedCalendarUser)->id === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
            <button class="as-button as-secondary" type="submit"><i class="bx bx-filter-alt" aria-hidden="true"></i> Apply</button>
        </form>
        <dl class="as-calendar-totals">
            @foreach(['month_days' => 'Month days', 'working_days' => 'Working days', 'week_off_days' => 'Week-off days', 'holiday_days' => 'Holiday days', 'overlap_days' => 'Overlapping days'] as $key => $label)
                <div><dt>{{ $label }}</dt><dd>{{ data_get($calendar, 'summary.'.$key, '-') }}</dd></div>
            @endforeach
        </dl>
        @if(!$selectedCalendarUser)<p class="as-empty">No employee calendar available.</p>@endif
        <div class="as-calendar-legend"><span class="as-tag as-working">Working</span><span class="as-tag as-off">Off</span><span class="as-tag as-holiday">Holiday</span><span class="as-tag as-swapped">Swap</span></div>
        <div class="as-calendar-grid" aria-label="{{ $calendarStart->format('F Y') }} attendance dates">
            @foreach($weekdays as $weekday)<div class="as-weekday">{{ $weekday }}</div>@endforeach
            @for($blank = 0; $blank < $calendarStart->dayOfWeek; $blank++)<div class="as-calendar-blank" aria-hidden="true"></div>@endfor
            @foreach(\Carbon\CarbonPeriod::create($calendarStart, $calendarStart->copy()->endOfMonth()) as $day)
                @php
                    $dayDate = $day->toDateString();
                    $isWorking = $selectedCalendarUser && $workingDates->has($dayDate);
                    $isOff = $selectedCalendarUser && !$isWorking;
                    $isHoliday = in_array($dayDate, $calendar['holidays'] ?? [], true);
                    $isSwap = collect($calendar['swaps'] ?? [])->contains(fn ($swap) => data_get($swap, 'worked_date') === $dayDate || data_get($swap, 'off_date') === $dayDate);
                @endphp
                @if($dayDate)
                    <div class="as-calendar-day {{ $isWorking ? 'as-working' : '' }} {{ $isOff ? 'as-off' : '' }} {{ $isHoliday ? 'as-holiday' : '' }} {{ $isSwap ? 'as-swapped' : '' }}">
                        <time datetime="{{ \Carbon\Carbon::parse($dayDate)->toDateString() }}">{{ \Carbon\Carbon::parse($dayDate)->day }}</time>
                        @if($isWorking)<span>Working</span>@endif
                        @if($isOff)<span>Off</span>@endif
                        @if($isHoliday)<span>Holiday</span>@endif
                        @if($isSwap)<span>Swap</span>@endif
                    </div>
                @endif
            @endforeach
        </div>
    </section>
    @endif
    @if($calendarReady && ($canManageCalendar ?? false))
    <section class="as-section" aria-labelledby="as-weekoffs-heading">
        <div class="as-section-heading"><h2 id="as-weekoffs-heading">Employee week-offs</h2></div>
        <p class="as-rule">Sunday is the default weekly off until an effective schedule applies. No days selected means no weekly off. Saving the same effective date updates that schedule and records the change.</p>
        <form method="POST" action="{{ route('admin.attendance.calendar.weekoffs') }}" data-weekoffs-form>
            @csrf
            <div class="as-toolbar">
                <label>Employee<select name="user_id" required><option value="">Select employee</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ (string) optional($selectedCalendarUser)->id === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
                <label>Effective from<input type="date" name="effective_from" value="{{ $calendarStart->toDateString() }}" required></label>
            </div>
            <fieldset class="as-weekdays"><legend>Weekly off days (none selected means no week-off)</legend>@foreach($weekdays as $number => $weekday)<label class="as-check"><input type="checkbox" name="weekdays[]" value="{{ $number }}">{{ $weekday }}</label>@endforeach</fieldset>
            <div class="as-form-footer"><button class="as-button" type="submit"><i class="bx bx-save" aria-hidden="true"></i> Save week-offs</button></div>
        </form>
        <table class="as-table"><thead><tr><th scope="col">Employee</th><th scope="col">Off days</th><th scope="col">Effective from</th><th scope="col">Action</th></tr></thead><tbody>
        @forelse(($calendar['schedules'] ?? []) as $schedule)
            <tr><td data-label="Employee">{{ optional($users->firstWhere('id', data_get($schedule, 'user_id')))->name ?: 'User #'.data_get($schedule, 'user_id') }}</td><td data-label="Off days">{{ collect(data_get($schedule, 'weekdays', []))->map(fn ($day) => $weekdays[(int) $day] ?? $day)->implode(', ') ?: 'None' }}</td><td data-label="Effective from">{{ $formatDate(data_get($schedule, 'effective_from')) }}</td><td data-label="Action"><button type="button" class="as-icon-button" data-edit-schedule data-user="{{ data_get($schedule, 'user_id') }}" data-effective="{{ data_get($schedule, 'effective_from') }}" data-weekdays="{{ json_encode(data_get($schedule, 'weekdays', [])) }}" aria-label="Edit schedule effective {{ $formatDate(data_get($schedule, 'effective_from')) }}" title="Edit schedule"><i class="bx bx-edit" aria-hidden="true"></i></button></td></tr>
        @empty
            <tr><td colspan="4" class="as-empty">No effective schedules. Sunday is the default weekly off.</td></tr>
        @endforelse
        </tbody></table>
    </section>
    <section class="as-section" aria-labelledby="as-holidays-heading">
        <div class="as-section-heading"><h2 id="as-holidays-heading">Company holidays</h2><span class="as-tag">{{ $calendarStart->format('F Y') }}</span></div>
        <form method="POST" action="{{ route('admin.attendance.calendar.holidays') }}" data-holidays-form>
            @csrf
            <input type="hidden" name="year" value="{{ $calendarYear }}"><input type="hidden" name="month" value="{{ $calendarMonth }}">
            <fieldset class="as-modes"><legend>Holiday mode</legend>
                <label class="as-check"><input type="radio" name="mode" value="additional" {{ ($calendar['holiday_mode'] ?? 'additional') === 'additional' ? 'checked' : '' }}> Additional: company holidays excluding normal employee offs</label>
                <label class="as-check"><input type="radio" name="mode" value="inclusive" {{ ($calendar['holiday_mode'] ?? '') === 'inclusive' ? 'checked' : '' }}> Inclusive: total-off list plus employee-specific week-offs, merged once</label>
            </fieldset>
            <div class="as-holiday-dates" data-holiday-dates>
                @foreach(($calendar['holidays'] ?? []) as $holiday)
                    <div class="as-date-row"><label>Holiday date<input type="date" name="dates[]" min="{{ $calendarStart->toDateString() }}" max="{{ $calendarStart->copy()->endOfMonth()->toDateString() }}" value="{{ $holiday }}" required></label><button class="as-icon-button" type="button" data-remove-date aria-label="Remove holiday date" title="Remove holiday date"><i class="bx bx-trash" aria-hidden="true"></i></button></div>
                @endforeach
            </div>
            <template data-holiday-template><div class="as-date-row"><label>Holiday date<input type="date" name="dates[]" min="{{ $calendarStart->toDateString() }}" max="{{ $calendarStart->copy()->endOfMonth()->toDateString() }}" required></label><button class="as-icon-button" type="button" data-remove-date aria-label="Remove holiday date" title="Remove holiday date"><i class="bx bx-trash" aria-hidden="true"></i></button></div></template>
            <output class="as-date-count" data-date-count aria-live="polite">{{ count($calendar['holidays'] ?? []) }} selected dates</output>
            <div class="as-form-footer"><button class="as-button as-secondary" type="button" data-add-date><i class="bx bx-plus" aria-hidden="true"></i> Add date</button><button class="as-button as-push" type="submit"><i class="bx bx-save" aria-hidden="true"></i> Save holidays</button></div>
        </form>
    </section>
    <section class="as-section" aria-labelledby="as-swaps-heading">
        <div class="as-section-heading"><h2 id="as-swaps-heading">HR-approved off-day swaps</h2></div>
        <form method="POST" action="{{ route('admin.attendance.calendar.swaps') }}">
            @csrf
            <div class="as-policy-fields">
                <label>Employee<select name="user_id" required><option value="">Select employee</option>@foreach($users as $user)<option value="{{ $user->id }}" {{ (string) optional($selectedCalendarUser)->id === (string) $user->id ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></label>
                <label>Worked date<input type="date" name="worked_date" required></label>
                <label>Off date<input type="date" name="off_date" required></label>
                <label>Note<input name="note" maxlength="500"></label>
            </div>
            <div class="as-form-footer"><button class="as-button" type="submit"><i class="bx bx-check" aria-hidden="true"></i> Approve swap</button></div>
        </form>
        <table class="as-table"><thead><tr><th scope="col">Employee</th><th scope="col">Worked date</th><th scope="col">Off date</th><th scope="col">Approved by</th><th scope="col">Action</th></tr></thead><tbody>
            @forelse(($calendar['swaps'] ?? []) as $swap)
                <tr><td data-label="Employee">{{ optional($users->firstWhere('id', data_get($swap, 'user_id')))->name ?: 'User #'.data_get($swap, 'user_id') }}</td><td data-label="Worked date">{{ $formatDate(data_get($swap, 'worked_date')) }}</td><td data-label="Off date">{{ $formatDate(data_get($swap, 'off_date')) }}</td><td data-label="Approved by">{{ optional($users->firstWhere('id', data_get($swap, 'approved_by')))->name ?: 'User #'.data_get($swap, 'approved_by') }}</td><td data-label="Action"><form method="POST" action="{{ route('admin.attendance.calendar.swaps.destroy', data_get($swap, 'id')) }}" data-confirm-swap>@csrf @method('DELETE')<button class="as-button as-secondary" type="submit"><i class="bx bx-undo" aria-hidden="true"></i> Undo</button></form></td></tr>
            @empty
                <tr><td colspan="5" class="as-empty">No approved swaps for this calendar.</td></tr>
            @endforelse
        </tbody></table>
    </section>
    @if(collect($calendar['audit'] ?? [])->isNotEmpty())
    <section class="as-section" aria-labelledby="as-audit-heading">
        <div class="as-section-heading"><h2 id="as-audit-heading">Calendar audit</h2></div>
        <table class="as-table"><thead><tr><th scope="col">Action</th><th scope="col">Employee</th><th scope="col">Changed by</th><th scope="col">Date</th></tr></thead><tbody>
            @foreach($calendar['audit'] as $entry)
                <tr><td data-label="Action">{{ ucfirst(str_replace('_', ' ', data_get($entry, 'action', '-'))) }}</td><td data-label="Employee">{{ data_get($entry, 'employee_name') ?: (data_get($entry, 'user_id') ? 'User #'.data_get($entry, 'user_id') : '-') }}</td><td data-label="Changed by">{{ data_get($entry, 'actor_name') ?: (data_get($entry, 'actor_id') ? 'User #'.data_get($entry, 'actor_id') : '-') }}</td><td data-label="Date">{{ data_get($entry, 'created_at') ? \Carbon\Carbon::parse(data_get($entry, 'created_at'))->format('d M Y h:i A') : '-' }}</td></tr>
            @endforeach
        </tbody></table>
    </section>
    @endif
    @endif
    <section class="as-section" aria-labelledby="as-history-heading">
        <div class="as-section-heading"><h2 id="as-history-heading">Recent assignment history</h2></div>
        <table class="as-table"><thead><tr><th scope="col">Employee</th><th scope="col">Office time</th><th scope="col">Status</th><th scope="col">Assigned at</th><th scope="col">Unassigned at</th><th scope="col">Changed by</th></tr></thead><tbody>
        @forelse($assignments as $assignment)
            <tr>
                <td data-label="Employee" class="as-person">{{ optional($assignment->user)->name ?: '-' }}</td>
                <td data-label="Office time">
                    @if(optional($assignment->shiftPolicy)->start_time)
                        {{ substr((string) $assignment->shiftPolicy->start_time, 0, 5) }} - {{ $assignment->shiftPolicy->end_time ? substr((string) $assignment->shiftPolicy->end_time, 0, 5) : 'Not set' }}
                    @else - @endif
                </td>
                <td data-label="Status"><span class="as-tag {{ $assignment->is_active ? 'as-working' : '' }}">{{ $assignment->is_active ? 'Current' : 'Previous' }}</span></td>
                <td data-label="Assigned at">{{ optional($assignment->assigned_at ?: $assignment->effective_from)->format('d M Y h:i A') }}</td>
                <td data-label="Unassigned at">{{ $assignment->unassigned_at ? $assignment->unassigned_at->format('d M Y h:i A') : 'Open' }}</td>
                <td data-label="Changed by">{{ optional($assignment->assignedBy)->name ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="as-empty">No assignment history.</td></tr>
        @endforelse
        </tbody></table>
    </section>
    <nav class="as-mobile-nav" aria-label="Mobile navigation" data-mobile-navigation hidden></nav>
    <dialog class="as-more-dialog" data-more-dialog aria-label="More navigation"><div class="as-dialog-heading"><h2>More</h2><button class="as-icon-button" type="button" data-close-more aria-label="Close navigation" title="Close navigation"><i class="bx bx-x" aria-hidden="true"></i></button></div><div data-more-links></div></dialog>
</div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin/js/crm/attendance-settings.js') }}?v=1" defer></script>
@endpush
