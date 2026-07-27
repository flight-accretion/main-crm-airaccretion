<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BookingSendUpdate extends Command
{
    protected $signature = 'booking:send-update {--minutes= : Use a short test reminder window in minutes} {--lead= : Limit reminder to a specific lead ID}';

    protected $description = 'Send Booking Reminder';

    public function handle()
    {
        $minutes = $this->option('minutes');
        $leadId = $this->option('lead');

        app(\App\Http\Controllers\RideController::class)
            ->sendRideReminders($minutes, $leadId);

        $this->info('Booking Reminder Completed Successfully');
            return Command::SUCCESS;

    }
}
