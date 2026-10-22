@php
    $currentLeadFollowup = $followups->first();
    $currentLeadStatusLabel = $currentLeadFollowup ? $currentLeadFollowup->statusLabel() : 'Initiated';
    $selectedLeadStatus = (string) old('status', 'keep');
@endphp
<select class="ti-form-select rounded-sm form-control-sm" name="status" id="status" required>
    <option value="keep" {{ $selectedLeadStatus === 'keep' ? 'selected' : '' }}>Keep current status - {{ $currentLeadStatusLabel }}</option>
    <option value="1" {{ $selectedLeadStatus === '1' ? 'selected' : '' }}>Active</option>
    <option value="2" {{ $selectedLeadStatus === '2' ? 'selected' : '' }}>Cancelled</option>
    <option value="3" {{ $selectedLeadStatus === '3' ? 'selected' : '' }}>Full payment received</option>
    <option value="4" {{ $selectedLeadStatus === '4' ? 'selected' : '' }}>Partial payment received</option>
</select>
