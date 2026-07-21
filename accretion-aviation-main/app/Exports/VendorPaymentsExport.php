<?php

namespace App\Exports;

use App\Models\LeadVendorPayment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

class VendorPaymentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        // Build query with same filtering logic as controller
        $query = LeadVendorPayment::with([
            'lead.client',
            'lead.leadFollowups.paymentAuditTrail',
            'lead.leadFollowups.followedBy',
            'lead.rideSegments',
            'vendor',
            'voucher.invoice',
            'paymentDetails.service',
            'paymentDetails.extraService',
            'vendorPayments'
        ]);

        // Apply filters (capture values locally so closures can use them)
        $clientId = $this->filters['client_id'] ?? null;
        $vendorId = $this->filters['vendor_id'] ?? null;
        $serviceId = $this->filters['service_id'] ?? null;

        if (!empty($clientId)) {
            $query->whereHas('lead.client', function ($q) use ($clientId) {
                $q->where('id', $clientId);
            });
        }

        if (!empty($vendorId)) {
            $query->where('vendor_id', $vendorId);
        }

        if (!empty($serviceId)) {
            $query->whereHas('paymentDetails', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId);
            });
        }

        if (!empty($this->filters['status'])) {
            $status = strtolower(trim($this->filters['status']));
            if (in_array($status, ['paid', 'full paid', 'full_paid', 'full'])) {
                $query->where('payment_status', 'paid');
            } elseif (in_array($status, ['partial', 'partial paid', 'partial_paid'])) {
                $query->where('payment_status', 'partial');
            } elseif (in_array($status, ['unpaid', 'not paid', 'not_paid'])) {
                $query->where(function ($q) {
                    $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
                });
            } else {
                $query->where('payment_status', $this->filters['status']);
            }
        }

        $vendorPayments = $query->get();

        // Group by lead and prepare data
        $groupedByLead = $vendorPayments->groupBy('lead_id');
        $exportData = collect();
        
        $groupedByLead->each(function ($vendorPaymentsGroup, $leadId) use (&$exportData) {
            $lead = $vendorPaymentsGroup->first()->lead;
            $clientName = $lead->client->name ?? 'N/A';
            $clientEmail = $lead->client->email ?? 'N/A';
            $clientContact = $lead->client->contact_number ?? 'N/A';
            
            // Calculate client's total paid amount from confirmed payments in Payment Review
            $clientPaidAmount = $this->getClientConfirmedPayments($lead);
            
            $vendorPaymentsGroup->each(function ($vendorPayment) use (&$exportData, $clientName, $clientEmail, $clientContact, $clientPaidAmount) {
                $vendorName = $vendorPayment->vendor->name ?? 'N/A';
                
                // Get vendor specific service info
                $vendorServiceInfo = $this->getVendorSpecificServiceInfo($vendorPayment);
                
                $vendorServiceCost = $vendorPayment->total_vendor_service_amount ?? 0;
                $paidAmount = $vendorPayment->vendorPayments->sum('paid_amount') ?? 0;
                $balanceAmount = $vendorServiceCost - $paidAmount;
                
                // Get latest paid date
                $vendorPaidDates = $vendorPayment->vendorPayments->pluck('paid_date')->filter();
                $latestPaidDate = 'Not Paid';
                if ($vendorPaidDates->isNotEmpty()) {
                    $latest = $vendorPaidDates->sort()->last();
                    try {
                        $latestPaidDate = \Carbon\Carbon::parse($latest)->format('d M Y');
                    } catch (\Exception $e) {
                        $latestPaidDate = $latest;
                    }
                }
                
                // Determine status
                $status = 'Unpaid';
                if ($paidAmount >= $vendorServiceCost && $vendorServiceCost > 0) {
                    $status = 'Full Paid';
                } elseif ($paidAmount > 0) {
                    $status = 'Partial Paid';
                }
                
                $serviceDisplay = $vendorServiceInfo['service_display'];
                if (!empty($vendorServiceInfo['extra_service_display'])) {
                    $serviceDisplay .= ', ' . $vendorServiceInfo['extra_service_display'];
                }
                
                // Get Booking Slip Number from Invoice
                $bookingSlipNumber = 'N/A';
                if ($vendorPayment->voucher && $vendorPayment->voucher->invoice) {
                    $bookingSlipNumber = $vendorPayment->voucher->invoice->invoice_id ?? 'N/A';
                }
                
                // Get Lead Created By (followup with status = 0)
                $leadCreatedBy = 'N/A';
                $createdByFollowup = $vendorPayment->lead->leadFollowups
                    ->where('status', 0)
                    ->first();
                if ($createdByFollowup && $createdByFollowup->followedBy) {
                    $leadCreatedBy = $createdByFollowup->followedBy->name;
                }
                
                // Get Ride Status
                // Priority: use lead followup status when present and one of [1,2,5,7]
                // Mapping: 1 = Active, 2 = Cancelled, 7 = Reschedule, 5 = Confirm/Complete
                // Otherwise fallback to ride segment dates or 'Pending'
                $rideStatus = 'Pending';
                // Ensure we always have first ride available for fallback and service date
                $firstRide = $vendorPayment->lead->rideSegments->first();
                // Check followups for a status in priority order (use latest followup by created_at if available)
                $followupStatusRecord = $vendorPayment->lead->leadFollowups()->orderByDesc('created_at')->first();
                if ($followupStatusRecord && in_array($followupStatusRecord->status, [1,2,5,7])) {
                    switch ($followupStatusRecord->status) {
                        case 1:
                            $rideStatus = 'Active';
                            break;
                        case 2:
                            $rideStatus = 'Cancelled';
                            break;
                        case 7:
                            $rideStatus = 'Rescheduled';
                            break;
                        case 5:
                            $rideStatus = 'Complete';
                            break;
                    }
                } else {
                    // Fallback: use ride segment dates (we already have $firstRide)
                    if ($firstRide) {
                        if (!empty($firstRide->is_tba)) {
                            $rideStatus = 'TBA';
                        } elseif ($firstRide->from_date && $firstRide->to_date) {
                            $now = \Carbon\Carbon::now();
                            $fromDate = \Carbon\Carbon::parse($firstRide->from_date);
                            $toDate = \Carbon\Carbon::parse($firstRide->to_date);
                            if ($now->lt($fromDate)) {
                                $rideStatus = 'Upcoming';
                            } elseif ($now->between($fromDate, $toDate)) {
                                $rideStatus = 'In Progress';
                            } else {
                                $rideStatus = 'Completed';
                            }
                        } else {
                            $rideStatus = 'Pending';
                        }
                    }
                }
                
                // Get No. of Passengers from Lead
                $noOfPassengers = $vendorPayment->lead->number_of_passengers ?? 'N/A';
                
                // Get Booking Date (first payment date from PaymentAuditTrail)
                $bookingDate = 'N/A';
                $firstPaymentDate = null;
                foreach ($vendorPayment->lead->leadFollowups as $followup) {
                    $firstPayment = $followup->paymentAuditTrail
                        ->sortBy('paid_date')
                        ->first();
                    if ($firstPayment && $firstPayment->paid_date) {
                        if (!$firstPaymentDate || \Carbon\Carbon::parse($firstPayment->paid_date)->lt(\Carbon\Carbon::parse($firstPaymentDate))) {
                            $firstPaymentDate = $firstPayment->paid_date;
                        }
                    }
                }
                if ($firstPaymentDate) {
                    try {
                        $bookingDate = \Carbon\Carbon::parse($firstPaymentDate)->format('d M Y');
                    } catch (\Exception $e) {
                        $bookingDate = $firstPaymentDate;
                    }
                }
                
                // Get Service Date from LeadRide
                $serviceDate = 'N/A';
                if ($firstRide && $firstRide->from_date) {
                    try {
                        $serviceDate = \Carbon\Carbon::parse($firstRide->from_date)->format('d M Y');
                    } catch (\Exception $e) {
                        $serviceDate = $firstRide->from_date;
                    }
                }
                
                $exportData->push([
                    'client_name' => $clientName,
                    'client_email' => $clientEmail,
                    'client_contact' => $clientContact,
                    'client_paid_amount' => $clientPaidAmount,
                    'vendor_name' => $vendorName,
                    'service_display' => $serviceDisplay,
                    'vendor_service_cost' => $vendorServiceCost,
                    'balance_amount' => $balanceAmount,
                    'paid_amount' => $paidAmount,
                    'paid_date' => $latestPaidDate,
                    'status' => $status,
                    'booking_slip_number' => $bookingSlipNumber,
                    'lead_created_by' => $leadCreatedBy,
                    'ride_status' => $rideStatus,
                    'no_of_passengers' => $noOfPassengers,
                    'booking_date' => $bookingDate,
                    'service_date' => $serviceDate,
                ]);
            });
        });

        return $exportData;
    }

    public function headings(): array
    {
        return [
            'S. No',
            'Client Name',
            'Client Email',
            'Client Contact',
            'Client Received Amount (INR)',
            'Vendor Name',
            'Service (Including Extra)',
            'Vendor Service Cost (INR)',
            'Balance Amount (INR)',
            'Paid Amount (INR)',
            'Date Paid',
            'Status',
            'Booking Slip Number',
            'Lead Created By',
            'Ride Status',
            'No. of Passengers',
            'Booking Date',
            'Service Date'
        ];
    }

    public function map($row): array
    {
        static $index = 0;
        $index++;

        return [
            $index,
            $row['client_name'],
            $row['client_email'],
            $this->formatPhoneNumber($row['client_contact']),
            '₹' . number_format($row['client_paid_amount'], 2),
            $row['vendor_name'],
            $row['service_display'],
            '₹' . number_format($row['vendor_service_cost'], 2),
            '₹' . number_format($row['balance_amount'], 2),
            '₹' . number_format($row['paid_amount'], 2),
            $row['paid_date'],
            $row['status'],
            $row['booking_slip_number'],
            $row['lead_created_by'],
            $row['ride_status'],
            $row['no_of_passengers'],
            $row['booking_date'],
            $row['service_date']
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Minimal static styles here; AfterSheet will apply full formatting
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Format phone number: remove non-digits and country code if present
     * Keep the last 10 digits for local number
     */
    private function formatPhoneNumber($number)
    {
        if (empty($number)) {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $number);
        if (!$digits) {
            return '';
        }
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }

    /**
     * Register events to style sheet after it's created (column widths, autofilter, alignments)
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Apply header style: bold white text on blue background
                $sheet->getStyle('A1:R1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F81BD']
                    ],
                ]);

                // Set sensible column widths
                $sheet->getColumnDimension('A')->setWidth(8);   // S. No
                $sheet->getColumnDimension('B')->setWidth(30);  // Client Name
                $sheet->getColumnDimension('C')->setWidth(30);  // Client Email
                $sheet->getColumnDimension('D')->setWidth(20);  // Client Contact
                $sheet->getColumnDimension('E')->setWidth(20);  // Client Received Amount
                $sheet->getColumnDimension('F')->setWidth(25);  // Vendor Name
                $sheet->getColumnDimension('G')->setWidth(45);  // Service
                $sheet->getColumnDimension('H')->setWidth(18);  // Vendor Service Cost
                $sheet->getColumnDimension('I')->setWidth(18);  // Balance Amount
                $sheet->getColumnDimension('J')->setWidth(18);  // Paid Amount
                $sheet->getColumnDimension('K')->setWidth(16);  // Date Paid
                $sheet->getColumnDimension('L')->setWidth(14);  // Status
                $sheet->getColumnDimension('M')->setWidth(18);  // Booking Slip Number
                $sheet->getColumnDimension('N')->setWidth(20);  // Lead Created By
                $sheet->getColumnDimension('O')->setWidth(15);  // Ride Status
                $sheet->getColumnDimension('P')->setWidth(18);  // No. of Passengers
                $sheet->getColumnDimension('Q')->setWidth(16);  // Booking Date
                $sheet->getColumnDimension('R')->setWidth(16);  // Service Date

                // Apply number formats and alignment for data rows
                $highestRow = $sheet->getHighestRow();
                $dataRange = 'A2:R' . $highestRow;

                // Right align numeric currency columns (E, H, I, J) and center A, P
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('H2:J' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('P2:P' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                // Wrap text for service column
                $sheet->getStyle('G2:G' . $highestRow)->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);

                // Apply autofilter
                $sheet->setAutoFilter('A1:R' . $highestRow);

                // Freeze the header row
                $sheet->freezePane('A2');
            },
        ];
    }

    /**
     * Get client's total confirmed payments from Payment Audit Trail
     */
    private function getClientConfirmedPayments($lead)
    {
        $totalPaidAmount = 0;

        // Get all lead followups for this lead
        $leadFollowups = $lead->leadFollowups;

        foreach ($leadFollowups as $followup) {
            // Get all approved payment audit trail entries for this followup
            // payment_status = 1 means approved/confirmed
            $confirmedPayments = $followup->paymentAuditTrail()
                ->where('payment_status', 1)
                ->get();

            // Sum up all confirmed payments
            $totalPaidAmount += $confirmedPayments->sum('paid_amount');
        }

        return $totalPaidAmount;
    }

    private function getVendorSpecificServiceInfo($vendorPayment)
    {
        $services = [];
        $extraServices = [];

        // Get services and extra services specific to this vendor payment
        foreach ($vendorPayment->paymentDetails as $detail) {
            if ($detail->is_extra_service) {
                // This is an extra service
                if ($detail->relationLoaded('extraService') && $detail->extraService) {
                    $extraServices[] = $detail->extraService->extra_service;
                } elseif (!empty($detail->service_name)) {
                    $extraServices[] = $detail->service_name;
                }
            } else {
                // This is a regular service
                if ($detail->relationLoaded('service') && $detail->service) {
                    $services[] = $detail->service->service;
                } elseif (!empty($detail->service_name)) {
                    $services[] = $detail->service_name;
                }
            }
        }

        // Remove duplicates and filter empty values
        $services = array_unique(array_filter($services));
        $extraServices = array_unique(array_filter($extraServices));

        return [
            'services' => $services,
            'extra_services' => $extraServices,
            'service_display' => implode(', ', $services),
            'extra_service_display' => implode(', ', $extraServices),
        ];
    }
}
