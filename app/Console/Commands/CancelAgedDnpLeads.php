<?php

namespace App\Console\Commands;

use App\Services\DnpCancellationService;
use Illuminate\Console\Command;

class CancelAgedDnpLeads extends Command
{
    protected $signature = 'lead:cancel-aged-dnp {--dry-run : List eligible IDs without writing}';
    protected $description = 'Cancel unpaid current DNP leads after 30 complete days';

    public function handle(DnpCancellationService $service): int
    {
        $result = $service->run((bool) $this->option('dry-run'));
        foreach ($result['candidate_ids'] as $id) {
            $this->line($id);
        }
        $this->info('Candidates: '.count($result['candidate_ids']).'; cancelled: '.count($result['cancelled_ids']));
        return self::SUCCESS;
    }
}
