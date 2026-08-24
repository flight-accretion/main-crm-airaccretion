@extends('admin.layouts.header')
@section('content')
<div class="block justify-between page-header md:flex"><h3 class="text-xl font-semibold">IVR Agent Mapping</h3></div>
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
<div class="box"><div class="box-header flex justify-between"><div class="box-title">Agent Mapping</div><a href="{{ route('admin.ivr.agents.create') }}" class="ti-btn ti-btn-primary">Add Agent</a></div><div class="box-body overflow-auto"><table class="table whitespace-nowrap"><thead><tr><th>VI Agent Number</th><th>VI Agent Name</th><th>Mapped CRM User</th><th>Status</th><th>Remarks</th><th>Action</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->vi_agent_number ?: '-' }}</td><td>{{ $item->vi_agent_name ?: '-' }}</td><td>{{ $item->mappedUser ? $item->mappedUser->name . ' (' . ($item->mappedUser->contact_number ?: '-') . ')' : '-' }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td><td>{{ $item->remarks ?: '-' }}</td><td><a href="{{ route('admin.ivr.agents.show',$item) }}" class="ti-btn ti-btn-sm ti-btn-info">View</a> <a href="{{ route('admin.ivr.agents.edit',$item) }}" class="ti-btn ti-btn-sm ti-btn-primary">Edit</a></td></tr>@empty<tr><td colspan="6" class="text-center">No agent mappings configured.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
