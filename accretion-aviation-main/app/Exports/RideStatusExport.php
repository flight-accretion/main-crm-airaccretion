<?php

namespace App\Exports;

use App\Models\LeadRide;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\PaymentAuditTrail;
use App\Models\Invoice;
use App\Models\Voucher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class RideStatusExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;
    protected $exportData = [];

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        $this->prepareData();
    }

    /**
     * Prepare export data using the same logic as rideStatus() controller method
     */
    private function prepareData()
    {
        // Build base query (same as rideStatus controller)
        $ridesQuery = LeadRide::with([
            'enquiry.client',
            'enquiry.representative',
            'enquiry.vouchers.invoice',
            'enquiry.leadVendorPayments.vendor',
            'enquiry.leadFollowups' => function ($query) {
                $query->with('paymentAuditTrail')->orderByDesc('created_at');
            }
        ])->whereHas('enquiry.vouchers');

        // Extract filters
        $fromDate = !empty($this->filters['from_date']) ? $this->filters['from_date'] : null;
        $toDate = !empty($this->filters['to_date']) ? $this->filters['to_date'] : null;
        $statusFilter = !empty($this->filters['status']) ? $this->filters['status'] : null;
        $nameFilter = !empty($this->filters['name']) ? $this->filters['name'] : null;
        $phoneFilter = !empty($this->filters['phone']) ? $this->filters['phone'] : null;
        $productFilter = !empty($this->filters['product_id']) ? $this->filters['product_id'] : null;
        $serviceFilter = !empty($this->filters['service_id']) ? $this->filters['service_id'] : null;

        // Apply date range filter (filter by ride from_date)
        if ($fromDate && $toDate) {
            $ridesQuery->whereBetween(DB::raw('DATE(from_date)'), [
                Carbon::parse($fromDate)->toDateString(),
                Carbon::parse($toDate)->toDateString(),
            ]);
        }

        // Apply status filter to query
        if ($statusFilter !== null && $statusFilter !== '') {
            $statusArray = is_array($statusFilter) ? $statusFilter : [$statusFilter];
            $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) use ($statusArray) {
                $q->whereIn('status', array_map('intval', $statusArray));
            });
        } else {
            // Default: include common statuses
            $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) {
                $q->whereIn('status', [1, 2, 3, 4, 5, 6, 7]);
            });
        }

        // Apply name filter
        if ($nameFilter) {
            $ridesQuery->whereHas('enquiry.client', function ($q) use ($nameFilter) {
                $q->where('name', 'ilike', '%' . $nameFilter . '%');
            });
        }

        // Apply phone filter
        if ($phoneFilter) {
            $ridesQuery->whereHas('enquiry.client', function ($q) use ($phoneFilter) {
                $q->where('contact_number', 'ilike', '%' . $phoneFilter . '%')
                    ->orWhere('alternate_number', 'ilike', '%' . $phoneFilter . '%');
            });
        }

        // Apply product filter
        if ($productFilter) {
            $ridesQuery->whereHas('enquiry', function ($q) use ($productFilter) {
                $q->where('product_ids', 'like', '%' . $productFilter . '%');
            });
        }

        // Apply service filter
        if ($serviceFilter) {
            $ridesQuery->whereHas('enquiry.leadFollowups', function ($q) use ($serviceFilter) {
                $q->where('service_ids', 'like', '%' . $serviceFilter . '%');
            });
        }

        // Get and group by lead_id (CRITICAL: group after query, not in DB)
        $rides = $ridesQuery->orderBy('created_at', 'desc')->get()->groupBy('lead_id');

        // Transform the grouped data (same as controller)
        $processedData = [];
        foreach ($rides as $leadId => $rideGroup) {
            $mainRide = $rideGroup->first();
            $lead = $mainRide->enquiry;
            $client = $lead->client ?? null;
            $latestFollowup = $lead->leadFollowups->first();

            // Get service names from latest followup
            $serviceNames = [];
            $extraServiceNames = [];
            if ($latestFollowup && $latestFollowup->service_ids) {
                $serviceIds = is_array($latestFollowup->service_ids)
                    ? $latestFollowup->service_ids
                    : json_decode($latestFollowup->service_ids, true);
                if ($serviceIds) {
                    $serviceNames = Service::whereIn('id', $serviceIds)->pluck('service')->toArray();
                }
            }

            // Get extra service names from latest followup
            if ($latestFollowup && $latestFollowup->extra_service_ids) {
                $extraServiceIds = is_array($latestFollowup->extra_service_ids)
                    ? $latestFollowup->extra_service_ids
                    : json_decode($latestFollowup->extra_service_ids, true);
                if ($extraServiceIds) {
                    $extraServiceNames = ExtraService::whereIn('id', $extraServiceIds)->pluck('extra_service')->toArray();
                }
            }

            // Calculate total amount
            $totalAmount = 0;
            if ($latestFollowup && $latestFollowup->total_amount && $latestFollowup->total_amount > 0) {
                $totalAmount = (float) $latestFollowup->total_amount;
            } else {
                $previousFollowupWithAmount = $lead->leadFollowups->filter(function ($f) {
                    return $f->total_amount && $f->total_amount > 0;
                })->first();
                if ($previousFollowupWithAmount) {
                    $totalAmount = (float) $previousFollowupWithAmount->total_amount;
                } elseif ($lead && isset($lead->total_amount) && $lead->total_amount > 0) {
                    $totalAmount = (float) $lead->total_amount;
                }
            }

            // Get total received amount from approved payment audit trail
            $totalReceivedAmount = 0;
            $followupIds = $lead->leadFollowups->pluck('id')->toArray();
            if (!empty($followupIds)) {
                $totalReceivedAmount = PaymentAuditTrail::whereIn('lead_followup_id', $followupIds)
                    ->where('payment_status', 1)
                    ->sum('paid_amount');
            }

            $balanceAmount = $totalAmount - $totalReceivedAmount;

            // Get payment method from audit trail
            $paymentMethod = 'Cash';
            if ($latestFollowup && $latestFollowup->paymentAuditTrail->isNotEmpty()) {
                $latestPayment = $latestFollowup->paymentAuditTrail->first();
                $paymentMethod = $latestPayment->payment_method ?? 'Cash';
            }

            // Get all rides for this lead
            $allRides = $rideGroup->sortBy('from_date')->values();

            $currentStatus = $latestFollowup ? $latestFollowup->status : 6;

            // Get invoice ID from first voucher
            $invoiceId = 'N/A';
            if ($lead->vouchers && $lead->vouchers->first()) {
                $firstVoucher = $lead->vouchers->first();
                if ($firstVoucher->invoice) {
                    $invoiceId = $firstVoucher->invoice->invoice_id ?? 'N/A';
                }
            }

            // Get vendor name from first vendor payment
            $vendorName = 'N/A';
            if ($lead->leadVendorPayments && $lead->leadVendorPayments->first()) {
                $firstVendorPayment = $lead->leadVendorPayments->first();
                if ($firstVendorPayment->vendor) {
                    $vendorName = $firstVendorPayment->vendor->name ?? 'N/A';
                }
            }

            // Get assigned rep name
            $assignedRep = 'N/A';
            if ($lead->representative) {
                $assignedRep = $lead->representative->name ?? 'N/A';
            }

            // Get created date
            $createdDate = $lead->created_at ? $lead->created_at->format('d-m-Y') : 'N/A';

            $processedData[] = (object) [
                'id' => $mainRide->id,
                'lead_id' => $leadId,
                'invoice_id' => $invoiceId,
                'vendor_name' => $vendorName,
                'assigned_rep' => $assignedRep,
                'created_date' => $createdDate,
                'client_name' => $client ? $client->name : 'N/A',
                'contact_number' => $client ? $client->contact_number : 'N/A',
                'service_date' => $allRides->first()->from_date ? $allRides->first()->from_date->format('d-m-Y') : 'N/A',
                'service_names' => implode(', ', $serviceNames),
                'extra_service_names' => implode(', ', $extraServiceNames),
                'total_amount' => $totalAmount,
                'received_amount' => $totalReceivedAmount,
                'balance_amount' => $balanceAmount,
                'payment_method' => $paymentMethod,
                'status' => $currentStatus,
                'status_text' => $this->getStatusText($currentStatus),
            ];
        }

        // Apply status filter POST-grouping (same as controller)
        // This ensures we only export leads whose LATEST followup matches the filter
        if ($statusFilter !== null && $statusFilter !== '') {
            $statusArray = is_array($statusFilter) ? array_map('intval', $statusFilter) : [intval($statusFilter)];
            $processedData = array_values(array_filter($processedData, function ($r) use ($statusArray) {
                $s = isset($r->status) ? intval($r->status) : null;
                return $s !== null && in_array($s, $statusArray, true);
            }));
        }

        $this->exportData = $processedData;
    }

    private function getStatusText($status)
    {
        $statusMap = [
            1 => 'Active',
            2 => 'Cancelled',
            3 => 'Full Payment Received',
            4 => 'Partial Payment Received',
            5 => 'Completed',
            7 => 'Reschedule',
            8 => 'Approved',
        ];
        return $statusMap[$status] ?? 'Unknown';
    }

    public function collection()
    {
        return collect($this->exportData);
    }

    public function headings(): array
    {
        return [
            'S.No',
            'Client Name',
            'Phone',
            'Service Date',
            'Services',
            'Invoice ID',
            'Vendor Name',
            'Assigned Rep',
            'Created Date',
            'Paid/Total',
            'Status',
        ];
    }

    public function map($row): array
    {
        static $count = 0;
        $count++;

        return [
            $count,
            $row->client_name,
            $row->contact_number,
            $row->service_date,
            $row->service_names,
            $row->invoice_id,
            $row->vendor_name,
            $row->assigned_rep,
            $row->created_date,
            '₹' . number_format($row->received_amount, 2) . '/₹' . number_format($row->total_amount, 2),
            $row->status_text,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4472C4']],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getColumnDimension('A')->setWidth(8);
                $event->sheet->getColumnDimension('B')->setWidth(15);
                $event->sheet->getColumnDimension('C')->setWidth(20);
                $event->sheet->getColumnDimension('D')->setWidth(18);
                $event->sheet->getColumnDimension('E')->setWidth(15);
                $event->sheet->getColumnDimension('F')->setWidth(20);
                $event->sheet->getColumnDimension('G')->setWidth(18);
                $event->sheet->getColumnDimension('H')->setWidth(15);
                $event->sheet->getColumnDimension('I')->setWidth(25);
                $event->sheet->getColumnDimension('J')->setWidth(20);
                $event->sheet->getColumnDimension('K')->setWidth(18);
            },
        ];
    }
}
