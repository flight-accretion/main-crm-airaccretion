<?php

namespace App\Console\Commands;

use App\Models\KpiOutreachAssignment;
use Illuminate\Console\Command;

class PurgeKpiOutreachRemarks extends Command
{
    protected $signature = 'kpi:purge-outreach-remarks';

    protected $description = 'Clear expired free-text KPI outreach remarks after the cooling period.';

    public function handle(): int
    {
        $count = KpiOutreachAssignment::query()
            ->whereNotNull('remark')
            ->whereNotNull('remark_expires_at')
            ->where('remark_expires_at', '<=', now())
            ->update([
                'remark' => null,
            ]);

        $this->info("Purged {$count} expired KPI outreach remarks.");

        return Command::SUCCESS;
    }
}
