<?php

namespace App\Console\Commands;

use App\Services\LeadAllocationService;
use Illuminate\Console\Command;

class ProcessLeadAllocation extends Command
{
    protected $signature = 'lead:process-allocation';
    protected $description = 'Process pending lead allocation queue';

    public function handle(LeadAllocationService $service): int
    {
        $result = $service->processPendingLeads();
        $this->info('Processed allocations: ' . ($result['processed'] ?? 0));

        return Command::SUCCESS;
    }
}
