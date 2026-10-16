<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/leads/leads-dialogs.css') }}?v=1">
@if($canDownloadLeads)
<dialog id="leads-download" class="crm-lead-dialog" aria-labelledby="leads-download-title">
    <header><h2 id="leads-download-title">Download Leads</h2><button type="button" data-lead-dialog-close aria-label="Close download modal"><i class="ri-close-line" aria-hidden="true"></i></button></header>
    <form id="leads-download-form">
        <div class="lead-dialog-fields">
            <label class="wide">Period<select name="period"><option value="filters">Current filters</option><option value="month">Selected month</option></select></label>
            <label data-download-month hidden>Month<select name="month">@foreach(range(1,12) as $m)<option value="{{ $m }}" {{ now()->month === $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000,$m,1)->format('F') }}</option>@endforeach</select></label>
            <label data-download-month hidden>Year<select name="year">@for($y=2020;$y<=now()->year;$y++)<option value="{{ $y }}" {{ now()->year === $y ? 'selected' : '' }}>{{ $y }}</option>@endfor</select></label>
            <label class="wide" data-download-month hidden>Date basis<select name="date_basis"><option value="service">Service date</option><option value="created">Created date</option><option value="followup">Follow-up date</option></select></label>
            <label class="wide">Person<select name="person_id"><option value="">All permitted staff</option>@if($canFilterUnassignedLeads ?? false)<option value="na" {{ in_array(strtolower((string) request('representative_user_id')), ['na','n/a','unassigned'], true) ? 'selected' : '' }}>Unassigned</option>@endif @foreach($staff as $person)<option value="{{ $person->id }}" {{ request('representative_user_id') === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>@endforeach</select></label>
            <fieldset class="wide"><legend>File type</legend><label><input type="radio" name="file_type" value="excel" checked>Excel (.xlsx)</label><label><input type="radio" name="file_type" value="csv">CSV</label></fieldset>
        </div>
        <p class="lead-dialog-error" role="alert" hidden></p>
        <footer><button type="button" class="leads-button" data-lead-dialog-close>Cancel</button><button type="submit" class="leads-button leads-primary"><i class="ri-download-2-line" aria-hidden="true"></i>Download</button></footer>
    </form>
</dialog>
@endif
@if($canRequestLeads)
<dialog id="leads-request" class="crm-lead-dialog lead-request-dialog" aria-labelledby="leads-request-title" data-search-url="{{ route('admin.leads.request-search') }}">
    <header><h2 id="leads-request-title">Request Lead</h2><button type="button" data-lead-dialog-close aria-label="Close lead request modal"><i class="ri-close-line" aria-hidden="true"></i></button></header>
    <form id="lead-request-search"><label for="lead-request-term">Name or phone number</label><div class="lead-search-row"><input type="search" id="lead-request-term" name="search" minlength="3" maxlength="100" autocomplete="off" required><button class="leads-button leads-primary" type="submit"><i class="ri-search-line" aria-hidden="true"></i>Search</button></div></form>
    <div class="lead-search-status" role="status" aria-live="polite">Enter a name or phone number to search.</div>
    <div id="lead-request-results"></div>
    <!-- <footer><a class="leads-button" href="{{ route('admin.leads.transfers.index') }}"><i class="ri-notification-3-line" aria-hidden="true"></i>Requested Leads <span data-lead-transfer-count></span></a><button class="leads-button" type="button" data-lead-dialog-close>Close</button></footer> -->
</dialog>
<template id="lead-request-csrf">@csrf</template>
@endif
