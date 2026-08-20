<?php

namespace App\Console\Commands;

use App\Services\SkyrackLeadSyncService;
use Illuminate\Console\Command;

class SyncSkyrackLeads extends Command
{
    protected $signature = 'skyrack:sync-leads {--limit=100}';

    protected $description =
        'Push pending CRM lead create/update data to Skyrack.';

    public function handle(SkyrackLeadSyncService $service)
    {
        $result =
            $service->processPending(
                (int) $this->option('limit')
            );

        $this->info(
            'Processed: '
            . $result['processed']
            . '; synced: '
            . $result['synced']
            . '; failed: '
            . $result['failed']
            . '; backfill queued: '
            . $result['backfill_queued']
        );

        return 0;
    }
}
