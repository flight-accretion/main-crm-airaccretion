@extends('admin.layouts.header')
@section('content')
<div class="box"><div class="box-header"><div class="box-title">IVR Call Type Details</div></div><div class="box-body"><p><b>Code:</b> {{ $item->code }}</p><p><b>Name:</b> {{ $item->name }}</p><p><b>Category:</b> {{ $item->category ?: '-' }}</p><p><b>Assignment:</b> {{ ucfirst($item->assignment_mode) }}</p><p><b>Users:</b> {{ $item->users->pluck('user.name')->filter()->implode(', ') ?: '-' }}</p><p><b>Status:</b> {{ $item->is_active ? 'Active' : 'Inactive' }}</p><div class="mt-4"><a href="{{ route('admin.ivr.call-types.edit',$item) }}" class="ti-btn ti-btn-primary">Edit</a> <a href="{{ route('admin.ivr.call-types.index') }}" class="ti-btn ti-btn-light">Back</a></div></div></div>
@endsection
