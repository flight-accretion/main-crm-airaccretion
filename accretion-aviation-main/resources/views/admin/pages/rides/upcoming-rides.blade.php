@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
    <div>
        <h3
            class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
            Upcoming Rides</h3>
    </div>
    <ol class="flex items-center whitespace-nowrap min-w-0">
        <li class="text-[0.813rem] ps-[0.5rem]">
            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate"
                href="javascript:void(0);">
                Rides
                <i
                    class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
            </a>
        </li>
        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50 "
            aria-current="page">
            Upcoming Rides
        </li>
    </ol>
</div>
<!-- Page Header Close -->
@if (session('success'))
<div class="alert alert-success mb-4">
    {{ session('success') }}
</div>
@endif
@if (session('error'))
<div class="alert alert-danger mb-4">
    {{ session('error') }}
</div>
@endif
<!-- Page Header Close -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box custom-box">
            <div class="box-header">
                <div class="box-title">
                    Search Filters
                </div>
                <button type="button" class="ti-btn ti-btn-sm ti-btn-outline-primary" id="toggle-filters">
                    <i class="ti ti-chevron-up" id="filter-icon"></i>
                </button>
            </div>
            <div class="box-body" id="filter-section">
                <form class="ti-custom-validation" method="GET" action="{{ route('admin.rides.upcoming') }}"
                    id="filter-form">
                    <div class="grid grid-cols-12 gap-4">
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">From Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm rounded-sm" name="from_date"
                                    value="{{ $currentFilters['from_date'] ?? '' }}">
                            </div>
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">To Date</label>
                            <div class="input-group">
                                <div class="input-group-text text-[#8c9097] dark:text-white/50">
                                    <i class="ri-calendar-line"></i>
                                </div>
                                <input type="date" class="form-control form-control-sm rounded-sm" name="to_date"
                                    value="{{ $currentFilters['to_date'] ?? '' }}">
                            </div>
                        </div>
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="sales-rep-select" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Sales
                                Representative</label>
                            {{-- <select name="representative_user_id" id="sales-rep-select"
                                class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All</option>
                                @foreach ($salesReps as $rep)
                                <option value="{{ $rep->id }}" {{ ($currentFilters['representative_user_id'] ?? ''
                                    )==$rep->id ? 'selected' : '' }}>
                                    {{ $rep->name }}</option>
                                @endforeach
                            </select> --}}
                            <select name="representative_user_id" id="sales-rep-select"
                                class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All</option>
                                <option value="unassigned" {{ ($currentFilters['representative_user_id'] ?? ''
                                    )==='unassigned' ? 'selected' : '' }}>
                                    Unassigned
                                </option>
                                @foreach ($salesReps as $rep)
                                <option value="{{ $rep->id }}" {{ ($currentFilters['representative_user_id'] ?? ''
                                    )==$rep->id ? 'selected' : '' }}>
                                    {{ $rep->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        {{-- <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="product-select"
                                class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Product</label>
                            <select name="product_id" id="product-select"
                                class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All Products</option>
                                @foreach ($products as $product)
                                <option value="{{ $product->id }}" {{ ($currentFilters['product_id'] ?? '' )==$product->
                                    id ? 'selected' : '' }}>
                                    {{ $product->product }}
                                </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="tba-select" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Time
                                Status</label>
                            <select name="is_tba" id="tba-select" class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All</option>
                                <option value="1" {{ ($currentFilters['is_tba'] ?? '' )==='1' ? 'selected' : '' }}>
                                    TBA (unconfirmed time)
                                </option>
                                <option value="0" {{ ($currentFilters['is_tba'] ?? '' )==='0' ? 'selected' : '' }}>
                                    Confirmed time
                                </option>
                            </select>
                        </div> --}}
                        <div class="xl:col-span-3 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label for="status-select" class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Ride
                                Status</label>
                            <select name="status" id="status-select" class="ti-form-select rounded-sm form-control-sm">
                                <option value="">All</option>
                                @foreach ($availableStatuses as $statusId => $statusName)
                                <option value="{{ $statusId }}" {{ ($currentFilters['status'] ?? '' )==$statusId
                                    ? 'selected' : '' }}>
                                    {{ $statusName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="xl:col-span-2 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">&nbsp;</label>
                            <div class="flex gap-2">
                                <button type="submit" class="ti-btn bg-theme ti-btn-primary-full !py-1 !px-2">
                                    Apply Filters
                                </button>
                                <button type="button" class="ti-btn ti-btn-outline-secondary !py-1 !px-2"
                                    onclick="clearFilters()">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-8 col-span-12">
        <div class="box custom-box">
            <div class="box-header">
                <div class="box-title">Calendar</div>
            </div>
            <div class="box-body">
                <div class="mb-3">
                    <div id="calendar-legend" class="md:flex block items-center gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span
                                style="display:inline-block;width:12px;height:12px;background:#8e44ad;border-radius:3px"></span>
                            <span>TBA (To Be Announced)</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                style="display:inline-block;width:12px;height:12px;background:#3ce7e7ff;border-radius:3px"></span>
                            <span>Today</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                style="display:inline-block;width:12px;height:12px;background:#f39c12;border-radius:3px"></span>
                            <span>Within a week</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                style="display:inline-block;width:12px;height:12px;background:#3788d8;border-radius:3px"></span>
                            <span>Future</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                style="display:inline-block;width:12px;height:12px;background:#6c757d;border-radius:3px"></span>
                            <span>Past</span>
                        </div>
                    </div>
                </div>
                <div id="calendar2"></div>
            </div>
        </div>
    </div>
    <div class="xl:col-span-4 col-span-12">
        <div class="box">
            <div class="box-header justify-between">
                <div class="box-title">
                    Ride Details
                </div>
            </div>
            <div class="box-body">
                <!-- Compact & Attractive Selected Ride Details Section -->
                <div id="selected-ride-details" class="grid grid-cols-12" style="display: none;">
                    <div
                        class="flex items-center justify-between pb-3 border-b border-blue-200/40 dark:border-gray-600 ">
                        <h6 class="text-blue-700 dark:text-blue-300 font-bold text-base flex items-center gap-3">
                            <div
                                class="w-8 h-8 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center mr-3">
                                <i class="bx bx-calendar-check text-blue-600 dark:text-blue-400"></i>
                            </div>
                            <span id="ride-details-title">Upcoming Week Rides</span>
                        </h6>
                        <button onclick="closeSelectedRideDetails()"
                            class="w-8 h-8 bg-gray-100 dark:bg-gray-700 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-full flex items-center justify-center text-gray-500 hover:text-red-500 transition-all duration-200">
                            <i class="bx bx-x text-lg"></i>
                        </button>
                    </div>
                    <div id="ride-details-content" class="space-y-3">
                        <!-- Selected ride details will be inserted here -->
                    </div>
                </div>

                <ul class="list-none daily-task-card">
                    @forelse($ridesData as $ride)
                    <li>
                        <div
                            class="bg-white dark:bg-gray-800 border border-blue-200 dark:border-gray-600 rounded-lg shadow-sm hover:shadow-md transition-all duration-200 overflow-hidden">
                            <div class="p-3">
                                <!-- Header with Name and Expand Button -->
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-2">
                                        <div
                                            class="w-6 h-6 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center">
                                            <i class="bx bx-user text-blue-600 dark:text-blue-400 text-sm"></i>
                                        </div>
                                        <div>
                                            <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">
                                                {{ $ride['client_name'] }}</h6>

                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">

                                        <button
                                            class="expand-btn w-8 h-8 bg-gray-100 dark:bg-gray-700 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-full flex items-center justify-center transition-colors duration-200">
                                            <i
                                                class="bx bx-chevron-down text-gray-600 dark:text-gray-400 text-sm transform transition-transform duration-200"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Quick Info Row -->
                                <div class="mt-2 pt-2 mb-2 border-t border-gray-100 dark:border-gray-600">
                                    <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1">
                                        <div class="flex items-center justify-between space-x-1 gap-2 mb-3">
                                            <div class="flex items-center space-x-1 gap-2">
                                                <i class="bx bx-phone"></i>
                                                <span>{{ $ride['contact_number'] }}</span>
                                            </div>
                                            <div class="flex items-center space-x-1 gap-2">
                                                <i class="ri-calendar-line"></i>
                                                <span>
                                                    {{ $ride['ride_date'] }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between space-x-1 gap-2 mb-2">
                                            <div class="flex items-center space-x-1 gap-2">
                                                <i class="bx bx-time"></i>
                                                <span class="whitespace-nowrap">{{ $ride['ride_time'] }}</span>
                                            </div>
                                            <span
                                                class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-medium flex-shrink-0 whitespace-nowrap">
                                                {{ isset($availableStatuses[$ride['status_id']]) ?
                                                $availableStatuses[$ride['status_id']] : 'Pending' }}
                                            </span>
                                        </div>
                                        <div class="flex items-center space-x-1 gap-2">
                                            <i class="bx bx-map-pin"></i>
                                            <span class="truncate">{{ $ride['from_place'] }} → {{
                                                $ride['to_place'] }}</span>
                                        </div>Upcoming Rides

                                    </div>
                                </div>

                                <!-- Expandable Details -->
                                <div class="rides-details hidden">
                                    <div class="pt-2 border-t border-gray-200 dark:border-gray-600 space-y-2">
                                        <div class="grid grid-cols-2 gap-3 text-xs">
                                            <div>
                                                <span
                                                    class="font-medium text-gray-700 dark:text-gray-300">Service:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['service_names'] ?: 'N/A' }}</p>
                                            </div>
                                            <div>
                                                <span
                                                    class="font-medium text-gray-700 dark:text-gray-300">Product:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['product_names'] ?: 'N/A' }}</p>
                                            </div>


                                            @if ($ride['extra_service_names'])
                                            <div class="text-xs">
                                                <span class="font-medium text-gray-700 dark:text-gray-300">Extra
                                                    Service:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['extra_service_names'] }}</p>
                                            </div>
                                            @endif


                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">From:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['from_place'] }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">To:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['to_place'] }}</p>
                                            </div>



                                            @if ($ride['number_of_passengers'])
                                            <div>
                                                <span
                                                    class="font-medium text-gray-700 dark:text-gray-300">Passengers:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['number_of_passengers'] }}</p>
                                            </div>
                                            @endif
                                            @if ($ride['occasion'])
                                            <div>
                                                <span
                                                    class="font-medium text-gray-700 dark:text-gray-300">Occasion:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['occasion'] }}</p>
                                            </div>
                                            @endif



                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">Pending
                                                    Amount:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    ₹{{ $ride['pending_amount'] }}</p>
                                            </div>
                                            <div>
                                                <span class="font-medium text-gray-700 dark:text-gray-300">Sales
                                                    Rep:</span>
                                                <p class="text-gray-600 dark:text-gray-400 mt-1">
                                                    {{ $ride['sales_person_name'] }}</p>
                                            </div>


                                            <!-- @if ($ride['description'])
                                                        <div class="text-xs">
                                                            <span
                                                                class="font-medium text-gray-700 dark:text-gray-300">Description:</span>
                                                            <p
                                                                class="text-gray-600 dark:text-gray-400 mt-1 bg-gray-50 dark:bg-gray-700 p-2 rounded">
                                                                {{ $ride['description'] }}</p>
                                                        </div>
                                                    @endif -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </li>
                    @empty
                    <li>
                        <div class="box border border-gray-200 shadow-none mb-0">
                            <div class="box-body text-center py-8">
                                <p class="text-gray-500">No upcoming rides found.</p>
                            </div>
                        </div>
                    </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@stop

