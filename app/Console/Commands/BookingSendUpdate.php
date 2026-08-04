<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BookingSendUpdate extends Command
{
    protected $signature = 'booking:send-update {--minutes= : Use a short test reminder window in minutes} {--lead= : Limit reminder to a specific lead ID} {--today : Send a lead-specific reminder for today}';

    protected $description = 'Send Booking Reminder';

    public function handle()
    {
        $minutes = $this->option('minutes');
        $leadId = $this->option('lead');
        $today = (bool) $this->option('today');

        if ($today && !$leadId) {
            $this->error('The --today option requires --lead to avoid sending same-day test reminders to all leads.');

            return Command::FAILURE;
        }

        app(\App\Http\Controllers\RideController::class)
            ->sendRideReminders($minutes, $leadId, $today);

        $this->info('Booking Reminder Completed Successfully');
            return Command::SUCCESS;

    }
}
