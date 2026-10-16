<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class TargetIncentiveExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithCustomValueBinder, WithTitle, WithEvents, WithStrictNullComparison
{
    private Collection $rows;
    private bool $formulas;

    public function __construct(Collection $rows, bool $formulas = true) { $this->rows = $rows; $this->formulas = $formulas; }
    public function collection(): Collection
    {
        $total = array_fill(0, count($this->headings()), null);
        $total[0] = 'TOTAL';
        foreach ($this->totalColumns() as $column) $total[$column] = round($this->rows->sum(fn ($row) => (float) ($row[$column] ?? 0)), 2);
        return $this->rows->values()->concat([$total]);
    }

    public function title(): string { return 'Employee Summary'; }
    public function headings(): array { return ['Employee', 'Role', 'Year', 'Month', 'Target', 'Own Sales', 'Sales Basis', 'Products', 'Incentive Sales Basis (overlapping)', 'Own/Individual Multiplier', 'Total Incentive', 'Status', 'Manager Own Sales', 'Team Sales', 'Team Multiplier', 'Own Incentive', 'Team Incentive', 'Individual Incentive', 'Company/Product Incentive', 'Configuration']; }
    protected function totalColumns(): array { return [4, 5, 8, 10, 12, 13, 15, 16, 17, 18]; }
    protected function multiplierColumns(): array { return [9, 14]; }
    protected function widths(): array { return [0 => 28, 1 => 24, 2 => 10, 3 => 10, 7 => 32, 8 => 34, 19 => 24]; }
    protected function wrappedColumns(): array { return [0, 7]; }

    public function registerEvents(): array
    {
        if (!$this->formulas) return [];
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = Coordinate::stringFromColumnIndex(count($this->headings()));
            $lastDataRow = $this->rows->count() + 1;
            $totalRow = $lastDataRow + 1;
            $sheet->freezePane('A2');
            if ($this->rows->isNotEmpty()) $sheet->setAutoFilter('A1:' . $lastColumn . $lastDataRow);
            foreach ($this->headings() as $index => $heading) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($this->widths()[$index] ?? 20);
            }
            $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 12],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '4CAF50']],
                'alignment' => ['horizontal' => 'center', 'vertical' => 'center', 'wrapText' => true],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(36);
            $sheet->getStyle('A1:' . $lastColumn . $totalRow)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '000000']]],
                'alignment' => ['vertical' => 'top'],
            ]);
            foreach ($this->totalColumns() as $index) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle($column . '2:' . $column . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
                if ($this->rows->isNotEmpty()) {
                    $sheet->setCellValueExplicit($column . $totalRow, '=SUM(' . $column . '2:' . $column . $lastDataRow . ')', DataType::TYPE_FORMULA);
                    $sheet->getCell($column . $totalRow)->setCalculatedValue(round($this->rows->sum(fn ($row) => (float) ($row[$index] ?? 0)), 2));
                }
            }
            foreach ($this->multiplierColumns() as $index) {
                $column = Coordinate::stringFromColumnIndex($index + 1);
                $sheet->getStyle($column . '2:' . $column . $totalRow)->getNumberFormat()->setFormatCode('0.00000000');
            }
            if ($this->rows->isNotEmpty()) {
                foreach ($this->wrappedColumns() as $index) {
                    $column = Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->getStyle($column . '2:' . $column . $lastDataRow)->getAlignment()->setWrapText(true);
                }
                foreach ($this->rows->values() as $rowIndex => $row) {
                    $lines = 1;
                    foreach ($this->wrappedColumns() as $index) {
                        $width = max(1, ($this->widths()[$index] ?? 20) * 0.8);
                        $needed = 0;
                        foreach (preg_split('/\R/u', (string) ($row[$index] ?? '')) as $line) $needed += max(1, (int) ceil(mb_strwidth($line) / $width));
                        $lines = max($lines, $needed);
                    }
                    $sheet->getRowDimension($rowIndex + 2)->setRowHeight(18 * $lines);
                }
            }
            $sheet->getStyle('A' . $totalRow . ':' . $lastColumn . $totalRow)->applyFromArray([
                'font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8F0E8']],
            ]);
            if ($this->title() === 'Employee Summary') {
                $sheet->getComment('I' . $totalRow)->getText()->createTextRun('Incentive sales bases can overlap between employees. Unique company sales are totalled once in Sales Detail.');
            }
        }];
    }

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
