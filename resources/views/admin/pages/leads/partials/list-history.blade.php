@php
                                $sortedFollowups = $enquiry->leadFollowups
                                    ->sortByDesc('next_followup_date')
                                    ->values();

                                $latestFollowupForPreview = $sortedFollowups->first();
                            @endphp

                            <!-- <div class="followup-history-preview"> -->
                              <div class="followup-history-preview"
                                tabindex="0"
                                role="button"
                                aria-expanded="false"
                                aria-label="Click to view complete follow-up history"
                                title="Click to view complete follow-up history">
                                @if ($latestFollowupForPreview)
                                    <span class="followup-history-preview-text"
                                        title="{{ $latestFollowupForPreview->followup_note }}">

                                        {{ $latestFollowupForPreview->followup_note ?: 'No follow-up note' }}
                                    </span>

                                    <span class="followup-history-preview-count">
                                        {{ $sortedFollowups->count() }} Followup
                                    </span>
                                @else
                                    <span class="text-muted">
                                        No follow-up history
                                    </span>
                                @endif
                            </div>
                            @if ($latestFollowupForPreview)

                                    <div class="followup-history table-responsive followup-history-popup">
                                        <table class="table table-bordered followup-history-inner-table" width="100%">
                                            <thead class="bg-primary text-white">
                                                <tr class="border-b border-defaultborder">
                                                    <th scope="col" class="text-start">Note</th>
                                                    <th scope="col" class="text-start">By</th>
                                                    <th scope="col" class="text-start">Next Follow-up</th>
                                                    <th scope="col" class="text-start">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="table-group-divider dark:border-defaultborder/10">
                                                @forelse($enquiry->leadFollowups->sortByDesc('next_followup_date') as $followup)
                                                <tr class="border-b border-defaultborder">
                                                    <td
                                                        style="white-space: normal; word-break: break-word; max-width: 300px;">
                                                        {{ $followup->followup_note }}
                                                    </td>
                                                    <td>
                                                        {{ $followup->followedBy->name ?? 'System' }}
                                                        @if ($followup->file)
                                                        <a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}" target="_blank"
                                                            class="text-blue-500 ml-2">
                                                            <i class="ri-image-line"></i> View
                                                        </a>
                                                        @endif
                                                    </td>
                                                    <td
                                                        style="white-space: normal; word-break: break-word; max-width: 100px;">
                                                        {{ $followup->next_followup_date?->format('Y-m-d H:i T') }}
                                                    </td>
                                                    <td>
                                                        <span
                                                            class="badge 
                                                                            {{ $followup->status === 0
                                                                                ? 'bg-secondary/10 text-secondary'
                                                                                : ($followup->status === 1
                                                                                    ? 'bg-success/10 text-success'
                                                                                    : ($followup->status === 2
                                                                                        ? 'bg-danger/10 text-danger'
                                                                                        : ($followup->status === 3
                                                                                            ? 'bg-primary/10 text-primary'
                                                                                            : ($followup->status === 4
                                                                                                ? 'bg-warning/10 text-warning'
                                                                                                : ($followup->status === 5
                                                                                                    ? 'bg-info/10 text-info'
                                                                                                    : 'bg-default/10 text-default'))))) }}">
                                                            {{ $followup->status === 0
                                                            ? 'Initiated'
                                                            : ($followup->status === 1
                                                            ? 'Active'
                                                            : ($followup->status === 2
                                                            ? 'Cancelled'
                                                            : ($followup->status === 3
                                                            ? 'Full Payment Received'
                                                            : ($followup->status === 4
                                                            ? 'Partial Payment Received'
                                                            : ($followup->status === 5
                                                            ? 'Confirmed'
                                                            : ($followup->status === 6
                                                            ? 'Pending'
                                                            : ($followup->status === 7
                                                            ? 'Rescheduled'
                                                            : ($followup->status === 8
                                                            ? 'Approved'
                                                            : ($followup->status === 9
                                                            ? 'Rejected'
                                                            : 'N/A'))))))))) }}

                                                        </span>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="4" class="text-sm text-gray-700 text-center">No
                                                        follow-up
                                                        history</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
