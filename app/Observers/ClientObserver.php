<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\SkyrackLeadSyncService;
use Illuminate\Support\Facades\Log;

class ClientObserver
{
    public function updated(Client $client): void
    {
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
}
