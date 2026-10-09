<?php

namespace App\Exports\Concerns;

trait StreamsLeadGroups
{
    private $leadBatchIds;

    public function generator(): \Generator
    {
        // Keep a lead's complete group in one batch so calculations stay unchanged.
        $leadQuery = $this->exportQuery()->setEagerLoads([])
            ->select('lead_id')->selectRaw('MAX(created_at) AS latest_created_at')
            ->groupBy('lead_id')->orderByDesc('latest_created_at')->orderBy('lead_id');
        try {
            for ($page = 1; ; $page++) {
                $ids = (clone $leadQuery)->forPage($page, 200)->pluck('lead_id');
                if ($ids->isEmpty()) {
                    break;
                }
                $this->leadBatchIds = $ids;
                foreach ($this->collection() as $row) {
                    yield $row;
                }
            }
        } finally {
            $this->leadBatchIds = null;
        }
    }
}
