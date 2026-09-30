<?php

namespace Database\Seeders;

use App\Services\Kpi\KpiTemplateAutomationService;
use Illuminate\Database\Seeder;

class OperationsKpiTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(KpiTemplateAutomationService::class)
            ->syncDepartment('operations', null);
    }
}
