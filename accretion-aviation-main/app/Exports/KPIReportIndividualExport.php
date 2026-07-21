<?php

namespace App\Exports;

use App\Models\LeadFollowup;
use App\Models\User;
use App\Models\Product;
use App\Models\Target;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class KPIReportIndividualExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents, WithTitle
{
    protected $filters;
    protected $representativeId;
    protected $representative;
    protected $targetPeriod;
    protected $targetStats;

    public function __construct($filters = [], $representativeId = null)
    {
        $this->filters = $filters;
        $this->representativeId = $representativeId;
        $this->representative = User::find($representativeId);
        $this->targetPeriod = $this->resolveTargetPeriod($this->filters);
        $this->targetStats = $this->calculateTargetStatsForRep($this->representativeId, $this->targetPeriod);
    }

    public function title(): string
    {
        $name = $this->representative ? $this->representative->name : 'Unknown';
        $name = preg_replace('/[^A-Za-z0-9 ]/', '', $name);
        return substr($name, 0, 31);
    }

    protected function resolveTargetPeriod(array $filters): array
    {
        $date = null;

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
            'year' => (int) $date->format('Y'),
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

        // Step 1: followup IDs with approved payments whose paid_date is in the period
        $paidFollowupIds = \App\Models\PaymentAuditTrail::where('payment_status', 1)
            ->whereYear('paid_date', $period['year'])
            ->whereMonth('paid_date', $period['month'])
            ->pluck('lead_followup_id')->unique();

        // Step 2: lead IDs for those followups, scoped to this rep
        $paidLeadIds = LeadFollowup::whereIn('id', $paidFollowupIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->pluck('lead_id')->unique();

        // Step 3: ALL followups for those leads (for this rep)
        $allFollowups = LeadFollowup::whereIn('lead_id', $paidLeadIds)
            ->whereHas('enquiry', function ($q) use ($repId) {
                $q->where('representative_user_id', $repId);
            })
            ->get();

        // Step 4: per-lead — latest qualifying followup [2,5,7,8], subtract refunds
        $achievedAmount = $allFollowups->groupBy('lead_id')->map(function ($group) {
            $qualifying = $group->filter(function ($f) {
                return in_array($f->status, [2, 5, 7, 8]);
            });
            return $qualifying->sortByDesc('created_at')->first();
        })->filter()->sum(function ($f) {
            $allFollowupIdsForLead = LeadFollowup::where('lead_id', $f->lead_id)->pluck('id');
            $refund = \App\Models\LeadRefund::whereIn('lead_followup_id', $allFollowupIdsForLead)
                ->whereIn('status', [1, 2])->sum('refund_amount');
            return max(0, (float) $f->total_amount - $refund);
        });

        $remainingAmount = max(0, (float) $targetAmount - (float) $achievedAmount);

        return [
            'target_amount' => (float) $targetAmount,
            'achieved_amount' => (float) $achievedAmount,
            'remaining_amount' => (float) $remainingAmount,
        ];
    }

    public function collection()
    {
        $query = LeadFollowup::with(['enquiry.representative.userType', 'enquiry.rideSegments'])
            ->whereIn('status', [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]);

        // Filter by specific representative
        if ($this->representativeId) {
            $query->whereHas('enquiry', function ($q) {
                $q->where('representative_user_id', $this->representativeId);
            });
        }

        // Apply date filters
        // if (!empty($this->filters['from_date'])) {
        //     $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
        //     $query->whereHas('enquiry.rideSegments', function ($q) use ($fromDate) {
        //         $q->where('from_date', '>=', $fromDate);
        //     });
        // }
        // if (!empty($this->filters['to_date'])) {
        //     $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
        //     $query->whereHas('enquiry.rideSegments', function ($q) use ($toDate) {
        //         $q->where('to_date', '<=', $toDate);
        //     });
        // }

        // Apply month filter — use lead's created_at (matches Leads page)
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
                // fallback to from/to date if month parse fails
            }
        } else {
            // Apply from/to date filters only if month not set
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
        if (!empty($this->filters['manager_user_id'])) {
            $managerUserId = $this->filters['manager_user_id'];
            $assignedExecutives = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($managerUserId);
            $assignedExecutiveIds = $assignedExecutives->pluck('id')->toArray();
            $assignedExecutiveIds[] = $managerUserId;
            $query->whereHas('enquiry', function ($q) use ($assignedExecutiveIds) {
                $q->whereIn('representative_user_id', $assignedExecutiveIds);
            });
        }

        $followups = $query->orderBy('created_at', 'desc')->get();

        // Group by product
        $groupedByProduct = collect();

        foreach ($followups as $followup) {
            if (!$followup->enquiry) continue;

            $productIds = $followup->enquiry->product_ids;

            if (empty($productIds)) {
                $productName = 'NO REQUIREMENT';
            } else {
                $productIdsArray = is_string($productIds) ? json_decode($productIds, true) : $productIds;
                if (!is_array($productIdsArray)) {
                    $productIdsArray = [$productIds];
                }

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
        $repName = $this->representative ? $this->representative->name : 'Unknown';
        $targetStats = $this->targetStats;

        return [
            AfterSheet::class => function (AfterSheet $event) use ($repName, $targetStats) {
                $sheet = $event->sheet->getDelegate();

                // Auto-size columns
                foreach (range('A', 'F') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $highestRow = $sheet->getHighestRow();

                // Add borders to data area
                $cellRange = 'A1:F' . $highestRow;
                $sheet->getStyle($cellRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);

                // Style header row
                $sheet->getStyle('A1:F1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4472C4']
                    ],
                ]);

                // Add summary section at the bottom
                $summaryStartRow = $highestRow + 3;

                $sheet->setCellValue('A' . $summaryStartRow, 'Summary for: ' . $repName);
                $sheet->getStyle('A' . $summaryStartRow)->getFont()->setBold(true)->setSize(12);
                $sheet->mergeCells('A' . $summaryStartRow . ':F' . $summaryStartRow);

                $summaryStartRow++;
                $sheet->setCellValue('A' . $summaryStartRow, 'Target Amount');
                $sheet->setCellValue('B' . $summaryStartRow, number_format($targetStats['target_amount'], 2));

                $summaryStartRow++;
                $sheet->setCellValue('A' . $summaryStartRow, 'Achieved Amount');
                $sheet->setCellValue('B' . $summaryStartRow, number_format($targetStats['achieved_amount'], 2));

                $summaryStartRow++;
                $sheet->setCellValue('A' . $summaryStartRow, 'Remaining Amount');
                $sheet->setCellValue('B' . $summaryStartRow, number_format($targetStats['remaining_amount'], 2));

                // Style summary section
                $summaryRange = 'A' . ($highestRow + 3) . ':B' . $summaryStartRow;
                $sheet->getStyle($summaryRange)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ]);
            },
        ];
    }
}
