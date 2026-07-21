@if(isset($registration_link) && $registration_link)
<div class="form-group">
    <label>Registration Link</label>
    <div class="input-group">
        <input type="text" class="form-control" readonly value="{{ $registration_link }}">
        <div class="input-group-append">
            <button class="btn btn-secondary" onclick="navigator.clipboard.writeText('{{ $registration_link }}')">Copy</button>
        </div>
    </div>
    <small class="form-text text-muted">Share this link with the client to let them fill the passenger registration form.</small>
</div>
@endif
