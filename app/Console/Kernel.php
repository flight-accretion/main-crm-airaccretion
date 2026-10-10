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
    $schedule->command('crm:scheduler-heartbeat')
        ->everyFiveMinutes()
        ->withoutOverlapping();

    // Run ride reminders every 5 minutes to catch upcoming rides 5h and 1h ahead.
    $schedule->command(
        'reminders:send-ride-reminders --limit='
        . max(1, (int) config('crm.ride_reminder_batch_size', 50))
    )->everyFiveMinutes()->withoutOverlapping();
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

        // Booking reminders are queued in batches to avoid one large cron send spike.
        $schedule->command(
                'booking:send-update --limit='
                . max(1, (int) config('crm.booking_reminder_batch_size', 100))
            )
            ->everyFiveMinutes()
            ->between('10:20', '11:55')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/booking-reminders.log'));

        $schedule->command(
                'reviews:send-reminders --limit='
                . max(1, (int) config('crm.review_reminder_batch_size', 100))
            )
            ->everyFiveMinutes()
            ->between('11:00', '12:00')
            ->timezone('Asia/Kolkata')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/review-reminders.log'));

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

        $schedule->command('kpi:sync-outreach-pool')
            ->dailyAt('00:15')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/kpi-outreach-pool.log'));

        $schedule->command('kpi:purge-outreach-remarks')
            ->dailyAt('00:45')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/kpi-outreach-remarks.log'));

        $schedule->command('ivr:fetch-vi-leads')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/vi-ivr-sync.log'));

        $schedule
            ->command('lead-ai:process-pending --limit=2')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(
                storage_path(
                    'logs/lead-ai-scoring.log'
                )
            );

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
            'call-summary:process-pending --limit=25'
        )
        ->everyFiveMinutes()
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
            'whatsapp:handoff-inactive-ai-conversations'
        )
        ->everyMinute()
        ->withoutOverlapping()
        ->appendOutputTo(
            storage_path(
                'logs/whatsapp-ai-handoff.log'
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

        $schedule
            ->command('operations:process-ride-alerts')
            ->hourly()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/operations-ride-alerts.log'));

        $schedule->command('google-chat:ensure-subscription')->hourly()->withoutOverlapping();
        $schedule->command('google-chat:retry-pending --limit=25')
            ->everyFiveMinutes()
            ->withoutOverlapping();
        $schedule->command('google-chat:reconcile')->hourly()->withoutOverlapping();

        $schedule->command('queue:health')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/queue-health.log'));

        $schedule->command('queue:prune-failed --hours=72')
            ->dailyAt('01:15')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/queue-prune-failed.log'));

        $schedule->command('google-chat:prune-technical-logs --months=2')
            ->dailyAt('02:15')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/google-chat-prune.log'));

        $schedule->command('crm:prune-technical-data')
            ->dailyAt('02:35')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-prune-technical-data.log'));

        $schedule->command('crm:prune-files')
            ->dailyAt('02:45')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-prune-files.log'));

        $schedule->command('crm:prune-whatsapp-history')
            ->dailyAt('02:40')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-prune-whatsapp-history.log'));

        $schedule->command('crm:storage-health')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-storage-health.log'));

        $schedule->command('crm:scheduler-health')
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-scheduler-health.log'));

        $schedule->command('crm:production-safety-check')
            ->dailyAt('03:15')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/crm-production-safety.log'));

            if (
        config(
                'crm_backup.enabled',
                false
            )
        ) {

            $schedule
                ->command(
                    'crm:backup-daily'
                )
                ->dailyAt(
                    config(
                        'crm_backup.time',
                        '02:30'
                    )
                )
                ->timezone(
                    config(
                        'crm_backup.timezone',
                        'Asia/Kolkata'
                    )
                )
                ->withoutOverlapping(
                    180
                )
                ->appendOutputTo(
                    storage_path(
                        'logs/crm-backup.log'
                    )
                );
        }

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
