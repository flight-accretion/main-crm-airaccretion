<?php

namespace App\Observers;

use App\Models\Lead;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function created(Lead $lead): void
    {
        $this->queue($lead, 'created');
    }

    public function updated(Lead $lead): void
    {
        $this->queue($lead, 'updated');
    }

    private function queue(Lead $lead, string $reason): void
    {
        try {
            app(SkyrackLeadSyncService::class)->queueLead(
                $lead,
                $reason
            );
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to queue Skyrack lead sync.',
                [
                    'lead_id' => $lead->id,
                    'reason' => $reason,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
