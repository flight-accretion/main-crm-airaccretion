<?php

namespace App\Console\Commands;

use App\Services\Operations\RescheduleWindowService;
use Illuminate\Console\Command;

class ProcessRescheduleWindows extends Command
{
    protected $signature = 'operations:process-reschedule-windows';
    protected $description = 'Create due reschedule call tasks and expired-window assessment tasks without moving money.';

    public function handle(RescheduleWindowService $service): int
    {
        $counts = $service->process();
        $this->info('Call tasks: '.$counts['reminders'].'; assessments: '.$counts['assessments'].'; missing original dates: '.$counts['missing_dates']);
        return self::SUCCESS;
    }
}
