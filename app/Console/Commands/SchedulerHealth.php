<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class SchedulerHealth extends Command
{
    protected $signature = 'crm:scheduler-health';

    protected $description = 'Check whether Laravel scheduler heartbeat is fresh.';

    public function handle(): int
    {
        $path = storage_path('framework/scheduler-heartbeat.txt');
        $maxAgeMinutes = max(1, (int) config('crm.scheduler_heartbeat_max_age_minutes', 10));

        if (!File::exists($path)) {
            $this->warn('Scheduler heartbeat file is missing.');
            Log::warning('crm.scheduler.heartbeat_missing');

            return self::FAILURE;
        }

        try {
            $lastHeartbeat = Carbon::parse(trim((string) File::get($path)));
        } catch (\Throwable $e) {
            $this->warn('Scheduler heartbeat file is not readable.');
            Log::warning('crm.scheduler.heartbeat_invalid', [
                'error' => substr($e->getMessage(), 0, 300),
            ]);

            return self::FAILURE;
        }

        $ageMinutes = $lastHeartbeat->diffInMinutes(now());

        $this->line('Last scheduler heartbeat: ' . $lastHeartbeat->toDateTimeString());
        $this->line('Heartbeat age minutes: ' . $ageMinutes);

        if ($ageMinutes > $maxAgeMinutes) {
            $this->warn('Scheduler heartbeat is stale.');
            Log::warning('crm.scheduler.heartbeat_stale', [
                'age_minutes' => $ageMinutes,
                'threshold_minutes' => $maxAgeMinutes,
            ]);

            return self::FAILURE;
        }

        $this->info('Scheduler heartbeat is healthy.');

        return self::SUCCESS;
    }
}
