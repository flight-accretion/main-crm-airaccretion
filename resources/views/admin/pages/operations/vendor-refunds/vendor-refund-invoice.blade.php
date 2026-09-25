<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Vendor Refund</title>

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
            font-size: 14px;
            font-weight: 500;
        }

        .logo img {
            max-width: 160px;
            height: auto;
        }

        .box {
            border: 1px solid #D1D5DB;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background-color: #f9fafb;
        }

        .box h3 {
            margin: 0 0 10px;
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 5px;
            vertical-align: top;
        }

        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .details-table th,
        .details-table td {
            border: 1px solid #D1D5DB;
            padding: 10px;
            text-align: left;
        }

        .details-table th {
            background-color: #f3f4f6;
            font-weight: bold;
            color: #374151;
        }

        .amount-row {
            background-color: #fef3cd;
            font-weight: bold;
        }

        .refund-amount {
            background-color: #d1ecf1;
            font-weight: bold;
            color: #0c5460;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6B7280;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            color: rgba(220, 38, 38, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }
    </style>
</head>

@php
    $vendor = $details['vendor'];
    $customer = $details['customer'];
    $refund = $details['refund'];
    $rides = $details['rides'];
    $firstRide = $rides[0] ?? null;
    $reference = 'VR-' . strtoupper(substr((string) ($details['vendor_refund_id'] ?? $details['id']), 0, 8));
    $money = fn ($value) => number_format((float) $value, 2);
    $label = 'color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;';
    $value = 'color: #303030; font-size: 14px;';
@endphp

<body>
    <div class="container">
        <div class="watermark">REFUND</div>

        <!-- Header -->
        <table class="header-table">
            <tr>
                <td style="width: 60%;">
                    <div class="logo">
                        @php
                            // DomPDF (PDF generation) requires absolute file paths. For browser previews we can use asset().
                            $logoPath = (isset($is_pdf) && $is_pdf) ? public_path('assets/admin/images/logo.png') : asset('assets/admin/images/logo.png');
                        @endphp
                        <img src="{{ $logoPath }}" alt="Logo" style="max-width:160px; height:auto;">
                    </div>
                </td>
                <td style="width: 40%;">
                    <div class="title">
                        <h2>VENDOR REFUND</h2>
                        <p><strong>Vendor Refund ID:</strong> {{ $reference }}</p>
                        <p><strong>Refund Date:</strong> {{ ($refund['refund_date'] ?? '') ? \Carbon\Carbon::parse($refund['refund_date'])->format('d-m-Y') : 'N/A' }}</p>
                        <p><strong>Status:</strong> {{ $details['is_completed'] ? 'Completed' : 'Pending' }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Vendor -->
        <div class="box">
            <h3>Refund From Vendor :</h3>
            <table class="info-table">
                <tr>
                    <td style="color: #000000; display:block; margin-bottom:8px;">{{ $vendor['name'] }}</td>
                </tr>
                <tr>
                    <td style="color: #6A6A6A; font-size: 14px;">{{ $vendor['email'] }}</td>
                    <td style="text-align:right; color: #6A6A6A; font-size: 14px;">{{ $vendor['address'] }}</td>
                </tr>
                <tr>
                    <td style="color: #6A6A6A; font-size: 14px;">{{ $vendor['contact_number'] }}</td>
                    <td style="text-align:right; color: #6A6A6A; font-size: 14px;">{{ $vendor['city'] }}</td>
                </tr>
            </table>
        </div>

        <!-- Vendor Information -->
        <div class="box">
            <h3>Vendor Information :</h3>
            <table class="info-table">
                <tr>
                    <td>
                        <label style="{{ $label }}">Bank Details</label>
                        <div style="{{ $value }}">{!! nl2br(e($vendor['bank_details'])) !!}</div>
                    </td>
                    <td>
                        <label style="{{ $label }}">Customer</label>
                        <div style="{{ $value }}">{{ $customer['name'] }}</div>
                    </td>
                    <td>
                        <label style="{{ $label }}">Customer Phone</label>
                        <div style="{{ $value }}">{{ $customer['phone'] }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Travel Details -->
        <div class="box">
            <h3>Travel Details :</h3>
            @if ($firstRide)
                <table class="info-table">
                    <tr>
                        <td>
                            <label style="{{ $label }}">From Date</label>
                            <div style="{{ $value }}">{{ $firstRide['from_date'] }}</div>
                        </td>
                        <td>
                            <label style="{{ $label }}">To Date</label>
                            <div style="{{ $value }}">{{ $firstRide['to_date'] }}</div>
                        </td>
                        <td>
                            <label style="{{ $label }}">Service Date</label>
                            <div style="{{ $value }}">{{ $details['service_date'] }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <label style="{{ $label }}">From</label>
                            <div style="{{ $value }}">{{ $firstRide['from_place'] }}</div>
                        </td>
                        <td>
                            <label style="{{ $label }}">To</label>
                            <div style="{{ $value }}">{{ $firstRide['to_place'] }}</div>
                        </td>
                        <td></td>
                    </tr>
                </table>
            @else
                <div style="{{ $value }}">N/A</div>
            @endif

            @if (count($rides) > 1)
                <div style="margin-top: 15px;">
                    <h4 style="margin: 0 0 10px; font-size: 14px; color: #8C8C8C;">All Trip Segments:</h4>
                    @foreach ($rides as $index => $ride)
                        <div style="margin-bottom: 8px; padding: 8px; background-color: #f8f9fa; border-radius: 4px;">
                            <strong>Trip {{ $index + 1 }}:</strong>
                            {{ $ride['from_place'] }} → {{ $ride['to_place'] }}
                            ({{ $ride['from_date'] }} - {{ $ride['to_date'] }})
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Service Details -->
        <div class="box">
            <h3>Service Details :</h3>
            <table class="info-table">
                <tr>
                    <td>
                        <label style="{{ $label }}">Services</label>
                        @forelse ($details['services'] as $service)
                            <div style="{{ $value }}">{{ $service['name'] }} — ₹{{ $money($service['vendor_amount']) }}</div>
                        @empty
                            <div style="{{ $value }}">N/A</div>
                        @endforelse
                    </td>
                </tr>
                @if (count($details['extra_services']))
                    <tr>
                        <td>
                            <label style="{{ $label }}">Extra Services</label>
                            @foreach ($details['extra_services'] as $service)
                                <div style="{{ $value }}">{{ $service['name'] }} — ₹{{ $money($service['vendor_amount']) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @endif
            </table>
        </div>

        <!-- Vendor Payment History -->
        <table class="details-table">
            <thead>
                <tr>
                    <th colspan="4">Vendor Payment History</th>
                </tr>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Narration</th>
                    <th style="text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($details['payment_history'] as $payment)
                    <tr>
                        <td>{{ $payment['paid_date'] }}</td>
                        <td>{{ $payment['payment_method'] }}</td>
                        <td>{{ $payment['narration'] ?: '-' }}</td>
                        <td style="text-align: right;">{{ $money($payment['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No vendor payments recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Vendor Refund History -->
        <table class="details-table">
            <thead>
                <tr>
                    <th colspan="4">Vendor Refund History</th>
                </tr>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reason</th>
                    <th style="text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($details['refund_history'] as $item)
                    <tr>
                        <td>{{ $item['refund_date'] }}</td>
                        <td>{{ $item['refund_type'] }}</td>
                        <td>{{ $item['refund_reason'] ?: '-' }}</td>
                        <td style="text-align: right;">{{ $money($item['amount']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">No vendor refunds recorded.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Refund Details -->
        <table class="details-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr class="amount-row">
                    <td><strong>Original Vendor Amount</strong></td>
                    <td style="text-align: right;"><strong>{{ $money($details['original_vendor_amount']) }}</strong></td>
                </tr>
                <tr>
                    <td>Cancellation Amount</td>
                    <td style="text-align: right;">{{ $details['cancellation_amount'] !== null ? $money($details['cancellation_amount']) : 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Total Paid To Vendor</td>
                    <td style="text-align: right;">{{ $money($details['gross_paid']) }}</td>
                </tr>
                <tr class="refund-amount">
                    <td><strong>Vendor Refund Received</strong></td>
                    <td style="text-align: right;"><strong>{{ $money($details['refund_received']) }}</strong></td>
                </tr>
                <tr>
                    <td>Refund Due</td>
                    <td style="text-align: right;">{{ $money($details['refund_due']) }}</td>
                </tr>
                @if ($refund)
                    <tr>
                        <td>Refund Type</td>
                        <td style="text-align: right;">{{ $refund['refund_type'] ?: 'N/A' }}</td>
                    </tr>
                    @if ($refund['refund_reason'])
                        <tr>
                            <td>Refund Reason</td>
                            <td style="text-align: right;">{{ $refund['refund_reason'] }}</td>
                        </tr>
                    @endif
                @endif
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated vendor refund statement and does not require a signature.</p>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #D1D5DB;">
            <p style="font-size: 11px; color: #9CA3AF;">
                Generated on {{ now()->format('d-m-Y H:i:s') }} |
                Vendor Refund ID: {{ $reference }}
            </p>
        </div>
    </div>
</body>

</html>
