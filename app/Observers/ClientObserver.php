<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\Kpi\KpiOutreachPoolService;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    public function created(Client $client): void
    {
        $this->syncOutreachPool($client);
    }

    public function updated(Client $client): void
    {
        $this->syncOutreachPool($client);

        try {
            $service =
                app(SkyrackLeadSyncService::class);

            foreach ($client->leads()->select('id')->get() as $lead) {
                $service->queueLead(
                    $lead->id,
                    'client_updated'
                );
            }
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to queue Skyrack client lead sync.',
                [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    private function syncOutreachPool(Client $client): void
    {
        try {
            app(KpiOutreachPoolService::class)->syncClient($client);
        } catch (\Throwable $e) {
            Log::warning(
                'Unable to sync client into KPI outreach pool.',
                [
                    'client_id' => $client->id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }
}
