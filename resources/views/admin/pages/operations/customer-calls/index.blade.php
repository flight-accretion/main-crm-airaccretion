@extends('admin.layouts.header')

@section('content')
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="text-[1.125rem] font-semibold">Operations Customer Calls</h3>
        </div>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 rounded border bg-green-50 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 p-3 rounded border bg-red-50 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="box">
        <div class="box-header">
            <h5 class="box-title">Operations Calling Queue</h5>
        </div>

        <div class="box-body overflow-x-auto">
            <table class="table whitespace-nowrap min-w-full">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Mobile</th>
                        <th>Salesperson</th>
                        <th>Operations Handler</th>
                        <th>Status</th>
                        <th>Last Ops Contact</th>
                        <th>Last Handled By</th>
                        <th>Direction</th>
                        <th>Called Today</th>
                        <th>Last Ops Note</th>
                        <th>Next Follow-up</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['customer_mobile'] }}</td>
                            <td>{{ $row['salesperson'] }}</td>
                            <td>{{ $row['operations_handler'] }}</td>
                            <td>{{ $row['current_status_label'] }}</td>
                            <td>
                                {{ $row['last_ops_contact_at'] ? $row['last_ops_contact_at']->format('d M Y, h:i A') : 'Not contacted' }}
                            </td>
                            <td>{{ $row['last_handled_by'] ?: '-' }}</td>
                            <td>{{ $row['last_direction'] ? ucfirst($row['last_direction']) : '-' }}</td>
                            <td>
                                @if ($row['called_today'])
                                    <span class="badge bg-success/10 text-success">Called</span>
                                @else
                                    <span class="badge bg-danger/10 text-danger">Not Called</span>
                                @endif
                            </td>
                            <td class="max-w-sm whitespace-normal">
                                {{ $row['last_summary'] ?: '-' }}
                            </td>
                            <td>
                                {{ $row['next_followup_date'] ? $row['next_followup_date']->format('d M Y, h:i A') : '-' }}
                            </td>
                            <td>
                                @if ($row['lead']->client_id)
                                    <a href="{{ route('admin.clients.view', $row['lead']->client_id) }}"
                                        class="ti-btn ti-btn-info !py-1 !px-2">
                                        Open
                                    </a>
                                @endif

                                @php
                                    $viewerRole = optional(auth()->user()->userType)->user_type;
                                    $canAssignOperations =
                                        auth()->user()->isSuperAdmin()
                                        || in_array(
                                            $viewerRole,
                                            [
                                                \App\Models\UserType::OPERATIONS_MANAGER,
                                                \App\Models\UserType::SENIOR_OPERATIONS_MANAGER,
                                            ],
                                            true
                                        );
                                @endphp

                                @if ($canAssignOperations)
                                    <form method="POST"
                                        action="{{ route('admin.operations.leads.assign', $row['lead']->id) }}"
                                        class="mt-2 flex gap-2">
                                        @csrf
                                        <select name="operations_user_id" class="ti-form-select form-control-sm" required>
                                            <option value="">Ops Handler</option>
                                            @foreach ($operationsUsers as $operationsUser)
                                                <option value="{{ $operationsUser->id }}"
                                                    {{ optional(optional($row['lead']->activeOperationsAssignment)->operationsUser)->id === $operationsUser->id ? 'selected' : '' }}>
                                                    {{ $operationsUser->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="ti-btn ti-btn-primary !py-1 !px-2">
                                            Assign
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-gray-500">
                                No Operations-eligible leads found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
