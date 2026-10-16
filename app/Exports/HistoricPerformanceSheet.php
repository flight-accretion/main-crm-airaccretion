<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class HistoricPerformanceSheet extends DefaultValueBinder implements FromCollection, WithHeadings, WithTitle, WithCustomValueBinder, WithStrictNullComparison, ShouldAutoSize
{
    public function __construct(private Collection $rows, private string $dataset) {}
    public function title(): string { return $this->dataset==='kpi' ? 'KPI History' : 'Target History'; }
    public function headings(): array
    {
        return $this->dataset==='kpi'
            ? ['Employee','Department','Month','KPI score / 5','Weighted score / 5','Score source','Period status']
            : ['Employee','Department','Month','Target (INR)','Achieved (INR)','Achievement (%)','Period status'];
    }
    public function collection(): Collection
    {
        return $this->rows->filter(fn ($r) => $this->dataset==='kpi' || $r['target']!==null)->map(fn ($r) => $this->dataset==='kpi'
            ? [$r['employee'],$r['department'],$r['period'],$r['score'],$r['raw_score'],$r['source'],$r['period_status']]
            : [$r['employee'],$r['department'],$r['period'],$r['target'],$r['achieved'],$r['achievement'],$r['period_status']])->values();
    }
    public function bindValue(Cell $cell, $value)
    {
        if (is_string($value)) {
            $unsafe = preg_match('/^(?:\s|\x{FEFF})*[=+@\-]|^[\t\r\n]/u', $value);
            $cell->setValueExplicit($unsafe ? "'".$value : $value,DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell,$value);
    }
}
