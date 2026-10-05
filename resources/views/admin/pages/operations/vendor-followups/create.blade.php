@extends('admin.layouts.header')

@section('content')
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 text-[1.125rem] font-semibold">Create Vendor Follow-up</h3>
            <p class="text-[0.75rem] text-gray-500 mt-1">Lead: {{ optional($lead->client)->name ?? $lead->id }}</p>
        </div>
        <a href="{{ route('admin.operations.vendor-followups.index') }}" class="ti-btn ti-btn-light mt-3 md:mt-0">Back</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="box custom-box">
        <div class="box-body">
            <form method="POST" action="{{ route('admin.operations.vendor-followups.store', $lead) }}" class="grid grid-cols-12 gap-4">
                @csrf
                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">Vendor</label>
                    <select name="vendor_id" class="ti-form-select" required>
                        <option value="">Select vendor</option>
                        @foreach ($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id') === $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">Assigned To</label>
                    <select name="assigned_to" class="ti-form-select">
                        <option value="">Me</option>
                        @foreach ($operationUsers as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-6 col-span-12">
                    <label class="ti-form-label">Purpose</label>
                    <input type="text" name="purpose" class="ti-form-input" value="{{ old('purpose', 'Confirm Ride Date & Time') }}" maxlength="255">
                </div>
                <div class="md:col-span-3 col-span-12">
                    <label class="ti-form-label">Tentative Ride</label>
                    <input type="datetime-local" name="tentative_ride_at" class="ti-form-input" value="{{ old('tentative_ride_at', $tentativeRideAt ? $tentativeRideAt->format('Y-m-d\TH:i') : '') }}">
                </div>
                <div class="md:col-span-3 col-span-12">
                    <label class="ti-form-label">Follow-up Date</label>
                    <input type="date" name="followup_date" class="ti-form-input" min="{{ now()->toDateString() }}" value="{{ old('followup_date', $suggestedFollowupDate->toDateString()) }}" required>
                </div>
                <div class="col-span-12">
                    <label class="ti-form-label">Remarks</label>
                    <textarea name="remarks" class="ti-form-input" rows="4" maxlength="5000">{{ old('remarks') }}</textarea>
                </div>
                <div class="col-span-12 flex gap-2">
                    <button type="submit" class="ti-btn ti-btn-primary">Create Follow-up</button>
                    <a href="{{ route('admin.operations.vendor-followups.index') }}" class="ti-btn ti-btn-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection