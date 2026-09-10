<?php

namespace App\Console\Commands;

use App\Services\Kpi\KpiOutreachPoolService;
use Illuminate\Console\Command;

class SyncKpiOutreachPool extends Command
{
    protected $signature = 'kpi:sync-outreach-pool';

    protected $description = 'Sync unique customer phone numbers into the KPI outreach pool.';

    public function handle(KpiOutreachPoolService $service): int
    {
        $count = $service->syncAll();

        $this->info("Synced {$count} customers into KPI outreach pool.");

        return Command::SUCCESS;
    }
}
