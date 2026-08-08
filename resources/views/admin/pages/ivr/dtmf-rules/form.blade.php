@extends('admin.layouts.header')
@section('content')
@php($editing = $item->exists)
<div class="block justify-between page-header md:flex"><h3 class="text-xl font-semibold">{{ $editing ? 'Edit' : 'Add' }} DTMF Rule</h3></div>
<div class="box"><div class="box-body"><form method="POST" action="{{ $editing ? route('admin.ivr.dtmf-rules.update',$item) : route('admin.ivr.dtmf-rules.store') }}">@csrf @if($editing) @method('PUT') @endif
<div class="grid grid-cols-12 gap-4">
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Call Type</label><select name="ivr_call_type_id" class="ti-form-select"><option value="">All Call Types</option>@foreach($callTypes as $ct)<option value="{{ $ct->id }}" {{ old('ivr_call_type_id',$item->ivr_call_type_id) === $ct->id ? 'selected' : '' }}>{{ $ct->code }} - {{ $ct->name }}</option>@endforeach</select></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">DTMF Value *</label><input name="dtmf_value" class="form-control" value="{{ old('dtmf_value',$item->dtmf_value) }}" required></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Name *</label><input name="name" class="form-control" value="{{ old('name',$item->name) }}" required></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Category</label><input name="category" class="form-control" value="{{ old('category',$item->category) }}"></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Assignment Mode</label><select name="assignment_mode" class="ti-form-select"><option value="balanced" {{ old('assignment_mode',$item->assignment_mode ?: 'balanced') === 'balanced' ? 'selected' : '' }}>Balanced</option><option value="random" {{ old('assignment_mode',$item->assignment_mode) === 'random' ? 'selected' : '' }}>Random</option></select></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Sort Order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order',$item->sort_order ?? 0) }}"></div>
<div class="col-span-12"><label class="ti-form-label">Raw DTMF Match Values (comma separated)</label><textarea name="match_values_text" class="form-control" rows="3">{{ old('match_values_text',$matchValuesText) }}</textarea><small>Example: 5, -1-&gt;5. Add exact VI values here; no code change is required later.</small></div>
<div class="col-span-12"><label class="ti-form-label">Eligible CRM Users</label><select name="user_ids[]" class="ti-form-select" multiple size="8">@foreach($users as $user)<option value="{{ $user->id }}" {{ in_array($user->id, old('user_ids',$selectedUsers)) ? 'selected' : '' }}>{{ $user->name }}</option>@endforeach</select></div>
<div class="col-span-12"><label class="me-4"><input type="checkbox" name="is_default" value="1" {{ old('is_default',$item->is_default) ? 'checked' : '' }}> Default fallback rule</label><label><input type="checkbox" name="is_active" value="1" {{ old('is_active',$item->exists ? $item->is_active : true) ? 'checked' : '' }}> Active</label></div>
</div><div class="mt-4"><button class="ti-btn ti-btn-primary">Save</button> <a href="{{ route('admin.ivr.dtmf-rules.index') }}" class="ti-btn ti-btn-light">Cancel</a></div></form></div></div>
@endsection
