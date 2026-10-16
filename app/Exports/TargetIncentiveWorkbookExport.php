<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

class TargetIncentiveWorkbookExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    private Collection $detail;
    private Collection $summary;

    public function __construct(Collection $detail, Collection $summary)
    {
        $this->detail = $detail;
        $this->summary = $summary;
    }

    public function sheets(): array
    {
        return [new TargetIncentiveSalesExport($this->detail), new TargetIncentiveExport($this->summary)];
    }
}
