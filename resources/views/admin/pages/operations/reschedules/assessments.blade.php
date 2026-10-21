@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/reschedules.css') }}">
<style>.rc-assessment-search{display:flex;gap:12px;align-items:end;margin:20px 0}.rc-assessment-search label{flex:1}.rc-assessment-form{display:grid;gap:12px}.rc-assessment-form .rc-button{white-space:normal}.rc-assessments th:last-child{width:30%}@media(max-width:1100px){.rc-assessments td[data-label=Actions]{text-align:left}.rc-assessments .rc-assessment-form{width:100%}}</style>
<div class="crm-reschedules">
    <header class="rc-heading"><div><h1>Reschedule Expiry Assessments</h1><p>Refund eligibility review &middot; no automatic payments</p></div></header>
    @if(session('success'))<p class="rc-notice" role="status">{{ session('success') }}</p>@endif
    @if($errors->any())<p class="rc-notice rc-danger" role="alert">{{ $errors->first() }}</p>@endif
    <nav class="rc-tabs" aria-label="Assessment state">@foreach(['pending' => 'Pending', 'completed' => 'Completed'] as $key => $label)<a href="{{ route('admin.operations.reschedule-assessments.index', ['state' => $key]) }}" @if(($filters['state'] ?? 'pending') === $key) aria-current="page" @endif>{{ $label }}</a>@endforeach</nav>
    <form class="rc-assessment-search" method="GET"><input type="hidden" name="state" value="{{ $filters['state'] ?? 'pending' }}"><label>Search<input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Client or phone"></label><button type="submit" class="rc-button rc-primary">Filter</button></form>
    <section class="rc-list rc-assessments"><h2>Expiry assessment cases</h2><table><thead><tr><th>Client</th><th>Original date</th><th>Window expired</th><th>Assigned to</th><th>Assessment</th><th>Action</th></tr></thead><tbody>
    @foreach($cases as $case)
        @php $parent = $parents->get(data_get($case->metadata, 'reschedule_case_id')); $original = data_get($parent?->metadata, 'reschedule.original.from_date'); @endphp
        <tr><td data-label="Client"><a class="rc-client" href="{{ route('admin.leads.view', $case->lead_id) }}" target="_blank" rel="noopener">{{ $case->lead?->client?->name ?: 'Not recorded' }}</a><small>{{ $case->lead?->client?->contact_number }}</small></td>
            <td data-label="Original date">{{ $original ? \Carbon\Carbon::parse($original)->format('d M Y') : 'Not recorded' }}</td>
            <td data-label="Window expired">{{ data_get($parent?->metadata, 'reschedule.expires_on') ?: 'Not recorded' }}</td>
            <td data-label="Assigned to">{{ $case->assignee?->name ?: 'Unassigned' }}</td>
            <td data-label="Assessment"><span class="rc-badge">{{ str_replace('_', ' ', data_get($case->metadata, 'assessment.decision', 'Pending review')) }}</span><small>{{ $case->note }}</small></td>
            <td data-label="Actions">
                @unless($case->completed_at)
                <form method="POST" action="{{ route('admin.operations.reschedule-assessments.update', $case) }}" class="rc-assessment-form">@csrf
                    <label>Decision<select name="decision"><option value="more_information">More information needed</option><option value="refund_review_required">{{ $canDecide ? 'Refer to refund processing' : 'Recommend refund review' }}</option>@if($canDecide)<option value="no_refund">No refund due</option>@endif</select></label>
                    <label>Assessment note<textarea name="note" required maxlength="5000" rows="3"></textarea></label>
                    @if($canDecide)<a class="rc-button" href="{{ route('admin.refunds.index') }}">Existing refund processing <i class="ri-external-link-line" aria-hidden="true"></i></a>@endif
                    <button class="rc-button rc-primary" type="submit"><i class="ri-save-line" aria-hidden="true"></i> Record assessment</button>
                </form>
                @else<span class="rc-badge rc-success">{{ data_get($case->metadata, 'superseded') ? 'No longer required' : 'Assessment completed' }}</span>@endunless
            </td></tr>
    @endforeach
    </tbody></table>@if($cases->isEmpty())<p class="rc-empty">No assessments match these filters.</p>@endif</section>
    <div class="rc-pagination">{{ $cases->onEachSide(1)->links() }}</div>
</div>
@endsection
