@php
    $sortedFollowups = $enquiry->leadFollowups->sortByDesc('created_at')->values();
    $latestFollowupForPreview = $sortedFollowups->first();
    $followupStatuses = [0 => ['Initiated', 'is-muted'], 1 => ['Active', 'is-active'], 2 => ['Cancelled', 'is-danger'], 3 => ['Full Payment Received', 'is-success'], 4 => ['Partial Payment Received', 'is-warning'], 5 => ['Ride Completed', 'is-primary'], 6 => ['Pending', 'is-warning'], 7 => ['Rescheduled', 'is-primary'], 8 => ['Approved', 'is-success'], 9 => ['Rejected', 'is-danger'], 10 => ['Sales Follow-up', 'is-active'], 11 => ['Close Follow-up', 'is-muted']];
@endphp
<div class="followup-history-preview" tabindex="0" role="button" aria-expanded="false" aria-label="Click to view complete follow-up history" title="Click to view complete follow-up history">
    @if ($latestFollowupForPreview)
        <span class="followup-history-preview-text" title="{{ $latestFollowupForPreview->followup_note }}">{{ $latestFollowupForPreview->followup_note ?: 'No follow-up note' }}</span>
        <span class="followup-history-preview-count">{{ $sortedFollowups->count() }} Followup</span>
    @else
        <span class="text-muted">No follow-up history</span>
    @endif
</div>
@if ($latestFollowupForPreview)
    <section class="followup-history-popup" role="dialog" aria-modal="true" aria-label="Follow-up history">
        <header class="followup-popup-header"><div><h2>Follow-up History</h2><p>Lead conversation timeline · newest activity first</p></div><button type="button" class="followup-popup-close" aria-label="Close follow-up history">&times;</button></header>
        <div class="followup-popup-filters">
            <label class="visually-hidden" for="followup-history-search-{{ $enquiry->id }}">Search follow-up history</label>
            <input id="followup-history-search-{{ $enquiry->id }}" class="followup-history-search" type="search" placeholder="Search note, staff or status" autocomplete="off">
            <label class="visually-hidden" for="followup-history-status-{{ $enquiry->id }}">Filter by status</label>
            <select id="followup-history-status-{{ $enquiry->id }}" class="followup-history-status"><option value="">All statuses</option>@foreach (collect($followupStatuses)->pluck(0)->unique() as $statusLabel)<option value="{{ strtolower($statusLabel) }}">{{ $statusLabel }}</option>@endforeach</select>
            <button type="button" class="followup-history-clear">Clear</button>
        </div>
        <div class="followup-history-timeline" tabindex="0">
            @foreach ($sortedFollowups as $followup)
                @php
                    [$statusLabel, $statusClass] = $followupStatuses[$followup->status] ?? ['N/A', 'is-muted'];
                    $staffName = $followup->followedBy->name ?? 'System';
                    $note = trim((string) $followup->followup_note) ?: 'No follow-up note was recorded.';
                @endphp
                <article class="followup-history-item" data-search="{{ strtolower($note . ' ' . $staffName . ' ' . $statusLabel) }}" data-status="{{ strtolower($statusLabel) }}">
                    <div class="followup-history-avatar" aria-hidden="true">{{ strtoupper(mb_substr($staffName, 0, 1)) }}</div>
                    <div class="followup-history-content"><p class="followup-history-note">{{ $note }}</p><div class="followup-history-meta"><span class="followup-history-by">{{ $staffName === 'System' ? 'System' : 'By ' . $staffName }}</span><span class="followup-history-status {{ $statusClass }}">{{ $statusLabel }}</span>@if ($followup->file)<a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}" target="_blank" rel="noopener">View attachment</a>@endif</div></div>
                    <div class="followup-history-dates"><span>Created</span><strong>{{ $followup->created_at ? $followup->created_at->format('d M Y · h:i A') : 'N/A' }}</strong><div class="followup-history-next"><span>Next Follow-up</span><strong>{{ $followup->next_followup_date ? $followup->next_followup_date->format('d M Y · h:i A') : 'Not scheduled' }}</strong></div></div>
                </article>
            @endforeach
            <p class="followup-history-empty" hidden>No follow-up records match this filter.</p>
        </div>
        <footer class="followup-popup-footer"><span>Showing {{ $sortedFollowups->count() }} follow-up {{ \Illuminate\Support\Str::plural('record', $sortedFollowups->count()) }} · internal scroll for larger history</span><div><button type="button" class="followup-popup-close">Close</button>@if ($canWriteLeads ?? false)<a class="followup-popup-add" href="{{ route('admin.leads.follow-up.create', $enquiry->id) }}#followup-form">Add Follow-up</a>@endif</div></footer>
    </section>
@endif
