@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseTypes = $caseTypes ?? [];
    @endphp

    <div class="container-fluid">
        <div class="mb-6">
            <h4 class="font-semibold text-lg">Operations History</h4>
        </div>

        <div class="box mb-4">
            <div class="box-body">
                <form
                    method="GET"
                    action="{{ route('admin.operations.history') }}"
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
                        <label class="ti-form-label">Type</label>
                        <select name="type" class="form-control">
                            <option value="">All Types</option>
                            @foreach($caseTypes as $caseType)
                                <option
                                    value="{{ $caseType }}"
                                    {{ ($filters['type'] ?? '') === $caseType ? 'selected' : '' }}
                                >
                                    {{ ucfirst(str_replace('_', ' ', $caseType)) }}
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
                            href="{{ route('admin.operations.history') }}"
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
                        id="operations-history-table"
                        class="table display nowrap operations-history-datatable whitespace-nowrap min-w-full"
                        width="100%"
                    >
                        <thead>
                            <tr>
                                <th>Completed</th>
                                <th>Type</th>
                                <th>Customer</th>
                                <th>Lead</th>
                                <th>Sales Executive</th>
                                <th>Operations User</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($cases as $case)
                                <tr>
                                    <td>{{ optional($case->completed_at)->format('d M Y h:i A') }}</td>
                                    <td>{{ ucfirst($case->type) }}</td>
                                    <td>{{ optional(optional($case->lead)->client)->name ?? '-' }}</td>
                                    <td>{{ $case->lead_id }}</td>
                                    <td>{{ optional(optional($case->lead)->representative)->name ?? '-' }}</td>
                                    <td>{{ optional($case->assignee)->name ?? '-' }}</td>
                                    <td>{{ $case->note ?: data_get($case->metadata, 'ai_summary') ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">No completed Operations cases.</td>
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
            const table = $('#operations-history-table');

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
                    ordering: true
                });
            }
        });
    </script>
@endpush
