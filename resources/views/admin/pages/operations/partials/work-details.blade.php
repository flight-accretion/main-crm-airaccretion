<table aria-label="Selected Operations cases">
    <thead><tr><th>Client</th><th>Phone</th><th>Type</th><th>Service</th><th>Service date</th><th>Assigned to</th><th>Completed by</th><th>Opened</th><th>Completed</th><th>Duration / Age</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr data-operations-case="{{ $row->id }}">
            <td data-label="Client">{{ $row->lead?->client?->name ?? '-' }}</td>
            <td data-label="Phone">{{ $row->lead?->client?->contact_number ?? '-' }}</td>
            <td data-label="Type">{{ ucwords(str_replace('_', ' ', $row->type)) }}</td>
            <td data-label="Service">{{ collect($row->lead?->service_ids_array ?? [])->map(fn($id) => $serviceNames[$id] ?? null)->filter()->implode(', ') ?: '-' }}</td>
            <td data-label="Service date">@forelse($row->lead?->rideSegments ?? [] as $ride)<small>{{ $ride->from_date?->format('d M Y, H:i') ?: '-' }}</small>@empty - @endforelse</td>
            <td data-label="Assigned to">{{ $row->assignee?->name ?? 'Unassigned' }}</td>
            <td data-label="Completed by">{{ $row->completed_at ? ($row->completedBy?->name ?? 'Not recorded') : '-' }}</td>
            <td data-label="Opened">{{ $row->opened_at?->format('d M Y, H:i') ?? '-' }}</td>
            <td data-label="Completed">{{ $row->completed_at?->format('d M Y, H:i') ?? '-' }}</td>
            <td data-label="Duration / Age">@if($row->opened_at && (!$row->completed_at || $row->completed_at->gte($row->opened_at)))@php($hours = round($row->opened_at->diffInSeconds($row->completed_at ?: now()) / 3600, 1))<span class="op-pill {{ $hours > 24 ? 'op-rejected' : 'op-completed' }}">{{ $hours }}h</span><small>{{ $row->completed_at ? 'Elapsed duration' : 'Open age' }}</small>@else - @endif</td>
            <td data-label="Status"><span class="op-pill op-{{ $row->status }}">{{ ucwords(str_replace('_', ' ', $row->status)) }}</span></td>
        </tr>
    @empty
        <tr><td colspan="11" class="op-empty">No matching cases for this selection.</td></tr>
    @endforelse
    </tbody>
</table>
<footer class="op-detail-footer">
    <span>{{ $rows->firstItem() ?? 0 }}-{{ $rows->lastItem() ?? 0 }} of {{ $rows->total() }} cases</span>
    <nav aria-label="Case detail pages">
        @if($rows->previousPageUrl())<a class="ti-btn" href="{{ $rows->previousPageUrl() }}" data-work-page>Previous</a>@endif
        <span>Page {{ $rows->currentPage() }} of {{ $rows->lastPage() }}</span>
        @if($rows->nextPageUrl())<a class="ti-btn" href="{{ $rows->nextPageUrl() }}" data-work-page>Next</a>@endif
    </nav>
</footer>
