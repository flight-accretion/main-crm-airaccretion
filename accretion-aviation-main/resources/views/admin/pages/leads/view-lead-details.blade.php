@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">Lead Details</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.clients.index') }}">
                    Leads
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.clients.view', $client->id) }}">
                    {{ $client->name }}
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50" aria-current="page">
                Lead Details
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Client Information</h5>
                    <a href="{{ route('admin.client.view', $client->id) }}" class="ti-btn ti-btn-secondary-full !py-1 !px-2 ti-btn-wave">
                        <i class="ri-arrow-left-line"></i> Back to Client
                    </a>
                </div>
                <div class="box-body">
                    <div class="grid lg:grid-cols-4 gap-6">
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->name }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->email }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->contact_number }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                            <p class="text-gray-800 dark:text-white">{{ $country ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Lead Information</h5>
                </div>
                <div class="box-body">
                    <div class="grid lg:grid-cols-4 gap-6">
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                            <p class="text-gray-800 dark:text-white">
                                @if(!empty($lead->services))
                                    {{ implode(', ', $lead->services) }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Number of Passengers</label>
                            <p class="text-gray-800 dark:text-white">{{ $lead->number_of_passengers ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Dates</label>
                            <p class="text-gray-800 dark:text-white">
                                @if($lead->ride_dates)
                                    {{ date('d-m-Y', strtotime($lead->ride_dates['from_date'])) }} to {{ date('d-m-Y', strtotime($lead->ride_dates['to_date'])) }}
                                @else
                                    N/A
                                @endif
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Representative</label>
                            <p class="text-gray-800 dark:text-white">{{ $lead->representative->name ?? 'Not assigned' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Created Date</label>
                            <p class="text-gray-800 dark:text-white">{{ $lead->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                            <p class="text-gray-800 dark:text-white">{{ $lead->description ?: 'N/A' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($selectedServices->count() > 0 || $selectedExtraServices->count() > 0)
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Selected Services & Pricing</h5>
                </div>
                <div class="box-body">
                    <div class="grid lg:grid-cols-2 gap-6">
                        @if($selectedServices->count() > 0)
                        <div>
                            <h6 class="text-lg font-semibold mb-3">Services</h6>
                            <div class="space-y-2">
                                @foreach($selectedServices as $service)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span>{{ $service->service }}</span>
                                    <span class="font-semibold">{{ number_format($service->service_amount, 2) }}</span>
                                </div>
                                @endforeach
                                <div class="flex justify-between items-center p-3 bg-primary/10 rounded font-semibold">
                                    <span>Total Services</span>
                                    <span>{{ number_format($totalServiceAmount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if($selectedExtraServices->count() > 0)
                        <div>
                            <h6 class="text-lg font-semibold mb-3">Extra Services</h6>
                            <div class="space-y-2">
                                @foreach($selectedExtraServices as $extraService)
                                <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <span>{{ $extraService->extra_service }}</span>
                                    <span class="font-semibold">{{ number_format($extraService->extra_service_amount, 2) }}</span>
                                </div>
                                @endforeach
                                <div class="flex justify-between items-center p-3 bg-primary/10 rounded font-semibold">
                                    <span>Total Extra Services</span>
                                    <span>{{ number_format($totalExtraServiceAmount, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    @if($totalAmount > 0)
                    <div class="mt-6 p-4 bg-success/10 rounded-lg">
                        <div class="flex justify-between items-center text-lg font-bold">
                            <span>Grand Total</span>
                            <span>Rs{{ number_format($totalAmount, 2) }}</span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($followups->count() > 0)
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header justify-between">
                    <div class="box-title">
                        Follow-up History
                    </div>
                    <span class="badge bg-info/10 text-info">{{ $followups->count() }} Follow-ups</span>
                </div>
                <div class="box-body">
                    <div class="space-y-3">
                        @foreach($followups as $followup)
                        <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
                            <div class="grid grid-cols-12 gap-4 items-center">
                                <div class="col-span-2">
                                    <h6 class="text-[.875rem] font-semibold mb-1">
                                        Follow-up #{{ $loop->iteration }}
                                    </h6>
                                    <span class="text-[#8c9097] dark:text-white/50 text-xs">
                                        {{ $followup->created_at->format('d-m-Y H:i') }}
                                    </span>
                                </div>
                                <div class="col-span-6">
                                    <span class="font-medium text-gray-700 dark:text-gray-300 text-sm">Notes:</span>
                                    <p class="text-gray-800 dark:text-white text-sm">{{ $followup->followup_note ?: 'No notes provided' }}</p>
                                </div>
                                <div class="col-span-2">
                                    @if($followup->next_followup_date)
                                        <span class="font-medium text-gray-700 dark:text-gray-300 text-sm block">Next Follow-up:</span>
                                        <p class="text-gray-800 dark:text-white text-sm">{{ $followup->next_followup_date->format('d-m-Y') }}</p>
                                    @else
                                        <span class="text-gray-500 text-sm">No next date</span>
                                    @endif
                                </div>
                                <div class="col-span-2 text-right">
                                    <span class="badge bg-primary/10 text-primary text-xs">
                                        {{ $followup->followedBy->name ?? 'Unknown' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any necessary scripts here
        });
    </script>
@endsection
