<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingReminderNotification;
use App\Models\LeadRide;
use App\Models\RideReminderLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BookingSendUpdate extends Command
{
    protected $signature = 'booking:send-update
        {--minutes= : Use a short test reminder window in minutes}
        {--lead= : Limit reminder to a specific lead ID}
        {--today : Send a lead-specific reminder for today}
        {--limit= : Maximum booking reminder jobs to queue in this run}';

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
            $windowEnd = Carbon::today('Asia/Kolkata')->setTime(11, 55);

            if (!$now->between($windowStart, $windowEnd)) {
                $this->error('Booking reminder command can only run during the configured IST reminder window unless --minutes or --today is used.');
                app('log')->warning('Booking send update skipped outside scheduled 10:30 AM IST window', [
                    'current_time_ist' => $now->toDateTimeString(),
                ]);
                return Command::SUCCESS;
            }
        }

        if ($minutes === null && !$today && !$leadId) {
            $queued = $this->queueDueBookingReminders(
                max(
                    1,
                    min(
                        500,
                        (int) ($this->option('limit') ?: config('crm.booking_reminder_batch_size', 100))
                    )
                )
            );

            $this->info("Booking reminder jobs queued: {$queued}");

            return Command::SUCCESS;
        }

        app(\App\Http\Controllers\RideController::class)
            ->sendRideReminders($minutes, $leadId, $today);

        $this->info('Booking Reminder Completed Successfully');
            return Command::SUCCESS;

    }

    private function queueDueBookingReminders(int $limit): int
    {
        $queued = 0;
        $hasReminderLogTable = Schema::hasTable('ride_reminder_logs');

        foreach ([5, 3, 1] as $days) {
            if ($queued >= $limit) {
                break;
            }

            $targetDate = Carbon::today('Asia/Kolkata')->addDays($days);
            $remaining = $limit - $queued;

            $rides = LeadRide::query()
                ->select('lead_rides.*')
                ->with('enquiry.client')
                ->whereDate('from_date', $targetDate->toDateString())
                ->whereHas('enquiry.vouchers', function ($query) {
                    $query->where('status', 1);
                })
                ->orderBy('from_date')
                ->limit($remaining * 3)
                ->get()
                ->unique('lead_id')
                ->take($remaining);

            foreach ($rides as $ride) {
                if ($queued >= $limit) {
                    break 2;
                }

                if (!$ride->lead_id) {
                    continue;
                }

                if ($hasReminderLogTable) {
                    $existing = DB::table('ride_reminder_logs')
                        ->where('ride_id', $ride->id)
                        ->where('hours_before', $days)
                        ->where('channel', 'booking')
                        ->first();

                    $pendingIsFresh = $existing
                        && $existing->status === RideReminderLog::STATUS_PENDING
                        && !empty($existing->updated_at)
                        && Carbon::parse($existing->updated_at)->gt(now()->subMinutes(30));

                    if ($existing && (
                        $existing->status === RideReminderLog::STATUS_SENT
                        || $pendingIsFresh
                    )) {
                        continue;
                    }

                    $now = now();

                    DB::table('ride_reminder_logs')->updateOrInsert(
                        [
                            'ride_id' => $ride->id,
                            'hours_before' => $days,
                            'channel' => 'booking',
                        ],
                        [
                            'id' => $existing->id ?? (string) Str::uuid(),
                            'lead_id' => $ride->lead_id,
                            'recipient' => optional($ride->enquiry?->client)->contact_number
                                ?: optional($ride->enquiry?->client)->email,
                            'status' => RideReminderLog::STATUS_PENDING,
                            'error_message' => null,
                            'sent_at' => null,
                            'updated_at' => $now,
                            'created_at' => $existing->created_at ?? $now,
                        ]
                    );
                }

                SendBookingReminderNotification::dispatch(
                    $ride->lead_id,
                    $targetDate->toDateString(),
                    $days
                );

                $queued++;
            }
        }

        Log::info('Booking reminder batch queued', [
            'queued' => $queued,
            'limit' => $limit,
        ]);

        return $queued;
    }
}
