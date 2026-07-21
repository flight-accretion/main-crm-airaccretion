@extends('admin.layouts.header')

@section('content')
    <!-- Page Header -->
    <div class="block justify-between page-header md:flex">
        <div>
            <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">{{ $client->name }}</h3>
        </div>
        <ol class="flex items-center whitespace-nowrap min-w-0">
            <li class="text-[0.813rem] ps-[0.5rem]">
                <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.client.index') }}">
                    Clients
                    <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
                </a>
            </li>
            <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50" aria-current="page">
                {{ $client->name }}
            </li>
        </ol>
    </div>
    <!-- Page Header Close -->

    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <div class="box">
                <div class="box-header flex justify-between items-center">
                    <h5 class="box-title">Basic Information</h5>
                     <a href="{{ route('admin.clients.create') }}" class="ti-btn ti-btn-primary-full !py-1 !px-2 ti-btn-wave">
                        <i class="ri-add-line"></i> Add Lead
                     </a>
                </div>
                <div class="box-body">
                    <div class="grid lg:grid-cols-4 gap-6">
                        <!-- <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Full Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->name }}</p>
                        </div> -->
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Email Address</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->email }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Phone Number</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->contact_number }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Whatsapp Number</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->alternate_number ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Date of Birth</label>
                            <p class="text-gray-800 dark:text-white">
                                {{ $client->date_of_birth ? date('d-m-Y', strtotime($client->date_of_birth)) : 'N/A' }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Country</label>
                            <p class="text-gray-800 dark:text-white">
                                {{ $country ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">City</label>
                            <p class="text-gray-800 dark:text-white">{{ $cityName ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Address</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->address ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->description ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Status</label>
                            <p class="text-gray-800 dark:text-white">
                                @if($client->status == 1)
                                    <span class="badge bg-success/10 text-success">Active</span>
                                @else
                                    <span class="badge bg-danger/10 text-danger">Inactive</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header justify-between">
                <div class="box-title">
                    Leads History
                </div>
            </div>
            <div class="box-body">
                <div class="tab-content">
                    <div id="leads-history" role="tabpanel" aria-labelledby="leads-history-tab">
                        <ul class="list-unstyled mb-0 upcoming-events-list">
                            @forelse($client->leads as $lead)
                            <li class="mb-4">
                                <div class="border rounded-lg p-4 bg-white dark:bg-gray-800">
                                    <div class="grid grid-cols-12 gap-3">
                                        <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                            <div class="flex items-start justify-between">
                                                <div>
                                                    <h6 class="text-[.875rem] font-semibold mb-1">
                                                        <a href="{{ route('admin.leads.view', $lead->id) }}" class="text-primary">
                                                            Lead #{{ $loop->iteration }}
                                                        </a>
                                                    </h6>
                                                </div>
                                                <div>
                                                    @if($lead->latestFollowup)
                                                        <span class="badge bg-primary/10 text-primary cursor-pointer" title="Follow-up: {{ $lead->latestFollowup->followup_note }} ({{ $lead->latestFollowup->next_followup_date ? $lead->latestFollowup->next_followup_date->format('d-m-Y') : 'No date' }})">
                                                            Follow-up exists
                                                        </span>
                                                    @else
                                                        <span class="badge bg-secondary/10 text-secondary">No follow-up</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                        <div class="xl:col-span-12 lg:col-span-12 md:col-span-12 sm:col-span-12 col-span-12">
                                            <span class="text-[#8c9097] dark:text-white/50 text-sm">
                                                <i class="ri-time-line align-middle me-1 inline-block"></i>
                                                <span class="font-medium">Created:</span> {{ $lead->created_at->format('d-m-Y H:i') }}
                                            </span>
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-3 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services:</label>
                                            <p class="text-gray-800 dark:text-white">
                                                @php
                                                    $serviceNames = $lead->service_names;
                                                    // Debug: Show followups count and service_ids
                                                    $followupsCount = $lead->leadFollowups->count();
                                                    $serviceIds = [];
                                                    foreach($lead->leadFollowups as $followup) {
                                                        if (!empty($followup->service_ids)) {
                                                            $serviceIds[] = $followup->service_ids;
                                                        }
                                                    }
                                                @endphp
                                                @if(!empty($serviceNames))
                                                    {{ implode(', ', $serviceNames) }}
                                                @else
                                                    N/A 
                                                    @if($followupsCount > 0)
                                                        ({{ $followupsCount }} followups, IDs: {{ json_encode($serviceIds) }})
                                                    @endif
                                                @endif
                                            </p>
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-3 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Passengers:</label>
                                            <p class="text-gray-800 dark:text-white">{{ $lead->number_of_passengers }}</p>
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-3 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Service Dates:</label>
                                            <p class="text-gray-800 dark:text-white">
                                                @if($lead->ride_dates)
                                                    {{ date('d-m-Y', strtotime($lead->ride_dates['from_date'])) }} to {{ date('d-m-Y', strtotime($lead->ride_dates['to_date'])) }}
                                                @else
                                                    N/A
                                                @endif
                                            </p>
                                        </div>
                                        <div class="xl:col-span-3 lg:col-span-3 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Representative:</label>
                                            <p class="text-gray-800 dark:text-white">{{ $lead->representative->name ?? 'Not assigned' }}</p>
                                        </div>
                                        <div class="xl:col-span-12 lg:col-span-3 md:col-span-6 sm:col-span-12 col-span-12">
                                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Description:</label>
                                            <p class="text-gray-800 dark:text-white">{{ $lead->description ?: 'N/A' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                            @empty
                            <li>
                                <div class="text-center text-gray-500 py-4">
                                    No leads found for this client
                                </div>
                            </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize any necessary scripts here
        });
    </script>
@endsection