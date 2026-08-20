<?php

namespace App\Observers;

use App\Models\LeadRide;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Support\Facades\Log;

class LeadRideObserver
{
    public function created(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_created');
    }

    public function updated(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_updated');
    }

    public function deleted(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_deleted');
    }

    private function queue(LeadRide $ride, string $reason): void
    {
        if (empty($ride->lead_id)) {
            return;
        }

        try {
            app(SkyrackLeadSyncService::class)->queueLead(
                $ride->lead_id,
                $reason
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to queue Skyrack ride lead sync.',
                [
                    'ride_id' => $ride->id,
                    'lead_id' => $ride->lead_id,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
