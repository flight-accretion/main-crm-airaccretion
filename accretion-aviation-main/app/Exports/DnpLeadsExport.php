<?php

namespace App\Exports;

use App\Models\Lead;
use App\Models\Service;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DnpLeadsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithEvents
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $service = Service::where('service', 'Call Not Connected')->first();
        if (!$service) {
            return collect();
        }

        $query = Lead::with(['client', 'representative'])
            ->whereRaw("replace(trim(both '\"' from service_ids::text), '\\', '') LIKE ?", ['%' . $service->id . '%']);

        // apply same filters as controller
        if (!empty($this->filters['from_date'])) {
            $fromDate = Carbon::parse($this->filters['from_date'])->startOfDay();
            $query->whereHas('rideSegments', function ($q) use ($fromDate) {
                $q->where('from_date', '>=', $fromDate);
            });
        }
        if (!empty($this->filters['to_date'])) {
            $toDate = Carbon::parse($this->filters['to_date'])->endOfDay();
            $query->whereHas('rideSegments', function ($q) use ($toDate) {
                $q->where('to_date', '<=', $toDate);
            });
        }
        if (!empty($this->filters['name'])) {
            $query->whereHas('client', function ($q) {
                $q->where('name', 'like', '%' . ($this->filters['name'] ?? '') . '%');
            });
        }
        if (!empty($this->filters['email'])) {
            $query->whereHas('client', function ($q) {
                $q->where('email', 'like', '%' . ($this->filters['email'] ?? '') . '%');
            });
        }
        if (!empty($this->filters['phone'])) {
            $query->whereHas('client', function ($q) {
                $q->where('contact_number', 'like', '%' . ($this->filters['phone'] ?? '') . '%');
            });
        }

        // apply representative (staff) filter if provided
        if (!empty($this->filters['representative_user_id'])) {
            $rep = $this->filters['representative_user_id'];
            $query->where('representative_user_id', $rep);
        }

        // allow explicit ids (from datatable) to restrict export to specific visible rows
        if (!empty($this->filters['ids'])) {
            $ids = is_array($this->filters['ids']) ? $this->filters['ids'] : explode(',', $this->filters['ids']);
            $query->whereIn('id', $ids);
        }

        $dnpLeads = $query->get();

        // transform to collection of arrays
        $export = $dnpLeads->map(function ($enquiry, $index) {
            $serviceNames = $enquiry->service_names ?? [];
            $serviceDisplay = is_array($serviceNames) ? implode(', ', $serviceNames) : $serviceNames;

            $nextFollowUp = 'N/A';
            if ($enquiry->latest_followup && $enquiry->latest_followup->next_followup_date) {
                try {
                    $nextFollowUp = Carbon::parse($enquiry->latest_followup->next_followup_date)->format('d-m-Y H:i');
                } catch (\Exception $e) {
                    $nextFollowUp = $enquiry->latest_followup->next_followup_date;
                }
            }

            return [
                'sno' => $index + 1,
                'name' => $enquiry->client->name ?? 'N/A',
                'email' => $enquiry->client->email ?? 'N/A',
                'phone' => ($enquiry->client->contact_number ?? '') !== '' ? preg_replace('/\D/', '', $enquiry->client->contact_number) : 'N/A',
                'next_follow_up' => $nextFollowUp,
                'created_date' => $enquiry->created_at ? Carbon::parse($enquiry->created_at)->format('d-m-Y') : '',
                'assigned' => $enquiry->representative->name ?? 'N/A',
                'services' => $serviceDisplay,
            ];
        });

        return $export;
    }

    public function headings(): array
    {
        return [
            'S. No',
            'Client Name',
            'Email',
            'Phone',
            'Next Follow Up',
            'Created Date',
            'Assigned',
            'Services',
        ];
    }

    public function map($row): array
    {
        return [
            $row['sno'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['next_follow_up'],
            $row['created_date'],
            $row['assigned'],
            Str::limit($row['services'], 100),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle('A1:H1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => [
                        'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F81BD']
                    ],
                ]);
                // Set column widths for readability
                $sheet->getColumnDimension('A')->setWidth(8);   // S. No
                $sheet->getColumnDimension('B')->setWidth(30);  // Client Name
                $sheet->getColumnDimension('C')->setWidth(30);  // Email
                $sheet->getColumnDimension('D')->setWidth(20);  // Phone
                $sheet->getColumnDimension('E')->setWidth(22);  // Next Follow Up
                $sheet->getColumnDimension('F')->setWidth(15);  // Created Date
                $sheet->getColumnDimension('G')->setWidth(20);  // Assigned
                $sheet->getColumnDimension('H')->setWidth(40);  // Services

                // Alignments
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D2:D' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('F2:F' . $highestRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                try {
                    // Ensure phone column is treated as text to avoid scientific notation in Excel
                    $sheet->getStyle('D2:D' . $highestRow)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
                    // Also set each cell explicitly as string to be safe
                    for ($r = 2; $r <= $highestRow; $r++) {
                        $cell = 'D' . $r;
                        $val = $sheet->getCell($cell)->getValue();
                        if ($val !== null) {
                            $sheet->setCellValueExplicit($cell, (string)$val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        }
                    }
                } catch (\Exception $e) {
                    // ignore if styling fails
                }
                $sheet->setAutoFilter('A1:H' . $highestRow);
                $sheet->freezePane('A2');
            },
        ];
    }
}
