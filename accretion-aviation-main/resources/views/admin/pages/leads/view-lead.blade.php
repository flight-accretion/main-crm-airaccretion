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

            <!-- Enquiries Section -->
        @if($latestLead && $latestFollowup)
        <div class="box">
            <div class="box-header flex justify-between items-center">
                <h5 class="box-title">Latest Follow-up Summary</h5>
            </div>
            <div class="box-body">
                <div class="overflow-auto">
                    <table class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr class="border-b border-defaultborder">
                                <th data-priority="1">S.No</th>
                                <th data-priority="2">Date</th>
                                <th data-priority="3">Services</th>
                                <th data-priority="4">Passengers</th>
                                <th data-priority="5">Trip Dates</th>
                                <th data-priority="6">Representative</th>
                                <!-- <th data-priority="7">Status</th> -->
                                <th data-priority="8">Next Follow-up</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="border-b border-defaultborder">
                                <td></td>
                                <td>
                                    {{ date('d-m-Y H:i', strtotime($latestFollowup->created_at)) }}
                                </td>
                                <td>
                                    @php
                                        $serviceNames = [];
                                        if (!empty($latestLead->service_ids)) {
                                            $serviceIds = json_decode($latestLead->service_ids, true);
                                            // Ensure we only call whereIn when we have a non-empty array to avoid
                                            // passing null (or invalid bindings) to the query builder.
                                            if (is_array($serviceIds) && count($serviceIds) > 0) {
                                                $serviceNames = \App\Models\Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                                            }
                                        }
                                    @endphp
                                    {{ $serviceNames ? implode(', ', $serviceNames) : 'N/A' }}
                                </td>
                                <td class="text-center">
                                    {{ $latestLead->number_of_passengers ?? 'N/A' }}
                                </td>
                                <td>
                                    @if(count($latestLead->rideSegments) > 0)
                                        @php
                                            $firstSegment = $latestLead->rideSegments->first();
                                            $lastSegment = $latestLead->rideSegments->last();
                                        @endphp
                                        {{ date('d-m-Y', strtotime($firstSegment->from_date)) }} to {{ date('d-m-Y', strtotime($lastSegment->to_date)) }}
                                    @else
                                        N/A
                                    @endif
                                </td>
                                <td>
                                    {{ $latestLead->representative->name ?? 'N/A' }}
                                </td>
                                <!-- <td class="text-center">
                                    @php
                                        $status = $latestFollowup->status;
                                    @endphp
                                    @if($status == 1)
                                        <span class="badge !rounded-full bg-warning/10 text-warning">Pending</span>
                                    @elseif($status == 2)
                                        <span class="badge !rounded-full bg-success/10 text-success">Completed</span>
                                    @elseif($status == 3)
                                        <span class="badge !rounded-full bg-danger/10 text-danger">Skipped</span>
                                    @else
                                        <span class="badge !rounded-full bg-black/10">N/A</span>
                                    @endif
                                </td> -->
                                <td>
                                    {{ $latestFollowup->next_followup_date ? date('d-m-Y H:i', strtotime($latestFollowup->next_followup_date)) : 'N/A' }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

            <!-- Call Notes Section -->
            @if(isset($latestLead))
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Call Notes</h5>
                    </div>
                    <div class="box-body">
                        @if($latestLead->description)
                            <p class="text-gray-800 dark:text-white whitespace-pre-line">{{ $latestLead->description }}</p>
                        @else
                            <p class="text-gray-500 dark:text-white/70">No call notes available.</p>
                        @endif
                    </div>
                </div>
            @endif

            <!-- Trip Segments Section -->
            @if(count($leads) > 0 && count($leads[0]->rideSegments) > 0)
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Trip Itinerary</h5>
                    </div>
                    <div class="box-body">
                        <div class="overflow-auto">
                            <table class="table display responsive nowrap table-datatable" width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">
                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">From Date</th>
                                        <th data-priority="3">To Date</th>
                                        <th data-priority="4">From Place</th>
                                        <th data-priority="5">To Place</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($leads[0]->rideSegments as $index => $segment)
                                        <tr class="border-b border-defaultborder">
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                {{ date('d-m-Y H:i', strtotime($segment->from_date)) }}
                                            </td>
                                            <td>
                                                {{ date('d-m-Y H:i', strtotime($segment->to_date)) }}
                                            </td>
                                            <td>
                                                {{ $segment->from_place }}
                                            </td>
                                            <td>
                                                {{ $segment->to_place }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
            
            <!-- Services & Costing Section -->
            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Services & Costing</h5>
                </div>
                <div class="box-body">
                    @if($selectedServices->count() > 0 || $selectedExtraServices->count() > 0)
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <!-- Services Section -->
                            @if($selectedServices->count() > 0)
                                <div class="space-y-4">
                                    <h6 class="text-lg font-semibold text-gray-800 dark:text-white">Selected Services</h6>
                                    <div class="overflow-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                            <thead class="bg-gray-50 dark:bg-black/20">
                                                <tr>
                                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-white/70 uppercase">Service</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                                @foreach($selectedServices as $service)
                                                    <tr>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            <div class="font-medium">{{ $service->service }}</div>
                                                            @if($service->description)
                                                                <div class="text-xs text-gray-500 dark:text-white/70 mt-1">{{ $service->description }}</div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            <!-- Extra Services Section -->
                            @if($selectedExtraServices->count() > 0)
                                <div class="space-y-4">
                                    <h6 class="text-lg font-semibold text-gray-800 dark:text-white">Extra Services</h6>
                                    <div class="overflow-auto">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10">
                                            <thead class="bg-gray-50 dark:bg-black/20">
                                                <tr>
                                                    <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 dark:text-white/70 uppercase">Extra Service</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                                @foreach($selectedExtraServices as $extraService)
                                                    <tr>
                                                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-white">
                                                            <div class="font-medium">{{ $extraService->extra_service }}</div>
                                                            @if($extraService->description)
                                                                <div class="text-xs text-gray-500 dark:text-white/70 mt-1">{{ $extraService->description }}</div>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- Total Costing Summary -->
                        <div class="mt-6 bg-gray-50 dark:bg-black/20 rounded-lg p-6">
                            <h6 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Cost Summary</h6>
                            

                            
                            <div class="space-y-3">
                                @if($isStoredAmount && $totalAmount > 0)
                                    <div class="flex justify-between items-center bg-green-50 dark:bg-green-900/20 p-3 rounded-lg">
                                        <span class="text-lg font-semibold text-green-800 dark:text-green-300">Total Amount:</span>
                                        <span class="text-lg font-bold text-green-600 dark:text-green-400">₹{{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                   
                                @else
                                    <div class="flex justify-between items-center">
                                        <span class="text-lg font-semibold text-gray-900 dark:text-white">Total Amount:</span>
                                        <span class="text-lg font-bold text-primary">₹{{ number_format($totalAmount, 2) }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <div class="text-gray-500 dark:text-white/70">
                                <i class="ti ti-receipt text-4xl mb-4"></i>
                                <p class="text-lg font-medium">No Services Selected</p>
                                <p class="text-sm">No services or extra services have been selected for this lead yet.</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Add Enquiry Modal -->
    <!-- Payment History Section -->
    <div class="box">
        <div class="box-header flex justify-between items-center">
            <h5 class="box-title">Payment History</h5>
        </div>
        <div class="box-body">
            @if(isset($followups) && $followups->count() > 0)
                <div class="space-y-4">
                    @php
                        // Prefer controller-provided clientPaymentHistory (built from PaymentAuditTrail) when available
                        if (isset($clientPaymentHistory) && is_iterable($clientPaymentHistory) && count($clientPaymentHistory) > 0) {
                            $payments = collect($clientPaymentHistory);
                        } else {
                            $allPayments = collect();
                            foreach($followups as $f) {
                                if(isset($f->paymentAuditTrail) && $f->paymentAuditTrail instanceof \Illuminate\Support\Collection) {
                                    $allPayments = $allPayments->merge($f->paymentAuditTrail);
                                } else {
                                    // try relation
                                    $allPayments = $allPayments->merge($f->paymentAuditTrail()->get());
                                }
                            }
                            $payments = $allPayments->sortByDesc('created_at')->map(function($item){
                                // Normalize Eloquent model or array shapes into consistent arrays
                                if (is_array($item)) return $item;
                                return [
                                    'id' => $item->id ?? null,
                                    'lead_followup_id' => $item->lead_followup_id ?? null,
                                    'amount' => $item->paid_amount ?? null,
                                    'paid_date' => $item->paid_date ?? ($item->created_at ?? null),
                                    'payment_method' => $item->payment_method ?? null,
                                    'narration' => $item->narration ?? null,
                                    'payment_status' => $item->payment_status ?? null,
                                    'file' => $item->file ?? null,
                                    'created_at' => $item->created_at ?? null,
                                ];
                            });
                        }
                    @endphp

                    @if($payments->count() > 0)
                        <div class="overflow-auto">
                            <table class="table display responsive nowrap table-datatable" width="100%">
                                <thead class="bg-primary text-white">
                                    <tr class="border-b border-defaultborder">
                                        <th data-priority="1">S.No</th>
                                        <th data-priority="2">Date</th>
                                        <th data-priority="3">Amount</th>
                                        <th data-priority="4">Method</th>
                                        <th data-priority="5">Narration</th>
                                        <th data-priority="6">Status</th>
                                        <th data-priority="7">Receipt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($payments as $payment)
                                        @php
                                            // Support both array and object shapes for payment items
                                            if (is_array($payment)) {
                                                $rawPaidDate = $payment['paid_date'] ?? $payment['created_at'] ?? null;
                                                $rawAmount = $payment['paid_amount'] ?? $payment['amount'] ?? 0;
                                                $rawMethod = $payment['payment_method'] ?? null;
                                                $rawNarration = $payment['narration'] ?? null;
                                                $rawStatus = $payment['payment_status'] ?? null;
                                                $rawFile = $payment['file'] ?? null;
                                            } else {
                                                $rawPaidDate = $payment->paid_date ?? $payment->created_at ?? null;
                                                $rawAmount = $payment->paid_amount ?? $payment->amount ?? 0;
                                                $rawMethod = $payment->payment_method ?? null;
                                                $rawNarration = $payment->narration ?? null;
                                                $rawStatus = $payment->payment_status ?? null;
                                                $rawFile = $payment->file ?? null;
                                            }

                                            // Format date safely
                                            $paidDateDisplay = 'N/A';
                                            if ($rawPaidDate) {
                                                try {
                                                    $paidDateDisplay = \Carbon\Carbon::parse($rawPaidDate)->format('d-m-Y');
                                                } catch (\Exception $e) {
                                                    $paidDateDisplay = (string) $rawPaidDate;
                                                }
                                            }

                                            $amountDisplay = number_format((float) ($rawAmount ?? 0), 2);
                                            $methodDisplay = $rawMethod ?? 'N/A';
                                            $narrationDisplay = $rawNarration ?? 'No notes';
                                            $statusRaw = $rawStatus;
                                            $filePath = $rawFile;
                                        @endphp
                                        <tr class="border-b border-defaultborder">
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $paidDateDisplay }}</td>
                                            <td>₹{{ $amountDisplay }}</td>
                                            <td>{{ $methodDisplay }}</td>
                                            <td>{{ $narrationDisplay }}</td>
                                            <td>
                                                @if(isset($statusRaw))
                                                    @if($statusRaw == 1)
                                                        <span class="badge bg-success/10 text-success">Approved</span>
                                                    @elseif($statusRaw == 2)
                                                        <span class="badge bg-danger/10 text-danger">Rejected</span>
                                                    @else
                                                        <span class="badge bg-warning/10 text-warning">Pending</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-warning/10 text-warning">Pending</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(!empty($filePath))
                                                    @php
                                                        $urlToUse = is_array($payment) ? ($payment['file_url'] ?? null) : ($payment->file_url ?? null);
                                                        if (empty($urlToUse)) {
                                                            // Fallback to asset if controller did not provide a file_url
                                                            $urlToUse = asset($filePath);
                                                        }
                                                    @endphp
                                                    <a href="#" class="view-receipt-btn text-primary hover:underline" data-file="{{ $urlToUse }}">View</a>
                                                    <a href="{{ $urlToUse }}" download class="ms-2 text-muted">Download</a>
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No payment audit entries found for this lead.</p>
                    @endif
                </div>
            @else
                <p class="text-gray-500">No payment history available.</p>
            @endif
        </div>
    </div>
    <div id="addEnquiryModal" class="hs-overlay hidden ti-modal">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out">
            <div class="ti-modal-content">
                <form action="" method="POST">
                    @csrf
                    <input type="hidden" name="client_id" value="{{ $client->id }}">
                    <div class="ti-modal-body">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-12">
                                <label class="ti-form-label">Services</label>
                                <select name="service_ids[]" class="ti-form-select" multiple required>
                                    @foreach($services as $service)
                                        <option value="{{ $service->id }}">{{ $service->service }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-6">
                                <label class="ti-form-label">Number of Passengers</label>
                                <input type="number" name="number_of_passengers" class="ti-form-input" min="1" value="1" required>
                            </div>
                            <div class="col-span-6">
                                <label class="ti-form-label">Occasion</label>
                                <input type="text" name="occasion" class="ti-form-input">
                            </div>
                            <div class="col-span-12">
                                <label class="ti-form-label">Representative</label>
                                <select name="representative_user_id" class="ti-form-select" required>
                                    <option value="">Select Representative</option>
                                    @foreach($staff as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-span-6">
                                <label class="ti-form-label">Next Follow-up</label>
                                <input type="datetime-local" name="next_follow_up" class="ti-form-input">
                            </div>
                            <div class="col-span-6">
                                <label class="ti-form-label">Status</label>
                                <select name="enquiry_status" class="ti-form-select" required>
                                    <option value="1">Active</option>
                                    <option value="2">Pending</option>
                                    <option value="3">Cancelled</option>
                                    <option value="4">Completed</option>
                                </select>
                            </div>
                            <div class="col-span-12">
                                <label class="ti-form-label">Requirements Description</label>
                                <textarea name="requirement_description" class="ti-form-input" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                  
                </form>
            </div>
        </div>
    </div>

    <script>
        // Define PHP variables for JavaScript use
        window.clientCountryId = @json($client->country_id ?? null);
        window.oldCityValue = @json(old('city', $client->city ?? ''));
    </script>

    <script>
      document.addEventListener('DOMContentLoaded', function() {
     $('#countryCodeSelect').select2({
        placeholder: "Select Country",
        allowClear: true,
        width: '100%'
    });

    // Initialize Select2 for city dropdown
    $('#citySelect').select2({
        placeholder: "Select City",
        allowClear: true,
        width: '100%'
    });

    // Country change handler
    $('#countryCodeSelect').on('change', function() {
        const countryId = $(this).val();
        console.log('Country selected:', countryId);
        
        // Clear and disable city dropdown
        const citySelect = $('#citySelect');
        citySelect.empty().append('<option value="">Select City</option>');
        
        if (!countryId) {
            return;
        }
        
        // Load cities for selected country
        $.ajax({
            url: '/get-cities/' + countryId,
            type: 'GET',
            dataType: 'json',
            beforeSend: function() {
                citySelect.prop('disabled', true);
            },
            success: function(response) {
                citySelect.empty().append('<option value="">Select City</option>');
                
                if (response?.length) {
                    response.forEach(city => {
                        citySelect.append(
                            $('<option></option>')
                                .val(city.id)
                                .text(city.name)
                                .prop('selected', city.id == '{{ old("city", $client->city) }}')
                        );
                    });
                    if (window.selectedCity) {
            citySelect.val(window.selectedCity).trigger('change');
        }
                }
            },
            error: function(xhr) {
                console.error('Error loading cities:', xhr.responseText);
                citySelect.html('<option value="">Error loading cities</option>');
            },
            complete: function() {
                citySelect.prop('disabled', false);
            }
        });
    });

    // Trigger change if country is preselected
    if (window.clientCountryId) {
        $('#countryCodeSelect').trigger('change');
    }

    // Initialize datetime pickers
    flatpickr(".datetime", { enableTime: true, dateFormat: "Y-m-d H:i" });
    flatpickr("#datetime", { enableTime: true, dateFormat: "Y-m-d H:i" });

    // Initialize service multi-select
    $('.js-example-basic-multiple').select2({
        placeholder: "Select Services",
        allowClear: true
    });
});
    </script>
    <!-- Receipt Viewer Modal for Lead View -->
    <div id="lead-receipt-viewer-modal" class="hs-overlay hidden ti-modal">
        <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out min-h-[calc(100%-3.5rem)] flex items-center">
            <div class="ti-modal-content w-full">
                <div class="ti-modal-header">
                    <h6 class="modal-title">Receipt Preview</h6>
                    <button type="button" class="hs-dropdown-toggle !text-[1rem] !font-semibold !text-defaulttextcolor"
                        data-hs-overlay="#lead-receipt-viewer-modal">
                        <span class="sr-only">Close</span>
                    </button>
                </div>
                <div class="ti-modal-body px-4">
                    <div id="lead-receipt-content" class="text-center">
                        <p>Loading...</p>
                    </div>
                </div>
                <div class="ti-modal-footer">
                    <button type="button" class="hs-dropdown-toggle ti-btn  ti-btn-secondary-full align-middle"
                        data-hs-overlay="#lead-receipt-viewer-modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle clicking on 'View' links in Payment History
        document.addEventListener('click', function(e) {
            const el = e.target.closest('.view-receipt-btn');
            if (!el) return;
            e.preventDefault();
            const file = el.getAttribute('data-file');
            const container = document.getElementById('lead-receipt-content');
            if (!file) {
                container.innerHTML = '<p>No file available</p>';
            } else {
                // Decide how to preview: images inline, PDFs using iframe, otherwise show link
                const lower = file.toLowerCase();
                if (lower.match(/\.(jpg|jpeg|png|gif|webp)$/)) {
                    container.innerHTML = `<img src="${file}" alt="Receipt" class="max-w-full max-h-[70vh] mx-auto" />`;
                } else if (lower.endsWith('.pdf')) {
                    container.innerHTML = `<iframe src="${file}" class="w-full h-[70vh]" frameborder="0"></iframe>`;
                } else {
                    container.innerHTML = `<p><a href="${file}" target="_blank" class="text-primary">Open file in new tab</a></p>`;
                }
            }
            window.HSOverlay.open(document.getElementById('lead-receipt-viewer-modal'));
        });
    </script>
@endsection