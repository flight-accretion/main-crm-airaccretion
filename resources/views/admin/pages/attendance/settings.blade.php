@extends('admin.layouts.header')

@section('content')

<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="!text-defaulttextcolor text-[1.125rem] font-semibold">
            Attendance Settings
        </h3>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
        {{ $errors->first() }}
    </div>
@endif

<div class="box">
    <div class="box-header">
        <h5 class="box-title">Office Time Policies</h5>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.attendance.settings.policies.store') }}" class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
            @csrf

            <input class="form-control" name="name" placeholder="Policy name" required>
            <input class="form-control" type="time" name="start_time" required>
            <input class="form-control" type="time" name="end_time">
            <input class="form-control" type="number" name="grace_minutes" min="0" max="240" value="15" placeholder="Grace Minutes" title="Grace Minutes" required>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_default" value="1">
                Default
            </label>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" checked>
                Active
            </label>

            <button class="ti-btn ti-btn-primary-full ti-btn-wave" type="submit">
                Save Policy
            </button>
        </form>

        <div class="overflow-x-auto">
            <table class="table whitespace-nowrap min-w-full">
                <thead>
                    <tr>
                        <th>Office Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($policies as $policy)
                        <tr>
                            <td>
                                <form
                                    method="POST"
                                    action="{{ route('admin.attendance.settings.policies.update', $policy) }}"
                                    class="grid grid-cols-1 md:grid-cols-8 gap-3 items-center"
                                >
                                    @csrf
                                    @method('PUT')

                                    <input class="form-control" name="name" value="{{ $policy->name }}" required>
                                    <input class="form-control" type="time" name="start_time" value="{{ substr((string) $policy->start_time, 0, 5) }}" required>
                                    <input class="form-control" type="time" name="end_time" value="{{ $policy->end_time ? substr((string) $policy->end_time, 0, 5) : '' }}">
                                    <input class="form-control" type="number" name="grace_minutes" min="0" max="240" value="{{ $policy->grace_minutes }}" placeholder="Grace Minutes" title="Grace Minutes" required>

                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_default" value="1" {{ $policy->is_default ? 'checked' : '' }}>
                                        Default
                                    </label>

                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="is_active" value="1" {{ $policy->is_active ? 'checked' : '' }}>
                                        Active
                                    </label>

                                    <span class="text-sm text-gray-500">
                                        {{ $policy->assignments_count }} assignment{{ $policy->assignments_count === 1 ? '' : 's' }}
                                    </span>

                                    <button class="ti-btn ti-btn-light ti-btn-wave" type="submit">
                                        Update
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">Employee Office Time Assignments</h5>
    </div>
    <div class="box-body">
        <form method="POST" action="{{ route('admin.attendance.settings.assignments.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <select class="form-control" name="shift_policy_id" required>
                    <option value="">Select office time</option>
                    @foreach($policies->where('is_active', true) as $policy)
                        <option value="{{ $policy->id }}">
                            {{ $policy->name }} ({{ substr((string) $policy->start_time, 0, 5) }} + {{ $policy->grace_minutes }} min)
                        </option>
                    @endforeach
                </select>

                <input class="form-control" data-attendance-user-search placeholder="Search employees">

                <button class="ti-btn ti-btn-primary-full ti-btn-wave" type="submit">
                    Assign Selected
                </button>
            </div>

            <div class="overflow-x-auto max-h-[420px]">
                <table class="table whitespace-nowrap min-w-full">
                    <thead>
                        <tr>
                            <th>Select</th>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Current Office Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                            @php($current = $currentAssignments->get($user->id))
                            <tr data-attendance-user-option data-attendance-user-name="{{ strtolower($user->name) }}">
                                <td>
                                    <input type="checkbox" name="user_ids[]" value="{{ $user->id }}">
                                </td>
                                <td>{{ $user->name }}</td>
                                <td>{{ optional($user->userType)->user_type ?: '-' }}</td>
                                <td>{{ optional(optional($current)->shiftPolicy)->name ?: '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    </div>
</div>

<div class="box mt-6">
    <div class="box-header">
        <h5 class="box-title">Recent Assignment History</h5>
    </div>
    <div class="box-body overflow-x-auto">
        <table class="table whitespace-nowrap min-w-full">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Office Time</th>
                    <th>Status</th>
                    <th>Assigned At</th>
                    <th>Unassigned At</th>
                    <th>Changed By</th>
                </tr>
            </thead>
            <tbody>
                @foreach($assignments as $assignment)
                    <tr>
                        <td>{{ optional($assignment->user)->name ?: '-' }}</td>
                        <td>{{ optional($assignment->shiftPolicy)->name ?: '-' }}</td>
                        <td>
                            {{ $assignment->is_active ? 'Current' : 'Previous' }}
                        </td>
                        <td>{{ optional($assignment->assigned_at ?: $assignment->effective_from)->format('d M Y h:i A') }}</td>
                        <td>{{ $assignment->unassigned_at ? $assignment->unassigned_at->format('d M Y h:i A') : 'Open' }}</td>
                        <td>{{ optional($assignment->assignedBy)->name ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const search = document.querySelector('[data-attendance-user-search]');
    const rows = Array.from(document.querySelectorAll('[data-attendance-user-option]'));

    if (!search) {
        return;
    }

    search.addEventListener('input', function () {
        const value = search.value.trim().toLowerCase();

        rows.forEach(function (row) {
            const name = row.getAttribute('data-attendance-user-name') || '';

            row.classList.toggle(
                'hidden',
                value !== '' && name.indexOf(value) === -1
            );
        });
    });
});
</script>
@endpush
