<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\LeadRide;
use App\Mail\VoucherMail;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\SendMessageController;
use Carbon\Carbon;

class SendRideReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reminders:send-ride-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send WhatsApp and email reminders 5 and 1 hour(s) before ride time';

    protected $sendMessageController;

    public function __construct(SendMessageController $sendMessageController)
    {
        parent::__construct();
        $this->sendMessageController = $sendMessageController;
    }

    public function handle()
    {
        Log::info('🚀 SendRideReminders command STARTED at ' . now());
        $now = Carbon::now();

        foreach ([5, 1] as $hoursBefore) {
            $target = $now->copy()->addHours($hoursBefore);

            // Match a 5-minute window to align with cron schedule (runs every 5 minutes)
            // Use -1 to +4 minutes to ensure no rides are missed between consecutive runs
            $from = $target->copy()->subMinutes(1);
            $to = $target->copy()->addMinutes(4);

            Log::info('Searching ride reminders window', ['hours_before' => $hoursBefore, 'from' => $from->toDateTimeString(), 'to' => $to->toDateTimeString()]);
            $rides = LeadRide::whereBetween('from_date', [$from, $to])->with('enquiry.client', 'serviceAddress')->get();
            Log::info('Ride query completed', ['hours_before' => $hoursBefore, 'found' => $rides->count()]);

            foreach ($rides as $ride) {
                try {
                    $lead = $ride->enquiry;
                    $client = $lead->client ?? null;
                    if (!$client) {
                        Log::warning('Ride reminder skipped - no client', ['ride' => $ride->id]);
                        continue;
                    }

                    // Skip rides marked as TBA (Time To Be Announced) - time not confirmed yet
                    if ($ride->is_tba) {
                        Log::info('Ride reminder skipped - ride is TBA (time not confirmed)', ['ride' => $ride->id]);
                        continue;
                    }

                    try {
                        // Check if any voucher has been generated for this lead
                        $hasVoucher = $lead->vouchers()->exists();
                    } catch (\Exception $e) {
                        $hasVoucher = false;
                    }

                    if (!$hasVoucher) {
                        Log::info('Ride reminder skipped - no voucher generated for this lead', ['ride' => $ride->id, 'lead' => $lead->id ?? null]);
                        continue;
                    }

                    // Check if latest followup status is canceled (status = 2)
                    try {
                        $latestFollowup = $lead->latestFollowup()->first();
                        if ($latestFollowup && $latestFollowup->status == 2) {
                            Log::info('Ride reminder skipped - latest followup status is canceled', ['ride' => $ride->id, 'lead' => $lead->id ?? null, 'followup_status' => $latestFollowup->status]);
                            continue;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Could not check latest followup status', ['ride' => $ride->id, 'error' => $e->getMessage()]);
                    }

                    // Check cache keys to ensure we send each reminder only once (no DB migration required)
                    $flagKey = $hoursBefore === 5 ? 'ride_reminder:' . $ride->id . ':5h' : 'ride_reminder:' . $ride->id . ':1h';
                    if (Cache::has($flagKey)) {
                        Log::info('Reminder already sent (cache) for this ride/time', ['ride' => $ride->id, 'flag' => $flagKey]);
                        continue;
                    }

                    $whatsappNumber = $client->alternate_number ?: $client->contact_number;

                    // Build template data according to user's template variables
                    // Template body variables: {{1}} name, {{2}} service, {{3}} time, {{4}} location, {{5}} extra, {{6}} service_date
                    $name = $client->name ?? ($lead->lead_name ?? 'Customer');
                    $service = null;
                    try {
                        $service = $lead->service->name ?? null;
                    } catch (\Exception $e) {
                        $service = $ride->serviceAddress->from_place ?? null;
                    }
                    $service = $service ?? ($ride->serviceAddress->from_place ?? 'your service');
                    $time = Carbon::parse($ride->from_date)->format('H:i');
                    $location = $ride->serviceAddress->from_place ?? $ride->from_place ?? 'pickup point';
                    $extra = 'Please arrive with original ID proof of all passengers.';
                    $service_date = Carbon::parse($ride->from_date)->format('d M, Y');
                    $bodyValues = [$name, $service, $time, $location, $extra, $service_date];

                    // Send WhatsApp via WhatsCRM
                    if (!empty($whatsappNumber)) {
                        // Clean the phone number - remove any existing country code prefix to avoid duplication
                        $cleanedNumber = preg_replace('/^(\+91[-\s]?|91[-\s]?)/', '', $whatsappNumber);
                        $countryCode = $client->whatsapp_country_code ?? '+91';
                        // Ensure country code has + prefix
                        if (!str_starts_with($countryCode, '+')) {
                            $countryCode = '+' . $countryCode;
                        }
                        $payloadNumber = $countryCode . '-' . $cleanedNumber;
                        try {
                            $this->sendMessageController->sendWhatsCrmRideReminderMessage($payloadNumber, $bodyValues);
                            Log::info('✓ WhatsApp ride reminder queued via WhatsCRM', ['ride' => $ride->id, 'hours_before' => $hoursBefore, 'number' => $payloadNumber]);
                        } catch (\Exception $e) {
                            Log::error('✗ Failed to queue WhatsApp ride reminder via WhatsCRM', ['ride' => $ride->id, 'error' => $e->getMessage(), 'number' => $payloadNumber]);
                        }
                    } else {
                        Log::warning('No WhatsApp number for ride reminder', ['ride' => $ride->id]);
                    }

                    // Send email if client email exists
                    $subject = "Ride reminder - Your ride is in {$hoursBefore} hour(s)";
                    $template = 'emails.ride_reminder';
                    $emailData = [
                        'name' => $name,
                        'service' => $service,
                        'time' => $time,
                        'location' => $location,
                        'extra' => $extra,
                        'ride' => $ride,
                    ];

                    if (!empty($client->email)) {
                        try {
                            Mail::to($client->email)->send(new VoucherMail($template, $subject, $emailData));
                            Log::info('Email reminder sent', ['ride' => $ride->id, 'email' => $client->email]);
                        } catch (\Exception $e) {
                            Log::error('Failed to send email reminder', ['ride' => $ride->id, 'email' => $client->email, 'error' => $e->getMessage()]);
                        }
                    }
                    // Mark cache key so the same reminder is not sent again. Set TTL to expire after the ride plus 1 day.
                    try {
                        $rideTime = Carbon::parse($ride->from_date);
                        $secondsUntilExpiry = max(3600, $rideTime->diffInSeconds(Carbon::now()) + 86400); // at least 1 hour, typically until ride + 1 day
                        Cache::put($flagKey, true, Carbon::now()->addSeconds($secondsUntilExpiry));
                    } catch (\Exception $e) {
                        // fallback: set a 48 hour TTL
                        Cache::put($flagKey, true, Carbon::now()->addHours(48));
                    }
                } catch (\Exception $e) {
                    Log::error('Error sending reminder: ' . $e->getMessage(), ['ride' => $ride->id ?? null]);
                }
            }
        }

        return 0;
    }
}
