@extends('admin.layouts.header')

@section('content')
    @php
        $metadata = $case->metadata ?? [];
        $resolution = data_get($metadata, 'resolution');
    @endphp

    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 text-[1.125rem] font-semibold">Vendor Follow-up Detail</h3>
            <p class="text-[0.75rem] text-gray-500 mt-1">Lead: {{ optional(optional($case->lead)->client)->name ?? $case->lead_id }}</p>
        </div>
        <a href="{{ route('admin.operations.vendor-followups.index') }}" class="ti-btn ti-btn-light mt-3 md:mt-0">Back</a>
    </div>

    @if (session('success'))
        <div class="alert alert-success mb-4">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger mb-4">{{ $errors->first() }}</div>
    @endif

    <div class="grid grid-cols-12 gap-6">
        <div class="xl:col-span-7 col-span-12">
            <div class="box custom-box">
                <div class="box-header"><div class="box-title">Summary</div></div>
                <div class="box-body grid grid-cols-12 gap-4">
                    <div class="md:col-span-6 col-span-12"><strong>Vendor</strong><div>{{ data_get($metadata, 'vendor_name_snapshot', 'N/A') }}</div></div>
                    <div class="md:col-span-6 col-span-12"><strong>Purpose</strong><div>{{ data_get($metadata, 'purpose', 'N/A') }}</div></div>
                    <div class="md:col-span-6 col-span-12"><strong>Status</strong><div>{{ $resolution ? ucfirst($resolution) : ucfirst(str_replace('_', ' ', $case->status)) }}</div></div>
                    <div class="md:col-span-6 col-span-12"><strong>Next Follow-up</strong><div>{{ optional($case->next_followup_at)->format('d-m-Y') ?? '-' }}</div></div>
                    <div class="md:col-span-6 col-span-12"><strong>Tentative Ride</strong><div>{{ data_get($metadata, 'tentative_ride_at') ? \Carbon\Carbon::parse(data_get($metadata, 'tentative_ride_at'))->format('d-m-Y H:i') : '-' }}</div></div>
                    <div class="md:col-span-6 col-span-12"><strong>Confirmed Ride</strong><div>{{ data_get($metadata, 'confirmed_ride_at') ? \Carbon\Carbon::parse(data_get($metadata, 'confirmed_ride_at'))->format('d-m-Y H:i') : '-' }}</div></div>
                    <div class="col-span-12"><strong>Vendor Response</strong><div>{{ data_get($metadata, 'vendor_response', '-') ?: '-' }}</div></div>
                    <div class="col-span-12"><strong>Initial Remark</strong><div>{{ $case->note ?: '-' }}</div></div>
                </div>
            </div>

            <div class="box custom-box">
                <div class="box-header"><div class="box-title">History</div></div>
                <div class="box-body">
                    @forelse ($case->activities->sortByDesc('created_at') as $activity)
                        <div class="border-b border-defaultborder py-3">
                            <div class="flex justify-between gap-3">
                                <strong>{{ optional($activity->user)->name ?? 'System' }}</strong>
                                <span class="text-xs text-gray-500">{{ optional($activity->created_at)->format('d-m-Y H:i') }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">{{ ucwords(str_replace('_', ' ', $activity->action)) }}</div>
                            <div class="mt-2">{{ $activity->note ?: '-' }}</div>
                        </div>
                    @empty
                        <div class="text-gray-500">No history found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="xl:col-span-5 col-span-12">
            @if ($canManage && !$isClosed)
                <div class="box custom-box">
                    <div class="box-header"><div class="box-title">Reschedule</div></div>
                    <div class="box-body">
                        <form method="POST" action="{{ route('admin.operations.vendor-followups.reschedule', $case) }}">
                            @csrf
                            <label class="ti-form-label">New Follow-up Date</label>
                            <input type="date" name="followup_date" class="ti-form-input mb-3" min="{{ now()->toDateString() }}" required>
                            <label class="ti-form-label">Reason</label>
                            <textarea name="reason" rows="3" class="ti-form-input mb-3" required></textarea>
                            <button class="ti-btn ti-btn-warning" type="submit">Reschedule</button>
                        </form>
                    </div>
                </div>

                <div class="box custom-box">
                    <div class="box-header"><div class="box-title">Complete</div></div>
                    <div class="box-body">
                        <form method="POST" action="{{ route('admin.operations.vendor-followups.complete', $case) }}">
                            @csrf
                            <div class="grid grid-cols-12 gap-3">
                                <div class="md:col-span-6 col-span-12">
                                    <label class="ti-form-label">Confirmed Date</label>
                                    <input type="date" name="confirmed_ride_date" class="ti-form-input" required>
                                </div>
                                <div class="md:col-span-6 col-span-12">
                                    <label class="ti-form-label">Confirmed Time</label>
                                    <input type="time" name="confirmed_ride_time" class="ti-form-input" required>
                                </div>
                                <div class="col-span-12">
                                    <label class="ti-form-label">Vendor Response</label>
                                    <textarea name="vendor_response" rows="4" class="ti-form-input" required></textarea>
                                </div>
                                <div class="col-span-12"><button class="ti-btn ti-btn-success" type="submit">Complete</button></div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="box custom-box">
                    <div class="box-header"><div class="box-title">Cancel</div></div>
                    <div class="box-body">
                        <form method="POST" action="{{ route('admin.operations.vendor-followups.cancel', $case) }}">
                            @csrf
                            <label class="ti-form-label">Cancellation Reason</label>
                            <textarea name="reason" rows="3" class="ti-form-input mb-3" required></textarea>
                            <button class="ti-btn ti-btn-danger" type="submit" onclick="return confirm('Cancel this Vendor Follow-up?')">Cancel Follow-up</button>
                        </form>
                    </div>
                </div>
            @elseif (!$canManage)
                <div class="box custom-box"><div class="box-body text-gray-500">Read-only access.</div></div>
            @endif
        </div>
    </div>
@endsection