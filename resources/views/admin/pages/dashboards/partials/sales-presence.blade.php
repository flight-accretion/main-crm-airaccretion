@php
    $salesPresenceRows = collect($salesPresenceRows ?? []);
    $canViewAllSalesPresence = (bool) ($canViewAllSalesPresence ?? false);
    $presentCount = $salesPresenceRows->where('is_present_today', true)->count();
    $absentCount = $salesPresenceRows->count() - $presentCount;
@endphp

<div class="grid grid-cols-12 gap-x-6">
    <div class="col-span-12">
        <div class="box">
            <div class="box-header flex items-center justify-between">
                <div>
                    <h6 class="text-[1rem] font-semibold text-gray-800">
                        {{ $canViewAllSalesPresence ? "Today's present Executive" : 'Your Availability Today' }}
                    </h6>
                    <p class="mb-0 text-sm text-gray-500">
                        {{ $canViewAllSalesPresence ? 'Live lead assignment uses only people who clicked Yes today.' : 'Your lead assignment status for today.' }}
                    </p>
                </div>
                <div class="flex gap-2 text-sm">
                    <span class="rounded bg-success/10 px-3 py-1 font-semibold text-success">Yes: {{ $presentCount }}</span>
                    <span class="rounded bg-danger/10 px-3 py-1 font-semibold text-danger">No: {{ $absentCount }}</span>
                </div>
            </div>
            <div class="box-body">
                @if($salesPresenceRows->isEmpty())
                    <div class="rounded bg-gray-50 p-3 text-sm text-gray-600">No sales presence data found.</div>
                @elseif($canViewAllSalesPresence)
                    <div class="overflow-x-auto">
                        <table class="table whitespace-nowrap min-w-full">
                            <thead>
                                <tr>
                                    <th scope="col">Salesperson</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Present Today</th>
                                    <th scope="col">Last Response</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($salesPresenceRows as $row)
                                    <tr>
                                        <td class="font-semibold text-gray-800">{{ $row['name'] }}</td>
                                        <td>{{ $row['role'] }}</td>
                                        <td>
                                            <span class="badge {{ $row['is_present_today'] ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
                                        <td>{{ $row['last_response_label'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    @php($row = $salesPresenceRows->first())
                    <div class="flex flex-col gap-3 rounded border border-defaultborder p-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <div class="font-semibold text-gray-800">{{ $row['name'] }}</div>
                            <div class="text-sm text-gray-500">{{ $row['role'] }}</div>
                        </div>
                        <div class="flex flex-col gap-1 md:items-end">
                            <span class="badge {{ $row['is_present_today'] ? 'bg-success/10 text-success' : 'bg-danger/10 text-danger' }}">
                                Present Today: {{ $row['status_label'] }}
                            </span>
                            <span class="text-sm text-gray-500">{{ $row['last_response_label'] }}</span>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
