<?php

namespace App\Jobs;

use App\Http\Controllers\RideController;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBookingReminderNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [300, 900, 1800];

    public function __construct(
        private string $leadId,
        private string $targetDate,
        private int $days
    ) {
    }

    public function handle(RideController $rideController): void
    {
        $rideController->sendReminder(
            Carbon::parse($this->targetDate),
            $this->days,
            null,
            $this->leadId
        );
    }
}
