@extends('admin.layouts.header')
@section('content')
<!-- Page Header -->
<div class="block justify-between page-header md:flex">
    <div>
        <h3 class="!text-defaulttextcolor dark:!text-defaulttextcolor/70 dark:text-white dark:hover:text-white text-[1.125rem] font-semibold">
            Lead Tracking - {{$lead->client->name ?? 'N/A'}}
        </h3>
    </div>
    <ol class="flex items-center whitespace-nowrap min-w-0">
        <li class="text-[0.813rem] ps-[0.5rem]">
            <a class="flex items-center text-primary hover:text-primary dark:text-primary truncate" href="{{ route('admin.lead-tracking.index') }}">
                Lead Tracking
                <i class="ti ti-chevrons-right flex-shrink-0 text-[#8c9097] dark:text-white/50 px-[0.5rem] overflow-visible rtl:rotate-180"></i>
            </a>
        </li>
        <li class="text-[0.813rem] text-defaulttextcolor font-semibold hover:text-primary dark:text-[#8c9097] dark:text-white/50" aria-current="page">
            Lead Details
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

<!-- Client Information -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">
                    <i class="ri-user-line"></i> Client Information
                </h5>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Client Name:</p>
                        <p class="font-semibold">{{ $lead->client->name ?? 'N/A' }}</p>
                    </div>
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Email:</p>
                        <p class="font-semibold">{{ $lead->client->email ?? 'N/A' }}</p>
                    </div>
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Phone:</p>
                        <p class="font-semibold">{{ $lead->client->phone ?? 'N/A' }}</p>
                    </div>
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Representative:</p>
                        <p class="font-semibold">{{ $lead->representative->name ?? 'Unassigned' }}</p>
                    </div>
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Number of Passengers:</p>
                        <p class="font-semibold">{{ $lead->number_of_passengers ?? 0 }}</p>
                    </div>
                    <div class="xl:col-span-3 col-span-6">
                        <p class="mb-1 text-muted">Occasion:</p>
                        <p class="font-semibold">{{ $lead->occasion ?? 'N/A' }}</p>
                    </div>
                    <div class="xl:col-span-6 col-span-12">
                        <p class="mb-1 text-muted">Description:</p>
                        <p class="font-semibold">{{ $lead->description ?? 'N/A' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Travel Information -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">
                    <i class="ri-plane-line"></i> Travel Information
                </h5>
            </div>
            <div class="box-body">
                @if($voucher)
                <!-- Information from Voucher -->
                <div class="mb-4">
                    <!-- <h6 class="font-semibold mb-2">Voucher ID: {{ $voucher->id }}</h6> -->
                    @if($voucher->operationTeam)
                    <p><strong>Operations Team Member:</strong> {{ $voucher->operationTeam->name }}</p>
                    @endif
                </div>

                <!-- Ride/Travel Details from Voucher Payment -->
                @if($travelInfo['rides'] && $travelInfo['rides']->count() > 0)
                <div class="table-responsive">
                    <h6 class="font-semibold mb-2">Ride Details</h6>
                    <table class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>From Location</th>
                                <th>To Location</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Service Address</th>
                                <th>Pickup Time</th>
                                <th>Drop Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($travelInfo['rides'] as $ride)
                            <tr>
                                <td>{{ $ride->from_place ?? 'N/A' }}</td>
                                <td>{{ $ride->to_place ?? 'N/A' }}</td>
                                <td>{{ $ride->from_date ? \Carbon\Carbon::parse($ride->from_date)->format('d M Y') : 'N/A' }}</td>
                                <td>{{ $ride->to_date ? \Carbon\Carbon::parse($ride->to_date)->format('d M Y') : 'N/A' }}</td>
                                <td>{{ $ride->serviceAddress->address ?? 'N/A' }}</td>
                                <td>{{ $ride->from_date ? \Carbon\Carbon::parse($ride->from_date)->format('h:i A') : 'N/A' }}</td>
                                <td>{{ $ride->to_date ? \Carbon\Carbon::parse($ride->to_date)->format('h:i A') : 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                @else
                <!-- Information from Lead (No Voucher Created) -->
                <div class="alert alert-warning">
                    <i class="ri-information-line"></i> No voucher has been created for this lead yet. Showing information from lead data.
                </div>

                @if($travelInfo['rides'] && $travelInfo['rides']->count() > 0)
                <div class="table-responsive">
                    <h6 class="font-semibold mb-2">Ride Details (From Lead)</h6>
                    <table class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>From Location</th>
                                <th>To Location</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Service Address</th>
                                <th>Pickup Time</th>
                                <th>Drop Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($travelInfo['rides'] as $ride)
                            <tr>
                                <td>{{ $ride->from_location ?? 'N/A' }}</td>
                                <td>{{ $ride->to_location ?? 'N/A' }}</td>
                                <td>{{ $ride->from_date ? \Carbon\Carbon::parse($ride->from_date)->format('d M Y') : 'N/A' }}</td>
                                <td>{{ $ride->to_date ? \Carbon\Carbon::parse($ride->to_date)->format('d M Y') : 'N/A' }}</td>
                                <td>{{ $ride->serviceAddress->address ?? 'N/A' }}</td>
                                <td>{{ $ride->pickup_time ?? 'N/A' }}</td>
                                <td>{{ $ride->drop_time ?? 'N/A' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Passenger Information -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">
                    <i class="ri-group-line"></i> Passenger Information
                </h5>
            </div>
            <div class="box-body">
                @if($travelInfo['passengers'] && $travelInfo['passengers']->count() > 0)
                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>S.No</th>
                                <th>Name</th>
                                <th>Age</th>
                                <th>Weight</th>
                                <th>Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($travelInfo['passengers'] as $index => $passenger)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $passenger->name ?? 'N/A' }}</td>
                                <td>{{ $passenger->age ?? 'N/A' }}</td>
                                <td>{{ $passenger->weight ?? 'N/A' }}</td>
                                <td>
                                    @if($passenger->is_handler)
                                    <span class="badge bg-primary-transparent">Handler</span>
                                    @elseif($passenger->is_additional_person)
                                    <span class="badge bg-info-transparent">Additional</span>
                                    @else
                                    <span class="badge bg-secondary-transparent">Regular</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted">No passenger information available.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Follow-ups Section -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">
                    <i class="ri-history-line"></i> Follow-up History
                </h5>
            </div>
            <div class="box-body">
                @if($followups && $followups->count() > 0)
                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable" id="followup-table" width="100%">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Followed By</th>
                                <th>Status</th>
                                <th>Next Follow-up Date</th>
                                <th>Amount</th>
                                <th>Received</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($followups as $index => $followup)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $followup->created_at->format('d M Y h:i A') }}</td>
                                <td>{{ $followup->followedBy->name ?? 'N/A' }}</td>
                                <td>
                                    @php
                                    $statusMap = [
                                    0 => 'Initiated',
                                    1 => 'Active',
                                    2 => 'Canceled',
                                    3 => 'Full Payment',
                                    4 => 'Partial Payment',
                                    5 => 'Complete',
                                    6 => 'Pending',
                                    7 => 'Reschedule',
                                    8 => 'Approved',
                                    9 => 'Rejected'
                                    ];
                                    $status = $statusMap[$followup->status] ?? 'Unknown';
                                    $badgeClass = match($followup->status) {
                                    0, 1 => 'bg-info-transparent',
                                    3, 5, 8 => 'bg-success-transparent',
                                    2, 9 => 'bg-danger-transparent',
                                    4, 6, 7 => 'bg-warning-transparent',
                                    default => 'bg-secondary-transparent'
                                    };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $status }}</span>
                                </td>
                                <td>{{ $followup->next_followup_date ? $followup->next_followup_date->format('d M Y') : 'N/A' }}</td>
                                <td>₹{{ number_format($followup->total_amount ?? 0, 2) }}</td>
                                <td>₹{{ number_format($followup->received_amount ?? 0, 2) }}</td>
                                <td>{{ Str::limit($followup->followup_note ?? 'No note', 50) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted">No follow-up history available.</p>
                @endif
            </div>
        </div>
    </div>
</div>
<!-- Payment Summary -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header bg-primary-transparent">
                <h5 class="box-title text-primary">
                    <i class="ri-money-dollar-circle-line"></i> Payment Summary
                </h5>
            </div>
            <div class="box-body">
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded">
                            <p class="text-muted mb-1">Total Amount</p>
                            <h4 class="font-bold text-primary">₹{{ number_format($totalAmount, 2) }}</h4>
                        </div>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded">
                            <p class="text-muted mb-1">Received Amount</p>
                            <h4 class="font-bold text-success">₹{{ number_format($receivedAmount, 2) }}</h4>
                        </div>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded">
                            <p class="text-muted mb-1">Balance Amount</p>
                            <h4 class="font-bold text-warning">₹{{ number_format($balanceAmount, 2) }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Payment Section (Approved & Rejected) -->
<div class="grid grid-cols-12 gap-6">
    <!-- Approved Payments -->
    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header bg-success-transparent">
                <h5 class="box-title text-success">
                    <i class="ri-check-line"></i> Approved Payments
                </h5>
            </div>
            <div class="box-body">
                @if($approvedPayments && $approvedPayments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered whitespace-nowrap min-w-full">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvedPayments as $index => $payment)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $payment->created_at->format('d M Y') }}</td>
                                <td>₹{{ number_format($payment->paid_amount ?? 0, 2) }}</td>
                                <td>{{ $payment->payment_method ?? 'N/A' }}</td>
                                <td>{{ Str::limit($payment->narration ?? 'No note', 30) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <!-- <tfoot>
                                    <tr class="font-bold">
                                        <td colspan="2" class="text-right">Total Approved:</td>
                                        <td colspan="3">₹{{ number_format($totalAmount, 2) }}</td>
                                    </tr>
                                </tfoot> -->
                    </table>
                </div>
                @else
                <p class="text-muted">No approved payments yet.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Rejected Payments -->
    <div class="xl:col-span-6 col-span-12">
        <div class="box">
            <div class="box-header bg-danger-transparent">
                <h5 class="box-title text-danger">
                    <i class="ri-close-line"></i> Rejected Payments
                </h5>
            </div>
            <div class="box-body">
                @if($rejectedPayments && $rejectedPayments->count() > 0)
                <div class="table-responsive">
                    <table class="table table-bordered whitespace-nowrap min-w-full">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>S.No</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rejectedPayments as $index => $payment)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $payment->created_at->format('d M Y') }}</td>
                                <td>₹{{ number_format($payment->paid_amount ?? 0, 2) }}</td>
                                <td>{{ $payment->payment_method ?? 'N/A' }}</td>
                                <td>{{ Str::limit($payment->narration ?? 'No reason', 30) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-muted">No rejected payments.</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Vendor Payments Section -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="box">
            <div class="box-header">
                <h5 class="box-title">
                    <i class="ri-truck-line"></i> Vendor Payments
                </h5>
            </div>
            <div class="box-body">
                @if($vendorPaymentSummary['vendor_payments'] && $vendorPaymentSummary['vendor_payments']->count() > 0)
                <div class="table-responsive">
                    <table class="table display responsive nowrap table-datatable" width="100%">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>S.No</th>
                                <th>Vendor Name</th>
                                <th>Total Amount</th>
                                <th>Paid Amount</th>
                                <th>Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vendorPaymentSummary['vendor_payments'] as $index => $vp)
                            @php
                            $vendorTotal = $vp->total_vendor_service_amount ?? 0;
                            $vendorPaid = $vp->vendorPayments->sum('paid_amount') ?? 0;
                            $vendorBalance = $vendorTotal - $vendorPaid;
                            @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $vp->vendor->name ?? 'N/A' }}</td>
                                <td>₹{{ number_format($vendorTotal, 2) }}</td>
                                <td>₹{{ number_format($vendorPaid, 2) }}</td>
                                <td>₹{{ number_format($vendorBalance, 2) }}</td>
                                <td>
                                    @if($vendorBalance <= 0)
                                        <span class="badge bg-success-transparent">Paid</span>
                                        @elseif($vendorPaid > 0)
                                        <span class="badge bg-warning-transparent">Partial</span>
                                        @else
                                        <span class="badge bg-danger-transparent">Pending</span>
                                        @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <!-- <tfoot>
                                    <tr class="font-bold">
                                        <td colspan="2" class="text-right">Total:</td>
                                        <td>₹{{ number_format($vendorPaymentSummary['total'], 2) }}</td>
                                        <td>₹{{ number_format($vendorPaymentSummary['paid'], 2) }}</td>
                                        <td>₹{{ number_format($vendorPaymentSummary['balance'], 2) }}</td>
                                        <td></td>
                                    </tr>
                                </tfoot> -->
                    </table>
                </div>

                <!-- Vendor Payment Summary Cards -->
                <div class="grid grid-cols-12 gap-4">
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded bg-info-transparent">
                            <p class="text-muted mb-1">Total Vendor Amount</p>
                            <h4 class="font-bold">₹{{ number_format($vendorPaymentSummary['total'], 2) }}</h4>
                        </div>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded bg-success-transparent">
                            <p class="text-muted mb-1">Paid to Vendors</p>
                            <h4 class="font-bold">₹{{ number_format($vendorPaymentSummary['paid'], 2) }}</h4>
                        </div>
                    </div>
                    <div class="xl:col-span-4 col-span-12">
                        <div class="border p-4 rounded bg-warning-transparent">
                            <p class="text-muted mb-1">Vendor Balance</p>
                            <h4 class="font-bold">₹{{ number_format($vendorPaymentSummary['balance'], 2) }}</h4>
                        </div>
                    </div>
                </div>
                @else
                <div class="alert alert-info">
                    <i class="ri-information-line"></i> No vendor payments have been created for this lead yet.
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="grid grid-cols-12 gap-6">
    <div class="xl:col-span-12 col-span-12">
        <div class="flex gap-2">
            <a href="{{ route('admin.lead-tracking.index') }}" class="ti-btn ti-btn-secondary">
                <i class="ri-arrow-left-line"></i> Back to List
            </a>
            @if($lead->vouchers->count() > 0)
            <a href="{{ route('admin.vouchers.index') }}" class="ti-btn ti-btn-primary">
                <i class="ri-file-text-line"></i> View Vouchers
            </a>
            @endif
        </div>
    </div>
</div>

@endsection