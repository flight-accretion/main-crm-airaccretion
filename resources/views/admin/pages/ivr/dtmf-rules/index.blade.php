@extends('admin.layouts.header')
@section('content')
<div class="block justify-between page-header md:flex"><h3 class="text-xl font-semibold">IVR DTMF Rules</h3></div>
@if(session('success'))<div class="alert alert-success mb-4">{{ session('success') }}</div>@endif
<div class="box"><div class="box-header flex justify-between"><div class="box-title">DTMF Rules</div><a href="{{ route('admin.ivr.dtmf-rules.create') }}" class="ti-btn ti-btn-primary">Add DTMF Rule</a></div><div class="box-body overflow-auto"><table class="table whitespace-nowrap"><thead><tr><th>Call Type</th><th>DTMF</th><th>Name</th><th>Matches</th><th>Users</th><th>Mode</th><th>Status</th><th>Action</th></tr></thead><tbody>
@forelse($items as $item)<tr><td>{{ $item->callType->name ?? 'All' }}</td><td>{{ $item->dtmf_value }}</td><td>{{ $item->name }} @if($item->is_default)<span class="badge bg-warning">Default</span>@endif</td><td>{{ implode(', ',$item->match_values ?? []) ?: '-' }}</td><td>{{ $item->users->pluck('user.name')->filter()->implode(', ') ?: '-' }}</td><td>{{ ucfirst($item->assignment_mode) }}</td><td>{{ $item->is_active ? 'Active' : 'Inactive' }}</td><td><a href="{{ route('admin.ivr.dtmf-rules.show',$item) }}" class="ti-btn ti-btn-sm ti-btn-info">View</a> <a href="{{ route('admin.ivr.dtmf-rules.edit',$item) }}" class="ti-btn ti-btn-sm ti-btn-primary">Edit</a></td></tr>@empty<tr><td colspan="8" class="text-center">No DTMF rules configured.</td></tr>@endforelse
</tbody></table></div></div>
@endsection
