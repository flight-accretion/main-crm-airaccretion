<?php

namespace App\Exports;

use App\Models\LeadFollowup;
use App\Models\PaymentAuditTrail;
use App\Models\User;
use App\Models\Service;
use App\Models\Product;
use App\Models\Target;
use App\Models\SalesExecutiveAssignment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

trait KpiReportTargetHelper
{
    // protected function resolveTargetPeriod(array $filters): array
    // {
    //     $date = null;
    //     foreach (['from_date', 'to_date', 'from_create_date', 'to_create_date'] as $key) {
    //         if (!empty($filters[$key])) {
    //             try {
    //                 $date = Carbon::parse($filters[$key]);
    //                 break;
    //             } catch (\Throwable $e) {
    //                 continue;
    //             }
    //         }
    //     }

    //     $date = $date ?: Carbon::now();

    //     return [
    //         'year' => (int) $date->format('Y'),
    //         'month' => (int) $date->format('n'),
    //     ];
    // }

    protected function resolveTargetPeriod(array $filters): array
    {
        $date = null;

        // If month filter provided (format: Y-m), use it directly
        if (!empty($filters['month'])) {
            try {
                $date = Carbon::createFromFormat('Y-m', $filters['month']);
                return [
                    'year'  => (int) $date->format('Y'),
                    'month' => (int) $date->format('n'),
                ];
            } catch (\Throwable $e) {
            }
        }

        foreach (['from_date', 'to_date', 'from_create_date', 'to_create_date'] as $key) {
            if (!empty($filters[$key])) {
                try {
                    $date = Carbon::parse($filters[$key]);
                    break;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        $date = $date ?: Carbon::now();

        return [
            'year'  => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
        ];
    }


    protected function calculateTargetStatsForRep($repId, array $period): array
    {
        if (!$repId) {
            return [
                'target_amount' => 0.0,
                'achieved_amount' => 0.0,
                'remaining_amount' => 0.0,
            ];
        }

        // treat rep id as raw value (UUID) to match user primary key type
        $targetAmount = Target::where('sales_executive_id', $repId)
            ->where('year', $period['year'])
            ->where('month', $period['month'])
            ->where('status', 'active')
            ->sum('target_amount');

        // $achievedAmount = LeadFollowup::whereHas('enquiry', function ($q) use ($repId) {
        //     $q->where('representative_user_id', $repId);
        //     })
        //     ->whereYear('created_at', $period['year'])
        //     ->whereMonth('created_at', $period['month'])
        //     ->whereHas('paymentAuditTrail', function ($q) {
        //         $q->where('payment_status', 1);
        //     })
        //     ->sum('total_amount');

        // Step 1: paid followup IDs in this period
        $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $period['year'])
            ->whereMonth('paid_date', $period['month'])
            ->pluck('lead_followup_id')->unique();

        // Step 2: lead IDs scoped to this rep
        $paidLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->pluck('lead_id')->unique();

        if ($paidLeadIds->isEmpty()) {
            return [
                'target_amount'    => (float) $targetAmount,
                'achieved_amount'  => 0.0,
                'remaining_amount' => (float) $targetAmount,
            ];
        }

        // Step 3: ALL followups for those leads
        $allFollowups = LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->get();

        // Step 4: batch-load ALL followup IDs per lead (avoid N+1)
        $allFollowupIdsByLead = $allFollowups->groupBy('lead_id')
            ->map(fn($group) => $group->pluck('id'));

        $allFollowupIdsFlat = $allFollowupIdsByLead->flatten();

        // Step 5: batch-load all refunds in one query
        $refundsByFollowupId = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsFlat)
            ->whereIn('status', [1, 2])
            ->get()
            ->groupBy('lead_followup_id');

        // Step 6: per-lead — latest qualifying followup [2,5,7,8], subtract refunds
        $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) use ($allFollowupIdsByLead, $refundsByFollowupId) {
            $qualifying = $group->filter(fn($f) => in_array($f->status, [2, 5, 7, 8]));
            $latest = $qualifying->sortByDesc('created_at')->first();

            if (!$latest) return 0;

            // Sum refunds for all followups of this lead using pre-loaded data
            $leadFollowupIds = $allFollowupIdsByLead->get($latest->lead_id, collect());
            $refund = $leadFollowupIds->sum(function ($fid) use ($refundsByFollowupId) {
                return $refundsByFollowupId->get($fid, collect())->sum('refund_amount');
            });

            return max(0, (float) $latest->total_amount - $refund);
        })->sum();

