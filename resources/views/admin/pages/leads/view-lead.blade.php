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

               @php
    $currentUserType =
        optional(auth()->user()->userType)->user_type;

    $canRequestLead =
        $latestLead
        &&
        $latestLead->representative_user_id
        &&
        !$latestLead->pendingTransfer
        &&
        (string) $latestLead->representative_user_id
            !==
            (string) auth()->id()
        &&
        in_array(
            $currentUserType,
            \App\Models\UserType::SALES_ROLES,
            true
        );
@endphp

@if($canRequestLead)

    <button
        type="button"
        class="ti-btn ti-btn-primary"
        data-hs-overlay="#lead-transfer-modal"
    >
        Request Lead
    </button>

@endif
            </div>
            @if($latestLead && $latestLead->pendingTransfer)
    @php
        $pendingTransfer = $latestLead->pendingTransfer;
    @endphp

    <div class="px-5 pt-4">
        <div class="alert alert-warning">
            <strong>Lead Transfer Pending</strong>

            <div class="mt-2">
                From:
                {{ optional($pendingTransfer->fromUser)->name ?? 'N/A' }}
            </div>

            <div>
                To:
                {{ optional($pendingTransfer->toUser)->name ?? 'N/A' }}
            </div>

            @if($pendingTransfer->reason)
                <div>
                    Reason:
                    {{ $pendingTransfer->reason }}
                </div>
            @endif

       @php
    $canApproveTransfer =
        (string) auth()->id()
            ===
            (string) $pendingTransfer->from_user_id
        ||
        optional(auth()->user()->userType)->user_type
            ===
            \App\Models\UserType::SUPER_ADMIN;
@endphp

@if($canApproveTransfer)
                <div class="flex gap-2 mt-3">

                    <form
                        method="POST"
                        action="{{ route(
                            'admin.leads.transfer.accept',
                            $pendingTransfer->id
                        ) }}"
                    >
                        @csrf

                        <button
                            type="submit"
                            class="ti-btn ti-btn-success"
                            onclick="return confirm(
                                'Accept this lead transfer?'
                            );"
                        >
                            Accept Transfer
                        </button>
                    </form>

                    <button
                        type="button"
                        class="ti-btn ti-btn-danger"
                        data-hs-overlay="#lead-transfer-reject-modal"
                    >
                        Reject Transfer
                    </button>

                </div>
            @endif

        </div>
    </div>
