@extends('admin.layouts.header')
@section('content')
<div class="block justify-between page-header md:flex"><h3 class="text-xl font-semibold">IVR Call Types</h3></div>
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
<div class="box"><div class="box-header flex justify-between"><div class="box-title">Call Types</div><a href="{{ route('admin.ivr.call-types.create') }}" class="ti-btn ti-btn-primary">Add Call Type</a></div>
<div class="box-body overflow-auto"><table class="table whitespace-nowrap"><thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Mode</th><th>Users</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->code }}</td><td>{{ $item->name }}</td><td>{{ $item->category ?: '-' }}</td><td>{{ ucfirst($item->assignment_mode) }}</td><td>{{ $item->users->pluck('user.name')->filter()->implode(', ') ?: '-' }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td><td><a href="{{ route('admin.ivr.call-types.show',$item) }}" class="ti-btn ti-btn-sm ti-btn-info">View</a> <a href="{{ route('admin.ivr.call-types.edit',$item) }}" class="ti-btn ti-btn-sm ti-btn-primary">Edit</a></td></tr>
@empty<tr><td colspan="7" class="text-center">No call types configured.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
