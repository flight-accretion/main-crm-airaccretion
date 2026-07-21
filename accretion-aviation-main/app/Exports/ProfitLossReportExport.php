<?php

namespace App\Exports;

use App\Models\LeadFollowup;
use App\Models\LeadVendorPayment;
use App\Models\PaymentAuditTrail;
use App\Models\Service;
use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ProfitLossReportExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    // public function collection()
    // {
    //     // Build query with same filtering logic as controller
    //     $query = LeadFollowup::with([
    //         'enquiry.client.country',
    //         'enquiry.client.city',
    //         'enquiry.rideSegments',
    //         'enquiry.representative.userType',
    //         'enquiry.leadVendorPayments.vendor',
    //         'enquiry.leadVendorPayments.vendorPayments',
    //         'followedBy.userType'
    //     ])->whereIn('status', [3, 4, 5, 6, 7, 8]);

    //     // Apply filters
    //     if (!empty($this->filters['from_date'])) {
    //         $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
    //         $query->whereHas('enquiry.rideSegments', function ($q) use ($fromDate) {
    //             $q->where('from_date', '>=', $fromDate);
    //         });
    //     }

    //     if (!empty($this->filters['to_date'])) {
    //         $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
    //         $query->whereHas('enquiry.rideSegments', function ($q) use ($toDate) {
    //             $q->where('to_date', '<=', $toDate);
    //         });
    //     }

    //     if (!empty($this->filters['from_create_date'])) {
    //         $fromDate = Carbon::parse($this->filters['from_create_date'])->startOfDay();
    //         $query->where('created_at', '>=', $fromDate);
    //     }

    //     if (!empty($this->filters['to_create_date'])) {
    //         $toDate = Carbon::parse($this->filters['to_create_date'])->endOfDay();
    //         $query->where('created_at', '<=', $toDate);
    //     }

    //     if (!empty($this->filters['service_name'])) {
    //         $serviceName = $this->filters['service_name'];
    //         $query->where(function ($q) use ($serviceName) {
    //             $q->where('service_ids', 'like', '%' . $serviceName . '%')
    //                 ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
    //         });
    //     }

    //     if (isset($this->filters['status']) && $this->filters['status'] != '') {
    //         $query->where('status', intval($this->filters['status']));
    //     }

    //     if (!empty($this->filters['name'])) {
    //         $query->whereHas('enquiry.client', function ($q) {
    //             $q->where('name', 'like', '%' . $this->filters['name'] . '%');
    //         });
    //     }

    //     if (!empty($this->filters['email'])) {
    //         $query->whereHas('enquiry.client', function ($q) {
    //             $q->where('email', 'like', '%' . $this->filters['email'] . '%');
    //         });
    //     }

    //     if (!empty($this->filters['phone'])) {
    //         $query->whereHas('enquiry.client', function ($q) {
    //             $q->where('contact_number', 'like', '%' . $this->filters['phone'] . '%');
    //         });
    //     }

    //     if (!empty($this->filters['representative_user_id'])) {
    //         $representativeUserId = $this->filters['representative_user_id'];
    //         $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
    //             $q->where('representative_user_id', $representativeUserId);
    //         });
    //     }

    //     if (!empty($this->filters['manager_user_id'])) {
    //         $managerUserId = $this->filters['manager_user_id'];
    //         $managerUser = \App\Models\User::find($managerUserId);
    //         if ($managerUser && $managerUser->user_type_id) {
    //             $managerUserTypeId = $managerUser->user_type_id;
    //             $query->whereHas('enquiry.representative.userType', function ($q) use ($managerUserTypeId) {
    //                 $q->where('parent_id', $managerUserTypeId);
    //             });
    //         }
    //     }

    //     if (!empty($this->filters['product_id'])) {
    //         $query->whereHas('enquiry', function ($q) {
    //             $q->where('product_ids', 'like', '%' . $this->filters['product_id'] . '%');
    //         });
    //     }

    //     $allData = $query->orderBy('created_at', 'desc')->get();

    //     // Process profit/loss data
    //     $profitLossData = $allData->groupBy('lead_id')->map(function ($group) {
    //         $latest = $group->sortByDesc('created_at')->first();
    //         $client = $latest->enquiry->client ?? null;
    //         $totalAmount = (float) $latest->total_amount;

    //         // Get approved payments (client received amount)
    //         $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $group->pluck('id'))
    //             ->where('payment_status', 1)
    //             ->get();
    //         $clientReceivedAmount = $approvedPayments->sum('paid_amount');

    //         // Get vendor payments for this lead
    //         $vendorPayments = $latest->enquiry->leadVendorPayments ?? collect();
    //         $totalVendorAmount = 0;
    //         $vendorNames = [];

    //         foreach ($vendorPayments as $vp) {
    //             $vendorAmount = $vp->total_vendor_service_amount ?? 0;
    //             $totalVendorAmount += $vendorAmount;
    //             if ($vp->vendor) {
    //                 $vendorNames[] = $vp->vendor->name;
    //             }
    //         }

    //         // Calculate profit/loss
    //         $profitLoss = $clientReceivedAmount - $totalVendorAmount;
    //         $profitLossPercent = 0;
    //         if ($clientReceivedAmount > 0) {
    //             $profitLossPercent = ($profitLoss / $clientReceivedAmount) * 100;
    //         }

    //         // Get sales person (representative)
    //         $salesPerson = $latest->enquiry->representative ?? null;
    //         $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

    //         // Get manager name (from sales person's user type parent)
    //         $managerName = 'N/A';
    //         if ($salesPerson && $salesPerson->userType && $salesPerson->userType->parent_id) {
    //             $managerUserType = $salesPerson->userType->parent;
    //             if ($managerUserType) {
    //                 $manager = User::where('user_type_id', $managerUserType->id)->first();
    //                 $managerName = $manager ? $manager->name : 'N/A';
    //             }
    //         }

    //         return (object) [
    //             'followup_id' => $latest->id,
    //             'lead_id' => $latest->lead_id,
    //             'customer_name' => $client->name ?? 'N/A',
    //             'client_received_amount' => $clientReceivedAmount,
    //             'vendor_name' => !empty($vendorNames) ? implode(', ', $vendorNames) : 'N/A',
    //             'vendor_amount' => $totalVendorAmount,
    //             'profit_loss' => $profitLoss,
    //             'profit_loss_percent' => $profitLossPercent,
    //             'sales_person_name' => $salesPersonName,
    //             'manager_name' => $managerName,
    //             'created_at' => $latest->created_at,
    //         ];
    //     })->values();

    //     return $profitLossData;
    // }
 public function collection()
{
    $query = LeadFollowup::with([
        'enquiry.client.country',
        'enquiry.client.city',
        'enquiry.rideSegments',
        'enquiry.representative.userType',
        'enquiry.leadVendorPayments.vendor',
        'enquiry.leadVendorPayments.vendorPayments',
        'followedBy.userType'
    ])->whereIn('status', [3, 4, 5, 6, 7, 8]);

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
        $name = $this->filters['name'];
        $query->whereHas('enquiry.client', function ($q) use ($name) {
            $q->where('name', 'like', '%' . $name . '%');
        });
    }
    if (!empty($this->filters['email'])) {
        $email = $this->filters['email'];
        $query->whereHas('enquiry.client', function ($q) use ($email) {
            $q->where('email', 'like', '%' . $email . '%');
        });
    }
    if (!empty($this->filters['phone'])) {
        $phone = $this->filters['phone'];
        $query->whereHas('enquiry.client', function ($q) use ($phone) {
            $q->where('contact_number', 'like', '%' . $phone . '%');
        });
    }
    if (!empty($this->filters['representative_user_id'])) {
        $representativeUserId = $this->filters['representative_user_id'];
        $query->whereHas('enquiry', function ($q) use ($representativeUserId) {
            $q->where('representative_user_id', $representativeUserId);
        });
    }
    if (!empty($this->filters['manager_user_id'])) {
        $managerUserId = $this->filters['manager_user_id'];
        $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
        $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
        $assignedExecutiveIds[] = $managerUserId;
        $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
            $q->whereIn('representative_user_id', $assignedExecutiveIds);
        });
    }
    if (!empty($this->filters['product_id'])) {
        $productId = $this->filters['product_id'];
        $query->whereHas('enquiry', function ($q) use ($productId) {
            $q->where('product_ids', 'like', '%' . $productId . '%');
        });
    }

    $allData = $query->orderBy('created_at', 'desc')->get();

    // Build approved lead IDs (same fix as controller)
    $allLeadIds = $allData->pluck('lead_id')->unique();
    $allFollowupsForLeads = LeadFollowup::whereIn('lead_id', $allLeadIds)
        ->select('id', 'lead_id')
        ->get();
    $followupToLeadMap = $allFollowupsForLeads->pluck('lead_id', 'id');
    $allFollowupIds = $allFollowupsForLeads->pluck('id');

    $approvedLeadIds = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIds)
        ->where('payment_status', 1)
        ->get()
        ->map(fn($pat) => $followupToLeadMap->get($pat->lead_followup_id))
        ->filter()
        ->unique()
        ->values();

    $profitLossData = $allData->groupBy('lead_id')->map(function ($group) use ($approvedLeadIds) {
        $latest = $group->sortByDesc('created_at')->first();

        // Only include leads with at least one approved payment
        if (!$approvedLeadIds->contains($latest->lead_id)) {
            return null;
        }

        // Skip if latest status is not qualifying
        if (!in_array($latest->status, [3, 4, 5, 6, 7, 8])) {
            return null;
        }

        $client = $latest->enquiry->client ?? null;
        $totalAmount = (float) $latest->total_amount;

        // Get ALL followup IDs for this lead (not just the filtered group)
        $allFollowupIdsForLead = LeadFollowup::where('lead_id', $latest->lead_id)->pluck('id');

        $approvedPayments = PaymentAuditTrail::whereIn('lead_followup_id', $allFollowupIdsForLead)
            ->where('payment_status', 1)
            ->get();
        $clientReceivedAmount = $approvedPayments->sum('paid_amount');

        $vendorPayments = $latest->enquiry->leadVendorPayments ?? collect();
        $totalVendorAmount = 0;
        $vendorNames = [];
        foreach ($vendorPayments as $vp) {
            $totalVendorAmount += $vp->total_vendor_service_amount ?? 0;
            if ($vp->vendor) $vendorNames[] = $vp->vendor->name;
        }

        $profitLoss = $clientReceivedAmount - $totalVendorAmount;
        $profitLossPercent = $clientReceivedAmount > 0
            ? ($profitLoss / $clientReceivedAmount) * 100
            : 0;

        $salesPerson = $latest->enquiry->representative ?? null;
        $salesPersonName = $salesPerson ? $salesPerson->name : 'N/A';

        // Get manager from SalesExecutiveAssignment (same as controller)
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

        return (object) [
            'customer_name'          => $client->name ?? 'N/A',
            'client_received_amount' => $clientReceivedAmount,
            'vendor_name'            => !empty($vendorNames) ? implode(', ', $vendorNames) : 'N/A',
            'vendor_amount'          => $totalVendorAmount,
            'profit_loss'            => $profitLoss,
            'profit_loss_percent'    => $profitLossPercent,
            'sales_person_name'      => $salesPersonName,
            'manager_name'           => $managerName,
        ];
    })->filter()->values();

    return $profitLossData;
}
    public function headings(): array
    {
        return [
            'Customer Name',
            'Client Received Amount',
            'Vendor Name',
            'Vendor Amount',
            'Profit/Loss',
            'Profit/Loss %',
            'Sales Person',
            'Manager',
        ];
    }

    public function map($row): array
    {
        return [
            $row->customer_name,
            number_format($row->client_received_amount, 2),
            $row->vendor_name,
            number_format($row->vendor_amount, 2),
            number_format($row->profit_loss, 2),
            number_format($row->profit_loss_percent, 2) . '%',
            $row->sales_person_name,
            $row->manager_name,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row as bold text.
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                
                // Auto-size columns
                foreach(range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                
                // Add borders to all cells
                $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
                
                $cellRange = 'A1:H' . $sheet->getHighestRow();
                $sheet->getStyle($cellRange)->applyFromArray($styleArray);
                
                // Style header row
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4']
                    ],
                    'alignment' => [
                        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    ],
                ]);
            },
        ];
    }
}
