<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/lead-detail.css') }}">
@php
    $detailRole = auth()->user()?->userType?->user_type;
    $detailCanAct = in_array($detailRole, array_merge(\App\Models\UserType::ADMIN_ROLES, \App\Models\UserType::SALES_ROLES, \App\Models\UserType::OPERATIONS_ROLES, \App\Models\UserType::ACCOUNTS_ROLES), true);
    $detailAccounts = \App\Support\LeadDetailAccess::canViewAccountsActivity(auth()->user());
    $detailNext = $latestFollowup?->next_followup_date;
    $detailScore = $latestLead ? $latestLead->latestAiScore()->whereNotNull('score')->first() : null;
    $detailTravel = $latestLead?->rideSegments?->filter(fn($ride) => $ride->from_date)->sortBy('from_date')->first()?->from_date;
    $detailPhone = preg_replace('/[^0-9+]/', '', $client->contact_number ?? '');
    if (strlen($detailPhone) === 10) $detailPhone = '+91'.$detailPhone;
    $detailWhatsapp = preg_replace('/\D/', '', $client->alternate_number ?: $detailPhone);
    $detailContacts = ['Full name' => $client->name, 'Phone' => $client->contact_number, 'Email' => $client->email,
        'WhatsApp' => $client->alternate_number, 'City' => $cityName ?? null, 'Country' => $country ?? null,
        'Address' => $client->address, 'Occasion' => $latestLead?->occasion];
    $detailFilled = collect($detailContacts)->filter(fn($value) => filled($value) && $value !== 'N/A');
    $detailCompleteness = (int) round($detailFilled->count() / count($detailContacts) * 100);
    $detailSource = $latestLead && \App\Models\EmailLeadLog::where('lead_id', $latestLead->id)->exists() ? 'Email enquiry' : null;
    $detailEnquiries = 0;
    if ($latestLead?->created_at) {
        $detailRelated = \App\Models\Lead::where('client_id', $client->id)->whereDate('created_at', $latestLead->created_at->toDateString());
        $detailRepresentatives = \App\Helpers\getRepresentativeIds(auth()->user());
        if ($detailRepresentatives !== null) $detailRelated->whereIn('representative_user_id', collect($detailRepresentatives)->all());
        $detailEnquiries = $detailRelated->count();
    }
@endphp
@if($detailEnquiries > 1)<div class="ld-warning">{{ $detailEnquiries }} enquiries for this contact on {{ $latestLead->created_at->format('d M Y') }}.</div>@endif
@if($latestLead && (!$latestLead->representative_user_id || !$detailNext))
    <div class="ld-warning" role="status">@if(!$latestLead->representative_user_id)This lead is unassigned. @endif @if(!$detailNext)No follow-up scheduled.@endif</div>
@endif
<header class="ld-hero">
    <div class="ld-identity"><span class="ld-avatar">{{ strtoupper(substr($client->name ?: 'L', 0, 2)) }}</span><div><h1>{{ $client->name }}</h1><span class="ld-tag">{{ $latestFollowup ? $latestFollowup->statusLabel() : ($client->status == 1 ? 'Active' : 'Inactive') }}</span>@if($detailSource)<span class="ld-tag">{{ $detailSource }}</span>@endif
        <p>@if($detailPhone)<a href="tel:{{ $detailPhone }}">{{ $detailPhone }}</a> &middot; @endif Created {{ optional($latestLead?->created_at)->format('d M Y') ?: '--' }} &middot; Owner: {{ $latestLead?->representative?->name ?: 'Unassigned' }}</p></div></div>
    @if($latestLead && $detailCanAct)<div class="ld-actions">
        <a class="ld-button" href="{{ route('admin.leads.edit', $latestLead) }}"><i class="ti ti-pencil" aria-hidden="true"></i> Edit</a>
        <a class="ld-button" href="{{ route('admin.leads.follow-up.create', $latestLead) }}"><i class="ti ti-plus" aria-hidden="true"></i> Note</a>
        <a class="ld-button ld-primary" href="{{ route('admin.leads.follow-up.create', $latestLead) }}">Schedule follow-up</a>
    </div>@endif
    <div class="ld-stats">
        <div><small>Lead score</small><strong>{{ $detailScore ? $detailScore->score.' - '.ucfirst($detailScore->temperature ?: 'Unrated') : 'Not scored' }}</strong></div>
        <div><small>Next follow-up</small><strong class="{{ !$detailNext ? 'ld-danger' : '' }}">{{ $detailNext ? \Carbon\Carbon::parse($detailNext)->format('d M Y, H:i') : 'None scheduled' }}</strong></div>
        <div><small>Interested product / service</small><strong>{{ $clientInfo['products'] ?? 'Not specified' }}</strong></div>
        <div><small>Travel date</small><strong>{{ $detailTravel ? $detailTravel->format('d M Y') : 'Not set' }}</strong>@if($detailTravel)<small>{{ $detailTravel->diffForHumans() }}</small>@endif</div>
        @if($detailAccounts)<div><small>Payments recorded</small><strong>&#8377;{{ number_format(collect($clientPaymentHistory ?? [])->sum('amount'), 2) }}</strong><small>All recorded statuses</small></div>@endif
    </div>
</header>
<section class="box" id="ld-owner"><div class="box-header"><h2>Ownership &amp; next step</h2></div><div class="box-body">
    <dl><dt>Assigned to</dt><dd>{{ $latestLead?->representative?->name ?: 'Unassigned' }}</dd><dt>Next follow-up</dt><dd class="{{ !$detailNext ? 'ld-danger' : '' }}">{{ $detailNext ? \Carbon\Carbon::parse($detailNext)->format('d M Y, H:i') : 'None scheduled' }}</dd></dl>
    @if($latestLead && $detailCanAct)<a class="ld-button" href="{{ route('admin.leads.edit', $latestLead) }}">Manage assignment</a>@endif
    @if($detailScore?->suggested_action)<p class="ld-suggestion">{{ $detailScore->suggested_action }}</p>@endif
</div></section>
<div id="ld-completeness"><div class="ld-missing">@foreach(collect($detailContacts)->diffKeys($detailFilled) as $name => $value)<span class="ld-tag">Missing: {{ $name }}</span>@endforeach</div>
    <label for="ld-profile">Contact completeness {{ $detailCompleteness }}%</label><progress id="ld-profile" value="{{ $detailCompleteness }}" max="100">{{ $detailCompleteness }}%</progress>
</div>
@if($latestLead && $detailCanAct)<nav class="ld-mobile-actions" aria-label="Lead actions">
    @if($detailPhone)<a href="tel:{{ $detailPhone }}"><i class="ti ti-phone" aria-hidden="true"></i> Call</a>@endif
    @if($detailWhatsapp)<a href="https://wa.me/{{ $detailWhatsapp }}" target="_blank" rel="noopener">WhatsApp</a>@endif
    <a href="{{ route('admin.leads.follow-up.create', $latestLead) }}"><i class="ti ti-plus" aria-hidden="true"></i> Note</a>
</nav>@endif
