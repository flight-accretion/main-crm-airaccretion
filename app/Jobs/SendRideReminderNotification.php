<?php

namespace App\Jobs;

use App\Http\Controllers\SendMessageController;
use App\Mail\VoucherMail;
use App\Models\LeadRide;
use App\Models\RideReminderLog;
use App\Support\SafeLogContext;
use App\Support\WhatsAppPhoneNumber;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendRideReminderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [60, 300, 900];

    public function __construct(
        private string $rideReminderLogId
    ) {
    }

    public function handle(SendMessageController $sendMessageController): void
    {
        $log = RideReminderLog::find($this->rideReminderLogId);

        if (!$log || $log->status === RideReminderLog::STATUS_SENT) {
            return;
        }

        try {
            $ride = LeadRide::with([
                'enquiry.client',
                'enquiry.representative',
                'enquiry.vouchers',
                'serviceAddress',
            ])->findOrFail($log->ride_id);

            $lead = $ride->enquiry;
            $client = $lead?->client;

            if (!$lead || !$client) {
                throw new \RuntimeException('Ride reminder lead/client missing.');
            }

            $name = $client->name ?? ($lead->lead_name ?? 'Customer');
            $service = $ride->serviceAddress->from_place ?? 'ride';
            $time = Carbon::parse($ride->from_date)->format('H:i');
            $location = $ride->serviceAddress->from_place ?? $ride->from_place ?? 'pickup point';
            $extra = 'Please arrive with original ID proof of all passengers.';
            $serviceDate = Carbon::parse($ride->from_date)->format('d M, Y');
            $bodyValues = [$name, $service, $time, $location, $extra, $serviceDate];

            if ($log->channel === 'whatsapp') {
                $recipient = $log->recipient ?: ($client->alternate_number ?: $client->contact_number);

                if (!$recipient) {
                    throw new \RuntimeException('Ride reminder WhatsApp recipient missing.');
                }

                $sendMessageController->sendWhatsCrmRideReminderMessage(
                    WhatsAppPhoneNumber::e164(
                        $recipient,
                        $client->whatsapp_country_code
                            ?: $client->contact_country_code
                            ?: null
                    ),
                    $bodyValues
                );
            } elseif ($log->channel === 'email') {
                $recipient = $log->recipient ?: $client->email;

                if (!$recipient) {
                    throw new \RuntimeException('Ride reminder email recipient missing.');
                }

                Mail::to($recipient)->send(new VoucherMail(
                    'emails.ride_reminder',
                    "Ride reminder - Your ride is in {$log->hours_before} hour(s)",
                    [
                        'name' => $name,
                        'service' => $service,
                        'time' => $time,
                        'location' => $location,
                        'extra' => $extra,
                        'ride' => $ride,
                    ]
                ));
            } else {
                throw new \RuntimeException('Unsupported ride reminder channel.');
            }

            $log->update([
                'status' => RideReminderLog::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => RideReminderLog::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 1000),
            ]);

            Log::error('ride_reminder_notification.failed', SafeLogContext::exception($e, [
                'ride_reminder_log_id' => $log->id,
                'ride_id' => $log->ride_id,
                'channel' => $log->channel,
            ]));

            throw $e;
        }
    }
}
