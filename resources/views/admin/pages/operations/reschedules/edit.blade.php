@extends('admin.layouts.header')

@section('content')
    <div class="container-fluid">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="font-semibold text-lg">Edit Reschedule</h4>
                <p class="text-sm text-gray-500">
                    Update ride trip details for the pending Operations reschedule task.
                </p>
            </div>
            <a href="{{ route('admin.operations.reschedules.index') }}" class="ti-btn ti-btn-light">
                Back
            </a>
        </div>

        <div class="box mb-4">
            <div class="box-header">
                <div class="box-title">Customer</div>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label">Name</label>
                        <div class="font-medium">{{ optional($client)->name ?? 'N/A' }}</div>
                    </div>
                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label">Phone</label>
                        <div class="font-medium">{{ optional($client)->contact_number ?? 'N/A' }}</div>
                    </div>
                    <div class="xl:col-span-4 md:col-span-6 col-span-12">
                        <label class="ti-form-label">Lead</label>
                        @if($lead)
                            <a href="{{ route('admin.leads.view', $lead->id) }}" class="text-primary" target="_blank">
                                {{ $lead->id }}
                            </a>
                        @else
                            <div class="font-medium">N/A</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.operations.reschedules.update', $ride) }}">
            @csrf

            <div class="box">
                <div class="box-header">
                    <div class="box-title">Trip Details</div>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-3 md:col-span-6 col-span-12">
                            <label class="ti-form-label">From Date</label>
                            <input
                                type="datetime-local"
                                name="from_date"
                                value="{{ optional($ride->from_date)->format('Y-m-d\TH:i') }}"
                                class="form-control"
                            >
                            @error('from_date')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="xl:col-span-3 md:col-span-6 col-span-12">
                            <label class="ti-form-label">To Date</label>
                            <input
                                type="datetime-local"
                                name="to_date"
                                value="{{ optional($ride->to_date)->format('Y-m-d\TH:i') }}"
                                class="form-control"
                            >
                            @error('to_date')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="xl:col-span-3 md:col-span-6 col-span-12">
                            <label class="ti-form-label">From Place</label>
                            <input
                                type="text"
                                name="from_place"
                                value="{{ old('from_place', $ride->from_place) }}"
                                class="form-control"
                            >
                            @error('from_place')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="xl:col-span-3 md:col-span-6 col-span-12">
                            <label class="ti-form-label">To Place</label>
                            <input
                                type="text"
                                name="to_place"
                                value="{{ old('to_place', $ride->to_place) }}"
                                class="form-control"
                            >
                            @error('to_place')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="xl:col-span-3 md:col-span-6 col-span-12">
                            <label class="ti-form-label">Total Time</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="total_time"
                                value="{{ old('total_time', $ride->total_time) }}"
                                class="form-control"
                            >
                            @error('total_time')
                                <div class="text-danger text-sm mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="xl:col-span-3 md:col-span-6 col-span-12 flex items-end">
                            <label class="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    name="no_date"
                                    value="1"
                                    class="form-check-input"
                                    {{ old('no_date', $ride->is_tba) ? 'checked' : '' }}
                                >
                                <span>No Date / TBA</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="box-footer flex justify-end gap-2">
                    <a href="{{ route('admin.operations.reschedules.index') }}" class="ti-btn ti-btn-light">
                        Cancel
                    </a>
                    <button type="submit" class="ti-btn ti-btn-primary">
                        Save
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
