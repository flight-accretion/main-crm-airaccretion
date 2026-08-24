@extends('admin.layouts.header')
@section('content')
<div class="box"><div class="box-header"><div class="box-title">IVR Agent Mapping Details</div></div><div class="box-body"><p><b>VI Agent Number:</b> {{ $item->vi_agent_number ?: '-' }}</p><p><b>VI Agent Name:</b> {{ $item->vi_agent_name ?: '-' }}</p><p><b>Mapped CRM User:</b> {{ $item->mappedUser ? $item->mappedUser->name . ' (' . ($item->mappedUser->contact_number ?: '-') . ')' : '-' }}</p><p><b>Status:</b> {{ $item->is_active ? 'Active' : 'Inactive' }}</p><p><b>Remarks:</b> {{ $item->remarks ?: '-' }}</p><div class="mt-4"><a href="{{ route('admin.ivr.agents.edit',$item) }}" class="ti-btn ti-btn-primary">Edit</a> <a href="{{ route('admin.ivr.agents.index') }}" class="ti-btn ti-btn-light">Back</a></div></div></div>
@endsection
