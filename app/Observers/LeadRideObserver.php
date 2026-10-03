<?php

namespace App\Observers;

use App\Models\LeadRide;
use App\Services\Operations\RideAlertService;
use App\Services\SkyrackLeadSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class LeadRideObserver
{
    public function created(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_created');
        $this->syncRideAlert($ride);
    }

    public function updated(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_updated');
        $previousRideDate = $ride->wasChanged('from_date')
            && $ride->getOriginal('from_date')
                ? Carbon::parse($ride->getOriginal('from_date'), 'Asia/Kolkata')
                    ->startOfDay()
                : null;

        $this->syncRideAlert($ride, $previousRideDate);
    }

    public function deleted(LeadRide $ride): void
    {
        $this->queue($ride, 'ride_deleted');
        $this->syncRideAlert($ride);
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

    private function syncRideAlert(
        LeadRide $ride,
        ?Carbon $previousRideDate = null
    ): void {
        if (empty($ride->lead_id)) {
            return;
        }

        try {
            $lead = $ride->lead()->first();

            if (!$lead) {
                return;
            }

            app(RideAlertService::class)->syncForLead(
                $lead,
                $previousRideDate
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to sync Operations ride alert.',
                [
                    'ride_id' => $ride->id,
                    'lead_id' => $ride->lead_id,
                    'error' => $e->getMessage(),
                ]
            );

            report($e);
        }
    }
}
