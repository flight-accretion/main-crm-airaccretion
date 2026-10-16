<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class HistoricPerformanceWorkbook implements WithMultipleSheets
{
    public function __construct(private Collection $rows, private array $datasets) {}
    public function sheets(): array
    {
        return array_map(fn ($dataset) => new HistoricPerformanceSheet($this->rows,$dataset),$this->datasets);
    }
}
