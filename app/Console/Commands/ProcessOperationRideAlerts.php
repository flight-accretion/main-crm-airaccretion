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
        $limit = max(1, min(50, (int) $this->option('limit')));
        $cutoff = now()->subHours(
            max(
                1,
                (int) config('services.operations_ride_alert.max_age_hours', 24)
            )
        );

        RideAlertNotification::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('due_at', '<', $cutoff)
            ->update([
                'status' => 'cancelled',
                'failure_reason' => 'Cancelled stale operation ride alert before queue dispatch.',
            ]);

        $rows = RideAlertNotification::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('due_at', '<=', now())
            ->where('due_at', '>=', $cutoff)
            ->orderBy('due_at')
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $queued = RideAlertNotification::query()
                ->where('id', $row->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'queued',
                ]);

            if ($queued) {
                SendOperationRideAlert::dispatch($row->id)
                    ->onQueue(
                        config('services.operations_ride_alert.queue', 'default')
                    );
            }
        }

        $this->info('Ride alert jobs dispatched: ' . $rows->count());

        return self::SUCCESS;
    }
}
