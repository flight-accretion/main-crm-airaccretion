@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/whatsapp/campaigns.css') }}?v=1">
<main class="msg91-page">
    <header class="msg91-page__heading">
        <div><p class="msg91-eyebrow">WhatsApp campaigns</p><h1>Campaign Records</h1><p>MSG91 delivery history. “Accepted” is not the same as delivered.</p></div>
        @if($canManageMsg91)<a class="msg91-button msg91-button--secondary" href="{{ route('admin.msg91.admin.index') }}">Sender access</a>@endif
    </header>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <section class="msg91-stats">
        <article><span>Campaigns</span><strong>{{ $campaigns->total() }}</strong></article>
        <article><span>Accepted</span><strong>{{ $campaigns->sum('accepted_count') }}</strong></article>
        <article><span>Delivered</span><strong>{{ $campaigns->sum('delivered_count') }}</strong></article>
        <article><span>Failed / unknown</span><strong>{{ $campaigns->sum('failed_count') + $campaigns->sum('unknown_count') }}</strong></article>
    </section>
    <form class="msg91-filter" method="get"><input name="search" value="{{ request('search') }}" placeholder="Search campaign"><select name="status"><option value="">All statuses</option>@foreach(['queued','processing','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst($status) }}</option>@endforeach</select><button class="msg91-button">Filter</button></form>
    <section class="msg91-card msg91-table-wrap"><table><thead><tr><th>Campaign</th><th>Created</th><th>Sender</th><th>Template</th><th>Eligible</th><th>Accepted</th><th>Delivered</th><th>Status</th><th></th></tr></thead><tbody>@forelse($campaigns as $campaign)<tr><td><strong>{{ $campaign->name }}</strong><small>{{ $campaign->creator?->name }}</small></td><td>{{ $campaign->created_at?->format('d M Y, H:i') }}</td><td>{{ $campaign->sender?->integrated_number }}</td><td>{{ $campaign->template?->name }}<small>{{ $campaign->template?->language }}</small></td><td>{{ $campaign->eligible_count }}</td><td>{{ $campaign->accepted_count }}</td><td>{{ $campaign->delivered_count }}</td><td><span class="msg91-status msg91-status--{{ $campaign->status }}">{{ ucfirst($campaign->status) }}</span></td><td><a class="msg91-button msg91-button--small" href="{{ route('admin.msg91.campaigns.show',$campaign) }}">View</a></td></tr>@empty<tr><td colspan="9" class="msg91-empty">No campaigns are visible to you yet.</td></tr>@endforelse</tbody></table></section>
    {{ $campaigns->links() }}
</main>
@endsection
