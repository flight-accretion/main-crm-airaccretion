<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Booking Slip</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Marcellus&family=Mulish:ital,wght@1,200..1000&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        body {
            font-family: "Mulish", sans-serif;
            font-size: 14px;
            color: #303030;
        }

        .container {
            border: 1px solid #000;
            padding: 20px;
            background: #fff;
        }

        table {
            font-family: "Mulish", sans-serif;
        }

        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-family: "Mulish", sans-serif;
        }

        .header-table td {
            vertical-align: top;
            padding: 5px;
        }

        .title {
            text-align: right;
        }

        .title h2 {
            margin: 0 0 10px;
            color: rgb(43 83 169);
        }

        .title p {
            margin: 2px 0;
            color: #3B3B3B;
            font-size: 15px;
        }

        .box {
            background: #F2F8FF;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 10px;
            font-family: "Mulish", sans-serif;
        }

        .box h3 {
            margin: 0 0 15px 0;
            color: rgb(43 83 169);
            font-size: 17px;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            vertical-align: top;
        }

        .service-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #d8d8ff;
            border-radius: 10px;
            overflow: hidden;
            font-family: 'Mulish', sans-serif;
            background: #fff;
        }

        .service-table th {
            color: #3B3B3B;
            text-align: left;
            padding: 10px;
            border-bottom: 1px solid #d8d8ff;
            font-weight: 400;
            font-size: 15px;
        }

        .service-table td {
            padding: 12px 10px;
            color: #6A6A6A;
            font-size: 14px;
            border: none;
            /* remove all cell borders */
        }

        .service-table .profit {
            color: #059509;
        }
        /* explicit profit class for positive values */
        .profit { color: #059509; }

        .status-tag {
            display: inline-block;
            padding: 4px 10px;
            font-size: 13px;
            border-radius: 12px;
            background: #E1FFD7;
            color: #059509;
            margin-top: 8px;
        }
    .balance-positive { color: #C71B1B; }
    .balance-negative { color: #059509; }
    .profit-negative { color: #C71B1B; }
    </style>
</head>

<body>
    <div class="container">

        <!-- Header -->
        <table class="header-table">
            <tr>
                <td class="logo">
                    {{-- $logo provided by controller: filesystem path for PDF, HTTP URL for HTML preview --}}
                    <img src="{{ $logo ?? public_path('assets/admin/images/logo.png') }}" alt="Company Logo">
                </td>
                <td class="title">
                    <h2>BOOKING SLIP</h2>
                    <p>Slip Number: {{ $existingInvoice->invoice_id ?? 'DRAFT-' . $voucher->id }}</p>
                    <p>Date: {{ $voucher->created_at ? $voucher->created_at->format('d F Y') : date('d F Y') }}</p>
                    <p>Service Date:
                        @if(isset($voucher->lead->rideSegments) && $voucher->lead->rideSegments->count() > 1)
                            @foreach($voucher->lead->rideSegments as $seg)
                                <div>{{ $seg->from_date ? \Carbon\Carbon::parse($seg->from_date)->format('d F Y') : 'N/A' }} ({{ $seg->from_place ?? 'N/A' }} &rarr; {{ $seg->to_place ?? 'N/A' }})</div>
                            @endforeach
                        @else
                            @php $single = $voucher->lead->rideSegments->first(); @endphp
                            {{ $single && $single->from_date ? \Carbon\Carbon::parse($single->from_date)->format('d F Y') : ($invoiceData['ride']['from_date'] ? \Carbon\Carbon::parse($invoiceData['ride']['from_date'])->format('d F Y') : 'N/A') }}
                        @endif
                    </p>
                </td>
            </tr>
        </table>

        <!-- Bill to -->
        <div class="box">
            <h3>Bill to :</h3>
            <table class="info-table">
                <tr>
                    <td style="color: #000000; display:block; margin-bottom:8px;">{{ $invoiceData['client']->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color: #6A6A6A; font-size: 14px;">{{ $invoiceData['client']->email ?? 'N/A' }}</td>
                    <td style="text-align:right; color: #6A6A6A; font-size: 14px;">{{ $invoiceData['client']->address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    @php
                        $contact = $invoiceData['client']->contact_number ?? null;
                        $alternate = $invoiceData['client']->alternate_number ?? null;
                        if ($contact && $alternate && $alternate != $contact) {
                            $phoneDisplay = $contact . ' / ' . $alternate;
                        } elseif ($contact) {
                            $phoneDisplay = $contact;
                        } elseif ($alternate) {
                            $phoneDisplay = $alternate;
                        } else {
                            $phoneDisplay = 'N/A';
                        }
                    @endphp
                    <td style="color: #6A6A6A; font-size: 14px;">{{ $phoneDisplay }}</td>
                </tr>
            </table>
        </div>

        <!-- Company Details -->
        <div class="box">
            <h3>Company Details :</h3>
            <table class="info-table">
                <tr>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Company
                            Name</label>
                        <div style="color: #303030; font-size: 14px;">{{ $existingInvoice->company_name ?? 'Accretion Aviation' }}</div>
                    </td>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">GST
                            Number</label>
                        <div style="color: #303030; font-size: 14px;">{{ $existingInvoice->gst_number ?? 'N/A' }}</div>
                    </td>
                    <td><label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Billing
                            Address</label>
                        <div style="color: #303030; font-size: 14px;">{{ $existingInvoice->billing_address ?? 'N/A' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Travel Details -->
        @if(isset($voucher->lead->rideSegments) && $voucher->lead->rideSegments->count() > 0)
        <div class="box">
            <h3>Travel Details :</h3>
            @foreach($voucher->lead->rideSegments as $index => $ride)
                <div style="border: 1px solid #d8d8ff; padding: 15px; margin-bottom: 12px; border-radius: 8px; background: #fff;">
                    @if($voucher->lead->rideSegments->count() > 1)
                        <h4 style="color: rgb(43 83 169); margin: 0 0 12px 0; font-size: 15px;">Trip {{ $index + 1 }}</h4>
                    @endif
                    
                    <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                        <tr>
                            <td style="width: 50%; vertical-align: top; padding-right: 15px;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0; width: 120px;">Date:</td>
                                        <td style="color: #3B3B3B;">
                                            @php
                                                $fromDate = $ride->from_date;
                                                $toDate = $ride->to_date;
                                                $isSameDay = $fromDate && $toDate && $fromDate->format('Y-m-d') === $toDate->format('Y-m-d');
                                                $fromTime = $fromDate ? $fromDate->format('H:i') : null;
                                                $toTime = $toDate ? $toDate->format('H:i') : null;
                                                $isTimeTBA = ($fromTime === '00:00' && $toTime === '00:00');
                                            @endphp
                                            @if($fromDate && $toDate)
                                                @if($isSameDay)
                                                    {{ $fromDate->format('d-m-Y') }}
                                                @else
                                                    {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
                                                @endif
    
                                            @endif
                                        </td>
                                    </tr>

                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Time:</td>
                                        <td style="color: #3B3B3B;">
                                            @if($fromDate && $toDate)
                                                @if($isTimeTBA)
                                                    <span style="color: #ff9800;">To Be Announced</span>
                                                @else
                                                    {{ $fromTime }} - {{ $toTime }}
                                                @endif
                                            @else
                                                N/A
                                            @endif
                                        </td>
                                    </tr>

                                    <!-- address moved out to span both columns -->
                                </table>
                            </td>

                            <td style="width: 50%; vertical-align: top; padding-left: 15px;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">From:</td>
                                        <td style="color: #3B3B3B;">{{ $ride->from_place ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0; width: 120px;">To:</td>
                                        <td style="color: #3B3B3B;">{{ $ride->to_place ?? 'N/A' }}</td>
                                    </tr>    
                                    @if($ride->contact_person || $ride->contact_number)
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Contact:</td>
                                        <td style="color: #3B3B3B;">
                                            {{ $ride->contact_person ?? 'N/A' }}
                                            @if($ride->contact_number) | {{ $ride->contact_number }} @endif
                                        </td>
                                    </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="color: #3B3B3B; padding-top: 10px;">
                                <span style="color: #6A6A6A; padding-right: 6px;">Address:</span>
                                {{ $ride->serviceAddress->address ?? 'N/A' }}
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>
        @endif

        <!-- Passenger Information -->
        @if(isset($voucher->passengers) && $voucher->passengers->count() > 0)
        <div class="box">
            <h3>Passenger Information :</h3>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Age</th>
                        <!-- <th>Contact Number</th> -->
                        <th>Weight (KG)</th>
                        <!-- <th>Type</th> -->
                    </tr>
                </thead>
                <tbody>
                    @foreach($voucher->passengers as $passenger)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $passenger->name ?? 'N/A' }}</td>
                        <td>{{ $passenger->age ?? 'N/A' }} @if($passenger->age) Years @endif</td>
                        <!-- <td>{{ $passenger->contact_number ?? 'N/A' }}</td> -->
                        <td>{{ $passenger->weight ?? 'N/A' }}</td>
                        <!-- <td>
                            @if($passenger->is_handler)
                                <span style="background: #E1FFD7; color: #059509; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Handler</span>
                            @elseif($passenger->is_additional_person)
                                <span style="background: #FFE1D7; color: #ff6b35; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Additional</span>
                            @else
                                <span style="background: #D7E1FF; color: #2b53a9; padding: 2px 8px; border-radius: 12px; font-size: 12px;">Primary</span>
                            @endif
                        </td> -->
                    </tr>
                    @endforeach
                </tbody>
            </table>
            
            <div style="margin-top: 12px; padding: 10px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid #2b53a9;">
                <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <tr>
                        <td style="color: #6A6A6A; padding: 3px 0;">Total Passengers:</td>
                        <td style="text-align: right; color: #3B3B3B; font-weight: 600;">{{ $voucher->passengers->count() }}</td>
                    </tr>
                    <tr>
                        <td style="color: #6A6A6A; padding: 3px 0;">Total Weight:</td>
                        <td style="text-align: right; color: #3B3B3B; font-weight: 600;">{{ $voucher->passengers->sum('weight') ?? 'N/A' }} KG</td>
                    </tr>
                </table>
            </div>
        </div>
        @endif

        <!-- Service Details -->
        <div class="box">
            <h3>Service Details :</h3>
            <p style="text-align:right; font-size:14px; color:#333; margin-bottom:8px;">Total Service Cost: <strong>Rs.{{ number_format($invoiceData['vendor']['total_service_amount'] ?? $invoiceData['vendor']['totalVendorCost'] ?? 0, 0) }}</strong></p>
            <table class="service-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Cost Price</th>
                        <th>Vendor Amount</th>
                        <th>Profit / Loss</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $detailed = $invoiceData['service']['detailed_items'] ?? [];
                        $totalCost = 0;
                        $totalVendorAmount = 0;
                    @endphp
                    @if(!empty($detailed) && is_array($detailed))
                        @foreach($detailed as $item)
                            @php
                                $cost = isset($item['cost_price']) ? (float)$item['cost_price'] : 0;
                                $vendorAmt = isset($item['vendor_amount']) ? (float)$item['vendor_amount'] : 0;
                                $profit = $cost - $vendorAmt;
                                $totalCost += $cost;
                                $totalVendorAmount += $vendorAmt;
                            @endphp
                            <tr>
                                <td>{{ $item['name'] ?? 'Unnamed' }}</td>
                                <td>{{ ucfirst($item['type'] ?? 'service') }}</td>
                                <td>Rs.{{ number_format($cost, 0) }}</td>
                                <td>Rs.{{ number_format($vendorAmt, 0) }}</td>
                                <td class="{{ $profit < 0 ? 'profit-negative' : 'profit' }}">{{ $profit < 0 ? 'Rs.-' . number_format(abs($profit), 0) : 'Rs.' . number_format($profit, 0) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><strong>Totals</strong></td>
                            <td></td>
                            <td><strong>Rs.{{ number_format($totalCost, 0) }}</strong></td>
                            <td><strong>Rs.{{ number_format($totalVendorAmount, 0) }}</strong></td>
                            <td><strong class="{{ ($totalCost - $totalVendorAmount) < 0 ? 'profit-negative' : 'profit' }}">{{ ($totalCost - $totalVendorAmount) < 0 ? 'Rs.-' . number_format(abs($totalCost - $totalVendorAmount), 0) : 'Rs.' . number_format($totalCost - $totalVendorAmount, 0) }}</strong></td>
                        </tr>
                    @else
                        <tr>
                            <td colspan="5">No service line items found</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Payment -->
        <div class="box">
            <h3>Payment Details :</h3>
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 14px;">
                <tr>
                    <td style="background: #fff; border-radius: 6px; box-shadow: 0 0 6px rgba(0,0,0,0.05); padding: 0;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <!-- Left vertical line -->
                                <td style="width: 2px; background: #7A5AF8; border-radius: 6px 0 0 6px;"></td>

                                <!-- Content area -->
                                <td style="padding: 15px 20px;">
                                    <table style="width: 100%; border-collapse: collapse;">
                                        <tr>
                                            <td style="color: #6A6A6A; padding: 6px 0;">Received Date :</td>
                                            <td style="text-align: right; color: #3B3B3B;">{{ $invoiceData['payment']['latest_payment']->created_at ? $invoiceData['payment']['latest_payment']->created_at->format('d F Y') : 'N/A' }}</td>
                                        </tr>
                                        <tr>
                                            <td style="color: #6A6A6A; padding: 6px 0;">Payment Mode :</td>
                                            <td style="text-align: right; color: #3B3B3B;">Bank Transfer</td>
                                        </tr>
                                        <tr>
                                            <td style="color: #6A6A6A; padding: 6px 0;">Amount :</td>
                                            <td style="text-align: right; color: #059509;">Rs.{{ number_format($invoiceData['payment']['received_amount'], 0) }}</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>

            <span class="status-tag">
                @if($invoiceData['payment']['received_amount'] >= $invoiceData['payment']['total_amount'] && $invoiceData['payment']['total_amount'] > 0)
                    Full Paid
                @elseif($invoiceData['payment']['received_amount'] > 0)
                    Partial Paid
                @else
                    Unpaid
                @endif
            </span>
        </div>

        <div class="box">
            <h3>Vendor Details :</h3>
            @if(isset($invoiceData['vendor']['vendors']) && count($invoiceData['vendor']['vendors']) > 0)
                @foreach($invoiceData['vendor']['vendors'] as $vIndex => $vendor)
                    <table style="width:100%; border-collapse:collapse; font-size: 14px; margin-bottom:12px;">
                        <tr>
                            <!-- Left Column -->
                            <td style="width:50%; vertical-align:top; padding-right:15px; border-right:1px solid #cfcfff;">
                                <table style="width:100%; border-collapse:collapse;">
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Vendor Name :</td>
                                        <td style="text-align: right; color: #3B3B3B;">{{ $vendor['name'] ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Service Cost :</td>
                                        <td style="text-align: right; color: #3B3B3B;">Rs.{{ number_format($vendor['total_amount'] ?? 0, 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Amount Paid :</td>
                                        <td style="text-align: right; color: #059509;">Rs.{{ number_format($vendor['paid_amount'] ?? 0, 0) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Balance :</td>
                                        <td class="{{ (($vendor['balance'] ?? 0) > 0) ? 'balance-positive' : 'balance-negative' }}" style="text-align: right;">Rs.{{ number_format($vendor['balance'] ?? 0, 0) }}</td>
                                    </tr>
                                </table>
                            </td>

                            <!-- Right Column -->
                            <td style="width:50%; vertical-align:top; padding-left:15px;">
                                <table style="width:100%; border-collapse:collapse;">
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Payment Date :</td>
                                        <td style="text-align: right; color: #3B3B3B;">{{ $invoiceData['payment']['latest_payment']->created_at ? $invoiceData['payment']['latest_payment']->created_at->format('d F Y') : date('d F Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Payment Method :</td>
                                        <td style="text-align: right; color: #3B3B3B;">{{ $vendor['payment_method'] ?? 'Bank Transfer' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="color: #6A6A6A; padding: 6px 0;">Payment Screenshot :</td>
                                        <td style="padding:6px 0; text-align:right;">
                                            @php
                                                $file = $invoiceData['payment']['latest_payment']->file ?? null;
                                            @endphp
                                            @if($file)
                                                <a href="{{ asset('storage/' . $file) }}" target="_blank" style="background:#f4f1ff; border:1px solid #d0caff; padding:3px 8px; border-radius:6px; font-size:12px; color:#5a4dff; display:inline-block; text-decoration:none;">
                                                    📎 View Receipt
                                                </a>
                                            @else
                                                <span style="color:#6a6a6a;">Not available</span>
                                            @endif
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                @endforeach
            @else
                <p style="color: #6A6A6A; font-size: 14px;">No vendor information available</p>
            @endif
        </div>

        <!-- Footer Section -->
        <table
            style="width:100%; border-collapse:collapse; margin-top:30px; font-family:Arial, sans-serif; font-size:14px; text-align:center;">
            <tr>
                <td style="padding:6px 0; color:#000000;">
                    This is a system-generated invoice. For any queries, please contact  ops@accretionaviation.com or +91-9575340786.
                </td>
            </tr>
            <!-- <tr>
                <td style="padding:4px 0; color:#6A6A6A;">
                    This invoice is generated electronically and does not require a signature.
                </td>
            </tr> -->

        </table>

    </div>
</body>

</html>
