<?php

namespace App\Exports;

use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Models\ExtraService;
use App\Models\Product;
use App\Models\User;
use function App\Helpers\extractPhoneWithoutCountryCode;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SalesReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        // Status array
        $statusArray = [
            2 => 'Cancelled',
            5 => 'Confirmed',
            7 => 'Rescheduled',
            8 => 'Approved'
        ];

        // Build query with same filtering logic as controller
        $query = LeadFollowup::with([
            'enquiry.client.country',
            'enquiry.client.city',
            'enquiry.rideSegments',
            'enquiry.representative.userType',
            'followedBy.userType'
        ])->whereIn('status', [2, 5, 7, 8]); // 2=Cancelled, 5=Confirmed, 7=Rescheduled, 8=Approved

        // Apply month/year filter based on PAID DATE (from PaymentAuditTrail)
        // Only show leads that received approved payment in the selected month/year
        if (!empty($this->filters['month']) || !empty($this->filters['year'])) {
            $paidDateQuery = \App\Models\PaymentAuditTrail::where('payment_status', 1);
            if (!empty($this->filters['month'])) {
                $paidDateQuery->whereMonth('paid_date', $this->filters['month']);
            }
            if (!empty($this->filters['year'])) {
                $paidDateQuery->whereYear('paid_date', $this->filters['year']);
            }
            $paidFollowupIds = $paidDateQuery->pluck('lead_followup_id')->unique();
            $paidLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)->pluck('lead_id')->unique();
            $query->whereIn('lead_id', $paidLeadIds);
        }

        // Sales Manager Filter
        if (!empty($this->filters['manager_user_id'])) {
            $managerUserId = $this->filters['manager_user_id'];
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
            $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
            $assignedExecutiveIds[] = $managerUserId;

            $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                $q->whereIn('representative_user_id', $assignedExecutiveIds);
            });
        }

        // Apply filters
        if (!empty($this->filters['from_date'])) {
            $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
            $query->whereHas('enquiry.rideSegments', function ($q) use ($fromDate) {
                $q->where('from_date', '>=', $fromDate);
            });
        }

        if (!empty($this->filters['to_date'])) {
            $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
            $query->whereHas('enquiry.rideSegments', function ($q) use ($toDate) {
                $q->where('to_date', '<=', $toDate);
            });
        }

        if (!empty($this->filters['from_create_date'])) {
            $fromDate = Carbon::parse($this->filters['from_create_date'])->startOfDay();
            $query->where('created_at', '>=', $fromDate);
        }

        if (!empty($this->filters['to_create_date'])) {
            $toDate = Carbon::parse($this->filters['to_create_date'])->endOfDay();
            $query->where('created_at', '<=', $toDate);
        }

        if (!empty($this->filters['service_name'])) {
            $serviceName = $this->filters['service_name'];
            $query->where(function ($q) use ($serviceName) {
                $q->where('service_ids', 'like', '%' . $serviceName . '%')
                    ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
            });
        }

        if (isset($this->filters['status']) && $this->filters['status'] != '') {
            $query->where('status', intval($this->filters['status']));
        }

        if (!empty($this->filters['name'])) {
            $query->whereHas('enquiry.client', function ($q) {
                $q->where('name', 'like', '%' . $this->filters['name'] . '%');
            });
        }

        if (!empty($this->filters['email'])) {
            $query->whereHas('enquiry.client', function ($q) {
                $q->where('email', 'like', '%' . $this->filters['email'] . '%');
            });
        }

        if (!empty($this->filters['phone'])) {
            $query->whereHas('enquiry.client', function ($q) {
                $q->where('contact_number', 'like', '%' . $this->filters['phone'] . '%');
            });
        }

        if (!empty($this->filters['representative_user_id'])) {
            $representativeUserId = $this->filters['representative_user_id'];
            $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
                $q->where('representative_user_id', $representativeUserId);
            });
        }

        if (!empty($this->filters['product_id'])) {
            $query->whereHas('enquiry', function ($q) {
                $q->where('product_ids', 'like', '%' . $this->filters['product_id'] . '%');
            });
        }

        $allSalesData = $query->orderBy('created_at', 'desc')->get();

        // Get all services, extra services, and products for reference
        $allServices = Service::all()->keyBy('id');
        $allExtraServices = ExtraService::all()->keyBy('id');
        $allProducts = Product::all()->keyBy('id');

        // Capture filter month/year for use inside closure
        $filterMonth = !empty($this->filters['month']) ? $this->filters['month'] : null;
        $filterYear = !empty($this->filters['year']) ? $this->filters['year'] : null;

        // Process sales data
        $salesData = $allSalesData->groupBy('lead_id')->map(function ($group) use ($allServices, $allExtraServices, $allProducts, $statusArray, $filterMonth, $filterYear) {
            $latest = $group->sortByDesc('created_at')->first();
            $client = $latest->enquiry->client ?? null;
            $ride = $latest->enquiry->rideSegments->first() ?? null;
            $totalAmount = (float) $latest->total_amount;

            // Get ALL followup IDs for this lead (not just current group)
            // This is important because payment might be in an earlier followup
            $allFollowupIdsForLead = LeadFollowup::where('lead_id', $latest->lead_id)
                ->pluck('id');

            // Get approved payments from ALL followups of this lead
            $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->where('payment_status', 1)
                ->get();

            $totalReceived = $approvedPayments->sum('paid_amount');
            $pendingAmount = $totalAmount - $totalReceived;

            // Get processed/completed refund amounts for this lead
            $refundAmount = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->whereIn('status', [1, 2]) // 1=processed, 2=completed
                ->sum('refund_amount');

            // Sales Amount = Total Amount - Refund Amount
            $salesAmount = max(0, $totalAmount - $refundAmount);

            // Get approved payments scoped to the filtered month/year
            // so paid_date shown matches the selected filter period
            $filteredPayments = $approvedPayments;
            if ($filterMonth || $filterYear) {
                $filteredPayments = $approvedPayments->filter(function ($p) use ($filterMonth, $filterYear) {
                    if (empty($p->paid_date)) return false;
                    try {
                        $pd = Carbon::parse($p->paid_date);
                        if ($filterMonth && $pd->month != $filterMonth) return false;
                        if ($filterYear && $pd->year != $filterYear) return false;
                        return true;
                    } catch (\Exception $e) {
                        return false;
                    }
                });
            }

            $lastPayment = $filteredPayments->sortByDesc('paid_date')->first();
            $paidDate = 'N/A';

            if ($lastPayment) {
                if (!empty($lastPayment->paid_date)) {
                    try {
                        $paidDate = Carbon::parse($lastPayment->paid_date)->format('d-m-Y');
                    } catch (\Exception $e) {
                        $paidDate = $lastPayment->created_at ? $lastPayment->created_at->format('d-m-Y') : 'N/A';
                    }
                } else {
                    $paidDate = $lastPayment->created_at ? $lastPayment->created_at->format('d-m-Y') : 'N/A';
                }
            }

            // Get sales person (representative)
            $salesPerson = $latest->enquiry->representative ?? null;
            $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

            // Get manager name from SalesExecutiveAssignment table
            $managerName = 'N/A';
            if ($salesPerson) {
                $assignment = \App\Models\SalesExecutiveAssignment::where('sales_executive_id', $salesPerson->id)
                    ->where('status', 1)
                    ->with('manager')
                    ->first();
                if ($assignment && $assignment->manager) {
                    $managerName = $assignment->manager->name;
                }
            }

            // Get product names
            $productNames = [];
            if (!empty($latest->enquiry->product_ids)) {
                $productIds = is_array($latest->enquiry->product_ids)
                    ? $latest->enquiry->product_ids
                    : json_decode($latest->enquiry->product_ids, true);
                if (is_array($productIds)) {
                    foreach ($productIds as $pid) {
                        if (isset($allProducts[$pid])) {
                            $productNames[] = $allProducts[$pid]->product;
                        }
                    }
                }
            }

            // Get service names
            $serviceNames = [];
            if (!empty($latest->service_ids)) {
                $serviceIds = is_array($latest->service_ids)
                    ? $latest->service_ids
                    : json_decode($latest->service_ids, true);
                if (is_array($serviceIds)) {
                    foreach ($serviceIds as $sid) {
                        if (isset($allServices[$sid])) {
                            $serviceNames[] = $allServices[$sid]->service;
                        }
                    }
                }
            }

            // Get extra service names
            $extraServiceNames = [];
            if (!empty($latest->extra_service_ids)) {
                $extraServiceIds = is_array($latest->extra_service_ids)
                    ? $latest->extra_service_ids
                    : json_decode($latest->extra_service_ids, true);
                if (is_array($extraServiceIds)) {
                    foreach ($extraServiceIds as $esid) {
                        if (isset($allExtraServices[$esid])) {
                            $extraServiceNames[] = $allExtraServices[$esid]->extra_service;
                        }
                    }
                }
            }

            return (object) [
                'client_name' => $client->name ?? 'N/A',
                'email' => $client->email ?? 'N/A',
                'contact' => extractPhoneWithoutCountryCode($client->contact_number ?? ''),
                'received_amount' => $totalReceived,
                'pending_amount' => $pendingAmount,
                'total_amount' => $totalAmount,
                'refund_amount' => $refundAmount,
                'sales_amount' => $salesAmount,
                'sales_person_name' => $salesPersonName,
                'product_name' => implode(', ', $productNames) ?: 'N/A',
                'service' => implode(', ', $serviceNames) ?: 'N/A',
                'extra_service' => implode(', ', $extraServiceNames) ?: 'N/A',
                'paid_date' => $paidDate,
                'ride_status' => $statusArray[$latest->status] ?? 'Unknown',
                'booking_date' => $latest->created_at ? $latest->created_at->format('Y-m-d') : 'N/A',
                'service_date' => $ride && $ride->from_date ? $ride->from_date : 'N/A',
                'manager_name' => $managerName,
            ];
        })->filter(function ($row) {
            return isset($row->paid_date) && $row->paid_date !== 'N/A';
        })->values();

        return $salesData;
    }

    // Note: use global helper extractPhoneWithoutCountryCode() from App\Helpers\helper.php

    public function headings(): array
    {
        return [
            'Client Name',
            'Email',
            'Contact',
            'Received Amount',
            'Pending Amount',
            'Total Amount',
            'Refund Amount',
            'Sales Amount',
            'Sales Person Name',
            'Product Name',
            'Service',
            'Extra Service',
            'Paid Date',
            'Ride Status',
            'Booking Date',
            'Service Date',
            'Manager Name',
        ];
    }

    public function map($row): array
    {
        return [
            $row->client_name,
            $row->email,
            $row->contact,
            $row->received_amount,
            $row->pending_amount,
            $row->total_amount,
            $row->refund_amount,
            $row->sales_amount,
            $row->sales_person_name,
            $row->product_name,
            $row->service,
            $row->extra_service,
            $row->paid_date,
            $row->ride_status,
            $row->booking_date,
            $row->service_date,
            $row->manager_name,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4CAF50']
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Set specific column widths for readability
                $sheet = $event->sheet->getDelegate();
                $sheet->getColumnDimension('A')->setWidth(30); // Client Name
                $sheet->getColumnDimension('B')->setWidth(30); // Email
                $sheet->getColumnDimension('C')->setWidth(16); // Contact
                $sheet->getColumnDimension('D')->setWidth(16); // Received Amount
                $sheet->getColumnDimension('E')->setWidth(16); // Pending Amount
                $sheet->getColumnDimension('F')->setWidth(16); // Total Amount
                $sheet->getColumnDimension('G')->setWidth(16); // Refund Amount
                $sheet->getColumnDimension('H')->setWidth(16); // Sales Amount
                $sheet->getColumnDimension('I')->setWidth(20); // Sales Person
                $sheet->getColumnDimension('J')->setWidth(28); // Product Name
                $sheet->getColumnDimension('K')->setWidth(24); // Service
                $sheet->getColumnDimension('L')->setWidth(24); // Extra Service
                $sheet->getColumnDimension('M')->setWidth(14); // Paid Date
                $sheet->getColumnDimension('N')->setWidth(16); // Ride Status
                $sheet->getColumnDimension('O')->setWidth(14); // Booking Date
                $sheet->getColumnDimension('P')->setWidth(14); // Service Date
                $sheet->getColumnDimension('Q')->setWidth(20); // Manager Name

                // Add borders to all cells and format numbers/dates
                $lastRow = $sheet->getHighestRow();
                $sheet->getStyle('A1:Q' . $lastRow)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);

                // Format numeric columns (D: Received, E: Pending, F: Total, G: Refund, H: Sales)
                $sheet->getStyle('D2:H' . $lastRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');

                // Ensure contact column is treated as text to avoid Excel formatting
                foreach ($sheet->getRowIterator(2, $lastRow) as $row) {
                    $cell = $sheet->getCell('C' . $row->getRowIndex());
                    $cell->setValueExplicit((string) $cell->getValue(), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            },
        ];
    }
}