@push('scripts')
<style>
    /* Custom styles for enhanced Selected Ride Details panel */
    #selected-ride-details {
        animation: fadeInUp 0.3s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Enhanced card hover effects */
    #ride-details-content .shadow-sm:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.1);
    }

    /* Gradient border effect for the main container */
    #selected-ride-details>div {
        /* background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(147, 51, 234, 0.05) 50%, rgba(236, 72, 153, 0.05) 100%);
                       backdrop-filter: blur(10px); */
        min-height: 0;
    }

    /* Dark mode enhancements */
    .dark #selected-ride-details>div {
        /* background: linear-gradient(135deg, rgba(31, 41, 55, 0.9) 0%, rgba(55, 65, 81, 0.9) 50%, rgba(75, 85, 99, 0.9) 100%); */
        min-height: 0;
    }

    /* Loading animation enhancements */
    @keyframes pulse-ring {
        0% {
            transform: scale(0.8);
            opacity: 1;
        }

        100% {
            transform: scale(1.2);
            opacity: 0;
        }
    }

    .animate-ping {
        animation: pulse-ring 1.5s cubic-bezier(0, 0, 0.2, 1) infinite;
    }

    /* Icon container animations */
    #ride-details-content .w-6.h-6 {
        transition: all 0.2s ease;
    }

    #ride-details-content .shadow-sm:hover .w-6.h-6 {
        transform: scale(1.1);
    }

    /* Calendar customizations */
    .fc-daygrid-day {
        cursor: pointer;
        transition: background-color 0.2s ease;
        position: relative;
    }

    .fc-daygrid-day:hover {
        background-color: rgba(59, 130, 246, 0.05);
    }

    .ride-count-badge {
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .ride-count-badge:hover {
        transform: translateX(-50%) scale(1.05);
        box-shadow: 0 2px 8px rgba(55, 136, 216, 0.3);
    }

    /* Ride item hover effects */
    .ride-item:hover {
        transform: translateY(-2px);
    }

    /* Compact ride card styles */
    .daily-task-card li:hover {
        transform: translateY(-1px);
    }

    .expand-btn {
        transition: all 0.2s ease;
    }

    .expand-btn:hover {
        transform: scale(1.05);
    }

    .expand-btn i {
        transition: transform 0.2s ease;
    }

    .rotate-180 {
        transform: rotate(180deg);
    }

    /* Better spacing for compact design */
    .rides-details {
        animation: slideDown 0.2s ease-out;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>


<script>
    $(document).ready(function() {
            // Initially hide the ride details
            $('.rides-details').hide();

            // Toggle on expand button click
            $('.expand-btn').on('click', function(e) {
                e.preventDefault();
                const $button = $(this);
                const $icon = $button.find('i');
                const $details = $button.closest('li').find('.rides-details');

                // Toggle the details
                $details.slideToggle(200, function() {
                    // Rotate the icon based on visibility
                    if ($details.is(':visible')) {
                        $icon.addClass('rotate-180');
                        $button.addClass('bg-blue-100 dark:bg-blue-900/50');
                    } else {
                        $icon.removeClass('rotate-180');
                        $button.removeClass('bg-blue-100 dark:bg-blue-900/50');
                    }
                });
            });

            // Initialize Calendar
            initializeCalendar();
        });
        function getFilterParams() {
    const params = new URLSearchParams();
    const fromDate = $('[name="from_date"]').val();
    const toDate   = $('[name="to_date"]').val();
    const salesRep = $('[name="representative_user_id"]').val();
    const status   = $('[name="status"]').val();
    const product  = $('[name="product_id"]').val();
    const tba      = $('[name="is_tba"]').val();

    if (fromDate) params.append('from_date', fromDate);
    if (toDate)   params.append('to_date', toDate);
    if (salesRep) params.append('representative_user_id', salesRep);
    if (status)   params.append('status', status);
    if (product)  params.append('product_id', product);
    if (tba)      params.append('is_tba', tba);

    return params;
}

        function initializeCalendar() {
            // Check if FullCalendar library is loaded
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar library is not loaded. Please ensure FullCalendar is included in your layout.');
                // Try to load FullCalendar from CDN if not available
                loadFullCalendarFromCDN();
                return;
            }

            var calendarEl = document.getElementById('calendar2');
            if (calendarEl) {
                window.calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
                    },
                    events: function(fetchInfo, successCallback, failureCallback) {
                        // Get current filter values
                        // const fromDate = $('[name="from_date"]').val();
                        // const toDate = $('[name="to_date"]').val();
                        // const salesRep = $('[name="representative_user_id"]').val();
                        // const status = $('[name="status"]').val();

                        // // Build URL with filters
                        // let url = "{{ route('admin.rides.calendar.events') }}";
                        // const params = new URLSearchParams();

                        // if (fromDate) params.append('from_date', fromDate);
                        // if (toDate) params.append('to_date', toDate);
                        // if (salesRep) params.append('representative_user_id', salesRep);
                        // if (status) params.append('status', status);

                        // if (params.toString()) {
                        //     url += '?' + params.toString();
                        // }
                        let url = "{{ route('admin.rides.calendar.events') }}";
const params = getFilterParams();
if (params.toString()) {
    url += '?' + params.toString();
}

                        fetch(url)
                            .then(response => response.json())
                            .then(data => {
                                successCallback(data);
                                // Add badges after events are loaded
                                setTimeout(() => {
                                    addRideCountBadges();
                                }, 200);
                            })
                            .catch(error => {
                                console.error('Failed to load calendar events:', error);
                                failureCallback(error);
                            });
                    },
                    dayMaxEvents: false, // Don't limit events per day
                    // Show individual event bars so users can click a specific ride
                    eventDisplay: 'auto',
                    // When a calendar event is clicked, show only that ride
                    eventClick: function(info) {
                        try {
                            info.jsEvent && info.jsEvent.preventDefault();
                            const evt = info.event;
                            const eventObj = {
                                id: evt.id,
                                title: evt.title,
                                start: evt.start ? evt.start.toISOString() : null,
                                end: evt.end ? evt.end.toISOString() : null,
                                extendedProps: evt.extendedProps || {}
                            };
                            // Show only the clicked ride
                            showRidesList([eventObj], true);
                        } catch (e) {
                            console.error('Error handling eventClick:', e);
                        }
                    },
                    eventDidMount: function(info) {
                        try {
                            
                            // If event is marked allDay (TBA) add a small TBA badge
                            if (info.event.allDay || info.event.extendedProps.isTba) {
                                const el = info.el;
                                
                                // For list view events
                                if (info.view && info.view.type && info.view.type.startsWith('list')) {
                                    // Wait a bit for FullCalendar to finish rendering
                                    setTimeout(() => {
                                        try {
                                            // Find the title element in list view
                                            const titleElement = el.querySelector('.fc-list-event-title') || 
                                                            el.querySelector('.fc-list-item-title') || 
                                                            el.querySelector('.fc-event-title');
                                            
                                            // Avoid adding duplicate labels
                                            if (titleElement && !titleElement.querySelector('.tba-label')) {
                                                const tbaSpan = document.createElement('span');
                                                tbaSpan.className = 'tba-label';
                                                tbaSpan.textContent = ' (TBA)';
                                                tbaSpan.style.cssText = 'color:#8e44ad;font-weight:600;margin-left:6px;';
                                                titleElement.appendChild(tbaSpan);
                                            }
                                        } catch (err) {
                                            console.error('Error adding TBA to list view:', err);
                                        }
                                    }, 50);
                                } 
                                // For other views (dayGrid, timeGrid)
                                else {
                                    const tbaBadge = document.createElement('span');
                                    tbaBadge.textContent = 'TBA';
                                    tbaBadge.style.cssText = 'background:#8e44ad;color:#fff;padding:2px 4px;border-radius:3px;font-size:10px;margin-left:6px;';
                                    
                                    const titleNode = el.querySelector('.fc-event-title');
                                    if (titleNode) {
                                        titleNode.appendChild(tbaBadge);
                                    }
                                }
                            }
                        } catch (e) {
                            console.error('Error in eventDidMount:', e);
                        }
                    },

                    datesSet: function(info) {
                        // Add ride count badges after calendar renders
                        setTimeout(() => {
                            addRideCountBadges();
                        }, 100);
                    },
                    dateClick: function(info) {
                        // Handle date click - show rides for that date
                        const clickedDate = info.dateStr;
                        showRidesForDate(clickedDate);
                    },
                    height: 'auto'
                });
                window.calendar.render();

                // Store calendar instance globally for potential future use
                window.aviationCalendar = calendar;

                console.log('Calendar initialized successfully');

                // Load upcoming week rides initially
                loadUpcomingWeekRides();
            } else {
                console.error('Calendar element with ID "calendar2" not found');
            }
        }

        function loadFullCalendarFromCDN() {
            // Load FullCalendar CSS
            const cssLink = document.createElement('link');
            cssLink.rel = 'stylesheet';
            cssLink.href = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css';
            document.head.appendChild(cssLink);

            // Load FullCalendar JS
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js';
            script.onload = function() {
                console.log('FullCalendar loaded from CDN');
                initializeCalendar();
            };
            script.onerror = function() {
                console.error('Failed to load FullCalendar from CDN');
            };
            document.head.appendChild(script);
        }

        // Function to add ride count badges to calendar days
        function addRideCountBadges() {
            if (!window.aviationCalendar) return;

            const events = window.aviationCalendar.getEvents();
            const dayCells = document.querySelectorAll('.fc-daygrid-day');

            dayCells.forEach(dayCell => {
                const dateStr = dayCell.getAttribute('data-date');
                if (!dateStr) return;

                const cellDate = new Date(dateStr);
                const dayEvents = events.filter(event => {
                    const eventDate = new Date(event.start);
                    return eventDate.toDateString() === cellDate.toDateString();
                });

                // Remove existing badge if any
                const existingBadge = dayCell.querySelector('.ride-count-badge');
                if (existingBadge) {
                    existingBadge.remove();
                }

                // ✅ Keep showing FullCalendar events — do NOT hide them
                if (dayEvents.length > 0) {
                    const badge = document.createElement('div');
                    badge.className = 'ride-count-badge';
                    badge.style.cssText = `
        position: absolute; 
        bottom: 2px; 
        left: 50%; 
        transform: translateX(-50%); 
        background: #3788d8; 
        color: white; 
        border-radius: 12px; 
        padding: 2px 6px; 
        font-size: 10px; 
        font-weight: bold; 
        text-align: center; 
        cursor: pointer;
        display: inline-block;
        z-index: 10;
      `;
                    badge.textContent = dayEvents.length + ' ride' + (dayEvents.length > 1 ? 's' : '');
                    badge.onclick = function(e) {
                        e.stopPropagation();
                        showRidesForDate(dateStr); // Custom click handler
                    };

                    // If any event for this day is TBA, tint the day cell border/background slightly
                    const hasTba = dayEvents.some(ev => ev.extendedProps && ev.extendedProps.isTba);
                    if (hasTba) {
                        dayCell.style.boxShadow = 'inset 0 0 0 2px rgba(142,68,173,0.12)';
                    } else {
                        dayCell.style.boxShadow = '';
                    }

                    // Ensure the day cell has relative positioning
                    dayCell.style.position = 'relative';
                    dayCell.appendChild(badge);
                }
            });
        }


        // Function to show rides for a specific date
        function showRidesForDate(dateStr) {
            // Get current filter values
            // const fromDate = $('[name="from_date"]').val();
            // const toDate = $('[name="to_date"]').val();
            // const salesRep = $('[name="representative_user_id"]').val();
            // const status = $('[name="status"]').val();

            // // Build URL with filters
            // let apiUrl = "{{ route('admin.rides.calendar.events') }}";
            // const params = new URLSearchParams();

            // if (fromDate) params.append('from_date', fromDate);
            // if (toDate) params.append('to_date', toDate);
            // if (salesRep) params.append('representative_user_id', salesRep);
            // if (status) params.append('status', status);

            // if (params.toString()) {
            //     apiUrl += '?' + params.toString();
            // }

            let url = "{{ route('admin.rides.calendar.events') }}";
const params = getFilterParams();
if (params.toString()) {
    url += '?' + params.toString();
}

            fetch(url)
                .then(response => response.json())
                .then(events => {
                    // Filter events for the selected date
                    const selectedDate = new Date(dateStr);
                    const ridesForDate = events.filter(event => {
                        const eventDate = new Date(event.start);
                        return eventDate.toDateString() === selectedDate.toDateString();
                    });

                    // Show list of rides for that date (including "No Rides Found" message if empty)
                    showRidesList(ridesForDate, true);
                })
                .catch(error => {
                    console.error('Error fetching rides for date:', error);
                });
        }

        // Function to load upcoming week rides initially
        function loadUpcomingWeekRides() {
            const today = new Date();
            const nextWeek = new Date();
            nextWeek.setDate(today.getDate() + 7);

            // Get current filter values
            // const fromDate = $('[name="from_date"]').val();
            // const toDate = $('[name="to_date"]').val();
            // const salesRep = $('[name="representative_user_id"]').val();
            // const status = $('[name="status"]').val();

            // // Build URL with filters
            // let apiUrl = "{{ route('admin.rides.calendar.events') }}";
            // const params = new URLSearchParams();

            // if (fromDate) params.append('from_date', fromDate);
            // if (toDate) params.append('to_date', toDate);
            // if (salesRep) params.append('representative_user_id', salesRep);
            // if (status) params.append('status', status);

            // if (params.toString()) {
            //     apiUrl += '?' + params.toString();
            // }

            let url = "{{ route('admin.rides.calendar.events') }}";
const params = getFilterParams();
if (params.toString()) {
    url += '?' + params.toString();
}

            fetch(url)
                .then(response => response.json())
                .then(events => {
                    // Filter events for upcoming week
                    const upcomingWeekRides = events.filter(event => {
                        const eventDate = new Date(event.start);
                        return eventDate >= today && eventDate <= nextWeek;
                    });

                    // Only show rides list if there are upcoming rides, otherwise hide the section
                    if (upcomingWeekRides.length > 0) {
                        showRidesList(upcomingWeekRides);
                    } else {
                        // Hide the ride details section completely when no upcoming rides
                        const detailsContainer = document.getElementById('selected-ride-details');
                        if (detailsContainer) {
                            detailsContainer.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching upcoming week rides:', error);
                });
        }

        // Function to display rides list in the ride details section
        function showRidesList(rides, showNoRidesMessage = true) {
            console.log('showRidesList called with rides:', rides);

            const detailsContainer = document.getElementById('selected-ride-details');
            const contentContainer = document.getElementById('ride-details-content');
            const titleElement = document.getElementById('ride-details-title');

            if (detailsContainer && contentContainer) {
                // Hide the main server-rendered rides list so only the selected rides are visible
                const mainList = document.querySelector('.daily-task-card');
                if (mainList) {
                    mainList.style.display = 'none';
                }

                detailsContainer.style.display = 'block';

                // Update title
                if (titleElement) {
                    titleElement.textContent = rides.length === 0 ? 'No Rides Found' :
                        `${rides.length} Ride${rides.length > 1 ? 's' : ''} Found`;
                }

                if (rides.length === 0 && showNoRidesMessage) {
                    contentContainer.innerHTML = `
          <div class="flex flex-col items-center justify-center py-8 text-center">
            <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-full flex items-center justify-center mb-4">
              <i class="bx bx-calendar-x text-gray-400 text-2xl"></i>
            </div>
            <p class="text-gray-600 dark:text-gray-400 font-medium mb-2">No rides found</p>
            <p class="text-gray-500 dark:text-gray-500 text-sm">No rides scheduled for the selected date</p>
          </div>
        `;
                    return;
                } else if (rides.length === 0 && !showNoRidesMessage) {
                    // Don't show anything, just return
                    return;
                }

                let html = `<div class="grid grid-cols-12 gap-6 pt-3">`;

                rides.forEach((ride, index) => {
                    const props = ride.extendedProps;
                    // const status_name = props.statusName;
                    // console.log(`Status Name for Ride ${index + 1}:`, status_name);
                    console.log(`Ride ${index + 1}:`, ride);

                    html += `
          <div class="xl:col-span-12 col-span-12 bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600 shadow-sm hover:shadow-md transition-all duration-200 cursor-pointer ride-item hover:border-blue-300 dark:hover:border-blue-600" 
               onclick="loadRideDetails('${ride.id}', '${props.statusName || ''}')" 
               data-ride-id="${ride.id}">
            <div class="flex items-center gap-3 pb-3 border-b mb-2">  
                <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center mr-2">
                  <i class="bx bx-user text-blue-600 dark:text-blue-400 text-sm"></i>
                </div>
                <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">${ride.title}</h6>              
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400 space-y-1 mt-4">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">${props.rideTime || 'N/A'}</span>
                <span class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-medium flex-shrink-0">
                  ${props.statusName || 'Pending'}
                </span>
              </div>
              <div class="flex items-center gap-2">
                  <i class="bx bx-map-pin mr-1"></i>
                  <span>${props.fromPlace} → ${props.toPlace}</span>
                </div>
              <div class="flex items-center gap-2">
                <i class="bx bx-phone mr-1"></i>
                <span>${props.contactNumber}</span>
              </div>
              <div class="flex items-center gap-2">
                    <i class="bx bx-briefcase mr-1"></i>
                    <span>Service: ${props.serviceNames || 'N/A'}</span>
                </div>
                <div class="flex items-center gap-2">
                    <i class="bx bx-plus mr-1"></i>
                    <span>Extra: ${props.extraServiceNames || 'N/A'}</span>
                </div>
              <div class="flex items-center gap-2">
                <i class="bx bx-rupee mr-1"></i>
                <span>Pending Amount: ₹${props.pendingAmount || '0.00'}</span>
              </div>
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100 dark:border-gray-600">
              <div class="flex items-center justify-between">
                <span class="text-xs text-gray-500 dark:text-gray-400">Click to view details</span>
                <i class="bx bx-chevron-right text-gray-400 text-sm"></i>
              </div>
            </div>
          </div>
        `;
                });

                html += `</div>`;
                contentContainer.innerHTML = html;

                // Add smooth entrance animation
                detailsContainer.style.opacity = '0';
                detailsContainer.style.transform = 'translateY(-10px)';

                setTimeout(() => {
                    detailsContainer.style.transition = 'all 0.3s ease-out';
                    detailsContainer.style.opacity = '1';
                    detailsContainer.style.transform = 'translateY(0)';
                }, 50);
            }
        }

        function loadRideDetails(rideId, statusName = '') {
            console.log('loadRideDetails called with rideId:', rideId, 'statusName:', statusName);

            // Show loading state with attractive animation
            const detailsContainer = document.getElementById('selected-ride-details');
            const contentContainer = document.getElementById('ride-details-content');

            if (detailsContainer && contentContainer) {
                detailsContainer.style.display = 'block';

                // Attractive loading state
                contentContainer.innerHTML = `
        <div class="flex flex-col items-center justify-center py-8 text-center">
          <div class="relative mb-4">
            <div class="w-12 h-12 border-4 border-blue-200 dark:border-blue-800 border-t-blue-600 dark:border-t-blue-400 rounded-full animate-spin"></div>
            <div class="absolute inset-0 w-12 h-12 border-4 border-transparent border-r-purple-400 rounded-full animate-ping"></div>
          </div>
          <p class="text-gray-600 dark:text-gray-400 text-sm font-medium">Loading ride details...</p>
          <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Please wait a moment</p>
        </div>
      `;

                // Add smooth entrance animation
                detailsContainer.style.opacity = '0';
                detailsContainer.style.transform = 'translateY(-10px)';

                setTimeout(() => {
                    detailsContainer.style.transition = 'all 0.3s ease-out';
                    detailsContainer.style.opacity = '1';
                    detailsContainer.style.transform = 'translateY(0)';
                }, 50);

                // Fetch ride details
                const detailsApiUrl = "{{ url('/admin/rides/api/ride-details') }}/" + rideId;
                console.log('Fetching from URL:', detailsApiUrl);

                fetch(detailsApiUrl)
                    .then(response => {
                        console.log('Response status:', response.status);
                        if (!response.ok) {
                            throw new Error('Failed to fetch ride details - Status: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Ride details received:', data);
                        displayRideDetails(data, statusName);
                    })
                    .catch(error => {
                        console.error('Error fetching ride details:', error);
                        contentContainer.innerHTML = `
            <div class="flex flex-col items-center justify-center py-8 text-center">
              <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center mb-4">
                <i class="bx bx-error text-red-600 dark:text-red-400 text-2xl"></i>
              </div>
              <p class="text-red-600 dark:text-red-400 font-medium mb-2">Error loading ride details</p>
              <p class="text-gray-500 dark:text-gray-400 text-sm">Please try again later</p>
              <p class="text-gray-400 text-xs mt-2">Error: ${error.message}</p>
            </div>
          `;
                    });
            } else {
                console.error('Required DOM elements not found');
            }
        }

        function displayRideDetails(ride, statusName = '') {
            try {
                const contentContainer = document.getElementById('ride-details-content');
                const titleElement = document.getElementById('ride-details-title');

                if (!contentContainer) {
                    console.error('Content container not found');
                    return;
                }

                // Use statusName from parameter if provided, otherwise fall back to ride.status_name
                const displayStatus = statusName || ride.status_name || 'Pending';

                // Update title to show ride details
                if (titleElement) {
                    titleElement.textContent = `${ride.client_name || 'Unknown Client'} - Ride Details`;
                }

                let html = `
      <!-- Back Button -->
      <div class="mb-3 mt-3">
        <button onclick="loadUpcomingWeekRides()" class="flex items-center text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm font-medium">
          <i class="bx bx-arrow-back mr-2"></i>
          Back to Rides List
        </button>
      </div>
      
      <!-- Quick Info Cards Row -->
      <div class="grid grid-cols-12 gap-3 bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-600 shadow-sm hover:shadow-md transition-shadow duration-200">
                <!-- Client Info Card -->
                <div class="xxl:col-span-12 xl:col-span-12 col-span-12 border-b">
                    <div class="flex items-center mb-2 gap-2 justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-6 h-6 bg-blue-100 dark:bg-blue-900/50 rounded-full flex items-center justify-center mr-2">
                                <i class="bx bx-user text-blue-600 dark:text-blue-400 text-sm"></i>
                            </div>
                            <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Client</h6>
                        </div>
                        <div>
                            <span class="text-xs px-2 py-1 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-medium">
                                ${displayStatus}
                            </span>
                        </div>
                    </div>
          <div class="space-y-1 mb-2">
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Name:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium ml-1">${ride.client_name || 'N/A'}</span>
            </div>
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Contact:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium ml-1">${ride.contact_number || 'N/A'}</span>
            </div>
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Pending Amount:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium ml-1">₹${ride.pending_amount || '0.00'}</span>
            </div>
          </div>
        </div>

        <!-- Schedule Info Card -->
        <div class="xxl:col-span-12 xl:col-span-12 col-span-12 border-b">
          <div class="flex items-center mb-2 gap-2">
            <div class="w-6 h-6 bg-green-100 dark:bg-green-900/50 rounded-full flex items-center justify-center mr-2">
              <i class="bx bx-calendar text-green-600 dark:text-green-400 text-sm"></i>
            </div>
            <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Schedule</h6>
          </div>
          <div class="space-y-1 mb-2">
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Date:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium ml-1">${ride.ride_date || 'N/A'}</span>
            </div>
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Time:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium ml-1">${ride.ride_time || 'N/A'}</span>
            </div>
          </div>
        </div>

        <!-- Route Card -->
        <div class="xxl:col-span-12 xl:col-span-12 col-span-12 border-b">
          <div class="flex items-center mb-2 gap-2">
            <div class="w-6 h-6 bg-purple-100 dark:bg-purple-900/50 rounded-full flex items-center justify-center mr-2">
              <i class="bx bx-map text-purple-600 dark:text-purple-400 text-sm"></i>
            </div>
            <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Route${ride.total_trips > 1 ? 's' : ''}</h6>
            ${ride.total_trips > 1 ? `<span class="ml-2 px-2 py-1 bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200 text-xs rounded-full">${ride.total_trips} trips</span>` : ''}
          </div>
          ${ride.trips && ride.trips.length > 0 ? ride.trips.map((trip, index) => `
                                                            <div class="flex items-center justify-between mb-2 ${index > 0 ? 'mt-3 pt-3 border-t border-gray-200 dark:border-gray-600' : ''}">
                                                              ${ride.trips.length > 1 ? `<div class="text-xs text-gray-500 dark:text-gray-400 font-medium mb-1">Trip ${index + 1}</div>` : ''}
                                                              <div class="flex items-center justify-between w-full ${ride.trips.length > 1 ? 'mt-1' : ''}">
                                                                <div class="flex-1 text-center">
                                                                  <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">From</div>
                                                                  <div class="text-xs text-gray-800 dark:text-gray-200 font-medium bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">${trip.from_place}</div>
                                                                  ${trip.from_date !== 'N/A' ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${trip.from_date}</div>` : ''}
                                                                </div>
                                                                <div class="mx-3">
                                                                  <i class="bx bx-right-arrow-alt text-gray-400 text-lg"></i>
                                                                </div>
                                                                <div class="flex-1 text-center">
                                                                  <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">To</div>
                                                                  <div class="text-xs text-gray-800 dark:text-gray-200 font-medium bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">${trip.to_place}</div>
                                                                  ${trip.to_date !== 'N/A' ? `<div class="text-xs text-gray-500 dark:text-gray-400 mt-1">${trip.to_date}</div>` : ''}
                                                                </div>
                                                              </div>
                                                            </div>
                                                          `).join('') : `
                                                            <div class="flex items-center justify-between mb-2">
                                                              <div class="flex-1 text-center">
                                                                <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">From</div>
                                                                <div class="text-xs text-gray-800 dark:text-gray-200 font-medium bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">${ride.from_place}</div>
                                                              </div>
                                                              <div class="mx-3">
                                                                <i class="bx bx-right-arrow-alt text-gray-400 text-lg"></i>
                                                              </div>
                                                              <div class="flex-1 text-center">
                                                                <div class="text-xs text-gray-600 dark:text-gray-400 mb-1">To</div>
                                                                <div class="text-xs text-gray-800 dark:text-gray-200 font-medium bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded">${ride.to_place}</div>
                                                              </div>
                                                            </div>
                                                          `}
        </div>

        <!-- Services & Additional Info Card -->
        <div class="xxl:col-span-12 xl:col-span-12 col-span-12 border-b">
          <div class="flex items-center mb-2 gap-2">
            <div class="w-6 h-6 bg-orange-100 dark:bg-orange-900/50 rounded-full flex items-center justify-center mr-2">
              <i class="bx bx-cog text-orange-600 dark:text-orange-400 text-sm"></i>
            </div>
            <h6 class="font-semibold text-gray-800 dark:text-gray-200 text-sm">Services & Details</h6>
          </div>
          <div class="space-y-1 mb-2">
            ${ride.service_names && ride.service_names.trim() ? `
                                                              <div class="text-xs">
                                                                <span class="text-gray-600 dark:text-gray-400">Service:</span>
                                                                <span class="text-gray-800 dark:text-gray-200 font-medium">${ride.service_names}</span>
                                                              </div>
                                                            ` : ''}
            ${ride.product_names && ride.product_names.trim() ? `
                                                              <div class="text-xs">
                                                                <span class="text-gray-600 dark:text-gray-400">Product:</span>
                                                                <span class="text-gray-800 dark:text-gray-200 font-medium">${ride.product_names}</span>
                                                              </div>
                                                            ` : ''}
           <div class="text-xs">
  <span class="text-gray-600 dark:text-gray-400">Extra:</span>
  <span class="text-gray-800 dark:text-gray-200 font-medium">
    ${ride.extra_service_names && ride.extra_service_names.trim()
        ? ride.extra_service_names
        : 'N/A'}
  </span>
</div>

            ${ride.number_of_passengers && ride.number_of_passengers > 0 ? `
                                                              <div class="text-xs">
                                                                <span class="text-gray-600 dark:text-gray-400">Passengers:</span> <span class="text-gray-800 dark:text-gray-200 font-medium">${ride.number_of_passengers}</span>
                                                              </div>
                                                            ` : ''}
            ${ride.occasion && ride.occasion.trim() ? `
                                                              <div class="text-xs">
                                                                <span class="text-gray-600 dark:text-gray-400">Occasion:</span>
                                                                <span class="text-gray-800 dark:text-gray-200 font-medium">${ride.occasion}</span>
                                                              </div>
                                                            ` : ''}
            <div class="text-xs">
              <span class="text-gray-600 dark:text-gray-400">Sales Rep:</span>
              <span class="text-gray-800 dark:text-gray-200 font-medium">${ride.sales_person_name || 'N/A'}</span>
            </div>
          </div>
        </div>
      </div>

      
    `;

                contentContainer.innerHTML = html;
            } catch (error) {
                console.error('Error displaying ride details:', error);
                const contentContainer = document.getElementById('ride-details-content');
                if (contentContainer) {
                    contentContainer.innerHTML =
                        '<div class="text-center text-gray-500 dark:text-gray-400 py-8">Error loading ride details. Please try again.</div>';
                }
            }
        }

        // Function to close selected ride details with smooth animation
        function closeSelectedRideDetails() {
            const detailsContainer = document.getElementById('selected-ride-details');
            if (detailsContainer) {
                // Add smooth exit animation
                detailsContainer.style.transition = 'all 0.3s ease-in';
                detailsContainer.style.opacity = '0';
                detailsContainer.style.transform = 'translateY(-10px)';

                setTimeout(() => {
                    // Completely reset the details container styles
                    detailsContainer.style.display = 'none';
                    detailsContainer.style.opacity = '1';
                    detailsContainer.style.transform = 'translateY(0)';
                    detailsContainer.style.transition = 'none';
                    
                    // Restore the main server-rendered rides list
                    const mainList = document.querySelector('.daily-task-card');
                    if (mainList) {
                        mainList.style.display = 'block';
                    }
                }, 300);
            }
        }

        // Test function to check if calendar events are loading
        function testCalendarEvents() {
            const apiUrl = "{{ route('admin.rides.calendar.events') }}";
            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    console.log('Calendar events:', data);
                    if (data.length === 0) {
                        console.log('No events found in the database');
                    } else {
                        console.log(`Found ${data.length} events`);
                    }
                })
                .catch(error => {
                    console.error('Error testing calendar events:', error);
                });
        }

        // Call test function on load (remove this in production)
        $(document).ready(function() {
            setTimeout(testCalendarEvents, 2000);
        });


        // Debug function to check ride data structure
        window.debugRideData = function(ride) {
            console.log('=== RIDE DATA DEBUG ===');
            console.log('Full ride object:', ride);
            console.log('Client name:', ride.client_name, typeof ride.client_name);
            console.log('Contact number:', ride.contact_number, typeof ride.contact_number);
            console.log('Number of passengers:', ride.number_of_passengers, typeof ride.number_of_passengers);
            console.log('Service names:', ride.service_names, typeof ride.service_names);
            console.log('Product names:', ride.product_names, typeof ride.product_names);
            console.log('Extra service names:', ride.extra_service_names, typeof ride.extra_service_names);
            console.log('Occasion:', ride.occasion, typeof ride.occasion);
            console.log('Description:', ride.description, typeof ride.description);
            console.log('======================');
        };

        function clearFilters() {
            $('#filter-form')[0].reset();
            // Refresh calendar after clearing filters
            if (window.calendar) {
                window.calendar.refetchEvents();
            }
            window.location.href = "{{ route('admin.rides.upcoming') }}";
        }

        // Function to refresh calendar when filters change
        function refreshCalendarEvents() {
            if (window.calendar) {
                window.calendar.refetchEvents();
            }
        }

        // Toggle filters
        $(document).ready(function() {
            const filterSection = $('#filter-section');
            const icon = $('#filter-icon');

            // Initially hide filter section and set icon to down
            filterSection.hide();
            icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');

            $('#toggle-filters').on('click', function() {
                if (filterSection.is(':visible')) {
                    filterSection.slideUp();
                    icon.removeClass('ti-chevron-up').addClass('ti-chevron-down');
                } else {
                    filterSection.slideDown();
                    icon.removeClass('ti-chevron-down').addClass('ti-chevron-up');
                }
            });

            // Handle form submission to refresh both list and calendar
            $('#filter-form').on('submit', function(e) {
                e.preventDefault();

                // Get form data
                const formData = new FormData(this);
                const params = new URLSearchParams(formData);

                // Update URL with filters
                const newUrl = "{{ route('admin.rides.upcoming') }}" + (params.toString() ? '?' + params
                    .toString() : '');

                // Update browser URL and reload page
                window.location.href = newUrl;
            });
        });
</script>
@endpush