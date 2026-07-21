<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\SalesExecutiveNotificationController;

class SendSalesExecutiveUpdate extends Command
{
    /**
     * php artisan sales:send-update --session=Morning
     * php artisan sales:send-update --session=Evening
     */
    protected $signature   = 'sales:send-update {--session=Morning : Morning or Evening}';
    protected $description = 'Send daily sales update (WhatsApp + Email) to all active sales executives';

    public function handle(): void
    {
        $session = $this->option('session');

        if (!in_array($session, ['Morning', 'Evening'])) {
            $this->error('Invalid session. Use --session=Morning or --session=Evening');
            return;
        }

        $this->info("Starting {$session} sales update...");

        $controller = new SalesExecutiveNotificationController();
        $controller->sendDailyUpdates($session);

        $this->info("{$session} sales update complete.");
    }
}