<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Voucher - {{ $voucher->lead->client->name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; margin: 10px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company-name { font-size: 18px; font-weight: bold; color: #333; }
        .voucher-title { font-size: 16px; margin: 10px 0; font-weight: bold; }
        .section { margin-bottom: 15px; page-break-inside: avoid; }
        .section-title { font-size: 14px; font-weight: bold; color: #333; background: #f5f5f5; padding: 5px; margin-bottom: 8px; border-bottom: 1px solid #ddd; }
        .info-row { display: flex; margin-bottom: 5px; }
        .info-label { font-weight: bold; width: 150px; }
        .info-value { flex: 1; }
        .travel-segment { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 11px; }
        .table th, .table td { border: 1px solid #ddd; padding: 5px; text-align: left; }
        .table th { background-color: #f5f5f5; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 10px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
        .page-break { page-break-after: always; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <!-- Page 1 - Cover Page -->
    <div class="header">
        <div class="company-name">ACCRETION AVIATION</div>
        <div class="voucher-title">
            @if($voucher->lead->rideSegments->count() > 0)
                @php
                    $firstService = $voucher->lead->rideSegments->first();
                    $serviceName = strtoupper($firstService->service->service_name ?? 'SERVICE VOUCHER');
                @endphp
                {{ $serviceName }} VOUCHER
            @else
                SERVICE VOUCHER
            @endif
        </div>
    </div>

    <!-- Client Information -->
    <div class="section">
        <div class="section-title">CLIENT DETAILS</div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value">{{ $voucher->lead->client->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone Number:</div>
            <div class="info-value">{{ $voucher->lead->client->contact_number }}</div>
        </div>
        @if($voucher->lead->latestFollowup && $voucher->lead->latestFollowup->pending_amount > 0)
        <div class="info-row">
            <div class="info-label">Pending Amount:</div>
            <div class="info-value">₹{{ number_format($voucher->lead->latestFollowup->pending_amount, 2) }}</div>
        </div>
        @endif
    </div>

    <!-- Special Instructions -->
    @if($voucher->naration)
    <div class="section">
        <div class="section-title">SPECIAL INSTRUCTIONS & NOTES</div>
        <div style="white-space: pre-line;">{{ $voucher->naration }}</div>
    </div>
    @endif

    <!-- Travel Details - Multiple Trips -->
    @foreach($voucher->lead->rideSegments as $index => $ride)
    <div class="section">
        <div class="section-title">TRAVEL DETAILS - TRIP {{ $index + 1 }}</div>
        
        @php
            $fromDate = $ride->from_date;
            $toDate = $ride->to_date;
            $isSameDay = $fromDate->format('Y-m-d') === $toDate->format('Y-m-d');
        @endphp

        <div class="info-row">
            <div class="info-label">Date:</div>
            <div class="info-value">
                @if($isSameDay)
                    {{ $fromDate->format('d-m-Y') }}
                @else
                    {{ $fromDate->format('d-m-Y') }} to {{ $toDate->format('d-m-Y') }}
                @endif
                @if($ride->is_tba) | To Be Announced @endif
            </div>
        </div>

        <div class="info-row">
            <div class="info-label">Time:</div>
            <div class="info-value">{{ $fromDate->format('H:i') }} - {{ $toDate->format('H:i') }}</div>
        </div>

        <div class="info-row">
            <div class="info-label">From:</div>
            <div class="info-value">{{ $ride->from_place }}</div>
        </div>

        <div class="info-row">
            <div class="info-label">To:</div>
            <div class="info-value">{{ $ride->to_place }}</div>
        </div>

        @if($ride->serviceAddress)
        <div class="info-row">
            <div class="info-label">Ride Address:</div>
            <div class="info-value">{{ $ride->serviceAddress->address }}</div>
        </div>
        @endif

        @if($ride->contact_person || $ride->contact_number)
        <div class="info-row">
            <div class="info-label">Contact Person:</div>
            <div class="info-value">
                {{ $ride->contact_person ?? 'N/A' }}
                @if($ride->contact_number) | {{ $ride->contact_number }} @endif
            </div>
        </div>
        @endif
    </div>
    @endforeach

    <!-- Page Break for Multi-page Vouchers -->
    <div class="page-break"></div>

    <!-- Guest List -->
    @if($voucher->passengers && $voucher->passengers->count() > 0)
    <div class="section">
        <div class="section-title">GUEST LIST</div>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Age</th>
                    <th>Contact Number</th>
                    <th>Weight (KG)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voucher->passengers as $passenger)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $passenger->name }}</td>
                    <td>{{ $passenger->age }} Years</td>
                    <td>{{ $passenger->contact_number ?? 'N/A' }}</td>
                    <td>{{ $passenger->weight ?? 'N/A' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Services Information -->
    @if($voucher->vendorPayments && $voucher->vendorPayments->count() > 0)
    <div class="section">
        <div class="section-title">SERVICES BOOKED</div>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Service</th>
                    <th>Vendor</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($voucher->vendorPayments as $payment)
                    @foreach($payment->paymentDetails as $detail)
                    <tr>
                        <td>{{ $loop->parent->iteration }}</td>
                        <td>
                            @if($detail->is_extra_service && $detail->extraService)
                                {{ $detail->extraService->extra_service }}
                            @elseif($detail->service)
                                {{ $detail->service->service_name ?? $detail->service->service }}
                            @else
                                Service Not Found
                            @endif
                        </td>
                        <td>{{ $payment->vendor->name ?? 'N/A' }}</td>
                        <td>₹{{ number_format($detail->service_amount, 2) }}</td>
                    </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Operation Team Information -->
    @if($voucher->operationTeamUser)
    <div class="section">
        <div class="section-title">OPERATION TEAM CONTACT</div>
        <div class="info-row">
            <div class="info-label">Team Member:</div>
            <div class="info-value">{{ $voucher->operationTeamUser->name }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Contact Number:</div>
            <div class="info-value">{{ $voucher->operationTeamUser->contact_number ?? $voucher->operationTeamUser->phone ?? 'N/A' }}</div>
        </div>
    </div>
    @endif

    <!-- Terms & Conditions -->
    <div class="section">
        <div class="section-title">TERMS & CONDITIONS</div>
        
        <div style="margin-bottom: 10px;">
            <strong>1. Scope of Services</strong>
            <p>These terms and conditions apply to all services provided by Accretion Aviation, including but not limited to aerial joyrides, charter private planes, charter helicopters, yachts, air ambulances, hot air balloons, and other services.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>2. Booking and Payment</strong>
            <p>- Booking Confirmation: Bookings will be confirmed upon receipt of full payment in Indian Rupees (INR).</p>
            <p>- Payment Methods: Payment can be made through any of the available options provided by Accretion Aviation.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>3. Passenger Identification</strong>
            <p>- Government-issued ID: All passengers must carry a valid government-issued photo identification card (international passengers must carry their passport) for entry into airports or ports.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>4. Baggage Restrictions</strong>
            <p>- Weight Limit: Each passenger is allowed a maximum of 5 kg of luggage. Handbags Only: Suitcases are not permitted.</p>
            <p>- Overweight Charges: Additional charges will apply for passengers weighing over 75 kg or if the total group weight exceeds 450 kg.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>5. Travel Planning</strong>
            <p>- Weather Conditions: Due to safety considerations, flights may be subject to delays or cancellations due to adverse weather conditions. In such cases, we may need to adjust the itinerary, including potentially staying at one dham for more than one day. Your safety remains our top priority.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>8. Cancellation and Refund</strong>
            <p>- Non-Transferable: All bookings are non-transferable.</p>
            <p>- Cancellation Fees:</p>
            <p>  - Cancellations within 30 days of the departure date: 100% of the total package cost is non-refundable.</p>
            <p>  - Cancellations more than 30 days prior to the departure date: 50% of the total package cost is non-refundable.</p>
        </div>
        
        <div style="margin-bottom: 10px;">
            <strong>9. Liability</strong>
            <p>- Accretion Aviation is not responsible for the actions or omissions of the operators of the aircraft, helicopters, or other vehicles used.</p>
            <p>- Passengers are responsible for their own safety, and Accretion Aviation is not liable for any losses, injuries, or accidents.</p>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>This Document is created on {{ now()->format('d/m/Y H:i A') }}</p>
        <p>Page 1 / 2</p>
    </div>

    <!-- Page Break for Multi-page Vouchers -->
    <div class="page-break"></div>

    <!-- Additional Services Information -->
    <div class="section">
        <div class="section-title">EXPLORE OUR EXCLUSIVE AVIATION & LUXURY TRAVEL SERVICES</div>
        <p style="margin-bottom: 15px;">Whether You're Planning A Quick Getaway, A Medical Transfer, Or A Luxury Experience, We've Got You Covered.</p>
        
        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
            <div style="width: 48%; margin-bottom: 10px;">
                <strong>CHARTER PLANE</strong>
                <p>Private jet charters for business or leisure travel</p>
            </div>
            <div style="width: 48%; margin-bottom: 10px;">
                <strong>YACHT CHARTER</strong>
                <p>Luxury yacht rentals for coastal experiences</p>
            </div>
            <div style="width: 48%; margin-bottom: 10px;">
                <strong>HELICOPTER TOUR</strong>
                <p>Scenic helicopter tours of iconic locations</p>
            </div>
            <div style="width: 48%; margin-bottom: 10px;">
                <strong>AIR AMBULANCE</strong>
                <p>Medical evacuation and air ambulance services</p>
            </div>
        </div>
    </div>

    <!-- Footer for last page -->
    <div class="footer">
        <p>This voucher is generated electronically and serves as confirmation of your booking with Accretion Aviation.</p>
        <p>For any queries, please contact us at your convenience.</p>
        <p>This Document is created on {{ now()->format('d/m/Y H:i A') }}</p>
        <p>Page 2 / 2</p>
    </div>

    <div class="no-print" style="margin-top: 20px; text-align: center;">
        <button onclick="window.print()" style="background: #007bff; color: white; padding: 8px 15px; border: none; cursor: pointer; margin-right: 10px;">Print Voucher</button>
        <a href="{{ route('admin.clients.index') }}" style="background: #6c757d; color: white; padding: 8px 15px; text-decoration: none;">Back to Leads</a>
    </div>
</body>
</html>