@endif
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
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Company Name</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->company_name ?? 'N/A' }}</p>
                        </div>
                        <div class="space-y-2">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">GST Number</label>
                            <p class="text-gray-800 dark:text-white">{{ $client->gst_number ?? 'N/A' }}</p>
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

            @php
                $serviceRows = collect($selectedServices ?? []);
                $extraServiceRows = collect($selectedExtraServices ?? []);
                $historyRows = collect($followups ?? []);
                $serviceLabels = $serviceRows->pluck('service')->filter()->values();
                $extraServiceLabels = $extraServiceRows->pluck('extra_service')->filter()->values();
            @endphp

            <div class="box">
                <div class="box-header">
                    <h5 class="box-title">Services and Extra Services</h5>
                </div>
                <div class="box-body">
                    <div class="grid grid-cols-12 gap-6">
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Products</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['products'] ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Number OF Passengers</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['passengers'] ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Occasion</label>
                            <p class="text-gray-800 dark:text-white">{{ $clientInfo['occasion'] ?? 'N/A' }}</p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Services</label>
                            <p class="text-gray-800 dark:text-white">
                                {{ $serviceLabels->isNotEmpty() ? $serviceLabels->implode(', ') : 'N/A' }}
                            </p>
                        </div>
                        <div class="xl:col-span-6 lg:col-span-6 md:col-span-6 sm:col-span-12 col-span-12">
                            <label class="ti-form-label dark:text-defaulttextcolor/70 mb-0">Extra Services</label>
                            <p class="text-gray-800 dark:text-white">
                                {{ $extraServiceLabels->isNotEmpty() ? $extraServiceLabels->implode(', ') : 'N/A' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Call Notes Section -->
            @if(isset($latestLead))
                <div class="box">
                    <div class="box-header">
                        <h5 class="box-title">Call Notes</h5>
                    </div>
                    <div class="box-body">
                        @if($historyRows->count() > 0)
                            <ul class="list-unstyled mb-0 upcoming-events-list">
                                @foreach($historyRows as $followup)
                                    @php
                                        $statusLabels = [
                                            0 => 'Initiated',
                                            1 => 'Active',
                                            2 => 'Cancelled',
                                            3 => 'Full payment received',
                                            4 => 'Partial payment received',
                                            5 => 'Completed',
                                            6 => 'Pending',
                                            7 => 'Rescheduled',
                                            8 => 'Approved',
                                            9 => 'Rejected',
                                        ];
                                        $statusText = $statusLabels[(int) $followup->status] ?? 'N/A';
                                    @endphp
                                    <li>
                                        <div class="grid grid-cols-12 gap-3">
                                            <div class="xl:col-span-12 col-span-12">
                                                <div class="md:flex block items-start justify-between">
                                                    <p class="mb-0 text-[.875rem]">
                                                        Note : {{ $followup->followup_note ?? 'N/A' }}
                                                        @if($followup->customer_not_picked_up)
                                                            <span class="badge bg-warning/10 text-warning ms-2">No Answer</span>
                                                        @endif
                                                    </p>
                                                    <div>
                                                        <span class="text-[#8c9097] dark:text-white/50">
                                                            <i class="ri-time-line align-middle me-1 inline-block"></i>
                                                            Created At: {{ $followup->created_at ? $followup->created_at->format('Y-m-d H:i:s') : 'N/A' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="xl:col-span-12 col-span-12">
                                                <p class="mb-0 text-[#8c9097] dark:text-white/50">
                                                    Created By: {{ $followup->followedBy->name ?? 'System' }}
                                                </p>
                                            </div>
                                            @if($followup->file)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <a href="{{ route('admin.followups.file', ['filename' => basename($followup->file)]) }}"
                                                        target="_blank"
                                                        class="me-2 text-primary">
                                                        <i class="ri-image-line"></i> View Image
                                                    </a>
                                                </div>
                                            @endif
                                            @if($followup->total_amount || $followup->received_amount || $followup->service_amount || $followup->discount_amount)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <div class="grid grid-cols-12 gap-3">
                                                        @if($followup->service_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3 col-span-12">
                                                                <span class="text-info">Service Amount: {{ number_format($followup->service_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                        @if($followup->discount_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3 col-span-12">
                                                                <span class="text-success">Discount: {{ number_format($followup->discount_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                        @if($followup->total_amount)
                                                            <div class="xxl:col-span-3 xl:col-span-3 col-span-12">
                                                                <span class="text-primary">Total Amount: {{ number_format($followup->total_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                        @if((float) ($followup->received_amount ?? 0) > 0)
                                                            <div class="xxl:col-span-3 xl:col-span-3 col-span-12">
                                                                <span class="text-success">Received: {{ number_format($followup->received_amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            @if($followup->payment_method || $followup->paid_date)
                                                <div class="xl:col-span-12 col-span-12">
                                                    <div class="grid grid-cols-12 gap-3">
                                                        @if($followup->payment_method)
                                                            <div class="xl:col-span-3 col-span-12">
                                                                <span class="text-info">Payment Method: {{ ucfirst($followup->payment_method) }}</span>
                                                            </div>
                                                        @endif
                                                        @if($followup->paid_date)
                                                            <div class="xl:col-span-3 col-span-12">
                                                                <span class="text-warning">Paid Date: {{ $followup->paid_date->format('d-m-Y') }}</span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="xl:col-span-12 col-span-12">
                                                <span class="badge bg-primary/10 text-primary">Status: {{ $statusText }}</span>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @elseif($latestLead->description)
                            <p class="text-gray-800 dark:text-white whitespace-pre-line">{{ $latestLead->description }}</p>
                        @else
                            <p class="text-gray-500 dark:text-white/70">No call notes available.</p>
                        @endif
                    </div>
                </div>
            @endif

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

 <div
    id="lead-transfer-modal"
    class="hs-overlay hidden ti-modal"
>
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out">

        <div class="ti-modal-content">

            <div class="ti-modal-header">

                <h6 class="modal-title">
                    Request Lead
                </h6>

                <button
                    type="button"
                    data-hs-overlay="#lead-transfer-modal"
                >
                    <i class="ri-close-line"></i>
                </button>

            </div>

            <form
                method="POST"
                action="{{ route(
                    'admin.leads.transfer.store',
                    $latestLead->id
                ) }}"
            >
                @csrf

                <div class="ti-modal-body">

                    <div class="mb-4">

                        <label class="ti-form-label">
                            Current Salesperson
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ optional(
                                $latestLead->representative
                            )->name ?? 'Unassigned' }}"
                            disabled
                        >

                    </div>

                    <div class="mb-4">

                        <label class="ti-form-label">
                            Requested By
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="{{ auth()->user()->name }}"
                            disabled
                        >

                    </div>

                    <div>

                        <label class="ti-form-label">
                            Reason
                        </label>

                        <textarea
                            name="reason"
                            class="form-control"
                            rows="4"
                            maxlength="1000"
                            placeholder="Why do you want this lead?"
                        ></textarea>

                    </div>

                </div>

                <div class="ti-modal-footer">

                    <button
                        type="button"
                        class="ti-btn ti-btn-light"
                        data-hs-overlay="#lead-transfer-modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="ti-btn ti-btn-primary"
                    >
                        Send Request
                    </button>

                </div>

            </form>

        </div>

    </div>
</div>

@if(
    $latestLead
    &&
    $latestLead->pendingTransfer
    &&
    (
        (string) auth()->id()
            ===
            (string) $latestLead->pendingTransfer->from_user_id
        ||
        optional(auth()->user()->userType)->user_type
            ===
            \App\Models\UserType::SUPER_ADMIN
    )
)

<div
    id="lead-transfer-reject-modal"
    class="hs-overlay hidden ti-modal"
>
    <div class="hs-overlay-open:mt-7 ti-modal-box mt-0 ease-out">

        <div class="ti-modal-content">

            <div class="ti-modal-header">
                <h6 class="modal-title">
                    Reject Lead Transfer
                </h6>
            </div>

            <form
                method="POST"
                action="{{ route(
                    'admin.leads.transfer.reject',
                    $latestLead->pendingTransfer->id
                ) }}"
            >
                @csrf

                <div class="ti-modal-body">

                    <label class="ti-form-label">
                        Reason for rejection
                    </label>

                    <textarea
                        name="response_note"
                        class="form-control"
                        rows="4"
                        maxlength="1000"
                        placeholder="Optional rejection reason"
                    ></textarea>

                </div>

                <div class="ti-modal-footer">

                    <button
                        type="button"
                        class="ti-btn ti-btn-light"
                        data-hs-overlay="#lead-transfer-reject-modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="ti-btn ti-btn-danger"
                    >
                        Reject Transfer
                    </button>

                </div>

            </form>

        </div>
    </div>
</div>

@endif
@endsection
