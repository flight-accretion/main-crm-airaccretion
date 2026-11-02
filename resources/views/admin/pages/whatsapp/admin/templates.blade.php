@extends('admin.layouts.header')
@section('content')
<link rel="stylesheet" href="{{ asset('assets/admin/css/crm/whatsapp/admin.css') }}?v=2">
<main class="msg91-page">
    <header class="msg91-page__heading">
        <div>
            <p class="msg91-eyebrow">MSG91 WhatsApp</p>
            <h1>Template registry</h1>
            <p>Register MSG91-approved templates against a verified sender. A template is usable only when it is approved, enabled, and freshly synced.</p>
        </div>
        @if($canManageAll)<a class="msg91-button msg91-button--secondary" href="{{ route('admin.msg91.admin.index') }}">Sender access</a>@else<a class="msg91-button msg91-button--secondary" href="{{ route('admin.msg91.campaigns.index') }}">Campaign records</a>@endif
    </header>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <section class="msg91-card p-4 mb-3">
        <h2 class="h5 mb-1">Sync approved templates</h2>
        <p class="text-muted mb-3">Enable template sync only after MSG91 credentials are configured. It reads template metadata; it does not send any WhatsApp message.</p>
        <div class="d-flex flex-wrap gap-2">
            @forelse($senders as $sender)
                <form method="post" action="{{ route('admin.msg91.templates.sync', $sender) }}">@csrf
                    <button class="msg91-button msg91-button--secondary" type="submit">Sync {{ $sender->integrated_number }} · {{ $sender->user?->name }}</button>
                </form>
            @empty
                <p class="mb-0 text-muted">No verified, active sender is available. Configure one in Sender access first.</p>
            @endforelse
        </div>
    </section>

    <section class="msg91-card p-4 mb-3">
        <h2 class="h5">Create a template in MSG91</h2>
        <p class="text-muted">The template is submitted for MSG91 approval. It remains unavailable for campaigns until approval and a later sync confirm its status.</p>
        <form method="post" action="{{ route('admin.msg91.templates.create') }}" class="msg91-admin-form">
            @csrf
            <div class="row">
                <label class="col-md-4">Template name<input required name="name" placeholder="e.g. accretion_demo_followup" pattern="[a-z0-9_]+"></label>
                <label class="col-md-2">Language<input required name="language" value="en"></label>
                <label class="col-md-3">Category<select name="category"><option value="UTILITY">Utility</option><option value="MARKETING">Marketing</option><option value="AUTHENTICATION">Authentication</option></select></label>
                <label class="col-md-3">Verified sender<select required name="sender_id"><option value="">Select sender</option>@foreach($senders as $sender)<option value="{{ $sender->id }}">{{ $sender->integrated_number }} · {{ $sender->user?->name }}</option>@endforeach</select></label>
            </div>
            <label>Body <textarea required name="body" rows="3" placeholder="Hello @{{1}}, this is a message from Accretion Aviation."></textarea></label>
            <label>Footer <input name="footer" maxlength="60" placeholder="Optional"></label>
            <p class="mb-1 mt-2"><strong>Body variable mapping</strong> <span class="text-muted">(map every @{{1}}, @{{2}} variable used in the body)</span></p>
            <div class="row">@foreach(range(1, 5) as $index)<label class="col-md">Body {{ $index }}<select name="body_{{ $index }}"><option value="">Not used</option>@foreach(['customer_name'=>'Customer name','product_name'=>'Product name','service_date'=>'Service date','next_followup_date'=>'Next follow-up date','salesperson_name'=>'Salesperson name'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>@endforeach</div>
            <button class="msg91-button mt-3" type="submit">Submit template to MSG91</button>
        </form>
    </section>

    @php($formTemplate = $editingTemplate ?? null)
    <section class="msg91-card p-4 mb-3">
        <h2 class="h5">{{ $formTemplate ? 'Edit template mapping' : 'Register an approved template' }}</h2>
        <p class="text-muted">Use this only for a template that is already approved in MSG91. Sales users can manage their own templates; Admin roles can manage central templates.</p>
        <form method="post" action="{{ route('admin.msg91.templates.save') }}" class="msg91-admin-form">
            @csrf
            @if($formTemplate)<input type="hidden" name="template_id" value="{{ $formTemplate->id }}">@endif
            <div class="row">
                <label class="col-md-4">Template name<input required name="name" value="{{ old('name', $formTemplate?->name) }}" placeholder="e.g. booking_update"></label>
                <label class="col-md-2">Language<input required name="language" value="{{ old('language', $formTemplate?->language ?? 'en') }}" placeholder="en"></label>
                <label class="col-md-3">Category<input name="category" value="{{ old('category', $formTemplate?->category) }}" placeholder="UTILITY"></label>
                <label class="col-md-3">MSG91 template ID<input name="provider_template_id" value="{{ old('provider_template_id', $formTemplate?->provider_template_id) }}"></label>
            </div>
            <div class="row">
                <label class="col-md-4">Verified sender<select required name="sender_id"><option value="">Select sender</option>@foreach($senders as $sender)<option value="{{ $sender->id }}" @selected(old('sender_id', $formTemplate?->bindings->first()?->sender_id)===$sender->id)>{{ $sender->integrated_number }} · {{ $sender->user?->name }}</option>@endforeach</select></label>
                <label class="col-md-3">MSG91 status<select name="provider_status">@foreach(['approved','pending','rejected','disabled','unknown'] as $status)<option value="{{ $status }}" @selected(old('provider_status', $formTemplate?->status ?? 'approved')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
                <label class="col-md-3 d-flex align-items-center gap-2 pt-4"><input type="checkbox" name="crm_enabled" value="1" @checked(old('crm_enabled', $formTemplate?->crm_enabled))> Make available in CRM campaigns</label>
            </div>
            <p class="mb-1 mt-2"><strong>Body variable mapping</strong> <span class="text-muted">(only map variables actually present in the MSG91 template)</span></p>
            <div class="row">
                @foreach(range(1, 5) as $index)<label class="col-md">Body {{ $index }}<select name="body_{{ $index }}"><option value="">Not used</option>@foreach(['customer_name'=>'Customer name','product_name'=>'Product name','service_date'=>'Service date','next_followup_date'=>'Next follow-up date','salesperson_name'=>'Salesperson name'] as $value=>$label)<option value="{{ $value }}" @selected(old('body_'.$index, data_get($formTemplate?->variable_schema, 'body_'.$index.'.source'))===$value)>{{ $label }}</option>@endforeach</select></label>@endforeach
            </div>
            <button class="msg91-button mt-3" type="submit">Save template mapping</button>
        </form>
    </section>

    <section class="msg91-card msg91-table-wrap">
        <table>
            <thead><tr><th>Template</th><th>Language</th><th>CRM</th><th>Sender mappings</th><th>Last updated</th><th></th></tr></thead>
            <tbody>
                @forelse($templates as $template)
                    <tr>
                        <td><strong>{{ $template->name }}</strong><small>{{ $template->category ?? 'No category' }} · {{ $template->status }}</small></td>
                        <td>{{ $template->language }}</td>
                        <td>{{ $template->crm_enabled ? 'Enabled' : 'Disabled' }}</td>
                        <td>@forelse($template->bindings as $binding)<div>{{ $binding->sender?->integrated_number }} · {{ $binding->provider_status }}<small>{{ optional($binding->last_synced_at)->format('d M Y H:i') }}</small></div>@empty<span class="text-muted">Not mapped</span>@endforelse</td>
                        <td>{{ optional($template->updated_at)->format('d M Y H:i') }}</td>
                        <td>@if($canManageAll || (string) $template->owner_user_id === (string) auth()->id())<a class="msg91-button msg91-button--small" href="{{ route('admin.msg91.templates.index', ['edit' => $template->id]) }}">Edit</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No templates registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    {{ $templates->links() }}
</main>
@endsection
