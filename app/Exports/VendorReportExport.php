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

class VendorReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = LeadVendorPayment::with([
            'vendor',
            'lead.client',
            'lead.rideSegments',
            'voucher.invoice',
            'paymentDetails',
            'vendorPayments',
            'vendorRefunds',
            'lead.leadFollowups.paymentAuditTrail',
            'lead.representative.userType.parent'
        ]);

        if (!empty($this->filters['client_id'])) {
            $query->whereHas('lead.client', function ($q) {
                $q->where('id', $this->filters['client_id']);
            });
        }
        if (!empty($this->filters['vendor_id'])) {
            $query->where('vendor_id', $this->filters['vendor_id']);
        }
        if (!empty($this->filters['service_id'])) {
            $query->whereHas('paymentDetails', function ($q) {
                $q->where('service_id', $this->filters['service_id']);
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

        $vendorPayments = $query->orderByDesc('created_at')->get();
        $rows = collect();

        foreach ($vendorPayments as $vp) {
            $lead = $vp->lead;
            $client = $lead->client ?? null;
            $clientName = $client->name ?? 'N/A';
            $clientContact = $client->contact_number ?? 'N/A';

            // Product
            $productNames = $lead->product_names ?? [];
            $product = is_array($productNames) ? implode(', ', $productNames) : (is_string($productNames) ? $productNames : 'N/A');

            $originalVendorAmount = round((float) (
                $vp->total_vendor_service_amount
                ?? $vp->total_service_amount
                ?? 0
            ), 2);
            $vendorCost = round((float) $vp->adjusted_vendor_payable, 2);
            $grossPaidAmount = round((float) $vp->total_paid, 2);
            $refundedAmount = round((float) $vp->total_refunded, 2);
            $paidAmount = round((float) $vp->net_paid_to_vendor, 2);
            $balance = round((float) $vp->vendor_payment_balance, 2);
            $refundDueAmount = round((float) $vp->vendor_refund_due, 2);
            $status = $this->formatVendorPaymentStatus($vp->derived_payment_status);

            // Booking slip
            $bookingSlip = 'N/A';
            if ($vp->voucher && $vp->voucher->invoice) {
                $bookingSlip = $vp->voucher->invoice->invoice_id ?? 'N/A';
            }

            // Paid date
            $paidDates = $vp->vendorPayments->pluck('paid_date')->filter();
            $paidDate = 'Not Paid';
            if ($paidDates->isNotEmpty()) {
                $date = $paidDates->sort()->last();
                try { $paidDate = \Carbon\Carbon::parse($date)->format('d M Y'); } catch (\Exception $e) { $paidDate = $date; }
            }

            // Service date
            $firstRide = $lead->rideSegments->first();
            $serviceDate = 'N/A';
            if ($firstRide && $firstRide->from_date) {
                try { $serviceDate = \Carbon\Carbon::parse($firstRide->from_date)->format('d M Y'); } catch (\Exception $e) { $serviceDate = $firstRide->from_date; }
            }

            // Booking date
            $firstPaymentDate = null;
            foreach ($lead->leadFollowups as $followup) {
                $p = $followup->paymentAuditTrail->sortBy('paid_date')->first();
                if ($p && $p->paid_date) {
                    if (!$firstPaymentDate || \Carbon\Carbon::parse($p->paid_date)->lt(\Carbon\Carbon::parse($firstPaymentDate))) {
                        $firstPaymentDate = $p->paid_date;
                    }
                }
            }
            $bookingDate = 'N/A';
            if ($firstPaymentDate) { try { $bookingDate = \Carbon\Carbon::parse($firstPaymentDate)->format('d M Y'); } catch (\Exception $e) { $bookingDate = $firstPaymentDate; } }

            // Ride status
            $rideStatus = 'Pending';
            $followupLatest = $lead->leadFollowups()->orderByDesc('created_at')->first();
            if ($followupLatest && in_array($followupLatest->status, [1,2,5,7])) {
                switch ($followupLatest->status) {
                    case 1: $rideStatus = 'Active'; break;
                    case 2: $rideStatus = 'Cancelled'; break;
                    case 7: $rideStatus = 'Rescheduled'; break;
                    case 5: $rideStatus = 'Complete'; break;
                }
            }

            // Manager name
            $managerName = 'N/A';
            try {
                $rep = $lead->representative ?? null;
                if ($rep && $rep->userType && $rep->userType->parent_id) {
                    $managerUserType = $rep->userType->parent;
                    if ($managerUserType) {
                        $manager = \App\Models\User::where('user_type_id', $managerUserType->id)->first();
                        $managerName = $manager ? $manager->name : 'N/A';
                    }
                }
            } catch (\Exception $e) {}

            $rows->push([
                'vendor_name' => $vp->vendor->name ?? 'N/A',
                'booking_slip' => $bookingSlip,
                'product' => $product,
                'customer_name' => $clientName,
                'customer_number' => $clientContact,
                'original_vendor_amount' => $originalVendorAmount,
                'vendor_service_cost' => $vendorCost,
                'balance_amount' => $balance,
                'gross_paid_amount' => $grossPaidAmount,
                'refunded_amount' => $refundedAmount,
                'paid_amount' => $paidAmount,
                'refund_due_amount' => $refundDueAmount,
                'status' => $status,
                'paid_date' => $paidDate,
                'ride_status' => $rideStatus,
                'service_date' => $serviceDate,
                'booking_date' => $bookingDate,
                'manager_name' => $managerName,
            ]);
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'S.No',
            'Vendor Name',
            'Booking Slip',
            'Product',
            'Customer Name',
            'Customer Number',
            'Original Vendor Amount (INR)',
            'Final Vendor Payable (INR)',
            'Balance Amount (INR)',
            'Gross Paid Amount (INR)',
            'Vendor Refund Received (INR)',
            'Net Paid Amount (INR)',
            'Refund Due (INR)',
            'Status',
            'Date Paid',
            'Ride Status',
            'Service Date',
            'Booking Date',
            'Manager Name'
        ];
    }

    public function map($row): array
    {
        static $i = 0; $i++;
        return [
            $i,
            $row['vendor_name'],
            $row['booking_slip'],
            $row['product'],
            $row['customer_name'],
            $this->formatPhoneNumber($row['customer_number']),
            number_format($row['original_vendor_amount'], 2),
            number_format($row['vendor_service_cost'], 2),
            number_format($row['balance_amount'], 2),
            number_format($row['gross_paid_amount'], 2),
            number_format($row['refunded_amount'], 2),
            number_format($row['paid_amount'], 2),
            number_format($row['refund_due_amount'], 2),
            $row['status'],
            $row['paid_date'],
            $row['ride_status'],
            $row['service_date'],
            $row['booking_date'],
            $row['manager_name']
        ];
    }
    
    /**
     * Format phone number: remove non-digits and country code if present
     * Keep the last 10 digits for local number (India/others)
     */
    private function formatPhoneNumber($number)
    {
        if (empty($number)) {
            return '';
        }
        // Remove all non-digit characters
        $digits = preg_replace('/\D+/', '', $number);
        if (!$digits) {
            return '';
        }
        // If more than 10 digits, keep last 10 digits (assume local number)
        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }
        return $digits;
    }

    private function formatVendorPaymentStatus(?string $status): string
    {
        if ($status === 'paid') {
            return 'Full Paid';
        }

        if ($status === 'partial') {
            return 'Partial Paid';
        }

        return 'Unpaid';
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highest = $sheet->getHighestRow();
                $sheet->getStyle('A1:S1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F81BD']
                    ],
                ]);
                $this->applyColumnFormatting($sheet, $highest);
                $sheet->setAutoFilter('A1:S' . $highest);
                $sheet->freezePane('A2');
            }
        ];
    }

    /**
     * Apply column widths and formatting for Excel export
     */
    private function applyColumnFormatting(Worksheet $sheet, $highestRow)
    {
        // Column widths:
        $sheet->getColumnDimension('A')->setWidth(8);  // S.No
        $sheet->getColumnDimension('B')->setWidth(30); // Vendor Name
        $sheet->getColumnDimension('C')->setWidth(18); // Booking Slip
        $sheet->getColumnDimension('D')->setWidth(30); // Product
        $sheet->getColumnDimension('E')->setWidth(30); // Customer Name
        $sheet->getColumnDimension('F')->setWidth(16); // Customer Number
        $sheet->getColumnDimension('G')->setWidth(18); // Original Vendor Amount
        $sheet->getColumnDimension('H')->setWidth(18); // Final Vendor Payable
        $sheet->getColumnDimension('I')->setWidth(18); // Balance
        $sheet->getColumnDimension('J')->setWidth(18); // Gross Paid Amount
        $sheet->getColumnDimension('K')->setWidth(18); // Vendor Refund Received
        $sheet->getColumnDimension('L')->setWidth(18); // Net Paid Amount
        $sheet->getColumnDimension('M')->setWidth(18); // Refund Due
        $sheet->getColumnDimension('N')->setWidth(14); // Status
        $sheet->getColumnDimension('O')->setWidth(16); // Date Paid
        $sheet->getColumnDimension('P')->setWidth(16); // Ride Status
        $sheet->getColumnDimension('Q')->setWidth(16); // Service Date
        $sheet->getColumnDimension('R')->setWidth(16); // Booking Date
        $sheet->getColumnDimension('S')->setWidth(20); // Manager Name

        // Alignment and wrap
        $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('G2:M' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('D2:D' . $highestRow)->getAlignment()->setWrapText(true)->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
    }
}
