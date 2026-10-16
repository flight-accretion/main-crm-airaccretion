<?php

namespace App\Exports;

class TargetIncentiveSalesExport extends TargetIncentiveExport
{
    public function title(): string { return 'Sales Detail'; }

    public function headings(): array
    {
        return array_merge((new SalesReportExport())->headings(), [
            'Individual Incentive', 'Own Incentive', 'Team Incentive', 'Company/Product Incentive',
            'Total Incentive', 'Incentive Recipients',
        ]);
    }

    protected function totalColumns(): array { return [3, 4, 5, 6, 7, 17, 18, 19, 20, 21]; }
    protected function multiplierColumns(): array { return []; }
    protected function wrappedColumns(): array { return [22]; }
    protected function widths(): array
    {
        return [0 => 30, 1 => 30, 2 => 16, 3 => 16, 4 => 16, 5 => 16, 6 => 16, 7 => 16,
            8 => 20, 9 => 28, 10 => 24, 11 => 24, 12 => 14, 13 => 16, 14 => 14, 15 => 22, 16 => 20,
            20 => 26, 22 => 65];
    }
}
