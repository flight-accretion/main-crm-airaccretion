<?php

namespace App\Console\Commands;

use App\Services\WhatsAppHistoryRetentionService;
use Illuminate\Console\Command;

class PruneWhatsAppHistory extends Command
{
    protected $signature = 'crm:prune-whatsapp-history {--dry-run : Count expired content without deleting it}';
    protected $description = 'Delete WhatsApp messages and CRM-managed media older than six months.';

    public function handle(WhatsAppHistoryRetentionService $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $summary = $retention->prune($dryRun);
        foreach ($summary as $name => $count) {
            $this->line($name . ': ' . $count . ($dryRun ? ' eligible' : ' processed'));
        }
        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
