<?php

namespace App\Services\Operations;

use App\Models\Client;
use App\Models\Lead;

class OperationsCallLeadResolver
{
    public function __construct(
        private OperationsLeadEligibilityService $eligibility
    ) {
    }

    public function resolveByPhone(string $phone): ?Lead
    {
        $needle = $this->normalizePhone($phone);

        if ($needle === '') {
            return null;
        }

        $clients = Client::query()
            ->where(function ($query) use ($needle) {
                $query
                    ->where('contact_number', 'like', '%' . $needle)
                    ->orWhere('alternate_number', 'like', '%' . $needle);
            })
            ->get(['id', 'contact_number', 'alternate_number']);

        $clientIds = $clients
            ->filter(function (Client $client) use ($needle) {
                return $this->samePhone($client->contact_number, $needle)
                    || $this->samePhone($client->alternate_number, $needle);
            })
            ->pluck('id')
            ->values();

        if ($clientIds->isEmpty()) {
            return null;
        }

        $query = Lead::query()
            ->with([
                'client',
                'representative',
                'leadFollowups',
                'activeOperationsAssignment.operationsUser',
            ])
            ->whereIn('client_id', $clientIds);

        $this->eligibility->applyEligibleLeadConstraint($query);

        $leads = $query
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (Lead $lead) => $this->eligibility->isEligible($lead))
            ->values();

        if ($leads->count() !== 1) {
            return null;
        }

        return $leads->first();
    }

    private function samePhone(?string $stored, string $needle): bool
    {
        return $stored
            && hash_equals($this->normalizePhone($stored), $needle);
    }

    private function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?: '';

        return strlen($digits) > 10
            ? substr($digits, -10)
            : $digits;
    }
}
