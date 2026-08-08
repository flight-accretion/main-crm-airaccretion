@extends('admin.layouts.header')
@section('content')
@php($editing = $item->exists)
<div class="block justify-between page-header md:flex"><h3 class="text-xl font-semibold">{{ $editing ? 'Edit' : 'Add' }} IVR Call Type</h3></div>
<div class="box"><div class="box-body"><form method="POST" action="{{ $editing ? route('admin.ivr.call-types.update',$item) : route('admin.ivr.call-types.store') }}">@csrf @if($editing) @method('PUT') @endif
<div class="grid grid-cols-12 gap-4">
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">VI Call Type Code *</label><input name="code" class="form-control" value="{{ old('code',$item->code) }}" required>@error('code')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Name *</label><input name="name" class="form-control" value="{{ old('name',$item->name) }}" required></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Category</label><input name="category" class="form-control" value="{{ old('category',$item->category) }}"></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Assignment Mode *</label><select name="assignment_mode" class="ti-form-select"><option value="balanced" {{ old('assignment_mode',$item->assignment_mode ?: 'balanced') === 'balanced' ? 'selected' : '' }}>Balanced</option><option value="random" {{ old('assignment_mode',$item->assignment_mode) === 'random' ? 'selected' : '' }}>Random</option></select></div>
<div class="col-span-12 md:col-span-4"><label class="ti-form-label">Sort Order</label><input type="number" min="0" name="sort_order" class="form-control" value="{{ old('sort_order',$item->sort_order ?? 0) }}"></div>
<div class="col-span-12"><label class="ti-form-label">Eligible CRM Users</label><select name="user_ids[]" class="ti-form-select" multiple size="8">@foreach($users as $user)<option value="{{ $user->id }}" {{ in_array($user->id, old('user_ids',$selectedUsers)) ? 'selected' : '' }}>{{ $user->name }} ({{ $user->userType->user_type ?? '' }})</option>@endforeach</select></div>
<div class="col-span-12"><label><input type="checkbox" name="is_active" value="1" {{ old('is_active',$item->exists ? $item->is_active : true) ? 'checked' : '' }}> Active</label></div>
</div><div class="mt-4"><button class="ti-btn ti-btn-primary" type="submit">Save</button> <a href="{{ route('admin.ivr.call-types.index') }}" class="ti-btn ti-btn-light">Cancel</a></div></form></div></div>
@endsection
