<?php

namespace App\Console\Commands;

use App\Services\ExpiredActiveRideLeadCancellationService;
use Illuminate\Console\Command;

class AutoCancelExpiredActiveRideLeads extends Command
{
    protected $signature = 'lead:auto-cancel-expired-active-rides';

    protected $description = 'Cancel active leads 15 days after their last ride date';

    public function handle(ExpiredActiveRideLeadCancellationService $service): int
    {
        $result = $service->cancelExpiredActiveRideLeads();

        $this->info('Checked leads: ' . ($result['checked'] ?? 0));
        $this->info('Auto-cancelled leads: ' . ($result['cancelled'] ?? 0));
        $this->info('Skipped leads: ' . ($result['skipped'] ?? 0));

        return Command::SUCCESS;
    }
}
