<?php

namespace App\Jobs\Operations;

use App\Models\RideAlertNotification;
use App\Services\WhatsApp\WhatsAppTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendOperationRideAlert implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;
    public array $backoff = [300, 900, 3600];
    public int $timeout = 60;

    public function __construct(public string $notificationId)
    {
    }

    public function handle(WhatsAppTemplateService $whatsapp): void
    {
        $notification = DB::transaction(function () {
            $row = RideAlertNotification::query()
                ->where('id', $this->notificationId)
                ->lockForUpdate()
                ->first();

            if (!$row || in_array($row->status, ['sent', 'cancelled'], true)) {
                return null;
            }

            if ($row->due_at && $row->due_at->isFuture()) {
                return null;
            }

            $cutoff = now()->subHours(
                max(
                    1,
                    (int) config('services.operations_ride_alert.max_age_hours', 24)
                )
            );

            if ($row->due_at && $row->due_at->lt($cutoff)) {
                $row->update([
                    'status' => 'cancelled',
                    'failure_reason' => 'Cancelled stale operation ride alert during queue processing.',
                ]);

                return null;
            }

            $row->update([
                'status' => 'processing',
                'attempt_count' => $row->attempt_count + 1,
                'last_attempt_at' => now(),
            ]);

            return $row->fresh();
        });

        if (!$notification) {
            return;
        }

        try {
            $result = $whatsapp->sendTemplate(
                sendTo: $notification->recipient_number,
                templateName: $notification->template_name,
                variables: $notification->template_variables ?: [],
                mediaUri: null
            );

            $notification->update([
                'status' => 'sent',
                'provider_message_id' => $result['provider_message_id'] ?? null,
                'sent_at' => now(),
                'failed_at' => null,
                'failure_reason' => null,
            ]);
        } catch (Throwable $e) {
            $notification->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            report($e);

            throw $e;
        }
    }
}
