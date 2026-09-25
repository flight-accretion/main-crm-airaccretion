@extends('admin.layouts.header')

@section('content')
    @php
        $filters = $filters ?? [];
        $operationUsers = $operationUsers ?? collect();
        $caseTypes = $caseTypes ?? [];
        $caseStatuses = $caseStatuses ?? [];
    @endphp

    <div class="container-fluid">
        <div class="mb-6">
            <h4 class="font-semibold text-lg">Operations Overview</h4>
            <p class="text-sm text-gray-500">
                Operations work status only. CRM lead, payment and ride status remain unchanged.
            </p>
        </div>

        <div class="grid grid-cols-12 gap-4 mb-6">
            @foreach([
                ['customer_call', 'Customer Calls', $customerCallCount],
                ['review', 'Review', $reviewCount],
                ['reschedule', 'Reschedule', $rescheduleCount],
                ['refund', 'Refund', $refundCount],
            ] as [$type, $label, $count])
                <div class="xl:col-span-3 md:col-span-6 col-span-12">
                    <a
                        href="{{ route('admin.operations.queue', $type) }}"
                        class="box block"
                        data-overview-count-type="{{ $type }}"
                        data-overview-count-value="{{ $count }}"
                    >
                        <div class="box-body">
                            <div class="text-sm text-gray-500">{{ $label }}</div>
                            <div class="text-3xl font-semibold">{{ $count }}</div>
                            <small>Pending</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="box mb-4">
            <div class="box-body">
                <form
                    method="GET"
                    action="{{ route('admin.operations.index') }}"
                    class="grid grid-cols-12 gap-3 items-end"
                >
                    <div class="xl:col-span-2 md:col-span-4 col-span-12">
                        <label class="ti-form-label">Date Filter</label>
                        <select name="date_filter" class="form-control">
                            <option value="">All Dates</option>
                            @foreach([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'monthly' => 'This Month',
                                'custom' => 'Custom Date',
                            ] as $value => $label)
                                <option
                                    value="{{ $value }}"
                                    {{ ($filters['date_filter'] ?? '') === $value ? 'selected' : '' }}
                                >
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

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
                            placeholder="Customer or action"
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
                        <button class="ti-btn ti-btn-primary w-full" style="width: auto;">Filter</button>
                        <a
                            href="{{ route('admin.operations.index') }}"
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
            <div class="box-header">
                <h5 class="box-title">Recent Activity</h5>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table
                        id="operations-activity-table"
                        class="table display nowrap operations-activity-datatable whitespace-nowrap min-w-full"
                        width="100%"
                    >
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Type</th>
                                <th>Action</th>
                                <th>User</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentActivity as $activity)
                                <tr>
                                    <td>{{ optional($activity->created_at)->format('d M Y h:i A') }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', optional($activity->operationCase)->type ?? '-')) }}</td>
                                    <td>{{ ucwords(str_replace('_', ' ', $activity->action)) }}</td>
                                    <td>{{ optional($activity->user)->name ?? 'System' }}</td>
                                    <td>
                                        {{ $activity->from_status ? ucwords(str_replace('_', ' ', $activity->from_status)) . ' -> ' : '' }}
                                        {{ ucwords(str_replace('_', ' ', $activity->to_status ?? '-')) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No activity yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(method_exists($recentActivity, 'links'))
                    <div class="mt-4">
                        {{ $recentActivity->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const table = $('#operations-activity-table');

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
