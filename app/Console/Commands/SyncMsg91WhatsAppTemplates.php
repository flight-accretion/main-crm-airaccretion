<?php

namespace App\Console\Commands;

use App\Models\Msg91WhatsAppSender;
use App\Services\WhatsApp\Msg91\Msg91TemplateSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncMsg91WhatsAppTemplates extends Command
{
    protected $signature = 'msg91:sync-templates';
    protected $description = 'Refresh MSG91 WhatsApp template approval status and CRM availability.';

    public function handle(Msg91TemplateSyncService $sync): int
    {
        if (!(bool) config('services.msg91_campaigns.template_sync_enabled', false)) {
            return self::SUCCESS;
        }

        Msg91WhatsAppSender::query()->where('status', 'active')->whereNotNull('verified_at')
            ->each(function (Msg91WhatsAppSender $sender) use ($sync) {
                try {
                    $sync->syncSender($sender);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->warn("Could not sync {$sender->integrated_number}.");
                }
            });

        return self::SUCCESS;
    }
}
