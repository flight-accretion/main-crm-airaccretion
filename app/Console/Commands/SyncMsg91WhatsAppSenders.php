<?php

namespace App\Console\Commands;

use App\Services\WhatsApp\Msg91\Msg91SenderVerificationService;
use Illuminate\Console\Command;

class SyncMsg91WhatsAppSenders extends Command
{
    protected $signature = 'msg91:sync-senders';
    protected $description = 'Read-only MSG91 number reconciliation for CRM sender verification';

    public function handle(Msg91SenderVerificationService $verification): int
    {
        try {
            $result = $verification->syncAll();
            $this->info("Checked {$result['checked']}; verified {$result['verified']}.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}