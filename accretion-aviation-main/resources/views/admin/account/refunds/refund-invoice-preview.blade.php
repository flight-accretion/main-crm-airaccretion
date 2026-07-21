<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Refund Invoice</title>

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
                        <h2>REFUND INVOICE</h2>
                        <p><strong>Refund Invoice ID:</strong> {{ $refundData['refund_invoice_id'] ?? 'N/A' }}</p>
                        @if($refundData['original_invoice'] && $refundData['original_invoice']->invoice_id)
                        <p><strong>Original Invoice:</strong> {{ $refundData['original_invoice']->invoice_id }}</p>
                        @endif
                        <p><strong>Refund Date:</strong> {{ $refundData['refund_date'] }}</p>
                        <p><strong>Status:</strong> 
                            @switch($refund->status)
                                @case(0) Pending @break
                                @case(1) Processed @break
                                @case(2) Completed @break
                                @case(3) Cancelled @break
                                @default Pending
                            @endswitch
                        </p>
                    </div>
                </td>
            </tr>
        </table>

        <!-- Bill to -->
        <div class="box">
            <h3>Refund to :</h3>
            <table class="info-table">
                <tr>
                    <td style="color: #000000; display:block; margin-bottom:8px;">{{ $refundData['client']->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td style="color: #6A6A6A; font-size: 14px;">{{ $refundData['client']->email ?? 'N/A' }}</td>
                    <td style="text-align:right; color: #6A6A6A; font-size: 14px;">{{ $refundData['client']->address ?? 'N/A' }}</td>
                </tr>
                <tr>
                    @php
                        $contact = $refundData['client']->contact_number ?? null;
                        $alternate = $refundData['client']->alternate_number ?? null;
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
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Company Name</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['original_invoice']->company_name ?? 'Accretion Aviation' }}</div>
                    </td>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">GST Number</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['original_invoice']->gst_number ?? 'N/A' }}</div>
                    </td>
                    <td><label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Billing Address</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['original_invoice']->billing_address ?? 'N/A' }}</div>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Travel Details -->
        <div class="box">
            <h3>Travel Details :</h3>
            <table class="info-table">
                <tr>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">From Date</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['ride']['from_date'] }}</div>
                    </td>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">To Date</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['ride']['to_date'] }}</div>
                    </td>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Duration</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['ride']['duration'] }}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">From</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['ride']['from_place'] }}</div>
                    </td>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">To</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['ride']['to_place'] }}</div>
                    </td>
                    <td></td>
                </tr>
            </table>

            @if(count($refundData['all_rides']) > 1)
            <div style="margin-top: 15px;">
                <h4 style="margin: 0 0 10px; font-size: 14px; color: #8C8C8C;">All Trip Segments:</h4>
                @foreach($refundData['all_rides'] as $index => $ride)
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
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Services</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['service_names'] ?: 'N/A' }}</div>
                    </td>
                </tr>
                @if($refundData['extra_service_names'])
                <tr>
                    <td>
                        <label style="color: #8C8C8C; font-size: 14px; display:block; margin-bottom:4px;">Extra Services</label>
                        <div style="color: #303030; font-size: 14px;">{{ $refundData['extra_service_names'] }}</div>
                    </td>
                </tr>
                @endif
            </table>
        </div>

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
                    <td><strong>Original Amount</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format($refund->original_amount, 2) }}</strong></td>
                </tr>
                <tr class="refund-amount">
                    <td><strong>Refund Amount</strong></td>
                    <td style="text-align: right;"><strong>{{ number_format($refund->refund_amount, 2) }}</strong></td>
                </tr>
                <tr>
                    <td>Refund Type</td>
                    <td style="text-align: right;">{{ $refund->refund_type }}</td>
                </tr>
                @if($refund->refund_reason)
                <tr>
                    <td>Refund Reason</td>
                    <td style="text-align: right;">{{ $refund->refund_reason }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        <!-- Footer -->
        <div class="footer">
            <p>This is a computer-generated refund invoice and does not require a signature.</p>
            <p>For any queries regarding this refund, please contact our support team.</p>
            <hr style="margin: 20px 0; border: none; border-top: 1px solid #D1D5DB;">
            <p style="font-size: 11px; color: #9CA3AF;">
                Generated on {{ now()->format('d-m-Y H:i:s') }} | 
                Refund Invoice ID: {{ $refundData['refund_invoice_id'] }}
            </p>
        </div>
    </div>
</body>

</html>