        $remainingAmount = max(0, (float) $targetAmount - (float) $achievedAmount);

        return [
            'target_amount' => (float) $targetAmount,
            'achieved_amount' => (float) $achievedAmount,
            'remaining_amount' => (float) $remainingAmount,
        ];
    }
}

class KPIReportExport implements WithMultipleSheets
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $sheets = [];

        // Summary sheet
        $sheets[] = new KPIReportSummarySheet($this->filters);

        try {
            // Get all sales persons
            $query = LeadFollowup::with(['enquiry.representative.userType', 'enquiry.rideSegments'])
                ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

            // Apply filters — use lead's created_at for date filtering (matches Leads page)
            if (!empty($this->filters['month'])) {
                try {
                    $month = Carbon::createFromFormat('Y-m', $this->filters['month']);
                    $start = $month->copy()->startOfMonth()->startOfDay();
                    $end   = $month->copy()->endOfMonth()->endOfDay();
                    $query->whereHas('enquiry', function ($q) use ($start, $end) {
                        $q->where('created_at', '>=', $start)
                            ->where('created_at', '<=', $end);
                    });
                } catch (\Throwable $e) {
                }
            } else {
                if (!empty($this->filters['from_date'])) {
                    $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
                    $query->whereHas('enquiry', function ($q) use ($fromDate) {
                        $q->where('created_at', '>=', $fromDate);
                    });
                }
                if (!empty($this->filters['to_date'])) {
                    $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
                    $query->whereHas('enquiry', function ($q) use ($toDate) {
                        $q->where('created_at', '<=', $toDate);
                    });
                }
            }
            if (!empty($this->filters['from_create_date'])) {
                $fromDate = Carbon::parse($this->filters['from_create_date'])->startOfDay();
                $query->whereHas('enquiry', function ($q) use ($fromDate) {
                    $q->where('created_at', '>=', $fromDate);
                });
            }
            if (!empty($this->filters['to_create_date'])) {
                $toDate = Carbon::parse($this->filters['to_create_date'])->endOfDay();
                $query->whereHas('enquiry', function ($q) use ($toDate) {
                    $q->where('created_at', '<=', $toDate);
                });
            }
            if (!empty($this->filters['service_name'])) {
                $serviceName = $this->filters['service_name'];
                $query->where(function ($q) use ($serviceName) {
                    $q->where('service_ids', 'like', '%' . $serviceName . '%')
                        ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
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
                // Get all sales executives assigned to this manager
                $assignedExecutives = SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
                $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
                // Include the manager themselves in the filter
                $assignedExecutiveIds[] = $managerUserId;

                $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                    $q->whereIn('representative_user_id', $assignedExecutiveIds);
                });
            }

            $allData = $query->get();

            // Group by representative
            $groupedByRep = $allData->groupBy(function ($f) {
                return $f->enquiry && $f->enquiry->representative ? $f->enquiry->representative->id : 'no_rep';
            });

            // Create a sheet for each salesperson
            foreach ($groupedByRep as $repId => $group) {
                if ($repId !== 'no_rep') {
                    $rep = $group->first()->enquiry->representative ?? null;
                    if ($rep) {
                        $sheets[] = new KPIReportSalespersonSheet($rep, $group, $this->filters);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::error('KPIReportExport sheets() error: ' . $e->getMessage());
        }

        return $sheets;
    }
}

class KPIReportSummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithTitle
{
    use KpiReportTargetHelper;

    protected $filters;
    protected $targetPeriod;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
        $this->targetPeriod = $this->resolveTargetPeriod($this->filters);
    }

    public function title(): string
    {
        return 'SUMMARY';
    }

    public function collection()
    {
        $query = LeadFollowup::with(['enquiry.representative.userType', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        // Apply filters — use lead's created_at for date filtering (matches Leads page)
        if (!empty($this->filters['month'])) {
            try {
                $month = Carbon::createFromFormat('Y-m', $this->filters['month']);
                $start = $month->copy()->startOfMonth()->startOfDay();
                $end   = $month->copy()->endOfMonth()->endOfDay();
                $query->whereHas('enquiry', function ($q) use ($start, $end) {
                    $q->where('created_at', '>=', $start)
                        ->where('created_at', '<=', $end);
                });
            } catch (\Throwable $e) {
            }
        } else {
            if (!empty($this->filters['from_date'])) {
                $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
                $query->whereHas('enquiry', function ($q) use ($fromDate) {
                    $q->where('created_at', '>=', $fromDate);
                });
            }
            if (!empty($this->filters['to_date'])) {
                $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
                $query->whereHas('enquiry', function ($q) use ($toDate) {
                    $q->where('created_at', '<=', $toDate);
                });
            }
        }
        if (!empty($this->filters['from_create_date'])) {
            $fromDate = Carbon::parse($this->filters['from_create_date'])->startOfDay();
            $query->whereHas('enquiry', function ($q) use ($fromDate) {
                $q->where('created_at', '>=', $fromDate);
            });
        }
        if (!empty($this->filters['to_create_date'])) {
            $toDate = Carbon::parse($this->filters['to_create_date'])->endOfDay();
            $query->whereHas('enquiry', function ($q) use ($toDate) {
                $q->where('created_at', '<=', $toDate);
            });
        }

        if (!empty($this->filters['service_name'])) {
            $serviceName = $this->filters['service_name'];
            $query->where(function ($q) use ($serviceName) {
                $q->where('service_ids', 'like', '%' . $serviceName . '%')
                    ->orWhere('service_ids', 'like', '%"' . $serviceName . '"%');
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
            // Get all sales executives assigned to this manager
            $assignedExecutives = SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
            $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
            // Include the manager themselves in the filter
            $assignedExecutiveIds[] = $managerUserId;

            $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                $q->whereIn('representative_user_id', $assignedExecutiveIds);
            });
        }

        $allData = $query->orderBy('created_at', 'desc')->get();

        // Group by representative user (Sales person). If not present, use N/A.
        $groupedByRep = $allData->groupBy(function ($f) {
            return $f->enquiry && $f->enquiry->representative ? $f->enquiry->representative->id : 'no_rep';
        });

        $rows = $groupedByRep->map(function ($group, $repId) {
            $rep = null;
            if ($repId !== 'no_rep') {
                $rep = $group->first()->enquiry->representative ?? null;
            }
            $repName = $rep ? $rep->name : 'N/A';

            // Each unique lead count
            $totalLeads = $group->groupBy('lead_id')->count();

            // Active count - latest status in [0, 1]
            $activeCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [1]) ? 1 : 0;
            })->sum();

            // Cancelled count - latest status == 2
            $cancelledCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return $latest->status == 2 ? 1 : 0;
            })->sum();

            // Completed count - latest status in [3, 4, 5, 7, 8]
            $distinctCompletedLeadCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [3, 4, 5, 7, 8]) ? 1 : 0;
            })->sum();

            // Conversion rate
            $conversionRate = 0;
            if ($totalLeads > 0) {
                $conversionRate = ($distinctCompletedLeadCount / $totalLeads) * 100;
            }

            $targetStats = $this->calculateTargetStatsForRep($repId === 'no_rep' ? null : $repId, $this->targetPeriod);

            return (object) [
                'employee' => $repName,
                'total_leads' => $totalLeads,
                'active' => $activeCount,
                'cancelled' => $cancelledCount,
                'completed' => $distinctCompletedLeadCount,
                'conversion_rate' => $conversionRate,
                'target_amount' => $targetStats['target_amount'],
                'achieved_amount' => $targetStats['achieved_amount'],
                'remaining_amount' => $targetStats['remaining_amount'],
            ];
        })->values();

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Employee',
            'Total Leads',
            'Active',
            'Cancelled',
            'Completed',
            'Conversion Rate %',
            'Target Amount',
            'Achieved Amount',
            'Remaining Amount'
        ];
    }

    public function map($row): array
    {
        return [
            $row->employee,
            $row->total_leads,
            $row->active,
            $row->cancelled,
            $row->completed,
            number_format($row->conversion_rate, 2) . '%',
            number_format($row->target_amount, 2),
            number_format($row->achieved_amount, 2),
            number_format($row->remaining_amount, 2)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (range('A', 'I') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $cellRange = 'A1:I' . $sheet->getHighestRow();
                $sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4']
                    ],
                ]);
            },
        ];
    }
}

class KPIReportSalespersonSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithTitle
{
    use KpiReportTargetHelper;

    protected $representative;
    protected $followups;
    protected $filters;
    protected $targetPeriod;
    protected $targetStats;

    public function __construct($representative, $followups, $filters = [])
    {
        $this->representative = $representative;
        $this->followups = $followups;
        $this->filters = $filters;
        $this->targetPeriod = $this->resolveTargetPeriod($this->filters);
        $this->targetStats = $this->calculateTargetStatsForRep($this->representative->id ?? null, $this->targetPeriod);
    }

    public function title(): string
    {
        // Sanitize sheet name (max 31 chars, no special characters)
        $name = $this->representative->name;
        $name = preg_replace('/[^A-Za-z0-9 ]/', '', $name);
        return substr($name, 0, 31);
    }

    public function collection()
    {
        // Group by product
        $groupedByProduct = collect();

        foreach ($this->followups as $followup) {
            if (!$followup->enquiry) continue;

            $productIds = $followup->enquiry->product_ids;

            if (empty($productIds)) {
                // No product
                $productName = 'NO REQUIREMENT';
            } else {
                // Decode product IDs
                $productIdsArray = is_string($productIds) ? json_decode($productIds, true) : $productIds;
                if (!is_array($productIdsArray)) {
                    $productIdsArray = [$productIds];
                }

                // Get product names
                $products = Product::whereIn('id', $productIdsArray)->pluck('product')->toArray();
                $productName = !empty($products) ? implode(', ', $products) : 'NO REQUIREMENT';
            }

            if (!$groupedByProduct->has($productName)) {
                $groupedByProduct->put($productName, collect());
            }
            $groupedByProduct->get($productName)->push($followup);
        }

        // Calculate stats for each product
        $rows = $groupedByProduct->map(function ($group, $productName) {
            $totalLeads = $group->groupBy('lead_id')->count();

            $activeCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [1]) ? 1 : 0;
            })->sum();

            $cancelledCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return $latest->status == 2 ? 1 : 0;
            })->sum();

            $completedCount = $group->groupBy('lead_id')->map(function ($leadGroup) {
                $latest = $leadGroup->sortByDesc('created_at')->first();
                return in_array($latest->status, [3, 4, 5, 7, 8]) ? 1 : 0;
            })->sum();

            $conversionRate = 0;
            if ($totalLeads > 0) {
                $conversionRate = ($completedCount / $totalLeads) * 100;
            }

            return (object) [
                'product' => $productName,
                'total_leads' => $totalLeads,
                'active' => $activeCount,
                'cancelled' => $cancelledCount,
                'completed' => $completedCount,
                'conversion_rate' => $conversionRate,
            ];
        })->values();

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Products',
            'Total Leads',
            'Active',
            'Cancelled',
            'Completed',
            'Conversion Rate (in %)'
        ];
    }

    public function map($row): array
    {
        return [
            $row->product,
            $row->total_leads,
            $row->active,
            $row->cancelled,
            $row->completed,
            number_format($row->conversion_rate, 2)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 12]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $cellRange = 'A1:F' . $sheet->getHighestRow();
                $sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4']
                    ],
                ]);
            },
        ];
    }
}
