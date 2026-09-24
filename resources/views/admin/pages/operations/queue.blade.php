@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseStatuses = $caseStatuses ?? [];
    @endphp

    <div class="container-fluid">
        <div class="mb-6">
            <h4 class="font-semibold text-lg">{{ ucfirst($type) }} Queue</h4>
            <p class="text-sm text-gray-500">
                Completing this queue item only closes the Operations case.
            </p>
        </div>

        <div class="box mb-4">
            <div class="box-body">
                <form
                    method="GET"
                    action="{{ route('admin.operations.queue', $type) }}"
                    class="grid grid-cols-12 gap-3 items-end"
                >
                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">From Date</label>
                        <input
                            type="date"
                            name="from_date"
                            value="{{ $filters['from_date'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">To Date</label>
                        <input
                            type="date"
                            name="to_date"
                            value="{{ $filters['to_date'] ?? '' }}"
                            class="form-control"
                        >
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">All Status</option>
                            @foreach($caseStatuses as $status)
                                <option
                                    value="{{ $status }}"
                                    {{ ($filters['status'] ?? '') === $status ? 'selected' : '' }}
                                >
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">Operations User</label>
                        <select name="operations_user_id" class="form-control">
                            <option value="">All Users</option>
                            @foreach($operationUsers as $user)
                                <option
                                    value="{{ $user->id }}"
                                    {{ ($filters['operations_user_id'] ?? '') === $user->id ? 'selected' : '' }}
                                >
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">Search</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ $filters['search'] ?? '' }}"
                            class="form-control"
                            placeholder="Customer, lead, note"
                        >
                    </div>

                    <div class="xl:col-span-1 md:col-span-2 col-span-6">
                        <label class="ti-form-label">Rows</label>
                        <select name="per_page" class="form-control">
                            @foreach([10, 25, 50, 100] as $size)
                                <option
                                    value="{{ $size }}"
                                    {{ (int) ($filters['per_page'] ?? 25) === $size ? 'selected' : '' }}
                                >
                                    {{ $size }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="xl:col-span-1 md:col-span-2 col-span-6 flex gap-2">
                        <button class="ti-btn ti-btn-primary w-full">Filter</button>
                        <a
                            href="{{ route('admin.operations.queue', $type) }}"
                            class="ti-btn ti-btn-light"
                            title="Reset"
                        >
                            <i class="ri-refresh-line"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="box">
            <div class="box-body">
                <div class="table-responsive">
                    <table
                        id="operations-queue-table"
                        class="table display nowrap operations-queue-datatable whitespace-nowrap min-w-full"
                        width="100%"
                    >
                        <thead>
                            <tr>
                                <th>Opened</th>
                                <th>Customer</th>
                                <th>Lead</th>
                                <th>Sales Executive</th>
                                <th>Operations Status</th>
                                <th>Operations User</th>
                                <th>Summary</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                <tr>
                                    <td>{{ optional($case->opened_at)->format('d M Y h:i A') }}</td>
                                    <td>{{ optional(optional($case->lead)->client)->name ?? '-' }}</td>
                                    <td>
                                        @if($case->lead_id)
                                            <a href="{{ route('admin.leads.view', $case->lead_id) }}" class="text-primary" target="_blank">
                                                {{ $case->lead_id }}
                                            </a>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ optional(optional($case->lead)->representative)->name ?? '-' }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $case->status)) }}</td>
                                    <td>{{ optional($case->assignee)->name ?? '-' }}</td>
                                    <td>{{ data_get($case->metadata, 'ai_summary') ?: $case->note ?: '-' }}</td>
                                    <td>
                                        @if($case->status === 'pending')
                                            <form method="POST" action="{{ route('admin.operations.case.start', $case) }}" class="inline-block mb-2">
                                                @csrf
                                                <button class="ti-btn ti-btn-primary" type="submit">Start</button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('admin.operations.case.complete', $case) }}" class="flex gap-2 items-center">
                                            @csrf
                                            <input name="note" class="form-control" placeholder="Completion note">
                                            <button class="ti-btn ti-btn-success" type="submit">Complete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">No pending records.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $cases->links() }}
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#operations-queue-table');

            if ($.fn.DataTable && table.length && !$.fn.DataTable.isDataTable(table[0])) {
                if (table.find('tbody td[colspan]').length) {
                    return;
                }

                table.DataTable({
                    paging: false,
                    searching: false,
                    info: false,
                    lengthChange: false,
                    responsive: false,
                    scrollX: true,
                    autoWidth: false,
                    ordering: true,
                    columnDefs: [
                        { orderable: false, targets: -1 }
                    ]
                });
            }
        });
    </script>
@endpush
