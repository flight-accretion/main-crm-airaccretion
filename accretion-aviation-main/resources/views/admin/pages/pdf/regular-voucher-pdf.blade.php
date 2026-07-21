<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher - {{ $voucher->lead->client->name }}</title>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Marcellus&family=Mulish:ital,wght@1,200..1000&family=Playfair+Display:ital,wght@0,400..900;1,400..900&family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap');

        body {
            font-family: "Mulish", sans-serif;
            padding: 0;
        }

        .header {
            width: 100%;
            text-align: center;
            margin-bottom: 20px;
        }

        .header img {
            width: 100%;
        }

        .title {
            font-family: "DM Serif Display", serif;
            font-size: 30px;
            font-weight: bold;
            text-align: center;
            color: #00227A;
        }

        .title h4 {
            margin: 20px 0 0;
        }

        .service-description {
            font-family: "Inter", sans-serif;
            font-size: 18px;
            text-align: center;
            color: #3B3B3B;
            margin: 0;
        }

        .border-b-1 {
            border: 1px solid rgb(245 184 73);
            border-bottom-width: 0.5px;
        }

        .border-b-2 {
            border: 1px solid #fff;
            border-bottom-width: 0.1px;
        }

        .box {
            padding: 10px;
            border: 0.2px solid rgba(1, 61, 219, 0.5);
            border-radius: 10px;
            margin-bottom: 20px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .box .box-header {
            border-bottom: 1px dashed #013DDB;
            margin-bottom: 10px;
        }

        .box .box-header .section-title {
            font-family: "Playfair Display", serif;
            color: #1B3150;
            margin: 0;
            padding-bottom: 10px;
        }

        .box .box-body {
            font-family: "Mulish", sans-serif;
        }

        .image-wrapper img {
            width: 100%;
        }

        .card {
            padding: 10px;
            border: 1px solid rgba(1, 61, 219, 0.5);
            border-radius: 10px;
            margin-bottom: 20px;
            background-color: #FAFDFF;
        }

        .card-header {
            border-bottom: 1px dashed #013DDB;
            margin-bottom: 10px;
        }

        .guest-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .guest-table th {
            background-color: #1a365d;
            color: #fff;
            text-align: left;
            padding: 8px;
        }

        .guest-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #1B3150;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #718096;
        }

        /* PDF Footer for every page - DomPDF compatible */
        .footer-content {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: 10px;
            font-size: 14px;
            color: #333;
            border-top: 1px solid #e2e8f0;
            background-color: #fff;
            box-sizing: border-box;
            z-index: 1000;
        }

        .footer-left {
            float: left;
            width: 60%;
            font-weight: 500;
        }

        .footer-right {
            float: right;
            width: 35%;
            text-align: right;
            font-weight: 500;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

    /* DomPDF @page rules removed to avoid Blade parsing issues.
       Using the text/php script below (DomPDF) to render footer content
       (creation date and page numbers) on every page instead. */

        /* Responsive design for smaller pages */
        @media print {
            .footer-content {
                position: fixed;
                bottom: 0;
                font-size: 12px;
                padding: 10px 15px;
                height: 35px;
            }
            
            .footer-left, .footer-right {
                font-size: 12px;
            }
        }

        /* Ensure page content doesn't overlap with footer */
        body {
            margin-bottom: 100px;
        }

        /* Page numbering for PDF */
        .pagenum:before {
            content: counter(page);
        }

        .page-break {
            page-break-after: always;
        }

        .break {
            page-break-after: always;
        }

        .terms-conditions-wrap {
            position: relative;
        }

        /* Allow guest list to split across pages */
.guest-list-box {
    page-break-inside: auto !important;
    break-inside: auto !important;
}

/* Let table rows break properly */
.guest-table tr {
    page-break-inside: avoid;
}
.guest-table {
    page-break-inside: auto;
    break-inside: auto;
}
@page {
    margin: 20mm 15mm 25mm 15mm;
}
body {
    margin: 0;
    padding: 0;
}
    </style>
</head>

<body>
    <!-- Header Section -->
    <div class="header">
        <img src="{{ public_path('assets/admin/images/pdf/accreation-voucher-header.png') }}"
            alt="Accretion Aviation Header">
        <div class="title">
            <h4>
                @php
                    // Get the primary service name
                    $primaryService = $voucher->vendorPayments->first()->paymentDetails->first()->service ?? null;
                    $serviceName = $primaryService ? $primaryService->service : 'PRIVATE PLANE CHARTER';
                @endphp
                {{ strtoupper($serviceName) }}
            </h4>
        </div>

        @if ($primaryService && $primaryService->description)
            <div class="service-description">
                <p> {{ $primaryService->description }} </p>
            </div>
        @endif

        <div class="border-b-1"></div>
        <div class="border-b-2"></div>
        <div class="border-b-1"></div>
    </div>

    <!-- Document Info
    <div class="document-info">
        This Document is created on {{ now()->format('jS F, Y | h:i A') }}
    </div> -->

    <!-- Client Details Section -->
    <!-- <div class="box">
        <div class="box-header">
            <h2 class="section-title">Client Details</h2>
        </div>
        <div class="box-body">
            <table style="width:100%; border-collapse: collapse;">
                <tr>
                    <td style="padding:4px; color: #1B3150; font-weight: 400;">
                        <span style="color: #8C8C8C; font-size: 14px;">Name:</span> {{ $voucher->lead->client->name }}
                    </td>
                    <td style="padding:4px; color: #1B3150; font-weight: 400;">
                        <span style="color: #8C8C8C; font-size: 14px;">Phone Number:</span>
                        {{ $voucher->lead->client->contact_number }}
                    </td>
                    @php
                        // Prefer controller-provided $pendingAmount; fallback to vendorPayments total minus approved payments
                        if (!isset($pendingAmount)) {
                            $latestFollowup = $voucher->lead->latestFollowup ?? null;
                            $totalAmount = $latestFollowup->total_amount ?? $voucher->vendorPayments->sum('total_service_amount');
                            $followupIds = $voucher->lead->leadFollowups->pluck('id')->toArray();
                            $approvedPaid = 0;
                            if (!empty($followupIds)) {
                                $approvedPaid = \App\Models\PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                                    ->where('payment_status', 1)
                                    ->sum('paid_amount');
                            }
                            $pendingAmount = max(0, $totalAmount - $approvedPaid);
                        }
                    @endphp

                </tr>
            </table>
        </div>
    </div> -->
    <!-- Guest List Section -->
    <div class="box guest-list-box">
        <div class="box-header">
            <h2 class="section-title">Guest List</h2>
        </div>

        <div class="box-body">
            <table class="guest-table" style="width:100%; border-collapse: collapse;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Weight</th>
                        <!-- @if ($voucher->passengers->where('contact_number', '!=', null)->count() > 0)
                            <th>Contact</th>
                        @endif -->
                    </tr>
                </thead>
                <tbody>
                    @if ($voucher->passengers && $voucher->passengers->count() > 0)
                        @foreach ($voucher->passengers as $index => $passenger)
                            @if (!$passenger->is_handler && !$passenger->is_additional_person)
                                <tr>
                                    <td>#{{ $index + 1 }}</td>
                                    <td>{{ $passenger->name ?? 'N/A' }}
                                    </td>
                                    <td>
                                        @if ($passenger->age)
                                            {{ $passenger->age }} Years
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        @if ($passenger->weight)
                                            {{ $passenger->weight }} KG
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <!-- @if ($voucher->passengers->where('contact_number', '!=', null)->count() > 0)
                                        <td>
                                            {{ $passenger->contact_number ?? 'N/A' }}</td>
                                    @endif -->
                                </tr>
                            @endif
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" style="text-align: center; padding:6px; border:1px solid #ccc;">
                                No passenger information available
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>


    <!-- Special Instructions Section -->
    <!-- Extra Services Section (names only) -->
    @php
        $extraServiceNames = collect();
        if ($voucher->vendorPayments) {
            foreach ($voucher->vendorPayments as $vp) {
                foreach ($vp->paymentDetails as $d) {
                    if (!empty($d->is_extra_service) && $d->is_extra_service) {
                        // Prefer related service name when available
                        $name = null;
                        if (isset($d->service) && $d->service) {
                            $name = $d->service->service ?? $d->service->extra_service ?? null;
                        }
                        if (empty($name)) {
                            try {
                                $es = \App\Models\ExtraService::find($d->service_id);
                                if ($es) {
                                    $name = $es->extra_service ?? $es->name ?? null;
                                }
                            } catch (\Throwable $e) {
                                $name = null;
                            }
                        }

                        $extraServiceNames->push($name ?? 'Extra Service');
                    }
                }
            }
        }

        $extraServiceNames = $extraServiceNames->unique()->values();
    @endphp

    @if ($extraServiceNames->isNotEmpty())
        <div class="box">
            <div class="box-header">
                <h2 class="section-title">Extra Services</h2>
            </div>
            <div class="box-body">
                <ul style="margin:0; padding-left: 18px; color: #1B3150;">
                    @foreach ($extraServiceNames as $name)
                        <li style="margin-bottom:6px;">{{ $name }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    <div class="box">
        <div class="box-header">
            <h2 class="section-title">
                Special Instructions & Notes
                @if (isset($pendingAmount))
                    <span style="float: right; color: #8C8C8C; font-size: 14px; font-weight: 500;">Pending Amount: Rs {{ number_format($pendingAmount, 2) }}</span>
                @endif
            </h2>
        </div>

        <div class="box-body">
            <table style="width:100%; border-collapse: collapse;">
                <tr>
                    <td style="padding:6px;">
                        <p style="color: #1B3150; margin: 0;">
                            @if ($voucher->naration)
                                {!! nl2br(e($voucher->naration)) !!}
                            @else
                                No special instructions provided.
                            @endif
                        </p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Travel Details Section -->
    <div class="box">
        <div class="box-header">
            <h2 class="section-title">Travel Details</h2>
        </div>
        <div class="box-body">
            <div class="card">
                @if ($voucher->lead->rideSegments->count() > 0)
                    @foreach ($voucher->lead->rideSegments as $ride)
                        <div class="card-header">
                            @php
                                // Use the same primary service name logic as in the header
                                $primaryService = $voucher->vendorPayments->first()->paymentDetails->first()->service ?? null;
                                $serviceName = strtolower($primaryService ? $primaryService->service : 'PRIVATE PLANE CHARTER');
                                                                
                                // Check for specific service types in the full service name
                                if (str_contains($serviceName, 'air ambulance') || str_contains($serviceName, 'air-ambulance') || str_contains($serviceName, 'airambulance') || str_contains($serviceName, 'air ambulace') || str_contains($serviceName, 'ambulance')) {
                                    $iconFile = public_path('assets/admin/images/pdf/air-ambulance.svg');
                                } elseif (str_contains($serviceName, 'helicopter')) {
                                    $iconFile = public_path('assets/admin/images/pdf/Helicopter.svg');
                                } elseif (str_contains($serviceName, 'yacht') || str_contains($serviceName, 'yatch') || str_contains($serviceName, 'boat')) {
                                    $iconFile = public_path('assets/admin/images/pdf/yacht.svg');
                                } elseif (str_contains($serviceName, 'plane') || str_contains($serviceName, 'aircraft') || str_contains($serviceName, 'airplane')) {
                                    $iconFile = public_path('assets/admin/images/pdf/plane.svg');
                                } else {
                                    // default to bus for unknown services
                                    $iconFile = public_path('assets/admin/images/pdf/bus.svg');
                                }

                                // File existence check with fallback - only fallback to bus if the chosen file doesn't exist
                                if (!file_exists($iconFile)) {
                                    // Try alternative files first before defaulting to bus
                                    if (str_contains($serviceName, 'ambulance')) {
                                        $altFile = public_path('assets/admin/images/pdf/air-ambulance.png');
                                        if (file_exists($altFile)) {
                                            $iconFile = $altFile;
                                        }
                                    }
                                    // Final fallback to bus only if nothing else works
                                    if (!file_exists($iconFile)) {
                                        $iconFile = public_path('assets/admin/images/pdf/bus.svg');
                                    }
                                }
                            @endphp

                            @php
                                // Determine if from and to places (and their dates) are effectively the same
                                $fromPlace = trim($ride->from_place ?? '');
                                $toPlace = trim($ride->to_place ?? '');

                                // Normalize casing: ensure first letter of each word is capitalized
                                // Use mb_strtolower to avoid issues with uppercase letters in mixed-case input
                                if ($fromPlace !== '') {
                                    $fromPlace = ucwords(mb_strtolower($fromPlace));
                                }
                                if ($toPlace !== '') {
                                    $toPlace = ucwords(mb_strtolower($toPlace));
                                }

                                // Normalize dates for comparison (null-safe)
                                $fromDateStr = $ride->from_date ? $ride->from_date->format('Y-m-d H:i') : null;
                                $toDateStr = $ride->to_date ? $ride->to_date->format('Y-m-d H:i') : null;

                                $samePlace = $fromPlace !== '' && $toPlace !== '' && strcasecmp($fromPlace, $toPlace) === 0;
                                $sameDate = $fromDateStr && $toDateStr && $fromDateStr === $toDateStr;

                                // Consider them same if both place and date are same OR both places same and dates are both null
                                $showSingle = ($samePlace && $sameDate) || ($samePlace && !$fromDateStr && !$toDateStr);

                                // If places are same but from/to datetimes differ, show single place and display a From -> To time range
                                $showSingleWithRange = false;
                                if ($samePlace && $fromDateStr && $toDateStr && $fromDateStr !== $toDateStr) {
                                    $showSingleWithRange = true;
                                }
                            @endphp

                            @if ($showSingleWithRange)
                                <table width="100%" border="0" cellspacing="0" cellpadding="6" style="margin-bottom:15px;">
                                    <tr>
                                        <td style="text-align:center; vertical-align:top;">
                                            <div style="display:inline-block; vertical-align:middle;">
                                                <span style="font-size:24px; color:#00227A; font-weight:bold;">
                                                    {{ $fromPlace ?: 'TBA' }}
                                                </span>
                                            </div>
                                            <p style="margin:5px 0 0; color:#303030; font-size:14px;">
                                                @php
                                                    $fromDateOnly = $ride->from_date ? $ride->from_date->format('Y-m-d') : null;
                                                    $toDateOnly = $ride->to_date ? $ride->to_date->format('Y-m-d') : null;
                                                @endphp

                                                @if ($fromDateOnly && $toDateOnly && $fromDateOnly === $toDateOnly)
                                                    {{-- Same calendar date but different times: show date once and time range --}}
                                                    {{ $ride->from_date->format('jS F, Y') }}
                                                    @php
                                                        $fromTime = $ride->from_date->format('h:i A');
                                                        $toTime = $ride->to_date->format('h:i A');
                                                    @endphp
                                                    | {{ $fromTime }} - {{ $toTime }} IST
                                                @else
                                                    {{-- Different dates/times: show full from -> to with dates and times --}}
                                                    From: {{ $ride->from_date ? $ride->from_date->format('jS F, Y | h:i A') . ' IST' : 'TBA' }}
                                                    <br>
                                                    To: {{ $ride->to_date ? $ride->to_date->format('jS F, Y | h:i A') . ' IST' : 'TBA' }}
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>

                            @elseif ($showSingle)
                                <table width="100%" border="0" cellspacing="0" cellpadding="6" style="margin-bottom:15px;">
                                    <tr>
                                        <td style="text-align:center; vertical-align:top;">
                                            <div style="display:inline-block; vertical-align:middle;">
                                                <span style="font-size:24px; color:#00227A; font-weight:bold;">
                                                    {{ $fromPlace ?: 'TBA' }}
                                                </span>
                                            </div>
                                            <p style="margin:5px 0 0; color:#303030; font-size:14px;">
                                                @if ($ride->from_date)
                                                    {{ $ride->from_date->format('jS F, Y') }}
                                                    @if ($ride->from_date->format('H:i') !== '00:00')
                                                        | {{ $ride->from_date->format('h:i A') }} IST
                                                    @endif
                                                @else
                                                    Date/Time TBA
                                                @endif
                                            </p>
                                        </td>
                                    </tr>
                                </table>
                            @else
                                <table width="100%" border="0" cellspacing="0" cellpadding="6" style="margin-bottom:15px;">
                                    <tr>
                                        <!-- From Place -->
                                        <td width="45%" style="text-align:left; vertical-align:top;">
                                            <!-- <div style="display:inline-block; vertical-align:middle;">
                                                <img src="{{ $iconFile }}" style="vertical-align:middle; margin-right:5px; max-width:32px; height:auto;">
                                            </div> -->
                                            <div style="display:inline-block; vertical-align:middle;">
                                                <span style="font-size:24px; color:#00227A; font-weight:bold;">
                                                    {{ $fromPlace ?: 'TBA' }}
                                                </span>
                                            </div>
                                            <p style="margin:5px 0 0; color:#303030; font-size:14px;">
                                                @if ($ride->from_date)
                                                    {{ $ride->from_date->format('jS F, Y') }}
                                                    @if ($ride->from_date->format('H:i') !== '00:00')
                                                        | {{ $ride->from_date->format('h:i A') }} IST
                                                    @endif
                                                @else
                                                    Date/Time TBA
                                                @endif
                                            </p>
                                        </td>


                                        <!-- Arrow -->
                                        <td width="10%" style="text-align:center; vertical-align:middle;">
                                            <img src="{{ public_path('assets/admin/images/pdf/arrow.svg') }}">
                                        </td>

                                        <!-- To Place -->
                                        <td width="45%" style="text-align:right; vertical-align:top;">
                                            <div style="display:inline-block; vertical-align:middle;">
                                                <span style="font-size:24px; color:#00227A; font-weight:bold;">
                                                    {{ $toPlace ?: 'TBA' }}
                                                </span>
                                            </div>
                                            <p style="margin:5px 0 0; color:#303030; font-size:14px;">
                                                @if ($ride->to_date)
                                                    {{ $ride->to_date->format('jS F, Y') }}
                                                    @if ($ride->to_date->format('H:i') !== '00:00')
                                                        | {{ $ride->to_date->format('h:i A') }} IST
                                                    @endif
                                                @else
                                                    Date/Time TBA
                                                @endif
                                            </p>
                                        </td>

                                    </tr>
                                </table>
                            @endif
                        </div>

                        @if ($ride->serviceAddress)
                            <div class="travel-address" style="margin-bottom:10px;">

                                {{-- First Row: Ride Address, Contact Person, Contact Number --}}
                                <table width="100%" cellspacing="0" cellpadding="4" style="border-collapse:collapse; margin-bottom: 10px;">
                                    <tr>
                                        <td width="40%">
                                            <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Ride Address:</div>
                                            <div style="color: #1B3150; font-weight: 400;">
                                                {{ $ride->serviceAddress->address ?? 'Address not available' }}
                                            </div>
                                        </td>
                                        @if ($ride->serviceAddress->contact_person_name)
                                            <td width="30%">
                                                <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Contact Person:</div>
                                                <div style="color: #1B3150; font-weight: 400;">
                                                    {{ $ride->serviceAddress->contact_person_name }}
                                                </div>
                                            </td>
                                        @endif
                                        @if ($ride->serviceAddress->contact_number)
                                            <td width="30%">
                                                <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Contact Number:</div>
                                                <div style="color: #1B3150; font-weight: 400;">
                                                    {{ $ride->serviceAddress->contact_number }}
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                </table>

                                {{-- Second Row: Additional Person & Contact (from lead passengers with flag) --}}
                                @php
                                    $additionalPerson = $voucher->passengers->where('is_additional_person', true)->first();
                                @endphp
                                @if ($additionalPerson)
                                    <table width="100%" cellspacing="0" cellpadding="4" style="border-collapse:collapse;">
                                        <tr>
                                            <td width="50%">
                                                <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Additional Person:</div>
                                                <div style="color: #1B3150; font-weight: 400;">
                                                    {{ $additionalPerson->name }}
                                                </div>
                                            </td>
                                            @if ($additionalPerson->contact_number)
                                                <td width="50%">
                                                    <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Additional Contact:</div>
                                                    <div style="color: #1B3150; font-weight: 400;">
                                                        {{ $additionalPerson->contact_number }}
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    </table>
                                @endif
                                {{-- Map Link (if provided) --}}
                                @if (!empty($ride->serviceAddress->map_link))
                                    @php
                                        $rawMapLink = trim($ride->serviceAddress->map_link);
                                        // ensure the link has a scheme so PDF link works; default to https if missing
                                        $mapLink = preg_match('/^https?:\/\//i', $rawMapLink) ? $rawMapLink : 'https://' . $rawMapLink;
                                    @endphp
                                    <table width="100%" cellspacing="0" cellpadding="4" style="border-collapse:collapse; margin-top: 8px; table-layout: fixed;">
                                        <tr>
                                            <td style="word-wrap: break-word; overflow-wrap: break-word;">
                                                <div style="color: #8C8C8C; font-size: 14px; margin-bottom:5px;">Map Link:</div>
                                                <div style="color: #1B3150; font-weight: 400;">
                                                    {{-- Add robust wrapping styles so very long URLs don't overflow the PDF layout. --}}
                                                    <a href="{{ $mapLink }}" title="{{ $ride->serviceAddress->map_link }}"
                                                       style="color: #1B3150; text-decoration: none; display: inline-block; max-width: 100%; white-space: normal; word-break: break-word; overflow-wrap: break-word;" target="_blank">
                                                        {{ $ride->serviceAddress->map_link }}
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </div>
                        @endif
                    @endforeach
                @else
                    <div style="text-align: center;">Travel details to be announced.</div>
                @endif
            </div>
        </div>
    </div>





    <!-- Footer Image -->
    <div class="image-wrapper">
        <img src="{{ public_path('assets/admin/images/pdf/service-footer.jpg') }}" alt="Service Footer">
    </div>

    <!-- Terms & Conditions Section -->
    <!-- <div class="break"></div> -->

    <div class="box terms-conditions-wrap">
        <div class="top"></div>
        <div class="box-header">
            <h2 class="section-title">Terms & Conditions</h2>
        </div>
        <div class="box-body">
            @php
                // Get all unique services from vendor payments
                $allServices = collect();
                if ($voucher->vendorPayments) {
                    foreach ($voucher->vendorPayments as $vendorPayment) {
                        foreach ($vendorPayment->paymentDetails as $detail) {
                            if (
                                $detail->service &&
                                !$detail->is_extra_service &&
                                $detail->service->terms_and_conditions
                            ) {
                                $allServices->push($detail->service);
                            }
                        }
                    }
                }
                $allServices = $allServices->unique('id');
            @endphp

            @if ($allServices->count() > 0)
                @foreach ($allServices as $service)
                    @if ($service->terms_and_conditions)
                        <div class="terms-section" style="margin:0;">
                            <!-- <div class="terms-title">Terms & Conditions for {{ $service->service }}</div> -->
                            <div class="terms-content" style="color: #1B3150; margin: 0;">
                                <p style="margin: 0;">{!! $service->terms_and_conditions !!}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            @else
                <!-- Fallback to general terms if no service-specific terms -->
                <div class="terms-section">
                    <div class="terms-content">
                        Please proceed to any booking through this website if you have understood & agreed
                        to all
                        terms and conditions for Accretion Aviation [AA] mentioned below. These terms and
                        conditions apply to all/any services (including and not limited to Aerial Joyrides,
                        Charter
                        Private Plane, Charter Helicopter, Yachts, Air Ambulance, Hot Air Balloon and other
                        services
                        offered by AA) availed by any customer through us or our agents.
                    </div>

                    <div class="terms-title" style="margin-top: 15px;">General Terms</div>
                    <div class="terms-content">
                        <ul style="margin-top: 5px; padding-left: 20px;">
                            <li style="margin-bottom: 5px;">Please contact us for specific terms and
                                conditions
                                related
                                to
                                your service.</li>
                            <li style="margin-bottom: 5px;">All bookings are subject to availability and
                                confirmation.
                            </li>
                            <li style="margin-bottom: 5px;">Payment terms and cancellation policies apply as
                                per
                                service
                                agreement.</li>
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>


    <!-- Extra Upload Section - Auto Merge Documents -->
    @if ($voucher->extra_upload)
        <div class="box">
            <div class="box-header">
                <h2 class="section-title">Additional Documents</h2>
            </div>
            <div class="box-body">
                @php
                    $extraUploadPath = storage_path('app/public/' . $voucher->extra_upload);
                    $fileExtension = pathinfo($voucher->extra_upload, PATHINFO_EXTENSION);
                @endphp

                @if (in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']))
                    <img src="{{ storage_path('app/public/' . $voucher->extra_upload) }}"
                        style="max-width: 100%; height: auto; border: 1px solid #ddd; padding: 10px;">
                @else
                    <ul>
                        <li>
                            <a
                                href="{{ basename($voucher->extra_upload) }}">{{ basename($voucher->extra_upload) }}</a>
                        </li>
                    </ul>
                @endif
            </div>
        </div>
    @endif
    <!-- Footer -->
    <div class="footer">
        <p>Thank you for choosing Accretion Aviation</p>
        <p>For any queries, please contact us at charter@accretion.in or call +91 9575340786</p>
    </div>
    
    <!-- Fixed Footer for every page -->
    <div class="footer-content clearfix">
        <div class="footer-left">
            Document created on {{ $voucher->created_at->format('d/m/Y h:i A') }}
        </div>
        <div class="footer-right">
            Page <span class="pagenum"></span>
        </div>
    </div>

</body>

</html>
