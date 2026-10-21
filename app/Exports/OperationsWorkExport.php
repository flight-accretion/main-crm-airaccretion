<?php

namespace App\Exports;

use App\Models\Service;
use App\Services\Operations\OperationsWorkSummaryService;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class OperationsWorkExport extends StringValueBinder implements FromQuery, WithMapping, WithHeadings, WithCustomValueBinder
{
    private array $filters;
    private array $types;
    private string $status;
    private ?string $type;
    private $services;

    public function __construct(array $filters, array $types, string $status, ?string $type)
    {
        $this->filters = $filters; $this->types = $types; $this->status = $status; $this->type = $type;
        $this->services = Service::pluck('service', 'id');
    }

    public function query()
    {
        return app(OperationsWorkSummaryService::class)->detailQuery($this->filters, $this->types, $this->status, $this->type);
    }

    public function headings(): array
    {
        return ['Client', 'Phone', 'Type', 'Service', 'Service Date', 'Assigned To', 'Completed By', 'Opened', 'Completed', 'Duration / Age (hours)', 'Status'];
    }

    public function map($row): array
    {
        $hours = $row->opened_at && (!$row->completed_at || $row->completed_at->gte($row->opened_at))
            ? round($row->opened_at->diffInSeconds($row->completed_at ?: now()) / 3600, 2) : '';
        $values = [$row->lead?->client?->name, $row->lead?->client?->contact_number,
            ucwords(str_replace('_', ' ', $row->type)),
            collect($row->lead?->service_ids_array ?? [])->map(fn ($id) => $this->services[$id] ?? null)->filter()->implode(', '),
            $row->lead?->rideSegments->pluck('from_date')->filter()->map(fn ($date) => $date->format('Y-m-d H:i'))->implode('; '),
            $row->assignee?->name, $row->completed_at ? $row->completedBy?->name : '',
            $row->opened_at?->format('Y-m-d H:i'), $row->completed_at?->format('Y-m-d H:i'), $hours, $row->status];
        return array_map(fn ($value) => is_string($value) && preg_match('/^[\s]*[=+@-]/', $value) ? "'".$value : $value, $values);
    }
}
