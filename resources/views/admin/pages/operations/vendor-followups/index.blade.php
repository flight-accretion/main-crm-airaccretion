@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $counts = $counts ?? [];
        $bucket = $filters['bucket'] ?? 'open';
    @endphp

    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 text-[1.125rem] font-semibold">Vendor Follow-ups</h3>
            <p class="text-[0.75rem] text-gray-500 mt-1">Operations-only vendor confirmation tasks linked to leads.</p>
        </div>
        <div class="flex gap-2 mt-3 md:mt-0">
            <a href="{{ route('admin.operations.vendor-followups.export', request()->query()) }}" class="ti-btn ti-btn-light">Export</a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-12 gap-4 mb-4">
        @foreach ([
            'open' => 'Open',
            'overdue' => 'Overdue',
            'today' => 'Today',
            'upcoming' => 'Upcoming',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'all' => 'All',
        ] as $key => $label)
            <div class="xl:col-span-2 md:col-span-3 col-span-6">
                <a href="{{ route('admin.operations.vendor-followups.index', array_merge(request()->except('page'), ['bucket' => $key])) }}" class="box custom-box block {{ $bucket === $key ? 'border border-primary' : '' }}">
                    <div class="box-body py-3">
                        <div class="text-xs text-gray-500">{{ $label }}</div>
                        <div class="text-xl font-semibold">{{ $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0) }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="box custom-box">
        <div class="box-body">
            <form method="GET" action="{{ route('admin.operations.vendor-followups.index') }}" class="grid grid-cols-12 gap-4">
                <input type="hidden" name="bucket" value="{{ $bucket }}">
                <div class="md:col-span-5 col-span-12">
                    <label class="ti-form-label">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="ti-form-input" placeholder="Client, vendor, phone, lead id">
                </div>
                <div class="md:col-span-3 col-span-12">
                    <label class="ti-form-label">Per Page</label>
                    <select name="per_page" class="ti-form-select">
                        @foreach ([10, 25, 50, 100] as $size)
                            <option value="{{ $size }}" @selected(($filters['per_page'] ?? 25) == $size)>{{ $size }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-4 col-span-12 flex items-end gap-2">
                    <button class="ti-btn ti-btn-primary" type="submit">Apply Filters</button>
                    <a class="ti-btn ti-btn-light" href="{{ route('admin.operations.vendor-followups.index') }}">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="box custom-box">
        <div class="box-header"><div class="box-title">Vendor Follow-up List</div></div>
        <div class="box-body">
            <div class="table-responsive">
                <table class="table whitespace-nowrap min-w-full">
                    <thead>
                        <tr>
                            <th>S.No</th>
                            <th>Client</th>
                            <th>Vendor</th>
                            <th>Purpose</th>
                            <th>Follow-up</th>
                            <th>Status</th>
                            <th>Assigned</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cases as $case)
                            <tr>
                                <td>{{ ($cases->firstItem() ?: 1) + $loop->index }}</td>
                                <td>
                                    <div>{{ optional(optional($case->lead)->client)->name ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ $case->lead_id }}</div>
                                </td>
                                <td>{{ data_get($case->metadata, 'vendor_name_snapshot', 'N/A') }}</td>
                                <td>{{ data_get($case->metadata, 'purpose', 'N/A') }}</td>
                                <td>{{ optional($case->next_followup_at)->format('d-m-Y') ?? '-' }}</td>
                                <td>
                                    @php($resolution = data_get($case->metadata, 'resolution'))
                                    {{ $resolution ? ucfirst($resolution) : ucfirst(str_replace('_', ' ', $case->status)) }}
                                </td>
                                <td>{{ optional($case->assignee)->name ?? 'N/A' }}</td>
                                <td>
                                    <a href="{{ route('admin.operations.vendor-followups.show', $case) }}" class="ti-btn ti-btn-sm ti-btn-primary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-gray-500">No Vendor Follow-up records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $cases->links() }}</div>
        </div>
    </div>
@endsection