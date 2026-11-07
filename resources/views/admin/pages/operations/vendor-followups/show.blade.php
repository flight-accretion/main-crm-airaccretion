@extends('admin.layouts.header')

@section('content')
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vendor-followups.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/admin/css/crm/vendor-followup-detail.css') }}">

    @php
        $metadata = $case->metadata ?? [];
        $resolution = data_get($metadata, 'resolution');
        $status = $resolution ? ucfirst($resolution) : ucfirst(str_replace('_', ' ', $case->status));
        $statusClass = $resolution === 'cancelled' ? 'vf-red' : ($case->completed_at ? 'vf-green' : 'vf-amber');
        $clientName = optional(optional($case->lead)->client)->name ?? 'Customer';
        $vendorName = data_get($metadata, 'vendor_name_snapshot', 'Not recorded');
    @endphp

    <div class="vf-page vf-detail-page">
        <header class="vf-heading vf-heading-card">
            <div>
                <p class="vf-eyebrow">Vendor Follow-ups</p>
                <h1>Vendor Follow-up</h1>
                <p>{{ $clientName }} · {{ $vendorName }}</p>
            </div>
            <div class="vf-actions">
                <span class="vf-badge {{ $statusClass }}">{{ $status }}</span>
                <a href="{{ route('admin.operations.vendor-followups.index') }}" class="vf-button"><i class="ri-arrow-left-line" aria-hidden="true"></i> Back to queue</a>
            </div>
        </header>

        @if (session('success'))<div class="vf-notice" role="status">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="vf-notice vf-red" role="alert">{{ $errors->first() }}</div>@endif

        <div class="vf-detail-layout">
            <main>
                <section class="vf-card vf-summary-card">
                    <header class="vf-card-header"><div><h2>Follow-up Summary</h2><p>Check the vendor details before recording the next action.</p></div><span class="vf-section-tag">Current task</span></header>
                    <div class="vf-card-body">
                        <div class="vf-summary-grid">
                            <div class="vf-summary-item"><small>Vendor</small><strong>{{ $vendorName }}</strong></div>
                            <div class="vf-summary-item"><small>Purpose</small><strong>{{ data_get($metadata, 'purpose', 'Not recorded') }}</strong></div>
                            <div class="vf-summary-item"><small>Assigned to</small><strong>{{ $case->assignee?->name ?: 'Unassigned' }}</strong></div>
                            <div class="vf-summary-item"><small>Next follow-up</small><strong>{{ optional($case->next_followup_at)->format('d M Y') ?? 'Not scheduled' }}</strong></div>
                            <div class="vf-summary-item"><small>Tentative ride</small><strong>{{ data_get($metadata, 'tentative_ride_at') ? \Carbon\Carbon::parse(data_get($metadata, 'tentative_ride_at'))->format('d M Y · H:i') : 'Not recorded' }}</strong></div>
                            <div class="vf-summary-item"><small>Confirmed ride</small><strong>{{ data_get($metadata, 'confirmed_ride_at') ? \Carbon\Carbon::parse(data_get($metadata, 'confirmed_ride_at'))->format('d M Y · H:i') : 'Not recorded' }}</strong></div>
                            <div class="vf-summary-item vf-full"><small>Vendor response</small><strong>{{ data_get($metadata, 'vendor_response', 'Not recorded') ?: 'Not recorded' }}</strong></div>
                            <div class="vf-summary-item vf-full"><small>Initial remark</small><strong>{{ $case->note ?: 'Not recorded' }}</strong></div>
                        </div>
                    @php
                        $billRole = auth()->user()?->userType?->user_type;
                        $canSeeBill = $billRole === \App\Models\UserType::SUPER_ADMIN
                            || $billRole === \App\Models\UserType::OWNER
                            || in_array($billRole, \App\Models\UserType::OPERATIONS_ROLES, true)
                            || in_array($billRole, \App\Models\UserType::ACCOUNTS_ROLES, true);
                    @endphp
                    @if($canSeeBill && data_get($metadata, 'kind') === 'vendor_bill' && data_get($metadata, 'bill_path'))
                        <a class="vf-button" href="{{ route('admin.operations.vendor-followups.download-bill', $case) }}">Download Vendor Bill</a>
                    @endif
                    </div>
                </section>

                <section class="vf-card vf-timeline-card">
                    <header class="vf-card-header"><div><h2>Activity Timeline</h2><p>Audit trail of vendor follow-up activity.</p></div></header>
                    <div class="vf-card-body">
                        <div class="vf-timeline">
                            @forelse ($case->activities->sortByDesc('created_at') as $activity)
                                <article class="vf-timeline-item">
                                    <span class="vf-timeline-dot" aria-hidden="true"></span>
                                    <div class="vf-timeline-head"><strong>{{ optional($activity->user)->name ?? 'System' }}</strong><span>{{ optional($activity->created_at)->format('d M Y · H:i') }}</span></div>
                                    <div class="vf-timeline-action">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</div>
                                    <p class="vf-timeline-note">{{ $activity->note ?: 'No additional note recorded.' }}</p>
                                </article>
                            @empty
                                <p class="vf-empty">No activity has been recorded yet.</p>
                            @endforelse
                        </div>
                    </div>
                </section>
            </main>

            <aside>
                               @if ($canManage && !$isClosed && data_get($metadata, 'kind') === \App\Services\Operations\VendorBillFollowupService::KIND)
                    <section class="vf-card vf-update-card">
                        <header class="vf-card-header"><h2>Collect Vendor Bill</h2></header>
                        <div class="vf-card-body">
                            <form method="POST"
                                  enctype="multipart/form-data"
                                  action="{{ route('admin.operations.vendor-followups.complete-bill', $case) }}">
                                @csrf
                                <label>Vendor bill (PDF/JPG/PNG, up to 10 MB)
                                    <input type="file" name="vendor_bill" accept=".pdf,.jpg,.jpeg,.png">
                                </label>
                                <label>
                                    <input type="checkbox" name="reimbursment_from_customer" value="1">
                                    reimbursment from customer
                                </label>
                                <p>Bill is required unless reimbursment from customer is checked.</p>
                                <button type="submit" class="vf-button vf-primary">Complete Vendor Bill Follow-up</button>
                            </form>
                        </div>
                    </section>
                @elseif ($canManage && !$isClosed)
                    <section class="vf-card vf-update-card">
                        <header class="vf-card-header"><div><h2>What happened with the vendor?</h2><p>Select one action below. Only the selected action will be saved.</p></div></header>
                        <div class="vf-card-body">
                            <div class="vf-action-tabs" role="tablist" aria-label="Vendor follow-up actions">
                                <button type="button" class="vf-action-tab is-active" role="tab" aria-selected="true" aria-controls="vf-reschedule-panel" data-vf-action-tab="reschedule"><span class="vf-action-number">1</span><span><strong>Need to follow up later</strong><small>Set a new follow-up date</small></span></button>
                                <button type="button" class="vf-action-tab" role="tab" aria-selected="false" aria-controls="vf-complete-panel" data-vf-action-tab="complete"><span class="vf-action-number">2</span><span><strong>Vendor confirmed the ride</strong><small>Record confirmed ride details</small></span></button>
                            </div>

                            <div id="vf-reschedule-panel" class="vf-action-panel" role="tabpanel" data-vf-action-panel="reschedule">
                                <div class="vf-panel-heading"><strong>Reschedule follow-up</strong><span>Use this when the vendor needs more time or is unavailable now.</span></div>
                                <form method="POST" action="{{ route('admin.operations.vendor-followups.reschedule', $case) }}">
                                    @csrf
                                    <label>New follow-up date <span class="vf-required">Required</span><input type="date" name="followup_date" min="{{ now()->toDateString() }}" required></label>
                                    <label>Why are you following up later? <span class="vf-required">Required</span><textarea name="reason" rows="4" required placeholder="Example: Vendor asked to call again tomorrow after checking availability."></textarea></label>
                                    <button class="vf-button vf-primary vf-action-submit" type="submit"><i class="ri-calendar-check-line" aria-hidden="true"></i> Save new follow-up date</button>
                                </form>
                            </div>

                            <div id="vf-complete-panel" class="vf-action-panel" role="tabpanel" data-vf-action-panel="complete" hidden>
                                <div class="vf-panel-heading"><strong>Vendor confirmed the ride</strong><span>Save the confirmed date, time and the vendor’s response.</span></div>
                                <form method="POST" action="{{ route('admin.operations.vendor-followups.complete', $case) }}">
                                    @csrf
                                    <div class="vf-inline-fields">
                                        <label>Confirmed ride date <span class="vf-required">Required</span><input type="date" name="confirmed_ride_date" required></label>
                                        <label>Confirmed ride time <span class="vf-required">Required</span><input type="time" name="confirmed_ride_time" required></label>
                                    </div>
                                    <label>Vendor response <span class="vf-required">Required</span><textarea name="vendor_response" rows="4" required placeholder="Example: Vendor confirmed the vessel and driver are available."></textarea></label>
                                    <button class="vf-button vf-primary vf-action-submit" type="submit"><i class="ri-checkbox-circle-line" aria-hidden="true"></i> Mark vendor follow-up complete</button>
                                </form>
                            </div>

                            <div class="vf-danger-action">
                                <div class="vf-danger-heading"><strong>Cannot continue this vendor follow-up?</strong><span>Cancel only when this task is no longer required. This cannot be undone.</span></div>
                                <form method="POST" action="{{ route('admin.operations.vendor-followups.cancel', $case) }}">
                                    @csrf
                                    <label>Cancellation reason <span class="vf-required">Required</span><textarea name="reason" rows="3" required placeholder="Example: Vendor is no longer required for this booking."></textarea></label>
                                    <button class="vf-danger-button" type="submit" onclick="return confirm('Cancel this Vendor Follow-up?')"><i class="ri-close-circle-line" aria-hidden="true"></i> Cancel vendor follow-up</button>
                                </form>
                            </div>
                        </div>
                    </section>
                @elseif (!$canManage)
                    <section class="vf-card"><div class="vf-card-body vf-muted-message">You have read-only access to this vendor follow-up.</div></section>
                @else
                    <section class="vf-card"><div class="vf-card-body vf-muted-message">This vendor follow-up is closed and cannot be updated.</div></section>
                @endif
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-vf-action-tab]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var selected = tab.dataset.vfActionTab;
            document.querySelectorAll('[data-vf-action-tab]').forEach(function (item) {
                var active = item.dataset.vfActionTab === selected;
                item.classList.toggle('is-active', active);
                item.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            document.querySelectorAll('[data-vf-action-panel]').forEach(function (panel) {
                panel.hidden = panel.dataset.vfActionPanel !== selected;
            });
        });
    });
});
</script>
@endpush
