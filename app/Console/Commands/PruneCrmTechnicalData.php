<?php

namespace App\Console\Commands;

use App\Models\RideReminderLog;
use App\Models\WhatsAppAiReplyBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneCrmTechnicalData extends Command
{
    protected $signature = 'crm:prune-technical-data
        {--dry-run : Show counts without deleting records}
        {--whatsapp-ai-days= : Override WhatsApp AI batch retention days}
        {--email-log-days= : Deprecated; email logs are retained for duplicate protection}
        {--reminder-days= : Override reminder log retention days}';

    protected $description = 'Prune old CRM technical records while preserving business records.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $summary = [
            'whatsapp_ai_reply_batches' => $this->pruneWhatsAppAiBatches($dryRun),
            'ride_reminder_logs' => $this->pruneRideReminderLogs($dryRun),
        ];

        foreach ($summary as $table => $count) {
            $this->line($table . ': ' . $count . ($dryRun ? ' eligible' : ' deleted'));
        }
        $this->line('email_lead_logs: retained for duplicate protection');

        return self::SUCCESS;
    }

    private function pruneWhatsAppAiBatches(bool $dryRun): int
    {
        if (!Schema::hasTable('whatsapp_ai_reply_batches')) {
            return 0;
        }

        $days = $this->daysOption(
            'whatsapp-ai-days',
            'crm.whatsapp_ai_batch_retention_days',
            60
        );

        $query = WhatsAppAiReplyBatch::query()
            ->where('created_at', '<', now()->subDays($days))
            ->whereIn('status', ['processed', 'failed', 'skipped', 'expired']);

        return $this->countOrDelete($query, $dryRun);
    }

    private function pruneRideReminderLogs(bool $dryRun): int
    {
        if (!Schema::hasTable('ride_reminder_logs')) {
            return 0;
        }

        $days = $this->daysOption(
            'reminder-days',
            'crm.reminder_log_retention_days',
            180
        );

        $query = RideReminderLog::query()
            ->where('created_at', '<', now()->subDays($days));

        if (Schema::hasColumn('ride_reminder_logs', 'status')) {
            $query->whereIn('status', [
                RideReminderLog::STATUS_SENT,
                RideReminderLog::STATUS_FAILED,
            ]);
        }

        return $this->countOrDelete($query, $dryRun);
    }

    private function countOrDelete($query, bool $dryRun): int
    {
        if ($dryRun) {
            return (int) $query->count();
        }

        return (int) $query->delete();
    }

    private function daysOption(string $option, string $configKey, int $default): int
    {
        $value = $this->option($option);

        return max(1, (int) ($value ?: config($configKey, $default)));
    }
}
