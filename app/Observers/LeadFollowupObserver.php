<?php

namespace App\Observers;

use App\Models\LeadFollowup;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Support\Facades\Log;

class LeadFollowupObserver
{
    public function created(LeadFollowup $followup): void
    {
        $this->queue($followup, 'followup_created');
    }

    public function updated(LeadFollowup $followup): void
    {
        $this->queue($followup, 'followup_updated');
    }

    private function queue(LeadFollowup $followup, string $reason): void
    {
        if (empty($followup->lead_id)) {
            return;
        }

        try {
            app(SkyrackLeadSyncService::class)->queueLead(
                $followup->lead_id,
                $reason
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to queue Skyrack follow-up lead sync.',
                [
                    'followup_id' => $followup->id,
                    'lead_id' => $followup->lead_id,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
