<?php

namespace App\Console\Commands;

use App\Models\GoogleChatEvent;
use Illuminate\Console\Command;

class PruneGoogleChatTechnicalLogs extends Command
{
    protected $signature = 'google-chat:prune-technical-logs {--months=}';

    protected $description = 'Delete old Google Chat technical event logs after the retention period.';

    public function handle(): int
    {
        $months = (int) (
            $this->option('months')
                ?: config('crm.google_chat_event_retention_months', 2)
        );
        $months = max(1, $months);
        $cutoff = now()->subMonths($months);

        $deleted = GoogleChatEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info(
            "Deleted {$deleted} Google Chat technical event logs older than {$months} month(s)."
        );

        return self::SUCCESS;
    }
}
