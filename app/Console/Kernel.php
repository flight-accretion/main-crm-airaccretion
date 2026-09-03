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
    if (filter_var(config('cron.sales_update_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
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

        // Booking Reminders - run daily at 10:30 AM for 5, 3, and 1 day reminders
        $schedule->command('booking:send-update')
            ->dailyAt('10:30')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/booking-reminders.log'));

        $schedule->command('lead:auto-cancel-expired-active-rides')
            ->dailyAt('00:30')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lead-auto-cancel.log'));

        // Auto allocate queued leads every 5 minutes during office hours
        $schedule->command('lead:process-allocation')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/lead-allocation.log'));

        $schedule->command('ivr:fetch-vi-leads')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/vi-ivr-sync.log'));

         $schedule->command('email:fetch-leads')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(
            storage_path(
                'logs/email-leads.log'
            )
        );
        $schedule
        ->command(
            'call-summary:process-pending'
        )
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(
            storage_path(
                'logs/call-summary-processing.log'
            )
        );

        $schedule
        ->command(
            'call-summary:replay-rejected --days=7 --limit=25'
        )
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(
            storage_path(
                'logs/call-summary-replay.log'
            )
        );

        $schedule
        ->command(
            'whatsapp:process-ai-replies'
            . ' --limit=' . max(
                1,
                (int) config('whatcrm.ai_process_limit', 25)
            )
            . ' --watch=' . max(
                0,
                (float) config('whatcrm.ai_scheduler_watch_seconds', 55)
            )
            . ' --sleep=' . max(
                0.01,
                (float) config('whatcrm.ai_scheduler_sleep_seconds', 0.5)
            )
        )
        ->everyMinute()
        ->withoutOverlapping()
        ->runInBackground()
        ->appendOutputTo(
            storage_path(
                'logs/whatsapp-ai-replies.log'
            )
        );

        $schedule
        ->command(
            'skyrack:sync-leads'
        )
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(
            storage_path(
                'logs/skyrack-lead-sync.log'
            )
        );
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
