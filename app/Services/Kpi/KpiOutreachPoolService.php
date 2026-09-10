<?php

namespace App\Services\Kpi;

use App\Models\Client;
use App\Models\KpiOutreachPool;
use App\Services\ActiveLeadService;

class KpiOutreachPoolService
{
    public function __construct(
        private ActiveLeadService $activeLeadService
    ) {}

    public function syncClient(Client $client): void
    {
        $phone = $this->activeLeadService->normalizePhone($client->contact_number);

        if (!$phone || strlen($phone) < 10) {
            return;
        }

        KpiOutreachPool::updateOrCreate(
            [
                'normalized_phone' => $phone,
            ],
            [
                'canonical_client_id' => $client->id,
                'display_name' => $client->name,
                'last_seen_at' => now(),
            ]
        );
    }

    public function syncAll(): int
    {
        $count = 0;

        Client::query()
            ->orderBy('created_at')
            ->chunk(500, function ($clients) use (&$count) {
                foreach ($clients as $client) {
                    $this->syncClient($client);
                    $count++;
                }
            });

        return $count;
    }
}
