<table aria-label="Selected Operations cases">
    <thead><tr><th>Customer</th><th>Phone</th><th>Category</th><th>Status</th><th>Assigned to</th><th>Opened</th><th>Completed</th></tr></thead>
    <tbody>
    @forelse($rows as $row)
        <tr data-operations-case="{{ $row->id }}">
            <td data-label="Customer">{{ $row->lead?->client?->name ?? '-' }}</td>
            <td data-label="Phone">{{ $row->lead?->client?->contact_number ?? '-' }}</td>
            <td data-label="Category">{{ ucwords(str_replace('_', ' ', $row->type)) }}</td>
            <td data-label="Status"><span class="op-pill op-{{ $row->status }}">{{ ucwords(str_replace('_', ' ', $row->status)) }}</span></td>
            <td data-label="Assigned to">{{ $row->assignee?->name ?? 'Unassigned' }}</td>
            <td data-label="Opened">{{ $row->opened_at?->format('d M Y, H:i') ?? '-' }}</td>
            <td data-label="Completed">{{ $row->completed_at?->format('d M Y, H:i') ?? '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="op-empty">No matching cases for this selection.</td></tr>
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
