<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SchedulerHeartbeat extends Command
{
    protected $signature = 'crm:scheduler-heartbeat';

    protected $description = 'Record a lightweight scheduler heartbeat timestamp.';

    public function handle(): int
    {
        $path = storage_path('framework/scheduler-heartbeat.txt');

        File::ensureDirectoryExists(dirname($path));
        File::put($path, now()->toIso8601String());

        $this->info('Scheduler heartbeat updated.');

        return self::SUCCESS;
    }
}
