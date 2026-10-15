<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class TargetIncentiveExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithCustomValueBinder
{
    private Collection $rows;

    public function __construct(Collection $rows) { $this->rows = $rows; }
    public function collection(): Collection { return $this->rows; }
    public function headings(): array { return ['Employee', 'Role', 'Year', 'Month', 'Target', 'Achieved', 'Sales basis', 'Products', 'Sales amount', 'Own/individual multiplier', 'Incentive', 'Status', 'Manager own sales', 'Team sales', 'Team multiplier', 'Own incentive', 'Team incentive']; }

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value)) {
            // Force names/product text to literal strings; never allow spreadsheet formula execution.
            $unsafe = preg_match('/^(?:\s|\x{FEFF})*[=+@\-]|^[\t\r\n]/u', $value);
            $cell->setValueExplicit($unsafe ? "'".$value : $value, DataType::TYPE_STRING);
            return true;
        }
        return parent::bindValue($cell, $value);
    }
}
