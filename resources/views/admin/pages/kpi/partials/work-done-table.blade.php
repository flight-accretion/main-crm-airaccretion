@if(in_array($type, ['completed', 'active', 'cancelled', 'total'], true))

    <div class="table-responsive">

        <table class="table display nowrap kpi-detail-datatable whitespace-nowrap min-w-full" width="100%">

            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Lead</th>
                    <th>Product / Service</th>
                    <th>Sales Executive</th>
                    <th>Current Status</th>
                    <th>Last Follow-Up</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

                @forelse($rows as $lead)

                    @php
                        $latest =
                            $lead->leadFollowups
                                ->sortByDesc('created_at')
                                ->first();

                        $status = (int) ($latest->status ?? 0);

                        $statusName = match ($status) {
                            1 => 'Active',
                            2 => 'Cancelled',
                            3 => 'Full Payment Received',
                            4 => 'Partial Payment Received',
                            5 => 'Confirmed / Complete',
                            6 => 'Pending',
                            7 => 'Rescheduled',
                            8 => 'Approved',
                            9 => 'Rejected',
                            default => 'Initiated',
                        };
                    @endphp

                    <tr>

                        <td>
                            {{ $lead->client->name ?? '-' }}
                        </td>

                        <td>
                            {{ $lead->client->mobile ?? '-' }}
                        </td>

                        <td>
                            {{ $lead->id }}
                        </td>

                        <td>
                            {{ $lead->product->product ?? '-' }}
                        </td>

                        <td>
                            {{ $lead->representative->name ?? '-' }}
                        </td>

                        <td>
                            {{ $statusName }}
                        </td>

                        <td>
                            @if($latest)
                                {{ optional($latest->created_at)->format('d-m-Y h:i A') }}
                            @else
                                -
                            @endif
                        </td>

                        <td>
                            <a
                                href="{{ route('admin.leads.view', $lead->id) }}"
                                class="ti-btn ti-btn-sm ti-btn-primary"
                            >
                                View Lead
                            </a>
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="8"
                            class="text-center py-4"
                        >
                            No records found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

@else

    {{-- Daily Outreach detail table --}}

    <div class="table-responsive">

        <table class="table display nowrap kpi-detail-datatable whitespace-nowrap min-w-full" width="100%">

            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Mobile</th>
                    <th>Executive</th>
                    <th>Completion</th>
                    <th>Completed At</th>
                </tr>
            </thead>

            <tbody>

                @forelse($rows as $row)

                    <tr>

                        <td>
                            {{ $row->pool->customer_name ?? '-' }}
                        </td>

                        <td>
                            {{ $row->pool->normalized_mobile ?? '-' }}
                        </td>

                        <td>
                            {{ $row->user->name ?? '-' }}
                        </td>

                        <td>
                            {{ strtoupper($row->completion_type ?? '-') }}
                        </td>

                        <td>
                            {{ optional($row->completed_at)->format('d-m-Y h:i A') }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td
                            colspan="5"
                            class="text-center py-4"
                        >
                            No records found.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

@endif
