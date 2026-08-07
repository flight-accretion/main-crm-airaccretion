<?php

namespace App\Console\Commands;

use Carbon\Carbon;
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

        if ($minutes === null && !$today) {
            $now = Carbon::now()->setTimezone('Asia/Kolkata');
            $windowStart = Carbon::today('Asia/Kolkata')->setTime(10, 20);
            $windowEnd = Carbon::today('Asia/Kolkata')->setTime(10, 40);

            if (!$now->between($windowStart, $windowEnd)) {
                $this->error('Booking reminder command can only run around 10:30 AM IST unless --minutes or --today is used.');
                app('log')->warning('Booking send update skipped outside scheduled 10:30 AM IST window', [
                    'current_time_ist' => $now->toDateTimeString(),
                ]);
                return Command::SUCCESS;
            }
        }

        app(\App\Http\Controllers\RideController::class)
            ->sendRideReminders($minutes, $leadId, $today);

        $this->info('Booking Reminder Completed Successfully');
            return Command::SUCCESS;

    }
}
