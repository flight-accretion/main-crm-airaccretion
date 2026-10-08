<?php

namespace App\Console\Commands;

use App\Jobs\SendRideReminderNotification;
use App\Models\LeadRide;
use App\Models\RideReminderLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SendRideReminders extends Command
{
    protected $signature = 'reminders:send-ride-reminders';

    protected $description = 'Queue WhatsApp and email reminders 5 and 1 hour(s) before ride time';

    public function handle()
    {
        $this->debugLog('SendRideReminders command started', [
            'started_at' => now()->toDateTimeString(),
        ]);

        $now = Carbon::now();

        foreach ([5, 1] as $hoursBefore) {
            $target = $now->copy()->addHours($hoursBefore);
            $from = $target->copy()->subMinutes(1);
            $to = $target->copy()->addMinutes(4);

            $this->debugLog('Searching ride reminders window', [
                'hours_before' => $hoursBefore,
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
            ]);

            $rides = LeadRide::whereBetween('from_date', [$from, $to])
                ->with('enquiry.client', 'enquiry.vouchers', 'serviceAddress')
                ->get();

            $this->debugLog('Ride query completed', [
                'hours_before' => $hoursBefore,
                'found' => $rides->count(),
            ]);

            foreach ($rides as $ride) {
                try {
                    $lead = $ride->enquiry;
                    $client = $lead->client ?? null;

                    if (!$client) {
                        Log::warning('Ride reminder skipped - no client', ['ride' => $ride->id]);
                        continue;
                    }

                    if ($ride->is_tba) {
                        $this->debugLog('Ride reminder skipped - ride is TBA', ['ride' => $ride->id]);
                        continue;
                    }

                    try {
                        $hasVoucher = $lead->vouchers()->exists();
                    } catch (\Exception $e) {
                        $hasVoucher = false;
                    }

                    if (!$hasVoucher) {
                        $this->debugLog('Ride reminder skipped - no voucher generated for this lead', [
                            'ride' => $ride->id,
                            'lead' => $lead->id ?? null,
                        ]);
                        continue;
                    }

                    try {
                        $latestFollowup = $lead->latestFollowup()->first();
                        if ($latestFollowup && (int) $latestFollowup->status === 2) {
                            $this->debugLog('Ride reminder skipped - latest followup status is canceled', [
                                'ride' => $ride->id,
                                'lead' => $lead->id ?? null,
                                'followup_status' => $latestFollowup->status,
                            ]);
                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not check latest followup status', [
                            'ride' => $ride->id,
                            'error' => $e->getMessage(),
                        ]);
                    }

                    $this->queueReminder(
                        $ride,
                        $hoursBefore,
                        'whatsapp',
                        $client->alternate_number ?: $client->contact_number
                    );
                    $this->queueReminder($ride, $hoursBefore, 'email', $client->email);
                } catch (\Exception $e) {
                    Log::error('Error queueing ride reminder: ' . $e->getMessage(), [
                        'ride' => $ride->id ?? null,
                    ]);
                }
            }
        }

        return 0;
    }

    private function queueReminder(LeadRide $ride, int $hoursBefore, string $channel, ?string $recipient): void
    {
        if (!$recipient) {
            Log::warning('Ride reminder skipped - recipient missing', [
                'ride' => $ride->id,
                'channel' => $channel,
            ]);
            return;
        }

        $log = RideReminderLog::firstOrCreate(
            [
                'ride_id' => $ride->id,
                'hours_before' => $hoursBefore,
                'channel' => $channel,
            ],
            [
                'id' => (string) Str::uuid(),
                'lead_id' => $ride->lead_id,
                'recipient' => $recipient,
                'status' => RideReminderLog::STATUS_PENDING,
            ]
        );

        if ($log->status === RideReminderLog::STATUS_SENT) {
            $this->debugLog('Ride reminder already sent', [
                'ride' => $ride->id,
                'hours_before' => $hoursBefore,
                'channel' => $channel,
            ]);
            return;
        }

        if (!$log->wasRecentlyCreated && $log->status !== RideReminderLog::STATUS_FAILED) {
            return;
        }

        if ($log->status === RideReminderLog::STATUS_FAILED) {
            $log->update([
                'recipient' => $recipient,
                'status' => RideReminderLog::STATUS_PENDING,
                'error_message' => null,
            ]);
        }

        SendRideReminderNotification::dispatch($log->id);
    }

    private function debugLog(string $message, array $context = []): void
    {
        if (config('crm.debug_flow_logs')) {
            Log::info($message, $context);
        }
    }
}
