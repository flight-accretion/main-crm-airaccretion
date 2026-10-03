<?php

namespace App\Console\Commands;

use App\Jobs\Operations\SendOperationRideAlert;
use App\Models\RideAlertNotification;
use Illuminate\Console\Command;

class ProcessOperationRideAlerts extends Command
{
    protected $signature = 'operations:process-ride-alerts {--limit=200}';

    protected $description = 'Dispatch due Operations ride alert WhatsApp jobs.';

    public function handle(): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));

        $rows = RideAlertNotification::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('due_at', '<=', now())
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            SendOperationRideAlert::dispatch($row->id);
        }

        $this->info('Ride alert jobs dispatched: ' . $rows->count());

        return self::SUCCESS;
    }
}
