<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
    // Run ride reminders every 5 minutes to catch upcoming rides 5h and 1h ahead
    $schedule->command('reminders:send-ride-reminders')->everyFiveMinutes()->withoutOverlapping();
    // Run product sync to Airpoints every 15 days at midnight
    $schedule->command('airpoints:sync-products')->cron('0 0 */15 * *')->withoutOverlapping();
    //$schedule->command('reminders:extra-services')->dailyAt('10:00');

     // Sales Executive Daily Update — Morning 9:00 AM IST (3:30 AM UTC)
        $schedule->command('sales:send-update --session=Morning')
                 ->dailyAt('03:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/sales-update-cron.log'));
 
        // Sales Executive Daily Update — Evening 7:00 PM IST (13:30 UTC)
        $schedule->command('sales:send-update --session=Evening')
                 ->dailyAt('13:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/sales-update-cron.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
